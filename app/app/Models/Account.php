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
    const INST_KLAR        = 'klar';
    const INST_MERCADOPAGO = 'mercadopago';
    const INST_NU          = 'nu';
    const INST_REVOLUT     = 'revolut';
    const INST_AMEX        = 'amex';
    const INST_EFECTIVO    = 'efectivo';
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

    public const INSTITUTION_LABELS = [
        self::INST_BANAMEX     => 'Banamex',
        self::INST_KLAR        => 'Klar',
        self::INST_MERCADOPAGO => 'MercadoPago',
        self::INST_NU          => 'Nu',
        self::INST_REVOLUT     => 'Revolut',
        self::INST_AMEX        => 'American Express',
        self::INST_EFECTIVO    => 'Efectivo',
        self::INST_OTHER       => 'Otra',
    ];

    public function institutionLabel(): string
    {
        return self::INSTITUTION_LABELS[$this->institution] ?? ucfirst((string) $this->institution);
    }

    /**
     * "Institución · Nombre" para selects y botones; si el nombre ya
     * menciona la institución, se muestra solo el nombre.
     */
    public function displayLabel(): string
    {
        $label = $this->institutionLabel();

        if (mb_stripos($this->name, $label) !== false) {
            return $this->name;
        }

        return $label . ' · ' . $this->name;
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
