<?php
/**
 * UniConnect Microservices Architecture
 * Shared PDO Database Connection Factory (Database-per-Service)
 */

class Database {
    /** @var array<string, PDO> */
    private static $connections = [];

    /**
     * Returns a singleton PDO connection for a specific service database.
     */
    public static function connect($dbName, $autoRedirectFrontend = false) {
        if (isset(self::$connections[$dbName])) {
            return self::$connections[$dbName];
        }

        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . $dbName . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            self::$connections[$dbName] = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            if ($autoRedirectFrontend && (int)$e->getCode() === 1049) {
                header("Location: " . MS_PUBLIC_BASE_URL . "/database/install.php");
                exit;
            }
            throw new RuntimeException("Database connection failed for '{$dbName}': " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Connects to MySQL server without specifying a database (used by installer/migrator).
     */
    public static function connectServer() {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
        return new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
