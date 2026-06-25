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

require_once __DIR__ . '/config/database.php';

$sql_file = __DIR__ . '/sacliconnect.sql';
if (!file_exists($sql_file)) {
    die("sacliconnect.sql not found.");
}

$sql = file_get_contents($sql_file);

// Split into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    fn($s) => !empty($s) && !preg_match('/^(--|\/\*|SET SQL_MODE|START TRANSACTION|COMMIT|\/\*!)/i', $s)
);

$success = 0;
$errors  = [];

foreach ($statements as $statement) {
    if (empty(trim($statement))) continue;
    if ($conn->query($statement)) {
        $success++;
    } else {
        $errors[] = $conn->error . "<br><small>" . htmlspecialchars(substr($statement, 0, 100)) . "</small>";
    }
}

echo "<style>body{font-family:monospace;background:#0a1f16;color:#00ffaa;padding:30px;}</style>";
echo "<h2>Database Import</h2>";
echo "<p>✅ Statements executed: $success</p>";

if (!empty($errors)) {
    echo "<h3 style='color:#ff4757'>Errors (" . count($errors) . "):</h3>";
    foreach ($errors as $e) echo "<p style='color:#ff4757'>❌ $e</p>";
} else {
    echo "<p>✅ All done! No errors.</p>";
}

echo "<p><a href='SacliConnect_LOG_IN.php' style='color:#00ffaa'>→ Go to Login</a></p>";
echo "<p style='color:#ff4757'>⚠️ Delete import_db.php now!</p>";
