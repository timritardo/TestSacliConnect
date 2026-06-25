<?php
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

header('Content-Type: application/json');

if (isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];

    $stmt = $conn->prepare("SELECT id, name FROM alumni WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Check kung ang account ay registered na sa students table
        $stmt_check = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
        $stmt_check->bind_param("s", $student_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'already_registered']);
        } else {
            $formatted_name = ucwords(strtolower($row['name']));
            echo json_encode(['status' => 'exists', 'name' => $formatted_name]);
        }
        $stmt_check->close();
    } else {
        echo json_encode(['status' => 'not_exists']);
    }
}
?>