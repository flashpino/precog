<?php
/**
 * API - Sumário Diário para n8n
 * Retorna todos os contatos ativos e a temperatura atual dos seus respectivos sensores.
 * Acesso: GET /api/daily_summary.php?secret=TOKEN
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/influxdb.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// Validar secret
$secret = isset($_GET['secret']) ? $_GET['secret'] : '';
if ($secret !== CRON_SECRET) {
    jsonResponse(['error' => 'Acesso negado'], 403);
}

// 1. Buscar todos os clientes ativos
$clients = Database::query("SELECT id, name, company, influx_org, influx_bucket, influx_token FROM clients WHERE is_active = 1");

$notifications = [];

foreach ($clients as $client) {
    // 2. Buscar contatos ativos do cliente
    $contacts = Database::query(
        "SELECT name, phone FROM contacts WHERE client_id = ? AND is_active = 1",
        [$client['id']]
    );

    if (empty($contacts)) continue;

    // 3. Buscar sensores ativos do cliente
    $sensors = Database::query(
        "SELECT device_id, label, location FROM sensors WHERE client_id = ? AND is_active = 1",
        [$client['id']]
    );

    if (empty($sensors)) continue;

    // 4. Buscar leituras e preparar retorno por sensor
    foreach ($sensors as $s) {
        $reading = InfluxDB::getLatestReading(
            $s['device_id'],
            $client['influx_bucket'],
            $client['influx_org'],
            $client['influx_token']
        );

        if ($reading['temperature'] !== null) {
            $notifications[] = [
                'device_id'   => $s['device_id'],
                'label'       => $s['label'],
                'location'    => $s['location'],
                'temperature' => $reading['temperature'],
                'humidity'    => $reading['humidity'],
                'time'        => formatDateRelative($reading['time']),
                'client'      => $client['company'] ?: $client['name'],
                'contacts'    => $contacts, // Lista de todos os contatos que devem receber este sensor
                'timestamp'   => date('Y-m-d H:i:s')
            ];
        }
    }
}

jsonResponse($notifications);
