<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    protected $fillable = [
        'name', 'type', 'institution', 'currency',
        'initial_balance', 'is_active', 'color', 'icon', 'logo_path',
        'invest_apr', 'notes',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'invest_apr'      => 'decimal:2',
        'is_active'       => 'boolean',
    ];

    const TYPE_DEBIT      = 'debit';
    const TYPE_CREDIT     = 'credit';
    const TYPE_SAVINGS    = 'savings';
    const TYPE_INVESTMENT = 'investment';
    const TYPE_CASH       = 'cash';

    const INST_BANAMEX     = 'banamex';
    const INST_MERCADOPAGO = 'mercadopago';
    const INST_NU          = 'nu';
    const INST_REVOLUT     = 'revolut';
    const INST_AMEX        = 'amex';
    const INST_OTHER       = 'other';

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(Transaction::class, 'counterparty_account_id');
    }

    public function creditCard(): HasOne
    {
        return $this->hasOne(CreditCard::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset('storage/' . $this->logo_path) : null;
    }

    public function isCredit(): bool
    {
        return $this->type === self::TYPE_CREDIT;
    }

    public function isInvestment(): bool
    {
        return in_array($this->type, [self::TYPE_SAVINGS, self::TYPE_INVESTMENT]);
    }
}
