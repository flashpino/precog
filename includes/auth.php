<?php
/**
 * Autenticação - Admin (sessão) e Cliente (token)
 */

require_once __DIR__ . '/db.php';

class Auth {

    /**
     * Inicia sessão com configurações seguras
     */
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('PRECOGNOVO_SESSID');
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_strict_mode', 1);
            session_set_cookie_params(SESSION_LIFETIME);
            session_start();
        }
    }

    /**
     * Tenta logar o admin com username/senha
     */
    public static function loginAdmin($username, $password) {
        $admin = Database::queryOne(
            "SELECT id, username, password_hash FROM admins WHERE username = ?",
            [$username]
        );

        if ($admin && password_verify($password, $admin['password_hash'])) {
            self::startSession();
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_user'] = $admin['username'];
            $_SESSION['admin_login_time'] = time();
            return true;
        }

        return false;
    }

    /**
     * Verifica se o admin está logado
     */
    public static function isAdminLoggedIn() {
        self::startSession();

        if (!isset($_SESSION['admin_id'])) return false;

        // Verificar expiração da sessão
        if (isset($_SESSION['admin_login_time']) && 
            (time() - $_SESSION['admin_login_time']) > SESSION_LIFETIME) {
            self::logoutAdmin();
            return false;
        }

        return true;
    }

    /**
     * Exige login admin ou redireciona
     */
    public static function requireAdmin() {
        if (!self::isAdminLoggedIn()) {
            header('Location: ' . APP_URL . '/admin/index.php');
            exit;
        }
    }

    /**
     * Faz logout do admin
     */
    public static function logoutAdmin() {
        self::startSession();
        session_unset();
        session_destroy();
    }

    /**
     * Valida token do cliente e retorna os dados
     */
    public static function validateClientToken($token) {
        if (empty($token) || strlen($token) < 32) return false;

        $client = Database::queryOne(
            "SELECT id, name, company, token, is_active, influx_org, influx_bucket, influx_token FROM clients WHERE token = ? AND is_active = 1",
            [$token]
        );

        return $client ?: false;
    }

    /**
     * Gera um token único para o cliente
     */
    public static function generateToken() {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(32));
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes(32));
        }
        return bin2hex(uniqid('', true));
    }
}
