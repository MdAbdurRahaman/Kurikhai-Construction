<?php
/**
 * Database Connection & Query Helper (PDO Singleton)
 * 
 * Provides prepared statement execution, transaction management,
 * and error suppression in production.
 */

require_once dirname(__DIR__) . '/config/config.php';

class DB {
    private static ?PDO $instance = null;
    private static bool $connectionAttempted = false;
    private static ?string $lastError = null;

    /**
     * Get or initialize PDO connection instance
     */
    public static function getConnection(): ?PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (self::$connectionAttempted && self::$instance === null) {
            return null;
        }

        self::$connectionAttempted = true;

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+08:00'"
        ];

        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            self::$lastError = null;
            return self::$instance;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            error_log('[Database Connection Failure] ' . $e->getMessage());
            self::$instance = null;
            return null;
        }
    }

    /**
     * Check if database connection is actively available
     */
    public static function isConnected(): bool {
        return self::getConnection() !== null;
    }

    /**
     * Get last connection error message (internal use only)
     */
    public static function getLastError(): ?string {
        return self::$lastError;
    }

    /**
     * Execute a prepared SQL statement
     */
    public static function query(string $sql, array $params = []): ?PDOStatement {
        $pdo = self::getConnection();
        if (!$pdo) {
            return null;
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('[SQL Query Error] ' . $e->getMessage() . ' | SQL: ' . $sql);
            if (APP_DEBUG) {
                throw $e;
            }
            return null;
        }
    }

    /**
     * Fetch all matching records
     */
    public static function fetchAll(string $sql, array $params = []): array {
        $stmt = self::query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Fetch a single matching record
     */
    public static function fetchOne(string $sql, array $params = []): ?array {
        $stmt = self::query($sql, $params);
        $result = $stmt ? $stmt->fetch() : false;
        return $result ?: null;
    }

    /**
     * Fetch a single scalar value
     */
    public static function fetchColumn(string $sql, array $params = [], int $col = 0) {
        $stmt = self::query($sql, $params);
        return $stmt ? $stmt->fetchColumn($col) : false;
    }

    /**
     * Execute an INSERT / UPDATE / DELETE and return affected row count
     */
    public static function execute(string $sql, array $params = []): int {
        $stmt = self::query($sql, $params);
        return $stmt ? $stmt->rowCount() : 0;
    }

    /**
     * Get the last inserted auto-increment ID
     */
    public static function lastInsertId(): string {
        $pdo = self::getConnection();
        return $pdo ? $pdo->lastInsertId() : '0';
    }

    /**
     * Execute callback within a database transaction
     */
    public static function transaction(callable $callback) {
        $pdo = self::getConnection();
        if (!$pdo) {
            throw new RuntimeException('Database unavailable for transaction.');
        }

        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $t) {
            $pdo->rollBack();
            error_log('[Transaction Rollback] ' . $t->getMessage());
            throw $t;
        }
    }
}

/**
 * Functional shortcuts for procedural code
 */
function db(): ?PDO {
    return DB::getConnection();
}

function db_query(string $sql, array $params = []): ?PDOStatement {
    return DB::query($sql, $params);
}

function db_all(string $sql, array $params = []): array {
    return DB::fetchAll($sql, $params);
}

function db_one(string $sql, array $params = []): ?array {
    return DB::fetchOne($sql, $params);
}

function db_val(string $sql, array $params = [], int $col = 0) {
    return DB::fetchColumn($sql, $params, $col);
}

function db_exec(string $sql, array $params = []): int {
    return DB::execute($sql, $params);
}
