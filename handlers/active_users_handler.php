<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) {
    http_response_code(500);
    die("Connection failed");
}

header('Content-Type: application/json');

$active_users = [];
// Get online students
$students_res = $conn->query("SELECT student_id FROM students WHERE is_online = 1");
if ($students_res) {
    while ($row = $students_res->fetch_assoc()) {
        $active_users[] = $row['student_id'];
    }
}

// Get online teachers
$teachers_res = $conn->query("SELECT id FROM teachers WHERE is_online = 1");
if ($teachers_res) {
    while ($row = $teachers_res->fetch_assoc()) {
        $active_users[] = "T-" . $row['id'];
    }
}

echo json_encode($active_users);
$conn->close();
?>