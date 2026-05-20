<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Contact;
use App\Models\SentAlertsLog;
use Carbon\Carbon;

class AlertService
{
    /**
     * Verifica se pode disparar um novo alerta (anti-spam / cooldown)
     */
    public function canTriggerAlert(int $sensorId, string $type, ?int $cooldownMinutes = null): bool
    {
        $cooldownMinutes = $cooldownMinutes ?? (int) config('app.alert_cooldown_minutes', 5);

        $lastAlert = Alert::where('sensor_id', $sensorId)
            ->where('type', $type)
            ->latest('created_at')
            ->first();

        if (!$lastAlert) {
            return true;
        }

        return $lastAlert->created_at->diffInMinutes(now()) >= $cooldownMinutes;
    }

    /**
     * Obtém a lista de telefones de contatos que devem receber um determinado alerta.
     * Aplica filtros de preferências individuais (tipo de alerta, horário, dias da semana, intervalo mínimo).
     */
    public function getContactsForAlert(int $clientId, string $alertType): array
    {
        // Mapeia o tipo de alerta interno para a categoria de preferência
        $typeGroupMap = [
            'esp32_reboot' => 'connectivity',
            'online' => 'connectivity',
            'offline' => 'connectivity',
            'temp_high' => 'temperature',
            'temp_low' => 'temperature',
            'normalized_temperature' => 'temperature',
            'hum_high' => 'humidity',
            'hum_low' => 'humidity',
            'normalized_humidity' => 'humidity',
            'test' => 'test'
        ];

        $category = $typeGroupMap[$alertType] ?? 'connectivity';

        // Busca contatos do cliente e administradores ativos
        $contacts = Contact::where(function ($query) use ($clientId) {
            $query->where('client_id', $clientId)
                  ->orWhere('is_admin', true);
        })
        ->where('is_active', true)
        ->with('alertPreferences')
        ->get();

        $validPhones = [];
        $now = now()->timezone(config('app.timezone', 'America/Sao_Paulo'));

        foreach ($contacts as $contact) {
            // Administradores sempre recebem alertas de queda de equipamento incondicionalmente
            if ($contact->is_admin && $alertType === 'esp32_reboot') {
                $validPhones[] = $contact->phone;
                continue;
            }

            $hasPrefs = $contact->alertPreferences->isNotEmpty();

            if (!$hasPrefs && $category !== 'test') {
                // Contato não migrado/sem preferências = recebe tudo (fallback legado)
                $validPhones[] = $contact->phone;
                continue;
            }

            if ($category === 'test') {
                $validPhones[] = $contact->phone;
                continue;
            }

            // Busca preferência ESPECÍFICA desta categoria
            $pref = $contact->alertPreferences->where('alert_type', $category)->first();

            if (!$pref) {
                continue;
            }

            if (!$pref->isActiveNow()) {
                continue;
            }

            // Determina se é um alerta de recuperação/normalização (não deve sofrer throttling nem bloqueá-los)
            $isRecovery = str_starts_with($alertType, 'normalized_') || $alertType === 'online';

            // Verifica Intervalo Mínimo (apenas para alertas de falha/evento, não para recuperação)
            if (!$isRecovery && $pref->min_interval > 0) {
                $lastSent = SentAlertsLog::where('contact_id', $contact->id)
                    ->where('alert_type', $category)
                    ->latest('sent_at')
                    ->first();

                if ($lastSent && $lastSent->sent_at) {
                    $sentAt = (clone $lastSent->sent_at)->setTimezone($now->timezone);
                    if ($sentAt->diffInMinutes($now) < $pref->min_interval) {
                        continue;
                    }
                }
            }

            // Registra log para throttling futuro (apenas para alertas de falha/evento)
            if (!$isRecovery) {
                SentAlertsLog::create([
                    'contact_id' => $contact->id,
                    'alert_type' => $category,
                    'sent_at'    => now(),
                ]);
            }

            $validPhones[] = $contact->phone;
        }

        return $validPhones;
    }
}
