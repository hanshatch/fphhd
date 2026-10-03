<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dinero apartado (positivo) o retirado (negativo) de una meta. */
class GoalContribution extends Model
{
    protected $fillable = ['goal_id', 'date', 'amount', 'note'];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    public function goal(): BelongsTo { return $this->belongsTo(Goal::class); }

    /** Día, no instante: Y-m-d igual en MySQL y SQLite (mismo criterio que Transaction) */
    protected function date(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : \Illuminate\Support\Carbon::parse($value)->toDateString(),
        );
    }
}
