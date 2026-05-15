<?php
require_once __DIR__ . '/../includes/db.php';
$events = Database::query("SELECT e.*, s.device_id FROM events e JOIN sensors s ON e.sensor_id = s.id ORDER BY e.created_at DESC LIMIT 15");
print_r($events);

$sensors = Database::query("SELECT id, device_id, last_status, last_seen FROM sensors");
print_r($sensors);
