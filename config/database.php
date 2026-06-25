<?php
/**
 * config/database.php
 * Single database connection file for the entire project.
 * Include this file with: require_once __DIR__ . '/../config/database.php';
 * Or from a file already in root: require_once 'config/database.php';
 */

// On Railway these env vars are set automatically by the MySQL plugin.
// Locally (XAMPP) they fall back to your local credentials.
define('DB_HOST', getenv('MYSQLHOST')     ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER')     ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'sacliconnect');
define('DB_PORT', (int)(getenv('MYSQLPORT') ?: 3306));

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Manila');
