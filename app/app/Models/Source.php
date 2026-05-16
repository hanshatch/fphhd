<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    protected $fillable = ['name', 'kind', 'notes', 'is_archived'];

    protected $casts = [
        'is_archived' => 'boolean',
    ];

    const KIND_AGENCY     = 'agency';
    const KIND_UNIVERSITY = 'university';
    const KIND_TRAINING   = 'training';
    const KIND_OTHER      = 'other';

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }
}
