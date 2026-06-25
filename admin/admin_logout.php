<?php
session_start();

if (isset($_SESSION['admin_username'])) {
    require_once __DIR__ . '/../config/database.php';
    if (!$conn->connect_error) {
        $admin_username = $_SESSION['admin_username'];
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Record the admin logout event
        $stmt = $conn->prepare("INSERT INTO login_history (student_id, device_info, ip_address, location) VALUES (?, 'LOGOUT', ?, 'N/A')");
        $stmt->bind_param("ss", $admin_username, $ip);
        $stmt->execute();
        $stmt->close();

        // Limit login history to 10 entries per user
        $limit = 9;
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM login_history WHERE student_id = ?");
        $count_stmt->bind_param("s", $admin_username);
        $count_stmt->execute();
        $count = $count_stmt->get_result()->fetch_assoc()['count'];
        $count_stmt->close();

        if ($count > $limit) {
            $num_to_delete = $count - $limit;
            $delete_stmt = $conn->prepare("DELETE FROM login_history WHERE student_id = ? ORDER BY login_time ASC LIMIT ?");
            $delete_stmt->bind_param("si", $admin_username, $num_to_delete);
            $delete_stmt->execute();
            $delete_stmt->close();
        }
        $conn->close();
    }
}

session_destroy();
header("Location: SacliConnect_LOG_IN.php?show=admin&no_intro=1");
exit();
?>
