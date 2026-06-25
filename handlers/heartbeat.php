<?php
session_start();
if (isset($_SESSION['student_id'])) {
    $my_id = $_SESSION['student_id'];

    // Kung admin ang naka-login (POV mode) o ang ID ay "Admin", bypass verification
    if ((isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') || $my_id === 'Admin') {
        echo "ok";
        exit;
    }

    require_once __DIR__ . '/../config/database.php';
    if (!$conn->connect_error) {
        $user_type = $_SESSION['user_type'] ?? 'student';

        $table = ($user_type === 'teacher') ? 'teachers' : 'students';
        $id_col = ($user_type === 'teacher') ? 'id' : 'student_id';
        $real_id = ($user_type === 'teacher') ? str_replace('T-', '', $my_id) : $my_id;

        // Check for force logout
        $stmt = $conn->prepare("SELECT force_logout FROM $table WHERE $id_col = ?");
        $stmt->bind_param("s", $real_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if($res && $res['force_logout'] == 1) {
            echo "logout";
            exit;
        }
        $stmt->close();

        $stmt = $conn->prepare("UPDATE $table SET last_activity = NOW(), is_online = 1 WHERE $id_col = ?");
        $stmt->bind_param("s", $real_id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        echo "ok";
    }
}
?>