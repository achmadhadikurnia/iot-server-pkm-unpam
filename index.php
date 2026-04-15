<?php
include "database.php";

$highest_temp = 0;
$highest_hum = 0;

try {
    // Determine time range
    $range = isset($_GET['range']) ? $_GET['range'] : '1h';
    $valid_ranges = ['1h', '6h', '12h', '24h', '7d'];
    if (!in_array($range, $valid_ranges)) {
        $range = '1h';
    }

    // Build interval clause
    $intervals = [
        '1h' => '1 hour',
        '6h' => '6 hours',
        '12h' => '12 hours',
        '24h' => '1 day',
        '7d' => '7 days',
    ];
    $interval = $intervals[$range];

    // Highest Temp within selected range
    $stmtTemp = $conn->query("SELECT temperature, created_at FROM sensor_data WHERE created_at >= NOW() - INTERVAL '$interval' ORDER BY temperature DESC LIMIT 1");
    $rowTemp = $stmtTemp->fetch(PDO::FETCH_ASSOC);
    $highest_temp = $rowTemp ? (float)$rowTemp['temperature'] : 0;
    $highest_temp_time = $rowTemp ? date('d M Y, H:i:s', strtotime($rowTemp['created_at'])) : '-';

    // Highest Humidity within selected range
    $stmtHum = $conn->query("SELECT humidity, created_at FROM sensor_data WHERE created_at >= NOW() - INTERVAL '$interval' ORDER BY humidity DESC LIMIT 1");
    $rowHum = $stmtHum->fetch(PDO::FETCH_ASSOC);
    $highest_hum = $rowHum ? (float)$rowHum['humidity'] : 0;
    $highest_hum_time = $rowHum ? date('d M Y, H:i:s', strtotime($rowHum['created_at'])) : '-';

    // Trend Data with aggregation strategy
    // 1h, 6h: raw data
    // 12h: avg per 30 minutes
    // 24h: avg per 1 hour
    // 7d: avg per 6 hours
    if ($range === '12h') {
        $query = "SELECT 
            date_trunc('hour', created_at) + INTERVAL '30 min' * FLOOR(EXTRACT(MINUTE FROM created_at) / 30) AS period,
            ROUND(AVG(temperature)::numeric, 2) AS temperature,
            ROUND(AVG(humidity)::numeric, 2) AS humidity
            FROM sensor_data 
            WHERE created_at >= NOW() - INTERVAL '12 hours'
            GROUP BY period
            ORDER BY period ASC";
    } elseif ($range === '24h') {
        $query = "SELECT 
            date_trunc('hour', created_at) AS period,
            ROUND(AVG(temperature)::numeric, 2) AS temperature,
            ROUND(AVG(humidity)::numeric, 2) AS humidity
            FROM sensor_data 
            WHERE created_at >= NOW() - INTERVAL '1 day'
            GROUP BY period
            ORDER BY period ASC";
    } elseif ($range === '7d') {
        $query = "SELECT 
            date_trunc('hour', created_at) - (EXTRACT(HOUR FROM created_at)::int % 6) * INTERVAL '1 hour' AS period,
            ROUND(AVG(temperature)::numeric, 2) AS temperature,
            ROUND(AVG(humidity)::numeric, 2) AS humidity
            FROM sensor_data 
            WHERE created_at >= NOW() - INTERVAL '7 days'
            GROUP BY period
            ORDER BY period ASC";
    } else {
        // Raw data for 1h, 6h
        $query = "SELECT created_at AS period, temperature, humidity 
            FROM sensor_data 
            WHERE created_at >= NOW() - INTERVAL '$interval'
            ORDER BY created_at ASC
            LIMIT 1000";
    }

    $stmtCharts = $conn->query($query);
    $chart_data = $stmtCharts->fetchAll(PDO::FETCH_ASSOC);

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

if (!isset($error)) {
    foreach ($chart_data as $row) {
        $ts = strtotime($row['period']);
        if ($range === '7d') {
            $chart_timestamps[] = date('d M H:i', $ts);
        } elseif (in_array($range, ['12h', '24h'])) {
            $chart_timestamps[] = date('H:i', $ts);
        } else {
            $chart_timestamps[] = date('H:i:s', $ts);
        }
        $chart_temps[] = (float)$row['temperature'];
        $chart_hums[] = (float)$row['humidity'];
    }
}

// Range labels for display
$range_labels = [
    '1h' => 'Last 1 Hour',
    '6h' => 'Last 6 Hours',
    '12h' => 'Last 12 Hours (avg/30min)',
    '24h' => 'Last 24 Hours (avg/hour)',
    '7d' => 'Last 7 Days (avg/6h)',
];
$range_label = $range_labels[$range] ?? 'Last 1 Hour';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Dashboard</title>
    
    <script>
        // Sync selected range with localStorage across dashboards
        const urlParams = new URLSearchParams(window.location.search);
        const rangeParam = urlParams.get('range');
        const savedRange = localStorage.getItem('trendRange');
        
        if (rangeParam) {
            localStorage.setItem('trendRange', rangeParam);
        } else if (savedRange && savedRange !== '1h') {
            window.location.replace('?range=' + savedRange);
        }
    </script>
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
            height: 350px;
        }
        .gauge-container {
            width: 100%;
            height: 250px;
        }
    </style>
</head>
<body>

<div class="container py-4">
    <!-- Header Row -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 gap-3">
        <h2 class="mb-0 fw-bold flex-grow-1">IoT Sensor Dashboard 
            <span class="badge bg-secondary fs-6 align-middle ms-lg-3 mt-2 mt-md-0 fw-normal">Refresh in <span id="countdown">30</span>s</span>
        </h2>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Time Range Filter (global, applies to all widgets) -->
            <form method="GET" class="d-flex" id="rangeForm">
                <select name="range" class="form-select form-select-sm fw-bold border-secondary shadow-sm" style="width: auto; cursor: pointer;" onchange="this.form.submit()">
                    <option value="1h" <?= $range === '1h' ? 'selected' : '' ?>>Last 1 Hour</option>
                    <option value="6h" <?= $range === '6h' ? 'selected' : '' ?>>Last 6 Hours</option>
                    <option value="12h" <?= $range === '12h' ? 'selected' : '' ?>>Last 12 Hours</option>
                    <option value="24h" <?= $range === '24h' ? 'selected' : '' ?>>Last 24 Hours</option>
                    <option value="7d" <?= $range === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
                </select>
            </form>
            <button onclick="window.location.reload()" class="btn btn-success btn-sm fw-bold">🔄 Refresh</button>
            <a href="simulator.php" class="btn btn-outline-secondary btn-sm fw-bold">IoT Simulator</a>
            <a href="dashboard.php" class="btn btn-primary btn-sm fw-bold">View Full Data</a>
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
                <h5 class="fw-bold text-danger">🌡️ Highest Temperature</h5>
                <div id="gaugeTemp" class="gauge-container"></div>
                <p class="text-muted small mt-2 mb-0">Recorded on: <strong class="text-dark"><?= $highest_temp_time ?></strong></p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="widget-card text-center">
                <h5 class="fw-bold text-info">💧 Highest Humidity</h5>
                <div id="gaugeHum" class="gauge-container"></div>
                <p class="text-muted small mt-2 mb-0">Recorded on: <strong class="text-dark"><?= $highest_hum_time ?></strong></p>
            </div>
        </div>
    </div>

    <!-- Trend Section Header -->
    <div class="mb-3 mt-4">
        <h4 class="fw-bold mb-0">Trend Analysis</h4>
        <small class="text-muted"><?= $range_label ?> &mdash; <?= count($chart_data) ?> data points</small>
    </div>

    <!-- Line Charts Row -->
    <div class="row mb-4">
        <!-- Temperature Chart -->
        <div class="col-xl-6 mb-4 mb-xl-0">
            <div class="widget-card">
                <h5 class="fw-bold text-danger mb-3">Temperature Trend</h5>
                <div id="lineTemp" class="chart-container"></div>
            </div>
        </div>

        <!-- Humidity Chart -->
        <div class="col-xl-6">
            <div class="widget-card">
                <h5 class="fw-bold text-info mb-3">Humidity Trend</h5>
                <div id="lineHum" class="chart-container"></div>
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
        function getLineOption(times, data, color, hexRgba, unit) {
            return {
                tooltip: { 
                    trigger: 'axis',
                    formatter: function(params) {
                        let p = params[0];
                        return p.axisValue + '<br/>' + p.marker + ' ' + p.value + ' ' + unit;
                    }
                },
                grid: { left: '12%', right: '5%', bottom: '18%', top: '10%' },
                xAxis: {
                    type: 'category',
                    data: times,
                    boundaryGap: false,
                    axisLine: { lineStyle: { color: '#999' } },
                    axisLabel: { 
                        fontSize: 10,
                        rotate: times.length > 20 ? 45 : 0
                    }
                },
                yAxis: {
                    type: 'value',
                    axisLine: { show: true, lineStyle: { color: '#999' } },
                    splitLine: { lineStyle: { type: 'dashed', color: '#eee' } }
                },
                dataZoom: [
                    { type: 'inside', start: 0, end: 100 },
                    { type: 'slider', start: 0, end: 100, height: 20, bottom: 0 }
                ],
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
            ['rgba(220, 53, 69, 0.4)', 'rgba(220, 53, 69, 0.05)'],
            '°C'
        ));

        var lineHumChart = echarts.init(document.getElementById('lineHum'));
        lineHumChart.setOption(getLineOption(
            chartTimes, 
            chartHums, 
            '#0dcaf0', 
            ['rgba(13, 202, 240, 0.4)', 'rgba(13, 202, 240, 0.05)'],
            '%'
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
        const refreshTimer = setInterval(function() {
            timeLeft--;
            if (countdownEl) countdownEl.innerText = Math.max(0, timeLeft);
            if (timeLeft <= 0) {
                clearInterval(refreshTimer);
                window.location.reload();
            }
        }, 1000);
    </script>
    <?php endif; ?>
</div>

</body>
</html>
