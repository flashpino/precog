<?php
/**
 * API - Retorna alertas de um cliente
 * GET /api/alerts.php?token=XXX&limit=20
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$token = isset($_GET['token']) ? $_GET['token'] : '';
$limit = min(intval(isset($_GET['limit']) ? $_GET['limit'] : 10), 50);

// Validar token
$client = Auth::validateClientToken($token);
if (!$client) {
    jsonResponse(['error' => 'Token inválido'], 401);
}

// Buscar alertas recentes dos sensores deste cliente
$alertsRaw = Database::query(
    "SELECT a.*, s.device_id, s.label, s.location 
     FROM alerts a 
     JOIN sensors s ON a.sensor_id = s.id 
     WHERE s.client_id = ? 
     ORDER BY a.created_at DESC 
     LIMIT ?",
    [$client['id'], $limit]
);

$alerts = [];
foreach ($alertsRaw as $al) {
    $al['created_at_raw'] = $al['created_at'];
    $al['created_at'] = formatDateRelative($al['created_at']);
    $alerts[] = $al;
}

// Buscar eventos recentes
$eventsRaw = Database::query(
    "SELECT e.*, s.device_id, s.label 
     FROM events e 
     LEFT JOIN sensors s ON e.sensor_id = s.id 
     WHERE (s.client_id = ? OR e.sensor_id IS NULL) 
     AND e.message NOT LIKE '%reiniciou (interrupção%'
     ORDER BY e.created_at DESC 
     LIMIT ?",
    [$client['id'], $limit]
);

$events = [];
foreach ($eventsRaw as $ev) {
    $ev['created_at_raw'] = $ev['created_at'];
    $ev['created_at'] = formatDateRelative($ev['created_at']);
    $events[] = $ev;
}

// Contar alertas das últimas 24h
$alertCount24h = Database::queryOne(
    "SELECT COUNT(*) as total FROM alerts a 
     JOIN sensors s ON a.sensor_id = s.id 
     WHERE s.client_id = ? AND a.created_at >= NOW() - INTERVAL 24 HOUR",
    [$client['id']]
);

jsonResponse([
    'alerts'         => $alerts,
    'events'         => $events,
    'alerts_today'   => intval($alertCount24h['total']),
    'timestamp'      => date('Y-m-d\TH:i:s'),
]);
