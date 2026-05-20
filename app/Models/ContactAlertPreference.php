<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactAlertPreference extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'contact_id',
        'alert_type',
        'days_of_week',
        'time_start',
        'time_end',
        'min_interval',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * Verifica se o alerta pode ser enviado agora com base no schedule.
     */
    public function isActiveNow(): bool
    {
        $now = now()->timezone('America/Sao_Paulo');
        $currentDay  = $now->dayOfWeek; // 0=Dom, 1=Seg ... 6=Sáb
        $currentTime = $now->format('H:i:s');

        $days = array_map('trim', explode(',', $this->days_of_week));

        if (!in_array((string) $currentDay, $days)) {
            return false;
        }

        return $currentTime >= $this->time_start && $currentTime <= $this->time_end;
    }
}
