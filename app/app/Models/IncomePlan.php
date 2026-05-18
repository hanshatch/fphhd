<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class IncomePlan extends Model
{
    protected $fillable = [
        'name', 'notes', 'account_id', 'source_id', 'category_id',
        'expected_amount', 'frequency', 'day_1', 'day_2',
        'next_expected_date', 'is_active',
    ];

    protected $casts = [
        'expected_amount'   => 'decimal:2',
        'next_expected_date'=> 'date',
        'is_active'         => 'boolean',
    ];

    const FREQ_ONCE     = 'once';
    const FREQ_WEEKLY   = 'weekly';
    const FREQ_BIWEEKLY = 'biweekly';
    const FREQ_MONTHLY  = 'monthly';

    public function account(): BelongsTo  { return $this->belongsTo(Account::class); }
    public function source(): BelongsTo   { return $this->belongsTo(Source::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeUpcoming(Builder $q, int $days = 30): Builder
    {
        return $q->active()
            ->where('next_expected_date', '<=', Carbon::today()->addDays($days)->toDateString())
            ->orderBy('next_expected_date');
    }

    public function frequencyLabel(): string
    {
        return match ($this->frequency) {
            self::FREQ_ONCE     => 'Único',
            self::FREQ_WEEKLY   => 'Semanal',
            self::FREQ_BIWEEKLY => 'Quincenal',
            self::FREQ_MONTHLY  => 'Mensual',
            default             => $this->frequency,
        };
    }

    public function isOnce(): bool
    {
        return $this->frequency === self::FREQ_ONCE;
    }

    public function daysUntilNext(): int
    {
        return (int) Carbon::today()->diffInDays($this->next_expected_date, false);
    }

    /**
     * Calcula la siguiente fecha de pago después de registrar uno.
     */
    public function calculateNextDate(Carbon $after): ?Carbon
    {
        return match ($this->frequency) {
            self::FREQ_ONCE     => null,  // se desactiva, no hay siguiente
            self::FREQ_WEEKLY   => $after->copy()->addWeek(),
            self::FREQ_MONTHLY  => $this->nextMonthlyDate($after),
            self::FREQ_BIWEEKLY => $this->nextBiweeklyDate($after),
            default             => $after->copy()->addDays(15),
        };
    }

    private function nextMonthlyDate(Carbon $after): Carbon
    {
        $next = $after->copy()->addMonth()->startOfMonth();
        $day  = min($this->day_1 ?? 1, $next->daysInMonth);
        return $next->setDay($day);
    }

    private function nextBiweeklyDate(Carbon $after): Carbon
    {
        // Si tiene day_1 y day_2 definidos (ej. 15 y 30), alterna entre ellos
        if ($this->day_1 && $this->day_2) {
            $currentDay = $after->day;
            if ($currentDay <= $this->day_1) {
                // Siguiente es day_2 del mismo mes
                $day = min($this->day_2, $after->daysInMonth);
                return $after->copy()->setDay($day);
            } else {
                // Siguiente es day_1 del mes siguiente
                $next = $after->copy()->addMonth()->startOfMonth();
                $day  = min($this->day_1, $next->daysInMonth);
                return $next->setDay($day);
            }
        }

        // Fallback: simplemente +15 días
        return $after->copy()->addDays(15);
    }
}
