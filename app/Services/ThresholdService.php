<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Event;
use App\Models\Sensor;
use App\Jobs\TriggerWebhookJob;

class ThresholdService
{
    protected AlertService $alertService;

    public function __construct(AlertService $alertService)
    {
        $this->alertService = $alertService;
    }

    /**
     * Verifica leitura contra limites e gera alertas se necessário
     */
    public function checkThresholds(Sensor $sensor, ?float $temperature, ?float $humidity, ?string $ip = null, ?string $mac = null, ?int $cooldownMinutes = null): array
    {
        $alertsTriggered = [];

        $metrics = [
            'temperature' => [
                'value' => $temperature,
                'max' => (float) $sensor->temp_max,
                'min' => (float) $sensor->temp_min,
                'state_col' => 'alert_state_temp',
                'high_type' => 'temp_high',
                'low_type' => 'temp_low',
                'high_msg' => 'Temperatura acima do limite',
                'low_msg' => 'Temperatura abaixo do limite'
            ],
            'humidity' => [
                'value' => $humidity,
                'max' => (float) $sensor->hum_max,
                'min' => (float) $sensor->hum_min,
                'state_col' => 'alert_state_hum',
                'high_type' => 'hum_high',
                'low_type' => 'hum_low',
                'high_msg' => 'Umidade acima do limite',
                'low_msg' => 'Umidade abaixo do limite'
            ]
        ];

        foreach ($metrics as $metricName => $m) {
            if ($m['value'] === null) {
                continue;
            }

            $currentVal = (float)$m['value'];
            $max = $m['max'];
            $min = $m['min'];
            $oldState = $sensor->{$m['state_col']} ?? 'normal';
            
            $newState = 'normal';
            $alertType = null;
            $alertMsg = null;

            if ($currentVal > $max) {
                $newState = 'high';
                $alertType = $m['high_type'];
                $alertMsg = $m['high_msg'];
            } elseif ($currentVal < $min) {
                $newState = 'low';
                $alertType = $m['low_type'];
                $alertMsg = $m['low_msg'];
            }

            // 1. Mudança de Estado
            if ($newState !== $oldState) {
                
                if ($newState === 'normal') {
                    // Hysteresis: Margem para evitar "flapping" na normalização
                    $margin = ($metricName === 'temperature') ? 0.5 : 2.0;
                    $canNormalize = false;

                    if ($oldState === 'high' && $currentVal <= ($max - $margin)) {
                        $canNormalize = true;
                    } elseif ($oldState === 'low' && $currentVal >= ($min + $margin)) {
                        $canNormalize = true;
                    }

                    if ($canNormalize) {
                        $normMsg = ($metricName === 'temperature') ? "Temperatura normalizada: {$currentVal}°C" : "Umidade normalizada: {$currentVal}%";
                    
                        Event::create([
                            'sensor_id' => $sensor->id,
                            'type' => 'info',
                            'message' => "Sensor {$sensor->label} normalizado. {$normMsg}",
                            'is_admin_only' => false,
                        ]);

                        $alertInternalType = 'normalized_' . $metricName;
                        $phones = $this->alertService->getContactsForAlert($sensor->client_id, $alertInternalType);

                        $payload = $this->buildPayload($sensor, $alertInternalType, $metricName, $currentVal, null, $normMsg, $ip, $mac, $phones);
                        TriggerWebhookJob::dispatch($payload);
                        
                        $sensor->update([$m['state_col'] => 'normal']);
                    }
                } else {
                    // NOVO ALERTA (Transição de normal para high/low ou de high para low, etc.)
                    if ($this->alertService->canTriggerAlert($sensor->id, $alertType, $cooldownMinutes)) {
                        $thresholdValue = ($newState === 'high' ? $max : $min);
                        
                        $alert = Alert::create([
                            'sensor_id' => $sensor->id,
                            'type' => $alertType,
                            'message' => $alertMsg,
                            'value' => $currentVal,
                            'threshold' => $thresholdValue,
                            'webhook_sent' => false,
                        ]);

                        $phones = $this->alertService->getContactsForAlert($sensor->client_id, $alertType);
                        $payload = $this->buildPayload($sensor, $alertType, $metricName, $currentVal, $thresholdValue, $alertMsg, $ip, $mac, $phones);
                        
                        // We set webhook_sent to true directly as we dispatch the job
                        // Alternatively, the job could update it, but for simplicity we keep parity
                        $alert->update(['webhook_sent' => true]);
                        TriggerWebhookJob::dispatch($payload);

                        $sensor->update([$m['state_col'] => $newState]);
                        $alertsTriggered[] = $payload;
                    }
                }
            } 
            // 2. Continua em alerta (Repetição por cooldown)
            elseif ($newState !== 'normal' && $this->alertService->canTriggerAlert($sensor->id, $alertType, $cooldownMinutes)) {
                $thresholdValue = ($newState === 'high' ? $max : $min);
                $phones = $this->alertService->getContactsForAlert($sensor->client_id, $alertType);
                
                $payload = $this->buildPayload($sensor, $alertType, $metricName, $currentVal, $thresholdValue, $alertMsg . " (Ainda fora do limite)", $ip, $mac, $phones);

                TriggerWebhookJob::dispatch($payload);
                
                Alert::create([
                    'sensor_id' => $sensor->id,
                    'type' => $alertType,
                    'message' => $alertMsg . " (Lembrete)",
                    'value' => $currentVal,
                    'threshold' => $thresholdValue,
                    'webhook_sent' => true,
                ]);
                
                $alertsTriggered[] = $payload;
            }
        }

        return $alertsTriggered;
    }

    private function buildPayload(Sensor $sensor, string $type, string $field, float $value, ?float $threshold, string $message, ?string $ip, ?string $mac, array $contacts): array
    {
        $client = $sensor->client;

        return [
            'client'    => $client->company ?: $client->name,
            'sensor'    => $sensor->device_id,
            'location'  => $sensor->location,
            'label'     => $sensor->label,
            'type'      => $type,
            'field'     => $field,
            'value'     => $value,
            'threshold' => $threshold,
            'message'   => $message,
            'ip'        => $ip,
            'mac'       => $mac,
            'contacts'  => $contacts,
            'timestamp' => now()->timezone('America/Sao_Paulo')->format('Y-m-d\TH:i:s'),
        ];
    }
}
