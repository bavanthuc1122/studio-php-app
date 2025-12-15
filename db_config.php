<?php
// Database configuration for MySQL connection (placeholders)
// Update these values on your hosting environment

define('DB_HOST', 'localhost');
define('DB_NAME', 'studio_db');
define('DB_USER', 'DB_USER_HERE');
define('DB_PASS', 'DB_PASS_HERE');

/**
 * Get PDO connection (singleton)
 *
 * @return PDO
 */
function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}