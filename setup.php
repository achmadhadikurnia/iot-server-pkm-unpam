<?php
$envFile = __DIR__ . '/.env';
$status = [
    'env_exists' => false,
    'db_connected' => false,
    'table_exists' => false,
    'error' => null,
    'migrated' => false
];

if (file_exists($envFile)) {
    $status['env_exists'] = true;
    $env = parse_ini_file($envFile);
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? 5432;
    $db   = $env['DB_NAME'] ?? 'postgres';
    $user = $env['DB_USER'] ?? 'postgres';
    $pass = $env['DB_PASS'] ?? '';
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$db";
    
    try {
        $conn = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $status['db_connected'] = true;
        
        // Handle migration request
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate'])) {
            $sql = "CREATE TABLE IF NOT EXISTS sensor_data (
                id SERIAL PRIMARY KEY,
                temperature NUMERIC(5,2) NOT NULL,
                humidity NUMERIC(5,2) NOT NULL,
                device_name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $conn->exec($sql);
            $status['migrated'] = true;
        }
        
        // Check if table exists
        $stmt = $conn->query("SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_schema = 'public' 
            AND table_name = 'sensor_data'
        );");
        
        if ($stmt->fetchColumn()) {
            $status['table_exists'] = true;
        }

    } catch (PDOException $e) {
        $status['error'] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup & Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .setup-card { max-width: 600px; margin: 50px auto; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .status-icon { width: 30px; text-align: center; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <div class="card setup-card">
        <div class="card-header bg-dark text-white text-center rounded-top" style="border-start-start-radius: 15px; border-start-end-radius: 15px;">
            <h4 class="mb-0">System Diagnostic & Migration</h4>
            <p class="mb-0"><small>Supabase Postgres Connection Test</small></p>
        </div>
        <div class="card-body p-4">
            
            <?php if ($status['error']): ?>
                <div class="alert alert-danger">
                    <strong>Connection Error:</strong> <br>
                    <?= htmlspecialchars($status['error']) ?>
                </div>
            <?php endif; ?>

            <?php if ($status['migrated']): ?>
                <div class="alert alert-success">
                    <strong>Migration Successful!</strong> The 'tbl_sensor_data' table has been created.
                </div>
            <?php endif; ?>

            <ul class="list-group mb-4">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Environment File (.env)
                    <?php if ($status['env_exists']): ?>
                        <span class="badge bg-success rounded-pill status-icon">✔</span>
                    <?php else: ?>
                        <span class="badge bg-danger rounded-pill status-icon">✖</span>
                    <?php endif; ?>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Database Connection (Supabase)
                    <?php if ($status['db_connected']): ?>
                        <span class="badge bg-success rounded-pill status-icon">✔</span>
                    <?php else: ?>
                        <span class="badge bg-danger rounded-pill status-icon">✖</span>
                    <?php endif; ?>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Table 'sensor_data' 
                    <?php if ($status['table_exists']): ?>
                        <span class="badge bg-success rounded-pill status-icon">✔</span>
                    <?php else: ?>
                        <span class="badge bg-warning rounded-pill status-icon">✖</span>
                    <?php endif; ?>
                </li>
            </ul>

            <div class="d-grid gap-2">
                <?php if ($status['db_connected'] && !$status['table_exists']): ?>
                    <form method="POST" class="d-grid">
                        <button type="submit" name="migrate" value="1" class="btn btn-warning fw-bold">Run Database Migration Now</button>
                    </form>
                <?php elseif ($status['table_exists']): ?>
                    <button class="btn btn-success fw-bold disabled">System is Ready to Use</button>
                <?php else: ?>
                    <button class="btn btn-secondary fw-bold disabled">Please Fix Errors Above First</button>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-primary mt-2">Back to Simulator</a>
            </div>

        </div>
    </div>
</div>

</body>
</html>
