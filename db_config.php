<?php
// Database configuration for MySQL connection (Railway compatible)
// Reads database connection from Railway environment variables

/**
 * Check if PDO MySQL driver is available
 *
 * @return bool
 */
function isPDOMySQLAvailable() {
    $available = extension_loaded('pdo_mysql');
    error_log("PDO MySQL extension loaded: " . ($available ? "Yes" : "No"));
    return $available;
}

/**
 * Parse Railway MySQL connection URL
 *
 * @param string $url
 * @return array
 */
function parseRailwayDatabaseUrl($url) {
    error_log("Parsing database URL: " . $url);
    $parsed = parse_url($url);
    if (!$parsed) {
        throw new Exception('Invalid database URL');
    }
    
    $result = [
        'host' => $parsed['host'] ?? 'localhost',
        'port' => $parsed['port'] ?? '3306',
        'user' => $parsed['user'] ?? 'root',
        'pass' => $parsed['pass'] ?? '',
        'name' => substr($parsed['path'], 1) // Remove leading '/'
    ];
    
    error_log("Parsed database config: " . json_encode($result));
    return $result;
}

/**
 * Get database configuration
 *
 * @return array
 */
function getDatabaseConfig() {
    // Check if PDO MySQL driver is available
    if (!isPDOMySQLAvailable()) {
        throw new Exception('PDO MySQL driver is not available. Please enable pdo_mysql extension in your PHP configuration.');
    }
    
    // Try to get from Railway MySQL URL first
    $railwayMysqlUrl = getenv('MYSQL_URL');
    error_log("MYSQL_URL environment variable: " . ($railwayMysqlUrl ? "Set" : "Not set"));
    if ($railwayMysqlUrl) {
        try {
            return parseRailwayDatabaseUrl($railwayMysqlUrl);
        } catch (Exception $e) {
            error_log('Failed to parse MYSQL_URL: ' . $e->getMessage());
        }
    }
    
    // Fallback to DATABASE_URL (older format)
    $databaseUrl = getenv('DATABASE_URL');
    error_log("DATABASE_URL environment variable: " . ($databaseUrl ? "Set" : "Not set"));
    if ($databaseUrl) {
        try {
            return parseRailwayDatabaseUrl($databaseUrl);
        } catch (Exception $e) {
            error_log('Failed to parse DATABASE_URL: ' . $e->getMessage());
        }
    }
    
    // Fallback to individual environment variables
    $config = [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'user' => getenv('DB_USER') ?: 'DB_USER_HERE',
        'pass' => getenv('DB_PASS') ?: 'DB_PASS_HERE',
        'name' => getenv('DB_NAME') ?: 'studio_db'
    ];
    
    error_log("Fallback database config: " . json_encode($config));
    return $config;
}

/**
 * Get PDO connection (singleton)
 *
 * @return PDO
 */
function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        $config = getDatabaseConfig();
        
        $dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['name'] . ';charset=utf8mb4';
        error_log("Attempting to connect with DSN: " . $dsn);
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
        error_log("Database connection established successfully");
    }
    return $pdo;
}