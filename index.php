<?php
/**
 * Roteador principal
 */

require_once __DIR__ . '/config.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Redireciona para o dashboard do cliente se tiver token
if (isset($_GET['token'])) {
    header('Location: ' . APP_URL . '/client/dashboard.php?token=' . urlencode($_GET['token']));
    exit;
}

// Página padrão - redireciona para admin
header('Location: ' . APP_URL . '/admin/index.php');
exit;
