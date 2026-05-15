<?php
require_once __DIR__ . '/includes/auth.php';

try {
    Database::execute('ALTER TABLE contacts MODIFY client_id INT NULL');
    Database::execute('ALTER TABLE contacts ADD COLUMN is_admin TINYINT(1) DEFAULT 0');
    echo "<h1>Sucesso!</h1><p>Banco de dados atualizado. A tabela contacts agora suporta administradores e client_id = NULL.</p>";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<h1>Aviso!</h1><p>A coluna is_admin já existe. O banco já está atualizado.</p>";
    } else {
        echo "<h1>Erro!</h1><p>" . $e->getMessage() . "</p>";
    }
}
