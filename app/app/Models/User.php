<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'totp_secret',
        'totp_enabled',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            'totp_enabled'           => 'boolean',
            'locked_until'           => 'datetime',
            'failed_login_attempts'  => 'integer',
        ];
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function lockMinutesRemaining(): int
    {
        if (! $this->isLocked()) {
            return 0;
        }

        return (int) Carbon::now()->diffInMinutes($this->locked_until, false) + 1;
    }

    public function recordFailedLogin(): void
    {
        $attempts = $this->failed_login_attempts + 1;

        $this->update([
            'failed_login_attempts' => $attempts,
            'locked_until'          => $attempts >= 5 ? Carbon::now()->addMinutes(15) : null,
        ]);
    }

    public function clearFailedLogins(): void
    {
        $this->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
        ]);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
