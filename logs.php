<?php
include "database.php";

// ── Filter parameters ──────────────────────────────────────────────
$filter_device      = isset($_GET['device']) ? trim($_GET['device']) : '';
$filter_temp_min    = isset($_GET['temp_min']) && $_GET['temp_min'] !== '' ? (float)$_GET['temp_min'] : null;
$filter_temp_max    = isset($_GET['temp_max']) && $_GET['temp_max'] !== '' ? (float)$_GET['temp_max'] : null;
$filter_hum_min     = isset($_GET['hum_min']) && $_GET['hum_min'] !== '' ? (float)$_GET['hum_min'] : null;
$filter_hum_max     = isset($_GET['hum_max']) && $_GET['hum_max'] !== '' ? (float)$_GET['hum_max'] : null;
$filter_date_from   = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
$filter_date_to     = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;

// ── Sorting parameters ────────────────────────────────────────────
$allowed_sort = ['id', 'device_name', 'temperature', 'humidity', 'created_at'];
$sort_col = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : 'id';
$sort_dir = isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc' ? 'ASC' : 'DESC';

// ── Pagination settings ───────────────────────────────────────────
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$data = [];
$total_pages = 1;
$total_records = 0;
$devices = [];

try {
    // Fetch distinct device names for the filter dropdown
    $stmtDevices = $conn->query("SELECT DISTINCT device_name FROM sensor_data ORDER BY device_name");
    $devices = $stmtDevices->fetchAll(PDO::FETCH_COLUMN);

    // ── Build WHERE clause ─────────────────────────────────────────
    $where = [];
    $params = [];

    if ($filter_device !== '') {
        $where[] = "device_name = :device";
        $params[':device'] = $filter_device;
    }
    if ($filter_temp_min !== null) {
        $where[] = "temperature >= :temp_min";
        $params[':temp_min'] = $filter_temp_min;
    }
    if ($filter_temp_max !== null) {
        $where[] = "temperature <= :temp_max";
        $params[':temp_max'] = $filter_temp_max;
    }
    if ($filter_hum_min !== null) {
        $where[] = "humidity >= :hum_min";
        $params[':hum_min'] = $filter_hum_min;
    }
    if ($filter_hum_max !== null) {
        $where[] = "humidity <= :hum_max";
        $params[':hum_max'] = $filter_hum_max;
    }
    if ($filter_date_from !== null) {
        $where[] = "created_at >= :date_from";
        $params[':date_from'] = $filter_date_from;
    }
    if ($filter_date_to !== null) {
        $where[] = "created_at <= :date_to";
        $params[':date_to'] = $filter_date_to . ' 23:59:59';
    }

    $whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // ── Total records (filtered) ───────────────────────────────────
    $stmtTotal = $conn->prepare("SELECT COUNT(*) FROM sensor_data $whereSQL");
    foreach ($params as $k => $v) {
        $stmtTotal->bindValue($k, $v);
    }
    $stmtTotal->execute();
    $total_records = $stmtTotal->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    if ($total_pages == 0) $total_pages = 1;

    // ── Fetch data ─────────────────────────────────────────────────
    $stmt = $conn->prepare("SELECT * FROM sensor_data $whereSQL ORDER BY $sort_col $sort_dir LIMIT :limit OFFSET :offset");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'relation "sensor_data" does not exist') !== false) {
        $error = "Table 'sensor_data' does not exist yet. Please run migration in Setup.";
    } else {
        $error = "Failed to fetch data: " . $e->getMessage();
    }
}

// ── Helper: build query string preserving current params ────────────
function buildQS(array $overrides = []): string {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return http_build_query($params);
}

// Check if any filter is active
$has_filters = $filter_device !== '' || $filter_temp_min !== null || $filter_temp_max !== null
    || $filter_hum_min !== null || $filter_hum_max !== null
    || $filter_date_from !== null || $filter_date_to !== null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Sensor Data Logs</title>
    <!-- Premium Design Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }

        .dashboard-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
            margin-bottom: 50px;
        }

        .table-hover tbody tr:hover {
            background-color: #f1f5f9;
            transition: 0.3s;
        }

        /* ── Sortable column headers ─────────────────────────── */
        .sortable {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
            transition: color 0.2s;
        }

        .sortable:hover {
            color: #0d6efd;
        }

        .sort-icon {
            display: inline-block;
            width: 16px;
            margin-left: 4px;
            opacity: 0.35;
            font-size: 0.75rem;
        }

        .sort-icon.active {
            opacity: 1;
            color: #0d6efd;
        }

        /* ── Filter panel ────────────────────────────────────── */
        .filter-panel {
            background: linear-gradient(135deg, #f8f9fc 0%, #eef1f6 100%);
            border-bottom: 1px solid #dee2e6;
            padding: 1.25rem 1.5rem;
        }

        .filter-panel label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.2rem;
        }

        .filter-panel .form-control,
        .filter-panel .form-select {
            font-size: 0.85rem;
            border-radius: 8px;
        }

        .filter-badge {
            font-size: 0.7rem;
            vertical-align: middle;
        }

        .btn-filter-toggle {
            font-size: 0.85rem;
        }

        /* ── Range group styling ───────────────────────────── */
        .filter-group {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 0.75rem;
            height: 100%;
        }

        .filter-group-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-group .range-separator,
        .range-separator {
            font-size: 0.8rem;
            color: #adb5bd;
            font-weight: 600;
            flex-shrink: 0;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="card dashboard-card">
            <!-- ── Header ───────────────────────────────────────── -->
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center rounded-top"
                style="border-start-start-radius: 15px; border-start-end-radius: 15px; padding: 1rem 1.5rem;">
                <div>
                    <h4 class="mb-0">
                        Sensor Data Logs
                        <?php if ($has_filters): ?>
                            <span class="badge bg-info filter-badge ms-2">Filtered</span>
                        <?php endif; ?>
                    </h4>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <?php if (!isset($error)): ?>
                        <button class="btn btn-outline-warning btn-sm fw-bold btn-filter-toggle" type="button"
                            data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="<?= $has_filters ? 'true' : 'false' ?>">
                            <i class="bi bi-funnel"></i> Filter &amp; Sort
                        </button>
                    <?php endif; ?>
                    <a href="simulator.php" class="btn btn-outline-info btn-sm fw-bold">
                        🧪 Simulator
                    </a>
                    <a href="index.php" class="btn btn-outline-light btn-sm fw-bold">
                        ⬅️ Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- ── Filter Panel (collapsible) ──────────────────── -->
            <?php if (!isset($error)): ?>
                <div class="collapse <?= $has_filters ? 'show' : '' ?>" id="filterPanel">
                    <form method="GET" action="logs.php" class="filter-panel">
                        <!-- Preserve current sort -->
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_col) ?>">
                        <input type="hidden" name="dir" value="<?= htmlspecialchars(strtolower($sort_dir)) ?>">

                        <!-- Row 1: Device | Temperature Range | Humidity Range -->
                        <div class="row g-3 mb-3">
                            <!-- Device Name -->
                            <div class="col-12 col-md-4 col-lg-3">
                                <div class="filter-group">
                                    <div class="filter-group-label">
                                        <i class="bi bi-cpu"></i> Device
                                    </div>
                                    <select class="form-select" id="filterDevice" name="device">
                                        <option value="">All Devices</option>
                                        <?php foreach ($devices as $d): ?>
                                            <option value="<?= htmlspecialchars($d) ?>" <?= $filter_device === $d ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Temperature Range -->
                            <div class="col-6 col-md-4 col-lg">
                                <div class="filter-group">
                                    <div class="filter-group-label">
                                        <i class="bi bi-thermometer-half"></i> Temperature (°C)
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="number" step="0.01" class="form-control" id="filterTempMin" name="temp_min"
                                            placeholder="Min"
                                            value="<?= $filter_temp_min !== null ? htmlspecialchars($filter_temp_min) : '' ?>">
                                        <span class="range-separator">—</span>
                                        <input type="number" step="0.01" class="form-control" id="filterTempMax" name="temp_max"
                                            placeholder="Max"
                                            value="<?= $filter_temp_max !== null ? htmlspecialchars($filter_temp_max) : '' ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- Humidity Range -->
                            <div class="col-6 col-md-4 col-lg">
                                <div class="filter-group">
                                    <div class="filter-group-label">
                                        <i class="bi bi-droplet-half"></i> Humidity (%)
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="number" step="0.01" class="form-control" id="filterHumMin" name="hum_min"
                                            placeholder="Min"
                                            value="<?= $filter_hum_min !== null ? htmlspecialchars($filter_hum_min) : '' ?>">
                                        <span class="range-separator">—</span>
                                        <input type="number" step="0.01" class="form-control" id="filterHumMax" name="hum_max"
                                            placeholder="Max"
                                            value="<?= $filter_hum_max !== null ? htmlspecialchars($filter_hum_max) : '' ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 2: Date Range + Buttons -->
                        <div class="d-flex flex-wrap gap-3 align-items-end">
                            <!-- Date Range -->
                            <div class="flex-grow-1" style="min-width: 280px;">
                                <div class="filter-group">
                                    <div class="filter-group-label">
                                        <i class="bi bi-calendar-range"></i> Date Range
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="flex-grow-1">
                                            <label for="filterDateFrom" class="mb-1" style="text-transform: none; font-size: 0.72rem; color: #868e96;">From</label>
                                            <input type="date" class="form-control" id="filterDateFrom" name="date_from"
                                                value="<?= $filter_date_from !== null ? htmlspecialchars($filter_date_from) : '' ?>">
                                        </div>
                                        <span class="range-separator" style="padding-top: 1.2rem;">—</span>
                                        <div class="flex-grow-1">
                                            <label for="filterDateTo" class="mb-1" style="text-transform: none; font-size: 0.72rem; color: #868e96;">To</label>
                                            <input type="date" class="form-control" id="filterDateTo" name="date_to"
                                                value="<?= $filter_date_to !== null ? htmlspecialchars($filter_date_to) : '' ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Buttons -->
                            <div class="d-flex gap-2 align-items-end pb-1">
                                <button type="submit" class="btn btn-primary fw-bold px-4">
                                    <i class="bi bi-search"></i> Apply
                                </button>
                                <a href="logs.php" class="btn btn-outline-secondary fw-bold px-4">
                                    <i class="bi bi-x-circle"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ── Table Body ───────────────────────────────────── -->
            <div class="card-body p-0">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger m-4">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0 text-center align-middle">
                            <thead class="table-light">
                                <tr>
                                    <?php
                                    // Column definitions for sortable headers
                                    $columns = [
                                        'id'          => 'ID',
                                        'device_name' => 'Device Name',
                                        'temperature' => 'Temperature (°C)',
                                        'humidity'    => 'Humidity (%)',
                                        'created_at'  => 'Timestamp',
                                    ];
                                    foreach ($columns as $col => $label):
                                        $is_active = ($sort_col === $col);
                                        $next_dir = ($is_active && $sort_dir === 'ASC') ? 'desc' : 'asc';
                                        $qs = buildQS(['sort' => $col, 'dir' => $next_dir, 'page' => 1]);
                                    ?>
                                        <th class="py-3 sortable" onclick="window.location='?<?= htmlspecialchars($qs) ?>'">
                                            <?= $label ?>
                                            <?php if ($is_active): ?>
                                                <span class="sort-icon active">
                                                    <i class="bi bi-caret-<?= $sort_dir === 'ASC' ? 'up' : 'down' ?>-fill"></i>
                                                </span>
                                            <?php else: ?>
                                                <span class="sort-icon">
                                                    <i class="bi bi-caret-down-fill"></i>
                                                </span>
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($data) > 0): ?>
                                    <?php foreach ($data as $row): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($row['id']) ?></strong></td>
                                            <td><span class="badge bg-primary rounded-pill"><?= htmlspecialchars($row['device_name']) ?></span></td>
                                            <td class="text-danger fw-bold"><?= htmlspecialchars($row['temperature']) ?> °C</td>
                                            <td class="text-info fw-bold"><?= htmlspecialchars($row['humidity']) ?> %</td>
                                            <td class="text-muted"><small class="local-time" data-utc="<?= htmlspecialchars(date('c', strtotime($row['created_at']))) ?>">Loading...</small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="py-5 text-muted">
                                            <h4>No data found.</h4>
                                            <?php if ($has_filters): ?>
                                                <p>Try adjusting your filters or <a href="logs.php">reset all filters</a>.</p>
                                            <?php else: ?>
                                                <p>Ensure your device is sending data or use the simulator.</p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- ── Pagination Controls ──────────────────────── -->
                    <div class="card-footer bg-light border-top py-3 d-flex flex-wrap justify-content-between align-items-center rounded-bottom"
                        style="border-end-start-radius: 15px; border-end-end-radius: 15px;">
                        <span class="text-muted small mb-2 mb-md-0">
                            Showing page <strong><?= $page ?></strong> of <strong><?= $total_pages ?></strong>
                            (Total: <strong><?= $total_records ?></strong> records<?= $has_filters ? ', filtered' : '' ?>)
                        </span>
                        <nav aria-label="Data Page Navigation">
                            <ul class="pagination pagination-sm mb-0">
                                <!-- Previous Button -->
                                <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= buildQS(['page' => $page - 1]) ?>" tabindex="-1">Previous</a>
                                </li>

                                <?php
                                // Show page numbers (max 7 visible)
                                $range = 3;
                                $start_page = max(1, $page - $range);
                                $end_page = min($total_pages, $page + $range);
                                if ($start_page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="?<?= buildQS(['page' => 1]) ?>">1</a></li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">…</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= buildQS(['page' => $i]) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">…</span></li>
                                    <?php endif; ?>
                                    <li class="page-item"><a class="page-link" href="?<?= buildQS(['page' => $total_pages]) ?>"><?= $total_pages ?></a></li>
                                <?php endif; ?>

                                <!-- Next Button -->
                                <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= buildQS(['page' => $page + 1]) ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Convert all UTC timestamps to the user's local timezone
        document.querySelectorAll('.local-time').forEach(function(el) {
            const utc = el.getAttribute('data-utc');
            if (utc) {
                const date = new Date(utc);
                el.textContent = date.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                }) + ', ' + date.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                });
            }
        });
    </script>
</body>

</html>
