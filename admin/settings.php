<?php
/**
 * Admin - Configurações e Troca de Senha
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireAdmin();

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'change_password') {
        $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new     = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        $admin = Database::queryOne("SELECT password_hash FROM admins WHERE id = ?", [$_SESSION['admin_id']]);

        if (!password_verify($current, $admin['password_hash'])) {
            $msg = 'Senha atual incorreta.'; $msgType = 'error';
        } elseif (strlen($new) < 6) {
            $msg = 'A nova senha deve ter pelo menos 6 caracteres.'; $msgType = 'error';
        } elseif ($new !== $confirm) {
            $msg = 'As senhas não coincidem.'; $msgType = 'error';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            Database::execute("UPDATE admins SET password_hash = ? WHERE id = ?", [$hash, $_SESSION['admin_id']]);
            $msg = 'Senha alterada com sucesso!'; $msgType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Configurações</title>
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
        <li><a href="admin_contacts.php">Admins</a></li>
        <li><a href="reports.php">Relatórios</a></li>
        <li><a href="settings.php" class="active">Configurações</a></li>
        <li><a href="diagnostics.php">🔧 Diagnósticos</a></li>
    </ul>
    <div class="navbar-right">
        <a href="dashboard.php?logout=1" class="btn btn-secondary btn-sm">Sair</a>
    </div>
</nav>

<main class="main-content">
    <div class="admin-header">
        <h1>Configurações do Sistema</h1>
    </div>

    <?php if ($msg): ?>
    <div class="alert-msg <?= $msgType ?>"><?= sanitize($msg) ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <!-- Alterar Senha -->
        <div class="info-card">
            <div class="info-card-header">
                <span>🔐</span>
                <h3>Alterar Minha Senha</h3>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label>Senha Atual</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Nova Senha</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirmar Nova Senha</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Atualizar Senha</button>
            </form>
        </div>

        <!-- Informações do Sistema -->
        <div class="info-card">
            <div class="info-card-header">
                <span>ℹ️</span>
                <h3>Informações do Sistema</h3>
            </div>
            <div style="color:var(--text-secondary); font-size: 0.9rem;">
                <p style="margin-bottom: 0.5rem;"><strong>Versão:</strong> 1.0.0</p>
                <p style="margin-bottom: 0.5rem;"><strong>PHP:</strong> <?= PHP_VERSION ?></p>
                <p style="margin-bottom: 0.5rem;"><strong>InfluxDB:</strong> <?= sanitize(INFLUXDB_URL) ?></p>
                <p style="margin-bottom: 0.5rem;"><strong>Webhook n8n:</strong> <?= sanitize(N8N_WEBHOOK_URL) ?></p>
                <p style="margin-top: 1rem; color: var(--text-muted);">
                    Desenvolvido para monitoramento de ambientes críticos via ESP32.
                </p>
            </div>
        </div>
    </div>
</main>
<script src="../assets/js/main.js?v=2"></script>
</body>
</html>


