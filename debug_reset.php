<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/influxdb.php';

$deviceId = $_GET['device_id'] ?? '';

if (empty($deviceId)) {
    die("Passe device_id via GET");
}

echo "<h1>Debug InfluxDB para {$deviceId}</h1>";

echo "<h2>Últimas Leituras (getLatestReading):</h2>";
$latest = InfluxDB::getLatestReading($deviceId);
print_r($latest);

echo "<h2>Último Reinício (getLatestResetReason):</h2>";
$reset = InfluxDB::getLatestResetReason($deviceId);
if ($reset) {
    print_r($reset);
} else {
    echo "Nenhum evento de reinício encontrado na última 1 hora.";
}

echo "<h2>Query Crua para reset_event (últimas 24h):</h2>";
$flux = 'from(bucket: "' . INFLUXDB_BUCKET . '")
    |> range(start: -24h)
    |> filter(fn: (r) => r._measurement == "reset_event")
    |> filter(fn: (r) => r["device_id"] == "' . $deviceId . '")';

$data = InfluxDB::query($flux);
echo "<pre>";
print_r($data);
echo "</pre>";

echo "<h2>Últimos 10 Eventos no Banco:</h2>";
$events = Database::query("SELECT * FROM events ORDER BY id DESC LIMIT 10");
echo "<pre>";
print_r($events);
echo "</pre>";
