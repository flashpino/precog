<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/influxdb.php';

// Log de eventos recentes
$events = Database::query("SELECT * FROM events ORDER BY id DESC LIMIT 20");
file_put_contents(__DIR__ . '/debug_output.txt', "--- EVENTOS RECENTES ---\n" . print_r($events, true));

// Tentar buscar reset reason de algum sensor ativo
$sensors = Database::query("SELECT device_id, influx_bucket, influx_org, influx_token FROM sensors WHERE is_active = 1");
$resetLogs = "\n--- CHECK RESET REASONS ---\n";
foreach ($sensors as $s) {
    $reset = InfluxDB::getLatestResetReason($s['device_id'], $s['influx_bucket'], $s['influx_org'], $s['influx_token']);
    $resetLogs .= "Sensor {$s['device_id']}: " . ($reset ? print_r($reset, true) : "Nenhum reset na última hora\n");
}
file_put_contents(__DIR__ . '/debug_output.txt', $resetLogs, FILE_APPEND);
