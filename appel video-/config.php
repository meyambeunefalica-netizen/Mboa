<?php
/**
 * Configuration LingoCameroon
 * WebRTC natif — plus besoin de LiveKit
 */

// ── Base de données ───────────────────────────────────────────────────────────
define('DB_TYPE', 'postgresql');

define('MYSQL_HOST',     'localhost');
define('MYSQL_PORT',     '3306');
define('MYSQL_DATABASE', 'lingocameroon');
define('MYSQL_USERNAME', 'root');
define('MYSQL_PASSWORD', '');

define('PGSQL_HOST',     '/var/run/postgresql');
define('PGSQL_PORT',     '5432');
define('PGSQL_DATABASE', 'lingocameroon');
define('PGSQL_USERNAME', 'postgres');
define('PGSQL_PASSWORD', '');

// ── Application ───────────────────────────────────────────────────────────────
define('APP_NAME', 'LingoCameroon');
define('APP_URL',  'http://localhost:8000');
define('APP_ENV',  'development'); // 'development' ou 'production'

// ── Sécurité ──────────────────────────────────────────────────────────────────
define('JWT_SECRET',       'mboa_lingocameroon_secret_key_2024');
define('SESSION_LIFETIME', 86400); // 24h en secondes

// ── Uploads ───────────────────────────────────────────────────────────────────
define('MAX_UPLOAD_SIZE', 5242880); // 5 Mo

// ── Fuseau horaire ────────────────────────────────────────────────────────────
date_default_timezone_set('Africa/Douala');

// ── WebRTC — Configuration signalisation ─────────────────────────────────────
// Pas de serveur externe requis.
// Le signal passe par backend/api/webrtc-signal.php (long-polling PHP + PostgreSQL).
// Pour production, envisager un TURN server si NAT symétrique pose problème:
//   ex. Coturn: https://github.com/coturn/coturn
define('WEBRTC_SIGNAL_POLL_TIMEOUT', 20); // secondes max par requête long-poll
define('WEBRTC_SIGNAL_CLEANUP_AGE',  120); // secondes avant purge des vieux signaux

/**
 * Classe de connexion à la base de données (Singleton PDO)
 */
class Database {
    private static ?self $instance = null;
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
            die(json_encode(['success' => false, 'message' => 'DB connection error: ' . $e->getMessage()]));
        }
    }

    private function connectMySQL(): void {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
            MYSQL_HOST, MYSQL_PORT, MYSQL_DATABASE
        );
        $this->connection = new PDO($dsn, MYSQL_USERNAME, MYSQL_PASSWORD, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    private function connectPostgreSQL(): void {
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s",
            PGSQL_HOST, PGSQL_PORT, PGSQL_DATABASE
        );
        $this->connection = new PDO($dsn, PGSQL_USERNAME, PGSQL_PASSWORD, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): \PDO {
        return $this->connection;
    }

    public function query(string $sql, array $params = []): \PDOStatement {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("[DB] " . $e->getMessage() . " — SQL: $sql");
            throw $e;
        }
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []) {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    public function execute(string $sql, array $params = []): int {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastInsertId(?string $sequenceName = null): string {
        if (DB_TYPE === 'postgresql' && $sequenceName) {
            return $this->connection->lastInsertId($sequenceName);
        }
        return $this->connection->lastInsertId();
    }

    public function beginTransaction(): bool { return $this->connection->beginTransaction(); }
    public function commit(): bool           { return $this->connection->commit(); }
    public function rollBack(): bool         { return $this->connection->rollBack(); }

    private function __clone() {}
    public function __wakeup() { throw new Exception("Cannot unserialize singleton"); }
}
?>
