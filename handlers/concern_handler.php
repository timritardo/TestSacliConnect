<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['student_id'])) {
    die("Not logged in");
}
$my_id = $_SESSION['student_id'];

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS admin_concerns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    sender_type ENUM('student', 'admin') NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    INDEX(student_id)
)");

if (isset($_POST['action'])) {
    
    // Student sends a message
    if ($_POST['action'] == 'send') {
        $msg = trim($_POST['message']);
        if (!empty($msg)) {
            $stmt = $conn->prepare("INSERT INTO admin_concerns (student_id, message, sender_type) VALUES (?, ?, 'student')");
            $stmt->bind_param("ss", $my_id, $msg);
            $stmt->execute();
        }
    }

    // Delete Concern Message
    if ($_POST['action'] == 'delete') {
        $id = $_POST['msg_id'];
        // Ensure ownership (sender_type='student')
        $stmt = $conn->prepare("DELETE FROM admin_concerns WHERE id=? AND student_id=? AND sender_type='student'");
        $stmt->bind_param("is", $id, $my_id);
        $stmt->execute();
    }

    // Edit Concern Message
    if ($_POST['action'] == 'edit') {
        $id = $_POST['msg_id'];
        $new_msg = trim($_POST['message']);
        if (!empty($new_msg)) {
            $stmt = $conn->prepare("UPDATE admin_concerns SET message=? WHERE id=? AND student_id=? AND sender_type='student'");
            $stmt->bind_param("sis", $new_msg, $id, $my_id);
            $stmt->execute();
        }
    }

    // Fetch conversation for the logged-in student
    if ($_POST['action'] == 'fetch') {
        // Mark messages from admin as read
        $conn->query("UPDATE admin_concerns SET is_read = 1 WHERE student_id = '$my_id' AND sender_type = 'admin'");

        $stmt = $conn->prepare("SELECT * FROM admin_concerns WHERE student_id = ? ORDER BY timestamp ASC");
        $stmt->bind_param("s", $my_id);
        $stmt->execute();
        $res = $stmt->get_result();

        // Initial message
        echo "<div class='msg other-msg'>Hello! This is the admin support channel. How can we help you?</div>";

        while ($row = $res->fetch_assoc()) {
            $class = ($row['sender_type'] == 'student') ? 'my-msg' : 'other-msg';
            $controls = "";
            if ($row['sender_type'] == 'student') {
                $js_msg = htmlspecialchars(json_encode($row['message']), ENT_QUOTES, 'UTF-8');
                $controls = "<div class='msg-controls'>
                    <span onclick='editConcernMsg(" . $row['id'] . ", $js_msg)'>✎</span>
                    <span onclick='deleteConcernMsg(" . $row['id'] . ")'>🗑</span>
                </div>";
            }
            echo "<div class='msg $class'>" . htmlspecialchars($row['message']) . $controls . "</div>";
        }
    }

    // Check for unread messages from admin
    if ($_POST['action'] == 'check_unread') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_concerns WHERE student_id = ? AND sender_type = 'admin' AND is_read = 0");
        $stmt->bind_param("s", $my_id);
        $stmt->execute();
        $res = $stmt->get_result();
        echo $res->fetch_assoc()['count'];
    }
}
?>