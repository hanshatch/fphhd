<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'ip', 'user_agent', 'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    const ACTION_LOGIN          = 'login';
    const ACTION_LOGIN_FAIL     = 'login_fail';
    const ACTION_LOGOUT         = 'logout';
    const ACTION_PASSWORD_CHANGE = 'password_change';
    const ACTION_PROFILE_UPDATE  = 'profile_update';
    const ACTION_TOTP_ENABLE    = 'totp_enable';
    const ACTION_TOTP_DISABLE   = 'totp_disable';
    const ACTION_DATA_EXPORT    = 'data_export';
    const ACTION_ACCOUNT_LOCK   = 'account_lock';
    const ACTION_ACCOUNT_UNLOCK = 'account_unlock';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, array $metadata = []): void
    {
        static::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata'   => $metadata ?: null,
        ]);
    }
}
