<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company',
        'token',
        'is_active',
        'influx_org',
        'influx_bucket',
        'influx_token',
        'alert_interval_connectivity',
        'alert_interval_threshold',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function sensors(): HasMany
    {
        return $this->hasMany(Sensor::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function activeSensors(): HasMany
    {
        return $this->hasMany(Sensor::class)->where('is_active', true);
    }
}
