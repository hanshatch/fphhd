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

    public function __construct(private VisionExpenseService $vision) {}

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

        /** @var UploadedFile $image */
        foreach ($images as $image) {
            $items = $this->vision->parseCharges(
                base64_encode($image->get()),
                $image->getMimeType() ?: 'image/jpeg',
                $expenseCats,
                $incomeCats,
                'La cuenta es «' . $account->name . '» (' . $account->type . ').',
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
     * Crea las transacciones seleccionadas. $input viene del formulario de
     * revisión: [['include' => 1, 'date', 'description', 'amount', 'type', 'category_id'], ...]
     * Devuelve cuántas se crearon.
     */
    public function store(Account $account, string $token, array $input): int
    {
        $created = 0;

        foreach ($input as $row) {
            if (empty($row['include'])) {
                continue;
            }

            $amount = parse_money($row['amount'] ?? null);

            if ($amount === null || bccomp($amount, '0.00', 2) <= 0) {
                continue;
            }

            Transaction::create([
                'date'        => $this->sanitizeDate($row['date'] ?? null),
                'type'        => ($row['type'] ?? 'expense') === 'income' ? 'income' : 'expense',
                'amount'      => $amount,
                'account_id'  => $account->id,
                'category_id' => ($row['category_id'] ?? null) ?: null,
                'description' => Str::limit(trim((string) ($row['description'] ?? '')), 500, '') ?: 'Cargo',
            ]);

            $created++;
        }

        Cache::forget($this->key($account, $token));

        if ($created > 0) {
            AuditLog::record('statement_import', ['account_id' => $account->id, 'count' => $created]);
        }

        return $created;
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function buildRow(Account $account, array $item): array
    {
        $date = $this->sanitizeDate($item['date']);
        $type = $item['type'];

        $categoryId = null;

        if ($item['category']) {
            $categoryId = Category::active()
                ->ofKind($type === 'income' ? Category::KIND_INCOME : Category::KIND_EXPENSE)
                ->where('name', $item['category'])
                ->value('id');
        }

        return [
            'date'        => $date,
            'description' => Str::limit(Str::ucfirst($item['description']), 500, ''),
            'amount'      => $item['amount'],
            'type'        => $type,
            'category_id' => $categoryId,
            'duplicate'   => $this->findDuplicate($account, $item['amount'], $date),
        ];
    }

    /** Movimiento existente en la misma cuenta con el mismo monto ±3 días */
    private function findDuplicate(Account $account, string $amount, string $date): ?array
    {
        $d = Carbon::parse($date);

        $existing = Transaction::where('account_id', $account->id)
            ->where('amount', $amount)
            ->whereDate('date', '>=', $d->copy()->subDays(3)->toDateString())
            ->whereDate('date', '<=', $d->copy()->addDays(3)->toDateString())
            ->orderByDesc('id')
            ->first();

        if ($existing === null) {
            return null;
        }

        return [
            'id'          => $existing->id,
            'description' => $existing->description ?: 'Sin descripción',
            'date'        => $existing->date->translatedFormat('j M Y'),
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
