<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\RecurringCharge;
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
        'banamex' => 'App o estado de cuenta Banamex: columnas fecha, concepto, retiro y depósito; retiros son cargos y depósitos abonos.',
        'mercadopago' => 'App Mercado Pago: "Pagaste" o nombre de comercio con "-" es cargo; "Recibiste", "Rendimientos" o "+" es abono.',
    ];

    public const TYPES_TRANSFER = ['transfer_out', 'transfer_in'];

    public function __construct(
        private VisionExpenseService $vision,
        private MerchantMemoryService $memory,
        private TransactionMatchService $matcher,
        private RecurringMatchService $recurring,
        private RecurringChargeService $charges,
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
        $analyzed = false;

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
            );

            if ($items === null) {
                continue;
            }

            $analyzed = true;

            foreach ($items as $item) {
                $pocket = $this->pocketRule($account, $item);

                if ($pocket === false) {
                    continue; // movimiento de otra subcuenta (débito u otra cajita)
                }

                $rows[] = $this->buildRow($account, $item, $pocket);
            }
        }

        if (! $analyzed) {
            return null;
        }

        // Quitar renglones repetidos entre capturas que se traslapan
        $rows = collect($rows)
            ->unique(fn ($r) => $r['date'] . '|' . $r['amount'] . '|' . mb_strtolower($r['description']))
            ->values()
            ->all();

        $rows = $this->attachRecurring($account, $rows);

        $token = Str::random(32);

        Cache::put($this->key($account, $token), $rows, now()->addMinutes(self::TTL_MINUTES));

        return $token;
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
     * Devuelve ['created', 'linked', 'applied', 'adjusted'].
     */
    public function store(Account $account, string $token, array $input): array
    {
        $created  = 0;
        $linked   = 0;
        $applied  = 0;
        $adjusted = 0;

        foreach ($input as $row) {
            if (empty($row['include'])) {
                continue;
            }

            $amount = parse_money($row['amount'] ?? null);

            if ($amount === null || bccomp($amount, '0.00', 2) <= 0) {
                continue;
            }

            $type        = $row['type'] ?? 'expense';
            $date        = $this->sanitizeDate($row['date'] ?? null);
            $description = Str::limit(trim((string) ($row['description'] ?? '')), 500, '');

            if (in_array($type, self::TYPES_TRANSFER, true)) {
                $other = Account::find((int) ($row['counterparty_account_id'] ?? 0));

                if ($other === null || $other->id === $account->id) {
                    continue;
                }

                [$from, $to] = $type === 'transfer_out' ? [$account, $other] : [$other, $account];

                $twin = ! empty($row['twin_id']) ? Transaction::find((int) $row['twin_id']) : null;

                // Solo se convierte si de verdad es el otro lado: otra cuenta, cargo/abono suelto y mismo monto
                if ($twin
                    && $twin->account_id === $other->id
                    && $twin->type === ($type === 'transfer_out' ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE)
                    && bccomp((string) $twin->amount, $amount, 2) === 0) {
                    $this->matcher->convertToTransfer($twin, $from, $to);
                    $linked++;

                    continue;
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
                'type'        => $type === 'income' ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE,
                'amount'      => $amount,
                'account_id'  => $account->id,
                'category_id' => $categoryId,
                'description' => $description ?: 'Cargo',
            ]);

            $created++;
        }

        Cache::forget($this->key($account, $token));

        if ($created + $linked + $applied + $adjusted > 0) {
            AuditLog::record('statement_import', [
                'account_id' => $account->id, 'created' => $created, 'linked' => $linked,
                'applied' => $applied, 'adjusted' => $adjusted,
            ]);
        }

        return ['created' => $created, 'linked' => $linked, 'applied' => $applied, 'adjusted' => $adjusted];
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function buildRow(Account $account, array $item, ?array $pocket = null): array
    {
        $date = $this->sanitizeDate($item['date']);
        $type = $item['type'];

        // Movimiento entre la cuenta y una cajita del mismo banco: es transferencia
        if ($pocket !== null) {
            $description = Str::limit(Str::ucfirst($item['description']), 500, '');
            $existing    = $this->matcher->existing($account, $pocket['type'] === 'transfer_in' ? 'in' : 'out', $item['amount'], $date);

            return [
                'date'                    => $date,
                'description'             => $description,
                'amount'                  => $item['amount'],
                'type'                    => $pocket['type'],
                'category_id'             => null,
                'category_source'         => null,
                'duplicate'               => $existing ? $this->matcher->describe($existing, $account) : null,
                'counterparty_account_id' => $pocket['other']?->id,
                'twin'                    => null,
            ];
        }

        $description = Str::limit(Str::ucfirst($item['description']), 500, '');

        // 1) Lo que Hans ya decidió para este comercio manda; 2) si no, la pista del modelo
        $categoryId = $this->memory->suggest($description, $type);
        $source     = $categoryId ? 'memory' : null;

        if (! $categoryId && $item['category']) {
            $categoryId = Category::active()
                ->ofKind($type === 'income' ? Category::KIND_INCOME : Category::KIND_EXPENSE)
                ->where('name', $item['category'])
                ->value('id');
            $source = $categoryId ? 'model' : null;
        }

        $direction = $type === 'income' ? 'in' : 'out';
        $existing  = $this->matcher->existing($account, $direction, $item['amount'], $date);

        // ¿La otra mitad ya está en otra cuenta tuya? Entonces es transferencia interna
        $twin = $existing ? null : $this->matcher->transferTwin($account, $direction, $item['amount'], $date);

        if ($twin) {
            $type       = $direction === 'out' ? 'transfer_out' : 'transfer_in';
            $categoryId = null;
            $source     = null;
        }

        return [
            'date'                    => $date,
            'description'             => $description,
            'amount'                  => $item['amount'],
            'type'                    => $type,
            'category_id'             => $categoryId,
            'category_source'         => $source,
            'duplicate'               => $existing ? $this->matcher->describe($existing, $account) : null,
            'counterparty_account_id' => $twin?->account_id,
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

            if ($charge->category_id) {
                $rows[$i]['category_id']     = $charge->category_id;
                $rows[$i]['category_source'] = 'recurring';
            }
        }

        return $rows;
    }

    private function sanitizeDate(?string $date): string
    {
        if (! $date) {
            return now()->toDateString();
        }

        try {
            $parsed = Carbon::parse($date);
        } catch (\Throwable) {
            return now()->toDateString();
        }

        if ($parsed->isFuture() || $parsed->lt(now()->subYears(2))) {
            return now()->toDateString();
        }

        return $parsed->toDateString();
    }

    private function key(Account $account, string $token): string
    {
        return "statement_import:{$account->id}:{$token}";
    }
}
