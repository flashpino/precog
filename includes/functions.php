<?php
/**
 * Funções auxiliares gerais
 */

/**
 * Retorna JSON e encerra
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Sanitiza input
 */
function sanitize($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Formata data para exibição BR
 */
function formatDateBR($datetime) {
    if (empty($datetime)) return '-';
    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $dt->format('d/m/Y H:i');
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Formata data relativa (Hoje, Ontem, etc.)
 */
function formatDateRelative($datetime) {
    if (empty($datetime)) return '-';
    try {
        $dt = new DateTime($datetime, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        
        $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        $today = $now->format('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day', strtotime($today)));
        
        $dtDay = $dt->format('Y-m-d');

        if ($dtDay === $today) return 'Hoje, ' . $dt->format('H:i');
        if ($dtDay === $yesterday) return 'Ontem, ' . $dt->format('H:i');
        return $dt->format('d/m/Y') . ', ' . $dt->format('H:i');
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Verifica se pode disparar um novo alerta (anti-spam)
 */
function canTriggerAlert($sensorId, $type, $cooldownMinutes = null) {
    require_once __DIR__ . '/db.php';
    
    // Fallback para o padrão global se não informado ou inválido
    if ($cooldownMinutes === null || $cooldownMinutes <= 0) {
        $cooldownMinutes = defined('ALERT_COOLDOWN_MINUTES') ? ALERT_COOLDOWN_MINUTES : 5;
    }
    
    $lastAlert = Database::queryOne(
        "SELECT created_at FROM alerts 
         WHERE sensor_id = ? AND type = ? 
         ORDER BY created_at DESC LIMIT 1",
        [$sensorId, $type]
    );

    if (!$lastAlert) return true;

    $lastTime = new DateTime($lastAlert['created_at'], new DateTimeZone('UTC'));
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $diffMinutes = ($now->getTimestamp() - $lastTime->getTimestamp()) / 60;

    return $diffMinutes >= $cooldownMinutes;
}

/**
 * Dispara webhook para o n8n
 */
function triggerWebhook($payload) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => N8N_WEBHOOK_URL,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("Webhook error: " . $error);
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("Webhook rejected: Code " . $httpCode . " | Resp: " . $response);
        return false;
    }

    return true;
}

/**
 * Verifica se o momento atual (America/Sao_Paulo) está dentro do horário de envio de alertas do cliente.
 */
function isWithinAlertSchedule($startTime, $endTime, $daysCsv) {
    if (empty($startTime) || empty($endTime) || empty($daysCsv)) {
        return true; // Se não configurado, envia sempre
    }

    try {
        $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
        $currentDay = $now->format('w'); // 0 (Domingo) a 6 (Sábado)
        $currentTime = $now->format('H:i:s');

        $allowedDays = explode(',', $daysCsv);
        if (!in_array($currentDay, $allowedDays)) {
            return false;
        }

        if ($startTime <= $endTime) {
            // Horário normal (ex: 07:00 as 17:00)
            if ($currentTime < $startTime || $currentTime > $endTime) {
                return false;
            }
        } else {
            // Horário noturno (ex: 22:00 as 06:00)
            if ($currentTime < $startTime && $currentTime > $endTime) {
                return false;
            }
        }

        return true;
    } catch (Exception $e) {
        return true; // Em caso de erro de parse, fallback para enviar
    }
}

/**
 * Obtém a lista de telefones de contatos que devem receber um determinado alerta.
 * Aplica filtros de preferências individuais (tipo de alerta, horário, dias da semana, intervalo mínimo).
 */
function getContactsForAlert($clientId, $alertType) {
    require_once __DIR__ . '/db.php';
    
    // Mapeia o tipo de alerta interno para a categoria de preferência
    $typeGroupMap = [
        'esp32_reboot' => 'connectivity',
        'online' => 'connectivity',
        'offline' => 'connectivity',
        'temp_high' => 'temperature',
        'temp_low' => 'temperature',
        'normalized_temperature' => 'temperature',
        'hum_high' => 'humidity',
        'hum_low' => 'humidity',
        'normalized_humidity' => 'humidity',
        'test' => 'test'
    ];

    $category = $typeGroupMap[$alertType] ?? 'connectivity';
    
    $contacts = Database::query("SELECT id, phone, is_admin FROM contacts WHERE (client_id = ? OR is_admin = 1) AND is_active = 1", [$clientId]);
    $validPhones = [];
    
    try {
        $now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
    } catch (Exception $e) {
        $now = new DateTime('now');
    }
    
    $currentDay = $now->format('w'); // 0 (Dom) - 6 (Sab)
    $currentTime = $now->format('H:i:s');

    foreach ($contacts as $contact) {
        $contactId = $contact['id'];
        
        // Administradores sempre recebem alertas de queda de equipamento incondicionalmente
        if ($contact['is_admin'] == 1 && $alertType === 'esp32_reboot') {
            $validPhones[] = $contact['phone'];
            continue;
        }
        
        // Verifica se o contato possui ALGUMA preferência configurada
        $hasPrefs = Database::queryOne("SELECT id FROM contact_alert_preferences WHERE contact_id = ? LIMIT 1", [$contactId]);
        
        if (!$hasPrefs && $category !== 'test') {
            // Contato não migrado/sem preferências = recebe tudo (fallback legado)
            $validPhones[] = $contact['phone'];
            continue;
        }

        if ($category === 'test') {
            $validPhones[] = $contact['phone'];
            continue;
        }

        // Busca preferência ESPECÍFICA desta categoria
        $pref = Database::queryOne(
            "SELECT * FROM contact_alert_preferences WHERE contact_id = ? AND alert_type = ?",
            [$contactId, $category]
        );

        if (!$pref) {
            continue;
        }

        // Verifica Dias da Semana
        $allowedDays = explode(',', $pref['days_of_week']);
        if (!in_array($currentDay, $allowedDays)) {
            continue;
        }

        // Verifica Horário
        $start = $pref['time_start'];
        $end = $pref['time_end'];
        
        $isTimeValid = false;
        if ($start <= $end) {
            if ($currentTime >= $start && $currentTime <= $end) $isTimeValid = true;
        } else {
            if ($currentTime >= $start || $currentTime <= $end) $isTimeValid = true;
        }

        if (!$isTimeValid) {
            continue;
        }

        // Verifica Intervalo Mínimo
        $minIntervalMinutes = intval($pref['min_interval']);
        if ($minIntervalMinutes > 0) {
            $lastSent = Database::queryOne(
                "SELECT sent_at FROM sent_alerts_log WHERE contact_id = ? AND alert_type = ? ORDER BY sent_at DESC LIMIT 1",
                [$contactId, $category]
            );

            if ($lastSent) {
                try {
                    // sent_at no banco está como current_timestamp, que pode ser UTC dependendo do mysql. 
                    // Melhor usar a mesma timezone da inserção. Vamos assumir UTC e converter.
                    $lastTime = new DateTime($lastSent['sent_at']);
                    $nowUtc = new DateTime('now');
                    $diffMinutes = ($nowUtc->getTimestamp() - $lastTime->getTimestamp()) / 60;
                    
                    if ($diffMinutes < $minIntervalMinutes) {
                        continue;
                    }
                } catch(Exception $e) {}
            }
        }

        // Registra log para throttling futuro
        Database::execute(
            "INSERT INTO sent_alerts_log (contact_id, alert_type) VALUES (?, ?)",
            [$contactId, $category]
        );
        $validPhones[] = $contact['phone'];
    }

    return $validPhones;
}

/**
 * Verifica leitura contra limites e gera alertas se necessário
 */
function checkThresholds($sensor, $temperature, $humidity, $ip = null, $mac = null, $cooldownMinutes = null) {
    require_once __DIR__ . '/db.php';

    $alertsTriggered = [];

    // Mapeamento de métricas para colunas de estado
    $metrics = [
        'temperature' => [
            'value' => $temperature,
            'max' => $sensor['temp_max'],
            'min' => $sensor['temp_min'],
            'state_col' => 'alert_state_temp',
            'high_type' => 'temp_high',
            'low_type' => 'temp_low',
            'high_msg' => 'Temperatura acima do limite',
            'low_msg' => 'Temperatura abaixo do limite'
        ],
        'humidity' => [
            'value' => $humidity,
            'max' => $sensor['hum_max'],
            'min' => $sensor['hum_min'],
            'state_col' => 'alert_state_hum',
            'high_type' => 'hum_high',
            'low_type' => 'hum_low',
            'high_msg' => 'Umidade acima do limite',
            'low_msg' => 'Umidade abaixo do limite'
        ]
    ];

    foreach ($metrics as $metricName => $m) {
        if ($m['value'] === null) continue;

        $currentVal = (float)$m['value'];
        $max = (float)$m['max'];
        $min = (float)$m['min'];
        $oldState = $sensor[$m['state_col']] ?? 'normal';
        
        $newState = 'normal';
        $alertType = null;
        $alertMsg = null;

        // Determinar estado atual (sem margem para alerta)
        if ($currentVal > $max) {
            $newState = 'high';
            $alertType = $m['high_type'];
            $alertMsg = $m['high_msg'];
        } elseif ($currentVal < $min) {
            $newState = 'low';
            $alertType = $m['low_type'];
            $alertMsg = $m['low_msg'];
        }

        // 1. Mudança de Estado
        if ($newState !== $oldState) {
            
            if ($newState === 'normal') {
                // Hysteresis: Margem para evitar "flapping" na normalização
                // Se estava alto, precisa descer 0.5 (temp) ou 2 (hum) abaixo do max
                // Se estava baixo, precisa subir 0.5 (temp) ou 2 (hum) acima do min
                $margin = ($metricName === 'temperature') ? 0.5 : 2.0;
                $canNormalize = false;

                if ($oldState === 'high' && $currentVal <= ($max - $margin)) {
                    $canNormalize = true;
                } elseif ($oldState === 'low' && $currentVal >= ($min + $margin)) {
                    $canNormalize = true;
                }

                if ($canNormalize) {
                    // NORMALIZAÇÃO: Estava em alerta e agora voltou ao normal com margem de segurança
                    $normMsg = ($metricName === 'temperature') ? "Temperatura normalizada: {$currentVal}°C" : "Umidade normalizada: {$currentVal}%";
                
                    Database::execute(
                        "INSERT INTO events (sensor_id, type, message) VALUES (?, 'info', ?)",
                        [$sensor['id'], "Sensor {$sensor['label']} normalizado. {$normMsg}"]
                    );

                    $alertInternalType = 'normalized_' . $metricName;
                    $phones = getContactsForAlert($sensor['client_id'], $alertInternalType);
                    $client = Database::queryOne("SELECT name, company FROM clients WHERE id = ?", [$sensor['client_id']]);

                    $payload = [
                        'client'    => $client['company'] ?: $client['name'],
                        'sensor'    => $sensor['device_id'],
                        'location'  => $sensor['location'],
                        'label'     => $sensor['label'],
                        'type'      => $alertInternalType,
                        'field'     => $metricName,
                        'value'     => $currentVal,
                        'message'   => $normMsg,
                        'ip'        => $ip,
                        'mac'       => $mac,
                        'contacts'  => $phones,
                        'timestamp' => date('Y-m-d\TH:i:s'),
                    ];
                    triggerWebhook($payload);
                    
                    Database::execute("UPDATE sensors SET {$m['state_col']} = 'normal' WHERE id = ?", [$sensor['id']]);
                    echo " - Sensor {$sensor['device_id']}: Metric {$metricName} NORMALIZED.\n";
                }
            } else {
                // NOVO ALERTA (Transição de normal para high/low ou de high para low, etc.)
                if (canTriggerAlert($sensor['id'], $alertType, $cooldownMinutes)) {
                    Database::execute(
                        "INSERT INTO alerts (sensor_id, type, message, value, threshold) VALUES (?, ?, ?, ?, ?)",
                        [$sensor['id'], $alertType, $alertMsg, $currentVal, ($newState === 'high' ? $max : $min)]
                    );

                    $phones = getContactsForAlert($sensor['client_id'], $alertType);
                    $client = Database::queryOne("SELECT name, company FROM clients WHERE id = ?", [$sensor['client_id']]);

                    $payload = [
                        'client'    => $client['company'] ?: $client['name'],
                        'sensor'    => $sensor['device_id'],
                        'location'  => $sensor['location'],
                        'label'     => $sensor['label'],
                        'type'      => $alertType,
                        'field'     => $metricName,
                        'value'     => $currentVal,
                        'threshold' => ($newState === 'high' ? $max : $min),
                        'message'   => $alertMsg,
                        'ip'        => $ip,
                        'mac'       => $mac,
                        'contacts'  => $phones,
                        'timestamp' => date('Y-m-d\TH:i:s'),
                    ];

                    $webhookSent = triggerWebhook($payload);
                    if ($webhookSent) {
                        $alertId = Database::lastInsertId();
                        Database::execute("UPDATE alerts SET webhook_sent = 1 WHERE id = ?", [$alertId]);
                    }

                    Database::execute("UPDATE sensors SET {$m['state_col']} = ? WHERE id = ?", [$newState, $sensor['id']]);
                    $alertsTriggered[] = $payload;
                }
            }
        } 
        // 2. Continua em alerta (Repetição por cooldown)
        elseif ($newState !== 'normal' && canTriggerAlert($sensor['id'], $alertType, $cooldownMinutes)) {
            // Reaproveita lógica de envio de alerta para lembrete
            // (Opcional: O usuário pode preferir não repetir o alerta agora que tem normalização, 
            // mas vamos manter para segurança conforme ALERT_COOLDOWN_MINUTES)
            
            $phones = getContactsForAlert($sensor['client_id'], $alertType);
            $client = Database::queryOne("SELECT name, company FROM clients WHERE id = ?", [$sensor['client_id']]);

            $payload = [
                'client'    => $client['company'] ?: $client['name'],
                'sensor'    => $sensor['device_id'],
                'location'  => $sensor['location'],
                'label'     => $sensor['label'],
                'type'      => $alertType,
                'field'     => $metricName,
                'value'     => $currentVal,
                'threshold' => ($newState === 'high' ? $max : $min),
                'message'   => $alertMsg . " (Ainda fora do limite)",
                'ip'        => $ip,
                'mac'       => $mac,
                'contacts'  => $phones,
                'timestamp' => date('Y-m-d\TH:i:s'),
            ];

            if (triggerWebhook($payload)) {
                Database::execute(
                    "INSERT INTO alerts (sensor_id, type, message, value, threshold, webhook_sent) VALUES (?, ?, ?, ?, ?, 1)",
                    [$sensor['id'], $alertType, $alertMsg . " (Lembrete)", $currentVal, ($newState === 'high' ? $max : $min)]
                );
            }
            $alertsTriggered[] = $payload;
        }
    }

    return $alertsTriggered;
}
