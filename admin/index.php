<?php
/**
 * Admin - Login
 */
require_once __DIR__ . '/../includes/auth.php';

$error = '';

// Se já está logado, redireciona
if (Auth::isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $error = 'Preencha todos os campos.';
    } elseif (Auth::loginAdmin($username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Usuário ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Admin Login</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=2">
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <div style="text-align:center;margin-bottom:1.5rem;">
            <div class="navbar-brand" style="justify-content:center;font-size:1.3rem;">
                <div class="icon-bars"><span></span><span></span><span></span><span></span></div>
                <?= APP_NAME ?>
            </div>
        </div>
        <h1>Painel Administrativo</h1>
        <p class="subtitle">Faça login para acessar o sistema</p>

        <?php if ($error): ?>
        <div class="alert-msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="admin" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="margin-top:0.5rem;">Entrar</button>
        </form>
    </div>
</div>
<script src="../assets/js/main.js?v=2"></script>
</body>
</html>


