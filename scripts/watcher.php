<?php
/**
 * Watcher - Script de monitoramento automático (Background)
 * 
 * Modos de execução:
 *   1. CLI (Cron Job):  php /caminho/para/scripts/watcher.php
 *   2. Web (Cron URL):  GET /scripts/watcher.php?secret=SEU_CRON_SECRET
 *   3. Interno:         define('WATCHER_FORCE_RUN', true); include 'watcher.php';
 */

// Carrega config antes do guard para ter acesso ao CRON_SECRET
require_once __DIR__ . '/../config.php';

// Aumenta o tempo limite de execução para o script não ser interrompido pelos sleeps
@set_time_limit(0);

$isCli = (php_sapi_name() === 'cli');
$isInternal = defined('WATCHER_FORCE_RUN');
$secret = isset($_GET['secret']) ? $_GET['secret'] : '';
$cronSecret = defined('CRON_SECRET') ? CRON_SECRET : null;
$isWebCron = (
    $cronSecret !== null &&
    $secret !== '' &&
    hash_equals($cronSecret, $secret)
);

if (!$isCli && !$isInternal && !$isWebCron) {
    http_response_code(403);
    die("Acesso negado. Use CLI, defina WATCHER_FORCE_RUN ou passe ?secret=TOKEN correto.");
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/influxdb.php';
require_once __DIR__ . '/../includes/functions.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando monitoramento...\n";

try {
    $sensors = Database::query("
        SELECT s.*, c.influx_org, c.influx_bucket, c.influx_token
        FROM sensors s 
        JOIN clients c ON s.client_id = c.id 
        WHERE s.is_active = 1
    ");
    echo "Monitorando " . count($sensors) . " sensores.\n";

    foreach ($sensors as $s) {
        $deviceId = $s['device_id'];
        
        $tMax = (float) $s['temp_max'];
        $tMin = (float) $s['temp_min'];

        // 2. Buscar última leitura no InfluxDB usando as credenciais do cliente
        $reading = InfluxDB::getLatestReading($deviceId, $s['influx_bucket'], $s['influx_org'], $s['influx_token']);
        $temp = ($reading['temperature'] !== null) ? (float) $reading['temperature'] : null;
        $hum = ($reading['humidity'] !== null) ? (float) $reading['humidity'] : null;

        $lastReadingTime = isset($reading['time']) ? strtotime($reading['time']) : null;

        $isNowOnline = false;

        if ($lastReadingTime) {
            // InfluxDB sempre retorna UTC. Forçamos a comparação com time() (que é UTC)
            $diffMin = (time() - $lastReadingTime) / 60;
            $isNowOnline = ($diffMin <= 5); 
            echo "DEBUG: Sensor {$deviceId} | Diferença Influx: " . round($diffMin, 1) . " min\n";
        }
        // Trata NULL como 'offline' para sensores novos (nunca processados)
        $oldStatus = $s['last_status'] ?? 'offline';

        // Lógica de Eventos de Conexão
        if ($isNowOnline) {

            // === 1. Transição offline → online (quedas longas, >5 min) ===
            if ($oldStatus === 'offline') {
                echo " - Sensor {$deviceId}: VOLTOU ONLINE (estava offline)!\n";
                
                Database::reconnectIfGoneAway();

                $rebootMsg = "Sensor {$s['label']} estabeleceu conexão.";
                Database::execute(
                    "INSERT INTO events (sensor_id, type, message) VALUES (?, 'info', ?)",
                    [$s['id'], $rebootMsg]
                );

                $phones = getContactsForAlert($s['client_id'], 'online');
                $client = Database::queryOne("SELECT name, company FROM clients WHERE id = ?", [$s['client_id']]);

                $payload = [
                    'client' => $client['company'] ?: $client['name'],
                    'sensor' => $s['device_id'],
                    'location' => $s['location'],
                    'label' => $s['label'],
                    'type' => 'online',
                    'message' => $rebootMsg,
                    'ip' => $reading['ip'],
                    'mac' => $reading['mac'],
                    'contacts' => $phones,
                    'timestamp' => date('Y-m-d\TH:i:s'),
                ];
                $webhookResult = triggerWebhook($payload);
                echo " - Webhook online: " . ($webhookResult ? 'ENVIADO' : 'FALHOU') . "\n";
                
                if ($webhookResult) {
                    echo " - Aguardando 30s para o próximo sensor...\n";
                    sleep(30);
                }
            }

            // === 2. Detecção de reboot rápido (quedas curtas, >30s) ===
            // Analisa gap entre leituras consecutivas no InfluxDB.
            // ESP32 envia a cada ~11s. Gap > 30s = reboot/queda de energia.
            $rebootInfo = InfluxDB::detectReboot($deviceId, $s['influx_bucket'], $s['influx_org'], $s['influx_token']);
            
            if ($rebootInfo) {
                $gapSec = $rebootInfo['gap_seconds'];
                $rebootTime = $rebootInfo['time_after'];
                $gapMin = round($gapSec / 60, 1);
                
                // Verifica se já alertamos este reboot específico (pelo timestamp)
                $uniqueMsg = "Reboot detectado às {$rebootTime} (gap de {$gapSec}s)";
                
                Database::reconnectIfGoneAway();
                $alreadyAlerted = Database::queryOne(
                    "SELECT id FROM events WHERE sensor_id = ? AND message LIKE ? LIMIT 1",
                    [$s['id'], "%{$rebootTime}%"]
                );

                if (!$alreadyAlerted) {
                    echo " - Sensor {$deviceId}: REBOOT DETECTADO! Gap de {$gapSec}s nos dados.\n";

                    Database::execute(
                        "INSERT INTO events (sensor_id, type, message) VALUES (?, 'warning', ?)",
                        [$s['id'], $uniqueMsg]
                    );

                    $phones = getContactsForAlert($s['client_id'], 'esp32_reboot');
                    $client = Database::queryOne("SELECT name, company FROM clients WHERE id = ?", [$s['client_id']]);

                    $payload = [
                        'client' => $client['company'] ?: $client['name'],
                        'sensor' => $s['device_id'],
                        'location' => $s['location'],
                        'label' => $s['label'],
                        'type' => 'esp32_reboot',
                        'message' => "Sensor {$s['label']} reiniciou (interrupção de {$gapSec}s detectada).",
                        'gap_seconds' => $gapSec,
                        'ip' => $reading['ip'],
                        'mac' => $reading['mac'],
                        'contacts' => $phones,
                        'timestamp' => date('Y-m-d\TH:i:s'),
                    ];
                    $webhookResult = triggerWebhook($payload);
                    echo " - Webhook reboot: " . ($webhookResult ? 'ENVIADO' : 'FALHOU') . "\n";

                    if ($webhookResult) {
                        echo " - Aguardando 30s para o próximo sensor...\n";
                        sleep(30);
                    }
                } else {
                    echo " - Sensor {$deviceId}: Reboot em {$rebootTime} já alertado.\n";
                }
            }
            
            // Atualiza status
            Database::reconnectIfGoneAway();
            Database::execute("UPDATE sensors SET last_seen = NOW(), last_status = 'online' WHERE id = ?", [$s['id']]);
        } else {
            // Sensor está OFFLINE (Sem dados recentes no InfluxDB)
            
            // 1. Tenta atualizar o status para offline se necessário
            if ($oldStatus === 'online' && !empty($s['last_seen'])) {
                $lastSeen = new DateTime($s['last_seen'], new DateTimeZone('UTC'));
                $diff = round((time() - $lastSeen->getTimestamp()) / 60, 1);
                echo " - Sensor {$deviceId}: Sem dados há {$diff} min (limite: 5 min).\n";

                if ($diff > 5) {
                    Database::reconnectIfGoneAway();
                    Database::execute("UPDATE sensors SET last_status = 'offline' WHERE id = ?", [$s['id']]);
                    $s['last_status'] = 'offline'; // Atualiza na variável para o próximo passo
                    $oldStatus = 'offline';
                    echo " - Sensor {$deviceId}: Status atualizado para OFFLINE no BD.\n";
                }
            }

            // 2. Só processa alerta se o status for (ou tiver acabado de virar) OFFLINE
            if ($oldStatus === 'offline') {

                // Verifica o último evento de offline para cooldown
                $lastOfflineEvent = Database::queryOne(
                    "SELECT created_at FROM events
                     WHERE sensor_id = ? AND type = 'warning' AND message LIKE '%perdeu a conexão%'
                     ORDER BY created_at DESC LIMIT 1",
                    [$s['id']]
                );

                $podaEnviar = true;
                if ($lastOfflineEvent) {
                    $lastEvTime = new DateTime($lastOfflineEvent['created_at'], new DateTimeZone('UTC'));
                    $minutosSinceLastAlert = (time() - $lastEvTime->getTimestamp()) / 60;
                    $cooldownConn = 5;
                    $podaEnviar = ($minutosSinceLastAlert >= $cooldownConn);
                    echo " - Sensor {$deviceId}: Último alerta offline há " . round($minutosSinceLastAlert, 1) . " min.\n";
                }

                if ($podaEnviar) {
                    echo " - Sensor {$deviceId}: OFFLINE — Disparando alerta...\n";

                    Database::reconnectIfGoneAway();
                    Database::execute(
                        "INSERT INTO events (sensor_id, type, message) VALUES (?, 'warning', ?)",
                        [$s['id'], "Sensor {$s['label']} perdeu a conexão com o servidor."]
                    );

                    $phones = getContactsForAlert($s['client_id'], 'offline');
                    $client = Database::queryOne("SELECT name, company FROM clients WHERE id = ?", [$s['client_id']]);

                    $payload = [
                        'client'    => $client['company'] ?: $client['name'],
                        'sensor'    => $s['device_id'],
                        'location'  => $s['location'],
                        'label'     => $s['label'],
                        'type'      => 'offline',
                        'message'   => "Sensor {$s['label']} perdeu a conexão com o servidor.",
                        'ip'        => $reading['ip'],
                        'mac'       => $reading['mac'],
                        'contacts'  => $phones,
                        'timestamp' => date('Y-m-d\TH:i:s'),
                    ];
                    $webhookResult = triggerWebhook($payload);
                    echo " - Webhook offline: " . ($webhookResult ? 'ENVIADO com sucesso' : 'FALHOU') . "\n";

                    if ($webhookResult) {
                        echo " - Aguardando 30s para o próximo sensor...\n";
                        sleep(30);
                    }
                } else {
                    echo " - Sensor {$deviceId}: OFFLINE — aguardando cooldown.\n";
                }
            }
        }



        echo "DEBUG: Sensor {$deviceId} | LIDO: {$temp}°C | STATUS: " . ($isNowOnline ? 'Online' : 'Offline') . "\n";

        if ($temp === null) {
            echo " - Sensor {$deviceId}: Sem dados de temperatura.\n";
            continue;
        }

        // 3. Verificar limites
        // Vamos conferir o cooldown aqui no watcher para te mostrar o motivo
        $lastAlert = Database::queryOne(
            "SELECT created_at FROM alerts WHERE sensor_id = ? AND type = 'temp_high' ORDER BY created_at DESC LIMIT 1",
            [$s['id']]
        );

        $cooldownAtivo = false;
        if ($lastAlert) {
            // Forçamos UTC
            $lastTime = new DateTime($lastAlert['created_at'], new DateTimeZone('UTC'));
            $diff = (time() - $lastTime->getTimestamp()) / 60;
            $cooldownThresh = 5;
            if ($diff < $cooldownThresh) {
                $cooldownAtivo = true;
                $restante = round($cooldownThresh - $diff, 1);
            }
        }

        $alertsTriggered = checkThresholds(
            $s,
            $temp,
            $hum,
            isset($reading['ip']) ? $reading['ip'] : null,
            isset($reading['mac']) ? $reading['mac'] : null,
            5
        );

        if (!empty($alertsTriggered)) {
            echo " - Sensor {$deviceId}: !!! ALERTA DISPARADO COM SUCESSO !!!\n";
            echo " - Aguardando 30s para o próximo sensor...\n";
            sleep(30);
        } else {
            $isOutOfRange = ($temp > $tMax || $temp < $tMin);
            if ($isOutOfRange && $cooldownAtivo) {
                echo " - Sensor {$deviceId}: FORA DO LIMITE! (Aguarde {$restante} min para o próximo alerta).\n";
            } elseif ($isOutOfRange) {
                echo " - Sensor {$deviceId}: FORA DO LIMITE! (Tentou enviar mas houve erro no Webhook - verifique o config.php).\n";
            } else {
                echo " - Sensor {$deviceId}: Dentro dos limites.\n";
            }
        }
    }

} catch (Exception $e) {
    echo "ERRO CRÍTICO NO WATCHER: " . $e->getMessage() . "\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Monitoramento concluído.\n";
