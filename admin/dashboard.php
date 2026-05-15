<?php
/**
 * Admin - Dashboard principal
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireAdmin();

// Logout
if (isset($_GET['logout'])) {
    Auth::logoutAdmin();
    header('Location: index.php');
    exit;
}

// Estatísticas gerais
$totalClients  = Database::queryOne("SELECT COUNT(*) as total FROM clients")['total'];
$activeClients = Database::queryOne("SELECT COUNT(*) as total FROM clients WHERE is_active = 1")['total'];
$totalSensors  = Database::queryOne("SELECT COUNT(*) as total FROM sensors")['total'];
$activeSensors = Database::queryOne("SELECT COUNT(*) as total FROM sensors WHERE is_active = 1")['total'];
$totalContacts = Database::queryOne("SELECT COUNT(*) as total FROM contacts WHERE is_active = 1")['total'];
$alertsToday   = Database::queryOne("SELECT COUNT(*) as total FROM alerts WHERE created_at >= NOW() - INTERVAL 24 HOUR")['total'];

// Últimos alertas
$recentAlerts = Database::query(
    "SELECT a.*, s.device_id, s.label, s.location, c.name as client_name, c.company
     FROM alerts a
     JOIN sensors s ON a.sensor_id = s.id
     JOIN clients c ON s.client_id = c.id
     ORDER BY a.created_at DESC LIMIT 10"
);

// Clientes recentes
$recentClients = Database::query("SELECT * FROM clients ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
</head>
<body>

<nav class="navbar">
    <div class="navbar-brand">
        <div class="icon-bars"><span></span><span></span><span></span><span></span></div>
        <?= APP_NAME ?>
    </div>
    <ul class="navbar-nav">
        <li><a href="dashboard.php" class="active">Dashboard</a></li>
        <li><a href="clients.php">Clientes</a></li>
        <li><a href="sensors.php">Sensores</a></li>
        <li><a href="contacts.php">Contatos</a></li>
        <li><a href="admin_contacts.php">Admins</a></li>
        <li><a href="reports.php">Relatórios</a></li>
        <li><a href="settings.php">Configurações</a></li>
    </ul>
    <div class="navbar-right">
        <a href="?logout=1" class="btn btn-secondary btn-sm">Sair</a>
    </div>
</nav>

<main class="main-content">
    <div class="admin-header">
        <h1>Painel Administrativo</h1>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-header"><span class="label">Clientes</span><span class="icon">👥</span></div>
            <div class="stat-value"><?= $activeClients ?><span class="fraction">/<?= $totalClients ?></span></div>
            <div class="stat-footer">Ativos / Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header"><span class="label">Sensores</span><span class="icon">📡</span></div>
            <div class="stat-value"><?= $activeSensors ?><span class="fraction">/<?= $totalSensors ?></span></div>
            <div class="stat-footer">Ativos / Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header"><span class="label">Contatos</span><span class="icon">📱</span></div>
            <div class="stat-value"><?= $totalContacts ?></div>
            <div class="stat-footer">Cadastrados para alertas</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-header"><span class="label">Alertas (24h)</span><span class="icon">🔔</span></div>
            <div class="stat-value"><?= $alertsToday ?></div>
            <div class="stat-footer"><?= $alertsToday == 0 ? 'Tudo normal' : 'Verificar!' ?></div>
        </div>
    </div>

    <!-- Bottom grid -->
    <div class="bottom-grid">
        <!-- Clientes recentes -->
        <div class="info-card">
            <div class="info-card-header">
                <span>👥</span>
                <h3>Clientes Recentes</h3>
            </div>
            <?php if (empty($recentClients)): ?>
            <div class="empty-state">
                <div class="icon-big">👤</div>
                <h4>Nenhum cliente</h4>
                <p><a href="clients.php">Cadastrar primeiro cliente</a></p>
            </div>
            <?php else: ?>
            <ul class="event-list">
                <?php foreach ($recentClients as $rc): ?>
                <li class="event-item">
                    <span class="event-dot <?= $rc['is_active'] ? 'green' : 'red' ?>"></span>
                    <div class="event-content">
                        <div class="title"><?= sanitize($rc['name']) ?> <?= $rc['company'] ? '(' . sanitize($rc['company']) . ')' : '' ?></div>
                        <div class="time"><?= formatDateRelative($rc['created_at']) ?></div>
                    </div>
                    <span class="event-badge info"><?= $rc['is_active'] ? 'Ativo' : 'Inativo' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Alertas recentes -->
        <div class="info-card">
            <div class="info-card-header">
                <span>🔥</span>
                <h3>Últimos Alertas</h3>
            </div>
            <?php if (empty($recentAlerts)): ?>
            <div class="empty-state">
                <div class="icon-big">🛡️</div>
                <h4>Nenhum alerta</h4>
                <p>Todos os sensores dentro dos limites</p>
            </div>
            <?php else: ?>
            <ul class="event-list">
                <?php foreach (array_slice($recentAlerts, 0, 5) as $al): ?>
                <li class="event-item">
                    <span class="event-dot red"></span>
                    <div class="event-content">
                        <div class="title"><?= sanitize($al['client_name']) ?> - <?= sanitize($al['label']) ?></div>
                        <div class="time"><?= formatDateRelative($al['created_at']) ?> | <?= sanitize($al['message']) ?></div>
                    </div>
                    <span class="event-badge <?= $al['webhook_sent'] ? 'info' : 'warning' ?>"><?= $al['webhook_sent'] ? 'Enviado' : 'Pendente' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</main>
<script src="../assets/js/main.js?v=2"></script>
</body>
</html>


