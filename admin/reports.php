<?php
/**
 * Admin - Relatórios de Temperatura
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/influxdb.php';

Auth::requireAdmin();

// Função para embutir imagens no PDF (base64)
function imgBase64($path) {
    if (!file_exists($path)) return null;
    $mime = mime_content_type($path);
    $data = base64_encode(file_get_contents($path));
    return "data:$mime;base64,$data";
}

$logoPrecog = imgBase64(__DIR__ . '/../assets/img/logo-precog.png');
$logoClient = imgBase64(__DIR__ . '/../assets/img/logo-cliente.png'); // Logo padrão ou gsf.png

// Endpoint AJAX para buscar sensores de um cliente
if (isset($_GET['get_sensors'])) {
    $cId = intval($_GET['get_sensors']);
    $list = Database::query("SELECT id, device_id, label FROM sensors WHERE client_id = ? AND is_active = 1", [$cId]);
    header('Content-Type: application/json');
    echo json_encode($list);
    exit;
}

$clientId = $_GET['client_id'] ?? null;
$sensorId = $_GET['sensor_id'] ?? null;
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

$clients = Database::query("SELECT id, name, company FROM clients WHERE is_active = 1 ORDER BY name");

$reportData = null;
$selectedSensor = null;

if ($clientId && $sensorId && $startDate && $endDate) {
    // Buscar detalhes do sensor
    // Buscar detalhes do sensor e credenciais do cliente
    $selectedSensor = Database::queryOne("
        SELECT s.*, c.name as client_name, c.influx_org, c.influx_bucket, c.influx_token 
        FROM sensors s 
        JOIN clients c ON s.client_id = c.id 
        WHERE s.id = ?
    ", [$sensorId]);
    
    if ($selectedSensor) {
        $tz = new DateTimeZone('America/Sao_Paulo');
        
        $dtStart = new DateTime($startDate . ' 00:00:00', $tz);
        $dtStart->setTimezone(new DateTimeZone('UTC'));
        $startIso = $dtStart->format('Y-m-d\TH:i:s\Z');
        
        $dtEnd = new DateTime($endDate . ' 23:59:59', $tz);
        $dtEnd->setTimezone(new DateTimeZone('UTC'));
        $endIso = $dtEnd->format('Y-m-d\TH:i:s\Z');
        
        // Calcular o offset em horas para o agrupamento do Influx (ex: 3h para GMT-3)
        // Isso força a janela do InfluxDB a começar à meia-noite do Brasil e não à meia-noite UTC.
        $offsetSeconds = $tz->getOffset(new DateTime($startDate));
        $offsetHours = abs($offsetSeconds / 3600);
        $offsetStr = ($offsetSeconds < 0 ? $offsetHours : '-' . $offsetHours) . 'h'; // Para UTC-3, a meia-noite ocorre às 03:00 UTC, então o offset é 3h
        
        $deviceId = $selectedSensor['device_id'];

        $bucket = !empty($selectedSensor['influx_bucket']) ? $selectedSensor['influx_bucket'] : INFLUXDB_BUCKET;
        $org    = !empty($selectedSensor['influx_org'])    ? $selectedSensor['influx_org']    : INFLUXDB_ORG;
        $token  = !empty($selectedSensor['influx_token'])  ? $selectedSensor['influx_token']  : INFLUXDB_TOKEN;

        // Query InfluxDB para médias diárias
        $flux = 'data = from(bucket: "' . $bucket . '")
            |> range(start: ' . $startIso . ', stop: ' . $endIso . ')
            |> filter(fn: (r) => r["device_id"] == "' . $deviceId . '")
            |> filter(fn: (r) => r["_field"] == "temperatura")
            
            min_val = data |> window(every: 1d, offset: ' . $offsetStr . ') |> min() |> set(key: "_field", value: "min")
            max_val = data |> window(every: 1d, offset: ' . $offsetStr . ') |> max() |> set(key: "_field", value: "max")
            avg_val = data |> aggregateWindow(every: 1d, fn: mean, offset: ' . $offsetStr . ', timeSrc: "_start", createEmpty: false) |> set(key: "_field", value: "mean")
            
            union(tables: [min_val, max_val, avg_val])';

        $rawResults = InfluxDB::query($flux, $org, $token);
        
        // Organizar dados por dia
        $daily = [];
        foreach ($rawResults as $row) {
            if (empty($row['_time'])) continue;
            
            $dt = new DateTime($row['_time']);
            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
            $day = $dt->format('Y-m-d');
            $hour = $dt->format('H:i');
            
            if (!isset($daily[$day])) {
                $daily[$day] = [
                    'date'     => $dt->format('d/m/Y'),
                    'dia'      => $dt->format('d'),
                    'min'      => null,
                    'max'      => null,
                    'mean'     => null,
                    'hora_min' => '-',
                    'hora_max' => '-',
                ];
            }
            $val = $row['_value'] !== "" ? round(floatval($row['_value']), 1) : null;
            if ($row['_field'] === 'min') {
                $daily[$day]['min'] = $val;
                $daily[$day]['hora_min'] = $hour;
            }
            if ($row['_field'] === 'max') {
                $daily[$day]['max'] = $val;
                $daily[$day]['hora_max'] = $hour;
            }
            if ($row['_field'] === 'mean') {
                $daily[$day]['mean'] = $val;
            }
        }

        // Ordenar por data
        ksort($daily);

        // Calcular estatísticas (Threshold Legado: 24°C)
        $cntNormal = 0;
        $cntAlerta = 0;
        $processedDaily = [];
        foreach ($daily as $dayKey => $d) {
            $amp = ($d['max'] !== null && $d['min'] !== null) ? round($d['max'] - $d['min'], 1) : null;
            
            $cond = 'Estável';
            $condColor = '#27ae60';
            if ($amp > 5) {
                $cond = 'Instável';
                $condColor = '#c0392b';
            } elseif ($amp > 2) {
                $cond = 'Moderada';
                $condColor = '#f39c12';
            }

            if ($d['max'] !== null && $d['max'] > 24) {
                $cntAlerta++;
            } else {
                $cntNormal++;
            }

            $processedDaily[] = array_merge($d, [
                'amp' => $amp,
                'cond' => $cond,
                'cond_color' => $condColor
            ]);
        }
        $cntTotal = count($processedDaily);
        $percNormal = $cntTotal ? round(($cntNormal / $cntTotal) * 100) : 0;
        $percAlerta = $cntTotal ? round(($cntAlerta / $cntTotal) * 100) : 0;
        
        $reportData = [
            'daily' => $processedDaily,
            'stats' => [
                'total' => $cntTotal,
                'normal' => $cntNormal,
                'alerta' => $cntAlerta,
                'percent_normal' => $percNormal,
                'percent_alerta' => $percAlerta,
            ]
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Relatórios</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --accent: var(--accent-cyan);
            --success: var(--accent-green);
            --warning: var(--accent-orange);
            --danger: var(--accent-red);
        }
        
        * { box-sizing: border-box; }
        body { font-family: var(--font-sans); background: var(--bg-primary); color: var(--text-primary); margin: 0; min-height: 100vh; }
        
        /* Layout do Relatório */
        #printable-report { 
            width: 1100px; 
            margin: 0 auto 40px; 
            background: #fff; 
            box-shadow: 0 4px 30px rgba(0,0,0,0.4); 
            border-radius: 8px; 
            overflow: hidden; 
            display: none;
            color: #2c3e50; /* Forçar cor escura no papel */
        }
        #printable-report.active { display: block; }
        
        .rep-hdr { 
            background: linear-gradient(135deg, #1a252f, #2c3e50); 
            color: #fff; 
            padding: 18px 25px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            gap: 16px;
        }
        .rep-hdr h1 { margin: 0 0 5px; font-size: 21px; color: #fff; }
        .rep-hdr p { margin: 2px 0; font-size: 11px; color: #bdc3c7; }
        
        .rep-sec { padding: 12px 25px; }
        .sec-t { 
            font-size: 14px; 
            font-weight: 700; 
            color: var(--accent); 
            border-bottom: 2px solid var(--accent); 
            padding-bottom: 5px; 
            margin: 0 0 14px; 
        }
        
        .cards { display: flex; gap: 12px; }
        .card { 
            flex: 1; 
            border-radius: 8px; 
            border: 1px solid #e0e0e0; 
            padding: 14px; 
            text-align: center; 
            background: #fafafa; 
        }
        .card .v { font-size: 26px; font-weight: 800; margin-bottom: 2px; }
        .card .l { font-size: 10px; color: #7f8c8d; }
        
        .card.az { border-top: 4px solid var(--accent); } .card.az .v { color: var(--accent); }
        .card.vd { border-top: 4px solid var(--success); } .card.vd .v { color: var(--success); }
        .card.vm { border-top: 4px solid var(--danger); } .card.vm .v { color: var(--danger); }
        
        .cbox { border: 1px solid #e8e8e8; border-radius: 8px; padding: 12px; background: #fafafa; width: 100%; }
        .cap { font-size: 10px; color: #999; text-align: center; margin-top: 6px; font-style: italic; }
        
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { background: #2c3e50; color: #fff; padding: 8px 7px; text-align: center; white-space: nowrap; }
        td { padding: 7px; border-bottom: 1px solid #e8e8e8; white-space: nowrap; text-align: center; }
        
        .foot { text-align: center; font-size: 10px; color: #aaa; padding: 14px 28px; border-top: 1px solid #eee; }

        /* Estilo do Formulário de Filtros Dashboard */
        .report-filter-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-card);
        }
        .report-filter-card form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            align-items: flex-end;
        }
        .report-filter-card label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-filter-card .form-control {
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: var(--radius-sm);
            padding: 0.6rem 1rem;
            width: 100%;
            outline: none;
            transition: border-color 0.2s;
        }
        .report-filter-card .form-control:focus {
            border-color: var(--accent);
        }
        .btn-submit {
            background: var(--gradient-cyan);
            color: var(--bg-primary);
            border: none;
            border-radius: var(--radius-sm);
            padding: 0.6rem 1.5rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            height: 38px;
            font-size: 0.85rem;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 230, 180, 0.3);
        }
        .btn-download { background: var(--accent); color: var(--bg-primary); padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; margin-bottom: 20px; }
        .btn-download:hover { opacity: 0.9; transform: translateY(-1px); }

        @media print {
            body { background: #fff; }
            #printable-report { width: 100%; box-shadow: none; border-radius: 0; margin: 0; display: block; }
            .navbar, .report-filter-card, .btn-download, .admin-header { display: none !important; }
            .main-content { margin: 0; padding: 0; }
            @page { size: A4 landscape; margin: 10mm 8mm; }
        }
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
        <li><a href="reports.php" class="active">Relatórios</a></li>
        <li><a href="settings.php">Configurações</a></li>
    </ul>
    <div class="navbar-right">
        <a href="dashboard.php?logout=1" class="btn btn-secondary btn-sm">Sair</a>
    </div>
</nav>

<main class="main-content">
    <div class="admin-header">
        <h1>Relatórios de Temperatura</h1>
    </div>

    <div class="report-filter-card">
        <form method="GET" id="filter-form">
            <div class="filter-group">
                <label>Cliente</label>
                <select name="client_id" id="client_id" class="form-control" required onchange="loadSensors(this.value)">
                    <option value="">Selecione...</option>
                    <?php foreach ($clients as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $clientId == $c['id'] ? 'selected' : '' ?>>
                        <?= sanitize($c['name']) ?> <?= $c['company'] ? '(' . sanitize($c['company']) . ')' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Sensor</label>
                <select name="sensor_id" id="sensor_id" class="form-control" required>
                    <option value="">Selecione o cliente primeiro</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Início</label>
                <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>" required>
            </div>
            <div class="filter-group">
                <label>Fim</label>
                <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>" required>
            </div>
            <div class="filter-group">
                <button type="submit" class="btn-submit">GERAR RELATÓRIO</button>
            </div>
      <?php if ($reportData): ?>
    <div style="text-align: right;">
        <button onclick="gerarPDF()" class="btn-download">⬇ Baixar PDF</button>
    </div>

    <div id="printable-report" class="active">
        <!-- CABEÇALHO -->
        <div class="rep-hdr">
            <?php if ($logoClient): ?>
                <img src="<?= $logoClient ?>" style="height:48px; object-fit:contain;">
            <?php else: ?>
                <div style="width:48px"></div>
            <?php endif; ?>
            
            <div style="flex:1; text-align:center">
                <h1>Relatório de Monitoramento de Temperatura Datacenter</h1>
                <p>Período: <b><?= date('d/m/Y', strtotime($startDate)) ?> a <?= date('d/m/Y', strtotime($endDate)) ?></b> &nbsp;|&nbsp; Gerado em: <?= date('d/m/Y H:i') ?></p>
                <p>Relatório Gerado automaticamente por PrecogSystem</p>
            </div>

            <?php if ($logoPrecog): ?>
                <img src="<?= $logoPrecog ?>" style="height:28px; object-fit:contain;">
            <?php else: ?>
                <div style="width:48px"></div>
            <?php endif; ?>
        </div>

        <!-- RESUMO -->
        <div class="rep-sec" style="padding-bottom:8px">
            <p class="sec-t">Resumo do Período</p>
            <div class="cards">
                <div class="card az">
                    <div class="v"><?= $reportData['stats']['total'] ?></div>
                    <div class="l">Dias analisados</div>
                </div>
                <div class="card vd">
                    <div class="v"><?= $reportData['stats']['normal'] ?> <small style="font-size:13px">(<?= $reportData['stats']['percent_normal'] ?>%)</small></div>
                    <div class="l">Dias Normais (max ≤ 24°C)</div>
                </div>
                <div class="card vm">
                    <div class="v"><?= $reportData['stats']['alerta'] ?> <small style="font-size:13px">(<?= $reportData['stats']['percent_alerta'] ?>%)</small></div>
                    <div class="l">Dias em Alerta (max > 24°C)</div>
                </div>
            </div>
        </div>

        <!-- GRÁFICO DE LINHA -->
        <div class="rep-sec" style="padding-top:8px; padding-bottom:8px">
            <p class="sec-t">Evolução das Temperaturas</p>
            <div class="cbox">
                <canvas id="tempChart" height="85"></canvas>
                <p class="cap">Temperatura Mínima, Média e Máxima diária</p>
            </div>
        </div>

        <!-- GRÁFICO DE AMPLITUDE -->
        <div class="rep-sec" style="padding-top:8px; padding-bottom:8px">
            <p class="sec-t">Amplitude Térmica Diária</p>
            <div class="cbox">
                <canvas id="ampChart" height="75"></canvas>
                <p class="cap">Diretrizes ASHRAE: 0°C a 2°C Estável, 2°C a 5°C Moderada, acima de 5°C Instável</p>
            </div>
        </div>

        <!-- TABELA -->
        <div class="rep-sec" style="padding-top:10px">
            <p class="sec-t">Detalhamento</p>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Sensor</th>
                        <th>Mínima</th>
                        <th>Hora Min</th>
                        <th>Máxima</th>
                        <th>Hora Max</th>
                        <th>Média</th>
                        <th>Amplitude</th>
                        <th>Condição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reportData['daily'] as $d): ?>
                    <tr style="background: <?= ($d['max'] > 24 ? '#fdecea' : '#eafaf1') ?>;">
                        <td><?= $d['date'] ?></td>
                        <td><b><?= sanitize($selectedSensor['label']) ?></b></td>
                        <td><?= $d['min'] !== null ? number_format($d['min'], 1) . '° C' : '-' ?></td>
                        <td><?= $d['hora_min'] ?></td>
                        <td><?= $d['max'] !== null ? number_format($d['max'], 1) . '° C' : '-' ?></td>
                        <td><?= $d['hora_max'] ?></td>
                        <td><?= $d['mean'] !== null ? number_format($d['mean'], 1) . '° C' : '-' ?></td>
                        <td><?= $d['amp'] !== null ? number_format($d['amp'], 1) . '° C' : '-' ?></td>
                        <td>
                            <span style="background: <?= $d['cond_color'] ?>; color: #fff; padding: 3px 9px; border-radius: 12px; font-size: 10px; font-weight: 700;">
                                <?= mb_strtoupper($d['cond']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="foot">
            Relatório de Temperatura | PrecogSystem | www.precogsystem.com.br
        </div>
    </div>
    <?php elseif (isset($_GET['client_id'])): ?>
    <div class="filter-card" style="text-align: center;">
        <p style="color: #666;">Nenhum dado encontrado para o período selecionado.</p>
    </div>
    <?php endif; ?>

</main>

<script>
async function loadSensors(clientId, selectedId = null) {
    const sensorSelect = document.getElementById('sensor_id');
    sensorSelect.innerHTML = '<option value="">Carregando...</option>';
    
    if (!clientId) {
        sensorSelect.innerHTML = '<option value="">Selecione o cliente primeiro</option>';
        return;
    }

    try {
        const response = await fetch(`reports.php?get_sensors=${clientId}`);
        const sensors = await response.json();
        
        sensorSelect.innerHTML = sensors.length ? '' : '<option value="">Nenhum sensor ativo</option>';
        sensors.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = `${s.label} (${s.device_id})`;
            if (s.id == selectedId) opt.selected = true;
            sensorSelect.appendChild(opt);
        });
    } catch (e) {
        sensorSelect.innerHTML = '<option value="">Erro ao carregar</option>';
    }
}

// Inicializar sensor se já tiver selecionado
<?php if ($clientId): ?>
loadSensors(<?= $clientId ?>, <?= $sensorId ?: 'null' ?>);
<?php endif; ?>

<?php if ($reportData): ?>
// Gráfico de Evolução
const ctx = document.getElementById('tempChart').getContext('2d');
const labels = <?= json_encode(array_column($reportData['daily'], 'dia')) ?>;
const dMin = <?= json_encode(array_column($reportData['daily'], 'min')) ?>;
const dMax = <?= json_encode(array_column($reportData['daily'], 'max')) ?>;
const dMean = <?= json_encode(array_column($reportData['daily'], 'mean')) ?>;

const f = { size: 11 };
const gc = 'rgba(0,0,0,0.07)';

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            { label: 'Máxima', data: dMax, borderColor: '#c0392b', backgroundColor: 'rgba(192,57,43,0.07)', pointRadius: 2, fill: false, tension: 0.3, borderWidth: 2 },
            { label: 'Média', data: dMean, borderColor: '#27ae60', backgroundColor: 'rgba(39,174,96,0.07)', pointRadius: 2, fill: false, tension: 0.3, borderWidth: 2 },
            { label: 'Mínima', data: dMin, borderColor: '#2980b9', backgroundColor: 'rgba(41,128,185,0.07)', pointRadius: 2, fill: false, tension: 0.3, borderWidth: 2 }
        ]
    },
    options: {
        responsive: true,
        plugins: { 
            legend: { position: 'top', labels: { font: f, boxWidth: 13 } },
            tooltip: { callbacks: { label: c => ` ${c.dataset.label}: ${c.parsed.y}C` } }
        },
        scales: {
            x: { ticks: { font: f }, grid: { color: gc } },
            y: { ticks: { font: f, callback: v => parseFloat(v).toFixed(1) + '°C' }, grid: { color: gc } }
        }
    }
});

// Gráfico de Amplitude com Linhas de Referência
const dAmp = <?= json_encode(array_column($reportData['daily'], 'amp')) ?>;
const dCor = <?= json_encode(array_column($reportData['daily'], 'cond_color')) ?>;

const ctxAmp = document.getElementById('ampChart').getContext('2d');
new Chart(ctxAmp, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Amplitude',
            data: dAmp,
            backgroundColor: dCor,
            borderColor: dCor,
            borderWidth: 1,
            borderRadius: 3
        }]
    },
    options: {
        responsive: true,
        plugins: { 
            legend: { display: false },
            tooltip: { callbacks: { label: c => ` Amplitude: ${c.parsed.y}C` } }
        },
        scales: {
            x: { ticks: { font: f }, grid: { color: gc } },
            y: { min: 0, ticks: { font: f, callback: v => parseFloat(v).toFixed(1) + '°C' }, grid: { color: gc } }
        }
    },
    plugins: [{
        afterDraw(ch) {
            const { ctx, chartArea: { left, right }, scales: { y } } = ch;
            [[2, '#27ae60', '2°C'], [5, '#c0392b', '5°C']].forEach(([v, cor, txt]) => {
                const yp = y.getPixelForValue(v);
                if (yp >= ch.chartArea.top && yp <= ch.chartArea.bottom) {
                    ctx.save();
                    ctx.beginPath();
                    ctx.setLineDash([6, 4]);
                    ctx.strokeStyle = cor;
                    ctx.lineWidth = 1.5;
                    ctx.moveTo(left, yp);
                    ctx.lineTo(right, yp);
                    ctx.stroke();
                    ctx.fillStyle = cor;
                    ctx.font = '10px Arial';
                    ctx.textAlign = 'right';
                    ctx.fillText(txt, right, yp - 4);
                    ctx.restore();
                }
            });
        }
    }]
});

async function gerarPDF() {
    const btn = document.querySelector('.btn-download');
    btn.textContent = 'Gerando...';
    btn.disabled = true;

    const { jsPDF } = window.jspdf;
    const report = document.getElementById('printable-report');
    
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    const pdfW = pdf.internal.pageSize.getWidth();
    const pdfH = pdf.internal.pageSize.getHeight();
    const margin = 8;
    const usableW = pdfW - margin * 2;
    const usableH = pdfH - margin * 2;

    const blocos = report.querySelectorAll('.rep-hdr, .rep-sec');
    let currentPageHeight = margin;
    let primeiroBloco = true;

    for (let i = 0; i < blocos.length; i++) {
        const bloco = blocos[i];

        const canvas = await html2canvas(bloco, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
        });

        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const imgW = usableW;
        const imgH = (canvas.height * imgW) / canvas.width;

        if (!primeiroBloco && (currentPageHeight + imgH > usableH)) {
            pdf.addPage();
            currentPageHeight = margin;
        }

        pdf.addImage(imgData, 'JPEG', margin, currentPageHeight, imgW, imgH);
        currentPageHeight += imgH + 2; // Espaçamento entre blocos
        primeiroBloco = false;
    }

    pdf.save(`Relatorio_Temperatura_<?= $selectedSensor['device_id'] ?>.pdf`);
    
    btn.textContent = '⬇ Baixar PDF';
    btn.disabled = false;
}
<?php endif; ?>
</script>

<script src="../assets/js/main.js?v=2"></script>
</body>
</html>


