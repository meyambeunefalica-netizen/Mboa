<?php
/**
 * Configuration de la base de données
 * Supporte MySQL et PostgreSQL
 * Priorité aux variables d'environnement (Railway, Docker, etc.)
 */

// Type de base de données: 'mysql' ou 'postgresql'
define('DB_TYPE', getenv('DB_TYPE') ?: 'postgresql');

// Configuration MySQL
define('MYSQL_HOST', getenv('MYSQL_HOST') ?: getenv('DB_HOST') ?: 'localhost');
define('MYSQL_PORT', getenv('MYSQL_PORT') ?: getenv('DB_PORT') ?: '3306');
define('MYSQL_DATABASE', getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'lingocameroon');
define('MYSQL_USERNAME', getenv('MYSQL_USERNAME') ?: getenv('DB_USER') ?: 'root');
define('MYSQL_PASSWORD', getenv('MYSQL_PASSWORD') ?: getenv('DB_PASSWORD') ?: '');

// Configuration PostgreSQL
define('PGSQL_HOST', getenv('PGSQL_HOST') ?: getenv('DB_HOST') ?: '/var/run/postgresql');
define('PGSQL_PORT', getenv('PGSQL_PORT') ?: getenv('DB_PORT') ?: '5432');
define('PGSQL_DATABASE', getenv('PGSQL_DATABASE') ?: getenv('DB_NAME') ?: 'lingocameroon');
define('PGSQL_USERNAME', getenv('PGSQL_USERNAME') ?: getenv('DB_USER') ?: 'postgres');
define('PGSQL_PASSWORD', getenv('PGSQL_PASSWORD') ?: getenv('DB_PASSWORD') ?: '');

// Configuration de l'application
define('APP_NAME', getenv('APP_NAME') ?: 'Mboa');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8000');
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // 'development' ou 'production'

// Clé secrète pour JWT (JSON Web Tokens)
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'mboa_lingocameroon_secret_key_2024');

// Configuration des sessions
define('SESSION_LIFETIME', 86400); // 24 heures en secondes

// Limites de téléchargement
define('MAX_UPLOAD_SIZE', 5242880); // 5MB en octets

// Fuseau horaire
date_default_timezone_set('Africa/Douala');

// Configuration WebRTC
define('WEBRTC_SIGNAL_POLL_TIMEOUT', 20);
define('WEBRTC_SIGNAL_CLEANUP_AGE', 120);

/**
 * Classe de connexion à la base de données
 */
class Database {
    private static $instance = null;
    private $connection = null;

    private function __construct() {
        try {
            if (DB_TYPE === 'mysql') {
                $this->connectMySQL();
            } elseif (DB_TYPE === 'postgresql') {
                $this->connectPostgreSQL();
            } else {
                throw new Exception("Type de base de données non supporté: " . DB_TYPE);
            }
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }

    private function connectMySQL() {
        $dsn = "mysql:host=" . MYSQL_HOST . ";port=" . MYSQL_PORT . ";dbname=" . MYSQL_DATABASE . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->connection = new PDO($dsn, MYSQL_USERNAME, MYSQL_PASSWORD, $options);
    }

    private function connectPostgreSQL() {
        $dsn = "pgsql:host=" . PGSQL_HOST . ";port=" . PGSQL_PORT . ";dbname=" . PGSQL_DATABASE;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        $this->connection = new PDO($dsn, PGSQL_USERNAME, PGSQL_PASSWORD, $options);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Erreur SQL: " . $e->getMessage());
            throw $e;
        }
    }

    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function lastInsertId($sequenceName = null) {
        if (DB_TYPE === 'postgresql' && $sequenceName) {
            return $this->connection->lastInsertId($sequenceName);
        }
        return $this->connection->lastInsertId();
    }

    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    public function commit() {
        return $this->connection->commit();
    }

    public function rollBack() {
        return $this->connection->rollBack();
    }

    // Empêcher le clonage
    private function __clone() {}

    // Empêcher la désérialisation
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
