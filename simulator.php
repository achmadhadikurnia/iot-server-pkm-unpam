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
            
            <?php
                $randomTemp = number_format(mt_rand(200, 350) / 10, 1);
                $randomHum = number_format(mt_rand(400, 800) / 10, 1);
            ?>
            <form id="sensorForm">
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold">Device Name</label>
                    <input type="text" class="form-control" name="device_name" value="ESP32_Test" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold">Temperature (°C)</label>
                    <input type="number" step="0.01" class="form-control" name="temperature" id="tempInput" value="<?= $randomTemp ?>" required>
                    <input type="range" class="form-range mt-2" min="-10" max="50" step="0.1" value="<?= $randomTemp ?>" oninput="document.getElementById('tempInput').value = this.value">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted fw-bold">Humidity (%)</label>
                    <input type="number" step="0.01" class="form-control" name="humidity" id="humInput" value="<?= $randomHum ?>" required>
                    <input type="range" class="form-range mt-2" min="0" max="100" step="0.1" value="<?= $randomHum ?>" oninput="document.getElementById('humInput').value = this.value">
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted fw-bold">Created At (Optional)</label>
                    <input type="datetime-local" class="form-control" name="created_at" id="created_at">
                    <div class="form-text">Leave blank to use current server time.</div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold" id="submitBtn">Send Simulator Data</button>
            </form>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-between align-items-center">
                <a href="index.php" class="btn btn-outline-secondary btn-sm fw-bold">
                    ⬅️ Back to Dashboard
                </a>
                <a href="bulk-simulator.php" class="btn btn-outline-dark btn-sm fw-bold">
                    📦 Bulk Seeder ➡️
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
    
    // Convert created_at to UTC before sending (database stores UTC)
    const createdAtInput = document.getElementById('created_at');
    if (createdAtInput.value) {
        const localDate = new Date(createdAtInput.value);
        const pad = (n) => String(n).padStart(2, '0');
        const utcStr = localDate.getUTCFullYear() + '-' +
            pad(localDate.getUTCMonth() + 1) + '-' +
            pad(localDate.getUTCDate()) + ' ' +
            pad(localDate.getUTCHours()) + ':' +
            pad(localDate.getUTCMinutes()) + ':' +
            pad(localDate.getUTCSeconds());
        formData.set('created_at', utcStr);
    }
    
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
