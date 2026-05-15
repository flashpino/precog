<?php
/**
 * Admin - CRUD de Clientes
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

        if ($action === 'create') {
            $name    = trim(isset($_POST['name']) ? $_POST['name'] : '');
            $company = trim(isset($_POST['company']) ? $_POST['company'] : '');
            $org     = trim(isset($_POST['influx_org']) ? $_POST['influx_org'] : '');
            $bucket  = trim(isset($_POST['influx_bucket']) ? $_POST['influx_bucket'] : '');
            $itoken  = trim(isset($_POST['influx_token']) ? $_POST['influx_token'] : '');
            
            $token   = Auth::generateToken();

            if (empty($name)) {
                $msg = 'Nome é obrigatório.'; $msgType = 'error';
            } else {
                Database::execute(
                    "INSERT INTO clients (name, company, token, influx_org, influx_bucket, influx_token) VALUES (?, ?, ?, ?, ?, ?)",
                    [$name, $company, $token, $org, $bucket, $itoken]
                );
                $msg = 'Cliente criado com sucesso!'; $msgType = 'success';
            }
        }

        if ($action === 'update') {
            $id      = intval(isset($_POST['id']) ? $_POST['id'] : 0);
            $name    = trim(isset($_POST['name']) ? $_POST['name'] : '');
            $company = trim(isset($_POST['company']) ? $_POST['company'] : '');
            $org     = trim(isset($_POST['influx_org']) ? $_POST['influx_org'] : '');
            $bucket  = trim(isset($_POST['influx_bucket']) ? $_POST['influx_bucket'] : '');
            $itoken  = trim(isset($_POST['influx_token']) ? $_POST['influx_token'] : '');
            $active  = isset($_POST['is_active']) ? 1 : 0;
            
            if ($id > 0 && !empty($name)) {
                Database::execute(
                    "UPDATE clients SET name = ?, company = ?, influx_org = ?, influx_bucket = ?, influx_token = ?, is_active = ? WHERE id = ?",
                    [$name, $company, $org, $bucket, $itoken, $active, $id]
                );
                $msg = 'Cliente atualizado!'; $msgType = 'success';
            }
        }

        if ($action === 'regenerate_token') {
            $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
            $newToken = Auth::generateToken();
            if ($id > 0) {
                Database::execute("UPDATE clients SET token = ? WHERE id = ?", [$newToken, $id]);
                $msg = 'Token regenerado!'; $msgType = 'success';
            }
        }

        if ($action === 'delete') {
            $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
            if ($id > 0) {
                Database::execute("DELETE FROM clients WHERE id = ?", [$id]);
                $msg = 'Cliente excluído!'; $msgType = 'success';
            }
        }
    } catch (Exception $e) {
        $msg = 'Erro no banco de dados: ' . $e->getMessage();
        $msgType = 'error';
    }
}

// Listar clientes
$clients = Database::query("SELECT c.*, (SELECT COUNT(*) FROM sensors WHERE client_id = c.id) as sensor_count, (SELECT COUNT(*) FROM contacts WHERE client_id = c.id) as contact_count FROM clients c ORDER BY c.created_at DESC");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Clientes</title>
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
        <li><a href="clients.php" class="active">Clientes</a></li>
        <li><a href="sensors.php">Sensores</a></li>
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
        <h1>Clientes</h1>
        <button class="btn btn-primary" onclick="openModal('create')">+ Novo Cliente</button>
    </div>

    <?php if ($msg): ?>
    <div class="alert-msg <?= $msgType ?>"><?= sanitize($msg) ?></div>
    <?php endif; ?>

    <table class="data-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Empresa</th>
                <th>Token</th>
                <th>Sensores</th>
                <th>Contatos</th>
                <th>Status</th>
                <th>Criado em</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clients)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:2rem;">Nenhum cliente cadastrado</td></tr>
            <?php else: ?>
            <?php foreach ($clients as $c): ?>
            <tr>
                <td><strong><?= sanitize($c['name']) ?></strong></td>
                <td><?= sanitize($c['company'] ?: '-') ?></td>
                <td><span class="token-display" title="Clique para copiar" onclick="copyToken(this)"><?= $c['token'] ?></span></td>
                <td><?= $c['sensor_count'] ?></td>
                <td><?= $c['contact_count'] ?></td>
                <td><span class="status-badge <?= $c['is_active'] ? 'safe' : 'danger' ?>"><?= $c['is_active'] ? 'Ativo' : 'Inativo' ?></span></td>
                <td><?= formatDateBR($c['created_at']) ?></td>
                <td>
                    <div class="actions-cell">
                        <button class="btn btn-secondary btn-sm" onclick='copyLink("<?= APP_URL . "/client/dashboard.php?token=" . $c["token"] ?>")' title="Copiar Link de Acesso">🔗 Link</button>
                        <button class="btn btn-secondary btn-sm" onclick='openModal("edit", <?= json_encode($c) ?>)'>Editar</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Excluir este cliente e todos os seus dados?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
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

<!-- Modal Criar/Editar -->
<div class="modal-overlay" id="modal">
    <div class="modal">
        <h2 id="modal-title">Novo Cliente</h2>
        <form method="POST" id="modal-form">
            <input type="hidden" name="action" id="modal-action" value="create">
            <input type="hidden" name="id" id="modal-id" value="">
            <div class="form-group">
                <label for="m-name">Nome *</label>
                <input type="text" id="m-name" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="m-company">Empresa</label>
                <input type="text" id="m-company" name="company" class="form-control">
            </div>
            <hr style="margin: 1rem 0; opacity: 0.2;">
            <div class="form-group">
                <label for="m-org">InfluxDB Organization</label>
                <input type="text" id="m-org" name="influx_org" class="form-control" placeholder="Ex: supera">
            </div>
            <div class="form-group">
                <label for="m-bucket">InfluxDB Bucket</label>
                <input type="text" id="m-bucket" name="influx_bucket" class="form-control" placeholder="Ex: cliente_a">
            </div>
            <div class="form-group">
                <label for="m-itoken">InfluxDB Token</label>
                <input type="text" id="m-itoken" name="influx_token" class="form-control" placeholder="Token de acesso">
            </div>
            <hr style="margin: 1rem 0; opacity: 0.2;">
            <div class="form-group" id="fg-active" style="display:none">
                <label><input type="checkbox" name="is_active" id="m-active" checked> Ativo</label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="button" class="btn btn-secondary btn-sm" id="btn-regen" style="display:none" onclick="regenerateToken()">🔄 Regenerar Token</button>
                <button type="submit" class="btn btn-primary" id="modal-submit">Salvar</button>
            </div>
        </form>
        <!-- Form escondido para regenerar token -->
        <form method="POST" id="regen-form" style="display:none">
            <input type="hidden" name="action" value="regenerate_token">
            <input type="hidden" name="id" id="regen-id" value="">
        </form>
    </div>
</div>

<script>
function openModal(mode, data = {}) {
    document.getElementById('modal').classList.add('active');
    if (mode === 'edit') {
        document.getElementById('modal-title').textContent = 'Editar Cliente';
        document.getElementById('modal-action').value = 'update';
        document.getElementById('modal-id').value = data.id;
        document.getElementById('m-name').value = data.name;
        document.getElementById('m-company').value = data.company || '';
        document.getElementById('m-org').value = data.influx_org || '';
        document.getElementById('m-bucket').value = data.influx_bucket || '';
        document.getElementById('m-itoken').value = data.influx_token || '';
        document.getElementById('m-active').checked = data.is_active == 1;

        document.getElementById('fg-active').style.display = 'block';
        document.getElementById('btn-regen').style.display = 'inline-flex';
        document.getElementById('regen-id').value = data.id;
    } else {
        document.getElementById('modal-title').textContent = 'Novo Cliente';
        document.getElementById('modal-action').value = 'create';
        document.getElementById('modal-id').value = '';
        document.getElementById('m-name').value = '';
        document.getElementById('m-company').value = '';
        document.getElementById('m-org').value = '';
        document.getElementById('m-bucket').value = '';
        document.getElementById('m-itoken').value = '';
        
        document.getElementById('fg-active').style.display = 'none';
        document.getElementById('btn-regen').style.display = 'none';
    }
}

function regenerateToken() {
    if (confirm('Deseja realmente gerar um novo token? O token antigo parará de funcionar.')) {
        document.getElementById('regen-form').submit();
    }
}

function copyLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('Link copiado para a área de transferência!');
    }).catch(err => {
        alert('Erro ao copiar: ' + err);
    });
}

function closeModal() {
    document.getElementById('modal').classList.remove('active');
}

function copyToken(el) {
    navigator.clipboard.writeText(el.textContent);
    const orig = el.textContent;
    el.textContent = '✅ Copiado!';
    el.style.color = 'var(--accent-green)';
    setTimeout(() => { el.textContent = orig; el.style.color = ''; }, 1500);
}

document.getElementById('modal').addEventListener('click', e => {
    if (e.target === document.getElementById('modal')) closeModal();
});
</script>
<script src="../assets/js/main.js?v=2"></script>
</body>
</html>


