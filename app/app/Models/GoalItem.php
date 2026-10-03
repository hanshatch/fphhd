<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Partida del desglose de costos de una meta (vuelo, hotel, enganche…). */
class GoalItem extends Model
{
    protected $fillable = ['goal_id', 'description', 'amount', 'position'];

    protected $casts = ['amount' => 'decimal:2'];

    public function goal(): BelongsTo { return $this->belongsTo(Goal::class); }
}
