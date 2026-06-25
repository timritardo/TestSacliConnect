<?php
/**
 * import_db.php - Full database reset and import
 * Access: https://your-domain/import_db.php?key=sacli_setup_2026
 * DELETE THIS FILE after running.
 */

$key = getenv('SETUP_KEY') ?: 'sacli_setup_2026';
if (!isset($_GET['key']) || $_GET['key'] !== $key) {
    die("Access denied.");
}

$host = getenv('MYSQLHOST')     ?: 'localhost';
$user = getenv('MYSQLUSER')     ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db   = getenv('MYSQLDATABASE') ?: 'sacliconnect';
$port = (int)(getenv('MYSQLPORT') ?: 3306);

$conn = new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8mb4");

echo "<style>body{font-family:monospace;background:#0a1f16;color:#00ffaa;padding:30px;line-height:1.8;}</style>";
echo "<h2>SacliConnect — Database Import</h2>";

// ── Step 1: Drop all existing tables ─────────────────────────────────────────
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$tables_res = $conn->query("SHOW TABLES");
$dropped = 0;
while ($row = $tables_res->fetch_row()) {
    $conn->query("DROP TABLE IF EXISTS `{$row[0]}`");
    $dropped++;
}
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
echo "<p>🗑️ Dropped $dropped existing tables.</p>";

// ── Step 2: Import the SQL dump ───────────────────────────────────────────────
$sql_file = __DIR__ . '/sacliconnect.sql';
if (!file_exists($sql_file)) die("<p style='color:red'>sacliconnect.sql not found.</p>");

$sql = file_get_contents($sql_file);

mysqli_report(MYSQLI_REPORT_OFF);

if ($conn->multi_query($sql)) {
    $count = 0;
    $errors = [];
    do {
        $count++;
        if ($result = $conn->store_result()) {
            $result->free();
        }
        if ($conn->errno) {
            $errors[] = "Query $count: " . $conn->error;
        }
    } while ($conn->more_results() && $conn->next_result());

    echo "<p>✅ Queries processed: $count</p>";

    if (!empty($errors)) {
        echo "<h3 style='color:#ffaa00'>Warnings:</h3>";
        foreach ($errors as $e) echo "<p style='color:#ffaa00'>⚠️ $e</p>";
    } else {
        echo "<p>✅ Import complete with no errors!</p>";
    }
} else {
    echo "<p style='color:#ff4757'>❌ Import failed: " . $conn->error . "</p>";
}

echo "<p><a href='SacliConnect_LOG_IN.php' style='color:#00ffaa'>→ Go to Login Page</a></p>";
echo "<p style='color:#ff4757'>⚠️ Delete import_db.php from your server now!</p>";
