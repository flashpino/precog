<?php
/**
 * API - Retorna leituras do InfluxDB para um sensor
 * GET /api/readings.php?token=XXX&device_id=YYY&range=-24h&field=temperature
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/influxdb.php';
require_once __DIR__ . '/../includes/functions.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

$token    = isset($_GET['token']) ? $_GET['token'] : '';
$deviceId = isset($_GET['device_id']) ? $_GET['device_id'] : '';
$range    = isset($_GET['range']) ? $_GET['range'] : '-24h';
$field    = isset($_GET['field']) ? $_GET['field'] : '';
$action   = isset($_GET['action']) ? $_GET['action'] : 'history';

// Validar token
$client = Auth::validateClientToken($token);
if (!$client) {
    jsonResponse(['error' => 'Token inválido'], 401);
}

// Validar que o sensor pertence ao cliente
if (!empty($deviceId)) {
    $sensor = Database::queryOne(
        "SELECT * FROM sensors WHERE device_id = ? AND client_id = ? AND is_active = 1",
        [$deviceId, $client['id']]
    );
    if (!$sensor) {
        jsonResponse(['error' => 'Sensor não encontrado'], 404);
    }
}

// Rota por ação
switch ($action) {
    case 'latest':
        // Leitura mais recente de todos os sensores do cliente
        $sensors = Database::query(
            "SELECT * FROM sensors WHERE client_id = ? AND is_active = 1",
            [$client['id']]
        );

        $readings = [];
        foreach ($sensors as $s) {
            $reading = InfluxDB::getLatestReading(
                $s['device_id'], 
                $client['influx_bucket'], 
                $client['influx_org'], 
                $client['influx_token']
            );
            $online = InfluxDB::isSensorOnline(
                $s['device_id'], 
                $client['influx_bucket'], 
                $client['influx_org'], 
                $client['influx_token']
            );

            $readings[] = [
                'device_id'   => $s['device_id'],
                'label'       => $s['label'],
                'location'    => $s['location'],
                'temperature' => $reading['temperature'],
                'humidity'    => $reading['humidity'],
                'time'        => $reading['time'],
                'online'      => $online,
                'limits'      => [
                    'temp_min' => floatval($s['temp_min']),
                    'temp_max' => floatval($s['temp_max']),
                    'hum_min'  => floatval($s['hum_min']),
                    'hum_max'  => floatval($s['hum_max']),
                ],
            ];

            // Verificar limites e disparar alertas se necessário
            if ($reading['temperature'] !== null || $reading['humidity'] !== null) {
                checkThresholds(
                    $s, 
                    $reading['temperature'], 
                    $reading['humidity'],
                    isset($reading['ip']) ? $reading['ip'] : null,
                    isset($reading['mac']) ? $reading['mac'] : null
                );
            }
        }

        jsonResponse([
            'client'   => $client['name'],
            'company'  => $client['company'],
            'readings' => $readings,
            'timestamp' => date('Y-m-d\TH:i:s'),
        ]);
        break;

    case 'history':
        // Histórico para gráfico
        if (empty($deviceId) || empty($field)) {
            jsonResponse(['error' => 'device_id e field são obrigatórios'], 400);
        }

        $allowedRanges = ['-1h', '-6h', '-24h', '-7d', '-30d'];
        if (!in_array($range, $allowedRanges)) $range = '-24h';

        $allowedFields = ['temperature', 'humidity'];
        if (!in_array($field, $allowedFields)) $field = 'temperature';

        $data = InfluxDB::getHistory(
            $deviceId, 
            $range, 
            $field,
            $client['influx_bucket'], 
            $client['influx_org'], 
            $client['influx_token']
        );

        jsonResponse([
            'device_id' => $deviceId,
            'field'     => $field,
            'range'     => $range,
            'data'      => $data,
        ]);
        break;

    default:
        jsonResponse(['error' => 'Ação inválida'], 400);
}
