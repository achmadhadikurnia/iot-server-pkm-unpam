<?php
include "database.php";

if ($db_error || !$conn) {
    http_response_code(500);
    die("Database Error: " . htmlspecialchars($db_error ?? "Connection not available."));
}

// 1. Retrieve data from ESP32 or Simulator (Ensure POST variable names match the device code)
if(isset($_POST['temperature']) && isset($_POST['humidity']) && isset($_POST['device_name'])) {

    $temp = $_POST['temperature'];
    $humi = $_POST['humidity'];
    $device = $_POST['device_name'];
    $created_at = isset($_POST['created_at']) && !empty($_POST['created_at']) ? $_POST['created_at'] : null;

    // 2. QUERY: Insert data into the table
    // Using PDO prepared statements for security against SQL Injection
    if ($created_at) {
        $sql = "INSERT INTO sensor_data (temperature, humidity, device_name, created_at) VALUES (:temperature, :humidity, :device_name, :created_at)";
    } else {
        $sql = "INSERT INTO sensor_data (temperature, humidity, device_name) VALUES (:temperature, :humidity, :device_name)";
    }

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':temperature', $temp);
        $stmt->bindParam(':humidity', $humi);
        $stmt->bindParam(':device_name', $device);
        if ($created_at) {
            $stmt->bindParam(':created_at', $created_at);
        }
        
        $stmt->execute();
        echo "SUCCESS! Data inserted successfully into the database.";
    } catch (PDOException $e) {
        echo "DATABASE ERROR: " . $e->getMessage();
    }

} else {
    echo "INCOMPLETE DATA: Ensure the ESP32 or Simulator is sending temperature, humidity, and device_name variables.";
}
?>