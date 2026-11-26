<?php
/**
 * UniConnect - Monolithic University Management System
 * Database Configuration and Connection Handler
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'uniconnect_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns the singleton PDO database connection.
 * If the database doesn't exist yet, it safely returns null or throws.
 *
 * @return PDO
 */
function get_db_connection() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Check if error is because uniconnect_db does not exist yet
            if ($e->getCode() == 1049) {
                // Database does not exist yet; provide seamless installer redirect
                header("Location: /UniConnect/install.php");
                exit;
            }
            // Display polished error message for XAMPP users
            die("
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 60px auto; padding: 25px; border-radius: 8px; border: 1px solid #f5c6cb; background-color: #f8d7da; color: #721c24;'>
                    <h2 style='margin-top:0;'>UniConnect Database Connection Failed</h2>
                    <p>Could not connect to MySQL server at <strong>" . DB_HOST . "</strong>.</p>
                    <p><strong>Error details:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
                    <hr style='border:0; border-top:1px solid #f5c6cb; margin:15px 0;'>
                    <p><strong>Quick Troubleshooting Steps for XAMPP:</strong></p>
                    <ol style='line-height: 1.6;'>
                        <li>Open the <strong>XAMPP Control Panel</strong>.</li>
                        <li>Ensure <strong>Apache</strong> and <strong>MySQL</strong> services are both running (green).</li>
                        <li>Import <code>uniconnect_db.sql</code> into <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a> or visit <a href='/UniConnect/install.php'>install.php</a>.</li>
                    </ol>
                </div>
            ");
        }
    }

    return $pdo;
}

/**
 * Helper to check if tables are initialized.
 *
 * @param PDO $pdo
 * @return bool
 */
function is_database_initialized($pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}
