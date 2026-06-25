<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed");

// Security: Only Admin can access this hidden control page
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin' || $_SESSION['admin_username'] !== 'Princess') {
    header("Location: ../SacliConnect_LOG_IN.php");
    exit();
}

// Ensure setting exists in database
$conn->query("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES ('blackout_mode', '0')");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_blackout'])) {
    $res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='blackout_mode'");
    $current = $res->fetch_assoc()['setting_value'];
    $new_status = ($current === '1') ? '0' : '1';
    $conn->query("UPDATE site_settings SET setting_value='$new_status' WHERE setting_key='blackout_mode'");
}

$status_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='blackout_mode'");
$status = $status_res->fetch_assoc()['setting_value'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SACLICONNECT // PROTOCOL_BLACKOUT</title>
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <style>
        body { background: #020806; color: #00ffaa; font-family: 'Courier New', monospace; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .control-panel { border: 1px solid #00ffaa; padding: 50px; background: rgba(0, 255, 170, 0.05); box-shadow: 0 0 30px rgba(0, 255, 170, 0.1); border-radius: 10px; text-align: center; }
        h1 { font-size: 24px; letter-spacing: 5px; margin-bottom: 30px; }
        .status-indicator { margin-bottom: 30px; padding: 10px; border-radius: 5px; font-weight: bold; }
        .status-active { background: rgba(255, 71, 87, 0.2); color: #ff4757; border: 1px solid #ff4757; }
        .status-inactive { background: rgba(0, 255, 170, 0.2); color: #00ffaa; border: 1px solid #00ffaa; }
        button { background: transparent; border: 2px solid #00ffaa; color: #00ffaa; padding: 15px 40px; cursor: pointer; font-family: inherit; font-size: 16px; font-weight: 900; transition: 0.3s; letter-spacing: 2px; }
        button:hover { background: #00ffaa; color: #020806; box-shadow: 0 0 30px #00ffaa; }
        .btn-danger:hover { background: #ff4757; border-color: #ff4757; box-shadow: 0 0 30px #ff4757; }
    </style>
</head>
<body>
    <div class="control-panel">
        <h1>PROTOCOL_SACLISACLI</h1>
        <div class="status-indicator <?php echo ($status === '1') ? 'status-active' : 'status-inactive'; ?>">
            SYSTEM_UI_STATUS: <?php echo ($status === '1') ? "BLACKOUT_ENABLED" : "OPERATIONAL"; ?>
        </div>
        <form method="POST">
            <button type="submit" name="toggle_blackout" class="<?php echo ($status === '0') ? 'btn-danger' : ''; ?>">
                <?php echo ($status === '1') ? "RESTORE_SYSTEM_UI" : "INITIATE_TOTAL_BLACKOUT"; ?>
            </button>
        </form>
        <p style="margin-top:30px; font-size: 10px; opacity: 0.4;">WARNING: This command overrides all interface styles globally across the network node.</p>
    </div>
</body>
</html>