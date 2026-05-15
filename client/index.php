<?php
/**
 * Tela de Entrada do Cliente - Login via Token
 */
require_once __DIR__ . '/../includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim(isset($_POST['token']) ? $_POST['token'] : '');
    if (empty($token)) {
        $error = 'Por favor, insira seu código de acesso.';
    } else {
        $client = Auth::validateClientToken($token);
        if ($client) {
            header("Location: dashboard.php?token=" . urlencode($token));
            exit;
        } else {
            $error = 'Código de acesso inválido ou expirado.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Acesso do Cliente</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
</head>
<body class="login-page">
<div class="login-container">
    <div class="login-card">
        <div style="text-align:center;margin-bottom:1.5rem;">
            <div class="navbar-brand" style="justify-content:center;font-size:1.3rem;">
                <div class="icon-bars"><span></span><span></span><span></span><span></span></div>
                <?= APP_NAME ?>
            </div>
        </div>
        <h1>Acesso ao Monitoramento</h1>
        <p class="subtitle">Insira o código de acesso enviado pela administração</p>

        <?php if ($error): ?>
        <div class="alert-msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="token">Código de Acesso (Token)</label>
                <input type="text" id="token" name="token" class="form-control" placeholder="Cole seu token aqui..." required autofocus>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="margin-top:1rem;">Entrar no Dashboard</button>
        </form>
        
        <div style="margin-top:2rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">
            &copy; <?= date('Y') ?> <?= APP_NAME ?> - Monitoramento em Tempo Real
        </div>
    </div>
</div>
<script src="../assets/js/main.js?v=2"></script>
</body>
</html>


