<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantRule extends Model
{
    protected $fillable = ['merchant_key', 'sample', 'type', 'category_id', 'hits', 'last_used_at'];

    protected $casts = [
        'hits'         => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
