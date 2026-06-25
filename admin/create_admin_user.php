<?php
// Run this file once to create an admin user correctly
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Create table if not exists
$sql = "CREATE TABLE IF NOT EXISTS admins2 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)";
$conn->query($sql);

// Insert admin user
$username = "Princess";
$password = "092025"; // Ang bagong password na nirequest
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Gamit ang ON DUPLICATE KEY UPDATE para ma-reset ang password kahit existing na ang user
$stmt = $conn->prepare("INSERT INTO admins2 (username, password) VALUES (?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password)");
$stmt->bind_param("ss", $username, $hashed_password);

if ($stmt->execute()) {
    echo "Admins2 user updated successfully! <br>Username: Princess<br>Password: 092025<br><br><a href='SacliConnect_LOG_IN.php?show=admin'>Go to Login</a>";
} else {
    echo "Error (baka meron na nyan): " . $stmt->error;
}
?>