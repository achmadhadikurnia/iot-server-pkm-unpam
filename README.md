# IoT Sensor Server (PKM UNPAM)

A lightweight, secure, and procedural PHP web server designed to act as an endpoint for IoT devices (like ESP32/ESP8266 or Arduino). It receives real-time environment data, typically Temperature and Humidity, and securely records it into a Supabase PostgreSQL database. 

This project was built for the **PKM (Pengabdian Kepada Masyarakat)** program by students of **Universitas Pamulang (UNPAM)**.

## ✨ Features

- **Built-in Web Simulator**: Test API endpoints seamlessly without needing physical hardware connected.
- **Visual Data Dashboard**: A premium Bootstrap 5-based dashboard that supports pagination to view the captured sensor records effortlessly.
- **Secure Supabase Postgres Connect**: Fully migrated to modern `PDO pgsql` database connection configurations with Prepared Statements designed against SQL injection.
- **Automated Visual Setup**: Ships with a Setup Wizard (`setup.php`) to test environment configurations and auto-run database table migrations.
- **High Security Standard**: Implements `.env` configurations and Apache `.htaccess` boundary checks, keeping sensitive endpoints entirely secure.

## 🚀 Quick Start

### 1. Requirements
* PHP 8.x
* PHP Extensions enabled: `pdo_pgsql`, `pgsql`
* Web Server (Apache natively recommended for `.htaccess`)
* A [Supabase](https://supabase.com/) Account & Project Database

### 2. Setup Procedure
1. **Clone the repository** to your local web server root (or upload to cPanel).
2. **Setup Credentials**: Copy or rename `.env.example` to `.env`. Fill in your Supabase database access variables.
3. Open `http://your-domain/setup.php` in your browser.
4. If your `.env` connection is configured properly and successfully showing a **Green** status, click the **"Run Database Migration Now"** button. This will automatically create the `sensor_data` table.

## 📡 Hardware Integration (ESP32 / Arduino C++)
Make a standard HTTP POST request natively from your microprocessor pointing to: 
`http://your-domain.com/insert-sensor.php`

**Expected Payload (POST/x-www-form-urlencoded):**
- `temperature`: Float (e.g. `24.5`)
- `humidity`: Float (e.g. `60.0`)
- `device_name`: String (e.g. `ESP32_Lab_1`)

## 🛠 File Structure & Routing

- `index.php` : The Web Simulator entrypoint.
- `logs.php` : Paginated GUI to view inserted sensor records.
- `insert-sensor.php` : Secure webhook/API endpoint to ingest device payloads.
- `database.php` : Core connection loader (Protected).
- `setup.php` : Migration and Diagnostic system.
- `/.htaccess` : File security and restriction policies.

## 📜 License

This project is open-sourced software licensed under the [MIT License](LICENSE).
