<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if(isset($_GET['q'])){
    $q = "%".$_GET['q']."%";
    $data = [];

    // Search Students
    $stmt = $conn->prepare("SELECT student_id, student_name, profile_pic FROM students WHERE student_name LIKE ? LIMIT 5");
    $stmt->bind_param("s", $q);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()){
        $data[] = $row;
    }

    // Search Teachers
    $stmt = $conn->prepare("SELECT id, name as student_name, profile_pic FROM teachers WHERE name LIKE ? LIMIT 5");
    $stmt->bind_param("s", $q);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()){
        $row['student_id'] = "T-" . $row['id']; // Format ID for teachers
        $data[] = $row;
    }

    echo json_encode($data);
}
?>