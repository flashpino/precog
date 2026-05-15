<?php
/**
 * Conexão MySQL via PDO (Singleton)
 */

require_once __DIR__ . '/../config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    /**
     * Verifica se a conexão ainda está ativa, se não, reconecta.
     * Essencial para scripts longos com sleep().
     */
    public static function reconnectIfGoneAway() {
        $instance = self::getInstance();
        try {
            // Tenta um comando simples para ver se a conexão está viva
            $instance->pdo->query("SELECT 1");
        } catch (Exception $e) {
            // Se falhar, reseta a instância para forçar nova conexão no próximo getInstance()
            self::$instance = null;
            self::getInstance();
        }
    }

    /**
     * Executa uma query preparada e retorna todos os resultados
     */
    public static function query($sql, $params = []) {
        self::reconnectIfGoneAway();
        $pdo = self::getInstance()->getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Executa uma query preparada e retorna uma única linha
     */
    public static function queryOne($sql, $params = []) {
        self::reconnectIfGoneAway();
        $pdo = self::getInstance()->getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Executa INSERT/UPDATE/DELETE e retorna o número de linhas afetadas
     */
    public static function execute($sql, $params = []) {
        self::reconnectIfGoneAway();
        $pdo = self::getInstance()->getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Retorna o último ID inserido
     */
    public static function lastInsertId() {
        return self::getInstance()->getConnection()->lastInsertId();
    }
}
