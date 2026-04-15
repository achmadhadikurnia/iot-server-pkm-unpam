<?php
session_start();

// ── Auth credentials: from .env or defaults ───────────────────────
$envFile = __DIR__ . '/.env';
$setupUser = 'admin';
$setupPass = 'password';
if (file_exists($envFile)) {
    $envVars = parse_ini_file($envFile);
    if (!empty($envVars['SETUP_USER'])) $setupUser = $envVars['SETUP_USER'];
    if (!empty($envVars['SETUP_PASS'])) $setupPass = $envVars['SETUP_PASS'];
}

// ── Handle logout ─────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    unset($_SESSION['setup_authenticated']);
    header('Location: setup.php');
    exit;
}

// ── Handle login POST ─────────────────────────────────────────────
$login_error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $inputUser = $_POST['username'] ?? '';
    $inputPass = $_POST['password'] ?? '';
    if ($inputUser === $setupUser && $inputPass === $setupPass) {
        $_SESSION['setup_authenticated'] = true;
        header('Location: setup.php');
        exit;
    } else {
        $login_error = 'Invalid username or password.';
    }
}

// ── Check authentication ──────────────────────────────────────────
$is_authenticated = !empty($_SESSION['setup_authenticated']);

// ── Only run diagnostic if authenticated ──────────────────────────
$status = null;
if ($is_authenticated) {
    $env_created = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_env'])) {
        $envExample = __DIR__ . '/.env.example';
        if (file_exists($envExample) && !file_exists($envFile)) {
            copy($envExample, $envFile);
            $env_created = true;
        }
    }

    $env_saved = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_env'])) {
        file_put_contents($envFile, $_POST['env_content']);
        $env_saved = true;
    }

    include "database.php";

    $status = [
        'env_exists'   => $db_env_exists,
        'db_connected' => ($conn !== null),
        'table_exists' => false,
        'error'        => $db_error,
        'migrated'     => false,
        'reset'        => false
    ];

    if ($conn) {
        try {
            // Handle reset request
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
                $conn->exec("TRUNCATE TABLE sensor_data RESTART IDENTITY;");
                $status['reset'] = true;
            }

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
                        <h4 class="mb-0"><?= $is_authenticated ? 'System Diagnostic & Migration' : 'Setup Login' ?></h4>
                        <small class="text-white-50"><?= $is_authenticated ? 'Supabase Postgres Connection Test' : 'Authentication Required' ?></small>
                    </div>
                    <?php if ($is_authenticated): ?>
                        <a href="?logout" class="btn btn-outline-danger btn-sm fw-bold">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-4">

                <?php if (!$is_authenticated): ?>
                    <!-- ── Login Form ──────────────────────────────── -->
                    <?php if ($login_error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <?= htmlspecialchars($login_error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label fw-bold">
                                <i class="bi bi-person me-1"></i> Username
                            </label>
                            <input type="text" class="form-control" id="username" name="username"
                                placeholder="Enter username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label fw-bold">
                                <i class="bi bi-lock me-1"></i> Password
                            </label>
                            <input type="password" class="form-control" id="password" name="password"
                                placeholder="Enter password" required>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" name="login" value="1" class="btn btn-primary fw-bold">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Login
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">⬅️ Back to Dashboard</a>
                        </div>
                    </form>

                <?php else: ?>
                    <!-- ── Diagnostic Content ──────────────────────── -->
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

                    <?php if ($status['reset']): ?>
                        <div class="alert alert-success">
                            <strong>Data Reset Successful!</strong> All sensor data has been deleted.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($env_created) && $env_created): ?>
                        <div class="alert alert-success mt-3">
                            <strong>Success!</strong> Created .env file from .env.example. Please edit it with your database credentials.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($env_saved) && $env_saved): ?>
                        <div class="alert alert-success mt-3">
                            <strong>Success!</strong> Configuration saved to .env file.
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['edit_env']) && $status['env_exists']): ?>
                        <div class="mb-4 mt-3">
                            <h5 class="fw-bold"><i class="bi bi-pencil-square me-1"></i> Edit .env Configuration</h5>
                            <form method="POST" action="setup.php">
                                <textarea name="env_content" class="form-control mb-3" rows="6" style="font-family: monospace;" required><?= htmlspecialchars(file_get_contents($envFile)) ?></textarea>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="save_env" value="1" class="btn btn-primary fw-bold"><i class="bi bi-save"></i> Save Changes</button>
                                    <a href="setup.php" class="btn btn-outline-secondary fw-bold">Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <ul class="list-group mb-4">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-file-earmark-text me-2 text-muted"></i> Environment File (.env)
                                    <?php if (!$status['env_exists']): ?>
                                        <form method="POST" class="d-inline ms-2">
                                            <button type="submit" name="create_env" value="1" class="btn btn-sm btn-outline-primary py-0" style="font-size: 0.75rem;">Create from .env.example</button>
                                        </form>
                                    <?php else: ?>
                                        <a href="?edit_env=1" class="btn btn-sm btn-outline-secondary py-0 ms-2" style="font-size: 0.75rem;"><i class="bi bi-pencil"></i> Edit</a>
                                    <?php endif; ?>
                                </span>
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
                                <form method="POST" class="d-grid mt-2" onsubmit="return confirm('Are you sure you want to delete all sensor data? This action cannot be undone.');">
                                    <button type="submit" name="reset" value="1" class="btn btn-danger fw-bold"><i class="bi bi-trash3 me-1"></i> Reset / Delete All Data</button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-secondary fw-bold disabled">Please Fix Errors Above First</button>
                            <?php endif; ?>
                            <a href="index.php" class="btn btn-outline-primary mt-2">⬅️ Back to Dashboard</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
        </div>
    </div>

</body>

</html>
