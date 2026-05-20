<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Sensor;
use App\Models\Event;
use App\Models\Alert;
use App\Services\ThresholdService;
use App\Services\AlertService;
use App\Jobs\TriggerWebhookJob;
use InfluxDB2\Client;
use Carbon\Carbon;

class WatcherCommand extends Command
{
    protected $signature = 'precog:watcher';
    protected $description = 'Verifica métricas no InfluxDB e gera alertas de limites e conectividade';

    protected ThresholdService $thresholdService;
    protected AlertService $alertService;

    public function __construct(ThresholdService $thresholdService, AlertService $alertService)
    {
        parent::__construct();
        $this->thresholdService = $thresholdService;
        $this->alertService = $alertService;
    }

    public function handle()
    {
        $this->info('Iniciando Watcher...');

        $sensors = Sensor::where('is_active', true)->with('client')->get();

        if ($sensors->isEmpty()) {
            $this->info('Nenhum sensor ativo encontrado.');
            return 0;
        }

        $now = now();

        // Agrupar sensores por cliente para buscar os dados de telemetria no InfluxDB correto de cada um
        $sensorsByClient = $sensors->groupBy('client_id');

        foreach ($sensorsByClient as $clientId => $clientSensors) {
            $client = $clientSensors->first()->client;

            // Cooldown e Tolerância de queda específicos do cliente
            $clientCooldown = (int) ($client->alert_interval_connectivity ?: env('ALERT_COOLDOWN_MINUTES', 5));
            $clientOfflineThreshold = (int) ($client->alert_interval_threshold ?: 5);

            // Obter configurações específicas do cliente ou usar as globais como fallback
            $url = config('influxdb.url');
            $org = $client->influx_org ?: config('influxdb.org');
            $bucket = $client->influx_bucket ?: config('influxdb.bucket');
            $token = $client->influx_token ?: config('influxdb.token');

            if (!$url || !$token) {
                $this->error("Configuração do InfluxDB incompleta para o cliente ID {$clientId}.");
                // Marcar todos os sensores deste cliente como offline
                foreach ($clientSensors as $sensor) {
                    $this->handleOfflineSensor($sensor, $now, $clientOfflineThreshold, $clientCooldown);
                }
                continue;
            }

            $deviceIds = $clientSensors->pluck('device_id')->toArray();
            $searchIds = array_unique(array_merge($deviceIds, array_map('strtolower', $deviceIds), array_map('strtoupper', $deviceIds)));
            $latestData = $this->fetchClientInfluxData($url, $org, $bucket, $token, $searchIds, $clientOfflineThreshold);

            foreach ($clientSensors as $sensor) {
                $device_id = $sensor->device_id;
                $data = $latestData[strtolower($device_id)] ?? null;

                if ($data) {
                    // SENSOR ONLINE
                    $this->handleOnlineSensor($sensor, $data, $clientCooldown);
                } else {
                    // SENSOR OFFLINE (Não enviou dados recentes)
                    $this->handleOfflineSensor($sensor, $now, $clientOfflineThreshold, $clientCooldown);
                }
            }
        }

        $this->info('Watcher concluído.');
        return 0;
    }

    private function handleOnlineSensor(Sensor $sensor, array $data, int $cooldownMinutes): void
    {
        // Verifica se estava offline e voltou
        if ($sensor->last_status === 'offline') {
            Event::create([
                'sensor_id' => $sensor->id,
                'type' => 'info',
                'message' => "Sensor {$sensor->label} restabeleceu conexão.",
                'is_admin_only' => false,
            ]);

            $alertType = 'online';
            $phones = $this->alertService->getContactsForAlert($sensor->client_id, $alertType);
            
            $payload = [
                'client'    => $sensor->client->company ?: $sensor->client->name,
                'sensor'    => $sensor->device_id,
                'location'  => $sensor->location,
                'label'     => $sensor->label,
                'type'      => $alertType,
                'message'   => "Conexão restabelecida",
                'contacts'  => $phones,
                'timestamp' => now()->timezone('America/Sao_Paulo')->format('Y-m-d\TH:i:s'),
            ];
            TriggerWebhookJob::dispatch($payload);
        }

        // Verifica reboot flag (se o ESP32 enviou reason != normal/null)
        if (!empty($data['reset_reason'])) {
            $reason = $data['reset_reason'];
            Event::create([
                'sensor_id' => $sensor->id,
                'type' => 'warning',
                'message' => "ESP32 Reboot detectado. Motivo: {$reason}",
                'is_admin_only' => true, // Só admins devem ver reboots
            ]);

            // Envia alerta interno (esp32_reboot) que só administradores recebem
            if ($this->alertService->canTriggerAlert($sensor->id, 'esp32_reboot', 60)) { // Cooldown maior para reboots
                $phones = $this->alertService->getContactsForAlert($sensor->client_id, 'esp32_reboot');
                $payload = [
                    'client'    => $sensor->client->company ?: $sensor->client->name,
                    'sensor'    => $sensor->device_id,
                    'location'  => $sensor->location,
                    'label'     => $sensor->label,
                    'type'      => 'esp32_reboot',
                    'message'   => "Alerta de Reboot: {$reason}",
                    'contacts'  => $phones,
                    'timestamp' => now()->timezone('America/Sao_Paulo')->format('Y-m-d\TH:i:s'),
                ];
                TriggerWebhookJob::dispatch($payload);
                
                // Alert::create não suporta 'esp32_reboot' no enum original. Ignorando a inserção em alertas, Event já registra.
            }
        }

        // Atualiza sensor
        $sensor->last_seen = now();
        $sensor->last_status = 'online';
        $sensor->save();

        // Checa Thresholds (Temperatura / Umidade)
        $this->thresholdService->checkThresholds(
            $sensor,
            $data['temperature'] ?? null,
            $data['humidity'] ?? null,
            $data['ip'] ?? null,
            $data['mac'] ?? null,
            $cooldownMinutes
        );
    }

    private function handleOfflineSensor(Sensor $sensor, Carbon $now, int $offlineThreshold, int $cooldownMinutes): void
    {
        if (!$sensor->last_seen) {
            return; // Nunca conectou
        }

        $diffMinutes = $sensor->last_seen->diffInMinutes($now);

        if ($diffMinutes >= $offlineThreshold) {
            if ($sensor->last_status !== 'offline') {
                Event::create([
                    'sensor_id' => $sensor->id,
                    'type' => 'warning',
                    'message' => "Sensor {$sensor->label} perdeu conexão (offline).",
                    'is_admin_only' => false,
                ]);

                $sensor->last_status = 'offline';
                $sensor->alert_state_temp = 'normal'; // Reset state on offline
                $sensor->alert_state_hum = 'normal';
                $sensor->save();
            }

            if ($this->alertService->canTriggerAlert($sensor->id, 'offline', $cooldownMinutes)) {
                $phones = $this->alertService->getContactsForAlert($sensor->client_id, 'offline');
                
                $payload = [
                    'client'    => $sensor->client->company ?: $sensor->client->name,
                    'sensor'    => $sensor->device_id,
                    'location'  => $sensor->location,
                    'label'     => $sensor->label,
                    'type'      => 'offline',
                    'message'   => "Equipamento Offline (>{$offlineThreshold} min)",
                    'contacts'  => $phones,
                    'timestamp' => now()->timezone('America/Sao_Paulo')->format('Y-m-d\TH:i:s'),
                ];
                TriggerWebhookJob::dispatch($payload);

                // Como offline não tá no ENUM de alerts, simulamos no Events
                // Alert::create() não é feito para conectividade devido ao schema
            }
        }
    }

    private function fetchClientInfluxData(string $url, string $org, string $bucket, string $token, array $deviceIds, int $offlineThreshold = 5): array
    {
        if (!$url || empty($deviceIds)) {
            return [];
        }

        $client = \App\Services\InfluxConnectionHelper::createClient($url, $org, $token);

        $queryApi = $client->createQueryApi();

        $deviceList = implode('", "', $deviceIds);
        
        $query = "
            from(bucket: \"{$bucket}\")
            |> range(start: -{$offlineThreshold}m)
            |> filter(fn: (r) => r[\"_measurement\"] == \"ambiente\")
            |> filter(fn: (r) => contains(value: r[\"device_id\"], set: [\"{$deviceList}\"]))
            |> filter(fn: (r) => r[\"_field\"] == \"temperatura\" or r[\"_field\"] == \"umidade\" or r[\"_field\"] == \"ip\" or r[\"_field\"] == \"uptime\" or r[\"_field\"] == \"rssi\" or r[\"_field\"] == \"reset_reason\")
            |> last()
            |> pivot(rowKey:[\"device_id\"], columnKey: [\"_field\"], valueColumn: \"_value\")
        ";

        try {
            $tables = $queryApi->query($query);
            $results = [];

            foreach ($tables as $table) {
                foreach ($table->records as $record) {
                    $values = $record->values;
                    $device = $values['device_id'] ?? null;
                    if ($device) {
                        $results[strtolower($device)] = [
                            'temperature' => $values['temperatura'] ?? null,
                            'humidity' => $values['umidade'] ?? null,
                            'ip' => $values['ip'] ?? null,
                            'mac' => $values['mac'] ?? null,
                            'reset_reason' => $values['reset_reason'] ?? null,
                        ];
                    }
                }
            }
            return $results;
        } catch (\Exception $e) {
            $this->error("InfluxDB Query Error: " . $e->getMessage());
            return [];
        }
    }
}
