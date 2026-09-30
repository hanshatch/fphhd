<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Correo de notificación bancaria leído de Gmail. Guarda lo interpretado,
 * el estado del procesamiento y, si se registró, la transacción creada.
 */
class BankEmail extends Model
{
    public const STATUS_PENDING    = 'pending';     // esperando decisión de Hans (Telegram / web)
    public const STATUS_REGISTERED = 'registered';  // ya generó (o se ligó a) una transacción
    public const STATUS_DUPLICATE  = 'duplicate';   // ya existía el movimiento
    public const STATUS_SKIPPED    = 'skipped';     // Hans decidió no registrarlo
    public const STATUS_UNPARSED   = 'unparsed';    // formato desconocido
    public const STATUS_IGNORED    = 'ignored';     // remitente que no es banco

    protected $fillable = [
        'message_uid', 'bank', 'sender', 'subject', 'received_at', 'raw_text',
        'parsed', 'status', 'account_id', 'transaction_id', 'auth_number',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'parsed'      => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
