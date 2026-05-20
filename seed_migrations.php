<?php
$mysqli = new mysqli('212.1.210.112', 'asfindbr_esp32', 'nminfo*4990', 'asfindbr_precog');
if ($mysqli->connect_error) die('Connection failed: ' . $mysqli->connect_error);

$mysqli->query("CREATE TABLE IF NOT EXISTS migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INT NOT NULL
)");

$migrations = [
    '0001_01_01_000000_create_users_table',
    '0001_01_01_000001_create_cache_table',
    '0001_01_01_000002_create_jobs_table',
    '2026_01_01_000010_create_admins_table',
    '2026_01_01_000020_create_clients_table',
    '2026_01_01_000030_create_sensors_table',
    '2026_01_01_000040_create_contacts_table',
    '2026_01_01_000050_create_alerts_table',
    '2026_01_01_000060_create_events_table',
    '2026_01_01_000070_create_contact_alert_preferences_table',
    '2026_01_01_000080_create_sent_alerts_log_table',
];

foreach ($migrations as $m) {
    $mysqli->query("INSERT IGNORE INTO migrations (migration, batch) VALUES ('$m', 1)");
}
echo "Migrations seeded!\n";
