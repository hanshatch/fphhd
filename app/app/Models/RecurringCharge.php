<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class RecurringCharge extends Model
{
    protected $fillable = [
        'name', 'description', 'account_id', 'category_id',
        'type', 'amount', 'day_of_month', 'start_date', 'end_date',
        'is_msi', 'total_installments', 'applied_installments', 'original_amount',
        'next_application_date', 'is_active', 'notes',
    ];

    protected $casts = [
        'amount'                 => 'decimal:2',
        'original_amount'        => 'decimal:2',
        'start_date'             => 'date',
        'end_date'               => 'date',
        'next_application_date'  => 'date',
        'is_msi'                 => 'boolean',
        'is_active'              => 'boolean',
        'total_installments'     => 'integer',
        'applied_installments'   => 'integer',
    ];

    // ── Relaciones ────────────────────────────────────────────────────

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeDueOn(Builder $q, string $date): Builder
    {
        return $q->where('next_application_date', '<=', $date)->where('is_active', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** Progreso MSI 0-100 */
    public function progressPercent(): int
    {
        if (! $this->total_installments) return 0;
        return (int) round(($this->applied_installments / $this->total_installments) * 100);
    }

    /** Cuotas restantes */
    public function remainingInstallments(): ?int
    {
        if (! $this->total_installments) return null;
        return $this->total_installments - $this->applied_installments;
    }

    /** Saldo pendiente (MSI) */
    public function pendingAmount(): string
    {
        if (! $this->total_installments) return '0.00';
        $remaining = $this->total_installments - $this->applied_installments;
        return bcmul((string) $this->amount, (string) $remaining, 2);
    }

    /** ¿Se completó? */
    public function isCompleted(): bool
    {
        if (! $this->is_active) return true;
        if ($this->total_installments && $this->applied_installments >= $this->total_installments) return true;
        if ($this->end_date && $this->end_date->isPast()) return true;
        return false;
    }

    /**
     * Calcula la siguiente fecha de aplicación después de una dada.
     * Maneja correctamente meses con menos de `day_of_month` días (ej. día 31 en febrero).
     */
    public function calculateNextDate(Carbon $after): Carbon
    {
        $next = $after->copy()->addMonth()->startOfMonth();
        $day  = min($this->day_of_month, $next->daysInMonth);
        return $next->setDay($day);
    }
}
