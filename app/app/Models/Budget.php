<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Budget extends Model
{
    protected $fillable = ['category_id', 'amount', 'notes'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Gasto real de esta categoría en el mes dado.
     */
    public function spent(?Carbon $month = null): string
    {
        $month ??= now();
        $from = $month->copy()->startOfMonth()->toDateString();
        $to   = $month->copy()->endOfMonth()->toDateString();

        $spent = Transaction::where('category_id', $this->category_id)
            ->whereIn('type', ['expense', 'fee'])
            ->whereBetween('date', [$from, $to])
            ->sum('amount');

        return number_format((float) $spent, 2, '.', '');
    }

    /**
     * Porcentaje usado (0–100+).
     */
    public function percentUsed(?Carbon $month = null): float
    {
        $limit = (float) $this->amount;
        if ($limit <= 0) return 0;
        return round(((float) $this->spent($month)) / $limit * 100, 1);
    }
}
