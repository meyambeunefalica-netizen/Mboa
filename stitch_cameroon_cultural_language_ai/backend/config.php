<?php
/**
 * Database Configuration for Mboa
 * Uses environment variables from Railway
 */

// Get database credentials from environment variables
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_port = getenv('DB_PORT') ?: '5432';
$db_name = getenv('DB_NAME') ?: 'railway';
$db_user = getenv('DB_USER') ?: 'postgres';
$db_password = getenv('DB_PASSWORD') ?: '';

// Create PDO connection to PostgreSQL
try {
    $pdo = new PDO(
        "pgsql:host=$db_host;port=$db_port;dbname=$db_name",
        $db_user,
        $db_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false
        ]
    );
    
    // Log successful connection
    error_log("✅ Database connected successfully to $db_host:$db_port/$db_name");
    
} catch (PDOException $e) {
    error_log("❌ Database connection failed: " . $e->getMessage());
    die("Database connection error. Please check your configuration.");
}

// Return the PDO instance for use in other files
return $pdo;
