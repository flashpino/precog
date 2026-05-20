<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'sensor_id',
        'type',
        'message',
        'value',
        'threshold',
        'webhook_sent',
    ];

    protected $casts = [
        'webhook_sent' => 'boolean',
        'value'        => 'decimal:2',
        'threshold'    => 'decimal:2',
    ];

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }
}
