<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/storage.php';
use PHPMailer\PHPMailer\PHPMailer;

set_time_limit(0); 
ini_set('memory_limit', '1024M');
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/../config/database.php';
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['student_id'], $_SESSION['student_name'])) exit;

$my_name = $_SESSION['student_name'];
$my_id = $_SESSION['student_id'];
$user_type = $_SESSION['user_type'] ?? 'student';

function sendNotificationEmail($to_email, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sacliconnect20@gmail.com';
        $mail->Password   = 'umrrmsyujepjopbo'; 
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->Timeout    = 5; 
        $mail->SMTPOptions = array('ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true));
        $mail->setFrom('sacliconnect20@gmail.com', 'SacliConnect');
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = "
            <div style='font-family: Arial; padding: 20px; border: 2px solid #00ffaa; border-radius: 10px; background-color: #0a1f16; color: #fff;'>
                <h2 style='color: #00ffaa;'>SacliConnect Official Announcement</h2>
                <p style='font-size: 16px;'>$body</p>
                <hr style='border: 0.5px solid #00ffaa;'>
                <p style='font-size: 12px;'>This is an automated message. Please check the SacliConnect app for more details.</p>
            </div>";
        $mail->send();
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- HANDLE POLL CREATION ---
    if ($action === 'create_poll' && $user_type === 'admin') {
        $question = trim($_POST['content']);
        $options = $_POST['options'] ?? [];

        if (!empty($question) && count($options) >= 2) {
            $stmt = $conn->prepare("INSERT INTO posts (student_name, content, category) VALUES (?, ?, 'Announcement')");
            $stmt->bind_param("ss", $my_name, $question);
            if ($stmt->execute()) {
                $post_id = $stmt->insert_id;
                $opt_stmt = $conn->prepare("INSERT INTO poll_options (post_id, option_text) VALUES (?, ?)");
                foreach ($options as $opt_text) {
                    $opt_text = trim($opt_text);
                    if (!empty($opt_text)) {
                        $opt_stmt->bind_param("is", $post_id, $opt_text);
                        $opt_stmt->execute();
                    }
                }
                header("Location: SacliConnect.php?poll_created=1");
                exit();
            }
        }
    } 
    
    // --- HANDLE NORMAL POST ---
    else {
        $content = trim($_POST['content'] ?? '');
        $category = isset($_POST['category']) ? $_POST['category'] : 'General';

        // Insert into SacliConnect's own posts table
        $sql = "INSERT INTO posts (student_name, content, category) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sss", $my_name, $content, $category);
            $stmt->execute();
            $post_id = $stmt->insert_id;
        } else {
            die("SQL Prepare Error: " . $conn->error);
        }

        if ($category === 'Announcement') {
            $all_users = $conn->query("SELECT student_id, email, student_name FROM students WHERE is_restricted = 0");
            while ($u = $all_users->fetch_assoc()) {
                if ($u['student_id'] != $my_id) {
                    $conn->query("INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES ('".$u['student_id']."', '$my_id', 'announcement', '$post_id')");
                    if (!empty($u['email'])) {
                        @sendNotificationEmail($u['email'], "SacliConnect: New Announcement!", "Hi {$u['student_name']}, $my_name just posted an important announcement: \"<i>$content</i>\"");
                    }
                }
            }
        }

        if (isset($_POST['tagged_users']) && is_array($_POST['tagged_users']) && $post_id > 0) {
            $tag_stmt = $conn->prepare("INSERT INTO post_tags (post_id, student_id) VALUES (?, ?)");
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES (?, ?, 'tag', ?)");
            foreach ($_POST['tagged_users'] as $uid) {
                if ($uid != $my_id) {
                    $tag_stmt->bind_param("is", $post_id, $uid);
                    $tag_stmt->execute();
                    $notif_stmt->bind_param("ssi", $uid, $my_id, $post_id);
                    $notif_stmt->execute();
                }
            }
        }

        if (isset($_FILES['media']) && !empty($_FILES['media']['name'][0]) && $post_id > 0) {
            $media_stmt = $conn->prepare("INSERT INTO post_media (post_id, file_path, file_type) VALUES (?, ?, ?)");
            $total_files = count($_FILES['media']['name']);
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['media']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['media']['tmp_name'][$i];
                    $ext = strtolower(pathinfo($_FILES['media']['name'][$i], PATHINFO_EXTENSION));
                    $allowed = ['jpg','jpeg','png','gif','mp4','mov','webm'];
                    if (in_array($ext, $allowed)) {
                        $ftype = in_array($ext, ['mp4','mov','webm']) ? 'video' : 'photo';
                        $dest_filename = "post_" . $post_id . "_" . uniqid() . "." . $ext;
                        $url = uploadToSupabase($tmp_name, $dest_filename);
                        if ($url) {
                            $media_stmt->bind_param("iss", $post_id, $url, $ftype);
                            $media_stmt->execute();
                        }
                    }
                }
            }
        }
        header("Location: SacliConnect.php");
        exit();
    }
}

$conn->close();
?>
