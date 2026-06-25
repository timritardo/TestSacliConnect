<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

if (empty($_SESSION['admin_username'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student_id') {
    $student_id = $_POST['student_id'];

    // Fetch student info to check if claimed and for cleanup
    $stmt_check = $conn->prepare("SELECT student_name, profile_pic, password FROM students WHERE student_id = ?");
    $stmt_check->bind_param("s", $student_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $student_data = $result_check->fetch_assoc();
        $student_name = $student_data['student_name'];
        $profile_pic = $student_data['profile_pic'];
        $is_claimed = !empty($student_data['password']);

        // Start transaction for data integrity
        $conn->begin_transaction();
        try {
            // 1. Delete profile picture file if exists
            if (!empty($profile_pic) && file_exists("uploads/" . $profile_pic)) {
                unlink("uploads/" . $profile_pic);
            }

            // 2. Delete posts and related media/interactions by this student
            $posts_query = $conn->query("SELECT id FROM posts WHERE student_name = '$student_name'");
            if ($posts_query) {
                while ($post = $posts_query->fetch_assoc()) {
                    $post_id = $post['id'];
                    // Delete post media files
                    $media_query = $conn->query("SELECT file_path FROM post_media WHERE post_id = '$post_id'");
                    if ($media_query) {
                        while ($media = $media_query->fetch_assoc()) {
                            if (!empty($media['file_path']) && file_exists($media['file_path'])) {
                                unlink($media['file_path']);
                            }
                        }
                    }
                    $conn->query("DELETE FROM post_media WHERE post_id = '$post_id'");
                    $conn->query("DELETE FROM post_comments WHERE post_id = '$post_id'");
                    $conn->query("DELETE FROM post_reactions WHERE post_id = '$post_id'");
                    $conn->query("DELETE FROM notifications WHERE post_id = '$post_id'");
                    $conn->query("DELETE FROM post_tags WHERE post_id = '$post_id'");
                }
            }
            $conn->query("DELETE FROM posts WHERE student_name = '$student_name'");

            // 3. Delete other interactions (comments, reactions, messages, notifications, etc.)
            $conn->query("DELETE FROM post_comments WHERE student_id = '$student_id'");
            $conn->query("DELETE FROM post_reactions WHERE student_id = '$student_id'");
            $conn->query("DELETE FROM notifications WHERE user_id = '$student_id' OR actor_id = '$student_id'");
            $conn->query("DELETE FROM direct_messages WHERE sender_id = '$student_id' OR receiver_id = '$student_id'");
            $conn->query("DELETE FROM group_chat_members WHERE user_id = '$student_id'");     // Auto-fix: Siguraduhing pati messages sa Group Chat ay malinis
            $conn->query("DELETE FROM group_chat_messages WHERE sender_id = '$student_id'");
            $conn->query("DELETE FROM login_history WHERE student_id = '$student_id'");
            $conn->query("DELETE FROM post_tags WHERE student_id = '$student_id'");
            $conn->query("DELETE FROM evaluations WHERE student_id = '$student_id'");
            $conn->query("DELETE FROM sacli_room_members WHERE student_id = '$student_id'");
            $conn->query("DELETE FROM sacli_meeting_logs WHERE student_id = '$student_id'");
            $conn->query("DELETE FROM security_audit_logs WHERE user_id = '$student_id'");
            $conn->query("DELETE FROM pending_profile_changes WHERE user_id = '$student_id'");
            
            // 4. Finally, delete the student record itself
            $stmt_delete = $conn->prepare("DELETE FROM students WHERE student_id = ?");
            $stmt_delete->bind_param("s", $student_id);
            $stmt_delete->execute();
            $stmt_delete->close();

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Student ID and all associated data deleted successfully.']);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete student ID and associated data: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Student ID not found.']);
    }
    $stmt_check->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
}

$conn->close();
?>