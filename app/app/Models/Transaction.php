<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'date', 'type', 'amount', 'account_id', 'category_id',
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
