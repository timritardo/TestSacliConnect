<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$message = "";
$msg_type = "error";

if (isset($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);

    // 1. Find the pending change request
    $stmt = $conn->prepare("SELECT * FROM pending_profile_changes WHERE verification_token = ? AND expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $change_request = $result->fetch_assoc();
        $user_id = $change_request['user_id'];
        $change_type = $change_request['change_type'];
        $new_value = $change_request['new_value'];

        // Determine if user is student or teacher
        $is_teacher = (strpos($user_id, 'T-') === 0);
        $table = $is_teacher ? 'teachers' : 'students';
        $id_col = $is_teacher ? 'id' : 'student_id';
        $real_id = $is_teacher ? substr($user_id, 2) : $user_id;

        // 2. Update the user's profile
        $update_field = ($change_type === 'email') ? 'email' : 'phone';
        $update_stmt = $conn->prepare("UPDATE $table SET $update_field = ? WHERE $id_col = ?");
        $update_stmt->bind_param("ss", $new_value, $real_id);

        if ($update_stmt->execute()) {
            // 3. Delete the pending request to prevent reuse
            $conn->query("DELETE FROM pending_profile_changes WHERE verification_token = '$token'");
            $message = "Your " . $change_type . " has been successfully updated!";
            $msg_type = "success";
        } else {
            $message = "Database error during update: " . $conn->error;
        }
        $update_stmt->close();
    } else {
        $message = "Invalid or expired verification link.";
    }
    $stmt->close();
} else {
    $message = "No verification token provided.";
}

$conn->close();

// Redirect back to profile page with status message
$redirect_url = "Student_Profile.php?status_message=" . urlencode($message) . "&status_type=" . urlencode($msg_type);
header("Location: " . $redirect_url);
exit();
?>