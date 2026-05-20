<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sensor extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'device_id',
        'location',
        'label',
        'temp_min',
        'temp_max',
        'hum_min',
        'hum_max',
        'activation_date',
        'alert_state_temp',
        'alert_state_hum',
        'last_status',
        'last_seen',
        'is_active',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'activation_date' => 'date',
        'last_seen'       => 'datetime',
        'temp_min'        => 'decimal:2',
        'temp_max'        => 'decimal:2',
        'hum_min'         => 'decimal:2',
        'hum_max'         => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    // Status helpers
    public function isOnline(): bool
    {
        return $this->last_status === 'online';
    }

    public function hasTemperatureAlert(): bool
    {
        return $this->alert_state_temp !== 'normal';
    }

    public function hasHumidityAlert(): bool
    {
        return $this->alert_state_hum !== 'normal';
    }
}
