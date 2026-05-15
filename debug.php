<?php
/**
 * Script de diagnóstico para testar a conexão com o banco e o login admin
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: text/plain; charset=utf-8');

echo "--- Diagnóstico PrecogNovo ---\n\n";

// 1. Testar Versão PHP
echo "PHP Version: " . PHP_VERSION . "\n";
echo "APP_URL: " . APP_URL . "\n";

// 2. Testar Extensões
echo "Extensão PDO: " . (extension_loaded('pdo') ? 'OK' : 'MISSING') . "\n";
echo "Extensão PDO MySQL: " . (extension_loaded('pdo_mysql') ? 'OK' : 'MISSING') . "\n";
echo "Extensão cURL: " . (extension_loaded('curl') ? 'OK' : 'MISSING') . "\n";

// Testar Sessão
session_start();
$_SESSION['debug_test'] = 1;
echo "Teste de Sessão: " . (isset($_SESSION['debug_test']) ? 'OK' : 'FAIL') . "\n\n";

// 3. Testar Conexão MySQL
try {
    $db = Database::getInstance()->getConnection();
    echo "Conexão MySQL: OK\n";
    
    // 4. Verificar Tabela Admins
    $admins = Database::query("SELECT username FROM admins");
    echo "Administradores cadastrados: " . count($admins) . "\n";
    foreach ($admins as $a) {
        echo " - " . $a['username'] . "\n";
    }

    // 5. Testar Reset de Senha (Opcional - útil se o usuário esqueceu)
    if (isset($_GET['reset'])) {
        $newPass = 'admin123';
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        Database::execute("UPDATE admins SET password_hash = ? WHERE username = 'admin'", [$hash]);
        echo "\nSENHA DO USUÁRIO 'admin' RESETADA PARA: admin123\n";
    }

} catch (Exception $e) {
    echo "ERRO DE CONEXÃO MYSQL: " . $e->getMessage() . "\n";
}

// 6. Testar InfluxDB
echo "\n--- Teste InfluxDB ---\n";
require_once __DIR__ . '/includes/influxdb.php';
echo "URL: " . INFLUXDB_URL . "\n";
echo "Bucket: " . INFLUXDB_BUCKET . "\n";

try {
    // Tentar buscar qualquer dado dos últimos 10 anos para ver se conecta
    $flux = 'from(bucket: "' . INFLUXDB_BUCKET . '") |> range(start: -10y) |> limit(n:1)';
    $data = InfluxDB::query($flux);
    
    if (empty($data)) {
        echo "Conexão InfluxDB: OK (mas nenhum dado encontrado no bucket)\n";
    } else {
        echo "Conexão InfluxDB: OK\n";
        echo "Últimos campos enviados pelo sensor 'precog_001':\n";
        
        // Query para listar campos únicos do sensor
        $fluxFields = 'from(bucket: "' . INFLUXDB_BUCKET . '") 
            |> range(start: -24h) 
            |> filter(fn: (r) => r["device_id"] == "precog_001")
            |> keep(columns: ["_field"])
            |> unique(column: "_field")';
        
        $fields = InfluxDB::query($fluxFields);
        foreach ($fields as $f) {
            echo " - " . $f['_field'] . "\n";
        }

        echo "\nExemplo de linha completa:\n";
        print_r($data[0]);
    }
} catch (Exception $e) {
    echo "ERRO INFLUXDB: " . $e->getMessage() . "\n";
}

echo "\n--- Fim do Diagnóstico ---";
