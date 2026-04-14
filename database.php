<?php
// Load credentials from .env file
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? 5432;
    $db   = $env['DB_NAME'] ?? 'postgres';
    $user = $env['DB_USER'] ?? 'postgres';
    $pass = $env['DB_PASS'] ?? '';
} else {
    die(".env file not found. Please create it.");
}

// Connect to Online Database (Supabase Postgres) using PDO
$dsn = "pgsql:host=$host;port=$port;dbname=$db";

try {
    $conn = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // echo "Connection Successful!";
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>