<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'sensor_id',
        'type',
        'message',
        'is_admin_only',
    ];

    protected $casts = [
        'is_admin_only' => 'boolean',
    ];

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    // Escopo para eventos visíveis ao cliente (não são internos)
    public function scopeClientVisible($query)
    {
        return $query->where('is_admin_only', false);
    }
}
