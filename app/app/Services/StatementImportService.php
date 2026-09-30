<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Category;
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
            . (self::INSTITUTION_HINTS[$account->institution] ?? '');

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
                $rows[] = $this->buildRow($account, $item);
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
     * Devuelve ['created' => n, 'linked' => n].
     */
    public function store(Account $account, string $token, array $input): array
    {
        $created = 0;
        $linked  = 0;

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

            Transaction::create([
                'date'        => $date,
                'type'        => $type === 'income' ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE,
                'amount'      => $amount,
                'account_id'  => $account->id,
                'category_id' => ($row['category_id'] ?? null) ?: null,
                'description' => $description ?: 'Cargo',
            ]);

            $created++;
        }

        Cache::forget($this->key($account, $token));

        if ($created + $linked > 0) {
            AuditLog::record('statement_import', ['account_id' => $account->id, 'created' => $created, 'linked' => $linked]);
        }

        return ['created' => $created, 'linked' => $linked];
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function buildRow(Account $account, array $item): array
    {
        $date = $this->sanitizeDate($item['date']);
        $type = $item['type'];

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
