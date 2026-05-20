<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Sensor;
use App\Models\Alert;
use Illuminate\Http\Request;
use InfluxDB2\Client as InfluxClient;

class ClientDashboardController extends Controller
{
    public function show($token)
    {
        $clientModel = Client::where('token', $token)->where('is_active', true)->firstOrFail();

        $sensors = Sensor::where('client_id', $clientModel->id)->orderBy('last_status', 'asc')->get();
        $recentAlerts = Alert::with('sensor')
                             ->whereIn('sensor_id', $sensors->pluck('id'))
                             ->latest()
                             ->take(10)
                             ->get();

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
            try {
                $url = config('influxdb.url');
                $org = $clientModel->influx_org ?: config('influxdb.org');
                $bucketRaw = $clientModel->influx_bucket ?: config('influxdb.bucket');
                $bucket = preg_replace('/[^a-zA-Z0-9_.-]/', '', $bucketRaw);
                $influxToken = $clientModel->influx_token ?: config('influxdb.token');

                if ($url && $influxToken) {
                    $sensorMap = $sensors->keyBy(function($s) { return strtolower($s->device_id); });

                    $influx = \App\Services\InfluxConnectionHelper::createClient($url, $org, $influxToken);
                    $queryApi = $influx->createQueryApi();
                    $deviceIds = $sensors->pluck('device_id')->toArray();
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
                    foreach ($tables as $table) {
                        foreach ($table->records as $record) {
                            $device = $record['device_id'] ?? null;
                            $field = $record['_field'] ?? null;
                            $value = $record['_value'] ?? null;
                            
                            if ($device && $field) {
                                $deviceLower = strtolower($device);
                                $sensorModel = $sensorMap->get($deviceLower);
                                $originalDevice = $sensorModel ? $sensorModel->device_id : $device;

                                if ($sensorModel && $sensorModel->last_status === 'online') {
                                    if ($field === 'temperatura') $influxData[$originalDevice]['temperature'] = $value;
                                    if ($field === 'umidade') $influxData[$originalDevice]['humidity'] = $value;
                                    if ($field === 'uptime') $influxData[$originalDevice]['uptime'] = $value;
                                    if ($field === 'rssi') $influxData[$originalDevice]['rssi'] = $value;
                                    
                                    if (isset($record['mac'])) {
                                        $influxData[$originalDevice]['mac'] = $record['mac'];
                                    } elseif (isset($record['mac_address'])) {
                                        $influxData[$originalDevice]['mac'] = $record['mac_address'];
                                    }
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error("InfluxDB Client Dashboard Error: " . $e->getMessage());
            }
        }

        return view('client.dashboard', compact('clientModel', 'sensors', 'recentAlerts', 'influxData'));
    }
}
