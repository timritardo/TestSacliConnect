<?php
session_start();
header('Content-Type: application/json');

// Check if the request exceeds the server's post_max_size.
// This is a common cause for silent failures on large file uploads.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $max_size = ini_get('post_max_size');
    echo json_encode(['status' => 'error', 'message' => "File upload failed. The total size of your post exceeds the server limit of {$max_size}. Please upload smaller files."]);
    exit();
}

// Use a try-catch for the database connection to handle connection errors gracefully.
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Enable exceptions for mysqli
    require_once __DIR__ . '/../config/database.php';
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed. Please check server configuration.']);
    exit();
}
mysqli_report(MYSQLI_REPORT_OFF); // Disable exceptions for the rest of the script to use procedural checks

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']);
    exit();
}

$my_id = $_SESSION['student_id'];
$user_type = $_SESSION['user_type'] ?? 'student';

// --- PHPMailer Helper for Room Notifs ---
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

function sendRoomEmail($to_email, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sacliconnect20@gmail.com';
        $mail->Password   = 'umrrmsyujepjopbo'; 
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->Timeout    = 10;
        $mail->SMTPOptions = array('ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true));
        $mail->setFrom('sacliconnect20@gmail.com', 'Sacli Connect (SacliRoom Alerts)');
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = "
            <div style='font-family: Arial; padding: 20px; border: 1px solid #00ffaa; background-color: #0a1f16; color: #fff; border-radius: 10px;'>
                <h2 style='color: #12dd12;'>SacliRoom Update</h2>
                <p style='font-size: 16px;'>$body</p>
                <hr style='border: 0.5px solid #3fe710;'>
                <p style='font-size: 12px;'>Neural Link Active. Check your classroom for details.</p>
            </div>";
        $mail->send();
    } catch (Exception $e) {}
}

if (isset($_POST['action'])) {
    // Teacher creates a room
    if ($_POST['action'] == 'create_room' && $user_type === 'teacher') {
        $name = trim($_POST['name']);
        $code = trim(strtoupper($_POST['code']));
        $desc = trim($_POST['description']);
        
        if (empty($name)) {
            echo json_encode(['status' => 'error', 'message' => 'Room name is required.']);
            exit();
        }
        if (empty($code)) {
            echo json_encode(['status' => 'error', 'message' => 'Room code is required.']);
            exit();
        }
        if (preg_match('/\s/', $code)) {
            echo json_encode(['status' => 'error', 'message' => 'Room code cannot contain spaces.']);
            exit();
        }

        // Check if code is unique
        $check_stmt = $conn->prepare("SELECT id FROM sacli_rooms WHERE room_code = ?");
        $check_stmt->bind_param("s", $code);
        if ($check_stmt === false) {
            echo json_encode(['status' => 'error', 'message' => 'Database error (check code).']);
            exit();
        }
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'This room code is already taken. Please choose another.']);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO sacli_rooms (teacher_id, name, description, room_code) VALUES (?, ?, ?, ?)");
        if ($stmt === false) {
            echo json_encode(['status' => 'error', 'message' => 'Database error (create room).']);
            exit();
        }
        $stmt->bind_param("ssss", $my_id, $name, $desc, $code);
        
        if ($stmt->execute()) {
            $room_id = $stmt->insert_id;
            // Automatically add the teacher to the room members
            $conn->query("INSERT INTO sacli_room_members (room_id, student_id, role) VALUES ('$room_id', '$my_id', 'teacher')");
            echo json_encode(['status' => 'success', 'message' => 'Room created successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create room.']);
        }
        exit();
    }

    // Teacher deletes a room
    if ($_POST['action'] == 'delete_room' && $user_type === 'teacher') {
        $room_id = (int)$_POST['room_id'];

        // Security check: ensure the user is the teacher of this room
        $check_stmt = $conn->prepare("SELECT id FROM sacli_rooms WHERE id = ? AND teacher_id = ?");
        if ($check_stmt === false) {
            echo json_encode(['status' => 'error', 'message' => 'Database error (auth).']);
            exit();
        }
        $check_stmt->bind_param("is", $room_id, $my_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Authorization error. You are not the creator of this room.']);
            exit();
        }

        // Delete the room and its members
        $conn->query("DELETE FROM sacli_rooms WHERE id = $room_id");
        $conn->query("DELETE FROM sacli_room_members WHERE room_id = $room_id");

        echo json_encode(['status' => 'success', 'message' => 'Room deleted successfully.']);
        exit();
    }

    // Teacher removes a student from a room
    if ($_POST['action'] == 'remove_student' && $user_type === 'teacher') {
        $student_id_to_remove = $_POST['student_id'];
        $room_id = (int)$_POST['room_id'];

        // Security check: ensure the current user is the teacher of this room
        $check_stmt = $conn->prepare("SELECT id FROM sacli_rooms WHERE id = ? AND teacher_id = ?");
        if ($check_stmt === false) {
            echo json_encode(['status' => 'error', 'message' => 'Database error (auth).']);
            exit();
        }
        $check_stmt->bind_param("is", $room_id, $my_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Authorization error. You are not the creator of this room.']);
            exit();
        }
        $check_stmt->close();

        // Prevent teacher from removing themselves
        if ($student_id_to_remove === $my_id) {
            echo json_encode(['status' => 'error', 'message' => 'You cannot remove yourself. Delete the room instead.']);
            exit();
        }

        // Proceed with removal
        $remove_stmt = $conn->prepare("DELETE FROM sacli_room_members WHERE room_id = ? AND student_id = ? AND role = 'student'");
        if ($remove_stmt === false) {
            echo json_encode(['status' => 'error', 'message' => 'Database error (remove student).']);
            exit();
        }
        $remove_stmt->bind_param("is", $room_id, $student_id_to_remove);
        if ($remove_stmt->execute()) {
            if ($remove_stmt->affected_rows > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Student removed successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Student not found in this room.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to remove student.']);
        }
        exit();
    }

    // Member leaves a room
    if ($_POST['action'] == 'leave_room') {
        $room_id = (int)$_POST['room_id'];

        // Security check: Creator cannot leave, they must delete.
        $check_stmt = $conn->prepare("SELECT teacher_id FROM sacli_rooms WHERE id = ?");
        if ($check_stmt === false) {
            echo json_encode(['status' => 'error', 'message' => 'Database error (check creator).']);
            exit();
        }
        $check_stmt->bind_param("i", $room_id);
        $check_stmt->execute();
        $room = $check_stmt->get_result()->fetch_assoc();
        $teacher_id = $room['teacher_id'];

        if ($room && $room['teacher_id'] === $my_id) {
            echo json_encode(['status' => 'error', 'message' => 'As the creator, you cannot leave the room. You must delete it instead.']);
            exit();
        }

        // Proceed to leave
        $leave_stmt = $conn->prepare("DELETE FROM sacli_room_members WHERE room_id = ? AND student_id = ?");
        if ($leave_stmt === false) {
            echo json_encode(['status' => 'error', 'message' => 'Database error (leave room).']);
            exit();
        }
        $leave_stmt->bind_param("is", $room_id, $my_id);
        if ($leave_stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'You have left the room.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to leave the room.']);
        }
        exit();
    }

    // Student joins a room
    if ($_POST['action'] == 'join_room') {
        $code = trim(strtoupper($_POST['code']));
        if (empty($code)) {
            echo json_encode(['status' => 'error', 'message' => 'Please enter a room code.']);
            exit();
        }

        $stmt = $conn->prepare("SELECT id, teacher_id FROM sacli_rooms WHERE room_code = ?");
        if ($stmt === false) {
            echo json_encode(['status' => 'error', 'message' => 'Database error (find room).']);
            exit();
        }
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $room = $res->fetch_assoc();
            $room_id = $room['id'];
            $teacher_id = $room['teacher_id'];

            // Check if already a member
            $check_stmt = $conn->prepare("SELECT id FROM sacli_room_members WHERE room_id = ? AND student_id = ?");
            if ($check_stmt === false) {
                echo json_encode(['status' => 'error', 'message' => 'Database error (check member).']);
                exit();
            }
            $check_stmt->bind_param("is", $room_id, $my_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                echo json_encode(['status' => 'error', 'message' => 'You are already in this room.']);
                exit();
            }

            // Add student to room
            $insert_stmt = $conn->prepare("INSERT INTO sacli_room_members (room_id, student_id, role) VALUES (?, ?, 'student')");
            if ($insert_stmt === false) {
                echo json_encode(['status' => 'error', 'message' => 'Database error (join room).']);
                exit();
            }
            $insert_stmt->bind_param("is", $room_id, $my_id);
            if ($insert_stmt->execute()) {
                // Notify the teacher
                if ($teacher_id) {
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES (?, ?, 'room_join', ?)");
                    $notif_stmt->bind_param("ssi", $teacher_id, $my_id, $room_id);
                    $notif_stmt->execute();
                    
                    $t_data = $conn->query("SELECT email, name FROM teachers WHERE id='".str_replace("T-", "", $teacher_id)."'")->fetch_assoc();
                    if($t_data && !empty($t_data['email'])) {
                        sendRoomEmail($t_data['email'], "New Student in Room", "Hi {$t_data['name']}, <b>{$_SESSION['student_name']}</b> has just joined your room.");
                    }
                }
                echo json_encode(['status' => 'success', 'message' => 'Successfully joined the room!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to join the room.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid room code.']);
        }
        exit();
    }

    // Teacher creates a post/assignment in a room
    if ($_POST['action'] == 'create_room_post') {
        $room_id = (int)$_POST['room_id'];
        $title = trim($_POST['title']);
        $content = isset($_POST['content']) ? trim($_POST['content']) : ''; // Safely handle optional content
        $due_date = !empty($_POST['due_date']) ? str_replace('T', ' ', $_POST['due_date']) : null;

        // Use a transaction for atomicity
        $conn->begin_transaction();

        try {
            // Security check: ensure the user is the teacher of this room
            $check_stmt = $conn->prepare("SELECT id FROM sacli_room_members WHERE room_id = ? AND student_id = ? AND role = 'teacher'");
            if ($check_stmt === false) throw new Exception('DB error (auth): ' . $conn->error);
            $check_stmt->bind_param("is", $room_id, $my_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows === 0) {
                throw new Exception('Authorization error. You are not a teacher in this room.');
            }
            $check_stmt->close();

            if (empty($title)) {
                throw new Exception('Post title is required.');
            }

            // 1. Insert the main post
            $stmt = $conn->prepare("INSERT INTO sacli_room_posts (room_id, user_id, title, content, due_date) VALUES (?, ?, ?, ?, ?)");
            if ($stmt === false) throw new Exception("DB prepare error (posts): " . $conn->error);
            $stmt->bind_param("issss", $room_id, $my_id, $title, $content, $due_date);
            if (!$stmt->execute()) throw new Exception("DB execute error (posts): " . $stmt->error);
            $post_id = $stmt->insert_id;
            $stmt->close();

            // --- NOTIFY ALL STUDENTS IN ROOM ---
            $is_assignment = !empty($due_date);
            $room_name = $conn->query("SELECT name FROM sacli_rooms WHERE id=$room_id")->fetch_assoc()['name'];
            $members = $conn->query("SELECT student_id FROM sacli_room_members WHERE room_id = $room_id AND role = 'student'");
            while($m = $members->fetch_assoc()){
                $sid = $m['student_id'];
                $conn->query("INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES ('$sid', '$my_id', 'room_post', '$post_id')");
                
                $s_data = $conn->query("SELECT email, student_name FROM students WHERE student_id='$sid'")->fetch_assoc();
                if($s_data && !empty($s_data['email'])){
                    $msg_type = $is_assignment ? "new assignment" : "new material";
                    sendRoomEmail($s_data['email'], "Update in $room_name", "Hi {$s_data['student_name']}, your Teacher just posted a $msg_type: <b>$title</b> in <b>$room_name</b>.");
                }
            }

            // 2. Handle file attachments
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    if (!@mkdir($upload_dir, 0777, true)) throw new Exception("Server error: Could not create upload directory.");
                }
                if (!is_writable($upload_dir)) throw new Exception("Server error: Upload directory is not writable.");

                $attachment_stmt = $conn->prepare("INSERT INTO sacli_room_post_attachments (post_id, file_path, original_filename, file_type) VALUES (?, ?, ?, ?)");
                if ($attachment_stmt === false) throw new Exception("DB prepare error (attachments): " . $conn->error);

                foreach ($_FILES['attachments']['name'] as $key => $name) {
                    if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['attachments']['tmp_name'][$key];
                        $original_name = basename($name);
                        $file_type = $_FILES['attachments']['type'][$key];
                        
                        $new_filename = "roompost_" . $post_id . "_" . uniqid() . "_" . preg_replace('/[^A-Za-z0-9.\-]/', '_', $original_name);
                        $destination = $upload_dir . $new_filename;

                        if (!move_uploaded_file($tmp_name, $destination)) {
                            throw new Exception("Failed to move uploaded file: " . htmlspecialchars($original_name));
                        }
                        
                        $attachment_stmt->bind_param("isss", $post_id, $destination, $original_name, $file_type);
                        if (!$attachment_stmt->execute()) {
                            throw new Exception("DB execute error (attachments): " . $attachment_stmt->error);
                        }
                    } elseif ($_FILES['attachments']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                        throw new Exception("Error uploading '" . htmlspecialchars($name) . "'. It might be too large.");
                    }
                }
                $attachment_stmt->close();
            }

            // 3. If all successful, commit transaction
            $conn->commit();

            // 4. Send success response
            $from_page = $_POST['from_page'] ?? 'stream';
            $redirect_url = "SacliRoom_view.php?id=$room_id&page=$from_page";
            echo json_encode(['status' => 'success', 'message' => 'Post created successfully!', 'redirect_to' => $redirect_url]);

        } catch (Throwable $e) { // Catch all errors, not just Exceptions
            // If anything fails, roll back and send error
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit();
    }

    // Teacher deletes a post/assignment in a room
    if ($_POST['action'] == 'delete_room_post' && $user_type === 'teacher') {
        $post_id = (int)$_POST['post_id'];

        $conn->begin_transaction();
        try {
            // Security check: ensure the user is the teacher of the room this post belongs to
            $check_stmt = $conn->prepare("
                SELECT r.teacher_id 
                FROM sacli_room_posts p 
                JOIN sacli_rooms r ON p.room_id = r.id 
                WHERE p.id = ?
            ");
            if ($check_stmt === false) throw new Exception('DB error (auth): ' . $conn->error);
            $check_stmt->bind_param("i", $post_id);
            $check_stmt->execute();
            $room_info = $check_stmt->get_result()->fetch_assoc();

            if (!$room_info || $room_info['teacher_id'] !== $my_id) {
                throw new Exception('Authorization error. You are not the teacher of this room.');
            }
            $check_stmt->close();

            // 1. Delete attached files from server
            // Files from post attachments
            $att_stmt = $conn->prepare("SELECT file_path FROM sacli_room_post_attachments WHERE post_id = ?");
            $att_stmt->bind_param("i", $post_id);
            $att_stmt->execute();
            $attachments = $att_stmt->get_result();
            while ($att = $attachments->fetch_assoc()) {
                if (!empty($att['file_path']) && file_exists($att['file_path'])) @unlink($att['file_path']);
            }
            $att_stmt->close();

            // Files from student submissions
            $sub_stmt = $conn->prepare("SELECT file_path FROM sacli_room_submissions WHERE post_id = ?");
            $sub_stmt->bind_param("i", $post_id);
            $sub_stmt->execute();
            $submissions = $sub_stmt->get_result();
            while ($sub = $submissions->fetch_assoc()) {
                if (!empty($sub['file_path']) && file_exists($sub['file_path'])) @unlink($sub['file_path']);
            }
            $sub_stmt->close();

            // 2. Delete database records
            $conn->query("DELETE FROM sacli_room_post_attachments WHERE post_id = $post_id");
            $conn->query("DELETE FROM sacli_room_submissions WHERE post_id = $post_id");
            $conn->query("DELETE FROM sacli_room_posts WHERE id = $post_id");

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Post deleted successfully.']);
        } catch (Throwable $e) { $conn->rollback(); echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
        exit();
    }

    // Student submits an assignment
    if ($_POST['action'] == 'submit_assignment' && $user_type === 'student') {
        $post_id = (int)$_POST['post_id'];
        
        if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'error', 'message' => 'File upload error. Please select a file.']);
            exit();
        }

        $conn->begin_transaction();
        try {
            // 1. Check if student is a member of the room for this post
            $check_stmt = $conn->prepare("SELECT r.id FROM sacli_rooms r JOIN sacli_room_members m ON r.id = m.room_id WHERE r.id = (SELECT room_id FROM sacli_room_posts WHERE id = ?) AND m.student_id = ?");
            if ($check_stmt === false) throw new Exception('DB error (auth): ' . $conn->error);
            $check_stmt->bind_param("is", $post_id, $my_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows === 0) {
                throw new Exception('Authorization error. You are not a member of this room.');
            }
            $check_stmt->close();

            // 2. Handle file upload
            $upload_dir = 'uploads/submissions/';
            if (!is_dir($upload_dir)) {
                if (!@mkdir($upload_dir, 0777, true)) throw new Exception("Server error: Could not create submission directory.");
            }
            if (!is_writable($upload_dir)) throw new Exception("Server error: Submission directory is not writable.");

            $tmp_name = $_FILES['submission_file']['tmp_name'];
            $original_name = basename($_FILES['submission_file']['name']);
            
            $new_filename = "sub_" . $post_id . "_" . $my_id . "_" . uniqid() . "_" . preg_replace('/[^A-Za-z0-9.\-]/', '_', $original_name);
            $destination = $upload_dir . $new_filename;

            if (!move_uploaded_file($tmp_name, $destination)) {
                throw new Exception("Failed to move uploaded file: " . htmlspecialchars($original_name));
            }

            // 3. Insert submission record into the database (Use ON DUPLICATE KEY UPDATE to handle resubmissions)
            $stmt = $conn->prepare("INSERT INTO sacli_room_submissions (post_id, student_id, file_path) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), submitted_at = NOW(), grade = NULL");
            if ($stmt === false) throw new Exception("DB prepare error (submission): " . $conn->error);
            $stmt->bind_param("iss", $post_id, $my_id, $destination);
            if (!$stmt->execute()) throw new Exception("DB execute error (submission): " . $stmt->error);
            $stmt->close();

            // 4. Notify the teacher
            $teacher_q = $conn->prepare("
                SELECT r.teacher_id 
                FROM sacli_room_posts p 
                JOIN sacli_rooms r ON p.room_id = r.id 
                WHERE p.id = ?
            ");
            $teacher_q->bind_param("i", $post_id);
            $teacher_q->execute();
            if ($teacher_info = $teacher_q->get_result()->fetch_assoc()) {
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES (?, ?, 'room_submission', ?)");
                $notif_stmt->bind_param("ssi", $teacher_info['teacher_id'], $my_id, $post_id);
                $notif_stmt->execute();
            }
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Assignment submitted successfully!']);

        } catch (Throwable $e) {
            $conn->rollback();
            if (isset($destination) && file_exists($destination)) @unlink($destination);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit();
    }

    // Student unsubmits an assignment
    if ($_POST['action'] == 'unsubmit_assignment' && $user_type === 'student') {
        $post_id = (int)$_POST['post_id'];
        try {
            $stmt = $conn->prepare("SELECT file_path FROM sacli_room_submissions WHERE post_id = ? AND student_id = ?");
            $stmt->bind_param("is", $post_id, $my_id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($sub = $res->fetch_assoc()) if (file_exists($sub['file_path'])) @unlink($sub['file_path']);
            $stmt->close();

            $delete_stmt = $conn->prepare("DELETE FROM sacli_room_submissions WHERE post_id = ? AND student_id = ?");
            $delete_stmt->bind_param("is", $post_id, $my_id);
            $delete_stmt->execute();
            echo json_encode(['status' => 'success', 'message' => 'Your work has been unsubmitted.']);
        } catch (Throwable $e) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); }
        exit();
    }

    // Teacher grades a submission
    if ($_POST['action'] == 'grade_submission' && $user_type === 'teacher') {
        $submission_id = (int)$_POST['submission_id'];
        $grade = trim($_POST['grade']);

        try {
            // Security check: Make sure the teacher grading this is the teacher of the room
            $check_stmt = $conn->prepare("
                SELECT r.teacher_id, sub.student_id, sub.post_id
                FROM sacli_room_submissions sub
                JOIN sacli_room_posts p ON sub.post_id = p.id
                JOIN sacli_rooms r ON p.room_id = r.id
                WHERE sub.id = ?
            ");
            if ($check_stmt === false) throw new Exception('DB error (auth): ' . $conn->error);
            $check_stmt->bind_param("i", $submission_id);
            $check_stmt->execute();
            $check_res = $check_stmt->get_result()->fetch_assoc();

            if (!$check_res || $check_res['teacher_id'] !== $my_id) {
                throw new Exception('Authorization Error. You are not the teacher of this room.');
            }

            // Update the grade
            $update_stmt = $conn->prepare("UPDATE sacli_room_submissions SET grade = ? WHERE id = ?");
            if ($update_stmt === false) throw new Exception('DB error (update): ' . $conn->error);
            $update_stmt->bind_param("si", $grade, $submission_id);
            $update_stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'Grade returned successfully.']);

        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit();
    }

    // Get Room Participants (for Meeting.jsx)
    if ($_POST['action'] == 'get_room_participants') {
        $room_id = (int)$_POST['room_id'];
        
        $query = "
            SELECT m.student_id, m.role, s.First_Name, s.Last_Name
            FROM sacli_room_members m
            LEFT JOIN students s ON m.student_id = s.Student_ID
            WHERE m.room_id = ?
        ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $participants = [];
        while ($row = $result->fetch_assoc()) {
            $isMe = ($row['student_id'] == $my_id);
            $displayName = (!empty($row['First_Name']) && !empty($row['Last_Name'])) ? ($row['First_Name'] . ' ' . $row['Last_Name']) : "User " . $row['student_id'];
            
            $participants[] = [
                'id' => $row['student_id'],
                'name' => $displayName,
                'isHost' => ($row['role'] === 'teacher'),
                'isMe' => $isMe
            ];
        }
        
        echo json_encode(['status' => 'success', 'participants' => $participants]);
        exit();
    }

    // Validate Room Code (for Meeting Join)
    if ($_POST['action'] == 'validate_room_code') {
        $code = trim($_POST['code']);
        $stmt = $conn->prepare("SELECT id, name FROM sacli_rooms WHERE room_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Room found.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid room code. No such room exists.']);
        }
        exit();
    }

    // --- MEETING HUD HANDLERS ---
    if ($_POST['action'] == 'log_meeting_entry') {
        $code = $_POST['room_code'];
        $host = $_POST['host_name'] ?? 'Unknown Host';
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("INSERT INTO sacli_meeting_logs (room_code, student_id, joined_at, host_name) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $code, $my_id, $now, $host);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'log_id' => $stmt->insert_id]);
        exit();
    }

    if ($_POST['action'] == 'get_meeting_history') {
        $stmt = $conn->prepare("SELECT * FROM sacli_meeting_logs WHERE student_id = ? ORDER BY joined_at DESC LIMIT 10");
        $stmt->bind_param("s", $my_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $logs = [];
        while($row = $res->fetch_assoc()) $logs[] = $row;
        echo json_encode($logs);
        exit();
    }

    if ($_POST['action'] == 'log_meeting_exit') {
        $log_id = (int)$_POST['log_id'];
        $now = date('Y-m-d H:i:s');
        $conn->query("UPDATE sacli_meeting_logs SET left_at = '$now' WHERE id = $log_id");
        echo json_encode(['status' => 'success']);
        exit();
    }

    if ($_POST['action'] == 'get_active_meeting_participants') {
        $code = $_POST['room_code'];
        $sql = "SELECT l.student_id, 
                COALESCE(s.student_name, t.name) as name, 
                COALESCE(s.profile_pic, t.profile_pic) as profile_pic 
                FROM sacli_meeting_logs l 
                LEFT JOIN students s ON l.student_id = s.student_id 
                LEFT JOIN teachers t ON l.student_id = CONCAT('T-', t.id) 
                WHERE l.room_code = ? AND l.left_at IS NULL
                GROUP BY l.student_id";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $res = $stmt->get_result();
        $parts = [];
        while($row = $res->fetch_assoc()) $parts[] = $row;
        echo json_encode(['status' => 'success', 'participants' => $parts]);
        exit();
    }

    // --- INVITATION SYSTEM HANDLERS ---
    if ($_POST['action'] == 'invite_to_room' && $user_type === 'teacher') {
        $room_id = (int)$_POST['room_id'];
        $student_ids = $_POST['student_ids'] ?? [];

        if (empty($student_ids)) {
            echo json_encode(['status' => 'error', 'message' => 'No students selected.']);
            exit();
        }

        // Get Room Name and Teacher Name para sa email content
        $room_q = $conn->query("SELECT name FROM sacli_rooms WHERE id = $room_id");
        $room_name = $room_q->fetch_assoc()['name'] ?? 'SacliRoom';
        $teacher_name = $_SESSION['student_name'] ?? 'Your Teacher';

        $conn->query("CREATE TABLE IF NOT EXISTS sacli_room_invitations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id INT NOT NULL,
            teacher_id VARCHAR(50) NOT NULL,
            student_id VARCHAR(50) NOT NULL,
            status ENUM('pending', 'accepted', 'declined') DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (room_id, student_id)
        )");

        $count = 0;
        $stmt = $conn->prepare("INSERT IGNORE INTO sacli_room_invitations (room_id, teacher_id, student_id) VALUES (?, ?, ?)");
        foreach ($student_ids as $sid) {
            $stmt->bind_param("iss", $room_id, $my_id, $sid);
            if ($stmt->execute()) {
                // I-check kung may bagong record na na-insert (1) o na-ignore lang (0)
                if ($stmt->affected_rows > 0) {
                    $count++;
                    // Kunin ang email at pangalan ng student para sa notification
                    $s_res = $conn->query("SELECT email, student_name FROM students WHERE student_id = '$sid'");
                    if ($s_res && $s_row = $s_res->fetch_assoc()) {
                        if (!empty($s_row['email'])) {
                            sendRoomEmail(
                                $s_row['email'], 
                                "Room Invitation: $room_name", 
                                "Hi {$s_row['student_name']}, Teacher <b>$teacher_name</b> has invited you to join the virtual room: <b>$room_name</b>. You can accept this invitation in your SacliChat Messenger."
                            );
                        }
                    }
                }
            }
        }
        echo json_encode(['status' => 'success', 'message' => "Successfully sent $count invitations."]);
        exit();
    }

    if ($_POST['action'] == 'get_my_invitations') {
        $sql = "SELECT i.*, r.name as room_name, t.name as teacher_name 
                FROM sacli_room_invitations i 
                JOIN sacli_rooms r ON i.room_id = r.id 
                JOIN teachers t ON i.teacher_id = CONCAT('T-', t.id) 
                WHERE i.student_id = ? AND i.status = 'pending'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $my_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $invites = [];
        while ($row = $res->fetch_assoc()) $invites[] = $row;
        echo json_encode($invites);
        exit();
    }

    if ($_POST['action'] == 'respond_room_invite') {
        $room_id = (int)$_POST['room_id'];
        $response = $_POST['response']; // 'accept' or 'decline'

        if ($response === 'accept') {
            // 1. Gawing member si student sa room
            $stmt = $conn->prepare("INSERT IGNORE INTO sacli_room_members (room_id, student_id, role) VALUES (?, ?, 'student')");
            $stmt->bind_param("is", $room_id, $my_id);
            $stmt->execute();
            
            // 2. I-update ang invitation status para mawala sa "Pending" list
            $conn->query("UPDATE sacli_room_invitations SET status = 'accepted' WHERE room_id = $room_id AND student_id = '$my_id'");
            
            // 3. (Optional) Mag-send ng internal notification sa Teacher na nag-accept ang student
            $room_q = $conn->query("SELECT teacher_id, name FROM sacli_rooms WHERE id = $room_id");
            if($room = $room_q->fetch_assoc()){
                $t_id = $room['teacher_id'];
                $msg = "{$_SESSION['student_name']} accepted your invitation to join {$room['name']}.";
                $conn->query("INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES ('$t_id', '$my_id', 'room_join', $room_id)");
            }

            echo json_encode(['status' => 'success', 'message' => ' neural_link established. you have joined the room.']);
        } else {
            // Kapag decline, burahin na agad ang invitation record
            $conn->query("DELETE FROM sacli_room_invitations WHERE room_id = $room_id AND student_id = '$my_id'");
            echo json_encode(['status' => 'success', 'message' => 'invitation terminated. access denied.']);
        }
        exit();
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
?>