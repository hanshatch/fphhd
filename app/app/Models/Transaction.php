<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'date', 'position', 'type', 'amount', 'account_id', 'category_id',
        'source_id', 'counterparty_account_id', 'description', 'tags',
    ];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
        'tags'   => 'array',
    ];

    const TYPE_INCOME   = 'income';
    const TYPE_EXPENSE  = 'expense';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_INTEREST = 'interest';
    const TYPE_FEE      = 'fee';

    /**
     * Una transferencia sin descripción se etiqueta sola, para que la lista
     * no muestre filas en blanco. Vale desde cualquier vía de alta.
     */
    protected static function booted(): void
    {
        static::saving(function (self $transaction) {
            if ($transaction->type === self::TYPE_TRANSFER && blank($transaction->description)) {
                $transaction->description = 'Transferencia entre cuentas';
            }
        });

        // Memoria de comercios: cada movimiento con categoría enseña la regla comercio → categoría
        static::saved(function (self $transaction) {
            if ($transaction->category_id && ($transaction->wasRecentlyCreated || $transaction->wasChanged(['category_id', 'description']))) {
                app(\App\Services\MerchantMemoryService::class)->learn($transaction);
            }
        });
    }

    /**
     * `date` es un día, no un instante: se guarda como Y-m-d para que los
     * rangos por fecha funcionen igual en MySQL (DATE) y en SQLite (texto).
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : \Illuminate\Support\Carbon::parse($value)->toDateString(),
        );
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function counterpartyAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counterparty_account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function isTransfer(): bool
    {
        return $this->type === self::TYPE_TRANSFER;
    }

    public function isIncome(): bool
    {
        return in_array($this->type, [self::TYPE_INCOME, self::TYPE_INTEREST]);
    }

    public function isExpense(): bool
    {
        return in_array($this->type, [self::TYPE_EXPENSE, self::TYPE_FEE]);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeInPeriod(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function scopeExcludingTransfers(Builder $query): Builder
    {
        return $query->where('type', '!=', self::TYPE_TRANSFER);
    }
}
