<?php
/**
 * import_db.php
 * Imports sacliconnect.sql into the Railway database.
 * Access: https://your-domain/import_db.php?key=sacli_setup_2026
 * DELETE THIS FILE after running.
 */

$key = getenv('SETUP_KEY') ?: 'sacli_setup_2026';
if (!isset($_GET['key']) || $_GET['key'] !== $key) {
    die("Access denied.");
}

// Connect without selecting a database first
$host = getenv('MYSQLHOST')     ?: 'localhost';
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db   = getenv('MYSQLDATABASE') ?: 'sacliconnect';
$port = (int)(getenv('MYSQLPORT') ?: 3306);

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

// Disable strict error reporting so duplicate table errors are skipped
$conn->query("SET FOREIGN_KEY_CHECKS=0");
mysqli_report(MYSQLI_REPORT_OFF);

$sql_file = __DIR__ . '/sacliconnect.sql';
if (!file_exists($sql_file)) die("sacliconnect.sql not found.");

$sql = file_get_contents($sql_file);

// Strip comments and problematic SET statements that cause issues
$sql = preg_replace('/^--.*$/m', '', $sql);
$sql = preg_replace('/^\/\*.*?\*\/;/ms', '', $sql);

echo "<style>body{font-family:monospace;background:#0a1f16;color:#00ffaa;padding:30px;line-height:1.8;}</style>";
echo "<h2>SacliConnect — Database Import</h2>";

// Use multi_query to handle the full SQL dump correctly
if ($conn->multi_query($sql)) {
    $count = 0;
    do {
        $count++;
        if ($result = $conn->store_result()) {
            $result->free();
        }
        if ($conn->errno && $conn->errno != 1050) {
            // 1050 = table already exists, skip it
            echo "<p style='color:#ffaa00'>⚠️ Skipped (query $count): " . $conn->error . "</p>";
        }
    } while ($conn->more_results() && @$conn->next_result());

    if ($conn->errno) {
        echo "<p style='color:#ff4757'>❌ Error at query $count: " . $conn->error . "</p>";
    } else {
        echo "<p>✅ Import complete! Queries run: $count</p>";
        echo "<p><a href='SacliConnect_LOG_IN.php' style='color:#00ffaa'>→ Go to Login Page</a></p>";
        echo "<p style='color:#ff4757'>⚠️ Delete import_db.php from your server now!</p>";
    }
} else {
    echo "<p style='color:#ff4757'>❌ Failed to start import: " . $conn->error . "</p>";
}
