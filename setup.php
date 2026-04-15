<?php
include "database.php";

$status = [
    'env_exists'   => $db_env_exists,
    'db_connected' => ($conn !== null),
    'table_exists' => false,
    'error'        => $db_error,
    'migrated'     => false
];

if ($conn) {
    try {
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }

        .setup-card {
            max-width: 600px;
            margin: 50px auto;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .status-icon {
            font-size: 1.4rem;
            line-height: 1;
        }

        .status-icon.text-success {
            color: #198754 !important;
        }

        .status-icon.text-danger {
            color: #dc3545 !important;
        }

        .status-icon.text-warning {
            color: #ffc107 !important;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="card setup-card">
            <div class="card-header bg-dark text-white rounded-top"
                style="border-start-start-radius: 15px; border-start-end-radius: 15px; padding: 1rem 1.5rem;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0">System Diagnostic & Migration</h4>
                        <small class="text-white-50">Supabase Postgres Connection Test</small>
                    </div>
                </div>
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
                        <strong>Migration Successful!</strong> The 'sensor_data' table has been created.
                    </div>
                <?php endif; ?>

                <ul class="list-group mb-4">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-file-earmark-text me-2 text-muted"></i> Environment File (.env)</span>
                        <?php if ($status['env_exists']): ?>
                            <i class="bi bi-check-circle-fill status-icon text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle-fill status-icon text-danger"></i>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-database me-2 text-muted"></i> Database Connection (PostgreSQL)</span>
                        <?php if ($status['db_connected']): ?>
                            <i class="bi bi-check-circle-fill status-icon text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-x-circle-fill status-icon text-danger"></i>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-table me-2 text-muted"></i> Table 'sensor_data'</span>
                        <?php if ($status['table_exists']): ?>
                            <i class="bi bi-check-circle-fill status-icon text-success"></i>
                        <?php else: ?>
                            <i class="bi bi-exclamation-circle-fill status-icon text-warning"></i>
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
                    <a href="index.php" class="btn btn-outline-primary mt-2">⬅️ Back to Dashboard</a>
                </div>

            </div>
        </div>
    </div>

</body>

</html>
