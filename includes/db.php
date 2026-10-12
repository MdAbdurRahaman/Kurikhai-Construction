<?php
/**
 * Database Connection & Query Helper (PDO Singleton) - Tabeeb Contractor
 * 
 * Provides strict write contracts, transactional execution,
 * prepared statements, and secure error propagation.
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

        if (APP_ENV === 'testing' && getenv('TEST_SQLITE_PATH')) {
            $path = (string)getenv('TEST_SQLITE_PATH');
            self::$instance = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
            self::$instance->exec('PRAGMA busy_timeout=5000');
            self::$instance->sqliteCreateFunction('NOW', fn()=>date('Y-m-d H:i:s'));
            self::$instance->sqliteCreateFunction('GET_LOCK', function($name,$timeout)use($path){
                $file=fopen($path.'.worker-lock','c');if(!flock($file,LOCK_EX|LOCK_NB)){fclose($file);return 0;}$GLOBALS['test_db_lock']=$file;return 1;
            });
            self::$instance->sqliteCreateFunction('RELEASE_LOCK',function($name){if(isset($GLOBALS['test_db_lock'])){flock($GLOBALS['test_db_lock'],LOCK_UN);fclose($GLOBALS['test_db_lock']);unset($GLOBALS['test_db_lock']);}return 1;});
            return self::$instance;
        }

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
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            // Execute timezone separately to guarantee standard MySQL/MariaDB grammar compliance
            if (self::$instance->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                self::$instance->exec("SET time_zone = '+08:00'");
            }
            self::$lastError = null;
            return self::$instance;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            error_log('[Database Connection Failure] Host: ' . DB_HOST . ' | DB: ' . DB_NAME . ' | ' . $e->getMessage());
            self::$instance = null;
            return null;
        }
    }

    /**
     * Reset connection instance (useful for tests or reconnection)
     */
    public static function resetConnection(): void {
        self::$instance = null;
        self::$connectionAttempted = false;
        self::$lastError = null;
    }

    /**
     * Inject custom connection instance (for testing/mocking)
     */
    public static function setConnection(?PDO $pdo): void {
        self::$instance = $pdo;
        self::$connectionAttempted = ($pdo !== null);
        self::$lastError = null;
    }

    /**
     * Check if database connection is actively available
     */
    public static function isSqlite(): bool {
        return self::getConnection()?->getAttribute(PDO::ATTR_DRIVER_NAME) === "sqlite";
    }

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
     * Strictly propagates PDOException on failure so callers/transactions cannot mistake failure for success.
     * 
     * @throws PDOException
     * @throws RuntimeException if DB connection is unavailable
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        $pdo = self::getConnection();
        if (!$pdo) {
            throw new RuntimeException('Database connection unavailable for query.');
        }

        try {
            if (self::isSqlite()) { $sql = preg_replace('/\s+FOR UPDATE\b/i', '', $sql); $sql=str_replace('INSERT IGNORE','INSERT OR IGNORE',$sql); }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('[SQL Query Error] ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw $e;
        }
    }

    /**
     * Fetch all matching records
     */
    public static function fetchAll(string $sql, array $params = []): array {
        if (!self::isConnected()) {
            return [];
        }
        try {
            $stmt = self::query($sql, $params);
            return $stmt->fetchAll();
        } catch (Throwable $t) {
            return [];
        }
    }

    /**
     * Fetch a single matching record
     */
    public static function fetchOne(string $sql, array $params = []): ?array {
        if (!self::isConnected()) {
            return null;
        }
        try {
            $stmt = self::query($sql, $params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (Throwable $t) {
            return null;
        }
    }

    /**
     * Fetch a single scalar value
     */
    public static function fetchColumn(string $sql, array $params = [], int $col = 0) {
        if (!self::isConnected()) {
            return false;
        }
        try {
            $stmt = self::query($sql, $params);
            return $stmt->fetchColumn($col);
        } catch (Throwable $t) {
            return false;
        }
    }

    /**
     * Execute an INSERT / UPDATE / DELETE and return affected row count
     * Throws on failure.
     */
    public static function execute(string $sql, array $params = []): int {
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Execute an INSERT statement, verify insertion, and return the auto-increment ID
     * Throws RuntimeException if no rows were affected.
     */
    public static function insert(string $sql, array $params = []): string {
        $pdo = self::getConnection();
        if (!$pdo) {
            throw new RuntimeException('Database unavailable for insert.');
        }
        $stmt = self::query($sql, $params);
        if ($stmt->rowCount() <= 0) {
            throw new RuntimeException('Insert failed: no rows were inserted.');
        }
        return $pdo->lastInsertId();
    }

    /**
     * Get the last inserted auto-increment ID
     */
    public static function lastInsertId(): string {
        $pdo = self::getConnection();
        return $pdo ? $pdo->lastInsertId() : '0';
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool {
        $pdo = self::getConnection();
        if (!$pdo) {
            throw new RuntimeException('Database unavailable for transaction.');
        }
        return $pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit(): bool {
        $pdo = self::getConnection();
        if (!$pdo) {
            throw new RuntimeException('Database unavailable for commit.');
        }
        return $pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollBack(): bool {
        $pdo = self::getConnection();
        if (!$pdo) {
            return false;
        }
        if ($pdo->inTransaction()) {
            return $pdo->rollBack();
        }
        return false;
    }

    /**
     * Check if currently in transaction
     */
    public static function inTransaction(): bool {
        $pdo = self::getConnection();
        return $pdo ? $pdo->inTransaction() : false;
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
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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

function db_query(string $sql, array $params = []): PDOStatement {
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

function db_insert(string $sql, array $params = []): string {
    return DB::insert($sql, $params);
}
