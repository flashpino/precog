<?php
require_once __DIR__ . '/../includes/db.php';

try {
    echo "Atualizando tabela 'sensors'...\n";
    
    // Adiciona colunas para rastrear status se não existirem
    Database::execute("ALTER TABLE sensors ADD COLUMN IF NOT EXISTS last_seen TIMESTAMP NULL");
    Database::execute("ALTER TABLE sensors ADD COLUMN IF NOT EXISTS last_status ENUM('online', 'offline') DEFAULT 'offline'");
    
    echo "Sucesso! O banco de dados está pronto para registrar eventos de status.\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
