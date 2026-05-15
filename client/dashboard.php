<?php
/**
 * Dashboard do Cliente - Acesso via token
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$client = Auth::validateClientToken($token);

if (!$client) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><title>Acesso Negado</title></head><body style="background:#0a0e1a;color:#ef4444;font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;"><h1>Token inválido ou expirado</h1><script src="../assets/js/main.js?v=2"></script>
</body></html>';
    exit;
}

// Buscar sensores do cliente
$sensors = Database::query(
    "SELECT * FROM sensors WHERE client_id = ? AND is_active = 1 ORDER BY label",
    [$client['id']]
);

$sensorCount = count($sensors);
$firstDeviceId = $sensorCount > 0 ? $sensors[0]['device_id'] : '';

// Alertas das últimas 24h
$alertCount = Database::queryOne(
    "SELECT COUNT(*) as total FROM alerts a JOIN sensors s ON a.sensor_id = s.id WHERE s.client_id = ? AND a.created_at >= NOW() - INTERVAL 24 HOUR",
    [$client['id']]
);

// Eventos recentes
$events = Database::query(
    "SELECT e.*, s.device_id, s.label FROM events e LEFT JOIN sensors s ON e.sensor_id = s.id WHERE (s.client_id = ? OR e.sensor_id IS NULL) AND e.message NOT LIKE '%reiniciou (interrupção%' ORDER BY e.created_at DESC LIMIT 10",
    [$client['id']]
);

// Alertas recentes
$recentAlerts = Database::query(
    "SELECT a.*, s.device_id, s.label, s.location FROM alerts a JOIN sensors s ON a.sensor_id = s.id WHERE s.client_id = ? ORDER BY a.created_at DESC LIMIT 10",
    [$client['id']]
);

$clientInitial = strtoupper(substr($client['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= sanitize($client['company'] ?: $client['name']) ?></title>
    <meta name="description" content="Dashboard de monitoramento de sensores - <?= sanitize($client['company'] ?: $client['name']) ?>">
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <style>
        .dropdown-item:hover { background: rgba(255,255,255,0.08) !important; }
        .custom-dropdown::-webkit-scrollbar { width: 6px; }
        .custom-dropdown::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 10px; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="navbar-brand">
        <div class="icon-bars"><span></span><span></span><span></span><span></span></div>
        <?= APP_NAME ?>
    </div>
    <ul class="navbar-nav">
        <li><a href="#" class="active">Dashboard</a></li>
        <li><a href="#sensors-section">Sensores</a></li>
    </ul>
    <div class="navbar-right">
        <button class="btn-icon" title="Notificações" id="btnNotifications">🔔</button>
        <div class="avatar" title="<?= sanitize($client['name']) ?>"><?= $clientInitial ?></div>
    </div>
</nav>

<!-- MAIN CONTENT -->
<main class="main-content">
    <div class="header-main" style="margin-bottom: 2rem; padding: 1rem 0;">
        <div class="header-content" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin: 0; font-size: 1.8rem;">Monitoramento PrecogNovo</h1>
                <p style="margin: 5px 0 0; color: var(--text-muted); font-size: 0.9rem;">Gerenciamento de sensores em tempo real</p>
            </div>
            <div class="sensor-search-container" style="position: relative; width: 320px;">
                <div class="search-input-wrapper" style="position: relative;">
                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);">🔍</span>
                    <input type="text" id="top-sensor-search" placeholder="Selecionar Sensor..." 
                           style="width: 100%; padding: 12px 15px 12px 40px; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg-card); color: white; transition: all 0.3s; outline: none;"
                           onfocus="this.style.borderColor='var(--accent-cyan)'; this.style.boxShadow='0 0 0 3px rgba(0, 230, 180, 0.1)'"
                           onblur="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none'">
                </div>
                <div id="sensor-dropdown" class="custom-dropdown" style="display: none; position: absolute; top: calc(100% + 10px); left: 0; width: 100%; background: #161f35; border: 1px solid var(--border-color); border-radius: 12px; z-index: 1000; max-height: 280px; overflow-y: auto; box-shadow: 0 15px 35px rgba(0,0,0,0.6); backdrop-filter: blur(10px);">
                    <?php foreach ($sensors as $s): ?>
                    <div class="dropdown-item" onclick="selectAndClose('<?= sanitize($s['device_id']) ?>', '<?= sanitize($s['label']) ?>')" 
                         style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; transition: background 0.2s; display: flex; align-items: center; gap: 10px;"
                         data-label="<?= strtolower(sanitize($s['label'])) ?>" data-id="<?= sanitize($s['device_id']) ?>">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: var(--accent-green);"></span>
                        <?= sanitize($s['label']) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- STAT CARDS -->
    <div class="stats-grid">
        <!-- Temperatura Atual -->
        <div class="stat-card" id="card-temp">
            <div class="stat-card-header">
                <span class="label">Temperatura Atual</span>
                <span class="icon">🌡️</span>
            </div>
            <div class="stat-value" id="val-temp">--<span class="unit">°C</span></div>
            <div class="stat-bar"><div class="fill cyan" id="bar-temp" style="width: 0%"></div></div>
            <div class="stat-footer" id="limit-temp">Limite: --</div>
        </div>

        <!-- Umidade Atual -->
        <div class="stat-card" id="card-hum">
            <div class="stat-card-header">
                <span class="label">Umidade Atual</span>
                <span class="icon">💧</span>
            </div>
            <div class="stat-value" id="val-hum">--<span class="unit">%</span></div>
            <div class="stat-bar"><div class="fill green" id="bar-hum" style="width: 0%"></div></div>
            <div class="stat-footer" id="limit-hum">Limite: --</div>
        </div>

        <!-- Sensores Online -->
        <div class="stat-card" id="card-sensors">
            <div class="stat-card-header">
                <span class="label">Sensores Online</span>
                <span class="icon">📡</span>
            </div>
            <div class="stat-value"><span id="val-online">0</span><span class="fraction">/<?= $sensorCount ?></span></div>
            <div class="sensor-dots" id="sensor-dots">
                <?php foreach ($sensors as $s): ?>
                <span class="dot" data-device="<?= sanitize($s['device_id']) ?>" title="<?= sanitize($s['label']) ?>"></span>
                <?php endforeach; ?>
            </div>
            <div class="stat-footer"><?= $sensorCount > 0 ? 'Monitorando...' : 'Nenhum sensor' ?></div>
        </div>

        <!-- Alertas Hoje -->
        <div class="stat-card" id="card-alerts">
            <div class="stat-card-header">
                <span class="label">Alertas Hoje</span>
                <span class="icon">🔔</span>
            </div>
            <div class="stat-value" id="val-alerts"><?= intval($alertCount['total']) ?></div>
            <div id="alert-status">
                <?php if (intval($alertCount['total']) === 0): ?>
                <span class="status-badge safe">✅ Sistema Seguro</span>
                <div class="stat-footer">Nenhum alerta nas últimas 24h</div>
                <?php else: ?>
                <span class="status-badge warning">⚠️ Atenção</span>
                <div class="stat-footer"><?= intval($alertCount['total']) ?> alerta(s) nas últimas 24h</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="charts-grid">
        <div class="chart-card">
            <div class="chart-card-header">
                <h3>Temperatura</h3>
                <div class="chart-range-tabs" id="temp-range-tabs">
                    <button class="active" data-range="-24h">24h</button>
                    <button data-range="-7d">7d</button>
                    <button data-range="-30d">30d</button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="chartTemp"></canvas>
            </div>
        </div>
        <div class="chart-card">
            <div class="chart-card-header">
                <h3>Umidade</h3>
                <div class="chart-range-tabs" id="hum-range-tabs">
                    <button class="active" data-range="-24h">24h</button>
                    <button data-range="-7d">7d</button>
                    <button data-range="-30d">30d</button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="chartHum"></canvas>
            </div>
        </div>
    </div>

    <!-- BOTTOM: Events + Alerts -->
    <div class="bottom-grid">
        <!-- Histórico de Eventos -->
        <div class="info-card">
            <div class="info-card-header">
                <span>📋</span>
                <h3>Histórico de Eventos</h3>
            </div>
            <ul class="event-list" id="event-list" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($events)): ?>
                <li class="event-item"><div class="event-content"><span class="title" style="color:var(--text-muted)">Nenhum evento registrado</span></div></li>
                <?php else: ?>
                <?php foreach ($events as $ev): ?>
                <li class="event-item">
                    <span class="event-dot <?= $ev['type'] === 'info' ? 'green' : ($ev['type'] === 'warning' ? 'orange' : 'red') ?>"></span>
                    <div class="event-content">
                        <div class="title"><?= sanitize($ev['message']) ?></div>
                        <div class="time"><?= formatDateRelative($ev['created_at']) ?></div>
                    </div>
                    <span class="event-badge <?= $ev['type'] ?>"><?= ucfirst($ev['type']) ?></span>
                </li>
                <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Alertas Recentes -->
        <div class="info-card">
            <div class="info-card-header">
                <span>🔥</span>
                <h3>Alertas Recentes</h3>
            </div>
            <?php if (empty($recentAlerts)): ?>
            <div class="empty-state">
                <div class="icon-big">🛡️</div>
                <h4>Nenhum alerta ativo</h4>
                <p>Todos os sensores dentro dos limites</p>
            </div>
            <?php else: ?>
            <ul class="event-list" id="alerts-list" style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($recentAlerts as $al): ?>
                <li class="event-item">
                    <span class="event-dot red"></span>
                    <div class="event-content">
                        <div class="title"><?= sanitize($al['label']) ?> - <?= sanitize($al['message']) ?></div>
                        <div class="time"><?= formatDateRelative($al['created_at']) ?> | Valor: <?= $al['value'] ?> | Limite: <?= $al['threshold'] ?></div>
                    </div>
                    <span class="event-badge error">Alerta</span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Seção de Sensores -->
    <div class="info-card" id="sensors-section" style="margin-top: 1.5rem;">
        <div class="info-card-header">
            <span>📡</span>
            <h3>Status dos Dispositivos</h3>
        </div>
        <ul class="event-list">
            <?php foreach ($sensors as $s): ?>
            <li class="event-item" style="cursor:pointer" onclick="changeDevice('<?= sanitize($s['device_id']) ?>')">
                <span class="dot" data-device="<?= sanitize($s['device_id']) ?>" style="width:12px;height:12px;border-radius:50%;display:inline-block;margin-right:10px;background:var(--accent-green);"></span>
                <div class="event-content">
                    <div class="title"><?= sanitize($s['label']) ?></div>
                    <div class="time"><?= sanitize($s['location']) ?> | ID: <?= sanitize($s['device_id']) ?></div>
                </div>
                <span class="event-badge info">Ver Dados</span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</main>

<script>
// Configuração global
const TOKEN = '<?= $token ?>';
const API_BASE = '<?= APP_URL ?>/api';
const POLLING_MS = <?= POLLING_INTERVAL_MS ?>;
const FIRST_DEVICE = '<?= $firstDeviceId ?>';
const SENSORS = <?= json_encode(array_map(function($s) {
    return ['device_id' => $s['device_id'], 'label' => $s['label'], 'location' => $s['location'],
            'temp_min' => floatval($s['temp_min']), 'temp_max' => floatval($s['temp_max']),
            'hum_min' => floatval($s['hum_min']), 'hum_max' => floatval($s['hum_max'])];
}, $sensors)) ?>;

// Chart.js defaults
Chart.defaults.color = '#8892a8';
Chart.defaults.borderColor = '#1e2a45';
Chart.defaults.font.family = "'Inter', sans-serif";

// Criação dos gráficos
const chartConfig = (color) => ({
    type: 'line',
    data: { labels: [], datasets: [{ data: [], borderColor: color, backgroundColor: color + '15',
        borderWidth: 2, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 4 }] },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { type: 'time', time: { unit: 'hour', displayFormats: { hour: 'HH:mm' } },
                grid: { display: false }, ticks: { maxTicksLimit: 8 } },
            y: { grid: { color: '#1e2a4530' }, ticks: { maxTicksLimit: 5 } }
        },
        interaction: { intersect: false, mode: 'index' }
    }
});

const tempChart = new Chart(document.getElementById('chartTemp'), chartConfig('#00e6b4'));
const humChart  = new Chart(document.getElementById('chartHum'),  chartConfig('#22c55e'));

let currentTempRange = '-24h';
let currentHumRange  = '-24h';
let selectedDevice   = FIRST_DEVICE;

// Função para trocar de sensor
function changeDevice(deviceId) {
    selectedDevice = deviceId;
    const selector = document.getElementById('sensor-selector');
    if (selector) selector.value = deviceId;
    fetchLatest();
    fetchHistory('temperature', currentTempRange);
    fetchHistory('humidity', currentHumRange);
    
    // Rola para o topo suavemente
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Listener removido pois o seletor não existe mais na interface

// Buscar leituras atuais
async function fetchLatest() {
    try {
        const res = await fetch(`${API_BASE}/readings.php?token=${TOKEN}&action=latest`);
        if (!res.ok) return;
        const data = await res.json();
        if (!data.readings || data.readings.length === 0) return;

        let onlineCount = 0;
        // Encontrar os dados do sensor selecionado
        const r = data.readings.find(item => item.device_id === selectedDevice) || data.readings[0];

        if (r) {
            // Atualizar cards principais
            if (r.temperature !== null) {
                document.getElementById('val-temp').innerHTML = r.temperature + '<span class="unit">°C</span>';
                const pct = Math.min(100, Math.max(0, ((r.temperature - r.limits.temp_min) / (r.limits.temp_max - r.limits.temp_min)) * 100));
                document.getElementById('bar-temp').style.width = pct + '%';
                document.getElementById('limit-temp').textContent = `Limite: ${r.limits.temp_min}°C - ${r.limits.temp_max}°C`;
            }

            if (r.humidity !== null) {
                document.getElementById('val-hum').innerHTML = r.humidity + '<span class="unit">%</span>';
                const pct = Math.min(100, Math.max(0, ((r.humidity - r.limits.hum_min) / (r.limits.hum_max - r.limits.hum_min)) * 100));
                document.getElementById('bar-hum').style.width = pct + '%';
                document.getElementById('limit-hum').textContent = `Limite: ${r.limits.hum_min}% - ${r.limits.hum_max}%`;
            }
        }

        // Sensor dots
        data.readings.forEach(s => {
            const dot = document.querySelector(`.dot[data-device="${s.device_id}"]`);
            if (dot) {
                dot.classList.toggle('offline', !s.online);
                if (s.online) onlineCount++;
            }
        });
        document.getElementById('val-online').textContent = onlineCount;

    } catch (e) { console.error('Erro ao buscar leituras:', e); }
}

// Buscar histórico para gráfico
async function fetchHistory(field, range) {
    if (!selectedDevice) return;
    try {
        const res = await fetch(`${API_BASE}/readings.php?token=${TOKEN}&action=history&device_id=${selectedDevice}&field=${field}&range=${range}`);
        if (!res.ok) return;
        const data = await res.json();

        const chart = field === 'temperature' ? tempChart : humChart;
        chart.data.labels = data.data.map(d => new Date(d.time));
        chart.data.datasets[0].data = data.data.map(d => d.value);

        // Ajustar time unit com base no range
        if (range === '-7d') chart.options.scales.x.time.unit = 'day';
        else if (range === '-30d') chart.options.scales.x.time.unit = 'day';
        else chart.options.scales.x.time.unit = 'hour';

        chart.update('none');
    } catch (e) { console.error('Erro ao buscar histórico:', e); }
}

// Busca dinâmica no Topo
const topSearch = document.getElementById('top-sensor-search');
const dropdown = document.getElementById('sensor-dropdown');

function selectAndClose(id, label) {
    changeDevice(id);
    topSearch.value = label;
    dropdown.style.display = 'none';
}

topSearch.addEventListener('focus', () => { 
    dropdown.style.display = 'block'; 
});

document.addEventListener('click', e => {
    if (!e.target.closest('.sensor-search-container')) {
        dropdown.style.display = 'none';
    }
});

topSearch.addEventListener('input', e => {
    const term = e.target.value.toLowerCase();
    dropdown.style.display = 'block';
    dropdown.querySelectorAll('.dropdown-item').forEach(item => {
        item.style.display = item.dataset.label.includes(term) ? 'flex' : 'none';
    });
});

// Range tabs
document.getElementById('temp-range-tabs').addEventListener('click', e => {
    if (e.target.tagName !== 'BUTTON') return;
    document.querySelectorAll('#temp-range-tabs button').forEach(b => b.classList.remove('active'));
    e.target.classList.add('active');
    currentTempRange = e.target.dataset.range;
    fetchHistory('temperature', currentTempRange);
});

document.getElementById('hum-range-tabs').addEventListener('click', e => {
    if (e.target.tagName !== 'BUTTON') return;
    document.querySelectorAll('#hum-range-tabs button').forEach(b => b.classList.remove('active'));
    e.target.classList.add('active');
    currentHumRange = e.target.dataset.range;
    fetchHistory('humidity', currentHumRange);
});

// Buscar alertas e eventos
async function fetchAlerts() {
    try {
        const res = await fetch(`${API_BASE}/alerts.php?token=${TOKEN}`);
        if (!res.ok) return;
        const data = await res.json();

        // Atualizar contador de hoje
        document.getElementById('val-alerts').textContent = data.alerts_today;
        const alertStatus = document.getElementById('alert-status');
        if (data.alerts_today === 0) {
            alertStatus.innerHTML = '<span class="status-badge safe">✅ Sistema Seguro</span><div class="stat-footer">Nenhum alerta nas últimas 24h</div>';
        } else {
            alertStatus.innerHTML = '<span class="status-badge warning">⚠️ Atenção</span><div class="stat-footer">' + data.alerts_today + ' alerta(s) nas últimas 24h</div>';
        }

        // Atualizar lista de alertas
        const alertsList = document.getElementById('alerts-list');
        if (alertsList) {
            if (data.alerts.length === 0) {
                alertsList.closest('.info-card').innerHTML = `
                    <div class="info-card-header"><span>🔥</span><h3>Alertas Recentes</h3></div>
                    <div class="empty-state"><div class="icon-big">🛡️</div><h4>Nenhum alerta ativo</h4><p>Todos os sensores dentro dos limites</p></div>`;
            } else {
                let html = '';
                data.alerts.forEach(al => {
                    html += `
                    <li class="event-item">
                        <span class="event-dot red"></span>
                        <div class="event-content">
                            <div class="title">${al.label} - ${al.message}</div>
                            <div class="time">${al.created_at} | Valor: ${al.value} | Limite: ${al.threshold}</div>
                        </div>
                        <span class="event-badge error">Alerta</span>
                    </li>`;
                });
                alertsList.innerHTML = html;
            }
        }

        // Atualizar lista de eventos
        const eventList = document.getElementById('event-list');
        if (eventList && data.events.length > 0) {
            let html = '';
            data.events.forEach(ev => {
                const dotClass = ev.type === 'info' ? 'green' : (ev.type === 'warning' ? 'orange' : 'red');
                html += `
                <li class="event-item">
                    <span class="event-dot ${dotClass}"></span>
                    <div class="event-content">
                        <div class="title">${ev.message}</div>
                        <div class="time">${ev.created_at}</div>
                    </div>
                    <span class="event-badge ${ev.type}">${ev.type.charAt(0).toUpperCase() + ev.type.slice(1)}</span>
                </li>`;
            });
            eventList.innerHTML = html;
        }
    } catch (e) { console.error('Erro ao buscar alertas:', e); }
}

// Init
fetchLatest();
fetchAlerts();
fetchHistory('temperature', currentTempRange);
fetchHistory('humidity', currentHumRange);

// Polling
setInterval(() => {
    fetchLatest();
    fetchAlerts();
    fetchHistory('temperature', currentTempRange);
    fetchHistory('humidity', currentHumRange);
}, POLLING_MS);
</script>
<script src="../assets/js/main.js?v=2"></script>
</body>
</html>


