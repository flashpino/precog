<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/influxdb.php';

echo "--- RELATÓRIO DE DIAGNÓSTICO ---\n";
echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Sensores no MySQL
echo "1. Sensores cadastrados no MySQL:\n";
$mysqlSensors = Database::query("SELECT s.id, s.device_id, s.label, c.name as client_name FROM sensors s JOIN clients c ON s.client_id = c.id");
foreach ($mysqlSensors as $s) {
    echo "- ID: {$s['id']} | DeviceID: {$s['device_id']} | Label: {$s['label']} | Cliente: {$s['client_name']}\n";
}
echo "\n";

// 2. Sensores enviando dados ao InfluxDB (últimos 30 min)
echo "2. DeviceIDs detectados no InfluxDB (últimos 30 min):\n";
$flux = 'from(bucket: "' . INFLUXDB_BUCKET . '")
    |> range(start: -30m)
    |> keep(columns: ["device_id"])
    |> unique(column: "device_id")';

$influxData = InfluxDB::query($flux);
$influxDevices = [];

if (empty($influxData)) {
    echo "Nenhum dado encontrado no InfluxDB nos últimos 30 minutos.\n";
} else {
    foreach ($influxData as $row) {
        $devId = $row['device_id'] ?? 'desconhecido';
        $influxDevices[] = $devId;
        echo "- {$devId}\n";
    }
}
echo "\n";

// 3. Cruzamento de dados
echo "3. Análise de discrepâncias:\n";
$mysqlDeviceIds = array_column($mysqlSensors, 'device_id');

foreach ($influxDevices as $devId) {
    if (!in_array($devId, $mysqlDeviceIds)) {
        echo "ALERTA: Sensor '{$devId}' está enviando dados mas NÃO está cadastrado no MySQL para nenhum cliente.\n";
    }
}

foreach ($mysqlDeviceIds as $devId) {
    if (!in_array($devId, $influxDevices)) {
        echo "INFO: Sensor '{$devId}' está cadastrado no MySQL mas NÃO enviou dados nos últimos 30 minutos.\n";
    }
}
