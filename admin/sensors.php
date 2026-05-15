<?php
/**
 * Admin - CRUD de Sensores
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireAdmin();

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        if ($action === 'create') {
            $client_id = intval(isset($_POST['client_id']) ? $_POST['client_id'] : 0);
            $device_id = trim(isset($_POST['device_id']) ? $_POST['device_id'] : '');
            $label     = trim(isset($_POST['label']) ? $_POST['label'] : 'Sensor');
            $location  = trim(isset($_POST['location']) ? $_POST['location'] : '');
            $tmin      = floatval(isset($_POST['temp_min']) ? $_POST['temp_min'] : 18);
            $tmax      = floatval(isset($_POST['temp_max']) ? $_POST['temp_max'] : 28);
            $hmin      = floatval(isset($_POST['hum_min']) ? $_POST['hum_min'] : 40);
            $hmax      = floatval(isset($_POST['hum_max']) ? $_POST['hum_max'] : 70);

            $activation_date = !empty($_POST['activation_date']) ? $_POST['activation_date'] : null;

            if ($client_id > 0 && !empty($device_id)) {
                Database::execute(
                    "INSERT INTO sensors (client_id, device_id, label, location, temp_min, temp_max, hum_min, hum_max, activation_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$client_id, $device_id, $label, $location, $tmin, $tmax, $hmin, $hmax, $activation_date]
                );
                $msg = 'Sensor cadastrado!'; $msgType = 'success';
            } else {
                $msg = 'Cliente e Device ID são obrigatórios.'; $msgType = 'error';
            }
        }

        if ($action === 'update') {
            $id        = intval(isset($_POST['id']) ? $_POST['id'] : 0);
            $client_id = intval(isset($_POST['client_id']) ? $_POST['client_id'] : 0);
            $device_id = trim(isset($_POST['device_id']) ? $_POST['device_id'] : '');
            $label     = trim(isset($_POST['label']) ? $_POST['label'] : '');
            $location  = trim(isset($_POST['location']) ? $_POST['location'] : '');
            $tmin      = floatval(isset($_POST['temp_min']) ? $_POST['temp_min'] : 18);
            $tmax      = floatval(isset($_POST['temp_max']) ? $_POST['temp_max'] : 28);
            $hmin      = floatval(isset($_POST['hum_min']) ? $_POST['hum_min'] : 40);
            $hmax      = floatval(isset($_POST['hum_max']) ? $_POST['hum_max'] : 70);
            $active    = isset($_POST['is_active']) ? 1 : 0;

            $activation_date = !empty($_POST['activation_date']) ? $_POST['activation_date'] : null;

            if ($id > 0 && $client_id > 0 && !empty($device_id)) {
                Database::execute(
                    "UPDATE sensors SET client_id = ?, device_id = ?, label = ?, location = ?, temp_min = ?, temp_max = ?, hum_min = ?, hum_max = ?, activation_date = ?, is_active = ? WHERE id = ?",
                    [$client_id, $device_id, $label, $location, $tmin, $tmax, $hmin, $hmax, $activation_date, $active, $id]
                );
                $msg = 'Sensor atualizado!'; $msgType = 'success';
            }
        }

        if ($action === 'test_alert') {
            $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
            if ($id > 0) {
                $sensor = Database::queryOne("SELECT s.*, c.name as client_name, c.company, c.influx_bucket, c.influx_org, c.influx_token FROM sensors s JOIN clients c ON s.client_id = c.id WHERE s.id = ?", [$id]);
                if ($sensor) {
                    // Buscar contatos
                    $contacts = Database::query("SELECT phone FROM contacts WHERE client_id = ? AND is_active = 1", [$sensor['client_id']]);
                    $phones = array_column($contacts, 'phone');
                    
                    if (empty($phones)) {
                        throw new Exception("O cliente deste sensor não possui contatos ativos para receber o teste.");
                    }

                    require_once __DIR__ . '/../includes/influxdb.php';
                    $reading = InfluxDB::getLatestReading($sensor['device_id'], $sensor['influx_bucket'], $sensor['influx_org'], $sensor['influx_token']);
                    $temp = $reading['temperature'];
                    $hum = $reading['humidity'];
                    
                    $statusMsg = "Alerta de TESTE para o Sensor {$sensor['label']}.";
                    if ($temp !== null) {
                        $statusMsg = "TESTE: Sensor {$sensor['label']} operando em {$temp}°C.";
                    } else {
                        $statusMsg = "TESTE: Sensor {$sensor['label']} está offline ou sem leituras recentes.";
                    }

                    $payload = [
                        'client'    => $sensor['company'] ?: $sensor['client_name'],
                        'sensor'    => $sensor['device_id'],
                        'location'  => $sensor['location'],
                        'label'     => $sensor['label'],
                        'type'      => 'test_notification',
                        'field'     => 'test',
                        'value'     => $temp,
                        'threshold' => null,
                        'message'   => $statusMsg,
                        'ip'        => $reading['ip'] ?? null,
                        'mac'       => $reading['mac'] ?? null,
                        'contacts'  => $phones,
                        'timestamp' => date('Y-m-d\TH:i:s'),
                    ];

                    if (triggerWebhook($payload)) {
                        $msg = 'Alerta de teste enviado para os contatos!'; $msgType = 'success';
                    } else {
                        throw new Exception("Falha ao disparar o webhook. Verifique a configuração.");
                    }
                }
            }
        }

        if ($action === 'delete') {
            $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
            if ($id > 0) {
                Database::execute("DELETE FROM sensors WHERE id = ?", [$id]);
                $msg = 'Sensor removido!'; $msgType = 'success';
            }
        }
    } catch (Exception $e) {
        $msg = 'Erro no banco de dados: ' . $e->getMessage();
        $msgType = 'error';
    }
}

$sensors = Database::query(
    "SELECT s.*, c.name as client_name, c.company FROM sensors s JOIN clients c ON s.client_id = c.id ORDER BY s.created_at DESC"
);
$clients = Database::query("SELECT id, name, company FROM clients WHERE is_active = 1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Sensores</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
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
        <li><a href="sensors.php" class="active">Sensores</a></li>
        <li><a href="contacts.php">Contatos</a></li>
        <li><a href="admin_contacts.php">Admins</a></li>
        <li><a href="reports.php">Relatórios</a></li>
        <li><a href="settings.php">Configurações</a></li>
    </ul>
    <div class="navbar-right">
        <a href="dashboard.php?logout=1" class="btn btn-secondary btn-sm">Sair</a>
    </div>
</nav>

<main class="main-content">
    <div class="admin-header">
        <h1>Sensores</h1>
        <button class="btn btn-primary" onclick="openModal('create')">+ Novo Sensor</button>
    </div>

    <?php if ($msg): ?>
    <div class="alert-msg <?= $msgType ?>"><?= sanitize($msg) ?></div>
    <?php endif; ?>

    <table class="data-table">
        <thead>
            <tr>
                <th>Device ID</th>
                <th>Label</th>
                <th>Localização</th>
                <th>Cliente</th>
                <th>Temp (min/max)</th>
                <th>Umid (min/max)</th>
                <th>Ativação</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($sensors)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">Nenhum sensor cadastrado</td></tr>
            <?php else: ?>
            <?php foreach ($sensors as $s): ?>
            <tr>
                <td><code style="color:var(--accent-cyan);font-family:var(--font-mono);font-size:0.85rem;"><?= sanitize($s['device_id']) ?></code></td>
                <td><?= sanitize($s['label']) ?></td>
                <td><?= sanitize($s['location']) ?></td>
                <td><?= sanitize($s['client_name']) ?> <?= $s['company'] ? '<br><small style="color:var(--text-muted)">' . sanitize($s['company']) . '</small>' : '' ?></td>
                <td><?= $s['temp_min'] ?>°C / <?= $s['temp_max'] ?>°C</td>
                <td><?= $s['hum_min'] ?>% / <?= $s['hum_max'] ?>%</td>
                <td style="font-size:0.9rem; white-space: nowrap;"><?= $s['activation_date'] ? date('d/m/Y', strtotime($s['activation_date'])) : '-' ?></td>
                <td><span class="status-badge <?= $s['is_active'] ? 'safe' : 'danger' ?>"><?= $s['is_active'] ? 'Ativo' : 'Inativo' ?></span></td>
                <td>
                    <div class="actions-cell">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="test_alert">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" title="Enviar Alerta de Teste">⚡ Teste</button>
                        </form>
                        <button class="btn btn-secondary btn-sm" onclick='openModal("edit", <?= json_encode($s) ?>)'>Editar</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este sensor?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Excluir</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</main>

<!-- Modal -->
<div class="modal-overlay" id="modal">
    <div class="modal">
        <h2 id="modal-title">Novo Sensor</h2>
        <form method="POST">
            <input type="hidden" name="action" id="modal-action" value="create">
            <input type="hidden" name="id" id="modal-id" value="">
            <div class="form-group">
                <label>Cliente *</label>
                <select name="client_id" id="m-client" class="form-control" required>
                    <option value="">Selecione...</option>
                    <?php foreach ($clients as $cl): ?>
                    <option value="<?= $cl['id'] ?>"><?= sanitize($cl['name']) ?> <?= $cl['company'] ? '(' . sanitize($cl['company']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Device ID *</label>
                <input type="text" name="device_id" id="m-device" class="form-control" placeholder="precog_001" required>
            </div>
            <div class="form-group">
                <label>Label</label>
                <input type="text" name="label" id="m-label" class="form-control" placeholder="Sensor CPD" value="Sensor">
            </div>
            <div class="form-group">
                <label>Localização</label>
                <input type="text" name="location" id="m-location" class="form-control" placeholder="CPD" value="CPD">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                <div class="form-group">
                    <label>Temp Mín (°C)</label>
                    <input type="number" name="temp_min" id="m-tmin" class="form-control" step="0.1" value="18">
                </div>
                <div class="form-group">
                    <label>Temp Máx (°C)</label>
                    <input type="number" name="temp_max" id="m-tmax" class="form-control" step="0.1" value="28">
                </div>
                <div class="form-group">
                    <label>Umid Mín (%)</label>
                    <input type="number" name="hum_min" id="m-hmin" class="form-control" step="0.1" value="40">
                </div>
                <div class="form-group">
                    <label>Umid Máx (%)</label>
                    <input type="number" name="hum_max" id="m-hmax" class="form-control" step="0.1" value="70">
                </div>
            </div>
            <div class="form-group">
                <label>Data de Ativação (Financeiro)</label>
                <input type="date" name="activation_date" id="m-activation" class="form-control">
            </div>
            <div class="form-group" id="fg-active" style="display:none">
                <label><input type="checkbox" name="is_active" id="m-active" checked> Ativo</label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="button" class="btn btn-secondary btn-sm" id="btn-test" style="display:none" onclick="testAlert()">⚡ Testar Alerta</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(mode, data = {}) {
    document.getElementById('modal').classList.add('active');
    if (mode === 'edit') {
        document.getElementById('modal-title').textContent = 'Editar Sensor';
        document.getElementById('modal-action').value = 'update';
        document.getElementById('modal-id').value = data.id;
        document.getElementById('m-client').value = data.client_id;
        document.getElementById('m-device').value = data.device_id;
        document.getElementById('m-label').value = data.label;
        document.getElementById('m-location').value = data.location;
        document.getElementById('m-tmin').value = data.temp_min;
        document.getElementById('m-tmax').value = data.temp_max;
        document.getElementById('m-hmin').value = data.hum_min;
        document.getElementById('m-hmax').value = data.hum_max;
        document.getElementById('m-activation').value = data.activation_date || '';
        document.getElementById('m-active').checked = data.is_active == 1;
        document.getElementById('fg-active').style.display = 'block';
        document.getElementById('btn-test').style.display = 'inline-flex';
    } else {
        document.getElementById('modal-title').textContent = 'Novo Sensor';
        document.getElementById('modal-action').value = 'create';
        document.getElementById('modal-id').value = '';
        document.getElementById('m-client').value = '';
        document.getElementById('m-device').value = '';
        document.getElementById('m-label').value = 'Sensor';
        document.getElementById('m-location').value = 'CPD';
        document.getElementById('m-tmin').value = '18';
        document.getElementById('m-tmax').value = '28';
        document.getElementById('m-hmin').value = '40';
        document.getElementById('m-hmax').value = '70';
        document.getElementById('m-activation').value = '';
        document.getElementById('fg-active').style.display = 'none';
        document.getElementById('btn-test').style.display = 'none';
    }
}

function testAlert() {
    if (confirm('Enviar um alerta de teste para os contatos ativos do cliente deste sensor?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.name = 'action';
        actionInput.value = 'test_alert';
        
        const idInput = document.createElement('input');
        idInput.name = 'id';
        idInput.value = document.getElementById('modal-id').value;
        
        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}
function closeModal() { document.getElementById('modal').classList.remove('active'); }
document.getElementById('modal').addEventListener('click', e => { if (e.target.id === 'modal') closeModal(); });
</script>
<script src="../assets/js/main.js?v=2"></script>
</body>
</html>


