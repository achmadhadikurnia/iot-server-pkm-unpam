<?php
// Prevent direct access to this file
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    die("Direct access not permitted.");
}

// Database connection result variables
$conn = null;
$db_error = null;
$db_env_exists = false;

// Load credentials from .env file
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $db_env_exists = true;
    $env = parse_ini_file($envFile);
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? 5432;
    $db   = $env['DB_NAME'] ?? 'postgres';
    $user = $env['DB_USER'] ?? 'postgres';
    $pass = $env['DB_PASS'] ?? '';

    // Connect to Online Database (Supabase Postgres) using PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";

    try {
        $conn = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
    }
} else {
    $db_error = ".env file not found. Please create it.";
}
?>