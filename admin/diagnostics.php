<?php
/**
 * Admin - Diagnósticos do Sistema
 * Testa webhook, InfluxDB, e executa o watcher manualmente.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/influxdb.php';

Auth::requireAdmin();

$results = [];

// --- Ação: Testar Webhook ---
if (isset($_POST['action']) && $_POST['action'] === 'test_webhook') {
    $url = trim($_POST['webhook_url'] ?? N8N_WEBHOOK_URL);
    $payload = [
        'type'      => 'test',
        'message'   => 'Teste de conectividade do PrecogSystem',
        'timestamp' => date('Y-m-d\TH:i:s'),
        'sensor'    => 'DIAGNOSTICO',
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);

    $results['webhook'] = [
        'url'       => $url,
        'http_code' => $httpCode,
        'curl_err'  => $curlErr,
        'response'  => substr($response, 0, 500),
        'time_ms'   => round($totalTime * 1000),
        'ok'        => ($httpCode >= 200 && $httpCode < 300 && !$curlErr),
    ];
}

// --- Ação: Executar Watcher ---
if (isset($_POST['action']) && $_POST['action'] === 'run_watcher') {
    define('WATCHER_FORCE_RUN', true); // bypassa o guard de CLI no watcher.php
    ob_start();
    try {
        include __DIR__ . '/../scripts/watcher.php';
    } catch (Exception $e) {
        echo "EXCEÇÃO: " . $e->getMessage();
    } catch (Throwable $t) {
        echo "ERRO FATAL: " . $t->getMessage();
    }
    $watcherOutput = ob_get_clean();

    $results['watcher'] = [
        'output'   => $watcherOutput,
        'ok'       => (stripos($watcherOutput, 'ERRO') === false && stripos($watcherOutput, 'FALHOU') === false),
    ];
}

// --- Sempre: Status geral ---
// Testar InfluxDB
$influxOk = false;
$influxMsg = '';
try {
    $testData = InfluxDB::query('buckets()');
    $influxOk = ($testData !== false);
    $influxMsg = $influxOk ? 'Conexão bem-sucedida' : 'Sem resposta';
} catch (Exception $e) {
    $influxMsg = $e->getMessage();
}

// Contar sensores ativos
$sensors     = Database::query("SELECT s.*, c.name as client_name, c.influx_org, c.influx_bucket, c.influx_token FROM sensors s JOIN clients c ON s.client_id = c.id WHERE s.is_active = 1");
$totalOnline = Database::queryOne("SELECT COUNT(*) as n FROM sensors WHERE last_status = 'online'"  );
$totalOffline= Database::queryOne("SELECT COUNT(*) as n FROM sensors WHERE last_status = 'offline'" );
$totalNull   = Database::queryOne("SELECT COUNT(*) as n FROM sensors WHERE last_status IS NULL"     );
$lastAlert   = Database::queryOne("SELECT created_at, type FROM alerts ORDER BY created_at DESC LIMIT 1");
$webhookUrl  = N8N_WEBHOOK_URL;
$webhookIsPlaceholder = (strpos($webhookUrl, 'SEU_N8N_URL') !== false);

// --- Diagnóstico InfluxDB por sensor ---
$influxDiag = [];
foreach ($sensors as $s) {
    $reading = InfluxDB::getLatestReading(
        $s['device_id'],
        $s['influx_bucket'],
        $s['influx_org'],
        $s['influx_token']
    );

    // Busca raw para ver campos disponíveis (últimas 7d, sem filtro de campo)
    $rawFlux = 'from(bucket: "' . $s['influx_bucket'] . '")
        |> range(start: -7d)
        |> filter(fn: (r) => r["device_id"] == "' . $s['device_id'] . '")
        |> last()';
    $rawData = InfluxDB::query($rawFlux, $s['influx_org'], $s['influx_token']);

    // Extrai campos únicos encontrados
    $fieldsFound = array_unique(array_column($rawData, '_field'));
    $lastTime    = count($rawData) > 0 ? ($rawData[0]['_time'] ?? null) : null;
    $rowCount    = count($rawData);

    $influxDiag[$s['device_id']] = [
        'reading'      => $reading,
        'fields_found' => $fieldsFound,
        'last_time'    => $lastTime,
        'row_count'    => $rowCount,
        'has_data'     => $rowCount > 0,
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Diagnósticos</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
    <style>
        .diag-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
        .diag-card { background: var(--card-bg, #1e2433); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border, #2a3245); }
        .diag-card h3 { margin: 0 0 1rem 0; font-size: 1rem; display:flex; align-items:center; gap:.5rem; }
        .badge { display:inline-block; padding:.2rem .6rem; border-radius:6px; font-size:.8rem; font-weight:600; }
        .badge-ok  { background:#0d3321; color:#2ecc71; }
        .badge-err { background:#3c0f0f; color:#e74c3c; }
        .badge-warn{ background:#3c2a00; color:#f39c12; }
        .badge-null{ background:#2a2a3a; color:#888; }
        .stat-row  { display:flex; justify-content:space-between; align-items:center; padding:.4rem 0; border-bottom:1px solid var(--border, #2a3245); font-size:.9rem; }
        .stat-row:last-child { border-bottom:none; }
        .terminal  { background:#0d0d0d; color:#00ff88; font-family:monospace; font-size:.82rem; padding:1rem; border-radius:8px; max-height:350px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; margin-top:.75rem; border:1px solid #1a3a2a; }
        .terminal.err { color:#ff6b6b; }
        .action-bar { display:flex; gap:.75rem; flex-wrap:wrap; margin-bottom:1.5rem; }
        .btn-diag   { padding:.55rem 1.2rem; border:none; border-radius:8px; cursor:pointer; font-weight:600; font-size:.9rem; }
        .btn-run    { background:#2ecc71; color:#000; }
        .btn-webhook{ background:#3498db; color:#fff; }
        .form-inline { display:flex; gap:.5rem; align-items:center; flex:1; }
        .form-inline input { flex:1; padding:.5rem .75rem; border-radius:8px; border:1px solid var(--border,#2a3245); background:var(--input-bg,#151b2e); color:var(--text,#fff); font-size:.9rem; }
        .sensor-table { width:100%; border-collapse:collapse; font-size:.85rem; }
        .sensor-table th, .sensor-table td { padding:.5rem .75rem; text-align:left; border-bottom:1px solid var(--border,#2a3245); }
        .sensor-table th { color: var(--text-secondary, #8892a4); font-weight:600; }
        @media(max-width:768px){ .diag-grid{ grid-template-columns:1fr; } }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-brand">
        <div class="icon-bars"><span></span><span></span><span></span><span></span></div>
        <?= APP_NAME ?>
    </div>
    <ul class="navbar-nav">
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="clients.php">Clientes</a></li>
        <li><a href="sensors.php">Sensores</a></li>
        <li><a href="contacts.php">Contatos</a></li>
        <li><a href="admin_contacts.php">Admins</a></li>
        <li><a href="reports.php">Relatórios</a></li>
        <li><a href="settings.php">Configurações</a></li>
        <li><a href="diagnostics.php" class="active">🔧 Diagnósticos</a></li>
    </ul>
    <div class="navbar-right">
        <a href="dashboard.php?logout=1" class="btn btn-secondary btn-sm">Sair</a>
    </div>
</nav>

<main class="main-content">
    <div class="admin-header">
        <h1>🔧 Diagnósticos do Sistema</h1>
        <p style="color:var(--text-secondary);margin-top:.25rem;">Verifique a saúde do watcher, webhook e sensores.</p>
    </div>

    <!-- === STATUS GERAL === -->
    <div class="diag-grid">

        <div class="diag-card">
            <h3>📡 Webhook n8n
                <?php if ($webhookIsPlaceholder): ?>
                    <span class="badge badge-err">NÃO CONFIGURADO</span>
                <?php else: ?>
                    <span class="badge badge-warn">Não testado</span>
                <?php endif; ?>
            </h3>
            <?php if ($webhookIsPlaceholder): ?>
                <div style="background:#3c0f0f;padding:.75rem;border-radius:8px;color:#e74c3c;font-size:.85rem;margin-bottom:.75rem;">
                    ⚠️ <strong>A URL do webhook ainda é o placeholder!</strong><br>
                    Edite <code>config.php</code> e substitua <code>SEU_N8N_URL</code> pela URL real do seu n8n.
                </div>
            <?php endif; ?>
            <div class="stat-row"><span>URL configurada</span><code style="font-size:.75rem;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($webhookUrl) ?></code></div>
            <div class="stat-row"><span>Último alerta BD</span><span><?= $lastAlert ? htmlspecialchars($lastAlert['type'].' às '.date('d/m H:i', strtotime($lastAlert['created_at']))) : 'Nenhum' ?></span></div>
        </div>

        <div class="diag-card">
            <h3>🗄️ InfluxDB
                <span class="badge <?= $influxOk ? 'badge-ok' : 'badge-err' ?>"><?= $influxOk ? 'CONECTADO' : 'ERRO' ?></span>
            </h3>
            <div class="stat-row"><span>URL</span><code style="font-size:.75rem;"><?= htmlspecialchars(INFLUXDB_URL) ?></code></div>
            <div class="stat-row"><span>Status</span><span><?= htmlspecialchars($influxMsg) ?></span></div>
        </div>

        <div class="diag-card">
            <h3>📊 Sensores Ativos</h3>
            <div class="stat-row"><span>Total ativos no BD</span><strong><?= count($sensors) ?></strong></div>
            <div class="stat-row"><span>Online</span><span class="badge badge-ok"><?= (int)$totalOnline['n'] ?></span></div>
            <div class="stat-row"><span>Offline</span><span class="badge badge-err"><?= (int)$totalOffline['n'] ?></span></div>
            <div class="stat-row"><span>Status NULL (nunca processados)</span><span class="badge badge-null"><?= (int)$totalNull['n'] ?></span></div>
        </div>

        <div class="diag-card" style="grid-column: 1 / -1;">
            <?php
                $cronSecretIsPlaceholder = (CRON_SECRET === 'TROQUE_POR_UM_TOKEN_SECRETO_AQUI');
                $cronUrl = APP_URL . '/scripts/watcher.php?secret=' . urlencode(CRON_SECRET);
            ?>
            <h3>⏱️ Cron Job (Execução Automática)
                <?php if ($cronSecretIsPlaceholder): ?>
                    <span class="badge badge-err">TOKEN NÃO CONFIGURADO</span>
                <?php else: ?>
                    <span class="badge badge-ok">Pronto para usar</span>
                <?php endif; ?>
            </h3>

            <?php if ($cronSecretIsPlaceholder): ?>
            <div style="background:#3c0f0f;padding:.75rem;border-radius:8px;color:#e74c3c;font-size:.85rem;margin-bottom:1rem;">
                ⚠️ <strong>Defina um CRON_SECRET seguro no config.php antes de usar a URL web.</strong><br>
                Gere um token aleatório: copie qualquer string longa e única (ex: <code>minha-chave-32-chars-aleatoria-1234</code>)
            </div>
            <?php endif; ?>

            <p style="color:var(--text-secondary);font-size:.85rem;margin-bottom:1rem;">
                Como o servidor bloqueia <code>exec()</code>, o watcher deve ser acionado via <strong>requisição HTTP</strong>.
                Configure um serviço externo para chamar esta URL a cada minuto:
            </p>

            <!-- URL do Cron -->
            <div style="margin-bottom:1rem;">
                <label style="font-size:.8rem;color:var(--text-secondary);display:block;margin-bottom:.3rem;">🔗 URL do Cron Watcher:</label>
                <div style="display:flex;gap:.5rem;align-items:center;">
                    <code id="cron-url-box" style="flex:1;background:#0d0d0d;padding:.6rem .9rem;border-radius:8px;font-size:.8rem;color:<?= $cronSecretIsPlaceholder ? '#e74c3c' : '#2ecc71' ?>;word-break:break-all;border:1px solid #2a3245;">
                        <?= htmlspecialchars($cronUrl) ?>
                    </code>
                    <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($cronUrl, ENT_QUOTES) ?>');this.textContent='✅ Copiado!';setTimeout(()=>this.textContent='📋 Copiar',2000)"
                            style="padding:.5rem .9rem;background:#2a3245;border:none;border-radius:8px;color:#fff;cursor:pointer;white-space:nowrap;font-size:.85rem;">
                        📋 Copiar
                    </button>
                </div>
            </div>

            <!-- Opções de configuração -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:.75rem;">

                <!-- cron-job.org -->
                <div style="background:#0d1117;padding:1rem;border-radius:8px;border:1px solid #2a3245;">
                    <strong style="color:#3498db;">Opção 1 — cron-job.org (Grátis)</strong>
                    <ol style="color:var(--text-secondary);font-size:.82rem;margin:.6rem 0 0 1rem;line-height:1.8;">
                        <li>Acesse <a href="https://cron-job.org" target="_blank" style="color:#3498db;">cron-job.org</a> e crie uma conta grátis</li>
                        <li>Clique em <strong>Create Cronjob</strong></li>
                        <li>Cole a URL acima no campo <strong>URL</strong></li>
                        <li>Defina a execução: <strong>Every minute</strong></li>
                        <li>Salve — pronto! ✅</li>
                    </ol>
                </div>

                <!-- cPanel -->
                <div style="background:#0d1117;padding:1rem;border-radius:8px;border:1px solid #2a3245;">
                    <strong style="color:#9b59b6;">Opção 2 — cPanel Cron Jobs</strong>
                    <ol style="color:var(--text-secondary);font-size:.82rem;margin:.6rem 0 0 1rem;line-height:1.8;">
                        <li>Abra o cPanel → <strong>Cron Jobs</strong></li>
                        <li>Selecione <strong>Once Per Minute</strong></li>
                        <li>No campo Command, use:<br>
                            <code style="font-size:.75rem;display:block;margin-top:.3rem;background:#1a1a2e;padding:.3rem .5rem;border-radius:4px;">
                                curl -s "<?= htmlspecialchars($cronUrl) ?>" > /dev/null
                            </code>
                        </li>
                        <li>Clique em <strong>Add New Cron Job</strong> ✅</li>
                    </ol>
                </div>
            </div>

            <!-- Último cron executado -->
            <?php
                $lastEvent = Database::queryOne("SELECT created_at FROM events ORDER BY created_at DESC LIMIT 1");
                $lastAlert2 = Database::queryOne("SELECT created_at FROM alerts ORDER BY created_at DESC LIMIT 1");
                $lastActivity = null;
                if ($lastEvent && $lastAlert2) {
                    $lastActivity = max(strtotime($lastEvent['created_at']), strtotime($lastAlert2['created_at']));
                } elseif ($lastEvent) {
                    $lastActivity = strtotime($lastEvent['created_at']);
                } elseif ($lastAlert2) {
                    $lastActivity = strtotime($lastAlert2['created_at']);
                }
            ?>
            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #2a3245;">
                <div class="stat-row">
                    <span>Última atividade registrada (eventos/alertas)</span>
                    <span>
                        <?php if ($lastActivity): ?>
                            <?php $agoSec = time() - $lastActivity; ?>
                            <?php if ($agoSec < 120): ?>
                                <span class="badge badge-ok"><?= $agoSec ?>s atrás — Cron rodando! ✅</span>
                            <?php elseif ($agoSec < 600): ?>
                                <span class="badge badge-warn"><?= round($agoSec/60) ?>min atrás</span>
                            <?php else: ?>
                                <span class="badge badge-err"><?= round($agoSec/60) ?>min atrás — Cron pode estar parado ⚠️</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge-null">Nenhuma atividade ainda</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>


    <!-- === AÇÕES === -->
    <div class="diag-card" style="margin-bottom:1.5rem;">
        <h3>🚀 Ações de Teste</h3>

        <div class="action-bar">

            <!-- Botão: Executar Watcher Agora -->
            <form method="POST">
                <input type="hidden" name="action" value="run_watcher">
                <button type="submit" class="btn-diag btn-run" id="btn-run-watcher">
                    ▶ Executar Watcher Agora
                </button>
            </form>

            <!-- Formulário: Testar Webhook -->
            <form method="POST" class="form-inline">
                <input type="hidden" name="action" value="test_webhook">
                <input type="url" name="webhook_url"
                       value="<?= htmlspecialchars($webhookIsPlaceholder ? '' : $webhookUrl) ?>"
                       placeholder="URL do webhook (ex: https://n8n.dominio.com/webhook/...)"
                       id="webhook-url-input">
                <button type="submit" class="btn-diag btn-webhook">📡 Testar Webhook</button>
            </form>
        </div>

        <!-- Resultado: Watcher -->
        <?php if (isset($results['watcher'])): ?>
            <h4 style="margin:.75rem 0 .25rem;">Output do Watcher:</h4>
            <div class="terminal <?= $results['watcher']['ok'] ? '' : 'err' ?>"><?= htmlspecialchars($results['watcher']['output'] ?: '(sem output)') ?></div>
            <p style="font-size:.8rem;color:var(--text-secondary);margin-top:.3rem;">
                <?= $results['watcher']['ok'] ? '✅ Executou sem erros detectados' : '❌ Erros detectados no output (veja acima)' ?>
            </p>
        <?php endif; ?>

        <!-- Resultado: Webhook -->
        <?php if (isset($results['webhook'])): ?>
            <h4 style="margin:.75rem 0 .25rem;">Resultado do Webhook:</h4>
            <div class="stat-row"><span>URL testada</span><code><?= htmlspecialchars($results['webhook']['url']) ?></code></div>
            <div class="stat-row"><span>HTTP Status</span>
                <span class="badge <?= $results['webhook']['ok'] ? 'badge-ok' : 'badge-err' ?>">
                    <?= $results['webhook']['http_code'] ?: 'SEM RESPOSTA' ?>
                </span>
            </div>
            <div class="stat-row"><span>Tempo de resposta</span><span><?= $results['webhook']['time_ms'] ?>ms</span></div>
            <?php if ($results['webhook']['curl_err']): ?>
                <div class="stat-row"><span>Erro cURL</span><span style="color:#e74c3c;"><?= htmlspecialchars($results['webhook']['curl_err']) ?></span></div>
            <?php endif; ?>
            <?php if ($results['webhook']['response']): ?>
                <div class="terminal" style="max-height:120px;"><?= htmlspecialchars($results['webhook']['response']) ?></div>
            <?php endif; ?>
            <?php if ($results['webhook']['ok']): ?>
                <p style="color:#2ecc71;margin-top:.5rem;font-weight:600;">✅ Webhook respondeu com sucesso! O n8n está recebendo os dados.</p>
            <?php else: ?>
                <p style="color:#e74c3c;margin-top:.5rem;font-weight:600;">❌ Webhook falhou. Verifique a URL e se o n8n está rodando.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- === TABELA DE SENSORES === -->
    <div class="diag-card">
        <h3>📋 Estado Atual dos Sensores (BD)</h3>
        <?php if (empty($sensors)): ?>
            <p style="color:var(--text-secondary);">Nenhum sensor ativo cadastrado.</p>
        <?php else: ?>
        <table class="sensor-table">
            <thead>
                <tr>
                    <th>Sensor / Label</th>
                    <th>Cliente</th>
                    <th>Status BD</th>
                    <th>Último contato</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sensors as $s): ?>
                <?php
                    $statusBadge = 'badge-null';
                    $statusText  = 'NULL';
                    if ($s['last_status'] === 'online')  { $statusBadge = 'badge-ok';  $statusText = 'ONLINE'; }
                    if ($s['last_status'] === 'offline') { $statusBadge = 'badge-err'; $statusText = 'OFFLINE'; }

                    $lastSeen = '-';
                    if (!empty($s['last_seen'])) {
                        // Interpreta como UTC para calcular diff corretamente
                        $lsDt = new DateTime($s['last_seen'], new DateTimeZone('UTC'));
                        $diff = time() - $lsDt->getTimestamp();
                        if ($diff < 0)         $lastSeen = 'Dados no futuro (verificar timezone do MySQL)';
                        elseif ($diff < 60)    $lastSeen = $diff . 's atrás';
                        elseif ($diff < 3600)  $lastSeen = round($diff/60) . 'min atrás';
                        else                   $lastSeen = round($diff/3600,1) . 'h atrás';
                    }
                ?>
                <tr>
                    <td><code><?= htmlspecialchars($s['device_id']) ?></code><br><small style="color:var(--text-secondary)"><?= htmlspecialchars($s['label']) ?></small></td>
                    <td><?= htmlspecialchars($s['client_name']) ?></td>
                    <td><span class="badge <?= $statusBadge ?>"><?= $statusText ?></span></td>
                    <td><?= $lastSeen ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- === DIAGNÓSTICO INFLUXDB POR SENSOR === -->
    <div class="diag-card" style="margin-top:1.5rem;">
        <h3>🔬 Diagnóstico InfluxDB — Dados por Sensor</h3>
        <p style="color:var(--text-secondary);font-size:.85rem;margin-bottom:1rem;">
            Consulta direta ao InfluxDB (últimos 7 dias) para verificar se os dados estão chegando e quais campos estão disponíveis.
        </p>

        <?php if (empty($sensors)): ?>
            <p style="color:var(--text-secondary);">Nenhum sensor ativo cadastrado.</p>
        <?php else: ?>
        <table class="sensor-table">
            <thead>
                <tr>
                    <th>Device ID</th>
                    <th>Dados no InfluxDB?</th>
                    <th>Campos encontrados (<code>_field</code>)</th>
                    <th>Última leitura</th>
                    <th>Temp / Umid (parsed)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sensors as $s):
                $diag = $influxDiag[$s['device_id']] ?? null;
                if (!$diag) continue;

                $hasData   = $diag['has_data'];
                $fields    = implode(', ', $diag['fields_found']) ?: '—';
                $lastTime  = $diag['last_time'] ? date('d/m H:i', strtotime($diag['last_time'])) : '—';
                $tempVal   = $diag['reading']['temperature'] !== null ? $diag['reading']['temperature'] . '°C' : '<span style="color:#e74c3c">null</span>';
                $humVal    = $diag['reading']['humidity']    !== null ? $diag['reading']['humidity']    . '%'  : '<span style="color:#e74c3c">null</span>';

                // Detecta problemas
                $warnings = [];
                if (!$hasData) {
                    $warnings[] = '❌ Nenhum dado nos últimos 7 dias — verifique o device_id ou se o sensor está enviando dados';
                } else {
                    if (!in_array('temperatura', $diag['fields_found']) && !in_array('temperature', $diag['fields_found'])) {
                        $warnings[] = '⚠️ Campo "temperatura" não encontrado — verifique o nome do field no firmware';
                    }
                    if ($diag['reading']['temperature'] === null && $hasData) {
                        $warnings[] = '⚠️ Temperatura lida como null — último registro tem mais de 5 min (sensor provavelmente offline)';
                    }
                }
            ?>
                <tr>
                    <td><code><?= htmlspecialchars($s['device_id']) ?></code></td>
                    <td>
                        <span class="badge <?= $hasData ? 'badge-ok' : 'badge-err' ?>">
                            <?= $hasData ? $diag['row_count'].' registros' : 'SEM DADOS' ?>
                        </span>
                    </td>
                    <td><code style="font-size:.78rem;"><?= htmlspecialchars($fields) ?></code></td>
                    <td><?= $lastTime ?></td>
                    <td><?= $tempVal ?> / <?= $humVal ?></td>
                </tr>
                <?php if (!empty($warnings)): ?>
                <tr>
                    <td colspan="5" style="padding:.25rem .75rem .75rem;">
                        <?php foreach ($warnings as $w): ?>
                            <div style="font-size:.82rem;color:#f39c12;margin-top:.2rem;"><?= htmlspecialchars($w) ?></div>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</main>
<script src="../assets/js/main.js?v=2"></script>
</body>
</html>



