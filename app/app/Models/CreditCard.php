<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditCard extends Model
{
    protected $fillable = [
        'account_id', 'statement_day', 'payment_day',
        'credit_limit', 'apr', 'min_payment_pct',
    ];

    protected $casts = [
        'credit_limit'    => 'decimal:2',
        'apr'             => 'decimal:2',
        'min_payment_pct' => 'decimal:2',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
