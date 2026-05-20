<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Sensor;
use App\Models\Alert;
use App\Models\Event;
use Illuminate\Http\Request;

use InfluxDB2\Client as InfluxClient;

class DashboardController extends Controller
{
    public function index()
    {
        // Admin Dashboard Stats
        $stats = [
            'total_clients' => Client::count(),
            'active_sensors' => Sensor::where('is_active', true)->count(),
            'offline_sensors' => Sensor::where('last_status', 'offline')->where('is_active', true)->count(),
            'recent_alerts' => Alert::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        $recentEvents = Event::with('sensor')->latest()->take(10)->get();
        $recentAlerts = Alert::with('sensor')->latest()->take(10)->get();
        $sensors = Sensor::with('client')->orderBy('last_status', 'asc')->get();

        // Fetch latest InfluxDB metrics
        $influxData = [];
        foreach ($sensors as $sensor) {
            $influxData[$sensor->device_id] = [
                'temperature' => null,
                'humidity' => null,
                'mac' => 'N/A',
                'uptime' => 'N/A',
                'rssi' => 'N/A',
            ];
        }

        if ($sensors->isNotEmpty()) {
            $sensorsByClient = $sensors->groupBy('client_id');
            foreach ($sensorsByClient as $clientId => $clientSensors) {
                try {
                    $clientModel = $clientSensors->first()->client;
                    
                    // Cache general InfluxDB queries for the client for 30 seconds
                    $cacheKey = "admin_dashboard_influx_client_" . $clientId;
                    $clientInfluxData = \Illuminate\Support\Facades\Cache::remember($cacheKey, 30, function() use ($clientModel, $clientSensors) {
                        $url = config('influxdb.url');
                        $org = $clientModel->influx_org ?: config('influxdb.org');
                        $bucketRaw = $clientModel->influx_bucket ?: config('influxdb.bucket');
                        $bucket = preg_replace('/[^a-zA-Z0-9_.-]/', '', $bucketRaw);
                        $token = $clientModel->influx_token ?: config('influxdb.token');

                        if (!$url || !$token) {
                            return [];
                        }

                        $influx = \App\Services\InfluxConnectionHelper::createClient($url, $org, $token);
                        $queryApi = $influx->createQueryApi();
                        $deviceIds = $clientSensors->pluck('device_id')->toArray();
                        $searchIds = array_unique(array_merge($deviceIds, array_map('strtolower', $deviceIds), array_map('strtoupper', $deviceIds)));
                        $searchIds = array_map(function($id) { return str_replace(['"', '\\'], '', $id); }, $searchIds);
                        $deviceList = implode('", "', $searchIds);

                        $query = "
                            from(bucket: \"{$bucket}\")
                            |> range(start: -7d)
                            |> filter(fn: (r) => r[\"_measurement\"] == \"ambiente\")
                            |> filter(fn: (r) => contains(value: r[\"device_id\"], set: [\"{$deviceList}\"]))
                            |> filter(fn: (r) => r[\"_field\"] == \"temperatura\" or r[\"_field\"] == \"umidade\" or r[\"_field\"] == \"uptime\" or r[\"_field\"] == \"rssi\")
                            |> group(columns: [\"device_id\", \"_field\"])
                            |> last()
                        ";

                        $tables = $queryApi->query($query);
                        $results = [];
                        foreach ($tables as $table) {
                            foreach ($table->records as $record) {
                                $device = $record['device_id'] ?? null;
                                $field = $record['_field'] ?? null;
                                $value = $record['_value'] ?? null;
                                
                                if ($device && $field) {
                                    $deviceLower = strtolower($device);
                                    if (!isset($results[$deviceLower])) {
                                        $results[$deviceLower] = [];
                                    }
                                    
                                    $results[$deviceLower][$field] = $value;
                                    
                                    if (isset($record['mac'])) {
                                        $results[$deviceLower]['mac'] = $record['mac'];
                                    } elseif (isset($record['mac_address'])) {
                                        $results[$deviceLower]['mac'] = $record['mac_address'];
                                    }
                                }
                            }
                        }
                        return $results;
                    });

                    // Map cached metrics dynamically
                    $sensorMap = $clientSensors->keyBy(function($s) { return strtolower($s->device_id); });
                    foreach ($clientInfluxData as $deviceLower => $metrics) {
                        $sensorModel = $sensorMap->get($deviceLower);
                        $originalDevice = $sensorModel ? $sensorModel->device_id : $deviceLower;
                        if ($sensorModel && $sensorModel->last_status === 'online') {
                            if (isset($metrics['temperatura'])) $influxData[$originalDevice]['temperature'] = $metrics['temperatura'];
                            if (isset($metrics['umidade'])) $influxData[$originalDevice]['humidity'] = $metrics['umidade'];
                            if (isset($metrics['uptime'])) $influxData[$originalDevice]['uptime'] = $metrics['uptime'];
                            if (isset($metrics['rssi'])) $influxData[$originalDevice]['rssi'] = $metrics['rssi'];
                            if (isset($metrics['mac'])) $influxData[$originalDevice]['mac'] = $metrics['mac'];
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error("InfluxDB Dashboard Error for Client ID {$clientId}: " . $e->getMessage());
                }
            }
        }

        return view('admin.dashboard', compact('stats', 'recentEvents', 'recentAlerts', 'sensors', 'influxData'));
    }

    public function telemetry(Request $request)
    {
        // Requer token de cliente OU sessão admin autenticada
        if ($request->has('token')) {
            $client = Client::where('token', $request->token)->first();
            if (!$client) return response()->json([], 401);
            $sensors = Sensor::where('client_id', $client->id)->with('client')->get();
        } elseif (auth('admin')->check()) {
            $sensors = Sensor::where('is_active', true)->with('client')->get();
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($sensors->isEmpty()) return response()->json([]);

        $influxData = [];
        foreach ($sensors as $sensor) {
            $influxData[$sensor->device_id] = [
                'temperature' => null,
                'humidity' => null,
                'rssi' => null,
                'uptime' => null,
                'status' => $sensor->last_status,
            ];
        }

        if ($sensors->isNotEmpty()) {
            $sensorsByClient = $sensors->groupBy('client_id');
            foreach ($sensorsByClient as $clientId => $clientSensors) {
                try {
                    $clientModel = $clientSensors->first()->client;
                    
                    // Cache general InfluxDB queries for the client for 5 seconds for telemetry
                    $cacheKey = "telemetry_influx_client_" . $clientId;
                    $clientInfluxData = \Illuminate\Support\Facades\Cache::remember($cacheKey, 5, function() use ($clientModel, $clientSensors) {
                        $url = config('influxdb.url');
                        $org = $clientModel->influx_org ?: config('influxdb.org');
                        $bucketRaw = $clientModel->influx_bucket ?: config('influxdb.bucket');
                        $bucket = preg_replace('/[^a-zA-Z0-9_.-]/', '', $bucketRaw);
                        $token = $clientModel->influx_token ?: config('influxdb.token');

                        if (!$url || !$token) {
                            return [];
                        }

                        $influx = \App\Services\InfluxConnectionHelper::createClient($url, $org, $token);
                        $queryApi = $influx->createQueryApi();
                        $deviceIds = $clientSensors->pluck('device_id')->toArray();
                        $searchIds = array_unique(array_merge($deviceIds, array_map('strtolower', $deviceIds), array_map('strtoupper', $deviceIds)));
                        $searchIds = array_map(function($id) { return str_replace(['"', '\\'], '', $id); }, $searchIds);
                        $deviceList = implode('", "', $searchIds);

                        $query = "
                            from(bucket: \"{$bucket}\")
                            |> range(start: -7d)
                            |> filter(fn: (r) => r[\"_measurement\"] == \"ambiente\")
                            |> filter(fn: (r) => contains(value: r[\"device_id\"], set: [\"{$deviceList}\"]))
                            |> filter(fn: (r) => r[\"_field\"] == \"temperatura\" or r[\"_field\"] == \"umidade\" or r[\"_field\"] == \"uptime\" or r[\"_field\"] == \"rssi\")
                            |> group(columns: [\"device_id\", \"_field\"])
                            |> last()
                        ";

                        $tables = $queryApi->query($query);
                        $results = [];
                        foreach ($tables as $table) {
                            foreach ($table->records as $record) {
                                $device = $record['device_id'] ?? null;
                                $field = $record['_field'] ?? null;
                                $value = $record['_value'] ?? null;
                                
                                if ($device && $field) {
                                    $deviceLower = strtolower($device);
                                    if (!isset($results[$deviceLower])) {
                                        $results[$deviceLower] = [];
                                    }
                                    
                                    $results[$deviceLower][$field] = $value;
                                }
                            }
                        }
                        return $results;
                    });

                    // Map cached metrics dynamically
                    $sensorMap = $clientSensors->keyBy(function($s) { return strtolower($s->device_id); });
                    foreach ($clientInfluxData as $deviceLower => $metrics) {
                        $sensorModel = $sensorMap->get($deviceLower);
                        $originalDevice = $sensorModel ? $sensorModel->device_id : $deviceLower;

                        if ($sensorModel && $sensorModel->last_status === 'online') {
                            if (isset($metrics['temperatura'])) $influxData[$originalDevice]['temperature'] = number_format($metrics['temperatura'], 1);
                            if (isset($metrics['umidade'])) $influxData[$originalDevice]['humidity'] = number_format($metrics['umidade'], 1);
                            if (isset($metrics['rssi'])) $influxData[$originalDevice]['rssi'] = $metrics['rssi'];
                            if (isset($metrics['uptime'])) {
                                $uptime = $metrics['uptime'];
                                if (is_numeric($uptime)) {
                                    $days = floor($uptime / 86400);
                                    $hours = floor(($uptime % 86400) / 3600);
                                    $mins = floor(($uptime % 3600) / 60);
                                    $influxData[$originalDevice]['uptime'] = $days > 0 ? "{$days}d {$hours}h" : "{$hours}h {$mins}m";
                                } else {
                                    $influxData[$originalDevice]['uptime'] = $uptime;
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error("InfluxDB Telemetry Error for Client ID {$clientId}: " . $e->getMessage());
                }
            }
        }

        return response()->json($influxData);
    }
}
