<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'client_id',
        'name',
        'phone',
        'is_active',
        'is_admin',
    ];

    protected $casts = [
        'is_admin'  => 'boolean',
        'is_active' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function alertPreferences(): HasMany
    {
        return $this->hasMany(ContactAlertPreference::class);
    }

    public function sentAlertsLog(): HasMany
    {
        return $this->hasMany(SentAlertsLog::class);
    }

    // Escopo para contatos admin (sem client_id)
    public function scopeAdminOnly($query)
    {
        return $query->where('is_admin', true)->whereNull('client_id');
    }
}
