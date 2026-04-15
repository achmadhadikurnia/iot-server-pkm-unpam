<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk IoT Simulator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .simulator-card {
            max-width: 600px;
            margin: 40px auto;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card simulator-card">
        <div class="card-header bg-dark text-white text-center rounded-top" style="border-start-start-radius: 15px; border-start-end-radius: 15px;">
            <h4 class="mb-0">📦 Bulk Sensor Data Seeder</h4>
            <p class="mb-0"><small>Generate and insert dummy data in batches</small></p>
        </div>
        <div class="card-body p-4">
            <div id="responseMessage" class="alert d-none" role="alert"></div>
            
            <form id="bulkForm">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted fw-bold">Device Name</label>
                        <input type="text" class="form-control" id="device_name" value="ESP32_Bulk" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted fw-bold">Number of Data Points</label>
                        <input type="number" class="form-control" id="num_data" value="50" min="1" max="1000" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted fw-bold">Start Date & Time</label>
                        <input type="datetime-local" class="form-control" id="start_date" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted fw-bold">End Date & Time</label>
                        <input type="datetime-local" class="form-control" id="end_date" required>
                    </div>
                </div>

                <div class="row mt-2">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted fw-bold">Min Temp (°C)</label>
                        <input type="number" class="form-control" id="min_temp" value="20.0" step="0.1" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted fw-bold">Max Temp (°C)</label>
                        <input type="number" class="form-control" id="max_temp" value="35.0" step="0.1" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label text-muted fw-bold">Min Humidity (%)</label>
                        <input type="number" class="form-control" id="min_hum" value="40.0" step="0.1" required>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label text-muted fw-bold">Max Humidity (%)</label>
                        <input type="number" class="form-control" id="max_hum" value="80.0" step="0.1" required>
                    </div>
                </div>

                <div class="progress mb-3 d-none" id="progressContainer" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="progressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                </div>

                <button type="submit" class="btn btn-dark w-100 py-2 fw-bold" id="submitBtn">Generate & Send Bulk Data</button>
            </form>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-between align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm fw-bold">
                    ⬅️ Back to Dashboard
                </a>
                <a href="simulator.php" class="btn btn-outline-info btn-sm fw-bold">
                    ⚙️ Single Simulator ➡️
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Set default start and end dates (e.g. past 24 hours)
const now = new Date();
const yesterday = new Date(now.getTime() - (24 * 60 * 60 * 1000));

// Format for datetime-local input using LOCAL time: YYYY-MM-DDThh:mm
const formatDateTime = (date) => {
    const pad = (n) => n < 10 ? '0' + n : n;
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) +
           'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
};

document.getElementById('start_date').value = formatDateTime(yesterday);
document.getElementById('end_date').value = formatDateTime(now);

document.getElementById('bulkForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // Get Elements
    const btn = document.getElementById('submitBtn');
    const msg = document.getElementById('responseMessage');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    
    // Get values
    const deviceName = document.getElementById('device_name').value;
    const numData = parseInt(document.getElementById('num_data').value);
    
    const startTimeStamp = new Date(document.getElementById('start_date').value).getTime();
    const endTimeStamp = new Date(document.getElementById('end_date').value).getTime();
    
    const minTemp = parseFloat(document.getElementById('min_temp').value);
    const maxTemp = parseFloat(document.getElementById('max_temp').value);
    const minHum = parseFloat(document.getElementById('min_hum').value);
    const maxHum = parseFloat(document.getElementById('max_hum').value);

    // Validation
    if(endTimeStamp <= startTimeStamp) {
        alert("End Date must be greater than Start Date.");
        return;
    }

    // UI Updates
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
    msg.classList.add('d-none');
    progressContainer.classList.remove('d-none');
    progressBar.style.width = '0%';
    progressBar.innerText = '0%';
    progressBar.classList.remove('bg-danger');
    progressBar.classList.add('bg-success');

    let successCount = 0;
    let errorCount = 0;
    
    const stepTime = (endTimeStamp - startTimeStamp) / (numData > 1 ? (numData - 1) : 1);

    for (let i = 0; i < numData; i++) {
        // Calculate spread time
        const currentTimestamp = startTimeStamp + (stepTime * i);
        const currentDate = new Date(currentTimestamp);
        
        // PHP expects standard MySQL datetime string "YYYY-MM-DD HH:MM:SS" or the datetime-local format.
        // Let's use the local ISO string format that PHP can parse well: YYYY-MM-DDTHH:MM
        
        const pad = (n) => n < 10 ? '0' + n : n;
        // Convert to UTC before sending (database stores UTC)
        const formattedDate = currentDate.getUTCFullYear() + "-" + 
                              pad(currentDate.getUTCMonth() + 1) + "-" + 
                              pad(currentDate.getUTCDate()) + " " + 
                              pad(currentDate.getUTCHours()) + ":" + 
                              pad(currentDate.getUTCMinutes()) + ":" +
                              pad(currentDate.getUTCSeconds());
        
        // Generate random values
        const randomTemp = (Math.random() * (maxTemp - minTemp) + minTemp).toFixed(2);
        const randomHum = (Math.random() * (maxHum - minHum) + minHum).toFixed(2);
        
        const formData = new FormData();
        formData.append('device_name', deviceName);
        formData.append('temperature', randomTemp);
        formData.append('humidity', randomHum);
        formData.append('created_at', formattedDate);
        
        try {
            const response = await fetch('insert-sensor.php', {
                method: 'POST',
                body: formData
            });
            const text = await response.text();
            if (text.includes("SUCCESS")) {
                successCount++;
            } else {
                errorCount++;
            }
        } catch (err) {
            errorCount++;
            console.error("Error inserting data:", err);
        }

        // Update progress
        const percent = Math.round(((i + 1) / numData) * 100);
        progressBar.style.width = percent + '%';
        progressBar.innerText = percent + '% - ' + (i + 1) + '/' + numData;
    }
    
    // Finished
    btn.disabled = false;
    btn.innerHTML = 'Generate & Send Bulk Data';
    
    msg.classList.remove('d-none', 'alert-danger', 'alert-success', 'alert-warning');
    if (errorCount === 0) {
        msg.classList.add('alert-success');
        msg.innerHTML = `<strong>Success!</strong> Successfully inserted ${successCount} dummy data points.`;
    } else if (successCount > 0) {
        msg.classList.add('alert-warning');
        msg.innerHTML = `<strong>Partial Success!</strong> Inserted ${successCount} successfully, but ${errorCount} failed.`;
        progressBar.classList.replace('bg-success', 'bg-warning');
    } else {
        msg.classList.add('alert-danger');
        msg.innerHTML = `<strong>Failed!</strong> All ${errorCount} data points failed to insert.`;
        progressBar.classList.replace('bg-success', 'bg-danger');
    }
});
</script>
</body>
</html>
