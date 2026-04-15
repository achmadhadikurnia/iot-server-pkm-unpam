<?php
include "database.php";

$highest_temp = 0;
$highest_hum = 0;
$last_10 = [];

try {
    // Highest Temp
    $stmtTemp = $conn->query("SELECT temperature, created_at FROM sensor_data ORDER BY temperature DESC LIMIT 1");
    $rowTemp = $stmtTemp->fetch(PDO::FETCH_ASSOC);
    $highest_temp = $rowTemp ? (float)$rowTemp['temperature'] : 0;
    $highest_temp_time = $rowTemp ? date('d M Y, H:i:s', strtotime($rowTemp['created_at'])) : '-';

    // Highest Hum
    $stmtHum = $conn->query("SELECT humidity, created_at FROM sensor_data ORDER BY humidity DESC LIMIT 1");
    $rowHum = $stmtHum->fetch(PDO::FETCH_ASSOC);
    $highest_hum = $rowHum ? (float)$rowHum['humidity'] : 0;
    $highest_hum_time = $rowHum ? date('d M Y, H:i:s', strtotime($rowHum['created_at'])) : '-';

    // Last 10 records
    $stmt10 = $conn->query("SELECT * FROM sensor_data ORDER BY id DESC LIMIT 10");
    $last_10 = $stmt10->fetchAll(PDO::FETCH_ASSOC);
    
    $chart_data = array_reverse($last_10);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'relation "sensor_data" does not exist') !== false || strpos($e->getMessage(), "Table 'sensor_data' doesn't exist") !== false) {
        $error = "Table 'sensor_data' does not exist yet. Please run migration in Setup.";
    } else {
        $error = "Failed to fetch data: " . $e->getMessage();
    }
}

$chart_timestamps = [];
$chart_temps = [];
$chart_hums = [];
$table_data = $last_10; 

if (!isset($error)) {
    foreach ($chart_data as $row) {
        $chart_timestamps[] = date('H:i:s', strtotime($row['created_at']));
        $chart_temps[] = (float)$row['temperature'];
        $chart_hums[] = (float)$row['humidity'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Dashboard</title>
    <!-- Add Bootstrap for a premium design look -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.5.0/dist/echarts.min.js"></script>
    <style>
        body { background: #f8f9fa; font-family: system-ui, -apple-system, sans-serif; }
        .widget-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 20px;
            height: 100%;
        }
        .chart-container {
            width: 100%;
            height: 300px;
        }
        .table-responsive {
            max-height: 250px;
            overflow-y: auto;
        }
        .gauge-container {
            width: 100%;
            height: 250px;
        }
        /* Custom scrollbar for tables */
        .table-responsive::-webkit-scrollbar {
            width: 6px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background-color: #cbd5e1;
            border-radius: 6px;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <h2 class="mb-0 fw-bold flex-grow-1">IoT Sensor Dashboard 
            <span class="badge bg-secondary fs-6 align-middle ms-lg-3 mt-2 mt-md-0 fw-normal">Refresh in <span id="countdown">30</span>s</span>
        </h2>
        <div>
            <button onclick="window.location.reload()" class="btn btn-success me-2 fw-bold">🔄 Refresh</button>
            <a href="simulator.php" class="btn btn-outline-secondary me-2 fw-bold">IoT Simulator</a>
            <a href="dashboard.php" class="btn btn-primary fw-bold">View Full Data</a>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php else: ?>

    <!-- Top Row: Gauges for highest values -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3 mb-md-0">
            <div class="widget-card text-center">
                <h5 class="fw-bold text-danger">Highest Temperature</h5>
                <div id="gaugeTemp" class="gauge-container"></div>
                <p class="text-muted small mt-2 mb-0">Recorded on: <strong class="text-dark"><?= $highest_temp_time ?></strong></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="widget-card text-center">
                <h5 class="fw-bold text-info">Highest Humidity</h5>
                <div id="gaugeHum" class="gauge-container"></div>
                <p class="text-muted small mt-2 mb-0">Recorded on: <strong class="text-dark"><?= $highest_hum_time ?></strong></p>
            </div>
        </div>
    </div>

    <!-- Second Row: Line Charts & Data Tables -->
    <div class="row mb-4">
        <!-- Temperature Column -->
        <div class="col-xl-6 mb-4 mb-xl-0">
            <div class="widget-card">
                <h5 class="fw-bold text-danger mb-3">Temperature Trend (Last 10)</h5>
                <div id="lineTemp" class="chart-container"></div>
                <hr class="my-4">
                <h6 class="fw-bold text-secondary mb-3">Recent Temperature Data</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover text-center align-middle mb-0">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th>Timestamp</th>
                                <th>Temperature (°C)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($table_data as $row): ?>
                            <tr>
                                <td class="text-muted"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></td>
                                <td class="text-danger fw-bold"><?= htmlspecialchars($row['temperature']) ?> °C</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($table_data)): ?>
                            <tr><td colspan="2" class="text-muted py-3">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Humidity Column -->
        <div class="col-xl-6">
            <div class="widget-card">
                <h5 class="fw-bold text-info mb-3">Humidity Trend (Last 10)</h5>
                <div id="lineHum" class="chart-container"></div>
                <hr class="my-4">
                <h6 class="fw-bold text-secondary mb-3">Recent Humidity Data</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover text-center align-middle mb-0">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th>Timestamp</th>
                                <th>Humidity (%)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($table_data as $row): ?>
                            <tr>
                                <td class="text-muted"><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></td>
                                <td class="text-info fw-bold"><?= htmlspecialchars($row['humidity']) ?> %</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($table_data)): ?>
                            <tr><td colspan="2" class="text-muted py-3">No data available</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ECharts Initialization -->
    <script>
        // Data parsed from PHP
        const maxTemp = <?= json_encode($highest_temp) ?>;
        const maxHum = <?= json_encode($highest_hum) ?>;
        const chartTimes = <?= json_encode($chart_timestamps) ?>;
        const chartTemps = <?= json_encode($chart_temps) ?>;
        const chartHums = <?= json_encode($chart_hums) ?>;

        // Configuration helper for Gauges
        function getGaugeOption(value, name, color, unit) {
            return {
                series: [
                    {
                        type: 'gauge',
                        min: 0,
                        max: 100,
                        itemStyle: { color: color },
                        progress: { show: true, width: 14 },
                        axisLine: { lineStyle: { width: 14 } },
                        axisTick: { show: false },
                        splitLine: { length: 14, lineStyle: { width: 2, color: '#999' } },
                        axisLabel: { distance: 20, color: '#999', fontSize: 11 },
                        pointer: {
                            icon: 'path://M12.8,0.7l12,40.1H0.7L12.8,0.7z',
                            length: '12%',
                            width: 8,
                            offsetCenter: [0, '-60%']
                        },
                        detail: {
                            valueAnimation: true,
                            fontSize: 28,
                            offsetCenter: [0, '70%'],
                            formatter: '{value} ' + unit,
                            color: color
                        },
                        data: [{ value: value }]
                    }
                ]
            };
        }

        // Initialize Gauges
        var gaugeTempChart = echarts.init(document.getElementById('gaugeTemp'));
        gaugeTempChart.setOption(getGaugeOption(maxTemp, 'Temp', '#dc3545', '°C'));

        var gaugeHumChart = echarts.init(document.getElementById('gaugeHum'));
        gaugeHumChart.setOption(getGaugeOption(maxHum, 'Hum', '#0dcaf0', '%'));

        // Configuration helper for Line Charts
        function getLineOption(times, data, color, hexRgba) {
            return {
                tooltip: { trigger: 'axis' },
                grid: { left: '12%', right: '5%', bottom: '15%', top: '10%' },
                xAxis: {
                    type: 'category',
                    data: times,
                    boundaryGap: false,
                    axisLine: { lineStyle: { color: '#999' } },
                    axisLabel: { fontSize: 10 }
                },
                yAxis: {
                    type: 'value',
                    axisLine: { show: true, lineStyle: { color: '#999' } },
                    splitLine: { lineStyle: { type: 'dashed', color: '#eee' } }
                },
                series: [{
                    data: data,
                    type: 'line',
                    smooth: true,
                    lineStyle: { width: 3, color: color },
                    itemStyle: { color: color },
                    areaStyle: {
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: hexRgba[0] },
                            { offset: 1, color: hexRgba[1] }
                        ])
                    }
                }]
            };
        }

        // Initialize Line Charts
        var lineTempChart = echarts.init(document.getElementById('lineTemp'));
        lineTempChart.setOption(getLineOption(
            chartTimes, 
            chartTemps, 
            '#dc3545', 
            ['rgba(220, 53, 69, 0.4)', 'rgba(220, 53, 69, 0.05)']
        ));

        var lineHumChart = echarts.init(document.getElementById('lineHum'));
        lineHumChart.setOption(getLineOption(
            chartTimes, 
            chartHums, 
            '#0dcaf0', 
            ['rgba(13, 202, 240, 0.4)', 'rgba(13, 202, 240, 0.05)']
        ));

        // Responsive behavior
        window.addEventListener('resize', function() {
            gaugeTempChart.resize();
            gaugeHumChart.resize();
            lineTempChart.resize();
            lineHumChart.resize();
        });
        
        // Auto refresh countdown mechanism
        let timeLeft = 30;
        const countdownEl = document.getElementById('countdown');
        setInterval(function() {
            timeLeft--;
            if (countdownEl) countdownEl.innerText = Math.max(0, timeLeft);
            if (timeLeft <= 0) {
                window.location.reload();
            }
        }, 1000);
    </script>
    <?php endif; ?>
</div>

</body>
</html>
