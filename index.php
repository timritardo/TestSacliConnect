<?php
// Temporary diagnostic - remove after fixing
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>PHP is working</h2>";
echo "<p>PHP version: " . phpversion() . "</p>";
echo "<p>Vendor autoload exists: " . (file_exists(__DIR__ . '/vendor/autoload.php') ? 'YES' : 'NO') . "</p>";
echo "<p>Config db exists: " . (file_exists(__DIR__ . '/config/database.php') ? 'YES' : 'NO') . "</p>";

// Test DB connection
$host = getenv('MYSQLHOST');
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$db   = getenv('MYSQLDATABASE');
$port = getenv('MYSQLPORT') ?: 3306;

echo "<p>DB Host from env: " . ($host ?: 'NOT SET') . "</p>";

$conn = @new mysqli($host, $user, $pass, $db, $port);
echo "<p>DB connection: " . ($conn->connect_error ? 'FAILED - ' . $conn->connect_error : 'OK') . "</p>";

