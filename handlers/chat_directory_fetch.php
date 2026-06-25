<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed");

if (!isset($_SESSION['student_id'])) exit();

$type = $_GET['type'] ?? 'student'; // student, teacher, alumni
$search = $_GET['search'] ?? '';
$my_id = $_SESSION['student_id'];

$users = [];

if ($type === 'student' || $type === 'alumni') {
    $is_alumni = ($type === 'alumni') ? 1 : 0;
    $sql = "SELECT student_id as id, student_name as name, profile_pic, is_online, 'direct' as chat_type 
            FROM students 
            WHERE is_alumni = $is_alumni AND student_id != '$my_id'";
    if (!empty($search)) {
        $sql .= " AND (student_name LIKE '%$search%' OR student_id LIKE '%$search%')";
    }
    // Sort: Online users (1) first, then alphabetical name
    $sql .= " ORDER BY is_online DESC, student_name ASC";
} elseif ($type === 'teacher') {
    $sql = "SELECT CONCAT('T-', id) as id, name, profile_pic, is_online, 'direct' as chat_type 
            FROM teachers 
            WHERE CONCAT('T-', id) != '$my_id'";
    if (!empty($search)) {
        $sql .= " AND (name LIKE '%$search%' OR email LIKE '%$search%')";
    }
    $sql .= " ORDER BY is_online DESC, name ASC";
}

$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) { $users[] = $row; }
}
header('Content-Type: application/json');
echo json_encode($users);