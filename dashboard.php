<?php
include "database.php";

// Pagination settings
$limit = 20; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$data = [];
$total_pages = 1;
try {
    // Get total records
    $stmtTotal = $conn->query("SELECT COUNT(*) FROM sensor_data");
    $total_records = $stmtTotal->fetchColumn();
    $total_pages = ceil($total_records / $limit);
    if ($total_pages == 0) $total_pages = 1;

    // Fetch data for the current page
    $stmt = $conn->prepare("SELECT * FROM sensor_data ORDER BY id DESC LIMIT :limit OFFSET :offset");
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Sensor Data Dashboard</title>
    <!-- Premium Design Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .dashboard-card { border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); margin-top: 50px; margin-bottom: 50px; }
        .table-hover tbody tr:hover { background-color: #f1f5f9; transition: 0.3s; }
    </style>
</head>
<body>

<div class="container">
    <div class="card dashboard-card">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center rounded-top" style="border-start-start-radius: 15px; border-start-end-radius: 15px; padding: 1rem 1.5rem;">
            <div>
                <h4 class="mb-0">Sensor Readings Dashboard</h4>
                <p class="mb-0"><small class="text-light">Page <?= $page ?> of <?= $total_pages ?> (Total: <?= isset($total_records) ? $total_records : 0 ?> records)</small></p>
            </div>
            <div>
                <a href="simulator.php" class="btn btn-outline-secondary btn-sm me-1">Simulator</a>
                <a href="index.php" class="btn btn-outline-light btn-sm">Return to Dashboard</a>
            </div>
        </div>
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
                                <th class="py-3">ID</th>
                                <th class="py-3">Device Name</th>
                                <th class="py-3">Temperature (°C)</th>
                                <th class="py-3">Humidity (%)</th>
                                <th class="py-3">Timestamp</th>
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
                                        <td class="text-muted"><small><?= htmlspecialchars(date('d M Y, H:i', strtotime($row['created_at']))) ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-5 text-muted"><h4>No data found on this page.</h4><p>Ensure your device is sending data or use the simulator.</p></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Controls -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white border-0 py-3 rounded-bottom" style="border-end-start-radius: 15px; border-end-end-radius: 15px;">
                    <nav aria-label="Data Page Navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <!-- Previous Button -->
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>" tabindex="-1">Previous</a>
                            </li>
                            
                            <!-- Next Button -->
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
