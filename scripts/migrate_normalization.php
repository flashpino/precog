<?php
require_once __DIR__ . '/../includes/db.php';

echo "Iniciando migração de banco de dados...\n";

try {
    // Adiciona coluna para estado de temperatura
    Database::execute("ALTER TABLE sensors ADD COLUMN alert_state_temp ENUM('normal', 'high', 'low') DEFAULT 'normal' AFTER hum_max");
    echo " - Coluna 'alert_state_temp' adicionada.\n";

    // Adiciona coluna para estado de umidade
    Database::execute("ALTER TABLE sensors ADD COLUMN alert_state_hum ENUM('normal', 'high', 'low') DEFAULT 'normal' AFTER alert_state_temp");
    echo " - Coluna 'alert_state_hum' adicionada.\n";

    echo "Migração concluída com sucesso!\n";
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    echo "Nota: Se o erro for 'Duplicate column name', as colunas já existem.\n";
}
