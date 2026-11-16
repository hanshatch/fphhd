<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\RecurringCharge;
use App\Models\Source;
use App\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Importa movimientos a una cuenta a partir de capturas de pantalla del
 * estado de cuenta: la visión extrae los renglones, Hans solo revisa y
 * asigna categoría, y aquí se crean las transacciones.
 */
class StatementImportService
{
    private const MAX_ITEMS_PER_IMAGE = 40;
    private const TTL_MINUTES         = 60;

    // Mismo monto a más de 3 días: se avisa como «posible duplicado». Sin fecha
    // legible se revisan las últimas semanas, que es de donde suele venir el renglón.
    private const POSSIBLE_DAYS = 20;
    private const UNDATED_DAYS  = 45;

    /**
     * Pistas por institución para el modelo de visión. Cada banco pinta
     * distinto su lista; aquí se describe lo que hay que leer y lo que
     * hay que ignorar en cada uno.
     */
    private const INSTITUTION_HINTS = [
        'revolut' => 'App Revolut: fondo negro, agrupado por día ("hoy", "8 de septiembre"). '
            . 'Montos en verde con "+" son abonos; en blanco con "-" son cargos. '
            . 'Los montos grandes se muestran sin decimales y con coma de miles: "+$20,000" es 20000.00, "+$700" es 700.00. '
            . 'Si un renglón muestra un segundo monto en otra moneda (R$, US$, €) es una transferencia enviada al extranjero: '
            . 'usa el monto en pesos (el primero) y describe "Transferencia a <nombre>". '
            . '"Transferencia interbancaria" con "+" es un depósito recibido. '
            . 'Ignora el saldo actual de arriba y los totales por día a la derecha de la fecha.',
        'amex' => 'Estado de cuenta American Express web: renglones con fecha "dd-mmm.", comercio en mayúsculas y monto a la derecha. '
            . 'Todos son cargos salvo que digan "PAGO" o "ABONO". Ignora casillas, etiquetas "SM" y flechas.',
        'nu' => 'App Nu: lista morada/blanca agrupada por fecha; "Compra" o nombre del comercio son cargos, "Depósito"/"Rendimientos" abonos.',
        'banamex' => 'App Banamex «Tus movimientos»: cada movimiento ocupa dos líneas: arriba el concepto en mayúsculas y el monto; '
            . 'abajo su fecha ("03 Nov") y el estatus ("Aprobada", "En proceso"). Todos los renglones tienen fecha. '
            . 'Monto en verde con "+" es abono; en negro sin signo es cargo. '
            . 'Ignora el contador ("36 movimientos"), los títulos "Esta semana"/"Anteriores" y los renglones cortados por el borde. '
            . 'En un estado de cuenta en PDF: columnas fecha, concepto, retiro y depósito; retiros son cargos y depósitos abonos.',
        'klar' => 'App Klar: lista agrupada por fecha ("Ayer", "Más en este mes", "octubre"). Monto con "-" es cargo; monto en verde sin signo es abono. '
            . 'Los renglones "En proceso" y "Diferible" son cargos válidos. IGNORA los renglones con el monto tachado o que digan '
            . '"Saldo insuficiente" o "Rechazada": no se cobraron. Ignora el saldo disponible, "Fondo de Rendimientos" e "Historial de ganancias" de la portada. '
            . 'En la pantalla "Inversión": "Pago semanal de intereses" es abono; "Monto Invertido" y "Retiro anticipado" con "-" son cargos.',
        'mercadopago' => 'App Mercado Pago: "Pagaste" o nombre de comercio con "-" es cargo; "Recibiste", "Rendimientos" o "+" es abono.',
    ];

    public const TYPES_TRANSFER = ['transfer_out', 'transfer_in'];

    /** Empate de duplicados durante analyze(): ids ya tomados, apartados por folio y renglón en curso */
    private array $claimed = [];

    private array $reserved = [];

    private int $current = -1;

    public function __construct(
        private VisionExpenseService $vision,
        private MerchantMemoryService $memory,
        private TransactionMatchService $matcher,
        private RecurringMatchService $recurring,
        private RecurringChargeService $charges,
        private TransferReconcileService $reconcile,
    ) {}

    public function isConfigured(): bool
    {
        return $this->vision->isConfigured();
    }

    /**
     * Analiza las imágenes y guarda el borrador en caché bajo un token.
     * Devuelve el token, o null si ninguna imagen se pudo analizar.
     */
    public function analyze(Account $account, array $images): ?string
    {
        $expenseCats = Category::active()->ofKind(Category::KIND_EXPENSE)->orderBy('name')->pluck('name')->all();
        $incomeCats  = Category::active()->ofKind(Category::KIND_INCOME)->orderBy('name')->pluck('name')->all();

        $rows     = [];
        $found    = [];
        $screens  = [];
        $analyzed = false;
        $apps     = array_values(array_diff(array_keys(Account::INSTITUTION_LABELS), [Account::INST_EFECTIVO, Account::INST_OTHER]));

        $context = 'La cuenta es «' . $account->name . '» (' . $account->institutionLabel() . ', ' . $account->type . '). '
            . $this->accountHint($account);

        /** @var UploadedFile $image */
        foreach ($images as $image) {
            $items = $this->vision->parseCharges(
                base64_encode($image->get()),
                $image->getMimeType() ?: 'image/jpeg',
                $expenseCats,
                $incomeCats,
                trim($context),
                self::MAX_ITEMS_PER_IMAGE,
                $apps,
            );

            $screens[] = $this->vision->lastScreen;

            if ($items === null) {
                continue;
            }

            $analyzed = true;

            foreach ($items as $item) {
                $pocket = $this->pocketRule($account, $item) ?? $this->cashRule($account, $item);

                if ($pocket === false) {
                    continue; // movimiento de otra subcuenta (débito u otra cajita)
                }

                // Renglón repetido entre capturas que se traslapan
                foreach ($found as [$seen]) {
                    if ($this->sameLine($seen, $item)) {
                        continue 2;
                    }
                }

                $found[] = [$item, $pocket];
            }
        }

        if (! $analyzed) {
            return null;
        }

        $found = $this->markInterest($account, $found);

        // Cada movimiento ya registrado empata con UN solo renglón: dos recargas
        // de $200 el mismo día no pueden darse ambas por registradas con una sola.
        $this->claimed  = [];
        $this->reserved = $this->reserveByFolio($account, $found);

        foreach ($found as $i => [$item, $pocket]) {
            $this->current = $i;
            $rows[]        = $this->buildRow($account, $item, $pocket);
        }

        $rows = $this->attachRecurring($account, $rows);

        $token = Str::random(32);

        Cache::put($this->key($account, $token), $rows, now()->addMinutes(self::TTL_MINUTES));
        Cache::put($this->key($account, $token) . ':screen', $this->screenCheck($account, $screens), now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /**
     * ¿La captura es de esta cuenta? Compara el banco que se reconoce en la
     * imagen y el título de la pantalla contra lo aprendido de cada cuenta.
     *
     * @return array{warning: ?string, title: ?string}
     */
    private function screenCheck(Account $account, array $screens): array
    {
        $titles = collect($screens)->pluck('title')->filter()->map(fn ($t) => $this->titleKey($t))->unique()->values();
        $title  = $titles->count() === 1 ? $titles->first() : null;
        $labels = Account::INSTITUTION_LABELS;

        // 1) El banco de la captura no es el de la cuenta
        $app = collect($screens)->pluck('app')->filter()->first(fn ($a) => $a !== $account->institution);

        if ($app && ! in_array($account->institution, [Account::INST_OTHER, Account::INST_EFECTIVO], true)) {
            return ['title' => $title, 'warning' => 'La captura parece de ' . $labels[$app] . ', pero la estás importando a «' . $account->displayLabel() . '».'];
        }

        // 2) Esa pantalla es la que siempre se ha usado para OTRA cuenta
        // (en Nu la misma captura sirve para débito y cajitas: el título no distingue)
        if ($account->institution === Account::INST_NU) {
            return ['title' => null, 'warning' => null];
        }

        foreach ($titles as $seen) {
            if ($account->statement_title && $this->titleKey($account->statement_title) === $seen) {
                continue;
            }

            $owner = Account::where('is_active', true)->where('id', '<>', $account->id)->whereNotNull('statement_title')->get()
                ->first(fn (Account $a) => $this->titleKey($a->statement_title) === $seen);

            if ($owner) {
                return ['title' => $title, 'warning' => 'La pantalla «' . $owner->statement_title . '» es la que usas para «' . $owner->displayLabel()
                    . '», pero la estás importando a «' . $account->displayLabel() . '».'];
            }
        }

        return ['title' => $title, 'warning' => null];
    }

    private function titleKey(string $title): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower(Str::ascii($title))));
    }

    /** Aviso de «esta captura parece de otra cuenta», o null */
    public function screenWarning(Account $account, string $token): ?string
    {
        return Cache::get($this->key($account, $token) . ':screen')['warning'] ?? null;
    }

    /** Borrador guardado, o null si expiró */
    public function draft(Account $account, string $token): ?array
    {
        return Cache::get($this->key($account, $token));
    }

    /**
     * Registra los renglones seleccionados. Cada renglón del formulario:
     * ['include', 'date', 'description', 'amount', 'type', 'category_id',
     *  'counterparty_account_id', 'twin_id']
     *
     * type: expense | income | transfer_out | transfer_in. En transferencias,
     * si viene twin_id y ese movimiento es el otro lado, se CONVIERTE en la
     * transferencia en vez de crear uno nuevo (no se duplica).
     *
     * Con recurring_id aplica el cargo recurrente; con adjust_tx_id corrige el
     * movimiento que ya se había aplicado con el monto estimado.
     *
     * Una transferencia sin counterparty_account_id queda «por conciliar»: al
     * final se intenta unir sola con su otro lado si ya está cargado.
     *
     * Devuelve ['created', 'linked', 'applied', 'adjusted', 'pending', 'reconciled'].
     */
    public function store(Account $account, string $token, array $input): array
    {
        $created  = 0;
        $linked   = 0;
        $applied  = 0;
        $adjusted = 0;
        $pending  = 0;

        foreach ($input as $row) {
            if (empty($row['include'])) {
                continue;
            }

            $amount = parse_money($row['amount'] ?? null);

            if ($amount === null || bccomp($amount, '0.00', 2) <= 0) {
                continue;
            }

            $type = $row['type'] ?? 'expense';
            $date = $this->readDate($row['date'] ?? null);

            if ($date === null) {
                continue; // sin fecha no se registra: la de hoy escondería un duplicado
            }

            $description = Str::limit(trim((string) ($row['description'] ?? '')), 500, '');

            if (in_array($type, self::TYPES_TRANSFER, true)) {
                $other = Account::find((int) ($row['counterparty_account_id'] ?? 0));

                if ($other?->id === $account->id) {
                    continue;
                }

                // No se sabe a cuál cuenta fue: queda por conciliar hasta cargar el otro lado
                if ($other === null) {
                    Transaction::create([
                        'date'             => $date,
                        'type'             => Transaction::TYPE_TRANSFER,
                        'amount'           => $amount,
                        'account_id'       => $account->id,
                        'transfer_pending' => $type === 'transfer_out' ? Transaction::PENDING_OUT : Transaction::PENDING_IN,
                        'description'      => $description ?: 'Transferencia por conciliar',
                    ]);

                    $pending++;

                    continue;
                }

                [$from, $to] = $type === 'transfer_out' ? [$account, $other] : [$other, $account];

                $twin = ! empty($row['twin_id']) ? Transaction::find((int) $row['twin_id']) : null;

                // Solo se convierte si de verdad es el otro lado: otra cuenta, mismo monto y
                // cargo/abono suelto, o la transferencia que esa cuenta dejó por conciliar
                if ($twin && $twin->account_id === $other->id && bccomp((string) $twin->amount, $amount, 2) === 0) {
                    if ($twin->isPendingTransfer()
                        && $twin->transfer_pending === ($type === 'transfer_out' ? Transaction::PENDING_IN : Transaction::PENDING_OUT)) {
                        $this->reconcile->assign($twin, $account);
                        $linked++;

                        continue;
                    }

                    if ($twin->type === ($type === 'transfer_out' ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE)) {
                        $this->matcher->convertToTransfer($twin, $from, $to);
                        $linked++;

                        continue;
                    }
                }

                Transaction::create([
                    'date'                    => $date,
                    'type'                    => Transaction::TYPE_TRANSFER,
                    'amount'                  => $amount,
                    'account_id'              => $from->id,
                    'counterparty_account_id' => $to->id,
                    'description'             => $description ?: 'Transferencia ' . $from->displayLabel() . ' → ' . $to->displayLabel(),
                ]);

                $created++;

                continue;
            }

            $categoryId = ($row['category_id'] ?? null) ?: null;

            // Cargo recurrente pendiente: aplicarlo con el monto y fecha reales
            if (! empty($row['recurring_id'])) {
                $charge = RecurringCharge::find((int) $row['recurring_id']);

                if ($charge && $charge->account_id === $account->id && $charge->is_active && $charge->type === $type) {
                    // Aprende cómo lo escribe el banco para reconocerlo solo la próxima vez
                    if (blank($charge->statement_text) && ($key = $this->memory->key($description))) {
                        $charge->update(['statement_text' => $key]);
                    }

                    $tx = $this->charges->applyCharge($charge, $amount, $date);

                    if ($categoryId && (int) $tx->category_id !== (int) $categoryId) {
                        $tx->update(['category_id' => $categoryId]);
                    }

                    $applied++;

                    continue;
                }
            }

            // Recurrente ya aplicado con el estimado: corregir ese movimiento
            if (! empty($row['adjust_tx_id'])) {
                $tx = Transaction::where('account_id', $account->id)->find((int) $row['adjust_tx_id']);

                if ($tx && $tx->type === $type) {
                    $tx->update(array_filter([
                        'amount'      => $amount,
                        'date'        => $date,
                        'category_id' => $categoryId,
                    ]));

                    $adjusted++;

                    continue;
                }
            }

            Transaction::create([
                'date'        => $date,
                'type'        => match ($type) {
                    'income'   => Transaction::TYPE_INCOME,
                    'interest' => Transaction::TYPE_INTEREST,
                    default    => Transaction::TYPE_EXPENSE,
                },
                'amount'      => $amount,
                'account_id'  => $account->id,
                'category_id' => $type === 'interest' ? null : $categoryId,
                'source_id'   => $type === 'income' ? (($row['source_id'] ?? null) ?: null) : null,
                'description' => $description ?: 'Cargo',
            ]);

            $created++;
        }

        $screen = Cache::pull($this->key($account, $token) . ':screen');
        Cache::forget($this->key($account, $token));

        // Aprende con qué pantalla se importa esta cuenta, para avisar si luego se sube a otra
        if ($created + $linked + $applied + $adjusted + $pending > 0 && ! empty($screen['title']) && empty($screen['warning'])
            && blank($account->statement_title)
            && ! Account::where('id', '<>', $account->id)->whereNotNull('statement_title')->get()
                ->contains(fn (Account $a) => $this->titleKey($a->statement_title) === $screen['title'])) {
            $account->update(['statement_title' => Str::limit($screen['title'], 120, '')]);
        }

        if ($created + $linked + $applied + $adjusted + $pending > 0) {
            AuditLog::record('statement_import', [
                'account_id' => $account->id, 'created' => $created, 'linked' => $linked,
                'applied' => $applied, 'adjusted' => $adjusted, 'pending' => $pending,
            ]);
        }

        // Con lo recién cargado puede haberse completado algún par pendiente
        $reconciled = $this->reconcile->autoMatch();

        return [
            'created' => $created, 'linked' => $linked, 'applied' => $applied, 'adjusted' => $adjusted,
            'pending' => max(0, $pending - $reconciled), 'reconciled' => $reconciled,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function buildRow(Account $account, array $item, ?array $pocket = null): array
    {
        $date = $this->readDate($this->anchorYear($item['date']));
        $type = $item['type'];

        // Movimiento entre la cuenta y una cajita del mismo banco: es transferencia
        if ($pocket !== null) {
            $description = Str::limit(Str::ucfirst($item['description']), 500, '');

            return [
                'date'                    => $date ?? '',
                'undated'                 => $date === null,
                'description'             => $description,
                'amount'                  => $item['amount'],
                'type'                    => $pocket['type'],
                'category_id'             => null,
                'category_source'         => null,
                'duplicate'               => $this->duplicate($account, $pocket['type'] === 'transfer_in' ? 'in' : 'out', $item['amount'], $date, $description),
                'counterparty_account_id' => $pocket['other']?->id,
                'twin'                    => null,
            ];
        }

        $description = Str::limit(Str::ucfirst($item['description']), 500, '');

        // 1) Lo que Hans ya decidió para este comercio manda; 2) si no, la pista del modelo
        $categoryId = $this->memory->suggest($description, $type);
        $categorySource = $categoryId ? 'memory' : null;

        if (! $categoryId && $item['category']) {
            $categoryId = Category::active()
                ->ofKind($type === 'income' ? Category::KIND_INCOME : Category::KIND_EXPENSE)
                ->where('name', $item['category'])
                ->value('id');
            $categorySource = $categoryId ? 'model' : null;
        }

        $source = $type === 'income' ? Source::find($this->memory->suggestSource($description)) : null;

        $direction = in_array($type, ['income', 'interest'], true) ? 'in' : 'out';

        // Los rendimientos no llevan categoría ni fuente: son tipo «interés»
        if ($type === 'interest') {
            $categoryId = $categorySource = $source = null;
        }

        $duplicate = $this->duplicate($account, $direction, $item['amount'], $date, $description);

        // ¿La otra mitad ya está en otra cuenta tuya? Entonces es transferencia interna
        // (como cargo/abono suelto, o como transferencia que quedó por conciliar)
        $twin = $duplicate || $date === null ? null
            : ($this->reconcile->pendingTwin($account, $direction, $item['amount'], $date)
                ?? $this->matcher->transferTwin($account, $direction, $item['amount'], $date));

        if ($twin) {
            $type           = $direction === 'out' ? 'transfer_out' : 'transfer_in';
            $categoryId     = null;
            $categorySource = null;
        }

        // Retiros entre la inversión/ahorro y la cuenta de débito del mismo banco son
        // transferencias: «Retiro anticipado» sale de la inversión y entra a débito.
        $sibling = null;

        if (! $twin && preg_match('/^\s*retiro\b/iu', $description) && $account->institution !== 'other') {
            $invested = [Account::TYPE_SAVINGS, Account::TYPE_INVESTMENT];
            $leaving  = $type === 'expense' && in_array($account->type, $invested, true);
            $arriving = $type === 'income' && $account->type === Account::TYPE_DEBIT;

            $others = $leaving || $arriving
                ? Account::where('is_active', true)->where('institution', $account->institution)->where('id', '<>', $account->id)
                    ->whereIn('type', $leaving ? [Account::TYPE_DEBIT] : $invested)->get()
                : collect();

            if ($others->isNotEmpty()) {
                $sibling        = $others->count() === 1 ? $others->first() : null;
                $type           = $leaving ? 'transfer_out' : 'transfer_in';
                $categoryId     = null;
                $categorySource = null;
                $source         = null;
            }
        }

        return [
            'date'                    => $date ?? '',
            'undated'                 => $date === null,
            'description'             => $description,
            'amount'                  => $item['amount'],
            'type'                    => $type,
            'category_id'             => $categoryId,
            'category_source'         => $categorySource,
            'source_id'               => $twin ? null : $source?->id,
            'source_name'             => $twin ? null : $source?->name,
            'duplicate'               => $duplicate,
            'counterparty_account_id' => $twin?->account_id ?? $sibling?->id,
            'twin'                    => $twin ? $this->matcher->describe($twin, $account) : null,
        ];
    }

    // ── Subcuentas (cajitas de Nu) ────────────────────────────────────

    private const POCKET_IN  = '/agregaste dinero a tu cajita/';
    private const POCKET_OUT = '/retiraste dinero de tu cajita/';

    /** Pista para el lector según la institución y si la cuenta es la de débito o una cajita */
    private function accountHint(Account $account): string
    {
        if ($account->institution !== 'nu') {
            return self::INSTITUTION_HINTS[$account->institution] ?? '';
        }

        $format = 'En esos renglones la description debe ser exactamente "Agregaste dinero a tu Cajita · <subtítulo>" '
            . 'o "Retiraste dinero de tu Cajita · <subtítulo>", donde el subtítulo es el nombre de la cajita sin emoji (ej. "Cajita Turbo"). ';

        if ($this->isPocket($account)) {
            return 'App Nu: las capturas mezclan la cuenta de débito y las cajitas. Esta cuenta es la cajita «' . $account->name . '». '
                . 'Extrae SOLO los renglones "Agregaste dinero a tu Cajita" y "Retiraste dinero de tu Cajita" (y rendimientos de la cajita si aparecen). '
                . 'Ignora compras, recargas, transferencias enviadas o recibidas y compensaciones SPEI: son de la cuenta de débito. ' . $format;
        }

        return 'App Nu (cuenta de débito): compras, recargas y "Transferencia enviada" son cargos; "Transferencia recibida", '
            . '"Compensación de retraso SPEI" y depósitos son abonos. "Agregaste dinero a tu Cajita" es dinero que SALE hacia la cajita '
            . 'y "Retiraste dinero de tu Cajita" es dinero que ENTRA, aunque la app muestre ambos con "+". ' . $format;
    }

    /**
     * Reglas para cajitas de Nu:
     *  - null:  renglón normal
     *  - false: no pertenece a esta cuenta (descartar)
     *  - ['type' => transfer_in|transfer_out, 'other' => ?Account]: transferencia débito ↔ cajita
     */
    private function pocketRule(Account $account, array $item): array|false|null
    {
        if ($account->institution !== 'nu') {
            return null;
        }

        $desc  = Str::ascii(mb_strtolower((string) $item['description']));
        $isIn  = (bool) preg_match(self::POCKET_IN, $desc);   // a la cajita
        $isOut = (bool) preg_match(self::POCKET_OUT, $desc);  // de la cajita

        $siblings = Account::where('is_active', true)->where('institution', 'nu')->get();
        $pockets  = $siblings->filter(fn (Account $a) => $this->isPocket($a));
        $debits   = $siblings->where('type', Account::TYPE_DEBIT);
        $named    = $pockets->first(fn (Account $p) => $this->mentions($desc, $p->name));

        if ($this->isPocket($account)) {
            if (! $isIn && ! $isOut) {
                // Los rendimientos de la cajita sí son de aquí; lo demás es de la cuenta de débito
                return preg_match('/rendimiento|interes/', $desc) ? null : false;
            }

            if ($named && $named->id !== $account->id) {
                return false; // es de otra cajita
            }

            return ['type' => $isIn ? 'transfer_in' : 'transfer_out', 'other' => $debits->count() === 1 ? $debits->first() : null];
        }

        if (! $isIn && ! $isOut) {
            return null;
        }

        $target = $named ?? ($pockets->count() === 1 ? $pockets->first() : null);

        return ['type' => $isIn ? 'transfer_out' : 'transfer_in', 'other' => $target];
    }

    /** Retiro de cajero en una cuenta de banco/tarjeta: transferencia a tu efectivo */
    private function cashRule(Account $account, array $item): ?array
    {
        if (($item['type'] ?? 'expense') !== 'expense' || $account->type === Account::TYPE_CASH
            || ! Account::isCashWithdrawal($item['description'] ?? '')) {
            return null;
        }

        $cash = Account::cashAccount();

        return $cash ? ['type' => 'transfer_out', 'other' => $cash] : null;
    }

    private function isPocket(Account $account): bool
    {
        return in_array($account->type, [Account::TYPE_SAVINGS, Account::TYPE_INVESTMENT], true);
    }

    private function mentions(string $haystack, string $name): bool
    {
        $letters = fn (string $t) => trim(preg_replace('/[^a-z]+/', ' ', Str::ascii(mb_strtolower($t))));

        return $letters($name) !== '' && str_contains(' ' . $letters($haystack) . ' ', ' ' . $letters($name) . ' ');
    }

    /** Marca los renglones que corresponden a un cargo recurrente (pendiente o ya aplicado) */
    private function attachRecurring(Account $account, array $rows): array
    {
        $candidates = collect($rows)
            ->filter(fn ($r) => ! $r['duplicate'] && in_array($r['type'], ['expense', 'income'], true))
            ->map(fn ($r) => ['type' => $r['type'], 'amount' => $r['amount'], 'date' => $r['date'], 'description' => $r['description']])
            ->all();

        foreach ($this->recurring->assign($account, $candidates) as $i => $match) {
            $charge = $match['charge'];
            $tx     = $match['transaction'];

            $rows[$i]['recurring'] = [
                'mode'           => $match['mode'],
                'id'             => $charge->id,
                'name'           => $charge->name,
                'expected'       => (string) ($tx ? $tx->amount : $charge->amount),
                'due'            => ($tx ? $tx->date : $charge->next_application_date)->translatedFormat('j M Y'),
                'transaction_id' => $tx?->id,
            ];

            // El renglón no traía fecha: toma la del vencimiento del cargo (editable en la revisión)
            if ($rows[$i]['undated']) {
                $rows[$i]['date']    = min($charge->next_application_date->toDateString(), now()->toDateString());
                $rows[$i]['undated'] = false;
            }

            if ($charge->category_id) {
                $rows[$i]['category_id']     = $charge->category_id;
                $rows[$i]['category_source'] = 'recurring';
            }
        }

        return $rows;
    }

    /**
     * Movimiento ya registrado con el mismo monto: a ±3 días es «ya existe»;
     * más lejos, o si no se leyó la fecha, es «posible duplicado». Ambos
     * llegan desmarcados a la revisión.
     */
    private function duplicate(Account $account, string $direction, string $amount, ?string $date, string $description = ''): ?array
    {
        $tx = $this->matches($account, $direction, $amount, $date)
            ->reject(fn (Transaction $t) => in_array($t->id, $this->claimed, true))
            // El que comparte folio/teléfono con este renglón es suyo; los apartados para otro renglón no se tocan
            ->first(fn (Transaction $t) => ($this->reserved[$t->id] ?? $this->current) === $this->current);

        if (! $tx) {
            return null;
        }

        $this->claimed[] = $tx->id;

        $sure = $date !== null && abs(Carbon::parse($date)->diffInDays($tx->date)) <= 3;

        return $this->matcher->describe($tx, $account) + ['possible' => ! $sure];
    }

    /**
     * Rendimientos: un abono que dice «intereses» o «rendimientos» en una cuenta
     * que no es de crédito se propone como interés, no como ingreso. Si la app
     * lista además «Monto invertido» por el mismo monto y día (Klar: el interés
     * pasa de la inversión a su «Fondo de Rendimientos»), ese renglón se descarta:
     * el fondo es parte de la misma cuenta de inversión, el dinero no ha salido.
     * Sale cuando se retira a la cuenta de débito, y eso sí es transferencia.
     */
    private function markInterest(Account $account, array $found): array
    {
        if ($account->isCredit()) {
            return $found;
        }

        foreach ($found as $i => [$item, $pocket]) {
            // «Retiro - Fondo de Rendimientos» no es interés: es dinero que llega de la inversión
            if ($pocket !== null || $item['type'] !== 'income' || preg_match('/^\s*retiro\b/iu', $item['description'])
                || ! preg_match('/\b(inter[eé]s(es)?|rendimientos?)\b/iu', $item['description'])) {
                continue;
            }

            $found[$i][0]['type'] = 'interest';

            foreach ($found as $j => [$other, $otherPocket]) {
                if ($otherPocket === null && $other['type'] === 'expense'
                    && $other['amount'] === $item['amount'] && $other['date'] === $item['date']
                    && preg_match('/monto invertido|reinver/iu', $other['description'])) {
                    unset($found[$j]);

                    break;
                }
            }
        }

        return array_values($found);
    }

    /** Movimientos ya registrados con ese monto: primero los de ±3 días, luego los más lejanos */
    private function matches(Account $account, string $direction, string $amount, ?string $date)
    {
        $anchor = Carbon::parse($date ?? now()->toDateString());

        return $this->matcher
            ->candidates($account, $direction, $amount, $anchor->toDateString(), $date ? self::POSSIBLE_DAYS : self::UNDATED_DAYS)
            ->sortBy(fn (Transaction $t) => abs($anchor->diffInDays($t->date)))
            ->values();
    }

    /**
     * Aparta cada movimiento registrado para el renglón que trae su mismo folio
     * o teléfono, para que otro renglón del mismo monto no se lo quede.
     *
     * @return array<int, int> id de transacción → índice del renglón
     */
    private function reserveByFolio(Account $account, array $found): array
    {
        $reserved = [];

        foreach ($found as $i => [$item, $pocket]) {
            $date      = $this->readDate($this->anchorYear($item['date']));
            $direction = $pocket !== null
                ? ($pocket['type'] === 'transfer_in' ? 'in' : 'out')
                : (in_array($item['type'], ['income', 'interest'], true) ? 'in' : 'out');

            $own = $this->matches($account, $direction, $item['amount'], $date)
                ->first(fn (Transaction $t) => ! isset($reserved[$t->id])
                    && array_intersect($this->folios((string) $t->description), $this->folios($item['description'])) !== []);

            if ($own) {
                $reserved[$own->id] = $i;
            }
        }

        return $reserved;
    }

    /** Números largos de una descripción: folios, referencias, teléfonos */
    private function folios(string $text): array
    {
        preg_match_all('/\d{5,}/', $text, $m);

        return array_unique($m[0]);
    }

    /** ¿Dos lecturas del mismo renglón (capturas traslapadas)? Folios distintos = movimientos distintos */
    private function sameLine(array $a, array $b): bool
    {
        if ($a['date'] !== $b['date'] || $a['amount'] !== $b['amount'] || $a['type'] !== $b['type']) {
            return false;
        }

        $x = mb_strtolower($a['description']);
        $y = mb_strtolower($b['description']);

        if ($x === $y) {
            return true;
        }

        $fa = $this->folios($x);
        $fb = $this->folios($y);
        sort($fa);
        sort($fb);

        if ($fa !== $fb) {
            return false;
        }

        similar_text($x, $y, $percent);

        return $percent >= 85;
    }

    /**
     * Las capturas casi nunca imprimen el año ("03 Nov") y el lector a veces lo
     * inventa. El día y el mes sí son confiables: si la fecha queda en el futuro
     * o a más de 11 meses, se toma la ocurrencia más reciente de ese día y mes.
     */
    private function anchorYear(?string $date): ?string
    {
        try {
            $parsed = $date ? Carbon::parse($date)->startOfDay() : null;
        } catch (\Throwable) {
            return $date;
        }

        if ($parsed === null || ($parsed->lte(now()) && $parsed->gte(now()->subMonths(11)))) {
            return $date;
        }

        $anchored = $parsed->copy()->year(now()->year);

        return ($anchored->isFuture() ? $anchored->subYear() : $anchored)->toDateString();
    }

    /** Fecha válida (ni futura ni de hace más de 2 años) o null; nunca se inventa la de hoy */
    private function readDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            $parsed = Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }

        if ($parsed->isFuture() || $parsed->lt(now()->subYears(2))) {
            return null;
        }

        return $parsed->toDateString();
    }

    private function key(Account $account, string $token): string
    {
        return "statement_import:{$account->id}:{$token}";
    }
}
