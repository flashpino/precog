<?php
/**
 * Admin - CRUD de Contatos para Alertas
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireAdmin();

$msg = '';
$msgType = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        // Função local para salvar preferências
        $savePreferences = function($contactId, $prefsPost) {
            Database::execute("DELETE FROM contact_alert_preferences WHERE contact_id = ?", [$contactId]);
            if (is_array($prefsPost)) {
                foreach ($prefsPost as $type => $p) {
                    if (!empty($p['enabled'])) {
                        $days = isset($p['days']) && is_array($p['days']) ? implode(',', $p['days']) : '0,1,2,3,4,5,6';
                        $tstart = empty($p['start']) ? '00:00' : $p['start'];
                        $tend = empty($p['end']) ? '23:59' : $p['end'];
                        $interval = empty($p['interval']) ? 30 : intval($p['interval']);
                        
                        Database::execute(
                            "INSERT INTO contact_alert_preferences (contact_id, alert_type, days_of_week, time_start, time_end, min_interval) VALUES (?, ?, ?, ?, ?, ?)",
                            [$contactId, $type, $days, $tstart, $tend, $interval]
                        );
                    }
                }
            }
        };

        if ($action === 'create') {
            $name  = trim(isset($_POST['name']) ? $_POST['name'] : '');
            $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');

            if (!empty($name) && !empty($phone)) {
                Database::execute(
                    "INSERT INTO contacts (client_id, name, phone, is_admin) VALUES (NULL, ?, ?, 1)",
                    [$name, $phone]
                );
                
                $contactId = Database::lastInsertId();
                if (isset($_POST['prefs'])) {
                    $savePreferences($contactId, $_POST['prefs']);
                }
                
                // Disparar webhook de novo admin (opcional)
                $payload = [
                    'event'    => 'admin_contact_added',
                    'contact'  => [
                        'name'  => $name,
                        'phone' => $phone
                    ],
                    'client'   => [
                        'id'      => 'ADMIN',
                        'name'    => 'Administração Geral',
                        'company' => ''
                    ],
                    'timestamp' => date('Y-m-d\TH:i:s')
                ];
                triggerWebhook($payload);

                $msg = 'Contato cadastrado!'; $msgType = 'success';
            } else {
                $msg = 'Preencha todos os campos obrigatórios.'; $msgType = 'error';
            }
        }

        if ($action === 'update') {
            $id      = intval(isset($_POST['id']) ? $_POST['id'] : 0);
            $name    = trim(isset($_POST['name']) ? $_POST['name'] : '');
            $phone   = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
            $active  = isset($_POST['is_active']) ? 1 : 0;

            if ($id > 0 && !empty($name)) {
                Database::execute(
                    "UPDATE contacts SET name = ?, phone = ?, is_active = ? WHERE id = ? AND is_admin = 1",
                    [$name, $phone, $active, $id]
                );
                
                if (isset($_POST['prefs'])) {
                    $savePreferences($id, $_POST['prefs']);
                }

                $msg = 'Contato atualizado!'; $msgType = 'success';
            }
        }

        if ($action === 'delete') {
            $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
            if ($id > 0) {
                Database::execute("DELETE FROM contacts WHERE id = ?", [$id]);
                $msg = 'Contato removido!'; $msgType = 'success';
            }
        }
    } catch (Exception $e) {
        $msg = 'Erro no banco de dados: ' . $e->getMessage();
        $msgType = 'error';
    }
}

$contacts = Database::query(
    "SELECT co.* FROM contacts co WHERE co.is_admin = 1 ORDER BY co.created_at DESC"
);

// Buscar preferências
$prefs = Database::query("SELECT * FROM contact_alert_preferences");
$prefsByContact = [];
foreach ($prefs as $p) {
    $prefsByContact[$p['contact_id']][$p['alert_type']] = $p;
}

foreach ($contacts as &$co) {
    $co['preferences'] = $prefsByContact[$co['id']] ?? [];
}
unset($co);

$clients = Database::query("SELECT id, name, company FROM clients WHERE is_active = 1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Contatos</title>
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
        <li><a href="sensors.php">Sensores</a></li>
        <li><a href="contacts.php">Contatos</a></li>
        <li><a href="admin_contacts.php" class="active">Admins</a></li>
        <li><a href="reports.php">Relatórios</a></li>
        <li><a href="settings.php">Configurações</a></li>
    </ul>
    <div class="navbar-right">
        <a href="dashboard.php?logout=1" class="btn btn-secondary btn-sm">Sair</a>
    </div>
</nav>

<main class="main-content">
    <div class="admin-header">
        <div>
            <h1>Contatos Administrativos</h1>
            <p style="color:var(--text-muted);font-size:0.85rem;margin-top:0.25rem;">Pessoas que receberão notificações via n8n</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('create')">+ Novo Contato</button>
    </div>



    <?php if ($msg): ?>
    <div class="alert-msg <?= $msgType ?>"><?= sanitize($msg) ?></div>
    <?php endif; ?>

    <table class="data-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Telefone</th>
                <th>Status</th>
                <th>Criado em</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($contacts)): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:2rem;">Nenhum contato cadastrado</td></tr>
            <?php else: ?>
            <?php foreach ($contacts as $co): ?>
            <tr>
                <td><strong><?= sanitize($co['name']) ?></strong></td>
                <td style="font-family:var(--font-mono);font-size:0.9rem;"><?= sanitize($co['phone']) ?></td>
                <td><span class="status-badge <?= $co['is_active'] ? 'safe' : 'danger' ?>"><?= $co['is_active'] ? 'Ativo' : 'Inativo' ?></span></td>
                <td><?= formatDateBR($co['created_at']) ?></td>
                <td>
                    <div class="actions-cell">
                        <button class="btn btn-secondary btn-sm" onclick='openModal("edit", <?= json_encode($co) ?>)'>Editar</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este contato?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $co['id'] ?>">
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
    <div class="modal" style="width: 600px; max-width: 95%;">
        <h2 id="modal-title">Novo Contato</h2>
        <form method="POST">
            <input type="hidden" name="action" id="modal-action" value="create">
            <input type="hidden" name="id" id="modal-id" value="">
            
            <div style="display:flex; gap: 15px;">
                <div class="form-group" style="flex:1;">
                    <label>Telefone (WhatsApp) *</label>
                    <input type="text" name="phone" id="m-phone" class="form-control" placeholder="+5511999999999" required>
                </div>
            </div>

            <div class="form-group">
                <label>Nome *</label>
                <input type="text" name="name" id="m-name" class="form-control" placeholder="João Silva" required>
            </div>

            <hr style="margin: 1.5rem 0; opacity: 0.2;">
            <h3 style="margin-bottom: 1rem; font-size: 1.1rem;">Preferências de Alertas</h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                Selecione quais alertas este contato deseja receber. Se não selecionar nenhum, ele receberá tudo por padrão (comportamento legado).
            </p>

            <?php 
            $alertTypes = [
                'connectivity' => 'Conectividade (Online / Offline / Queda)',
                'temperature'  => 'Temperatura (Alta / Baixa / Normalizou)',
                'humidity'     => 'Umidade (Alta / Baixa / Normalizou)'
            ];
            foreach ($alertTypes as $type => $label): 
            ?>
            <div style="background: var(--bg-color); border: 1px solid var(--border-color); padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                <div class="form-group" style="margin-bottom: 10px;">
                    <label style="font-weight: bold; font-size: 1rem;">
                        <input type="checkbox" name="prefs[<?= $type ?>][enabled]" id="pref-<?= $type ?>-enabled" value="1" onchange="togglePrefDetails('<?= $type ?>')">
                        <?= $label ?>
                    </label>
                </div>
                
                <div id="pref-<?= $type ?>-details" style="display: none; padding-left: 25px; border-left: 2px solid var(--border-color); margin-top: 10px;">
                    <div class="form-group">
                        <label>Dias de Envio</label>
                        <div style="display:flex; gap:10px; flex-wrap:wrap; font-size:0.9rem;">
                            <label><input type="checkbox" name="prefs[<?= $type ?>][days][]" id="pref-<?= $type ?>-d1" value="1" checked> Seg</label>
                            <label><input type="checkbox" name="prefs[<?= $type ?>][days][]" id="pref-<?= $type ?>-d2" value="2" checked> Ter</label>
                            <label><input type="checkbox" name="prefs[<?= $type ?>][days][]" id="pref-<?= $type ?>-d3" value="3" checked> Qua</label>
                            <label><input type="checkbox" name="prefs[<?= $type ?>][days][]" id="pref-<?= $type ?>-d4" value="4" checked> Qui</label>
                            <label><input type="checkbox" name="prefs[<?= $type ?>][days][]" id="pref-<?= $type ?>-d5" value="5" checked> Sex</label>
                            <label><input type="checkbox" name="prefs[<?= $type ?>][days][]" id="pref-<?= $type ?>-d6" value="6" checked> Sáb</label>
                            <label><input type="checkbox" name="prefs[<?= $type ?>][days][]" id="pref-<?= $type ?>-d0" value="0" checked> Dom</label>
                        </div>
                    </div>
                    
                    <div style="display:flex; gap: 15px;">
                        <div class="form-group" style="flex:2">
                            <label>Horário (vazio = 24h)</label>
                            <div style="display:flex; gap:5px; align-items:center;">
                                <input type="time" name="prefs[<?= $type ?>][start]" id="pref-<?= $type ?>-start" class="form-control" style="padding: 0.4rem;">
                                <span>-</span>
                                <input type="time" name="prefs[<?= $type ?>][end]" id="pref-<?= $type ?>-end" class="form-control" style="padding: 0.4rem;">
                            </div>
                        </div>
                        <div class="form-group" style="flex:1">
                            <label>Intervalo (min)</label>
                            <input type="number" name="prefs[<?= $type ?>][interval]" id="pref-<?= $type ?>-interval" class="form-control" value="30" min="1" style="padding: 0.4rem;">
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="form-group" id="fg-active" style="display:none; margin-top: 15px;">
                <label><input type="checkbox" name="is_active" id="m-active" checked> Contato Ativo</label>
            </div>
            <div class="modal-actions" style="margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePrefDetails(type) {
    const isChecked = document.getElementById('pref-' + type + '-enabled').checked;
    document.getElementById('pref-' + type + '-details').style.display = isChecked ? 'block' : 'none';
}

function resetPrefs() {
    ['connectivity', 'temperature', 'humidity'].forEach(type => {
        document.getElementById('pref-' + type + '-enabled').checked = false;
        togglePrefDetails(type);
        for(let i=0; i<=6; i++) {
            document.getElementById('pref-' + type + '-d' + i).checked = true;
        }
        document.getElementById('pref-' + type + '-start').value = '';
        document.getElementById('pref-' + type + '-end').value = '';
        document.getElementById('pref-' + type + '-interval').value = '30';
    });
}

function openModal(mode, data = {}) {
    document.getElementById('modal').classList.add('active');
    resetPrefs();
    
    if (mode === 'edit') {
        document.getElementById('modal-title').textContent = 'Editar Contato';
        document.getElementById('modal-action').value = 'update';
        document.getElementById('modal-id').value = data.id;
        document.getElementById('modal-id').value = data.id;
        document.getElementById('m-name').value = data.name;
        document.getElementById('m-phone').value = data.phone;
        document.getElementById('m-active').checked = data.is_active == 1;
        document.getElementById('fg-active').style.display = 'block';
        
        // Load preferences
        if (data.preferences) {
            Object.keys(data.preferences).forEach(type => {
                let p = data.preferences[type];
                document.getElementById('pref-' + type + '-enabled').checked = true;
                togglePrefDetails(type);
                
                let days = p.days_of_week ? p.days_of_week.split(',') : [];
                for(let i=0; i<=6; i++) {
                    document.getElementById('pref-' + type + '-d' + i).checked = days.includes(i.toString());
                }
                
                document.getElementById('pref-' + type + '-start').value = (p.time_start && p.time_start != '00:00:00') ? p.time_start.substring(0,5) : '';
                document.getElementById('pref-' + type + '-end').value = (p.time_end && p.time_end != '23:59:00') ? p.time_end.substring(0,5) : '';
                document.getElementById('pref-' + type + '-interval').value = p.min_interval || 30;
            });
        }
    } else {
        document.getElementById('modal-title').textContent = 'Novo Contato';
        document.getElementById('modal-action').value = 'create';
        document.getElementById('modal-id').value = '';
        document.getElementById('modal-id').value = '';
        document.getElementById('m-name').value = '';
        document.getElementById('m-phone').value = '';
        document.getElementById('fg-active').style.display = 'none';
    }
}
function closeModal() { document.getElementById('modal').classList.remove('active'); }
document.getElementById('modal').addEventListener('click', e => { if (e.target.id === 'modal') closeModal(); });
</script>
<script src="../assets/js/main.js?v=2"></script>
</body>
</html>


