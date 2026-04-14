<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IoT Sensor Simulator</title>
    <!-- Add Bootstrap for a premium design look -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .simulator-card {
            max-width: 500px;
            margin: 50px auto;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card simulator-card">
        <div class="card-header bg-primary text-white text-center rounded-top" style="border-start-start-radius: 15px; border-start-end-radius: 15px;">
            <h4 class="mb-0">IoT Sensor Simulator</h4>
            <p class="mb-0"><small>Send simulated data to insert-sensor.php</small></p>
        </div>
        <div class="card-body p-4">
            <div id="responseMessage" class="alert d-none" role="alert"></div>
            
            <form id="sensorForm">
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold">Device Name</label>
                    <input type="text" class="form-control" name="device_name" value="ESP32_Test" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold">Temperature (°C)</label>
                    <input type="number" step="0.01" class="form-control" name="temperature" id="tempInput" value="25.5" required>
                    <input type="range" class="form-range mt-2" min="-10" max="50" step="0.1" value="25.5" oninput="document.getElementById('tempInput').value = this.value">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted fw-bold">Humidity (%)</label>
                    <input type="number" step="0.01" class="form-control" name="humidity" id="humInput" value="60.0" required>
                    <input type="range" class="form-range mt-2" min="0" max="100" step="0.1" value="60.0" oninput="document.getElementById('humInput').value = this.value">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" id="submitBtn">Send Simulator Data</button>
            </form>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-center gap-2">
                <a href="dashboard.php" class="btn btn-outline-success btn-sm fw-bold">
                    📊 View Recorded Data
                </a>
                <a href="setup.php" class="btn btn-outline-secondary btn-sm">
                    ⚙️ Test Database Connection & Migration
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('sensorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    const msg = document.getElementById('responseMessage');
    
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
    msg.classList.add('d-none');
    
    const formData = new FormData(this);
    
    fetch('insert-sensor.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        msg.classList.remove('d-none', 'alert-danger');
        if (text.includes("SUCCESS")) {
            msg.classList.add('alert-success');
        } else {
            msg.classList.add('alert-warning');
        }
        msg.textContent = text;
    })
    .catch(err => {
        msg.classList.remove('d-none', 'alert-success', 'alert-warning');
        msg.classList.add('alert-danger');
        msg.textContent = 'Error: ' + err;
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = 'Send Simulator Data';
    });
});
</script>
</body>
</html>
