<?php
require_once __DIR__ . '/../includes/functions.php';

try {
    echo "Adicionando campos de InfluxDB na tabela 'clients'...\n";
    
    Database::execute("ALTER TABLE clients ADD COLUMN IF NOT EXISTS influx_org VARCHAR(100) DEFAULT NULL");
    Database::execute("ALTER TABLE clients ADD COLUMN IF NOT EXISTS influx_bucket VARCHAR(100) DEFAULT NULL");
    Database::execute("ALTER TABLE clients ADD COLUMN IF NOT EXISTS influx_token TEXT DEFAULT NULL");
    
    // Opcional: Preencher com os valores padrão do config.php para não quebrar os clientes existentes
    require_once __DIR__ . '/../config.php';
    Database::execute("UPDATE clients SET influx_org = ?, influx_bucket = ?, influx_token = ? WHERE influx_org IS NULL", [INFLUXDB_ORG, INFLUXDB_BUCKET, INFLUXDB_TOKEN]);

    echo "Sucesso!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
