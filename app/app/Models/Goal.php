<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Meta de ahorro (viaje, proyecto, compra…). Lo apartado vive en
 * goal_contributions; no son transacciones y no mueven saldos.
 */
class Goal extends Model
{
    public const KIND_TRIP     = 'trip';
    public const KIND_PROJECT  = 'project';
    public const KIND_PURCHASE = 'purchase';
    public const KIND_EVENT    = 'event';
    public const KIND_OTHER    = 'other';

    public const KINDS = [
        self::KIND_TRIP     => 'Viaje',
        self::KIND_PROJECT  => 'Proyecto',
        self::KIND_PURCHASE => 'Compra',
        self::KIND_EVENT    => 'Evento',
        self::KIND_OTHER    => 'Otro',
    ];

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'name', 'kind', 'target_amount', 'target_date', 'account_id',
        'color', 'notes', 'status', 'completed_at',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'target_date'   => 'date',
        'completed_at'  => 'datetime',
    ];

    /** Día, no instante: Y-m-d igual en MySQL y SQLite */
    protected function targetDate(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value === null ? null : \Illuminate\Support\Carbon::parse($value)->toDateString(),
        );
    }

    public function account(): BelongsTo { return $this->belongsTo(Account::class); }

    public function items(): HasMany
    {
        return $this->hasMany(GoalItem::class)->orderBy('position')->orderBy('id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class)->orderByDesc('date')->orderByDesc('id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? 'Otro';
    }
}
