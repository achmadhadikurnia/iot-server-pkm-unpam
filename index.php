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

    // Get all available devices
    $stmtDevices = $conn->query("SELECT DISTINCT device_name FROM sensor_data ORDER BY device_name ASC");
    $all_devices = $stmtDevices->fetchAll(PDO::FETCH_COLUMN);

    // Device filter (comma-separated from GET, default: all)
    $selected_devices = [];
    if (isset($_GET['devices']) && $_GET['devices'] !== '') {
        $selected_devices = explode(',', $_GET['devices']);
        // Validate
        $selected_devices = array_intersect($selected_devices, $all_devices);
    }
    if (empty($selected_devices)) {
        $selected_devices = $all_devices;
    }

    // Build device WHERE clause
    $device_placeholders = implode(',', array_fill(0, count($selected_devices), '?'));
    $device_where = "device_name IN ($device_placeholders)";

    // Highest Temp within selected range & devices
    $stmtTemp = $conn->prepare("SELECT device_name, temperature, created_at FROM sensor_data WHERE created_at >= NOW() - INTERVAL '$interval' AND $device_where ORDER BY temperature DESC LIMIT 1");
    $stmtTemp->execute($selected_devices);
    $rowTemp = $stmtTemp->fetch(PDO::FETCH_ASSOC);
    $highest_temp = $rowTemp ? (float)$rowTemp['temperature'] : 0;
    $highest_temp_time = $rowTemp ? date('d M Y, H:i:s', strtotime($rowTemp['created_at'])) : '-';
    $highest_temp_device = $rowTemp ? $rowTemp['device_name'] : '-';

    // Highest Humidity within selected range & devices
    $stmtHum = $conn->prepare("SELECT device_name, humidity, created_at FROM sensor_data WHERE created_at >= NOW() - INTERVAL '$interval' AND $device_where ORDER BY humidity DESC LIMIT 1");
    $stmtHum->execute($selected_devices);
    $rowHum = $stmtHum->fetch(PDO::FETCH_ASSOC);
    $highest_hum = $rowHum ? (float)$rowHum['humidity'] : 0;
    $highest_hum_time = $rowHum ? date('d M Y, H:i:s', strtotime($rowHum['created_at'])) : '-';
    $highest_hum_device = $rowHum ? $rowHum['device_name'] : '-';

    // Trend Data with aggregation strategy per device
    if ($range === '12h') {
        $query = "SELECT 
            device_name,
            date_trunc('hour', created_at) + INTERVAL '30 min' * FLOOR(EXTRACT(MINUTE FROM created_at) / 30) AS period,
            ROUND(AVG(temperature)::numeric, 2) AS temperature,
            ROUND(AVG(humidity)::numeric, 2) AS humidity
            FROM sensor_data 
            WHERE created_at >= NOW() - INTERVAL '12 hours' AND $device_where
            GROUP BY device_name, period
            ORDER BY period ASC";
    } elseif ($range === '24h') {
        $query = "SELECT 
            device_name,
            date_trunc('hour', created_at) AS period,
            ROUND(AVG(temperature)::numeric, 2) AS temperature,
            ROUND(AVG(humidity)::numeric, 2) AS humidity
            FROM sensor_data 
            WHERE created_at >= NOW() - INTERVAL '1 day' AND $device_where
            GROUP BY device_name, period
            ORDER BY period ASC";
    } elseif ($range === '7d') {
        $query = "SELECT 
            device_name,
            date_trunc('hour', created_at) - (EXTRACT(HOUR FROM created_at)::int % 6) * INTERVAL '1 hour' AS period,
            ROUND(AVG(temperature)::numeric, 2) AS temperature,
            ROUND(AVG(humidity)::numeric, 2) AS humidity
            FROM sensor_data 
            WHERE created_at >= NOW() - INTERVAL '7 days' AND $device_where
            GROUP BY device_name, period
            ORDER BY period ASC";
    } else {
        // Raw data for 1h, 6h
        $query = "SELECT device_name, created_at AS period, temperature, humidity 
            FROM sensor_data 
            WHERE created_at >= NOW() - INTERVAL '$interval' AND $device_where
            ORDER BY created_at ASC
            LIMIT 5000";
    }

    $stmtCharts = $conn->prepare($query);
    $stmtCharts->execute($selected_devices);
    $raw_data = $stmtCharts->fetchAll(PDO::FETCH_ASSOC);

    // Organize data per device
    $devices_data = [];
    $all_timestamps = [];

    foreach ($raw_data as $row) {
        $device = $row['device_name'];
        $ts = strtotime($row['period']);
        
        if ($range === '7d') {
            $label = date('d M H:i', $ts);
        } elseif (in_array($range, ['12h', '24h'])) {
            $label = date('H:i', $ts);
        } else {
            $label = date('H:i:s', $ts);
        }

        if (!isset($devices_data[$device])) {
            $devices_data[$device] = [];
        }
        $devices_data[$device][$label] = [
            'temp' => (float)$row['temperature'],
            'hum' => (float)$row['humidity'],
        ];
        $all_timestamps[$label] = $ts;
    }

    // Sort timestamps
    asort($all_timestamps);
    $sorted_labels = array_keys($all_timestamps);

    // Build final chart arrays per device (fill gaps with null for smooth lines)
    $chart_series = [];
    foreach ($devices_data as $device => $data) {
        $temps = [];
        $hums = [];
        foreach ($sorted_labels as $label) {
            if (isset($data[$label])) {
                $temps[] = $data[$label]['temp'];
                $hums[] = $data[$label]['hum'];
            } else {
                $temps[] = null;
                $hums[] = null;
            }
        }
        $chart_series[$device] = [
            'temps' => $temps,
            'hums' => $hums,
        ];
    }

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'relation "sensor_data" does not exist') !== false || strpos($e->getMessage(), "Table 'sensor_data' doesn't exist") !== false) {
        $error = "Table 'sensor_data' does not exist yet. Please run migration in Setup.";
    } else {
        $error = "Failed to fetch data: " . $e->getMessage();
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

// Color palette for devices
$device_colors = [
    '#dc3545', '#0dcaf0', '#198754', '#ffc107', '#6f42c1',
    '#fd7e14', '#d63384', '#20c997', '#0d6efd', '#6610f2',
    '#e83e8c', '#17a2b8', '#28a745', '#ff6384', '#36a2eb',
];
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
            // Preserve devices param if present
            const devicesParam = urlParams.get('devices');
            let url = '?range=' + savedRange;
            if (devicesParam) url += '&devices=' + devicesParam;
            window.location.replace(url);
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
        .device-filter {
            position: relative;
            display: inline-block;
        }
        .device-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 12px 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            z-index: 1000;
            min-width: 220px;
            max-height: 300px;
            overflow-y: auto;
        }
        .device-dropdown.show { display: block; }
        .device-dropdown label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 4px 0;
            cursor: pointer;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        .device-dropdown label:hover { color: #0d6efd; }
        .device-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
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
            <!-- Time Range Filter -->
            <form method="GET" class="d-flex" id="rangeForm">
                <input type="hidden" name="devices" value="<?= htmlspecialchars(implode(',', $selected_devices ?? [])) ?>">
                <select name="range" class="form-select form-select-sm fw-bold border-secondary shadow-sm" style="width: auto; cursor: pointer;" onchange="this.form.submit()">
                    <option value="1h" <?= $range === '1h' ? 'selected' : '' ?>>Last 1 Hour</option>
                    <option value="6h" <?= $range === '6h' ? 'selected' : '' ?>>Last 6 Hours</option>
                    <option value="12h" <?= $range === '12h' ? 'selected' : '' ?>>Last 12 Hours</option>
                    <option value="24h" <?= $range === '24h' ? 'selected' : '' ?>>Last 24 Hours</option>
                    <option value="7d" <?= $range === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
                </select>
            </form>

            <!-- Device Filter -->
            <?php if (!isset($error) && count($all_devices) > 0): ?>
            <div class="device-filter">
                <button class="btn btn-outline-secondary btn-sm fw-bold" onclick="document.getElementById('deviceDropdown').classList.toggle('show')">
                    📡 Devices (<?= count($selected_devices) ?>/<?= count($all_devices) ?>)
                </button>
                <div id="deviceDropdown" class="device-dropdown">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <strong class="small">Filter Devices</strong>
                        <div>
                            <button class="btn btn-link btn-sm p-0 me-2" onclick="toggleAllDevices(true)">All</button>
                            <button class="btn btn-link btn-sm p-0" onclick="toggleAllDevices(false)">None</button>
                        </div>
                    </div>
                    <?php foreach ($all_devices as $i => $device): ?>
                    <label>
                        <input type="checkbox" class="form-check-input device-cb" value="<?= htmlspecialchars($device) ?>"
                            <?= in_array($device, $selected_devices) ? 'checked' : '' ?>>
                        <span class="device-dot" style="background: <?= $device_colors[$i % count($device_colors)] ?>"></span>
                        <?= htmlspecialchars($device) ?>
                    </label>
                    <?php endforeach; ?>
                    <div class="mt-2 pt-2 border-top">
                        <button class="btn btn-primary btn-sm w-100 fw-bold" onclick="applyDeviceFilter()">Apply</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <button onclick="window.location.reload()" class="btn btn-outline-primary btn-sm fw-bold">🔄 Refresh</button>
            <a href="simulator.php" class="btn btn-outline-secondary btn-sm fw-bold">🧪 Simulator</a>
            <a href="dashboard.php" class="btn btn-primary btn-sm fw-bold">📊 Full Data</a>
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
                <p class="text-muted small mt-2 mb-0">
                    <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($highest_temp_device) ?></span>
                    on <strong class="text-dark"><?= $highest_temp_time ?></strong>
                </p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="widget-card text-center">
                <h5 class="fw-bold text-info">💧 Highest Humidity</h5>
                <div id="gaugeHum" class="gauge-container"></div>
                <p class="text-muted small mt-2 mb-0">
                    <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($highest_hum_device) ?></span>
                    on <strong class="text-dark"><?= $highest_hum_time ?></strong>
                </p>
            </div>
        </div>
    </div>

    <!-- Trend Section Header -->
    <div class="mb-3 mt-4">
        <h4 class="fw-bold mb-0">Trend Analysis</h4>
        <small class="text-muted"><?= $range_label ?> &mdash; <?= count($raw_data) ?> data points across <?= count($devices_data) ?> device(s)</small>
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
        const chartLabels = <?= json_encode(array_values($sorted_labels ?? [])) ?>;
        const chartSeries = <?= json_encode($chart_series ?? []) ?>;
        const deviceColors = <?= json_encode($device_colors) ?>;
        const deviceNames = Object.keys(chartSeries);

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

        // Build multi-series line chart options
        function getMultiLineOption(labels, seriesData, dataKey, unit) {
            var series = [];
            deviceNames.forEach(function(device, idx) {
                var color = deviceColors[idx % deviceColors.length];
                series.push({
                    name: device,
                    data: seriesData[device][dataKey],
                    type: 'line',
                    smooth: true,
                    connectNulls: true,
                    lineStyle: { width: 2.5, color: color },
                    itemStyle: { color: color },
                    areaStyle: {
                        color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                            { offset: 0, color: color + '40' },
                            { offset: 1, color: color + '08' }
                        ])
                    },
                    emphasis: { focus: 'series' }
                });
            });

            return {
                tooltip: { 
                    trigger: 'axis',
                    formatter: function(params) {
                        var html = params[0].axisValue + '<br/>';
                        params.forEach(function(p) {
                            if (p.value !== null && p.value !== undefined) {
                                html += p.marker + ' ' + p.seriesName + ': <strong>' + p.value + ' ' + unit + '</strong><br/>';
                            }
                        });
                        return html;
                    }
                },
                legend: {
                    data: deviceNames,
                    bottom: 25,
                    textStyle: { fontSize: 11 }
                },
                grid: { left: '12%', right: '5%', bottom: '22%', top: '10%' },
                xAxis: {
                    type: 'category',
                    data: labels,
                    boundaryGap: false,
                    axisLine: { lineStyle: { color: '#999' } },
                    axisLabel: { 
                        fontSize: 10,
                        rotate: labels.length > 20 ? 45 : 0
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
                series: series
            };
        }

        // Initialize Line Charts
        var lineTempChart = echarts.init(document.getElementById('lineTemp'));
        lineTempChart.setOption(getMultiLineOption(chartLabels, chartSeries, 'temps', '°C'));

        var lineHumChart = echarts.init(document.getElementById('lineHum'));
        lineHumChart.setOption(getMultiLineOption(chartLabels, chartSeries, 'hums', '%'));

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

        // Device filter functions
        function toggleAllDevices(checked) {
            document.querySelectorAll('.device-cb').forEach(cb => cb.checked = checked);
        }

        function applyDeviceFilter() {
            const checked = [];
            document.querySelectorAll('.device-cb:checked').forEach(cb => checked.push(cb.value));
            const params = new URLSearchParams(window.location.search);
            params.set('devices', checked.join(','));
            window.location.search = params.toString();
        }

        // Close device dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('deviceDropdown');
            const filter = e.target.closest('.device-filter');
            if (!filter && dropdown) dropdown.classList.remove('show');
        });
    </script>
    <?php endif; ?>
</div>

</body>
</html>
