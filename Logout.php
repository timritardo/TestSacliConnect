<?php
session_start();

if (isset($_SESSION['student_id'])) {
    require_once __DIR__ . '/config/database.php';
    if (!$conn->connect_error) {
        $student_id = $_SESSION['student_id'];
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Set user offline
        if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'teacher') {
            $real_id = str_replace("T-", "", $student_id);
            $conn->query("UPDATE teachers SET is_online = 0 WHERE id = '$real_id'");
        } else {
            $conn->query("UPDATE students SET is_online = 0 WHERE student_id = '$student_id'");
        }
        
        // Record the logout event
        $stmt = $conn->prepare("INSERT INTO login_history (student_id, device_info, ip_address, location) VALUES (?, 'LOGOUT', ?, 'N/A')");
        $stmt->bind_param("ss", $student_id, $ip);
        $stmt->execute();
        $stmt->close();

        // Limit login history to 10 entries per user
        $limit = 9;
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM login_history WHERE student_id = ?");
        $count_stmt->bind_param("s", $student_id);
        $count_stmt->execute();
        $count = $count_stmt->get_result()->fetch_assoc()['count'];
        $count_stmt->close();

        if ($count > $limit) {
            $num_to_delete = $count - $limit;
            $delete_stmt = $conn->prepare("DELETE FROM login_history WHERE student_id = ? ORDER BY login_time ASC LIMIT ?");
            $delete_stmt->bind_param("si", $student_id, $num_to_delete);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
        $conn->close();
    }
}

session_destroy(); // clear session
header("Location: SacliConnect_LOG_IN.php?no_intro=1"); // redirect to login without animation
exit();
