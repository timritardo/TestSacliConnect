<?php
session_start();
require_once __DIR__ . '/config/database.php';

// 1. Check kung naka-login (Security)
if (!isset($_SESSION['student_id'])) {
    header("Location: SacliConnect_LOG_IN.php");
    exit();
}

// 2. AUTO-FIX: Idagdag ang mga columns sa database kung wala pa (Para di mag-error)
$cols = [
    'year_level' => 'VARCHAR(50) DEFAULT ""', 
    'course' => 'VARCHAR(100) DEFAULT ""', 
    'bio' => 'TEXT', 
    'profile_pic' => 'VARCHAR(255) DEFAULT ""', 
    'email' => 'VARCHAR(100) DEFAULT ""', 
    'is_alumni' => 'TINYINT(1) DEFAULT 0', 
    'cover_photo' => 'VARCHAR(255) DEFAULT ""', 
    'cover_offset' => 'INT DEFAULT 0',
    'location' => 'VARCHAR(255) DEFAULT ""',
    'birthdate' => 'DATE NULL', 
    'phone' => 'VARCHAR(20) DEFAULT NULL',
    'hide_phone' => 'TINYINT(1) DEFAULT 0',
    'gender' => 'VARCHAR(20) DEFAULT ""'
];
foreach ($cols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM students LIKE '$col'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE students ADD COLUMN $col $def");
    }
}
safeAddColumn($conn, 'teachers', 'cover_photo', "VARCHAR(255) DEFAULT ''");
safeAddColumn($conn, 'teachers', 'cover_offset', "INT DEFAULT 0");
safeAddColumn($conn, 'teachers', 'location', "VARCHAR(255) DEFAULT ''");
safeAddColumn($conn, 'teachers', 'phone', "VARCHAR(20) DEFAULT NULL");
safeAddColumn($conn, 'teachers', 'hide_phone', "TINYINT(1) DEFAULT 0");
safeAddColumn($conn, 'teachers', 'birthdate', "DATE NULL");

// Fetch Logged-in User Info for the Header (Sync with SacliConnect.php)
$my_id_header = $_SESSION['student_id'];
$user_type_header = $_SESSION['user_type'] ?? 'student';
if ($user_type_header === 'teacher') {
    $real_id_h = str_replace("T-", "", $my_id_header);
    $me_res = $conn->query("SELECT *, name as student_name FROM teachers WHERE id='$real_id_h'");
} elseif ($user_type_header === 'admin') {
    $me_res = $conn->query("SELECT *, username as student_name FROM admins2 WHERE username='".$_SESSION['admin_username']."'");
} else {
    $me_res = $conn->query("SELECT * FROM students WHERE student_id='$my_id_header'");
}
$me = $me_res->fetch_assoc();

// Calculate sub-info (Role/Year)
$user_sub_info = "Student";
if($user_type_header === 'student') {
    $user_sub_info = (isset($me['is_alumni']) && $me['is_alumni'] == 1) ? "Alumni" : ($me['year_level'] ?? 'Student');
} elseif ($user_type_header === 'admin') { $user_sub_info = "Admin"; }
else { $user_sub_info = "Faculty Teacher"; }

// Blackout Protocol Check
$blackout_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='blackout_mode'");
$blackout_active = ($blackout_res && $blackout_res->num_rows > 0 && $blackout_res->fetch_assoc()['setting_value'] == '1');

// AUTO-FIX: Ensure teachers table has bio column
safeAddColumn($conn, 'teachers', 'bio', "TEXT");

$message = "";
$msg_type = "";

// Determine Target Profile (Own or Others)
$target_id = isset($_GET['id']) ? $_GET['id'] : $_SESSION['student_id'];
$is_admin = ($_SESSION['student_name'] === 'Admin');
$is_own_profile = ($target_id == $_SESSION['student_id'] || $is_admin);
$is_teacher_profile = (strpos($target_id, 'T-') === 0);

// Determine if the user has already verified their identity for this session
$is_verified_for_update = (isset($_SESSION['profile_update_verified']) && $_SESSION['profile_update_verified'] === true) || $is_admin;

// 3. Handle Save/Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_own_profile) {
    // Fetch current DB data for verification comparison
    if ($is_teacher_profile) {
        $real_id_chk = substr($target_id, 2);
        $curr_db_user = $conn->query("SELECT email, phone FROM teachers WHERE id = '$real_id_chk'")->fetch_assoc();
    } else {
        $curr_db_user = $conn->query("SELECT email, phone FROM students WHERE student_id = '$target_id'")->fetch_assoc();
    }

    $year_level = trim($_POST['year_level'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $birthdate = !empty($_POST['birthdate']) ? $_POST['birthdate'] : null;
    $gender = $_POST['gender'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $hide_phone = isset($_POST['hide_phone']) ? 1 : 0;
    $cover_offset = intval($_POST['cover_offset'] ?? 0);
    $is_me_teacher = $is_teacher_profile;

    // Check if security verification is required and completed
    $email_changed = trim($_POST['email'] ?? '') !== trim($curr_db_user['email'] ?? '');
    $phone_changed = trim($_POST['phone'] ?? '') !== trim($curr_db_user['phone'] ?? '');
    $requires_verify = ($email_changed || $phone_changed) && !$is_admin;

    if ($requires_verify && (!isset($_SESSION['profile_update_verified']) || $_SESSION['profile_update_verified'] !== true)) {
        $message = "Security Error: Identity verification required for email/phone changes.";
        $msg_type = "error";
    } else {
        if ($requires_verify) unset($_SESSION['profile_update_verified']); // Reset after use

    // Handle Cover Photo Upload
    if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $real_id = $is_me_teacher ? substr($target_id, 2) : $target_id;
            $new_filename = "cover_" . $real_id . "_" . time() . "." . $ext;
            if (!is_dir('uploads')) mkdir('uploads');
            if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], "uploads/" . $new_filename)) {
                $table = $is_me_teacher ? 'teachers' : 'students';
                $where = $is_me_teacher ? "id = '$real_id'" : "student_id = '$target_id'";
                $conn->query("UPDATE $table SET cover_photo = '$new_filename' WHERE $where");
            }
        }
    }

    // Handle Image Upload
    $pic_sql = "";
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            if ($is_me_teacher) {
                $real_id = substr($target_id, 2);
                $new_filename = "teacher_" . $real_id . "_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $new_filename)) {
                    $conn->query("UPDATE teachers SET profile_pic = '$new_filename' WHERE id = '$real_id'");
                }
            } else {
                $new_filename = "profile_" . $target_id . "_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $new_filename)) {
                    $conn->query("UPDATE students SET profile_pic = '$new_filename' WHERE student_id = '$target_id'");
                }
            }
        }
    }
    if ($is_me_teacher) {
        $real_id = substr($target_id, 2);
        // Map: year_level -> position, course -> department, phone and hide_phone added
        $stmt = $conn->prepare("UPDATE teachers SET position = ?, department = ?, bio = ?, email = ?, location = ?, birthdate = ?, phone = ?, hide_phone = ?, cover_offset = ? WHERE id = ?");
        $stmt->bind_param("sssssssiii", $year_level, $course, $bio, $email, $location, $birthdate, $phone, $hide_phone, $cover_offset, $real_id);
    } else {
        $stmt = $conn->prepare("UPDATE students SET year_level = ?, course = ?, bio = ?, email = ?, location = ?, birthdate = ?, gender = ?, phone = ?, hide_phone = ?, cover_offset = ? WHERE student_id = ?");
        $stmt->bind_param("ssssssssiis", $year_level, $course, $bio, $email, $location, $birthdate, $gender, $phone, $hide_phone, $cover_offset, $target_id);
    }

    if ($stmt->execute()) {
        $message = "successful updated info";
        $msg_type = "success";
    } else {
        $message = "Error updating profile: " . $conn->error;
        $msg_type = "error";
    }
    $stmt->close();
    } // Close the security verification else
} // Close the POST request check

// Handle Evaluation Submission (Added for profile page evaluation)
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'submit_evaluation'){
    // Check lock
    $eval_lock_chk = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='evaluation_locked'");
    $is_locked_submit = ($eval_lock_chk && $eval_lock_chk->num_rows > 0 && $eval_lock_chk->fetch_assoc()['setting_value'] == '1');

    if(!$is_locked_submit) {
        $teacher_id = intval($_POST['teacher_id']);
        $comments = $conn->real_escape_string($_POST['comments']);
        $ratings = isset($_POST['rating']) ? $_POST['rating'] : [];
        
        if(!empty($ratings)){
            $total = 0; $count = 0;
            foreach($ratings as $r){ $total += intval($r); $count++; }
            $average = $count > 0 ? round($total / $count) : 0;
            
            $stmt = $conn->prepare("INSERT INTO evaluations (student_id, teacher_id, rating, comments) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("siis", $_SESSION['student_id'], $teacher_id, $average, $comments);
            if($stmt->execute()){
                $eval_id = $stmt->insert_id;
                $stmt_det = $conn->prepare("INSERT INTO evaluation_answers (evaluation_id, question_id, rating) VALUES (?, ?, ?)");
                foreach($ratings as $qid => $score){ $stmt_det->bind_param("iii", $eval_id, $qid, $score); $stmt_det->execute(); }
                $message = "Evaluation submitted successfully!"; $msg_type = "success";
            }
        }
    }
}

// Handle Reset Evaluation (For Students viewing Teachers)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reset_evaluation') {
    $teacher_id_reset = intval($_POST['teacher_id']);
    $student_id_reset = $_SESSION['student_id'];
    
    // Delete evaluation and answers
    $conn->query("DELETE FROM evaluation_answers WHERE evaluation_id IN (SELECT id FROM evaluations WHERE student_id='$student_id_reset' AND teacher_id='$teacher_id_reset')");
    $conn->query("DELETE FROM evaluations WHERE student_id='$student_id_reset' AND teacher_id='$teacher_id_reset'");
    $message = "Evaluation reset successfully. You can now evaluate again.";
    $msg_type = "success";
}

// 4. Fetch Current User Data (Para ipakita sa form)
if ($is_teacher_profile) {
    $real_id = substr($target_id, 2);
    // Map teacher columns to student columns for uniform display
    $stmt = $conn->prepare("SELECT *, name as student_name, department as course, position as year_level FROM teachers WHERE id = ?");
    $stmt->bind_param("i", $real_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $target_id);
}
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check for status messages from verify_profile_change.php
if (isset($_GET['status_message'])) {
    $message = htmlspecialchars($_GET['status_message']);
    $msg_type = htmlspecialchars($_GET['status_type']);
    // Clear the GET parameters to prevent re-showing on refresh
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]";
    $query_params = $_GET;
    unset($query_params['status_message']);
    unset($query_params['status_type']);
    $new_query_string = http_build_query($query_params);
    $new_url = $current_url . ($new_query_string ? '?' . $new_query_string : '');
    echo "<script>window.history.replaceState({}, document.title, '$new_url');</script>";
}

// Format the name for display
$formatted_name = $user ? ucwords(strtolower($user['student_name'])) : 'User Not Found';

// --- FETCH STATS (Enhancement) ---
$stats_name = $user ? $conn->real_escape_string($user['student_name']) : '';
$total_posts = $conn->query("SELECT COUNT(*) FROM posts WHERE student_name = '$stats_name'")->fetch_row()[0];
$total_views = $conn->query("SELECT SUM(views) FROM posts WHERE student_name = '$stats_name'")->fetch_row()[0] ?? 0;
$total_likes = $conn->query("SELECT COUNT(*) FROM post_reactions r JOIN posts p ON r.post_id = p.id WHERE p.student_name = '$stats_name'")->fetch_row()[0];

// Fetch Evaluation Lock Status
$eval_lock_q = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='evaluation_locked'");
$is_eval_locked = ($eval_lock_q && $eval_lock_q->num_rows > 0 && $eval_lock_q->fetch_assoc()['setting_value'] == '1');


if (!$user) die("<div style='color:white;text-align:center;margin-top:50px;'>Student not found. <a href='SacliConnect.php' style='color:#00ffaa;'>Back</a></div>");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($formatted_name); ?>'s Profile</title>
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        /* --- Header & Layout Sync Styles --- */
        :root {
            --header-h: 58px;
            --bg-dark: #102e22;
            --bg-light: #1a3d2f;
            --text-color: #e4e6eb;
            --accent: #00ffaa;
        }
        header {
            position: fixed; top: 0; left: 0; width: 100%; height: var(--header-h);
            background-color: var(--bg-dark); border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 20px; z-index: 10000; box-shadow: 0 2px 10px rgba(0,0,0,0.4);
        }
        .hd-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
        .hd-logo { height: 40px; width: 40px; border-radius: 50%; cursor: pointer; }
        .hd-right { display: flex; align-items: center; gap: 10px; justify-content: flex-end; flex-shrink: 0; position: relative; right: 1.8%; }
        .hd-icon-btn {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: linear-gradient(to top right, rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.4));
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            color: white; font-size: 18px;
        }
        .hd-icon-btn:hover {
             transform: scale(1.1) rotate(3deg);
        border-color: rgba(255, 255, 255, 0.83);
        background: linear-gradient(to top right, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.47));
        box-shadow: 0 0 20px rgba(255, 255, 255, 0.42);
        }
        .hd-icon-btn:active { transform: scale(0.95) rotate(0deg); }
        .hd-icon-btn img { width: 22px; height: 22px; object-fit: contain; position: relative; z-index: 10; transition: transform 0.3s; }
        .hd-icon-btn:hover img { transform: scale(1.1); }
        
        .hd-icon-btn .shimmer {
            position: absolute; inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.7s ease-out;
            z-index: 5;
        }
        .hd-icon-btn:hover .shimmer { transform: translateX(100%); }

        /* Theme Variations */
    .hd-icon-btn.msg-btn { border-color: rgba(204, 0, 255, 0.2); }
    .hd-icon-btn.msg-btn:hover { border-color: rgba(255, 0, 204, 0.5); box-shadow: 0 0 20px rgba(255, 0, 255, 0.3); }
    .hd-icon-btn.msg-btn .shimmer { background: linear-gradient(90deg, transparent, rgba(255, 0, 251, 0.2), transparent); }

    .hd-icon-btn.notif-btn { border-color: rgba(0, 98, 255, 0.64); }
    .hd-icon-btn.notif-btn:hover { border-color: rgba(0, 160, 241, 0.5); box-shadow: 0 0 20px rgba(99, 101, 241, 0.31); }
    .hd-icon-btn.notif-btn .shimmer { background: linear-gradient(90deg, transparent, rgba(0, 128, 255, 0.37), transparent); }

    .hd-icon-btn.concern-btn { border-color: rgba(239, 68, 68, 0.2); }
    .hd-icon-btn.concern-btn:hover { border-color: rgba(239, 68, 68, 0.5); box-shadow: 0 0 20px rgba(255, 3, 3, 0.3); }
    .hd-icon-btn.concern-btn .shimmer { background: linear-gradient(90deg, transparent, rgba(239, 68, 68, 0.68), transparent); }
        .icon-wrapper { position: relative; cursor: pointer; }
        .badge-count { position: absolute; top: -2px; right: -2px; background-color: #e41e3f; color: white; font-size: 10px; font-weight: bold; padding: 2px 5px; border-radius: 10px; border: 1px solid var(--bg-dark); }

        /* Initial load animations (Zoom In effect) */
        .initial-load .post { animation: popInEntry 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) backwards; }
        @keyframes popInEntry {
            0% { opacity: 0; transform: scale(0.9); }
            70% { transform: scale(1.02); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* Enhanced Dark Green / Neon Theme */
        html { overflow-x: hidden; }
        body {
            margin: 0;
            padding-top: var(--header-h);
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(120deg, #05100c 40%, #0d2b1f 50%, #05100c 60%);
            background-size: 400% 400%;
            animation: backgroundFlow 12s ease-in-out infinite;
            color: #e4e6eb;
            min-height: 100vh;
            overflow-y: auto;
            overflow-x: hidden; /* I-lock ang horizontal scroll */
            scroll-behavior: smooth;
            touch-action: pan-y; /* Pinapagana lang ang vertical scroll, bawal ang swipe left/right */
        }

        /* --- Responsive Header Fix --- */
        @media (max-width: 1100px) {
            .header-gc-list { max-width: 150px !important; }
            .hd-search { width: 180px !important; }
        }

        @media (max-width: 900px) {
            .create-gc-btn { display: none !important; }
            .header-gc-list { display: none !important; }
            .hd-search { width: 45px !important; padding: 0 !important; justify-content: center !important; cursor: pointer; }
            .hd-search input { display: none; }
            .hd-profile span { display: none; }
            .hd-profile { padding: 0 !important; }
        }
        
        .hd-search:hover { border: 1px solid var(--accent); }
        .hd-search.active { width: 220px !important; justify-content: flex-start !important; padding: 0 12px !important; }
        .hd-search.active input { display: block !important; }

        .dropdown-popover {
            position: fixed; top: 60px; background: var(--bg-light);
            border: 1px solid rgba(0, 255, 170, 0.3); border-radius: 8px;
            width: 300px; box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            display: none; flex-direction: column; z-index: 10001; overflow: hidden;
        }
        .dropdown-popover.active { display: flex; animation: fadeIn 0.2s; }
        .dp-header { padding: 10px 15px; background: rgba(0,0,0,0.2); color: var(--accent); font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .dp-item { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-color); cursor: pointer; display: flex; align-items: center; gap: 10px; }
        .dp-item:hover { background: rgba(255,255,255,0.05); }
        .dp-item small { display: block; color: #b0b3b8; font-size: 12px; margin-top: 2px; }

        .search-results {
            position: absolute; top: 45px; left: 0; width: 100%; background: #1a3d2f;
            border: 1px solid rgba(0, 255, 170, 0.3); border-radius: 0 0 15px 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5); display: none; z-index: 10001; overflow: hidden;
        }
        .cb-options-menu { position: absolute; top: 100%; right: 0; background: var(--bg-light); border: 1px solid rgba(0, 255, 170, 0.3); border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.5); display: none; flex-direction: column; z-index: 10003; min-width: 150px; }
        .cb-options-menu.active { display: flex; }
        .cb-options-item { padding: 8px 12px; color: var(--text-color); cursor: pointer; transition: background 0.2s; font-size: 13px; }
        .cb-options-item:hover { background: rgba(0, 255, 170, 0.1); }
        
        /* Chat Box Styles from SacliConnect */
        .chat-box-container {
            position: fixed; bottom: 0; right: 80px; width: 320px; background: var(--bg-light);
            border: 1px solid rgba(0, 255, 170, 0.3); border-radius: 10px 10px 0 0;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.5); display: none; flex-direction: column; z-index: 10002;
        }
        .cb-header { background: var(--bg-dark); padding: 10px 15px; border-bottom: 1px solid rgba(255,255,255,0.1); border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
        .cb-title { font-weight: bold; color: var(--accent); }
        .cb-body { height: 300px; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 8px; background: rgba(0,0,0,0.2); }
        .cb-footer { padding: 10px; display: flex; gap: 5px; border-top: 1px solid rgba(255,255,255,0.1); }
        .cb-input { flex: 1; padding: 8px; border-radius: 20px; border: none; outline: none; background: rgba(255,255,255,0.1); color: white; }
        .cb-send { background: var(--accent); border: none; padding: 8px 15px; border-radius: 20px; cursor: pointer; font-weight: bold; color: #0a1f16; }

        /* Message Styles for Mini Chat */
        .msg { padding: 8px 12px; border-radius: 15px; max-width: 75%; font-size: 14px; word-wrap: break-word; position: relative; }
        .my-msg { align-self: flex-end; background: var(--accent); color: #0a1f16; }
        .other-msg { align-self: flex-start; background: rgba(255,255,255,0.1); color: white; }
        
        .create-gc-btn {
          --green: #1BFD9C;
          font-size: 13px;
          padding: 0.5em 1.5em;
          letter-spacing: 0.06em;
          position: relative;
          font-family: inherit;
          border-radius: 0.6em;
          overflow: hidden;
          transition: all 0.3s;
          line-height: 1.4em;
          border: 2px solid var(--green);
          background: linear-gradient(to right, rgba(27, 253, 156, 0.1) 1%, transparent 40%,transparent 60% , rgba(27, 253, 156, 0.1) 100%);
          color: var(--green);
          box-shadow: inset 0 0 10px rgba(27, 253, 156, 0.4), 0 0 9px 3px rgba(27, 253, 156, 0.1);
          cursor: pointer;
          margin-left: 10px;
          font-weight: bold;
          white-space: nowrap;
        }
        .create-gc-btn:hover { color: #82ffc9; box-shadow: inset 0 0 10px rgba(27, 253, 156, 0.6), 0 0 9px 3px rgba(27, 253, 156, 0.2); }
        .create-gc-btn:before {
          content: ""; position: absolute; left: -4em; width: 4em; height: 100%; top: 0;
          transition: transform .4s ease-in-out;
          background: linear-gradient(to right, transparent 1%, rgba(27, 253, 156, 0.1) 40%,rgba(27, 253, 156, 0.1) 60% , transparent 100%);
        }
        .create-gc-btn:hover:before { transform: translateX(15em); }
        
        .header-gc-list::-webkit-scrollbar { display: none; }

        @keyframes backgroundFlow {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .change-link-modern {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        color: #00ffaa;
        text-decoration: none;
        font-weight: 800;
        margin-top: 8px;
        cursor: pointer;
        opacity: 0.8;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        text-transform: uppercase;
        letter-spacing: 1px;
        font-family: 'Orbitron', sans-serif;
        text-shadow: 0 0 5px rgba(0, 255, 170, 0.4);
    }
    .change-link-modern:hover { 
        opacity: 1; 
        color: #fff;
        text-shadow: 0 0 10px #00ffaa, 0 0 20px #00ffaa;
        transform: translateX(5px);
    }
    .change-link-modern svg {
        filter: drop-shadow(0 0 2px #00ffaa);
    }

    @keyframes backgroundFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .background-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2; /* Mas mababa sa logo na -1 para nasa likod talaga */
            pointer-events: none;
            opacity: 0.2;
            background: radial-gradient(
                circle farthest-side at 0% 50%,
                #282828 23.5%,
                rgba(255, 170, 0, 0) 0
            )
            21px 30px,
            radial-gradient(
                circle farthest-side at 0% 50%,
                #2c3539 24%,
                rgba(240, 166, 17, 0) 0
            )
            19px 30px,
            linear-gradient(
                #282828 14%,
                rgba(240, 166, 17, 0) 0,
                rgba(240, 166, 17, 0) 85%,
                #282828 0
            )
            0 0,
            linear-gradient(
                150deg,
                #282828 24%,
                #2c3539 0,
                #2c3539 26%,
                rgba(240, 166, 17, 0) 0,
                rgba(240, 166, 17, 0) 74%,
                #2c3539 0,
                #2c3539 76%,
                #282828 0
            )
            0 0,
            linear-gradient(
                30deg,
                #282828 24%,
                #2c3539 0,
                #2c3539 26%,
                rgba(240, 166, 17, 0) 0,
                rgba(240, 166, 17, 0) 74%,
                #2c3539 0,
                #2c3539 76%,
                #282828 0
            )
            0 0,
            linear-gradient(90deg, #2c3539 2%, #282828 0, #282828 98%, #2c3539 0%) 0 0
            #282828;
            background-size: 40px 60px;
        }

        .background-logo {
            position: fixed;
            top: 50%;
            left: 85%;
            transform: translate(-50%, -50%);
            width: 1300px;
            opacity: 0.1;
            z-index: -1;
            pointer-events: none;
            animation: pulseLogo 10s infinite alternate;
        }

        @keyframes pulseLogo { 
            from { transform: translate(-50%, -50%) scale(1); opacity: 0.1; } 
            to { transform: translate(-50%, -50%) scale(1.05); opacity: 0.15; } 
        }

        .profile-wrapper {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding-bottom: 50px;
            position: relative; /* Ginawa nating relative para ma-lock dito ang back button */
            animation: slideUp 0.6s cubic-bezier(0.22, 1, 0.36, 1);
        }

        /* --- FB Header Layout --- */
        .profile-header-card {
            background: #1a3d2f;
            border-radius: 0 0 20px 20px;
            border: 1px solid rgba(0, 255, 170, 0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            overflow: hidden;
            margin-bottom: 25px;
        }

        .cover-area {
            height: 380px;
            background: #0a1f16;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        .cover-photo-img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            transition: filter 0.5s; 
            position: absolute;
            top: 0;
            left: 0;
        }
        .cover-area:hover .cover-photo-img { filter: brightness(0.7); }

        .cover-btn-group { position: absolute; bottom: 20px; right: 20px; display: flex; gap: 10px; z-index: 10; }
        .cover-btn-sub {
            background: rgba(0,0,0,0.6); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2);
            color: white; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600;
            cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.3s;
        }
        .cover-btn-sub:hover { background: #00ffaa; color: #0a1f16; }
        .cover-area.adjusting .cover-btn-group { display: none; }

        /* --- Repositioning UI Enhancements --- */
        #repositionContainer {
            width: 100%;
            height: 380px;
            background: #000;
            position: relative;
            overflow: hidden;
            cursor: move;
            user-select: none;
            touch-action: none; /* Prevent scrolling on mobile while dragging */
        }
        #repositionImg {
            width: 100%;
            height: auto;
            display: block;
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none; /* Let the container handle events */
        }

        .header-content-bar {
            padding: 0 40px 10px;
            display: flex;
            align-items: flex-end;
            gap: 25px;
            margin-top: -80px;
            position: relative;
            z-index: 5;
        }

        .pfp-container {
            width: 180px; height: 180px;
            border-radius: 50%;
            border: 5px solid #1a3d2f;
            background: #1a3d2f;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0,0,0,0.5);
            cursor: pointer;
            flex-shrink: 0;
        }
        .pfp-container img { width: 100%; height: 100%; object-fit: cover; }

        .user-identity { padding-bottom: 15px; flex: 1; }
        .user-identity h1 { margin: 0; font-size: 32px; font-weight: 800; color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.5); }
        .user-identity p { margin: 5px 0 0; color: #00ffaa; font-weight: 600; letter-spacing: 1px; }

        .header-actions { display: flex; gap: 10px; padding-bottom: 20px; align-self: flex-end; }
        .action-btn-pill { 
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            color: white; padding: 10px 20px; border-radius: 10px; cursor: pointer;
            font-weight: 700; font-size: 14px; transition: 0.3s; display: flex; align-items: center; gap: 8px;
        }
        .action-btn-pill:hover { background: #00ffaa; color: #0a1f16; transform: translateY(-2px); }

        /* --- Tabs --- */
        .profile-nav-tabs {
            display: flex;
            padding: 0 40px;
            border-top: 1px solid rgba(255,255,255,0.05);
            gap: 5px;
        }
        .nav-tab {
            padding: 15px 20px; color: #aaa; text-decoration: none;
            font-weight: 600; font-size: 15px; border-bottom: 3px solid transparent;
            transition: 0.3s;
        }
        .nav-tab:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .nav-tab.active { color: #00ffaa; border-bottom-color: #00ffaa; }

        /* --- Bottom Layout --- */
        .profile-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 35px;
            transition: all 0.6s cubic-bezier(0.22, 1, 0.36, 1);
            width: 100%;
        }

        /* --- Scroll Animation Enhancements --- */
        .profile-sidebar-col {
            transition: all 0.6s cubic-bezier(0.22, 1, 0.36, 1);
            opacity: 1;
            transform: translateX(0);
        }

        .profile-timeline-col > div {
            transition: all 0.8s cubic-bezier(0.22, 1, 0.36, 1);
            transform-origin: top center;
            width: 100%;
            max-width: 700px; /* Limit width initially */
            margin: 0 auto;
        }
        
        .profile-timeline-col {
            transition: all 0.5s ease;
            width: 100%;
        }

        .profile-wrapper.scrolled-past .profile-grid {
            grid-template-columns: 1fr !important;
            gap: 0 !important;
        }

        .profile-wrapper.scrolled-past .profile-sidebar-col {
            max-height: 0 !important;
            opacity: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            transform: translateX(-150px) scale(0.8);
            overflow: hidden;
            pointer-events: none;
            position: absolute; /* Alisin sa flow para hindi humarang sa centering */
        }

        .profile-wrapper.scrolled-past .profile-timeline-col > div {
            max-width: 100% !important; /* Eexpand sa buong 1100px na wrapper */
            margin: 0 auto;
            transform: scale(1.02);
            /* Glassmorphism Effect */
            background: rgba(26, 61, 47, 0.4) !important;
            backdrop-filter: blur(15px) saturate(150%);
            -webkit-backdrop-filter: blur(15px) saturate(150%);
            border: 1px solid rgba(0, 255, 170, 0.3) !important;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.7), inset 0 0 20px rgba(0, 255, 170, 0.05);
        }

        .intro-card {
            background: #1a3d2f;
       
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255,255,255,0.05);
            backdrop-filter: blur(15px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }
        .intro-card h3 { color: #fff; font-size: 18px; margin: 0 0 20px; font-weight: 700; }

        .detail-item { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; font-size: 14px; color: #ccffeb; opacity: 0.9; }
        .detail-icon { font-size: 18px; width: 25px; text-align: center; }

        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        .back-btn-modern {
            position: absolute;
            top: 15px;
            left: 15px;
            text-decoration: none;
            color: #00ffaa;
            font-weight: 800;
            background: rgba(10, 31, 22, 0.85);
            backdrop-filter: blur(10px);
            padding: 10px 22px;
            border-radius: 50px;
            z-index: 999;
            font-size: 14px;
            border: 1px solid #00ffaa;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .back-btn-modern:hover { 
            background: #00ffaa; 
            color: #0a1f16; 
            transform: translateX(-5px);
            box-shadow: 0 0 25px rgba(0, 255, 170, 0.6);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 35px;
            margin-top: 20px;
        }

        /* Image Picker */
        .profile-upload-container {
            position: relative;
            width: 130px;
            height: 130px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid rgba(0, 255, 170, 0.8);
            box-shadow: 0 0 0 5px rgba(0, 255, 170, 0.1), 0 0 30px rgba(0, 255, 170, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .profile-upload-container:hover {
            transform: scale(1.02);
            box-shadow: 0 0 0 8px rgba(0, 255, 170, 0.2), 0 0 50px rgba(0, 255, 170, 0.5);
            border-color: #fff;
        }
        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            transition: height 0.3s ease;
        }
        .profile-upload-container:hover .upload-overlay { height: 100%; }
        .camera-icon { font-size: 24px; margin-bottom: 5px; color: #fff; }
        .upload-text { color: #00ffaa; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        
        .profile-title { font-size: 26px; color: #fff; margin: 0; font-weight: 700; letter-spacing: 0.5px; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
        .profile-subtitle { color: #00ffaa; font-size: 14px; margin-top: 5px; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; }

        .form-group { margin-bottom: 20px; position: relative; }
        .form-label { 
            display: block; 
            margin-bottom: 8px; 
            color: #b0fce0; 
            font-size: 13px; 
            font-weight: 600; 
            margin-left: 5px;
        }
        .form-input {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(0, 255, 170, 0.2);
            border-radius: 12px;
            color: white;
            font-size: 15px;
            outline: none;
            transition: 0.3s;
            box-sizing: border-box;
        }
        .form-input:focus { 
            border-color: #00ffaa; 
            background: rgba(0, 0, 0, 0.4);
            box-shadow: 0 0 15px rgba(0, 255, 170, 0.15); 
        }
        .form-input[readonly] { 
            background: rgba(255, 255, 255, 0.03); 
            border-color: transparent; 
            color: #888; 
            cursor: default;
        }
        
        textarea.form-input { resize: vertical; min-height: 100px; }
        
        .save-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(45deg, #00ffaa, #00cc88);
            color: #0a1f16;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 15px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            box-shadow: 0 5px 15px rgba(0, 255, 170, 0.3);
            user-select: none;
        }
        .save-btn:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 25px rgba(0, 255, 170, 0.5); 
            filter: brightness(1.1);
        }
        .save-btn:active { transform: translateY(-1px); }

        .alert { 
            padding: 15px; 
            border-radius: 12px; 
            margin-bottom: 25px; 
            text-align: center; 
            font-size: 14px; 
            font-weight: 600;
            animation: fadeIn 0.3s;
        }
        .alert.success { background: rgba(0, 255, 170, 0.15); color: #00ffaa; border: 1px solid #00ffaa; }
        .alert.error { background: rgba(255, 50, 50, 0.15); color: #ff5555; border: 1px solid #ff5555; }
        
        /* Profile Stats Enhancement */
        .profile-stats { display: flex; justify-content: space-around; margin-bottom: 25px; background: rgba(0,0,0,0.2); padding: 15px; border-radius: 15px; border: 1px solid rgba(0, 255, 170, 0.1); backdrop-filter: blur(5px); user-select: none; }
        .stat-box { text-align: center; position: relative; }
        .stat-box:not(:last-child)::after { content: ''; position: absolute; right: -20px; top: 10%; height: 80%; width: 1px; background: rgba(255,255,255,0.1); }
        .stat-num { display: block; font-size: 20px; font-weight: 800; color: #fff; text-shadow: 0 0 10px rgba(0, 255, 170, 0.5); }
        .stat-label { font-size: 11px; color: #00ffaa; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-top: 2px; }

        /* --- New Tabs Styling --- */
        .full-width-card {
            background: #1a3d2f;
            border-radius: 15px;
            padding: 30px;
            border: 1px solid rgba(0, 255, 170, 0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: fadeIn 0.5s ease;
        }
        .photo-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .photo-item {
            aspect-ratio: 1 / 1;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
            cursor: pointer;
        }
        .photo-item:hover { border-color: #00ffaa; transform: scale(1.02); transition: 0.3s; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Scroll Top Button */
        .scroll-top-btn { position: fixed; bottom: 30px; right: 30px; width: 45px; height: 45px; background: #00ffaa; color: #0a1f16; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; cursor: pointer; box-shadow: 0 0 20px rgba(0, 255, 170, 0.4); opacity: 0; pointer-events: none; transition: 0.3s; z-index: 99; border: none; }
        .scroll-top-btn.visible { opacity: 1; pointer-events: auto; }
        .scroll-top-btn:hover { transform: translateY(-5px); box-shadow: 0 0 30px rgba(0, 255, 170, 0.6); }

        /* Custom Scrollbar for the page */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0a1f16; }
        ::-webkit-scrollbar-thumb { background: #1a3d2f; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #00ffaa; }

        /* My Posts Section */
        .my-posts-section { margin-top: 0; border-top: none; padding-top: 0; }
        .section-title { color: #00ffaa; font-size: 20px; margin-bottom: 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid #00ffaa; padding-bottom: 10px; display: inline-block; text-shadow: 0 0 10px rgba(0, 255, 170, 0.3); transition: 0.3s; cursor: pointer; }
        .section-title:hover { color: #fff; border-bottom-color: #fff; transform: translateX(5px); }
        
        /* Post Styles matching SacliConnect.php */
        .post { background: #1a3d2f; padding: 15px; border-radius: 10px; border: 1px solid rgba(0, 255, 170, 0.1); color: #e4e6eb; box-shadow: 0 2px 5px rgba(0,0,0,0.2); margin-bottom: 20px; position: relative; animation: fadeInUp 0.5s ease backwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .post:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.3); border-color: rgba(0,255,170,0.3); transition: all 0.3s ease; }
        .post-profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px; border: 2px solid #00ffaa; }
        .tagged-user, a.tagged-user { 
            color: #00ffaa !important; 
            font-weight: bold; 
            background: rgba(0, 255, 170, 0.15); 
            padding: 2px 4px; 
            border-radius: 4px;
            text-decoration: none;
        }
        
        .post-delete-btn { position: absolute; top: 15px; right: 15px; color: #ff5555; background: rgba(255, 85, 85, 0.1); border: 1px solid rgba(255, 85, 85, 0.3); width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.2s; font-size: 14px; }
        .post-delete-btn:hover { background: #ff5555; color: white; transform: scale(1.1); }
        .post-delete-btn { user-select: none; }

        /* Post Interactions */
        .post-actions { display: flex; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 15px; padding-top: 10px; }
        .action-btn { flex: 1; background: transparent; border: none; color: #b0b3b8; cursor: pointer; padding: 8px; border-radius: 5px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 5px; transition: 0.2s; user-select: none; }
        .action-btn:hover { background: rgba(255,255,255,0.1); }
        .action-btn.liked { color: #e41e3f; } /* Red Heart */
        
        .comment-section { margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px; }
        .comment-input-area { display: flex; gap: 10px; margin-bottom: 10px; }
        .comment-input { flex: 1; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 8px 12px; color: white; outline: none; }
        .comment-item {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 13px;
            align-items: flex-start;
        }
        .comment-item:hover .comment-actions {
            opacity: 1;
        }
        .comment-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .comment-bubble { background: rgba(255,255,255,0.1); padding: 8px 12px; border-radius: 15px; color: #e4e6eb; }
        .comment-bubble strong { display: block; color: #00ffaa; font-size: 12px; margin-bottom: 2px; }
        .comment-bubble p { margin: 0; }

        /* Comment Actions (Delete) */
        .comment-actions {
            position: relative; /* For menu positioning */
            opacity: 0;
            transition: opacity 0.2s;
            padding-top: 8px;
        }
        .dots-btn {
            cursor: pointer;
            font-weight: bold;
            color: #aaa;
            padding: 0 5px;
            border-radius: 50%;
            background: transparent;
            border: none;
            font-size: 16px;
        }
        .comment-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 25px; /* Position below the dots */
            background: #102e22;
            border: 1px solid #00ffaa;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.5);
            padding: 5px;
            z-index: 10;
            width: 80px;
        }
        .comment-actions:hover .comment-menu { display: block; }
        .comment-menu-item { padding: 5px 10px; cursor: pointer; color: #e4e6eb; border-radius: 3px; font-size: 13px; }
        .comment-menu-item:hover { background: #00ffaa; color: #000; }

        /* MULTI-MEDIA GRID STYLES */
        .media-grid {
            display: grid;
            gap: 2px;
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        .media-grid.grid-1 { grid-template-columns: 1fr; }
        .media-grid.grid-1 .media-item { height: auto; max-height: 500px; }

        .media-grid.grid-2 { grid-template-columns: 1fr 1fr; }
        .media-grid.grid-2 .media-item { height: 300px; }

        .media-grid.grid-3 { grid-template-columns: 1fr 1fr; grid-template-rows: 250px 250px; }
        .media-grid.grid-3 .media-item:first-child { grid-column: span 2; }

        .media-grid.grid-4 { grid-template-columns: 1fr 1fr; grid-template-rows: 200px 200px; }

        .media-grid.grid-5 { grid-template-columns: repeat(6, 1fr); grid-template-rows: 250px 200px; }
        .media-grid.grid-5 .media-item:nth-child(1), .media-grid.grid-5 .media-item:nth-child(2) { grid-column: span 3; }
        .media-grid.grid-5 .media-item:nth-child(3), .media-grid.grid-5 .media-item:nth-child(4), .media-grid.grid-5 .media-item:nth-child(5) { grid-column: span 2; }
        
        .media-item {
            position: relative;
            height: 100%;
            background: #000;
            cursor: pointer;
        }

        .media-item img, .media-item video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .more-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.6);
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 24px;
            font-weight: bold;
            backdrop-filter: blur(2px);
            z-index: 20;
        }
        
        /* Video Player Styles (Copied from SacliConnect.php) */
        .video-interface {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 15px 15px 10px;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 5;
        }
        .media-item:hover .video-interface { opacity: 1; }
        
        .video-seek-slider {
            -webkit-appearance: none; appearance: none; width: 100%; height: 4px; background: rgba(255,255,255,0.3); border-radius: 2px; outline: none; cursor: pointer; transition: height 0.1s;
        }
        .video-seek-slider:hover { height: 6px; }
        .video-seek-slider::-webkit-slider-thumb { -webkit-appearance: none; width: 12px; height: 12px; background: #00ffaa; border-radius: 50%; cursor: pointer; transition: transform 0.2s; border: none; }
        .video-seek-slider:hover::-webkit-slider-thumb { transform: scale(1.3); }

        .video-bottom-row { display: flex; justify-content: space-between; align-items: center; }
        .video-time { color: #fff; font-size: 12px; font-family: sans-serif; font-weight: 500; text-shadow: 0 1px 2px rgba(0,0,0,0.5); }
        .video-volume-control { display: flex; align-items: center; gap: 10px; }

        .video-mute-button { width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; background: transparent; border: none; padding: 0; }
        .video-mute-button svg { width: 20px; height: 20px; fill: white; transition: fill 0.2s; }
        .video-mute-button:hover svg { fill: #00ffaa; }
        .volume-slider {
            -webkit-appearance: none;
            appearance: none;
            width: 60px;
            height: 4px;
            background: rgba(255,255,255,0.3);
            border-radius: 2px;
            outline: none;
            transition: width 0.3s ease;
            cursor: pointer;
        }
        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none; appearance: none; width: 12px; height: 12px; border-radius: 50%; background: #00ffaa; cursor: pointer; border: 2px solid #fff;
        }
        
        /* VIDEO VIEWER MODAL (Facebook Style) */
        .video-modal { display: none; position: fixed; z-index: 20000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.95); overflow: hidden; animation: fadeIn 0.3s; }
        .video-modal-content { display: flex; width: 100%; height: 100%; }
        .video-section { flex: 1; background: #000; display: flex; align-items: center; justify-content: center; position: relative; }
        .video-section video { max-width: 100%; max-height: 100%; width: auto; height: auto; outline: none; box-shadow: 0 0 50px rgba(0,0,0,0.5); }
        .details-section { width: 360px; background: #1a3d2f; border-left: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; height: 100%; position: relative; z-index: 2; }
        .details-header { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; gap: 10px; background: #1a3d2f; }
        .details-header img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #00ffaa; }
        .details-header h4 { margin: 0; color: #fff; font-size: 14px; font-weight: 600; }
        .details-header span { font-size: 12px; color: #aaa; }
        .details-body { flex: 1; overflow-y: auto; padding: 15px; display: flex; flex-direction: column; }
        .details-body::-webkit-scrollbar { width: 5px; } .details-body::-webkit-scrollbar-thumb { background: #00ffaa; border-radius: 5px; }
        .viewer-caption { color: #e4e6eb; font-size: 14px; margin-bottom: 15px; line-height: 1.4; white-space: pre-wrap; }
        .viewer-stats { color: #aaa; font-size: 12px; margin-bottom: 10px; display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 10px; }
        .viewer-actions { display: flex; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin-bottom: 10px; gap: 5px; }
        .viewer-action-btn { flex: 1; background: rgba(255,255,255,0.05); border: none; color: #b0b3b8; padding: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 5px; border-radius: 5px; transition: 0.2s; }
        .viewer-action-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .viewer-action-btn.liked { color: #e41e3f; background: rgba(228, 30, 63, 0.1); }
        .viewer-comments { flex: 1; overflow-y: auto; }
        .details-footer { padding: 15px; border-top: 1px solid rgba(255,255,255,0.1); background: #102e22; }
        .viewer-input { width: 100%; padding: 12px 15px; border-radius: 20px; border: 1px solid rgba(0, 255, 170, 0.3); background: rgba(0,0,0,0.2); color: #fff; outline: none; transition: 0.3s; }
        .viewer-input:focus { border-color: #00ffaa; background: rgba(0,0,0,0.4); }
        .close-video-modal { position: absolute; top: 20px; left: 20px; color: #fff; font-size: 24px; cursor: pointer; z-index: 20001; background: rgba(255,255,255,0.1); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: 0.2s; backdrop-filter: blur(5px); }
        .close-video-modal:hover { background: rgba(255,255,255,0.2); transform: scale(1.1); }
        .view-count-badge { display: inline-flex; align-items: center; gap: 4px; color: #b0b3b8; font-size: 12px; }
        .video-expand-btn { color: white; cursor: pointer; font-size: 18px; margin-left: 10px; display: flex; align-items: center; }
        .video-expand-btn:hover { color: #00ffaa; transform: scale(1.1); }
        
        @media (max-width: 900px) {
            .video-modal-content { flex-direction: column; }
            .video-section { height: 40%; flex: none; }
            .details-section { width: 100%; height: 60%; border-left: none; border-top: 1px solid rgba(255,255,255,0.1); }
        }

        .video-play-icon::after {
            content: '';
            display: block;
            width: 0;
            height: 0;
            border-top: 12px solid transparent;
            border-bottom: 12px solid transparent;
            border-left: 20px solid white;
            margin-left: 5px;
        }
        .media-item:hover .video-play-icon {
            transform: translate(-50%, -50%) scale(1.1);
            background: rgba(0, 255, 170, 0.5);
            border-color: #fff;
        }
        .video-interface {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 15px 15px 10px;
            background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 5;
        }
        .media-item:hover .video-interface { opacity: 1; }
        
        .video-seek-slider {
            -webkit-appearance: none; appearance: none; width: 100%; height: 4px; background: rgba(255,255,255,0.3); border-radius: 2px; outline: none; cursor: pointer; transition: height 0.1s;
        }
        .video-seek-slider:hover { height: 6px; }
        .video-seek-slider::-webkit-slider-thumb { -webkit-appearance: none; width: 12px; height: 12px; background: #00ffaa; border-radius: 50%; cursor: pointer; transition: transform 0.2s; border: none; }
        .video-seek-slider:hover::-webkit-slider-thumb { transform: scale(1.3); }

        .video-bottom-row { display: flex; justify-content: space-between; align-items: center; }
        .video-time { color: #fff; font-size: 12px; font-family: sans-serif; font-weight: 500; text-shadow: 0 1px 2px rgba(0,0,0,0.5); }
        .video-volume-control { display: flex; align-items: center; gap: 10px; }

        .video-mute-button { width: 24px; height: 24px; cursor: pointer; display: flex; align-items: center; justify-content: center; background: transparent; border: none; padding: 0; }
        .video-mute-button svg { width: 20px; height: 20px; fill: white; transition: fill 0.2s; }
        .video-mute-button:hover svg { fill: #00ffaa; }
        .volume-slider {
            -webkit-appearance: none;
            appearance: none;
            width: 60px;
            height: 4px;
            background: rgba(255,255,255,0.3);
            border-radius: 2px;
            outline: none;
            transition: width 0.3s ease;
            cursor: pointer;
        }
        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none; appearance: none; width: 12px; height: 12px; border-radius: 50%; background: #00ffaa; cursor: pointer; border: 2px solid #fff;
        }

        /* Lightbox Modal */
        .modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.95); }

        @media (max-width: 900px) {
            .profile-grid { grid-template-columns: 1fr; }
            .header-content-bar { flex-direction: column; align-items: center; text-align: center; margin-top: -100px; padding: 0 20px 20px; }
            .header-actions { align-self: center; margin-top: 10px; }
            .cover-area { height: 250px; }
            .pfp-container { width: 150px; height: 150px; }
        }

        /* Floating Button para sa Zoom Mode (Un-zoom) */
        #focusModeBtnZoomed {
            position: fixed;
            top: 20px; 
            right: 20px;
            background: #00ffaa;
            border: 1px solid #00ffaa;
            color: #0a1f16;
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: flex; 
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            z-index: 10005; 
            box-shadow: 0 0 20px rgba(0, 255, 170, 0.4);
        }
        #focusModeBtnZoomed:hover { transform: scale(1.1); box-shadow: 0 0 30px rgba(0, 255, 170, 0.6); }

        /* Zoom Effect Styles (Focus Mode) */
        .profile-wrapper.scrolled-past .profile-grid {
            grid-template-columns: 1fr !important;
            gap: 0 !important;
        }
        .profile-wrapper.scrolled-past .profile-sidebar-col {
            display: none !important;
        }
        /* Itago ang header para rekta sa activity ang focus */
        .profile-wrapper.scrolled-past .profile-header-card {
            display: none !important;
        }
        .profile-wrapper.scrolled-past .post {
            max-width: 1000px !important;
            margin-left: auto;
            margin-right: auto;
            transform: scale(1.02);
            background: rgba(26, 61, 47, 0.4) !important;
            backdrop-filter: blur(15px) saturate(150%);
            border: 1px solid rgba(0, 255, 170, 0.3) !important;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.5);
        }

        /* Flash Message Style */
        .flash-message {
            position: fixed;
            top: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            background: rgba(0, 255, 170, 0.95);
            color: #0a1f16;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: bold;
            z-index: 20000;
            box-shadow: 0 5px 20px rgba(0,0,0,0.4);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            pointer-events: none;
            border: 1px solid #fff;
            font-size: 14px;
        }
        .flash-message.show { opacity: 1; transform: translateX(-50%) translateY(0); }

        /* --- Sidebar Photos Grid (Better than Facebook) --- */
        .sidebar-photos-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        .sidebar-photo-thumb {
            aspect-ratio: 1;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(0, 255, 170, 0.2);
            position: relative;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background: #000;
        }
        .sidebar-photo-thumb img, .sidebar-photo-thumb video {
            width: 100%; height: 100%; object-fit: cover;
            transition: transform 0.5s ease;
        }
        .sidebar-photo-thumb:hover {
            transform: scale(1.05);
            border-color: #00ffaa;
            box-shadow: 0 0 15px rgba(0, 255, 170, 0.4);
            z-index: 2;
        }
        .sidebar-photo-thumb:hover img { transform: scale(1.15); }
        .sidebar-photo-thumb::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(0, 255, 170, 0.3), transparent);
            opacity: 0; transition: 0.3s;
        }
        .sidebar-photo-thumb:hover::after { opacity: 1; }
        .play-overlay-mini { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; font-size: 14px; text-shadow: 0 0 10px #00ffaa; pointer-events: none; opacity: 0.8; }
    </style>
</head>
<body>

    <div id="flashMsg" class="flash-message"></div>

    <?php if ($message): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showFlash("<?php echo addslashes($message); ?>", "<?php echo $msg_type; ?>");
        });
    </script>
    <?php endif; ?>

    <div class="background-pattern"></div>
    <img class="background-logo" src="assets/images/St.Anne_logo.png" alt="">

    <!-- Main Holographic Header -->
    <header>
        <div class="hd-left">
            <img src="assets/images/St.Anne_logo.png" class="hd-logo" alt="Sacli Logo" onclick="window.location.href='SacliConnect.php'">
            <div class="hd-search" onclick="this.classList.toggle('active')" style="position: relative; background: var(--bg-light); border-radius: 50px; padding: 0 12px; height: 40px; display: flex; align-items: center; width: 240px; transition: width 0.3s ease;">
                <span style="color:#aaa;">🔍</span>
                <input type="text" id="headerSearch" placeholder="Search students..." autocomplete="off" style="background:transparent; border:none; color:var(--text-color); margin-left:8px; outline:none; width:100%;">
                <div id="searchResults" class="search-results"></div>
            </div>
            <button class="create-gc-btn" onclick="openCreateGroupModal()">+ Create GC</button>

            <div style="display:flex; gap:8px; margin-left:15px; overflow-x:auto; max-width:300px; align-items:center; scrollbar-width: none;" class="header-gc-list">
                <?php
                $my_id = $_SESSION['student_id'];
                $header_groups = $conn->query("SELECT g.id, g.name, g.group_icon FROM group_chats g JOIN group_chat_members m ON g.id = m.group_id WHERE m.user_id = '$my_id' ORDER BY g.created_at DESC");
                if($header_groups){
                    while($hg = $header_groups->fetch_assoc()){
                        $hg_pic = !empty($hg['group_icon']) ? "uploads/".$hg['group_icon'] : "7icons8-organization-64.png";
                        $hg_name = htmlspecialchars($hg['name'], ENT_QUOTES);
                        echo '<div onclick="openGroupChat('.$hg['id'].', \''.$hg_name.'\', \''.$hg_pic.'\')" title="'.$hg_name.'" style="cursor:pointer; width:34px; height:34px; border-radius:50%; border:1px solid #00ffaa; overflow:hidden; flex-shrink:0; transition:0.2s;">
                                <img src="'.$hg_pic.'" style="width:100%; height:100%; object-fit:cover;">
                              </div>';
                    }
                }
                ?>
            </div>
        </div>

        <div class="hd-right">
        <div class="hd-profile" style="display:flex; align-items:center; gap:8px; cursor:pointer;" onclick="window.location.href='Student_Profile.php'">
            <img src="<?php echo !empty($me['profile_pic']) ? 'uploads/'.$me['profile_pic'] : 'assets/images/3icons8-student-64.png'; ?>" style="width:28px; height:28px; border-radius:50%; object-fit:cover; border:1.5px solid #00ffaa;">
            <div style="display: flex; flex-direction: column; align-items: flex-start; justify-content: center;">
                <span style="color:white; font-size:14px; font-weight:600; line-height: 1.2;"><?php echo htmlspecialchars($me['student_name']); ?></span>
                <small style="font-size: 10px; color: var(--accent); font-weight: bold; opacity: 0.8; line-height: 1;"><?php echo htmlspecialchars($user_sub_info); ?></small>
            </div>
        </div>

            <div class="icon-wrapper" onclick="toggleDropdown('groupDropdown'); loadMyGroups();">
                <div class="hd-icon-btn group-btn">
                    <div class="shimmer"></div>
                    <img src="group.png" alt="group">
                </div>
                <span class="badge-count" id="groupBadge" style="display:none;">0</span>
            </div>

            <div class="icon-wrapper" onclick="toggleDropdown('msgDropdown'); loadMessagesDropdown();">
                <div class="hd-icon-btn msg-btn">
                    <div class="shimmer"></div>
                    <img src="communication.png" alt="communication">
                </div>
                <span class="badge-count" id="msgCount" style="display:none;">0</span>
            </div>
            
            <div class="icon-wrapper" onclick="toggleDropdown('notifDropdown'); loadNotifications();">
                <div class="hd-icon-btn notif-btn">
                    <div class="shimmer"></div>
                    <img src="notification-bell.png" alt="notification">
                </div>
                <span class="badge-count" id="notifCount" style="display:none;">0</span>
            </div>

            <div class="icon-wrapper" onclick="openConcernChat()" title="Tell a Concern">
                <div class="hd-icon-btn concern-btn">
                    <div class="shimmer"></div>
                    <img src="important.png" alt="Help">
                </div>
                <span class="badge-count" id="concernBadge" style="display:none;">0</span>
            </div>
        </div>
    </header>

    <!-- Dropdown Menus -->
    <div id="groupDropdown" class="dropdown-popover" style="right: 160px;">
        <div class="dp-header">Group Chats</div>
        <div class="dp-item" onclick="openCreateGroupModal()" style="color:#00ffaa; justify-content:center; font-weight:bold;">+ Create New Group</div>
        <div id="groupListContainer" style="max-height:300px; overflow-y:auto;"></div>
    </div>

    <div id="msgDropdown" class="dropdown-popover" style="right: 110px;">
        <div class="dp-header">Messages</div>
        <div id="msgListContainer" style="max-height:300px; overflow-y:auto;">
            <div style="padding:15px; text-align:center; color:#aaa;">Loading...</div>
        </div>
    </div>

    <div id="notifDropdown" class="dropdown-popover" style="right: 60px;">
        <div class="dp-header">Notifications</div>
        <div id="notifListContainer" style="max-height:300px; overflow-y:auto;">
            <div style="padding:15px; text-align:center; color:#aaa;">Loading...</div>
        </div>
    </div>

    <div class="profile-wrapper">
        <button id="focusModeBtnZoomed" style="display:none; top: 80px;" onclick="toggleFocusMode()" title="Exit Focus Mode (Un-zoom)">
            <svg class="icon-minimize" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16v3a2 2 0 0 1 2 2v3"></path>
            </svg>
        </button>

        <a href="javascript:void(0)" onclick="window.history.length > 1 ? history.back() : window.location.href='SacliConnect.php'" class="back-btn-modern">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Return
        </a>

        <!-- Facebook Style Header Card -->
        <div class="profile-header-card">
            <div class="cover-area" id="coverArea">
                <?php $cover = !empty($user['cover_photo']) ? "uploads/".$user['cover_photo'] : "Adobe Express - file.png"; ?>
                <img src="<?php echo $cover; ?>" class="cover-photo-img" id="coverPreview" onclick="viewCoverPhoto()" style="top: <?php echo $user['cover_offset'] ?? 0; ?>px;">
                <?php if($is_own_profile): ?>
                    <div class="cover-btn-group">
                        <button class="cover-btn-sub" onclick="viewCoverPhoto()">👁 View Cover</button>
                        <button class="cover-btn-sub" onclick="openRepositionModal()">✥ Reposition</button>
                        <button class="cover-btn-sub" onclick="document.getElementById('coverInput').click()">📷 Change Cover Photo</button>
                    </div>
                <?php endif; ?>
            </div>

<!-- ================= Reposition Modal ================= -->
<div class="modal" id="repositionModal" style="display:none; background: rgba(0,0,0,0.9); z-index: 20000; align-items:center; justify-content:center;">
    <div class="modal-content" style="max-width: 1000px; width: 95%; background: #1a3d2f; border: 1px solid #00ffaa; padding: 0; overflow: hidden; border-radius: 15px;">
        <div style="padding: 15px; border-bottom: 1px solid rgba(0,255,170,0.2); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin:0; color:#00ffaa; font-size: 18px;">Reposition Cover Photo</h3>
            <span class="close" onclick="closeRepositionModal()" style="color:#ff5555; cursor:pointer; font-size:24px;">&times;</span>
        </div>
        <div id="repositionContainer" onmousedown="startDragReposition(event)">
            <img id="repositionImg" src="">
        </div>
        <div style="padding: 20px; display: flex; justify-content: flex-end; gap: 10px; background: rgba(0,0,0,0.2);">
            <button type="button" class="action-btn-pill" style="background: rgba(255,255,255,0.1);" onclick="closeRepositionModal()">Cancel</button>
            <button type="button" class="action-btn-pill" style="background: #00ffaa; color: #0a1f16;" onclick="applyReposition()">Apply Position</button>
        </div>
    </div>
</div>

            <div class="header-content-bar">
                <div class="pfp-container" onclick="<?php echo $is_own_profile ? "document.getElementById('fileInput').click()" : ""; ?>">
                    <?php $pic = !empty($user['profile_pic']) ? "uploads/".$user['profile_pic'] : "assets/images/3icons8-student-64.png"; ?>
                    <img src="<?php echo $pic; ?>" id="previewImg">
                </div>

                <div class="user-identity">
                    <h1><?php echo htmlspecialchars($formatted_name); ?></h1>
                    <p style="text-transform: lowercase;"><?php 
                        if ($is_teacher_profile) echo 'faculty member . ' . htmlspecialchars($user['year_level'] ?? 'n/a');
                        elseif (isset($user['is_alumni']) && $user['is_alumni'] == 1) echo 'alumni . ' . htmlspecialchars($user['course'] ?? '');
                        else echo 'student . ' . htmlspecialchars($user['year_level'] ?? 'n/a');
                    ?></p>
                </div>

                <div class="header-actions">
                    <?php if($is_own_profile): ?>
                        <button class="action-btn-pill" style="background:#00ffaa; color:#0a1f16;" onclick="window.location.href='Edit_Profile.php'">✎ Edit Profile</button>
                        <button class="action-btn-pill" onclick="window.location.href='SacliConnect.php?page=security'">⚙ Settings</button>
                    <?php else: ?>
                        <button class="action-btn-pill" style="background:#00ffaa; color:#0a1f16;" onclick="window.location.href='SacliChat_Full.php?id=<?php echo $target_id; ?>&type=direct'">Message</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="profile-nav-tabs">
                <a href="javascript:void(0)" class="nav-tab active" onclick="switchTab('posts', this)">Posts</a>
                <a href="javascript:void(0)" class="nav-tab" onclick="switchTab('about', this)">About</a>
                <a href="javascript:void(0)" class="nav-tab" onclick="switchTab('friends', this)">Friends</a>
                <a href="javascript:void(0)" class="nav-tab" onclick="switchTab('photos', this)">Photos</a>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="profile-grid tab-content" id="posts-content">
            <!-- Left: Sidebar / Intro -->
            <div class="profile-sidebar-col">
                <div class="intro-card">
                    <h3>Intro</h3>
                    <div style="font-size: 15px; color: #ccc; margin-bottom: 20px; line-height: 1.6; text-align: center; font-style: italic;">
                        <?php echo !empty($user['bio']) ? '"' . htmlspecialchars($user['bio']) . '"' : "No bio available yet."; ?>
                    </div>
                    
                    <!-- Stats Row -->
                    <?php function formatStat($n) { return ($n >= 1000) ? round($n/1000, 1).'k' : $n; } ?>
                    <div class="profile-stats">
                        <div class="stat-box"><span class="stat-num"><?php echo formatStat($total_posts); ?></span><span class="stat-label">Posts</span></div>
                        <div class="stat-box"><span class="stat-num"><?php echo formatStat($total_likes); ?></span><span class="stat-label">Likes</span></div>
                        <div class="stat-box"><span class="stat-num"><?php echo formatStat($total_views); ?></span><span class="stat-label">Views</span></div>
                    </div>

                    <div id="static-info-sidebar">
                        <div class="detail-item"><span class="detail-icon">🏷️</span> <?php echo $is_teacher_profile ? 'Position' : 'Year Level'; ?>: <strong><?php echo htmlspecialchars($user['year_level'] ?? 'N/A'); ?></strong></div>
                        <div class="detail-item"><span class="detail-icon">🎓</span> <?php echo $is_teacher_profile ? 'Department' : 'Course'; ?>: <strong><?php echo htmlspecialchars($user['course'] ?? 'N/A'); ?></strong></div>
                        <div class="detail-item"><span class="detail-icon">📧</span> Email: <strong><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></strong></div>
                        <div class="detail-item"><span class="detail-icon">📅</span> Birthday: <strong><?php echo !empty($user['birthdate']) ? date("F d, Y", strtotime($user['birthdate'])) : 'Not set'; ?></strong></div>
                        <div class="detail-item"><span class="detail-icon">⚧</span> Gender: <strong><?php echo !empty($user['gender']) ? htmlspecialchars($user['gender']) : 'Not set'; ?></strong></div>
                        <div class="detail-item"><span class="detail-icon">📍</span> Location: <strong><?php echo !empty($user['location']) ? htmlspecialchars($user['location']) : 'Not set'; ?></strong></div>
                        <?php if (!($user['hide_phone'] ?? 0)): // Only show if not hidden ?>
                        <div class="detail-item"><span class="detail-icon">📞</span> Phone: <strong><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not set'; ?></strong></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_teacher_profile && !$is_own_profile && !$is_eval_locked): ?>
                        <button type="button" onclick="document.getElementById('profileEvaluationModal').style.display='flex'" class="save-btn" style="background: #00ccff; margin-top: 10px; color:#0a1f16;">Evaluate Teacher</button>
                    <?php endif; ?>
                </div>

                <!-- Photos Sidebar Card (Facebook Style but Better) -->
                <div class="intro-card" style="margin-top: 20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h3 style="margin:0;">Photos</h3>
                        <a href="javascript:void(0)" onclick="switchTab('photos', document.querySelectorAll('.nav-tab')[3])" style="color:#00ffaa; text-decoration:none; font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:1px;">See All</a>
                    </div>
                    <div class="sidebar-photos-grid">
                        <?php
                        // Fetch up to 9 recent media items from user's posts
                        $side_media_q = $conn->query("SELECT m.* FROM post_media m JOIN posts p ON m.post_id = p.id WHERE p.student_name = '$stats_name' ORDER BY m.id DESC LIMIT 9");
                        
                        if($side_media_q && $side_media_q->num_rows > 0):
                            $s_idx = 0;
                            while($sm = $side_media_q->fetch_assoc()):
                        ?>
                            <div class="sidebar-photo-thumb" onclick="openGalleryLightbox(<?php echo $s_idx; ?>)">
                                <?php if($sm['file_type'] == 'video'): ?>
                                    <video src="<?php echo $sm['file_path']; ?>"></video>
                                    <div class="play-overlay-mini">▶</div>
                                <?php else: ?>
                                    <img src="<?php echo $sm['file_path']; ?>" alt="user photo">
                                <?php endif; ?>
                            </div>
                        <?php 
                            $s_idx++;
                            endwhile; 
                        else: 
                        ?>
                            <p style="grid-column: span 3; color:#555; font-size:11px; text-align:center; padding:20px; font-style:italic;">// NO_MEDIA_NODES_FOUND</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: Activity / Timeline -->
            <div class="profile-timeline-col">
                <div class="intro-card" style="width: 92%; position: relative; right: .5%;">
                    <div class="section-title ps-section-title" style="margin-top:0;" onclick="focusOnSection(this)">Timeline Activity</div>
                    <div class="profile-evaluation-actions">

        <?php if ($is_teacher_profile && !$is_own_profile): 
            $real_tid = substr($target_id, 2);
            $chk_eval = $conn->query("SELECT id FROM evaluations WHERE student_id='".$_SESSION['student_id']."' AND teacher_id='$real_tid'");
            $has_evaluated = ($chk_eval && $chk_eval->num_rows > 0);

            if (!$is_eval_locked): // Check if evaluation is NOT locked
                if($has_evaluated):
        ?>
        <form method="POST" id="profileResetForm">
            <input type="hidden" name="action" value="reset_evaluation">
            <input type="hidden" name="teacher_id" value="<?php echo $real_tid; ?>">
            <button type="button" onclick="confirmResetEvaluation('profileResetForm')" class="save-btn" style="background: #ff5555; margin-top: 10px; box-shadow: 0 5px 15px rgba(255, 85, 85, 0.3); color: white;">Reset Evaluation</button>
        </form>
        <?php   else: ?>
            <button type="button" onclick="document.getElementById('profileEvaluationModal').style.display='flex'" class="save-btn" style="background: #00ccff; margin-top: 10px; box-shadow: 0 5px 15px rgba(0, 204, 255, 0.3); color:#0a1f16;">Evaluate Teacher</button>
        <?php   endif; endif; endif; ?>
                    </div> <!-- End profile-evaluation-actions -->
                </div> <!-- End intro-card -->

                <div class="my-posts-section">
                    <div class="section-title ps-section-title" onclick="focusOnSection(this)"><?php echo $is_own_profile ? 'My Activity' : 'Student Activity'; ?></div>
 
                    <?php
                    $my_name = $user['student_name'];
                    $post_stmt = $conn->prepare("SELECT * FROM posts WHERE student_name = ? ORDER BY timestamp DESC");
                    $post_stmt->bind_param("s", $my_name);
                    $post_stmt->execute();
                    $posts = $post_stmt->get_result();
                    
                    echo '<div id="profile-posts-container">';
                    if ($posts->num_rows > 0) {
                        while ($post = $posts->fetch_assoc()) {
                            $post_id = $post['id'];
                            $pic = !empty($user['profile_pic']) ? "uploads/".$user['profile_pic'] : "assets/images/3icons8-student-64.png";
                            
                            
                            // Fetch Tags with IDs
                            $tags_res = $conn->query("SELECT t.student_id as user_id, COALESCE(s.student_name, te.name) as student_name 
                                                      FROM post_tags t 
                                                      LEFT JOIN students s ON t.student_id = s.student_id 
                                                      LEFT JOIN teachers te ON t.student_id = CONCAT('T-', te.id)
                                                      WHERE t.post_id = '$post_id'");
                            $tag_display = "";
                            if($tags_res && $tags_res->num_rows > 0){
                               
                                $tagged_users = [];
                                while($t = $tags_res->fetch_assoc()) $tagged_users[] = $t;
                                $first_tag = $tagged_users[0];
                                $first_tag_name = htmlspecialchars($first_tag['student_name']);
                                $first_tag_id = $first_tag['user_id'];
                                $other_count = count($tagged_users) - 1;
                                $tag_display = " <span style='color:#b0b3b8; font-weight:normal;'>is with</span> <a href='Student_Profile.php?id=$first_tag_id' style='color:#e4e6eb; text-decoration:none; font-weight:bold;'>$first_tag_name</a>";
                                if($other_count > 0) $tag_display .= " <span style='color:#b0b3b8;'>and </span><a href='javascript:void(0)' onclick='openTaggedUsersModal($post_id)' style='color:#b0b3b8; text-decoration:none; font-weight:bold;'>$other_count others</a>";
                            }
    
                            $post_content = htmlspecialchars($post['content']);
                            // Enhanced Mention Highlighting
                            $mentions_res = $conn->query("SELECT t.student_id as user_id, COALESCE(s.student_name, te.name) as student_name 
                                                          FROM post_tags t 
                                                          LEFT JOIN students s ON t.student_id = s.student_id 
                                                          LEFT JOIN teachers te ON t.student_id = CONCAT('T-', te.id)
                                                          WHERE t.post_id = '$post_id'");
                            if($mentions_res) {
                                while($m = $mentions_res->fetch_assoc()) {
                                    $m_name = htmlspecialchars($m['student_name']);
                                    $m_uid = $m['user_id'];
                                    // Gawing case-insensitive ang pag-match sa pangalan
                                    $pattern = '/@' . preg_quote($m_name, '/') . '/i';
                                    $replacement = '<a href="Student_Profile.php?id='.$m_uid.'" class="tagged-user" style="text-decoration:none;">@'.$m_name.'</a>';
                                    $post_content = preg_replace($pattern, $replacement, $post_content);
                                }
                            }
                            // Siguraduhin na hindi ma-o-overwrite ang mga nagawa nang links
                            $post_content = preg_replace('/(?<!">)@([\w\d.-]+)/', '<span class="tagged-user">@$1</span>', $post_content);
                            
                            echo '<div class="post" id="post-'.$post_id.'">';
                            
                            // Delete Button for Own Profile
                            if ($is_own_profile) {
                                echo '<div class="post-delete-btn" onclick="deletePost('.$post_id.')" title="Delete Post">🗑</div>';
                            }
    
                            echo '<div style="display: flex; align-items: center; margin-bottom: 10px;"><img src="'.$pic.'" class="post-profile-img"><div><h4 style="margin: 0; color: #e4e6eb; font-size: 15px;">'.ucwords(strtolower(htmlspecialchars($post['student_name']))).$tag_display.'</h4><span class="time" style="color: #b0b3b8; font-size: 12px;">'.date("M d, Y H:i", strtotime($post['timestamp'])).'</span></div></div>';
                            
                            echo '<div class="post-content-container">';
                            $threshold = 300;
                            if (mb_strlen($post_content) > $threshold) {
                                $truncated = mb_substr($post_content, 0, $threshold);
                                echo '<p class="content-text-truncated" style="white-space: pre-wrap; margin-top: 10px; margin-bottom: 10px; line-height: 1.5;">' . $truncated . '... <span class="see-more-link" onclick="expandPost(this)" style="color: #00ffaa; cursor: pointer; font-weight: bold;">See More...</span></p>';
                                echo '<p class="content-text-full" style="white-space: pre-wrap; margin-top: 10px; margin-bottom: 10px; line-height: 1.5; display: none;">' . $post_content . ' <span class="see-less-link" onclick="collapsePost(this)" style="color: #00ffaa; cursor: pointer; font-weight: bold;">See Less</span></p>';
                            } else {
                                echo '<p style="white-space: pre-wrap; margin-top: 10px; margin-bottom: 10px; line-height: 1.5;">'.$post_content.'</p>';
                            }
                            echo '</div>';
    
                            // --- MULTI-MEDIA DISPLAY LOGIC ---
                            $media_res = $conn->query("SELECT * FROM post_media WHERE post_id='$post_id'");
                            $media_files = [];
                            if($media_res && $media_res->num_rows > 0){
                                while($m = $media_res->fetch_assoc()) $media_files[] = $m;
                            }
    
                            // Backward compatibility (Old single file posts)
                            if(empty($media_files) && !empty($post['media'])){
                                $media_files[] = ['file_path' => $post['media'], 'file_type' => $post['type']];
                            }
    
                            $count = count($media_files);
                            if($count > 0){
                                $display_limit = 5;
                                $layout_count = min($count, 5);
                                $grid_class = 'grid-' . $layout_count;
    
                                echo '<div class="media-grid '.$grid_class.'">';
                                
                                $loop_limit = min($count, $display_limit);
    
                                for($i=0; $i < $loop_limit; $i++){
                                    $file = $media_files[$i];
                                    $path = htmlspecialchars($file['file_path']);
                                    $is_video = ($file['file_type'] == 'video');
                                    $remaining = $count - $display_limit;
                                    $show_overlay = ($i == $display_limit - 1 && $remaining > 0);
    
                                    // For videos, open video viewer. For images, open lightbox.
                                    $onclick_attr = $is_video ? 'onclick="openVideoViewer('.$post_id.', \''.$path.'\')"' : 'onclick="openLightbox('.$post_id.', '.$i.')"';
                                    
                                    echo '<div class="media-item" '.$onclick_attr.'>';
                                    if ($is_video) {
                                        echo '<video class="feed-video" src="'.$path.'" loop playsinline autoplay muted ontimeupdate="updateProgress(this)" onloadedmetadata="updateProgress(this)"></video>';
                                        echo '<div class="video-play-icon" onclick="event.stopPropagation(); togglePlay(this)">▶</div>';
                                        echo '<div class="video-interface" onclick="event.stopPropagation()">
                                                <input type="range" class="video-seek-slider" min="0" max="100" value="0" oninput="seekVideo(this)">
                                                <div class="video-bottom-row">
                                                    <span class="video-time">0:00 / 0:00</span>
                                                    <div class="video-volume-control">
                                                        <div class="video-mute-button" onclick="toggleMute(this)"><svg class="icon-muted-state" style="display:none;" viewBox="0 0 24 24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"></path></svg><svg class="icon-unmuted-state" style="display:block;" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"></path></svg></div>
                                                        <input type="range" class="volume-slider" min="0" max="1" step="0.1" value="0" oninput="setVolume(this)">
                                                        <div class="video-expand-btn" onclick="openVideoViewer('.$post_id.', \''.$path.'\')">⛶</div>
                                                    </div>
                                                </div>
                                              </div>';
                                    } else {
                                        echo '<img src="'.$path.'">';
                                    }
                                    if($show_overlay) echo '<div class="more-overlay">+'.$remaining.'</div>';
                                    echo '</div>';
                                }
                                echo '</div>';
                                // Store data for Lightbox
                                echo "<script>if(typeof window['post_media_".$post_id."'] === 'undefined') { window['post_media_".$post_id."'] = ".json_encode($media_files)."; }</script>";
                            }
    
                            // --- INTERACTIONS (Like & Comment) ---
                            $my_id = $_SESSION['student_id'];
                            $liked = $conn->query("SELECT id FROM post_reactions WHERE post_id='$post_id' AND student_id='$my_id'")->num_rows > 0;
                            $like_count = $conn->query("SELECT id FROM post_reactions WHERE post_id='$post_id'")->num_rows;
                            $like_class = $liked ? 'liked' : '';
                            
                            echo '<div class="post-actions">';
                            echo '<button class="action-btn '.$like_class.'" onclick="toggleLike(this, '.$post_id.')">
                                    <span class="heart-icon">'.($liked ? '❤️' : '🤍').'</span> 
                                    <span class="like-count">'.($like_count > 0 ? $like_count : 'Like').'</span>
                                  </button>';
                            echo '<button class="action-btn" onclick="document.getElementById(\'comment-input-'.$post_id.'\').focus()">💬 Comment</button>';
                            echo '</div>';
    
                            // Comments Section
                            echo '<div class="comment-section">';
                            echo '<div class="comment-list" id="comment-list-'.$post_id.'">';
                            // Load existing comments
                            $comments = $conn->query("SELECT c.*, COALESCE(s.student_name, t.name) as student_name, COALESCE(s.profile_pic, t.profile_pic) as profile_pic FROM post_comments c LEFT JOIN students s ON c.student_id = s.student_id LEFT JOIN teachers t ON c.student_id = CONCAT('T-', t.id) WHERE c.post_id='$post_id' ORDER BY c.timestamp ASC");
                            while($c = $comments->fetch_assoc()){
                                $c_pic = !empty($c['profile_pic']) ? "uploads/".$c['profile_pic'] : "assets/images/3icons8-student-64.png";
                                $is_my_comment = ($c['student_id'] == $my_id);
    
                                echo '<div class="comment-item" id="comment-'.$c['id'].'">
                                        <img src="'.$c_pic.'" class="comment-avatar">
                                        <div class="comment-bubble">
                                            <strong>'.ucwords(strtolower(htmlspecialchars($c['student_name']))).'</strong>
                                            <p>'.htmlspecialchars($c['comment']).'</p>
                                        </div>';
                                
                                if ($is_my_comment) {
                                    echo '<div class="comment-actions">
                                            <button class="dots-btn">•••</button>
                                            <div class="comment-menu">
                                                <div class="comment-menu-item" onclick="deleteComment('.$c['id'].')">Delete</div>
                                            </div>
                                          </div>';
                                }
                                echo '</div>'; // End of .comment-item
                            }
                            echo '</div>';
                            echo '<div class="comment-input-area">
                                    <input type="text" id="comment-input-'.$post_id.'" class="comment-input" placeholder="Write a comment..." onkeypress="handleComment(event, '.$post_id.')">
                                  </div>';
                            echo '</div>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p style="text-align:center; color:#888; font-size:14px; margin-top:50px;">No posts yet.</p>';
                    }
                    echo '</div>'; // End #profile-posts-container
                    $post_stmt->close(); ?>
                </div> <!-- End .my-posts-section -->
            </div> <!-- End .profile-timeline-col -->
        </div> <!-- End .profile-grid -->

        <!-- ================= About Tab ================= -->
        <div class="tab-content" id="about-content" style="display:none;">
            <div class="full-width-card">
                <h3 style="color:#00ffaa; margin-top:0; border-bottom:1px solid rgba(0,255,170,0.2); padding-bottom:15px;">About Information</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:30px; margin-top:20px;">
                    <div>
                        <div class="detail-item"><span class="detail-icon">👤</span> Name: <strong><?php echo htmlspecialchars($formatted_name); ?></strong></div>
                        <div class="detail-item"><span class="detail-icon">🎓</span> <?php echo $is_teacher_profile ? 'Dept' : 'Course'; ?>: <strong><?php echo htmlspecialchars($user['course'] ?? 'N/A'); ?></strong></div>
                        <div class="detail-item"><span class="detail-icon">🏷️</span> <?php echo $is_teacher_profile ? 'Position' : 'Year'; ?>: <strong><?php echo htmlspecialchars($user['year_level'] ?? 'N/A'); ?></strong></div>
                        <div class="detail-item"><span class="detail-icon">📧</span> Email: <strong><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></strong></div>
                    </div>
                    <div>
                        <div class="detail-item"><span class="detail-icon">📅</span> Birthday: <strong><?php echo !empty($user['birthdate']) ? date("F d, Y", strtotime($user['birthdate'])) : 'Not set'; ?></strong></div>
                        <div class="detail-item"><span class="detail-icon">⚧</span> Gender: <strong><?php echo !empty($user['gender']) ? htmlspecialchars($user['gender']) : 'Not set'; ?></strong></div>
                        <div class="detail-item"><span class="detail-icon">📍</span> Location: <strong><?php echo !empty($user['location']) ? htmlspecialchars($user['location']) : 'Not set'; ?></strong></div>
                        <?php if (isset($user['created_at'])): ?>
                            <div class="detail-item"><span class="detail-icon">🚀</span> Joined: <strong><?php echo date("F Y", strtotime($user['created_at'])); ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="margin-top:30px; padding-top:20px; border-top:1px solid rgba(255,255,255,0.05);">
                    <h4 style="color:#00ffaa;">Biography</h4>
                    <p style="font-style:italic; color:#ccc; line-height:1.6;"><?php echo !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : "No biography added yet."; ?></p>
                </div>
            </div>
        </div>

        <!-- ================= Friends (Classmates) Tab ================= -->
        <div class="tab-content" id="friends-content" style="display:none;">
            <div class="full-width-card">
                <h3 style="color:#00ffaa; margin-top:0; border-bottom:1px solid rgba(0,255,170,0.2); padding-bottom:15px;">Classmates & Mutuals</h3>
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap:20px; margin-top:20px;">
                    <?php
                    $course = $conn->real_escape_string($user['course']);
                    $friends_sql = "SELECT * FROM students WHERE course = '$course' AND student_id != '$target_id' LIMIT 12";
                    $friends_res = $conn->query($friends_sql);
                    if($friends_res && $friends_res->num_rows > 0):
                        while($f = $friends_res->fetch_assoc()):
                            $f_pic = !empty($f['profile_pic']) ? "uploads/".$f['profile_pic'] : "assets/images/3icons8-student-64.png";
                    ?>
                        <div onclick="window.location.href='Student_Profile.php?id=<?php echo $f['student_id']; ?>'" style="display:flex; align-items:center; gap:15px; background:rgba(0,0,0,0.2); padding:15px; border-radius:12px; border:1px solid rgba(255,255,255,0.05); cursor:pointer;" onmouseover="this.style.borderColor='#00ffaa'" onmouseout="this.style.borderColor='rgba(255,255,255,0.05)'">
                            <img src="<?php echo $f_pic; ?>" style="width:60px; height:60px; border-radius:10px; object-fit:cover;">
                            <div>
                                <div style="font-weight:bold; color:#fff;"><?php echo ucwords(strtolower(htmlspecialchars($f['student_name']))); ?></div>
                                <small style="color:#00ffaa;"><?php echo htmlspecialchars($f['year_level']); ?></small>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <p style="color:#888;">No classmates found in this department.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ================= Photos Tab ================= -->
        <div class="tab-content" id="photos-content" style="display:none;">
            <div class="full-width-card">
                <h3 style="color:#00ffaa; margin-top:0; border-bottom:1px solid rgba(0,255,170,0.2); padding-bottom:15px;">Photos & Videos</h3>
                <div class="photo-gallery" id="profile-media-gallery">
                    <?php
                    // Fetch all media from user's posts
                    $media_sql = "SELECT m.* FROM post_media m JOIN posts p ON m.post_id = p.id WHERE p.student_name = '$stats_name' ORDER BY m.id DESC";
                    $media_res = $conn->query($media_sql);
                    
                    // Prepare gallery for lightbox
                    $gallery_data = [];
                    if($media_res && $media_res->num_rows > 0):
                        $idx = 0;
                        while($m = $media_res->fetch_assoc()):
                            $gallery_data[] = $m;
                    ?>
                        <div class="photo-item" onclick='openGalleryLightbox(<?php echo $idx; ?>)'>
                            <?php if($m['file_type'] == 'video'): ?>
                                <video src="<?php echo $m['file_path']; ?>" style="width:100%; height:100%; object-fit:cover;"></video>
                                <div style="position:absolute; top:10px; right:10px; background:rgba(0,0,0,0.5); border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; font-size:10px;">▶️</div>
                            <?php else: ?>
                                <img src="<?php echo $m['file_path']; ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php endif; ?>
                        </div>
                    <?php $idx++; endwhile; ?>
                        <script>window.userGallery = <?php echo json_encode($gallery_data); ?>;</script>
                    <?php else: ?>
                        <p style="color:#888; grid-column: 1/-1; text-align:center; padding:40px;">No photos or videos shared yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div> <!-- End .profile-wrapper -->

<!-- ================= Lightbox Modal ================= -->
<div class="modal" id="lightboxModal">
    <span class="close" onclick="closeLightbox()" style="color: white; position: absolute; top: 20px; right: 30px; font-size: 40px; cursor: pointer; z-index: 1001;">&times;</span>
    
    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; position: relative;">
        <button onclick="prevSlide()" style="position: absolute; left: 20px; background: rgba(255,255,255,0.1); color: white; border: none; padding: 15px; font-size: 24px; cursor: pointer; border-radius: 50%; z-index: 1000;">&#10094;</button>
        
        <div id="lightboxContent" style="max-width: 90%; max-height: 90%; display: flex; justify-content: center;">
            <!-- Image/Video goes here -->
        </div>

        <button onclick="nextSlide()" style="position: absolute; right: 20px; background: rgba(255,255,255,0.1); color: white; border: none; padding: 15px; font-size: 24px; cursor: pointer; border-radius: 50%; z-index: 1000;">&#10095;</button>
    </div>
    <div id="lightboxCounter" style="position: absolute; bottom: 20px; color: white; font-size: 16px; width: 100%; text-align: center;"></div>
</div>

<!-- ================= Evaluation Modal (From Profile) ================= -->
<?php if ($is_teacher_profile && !$is_own_profile && !$is_eval_locked && isset($has_evaluated) && !$has_evaluated): ?>
<div class="modal" id="profileEvaluationModal" style="background: rgba(0,0,0,0.8);">
    <div class="modal-content" style="max-width: 640px; width: 95%; padding: 0; background: #0a1f16; border-radius: 8px; overflow: hidden; color: #e4e6eb; font-family: 'Segoe UI', sans-serif; border: 1px solid #00ffaa; max-height:90vh; overflow-y:auto;">
        <div style="height: 10px; background: #00ffaa;"></div>
        <div style="padding: 20px;">
            <div style="background: #1a3d2f; border-radius: 8px; padding: 24px; margin-bottom: 12px; border: 1px solid rgba(0, 255, 170, 0.3); position: relative;">
                <span onclick="document.getElementById('profileEvaluationModal').style.display='none'" style="position: absolute; top: 10px; right: 15px; color: #00ffaa; font-size: 28px; cursor: pointer;">&times;</span>
                <h2 style="margin: 0 0 10px; font-size: 24px; color: #00ffaa;">Evaluate <?php echo htmlspecialchars($formatted_name); ?></h2>
                <p style="color: #ff5555; font-size: 12px;">* Required</p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="submit_evaluation">
                <input type="hidden" name="teacher_id" value="<?php echo substr($target_id, 2); ?>">
                <?php 
                $eval_questions = [];
                $eq_res = $conn->query("SELECT * FROM evaluation_questions ORDER BY category ASC, id ASC");
                if($eq_res) while($row = $eq_res->fetch_assoc()) $eval_questions[] = $row;
                $current_cat = "";
                foreach($eval_questions as $q): 
                    if($q['category'] != $current_cat): $current_cat = $q['category']; ?>
                    <div style="margin: 20px 0 10px; padding: 10px; background: rgba(0, 255, 170, 0.1); border-left: 4px solid #00ffaa;"><h3 style="margin:0; color: #00ffaa; font-size: 16px;"><?php echo htmlspecialchars($current_cat); ?></h3></div>
                <?php endif; ?>
                <div style="background: #1a3d2f; border-radius: 8px; padding: 20px; margin-bottom: 12px; border: 1px solid rgba(0, 255, 170, 0.3);">
                    <p style="margin:0 0 15px; font-size: 15px; color: #fff;"><?php echo htmlspecialchars($q['question']); ?> <span style="color: #ff5555;">*</span></p>
                    <div style="display: flex; justify-content: space-between; align-items: flex-end;"><span style="font-size: 12px; color: #b0fce0;">Poor</span><?php for($i=1; $i<=5; $i++): ?><label style="cursor:pointer; display:flex; flex-direction:column; align-items:center;"><span style="margin-bottom: 10px; font-size: 14px;"><?php echo $i; ?></span><input type="radio" name="rating[<?php echo $q['id']; ?>]" value="<?php echo $i; ?>" required style="accent-color: #00ffaa;"></label><?php endfor; ?><span style="font-size: 12px; color: #b0fce0;">Excellent</span></div>
                </div>
                <?php endforeach; ?>
                <div style="background: #1a3d2f; border-radius: 8px; padding: 20px; margin-bottom: 12px; border: 1px solid rgba(0, 255, 170, 0.3);">
                    <p style="margin:0 0 10px; font-size: 15px; color: #fff;">Comments (Optional)</p>
                    <textarea name="comments" rows="2" style="width: 100%; padding: 10px; background: rgba(0,0,0,0.2); border: 1px solid #00ffaa; color: #fff; border-radius: 5px;"></textarea>
                </div>
                <button type="submit" class="save-btn" style="width: 100%;">Submit Evaluation</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<!-- ================= Profile Update Verification Modal ================= -->
<div class="modal" id="profileVerifyModal" style="background: rgba(0,0,0,0.9); z-index: 30000; align-items:center; justify-content:center;">
    <div class="modal-content" style="max-width: 450px; background: linear-gradient(135deg, #05100c 0%, #1a3d2f 100%); border: 1px solid #00ffaa; border-radius: 12px; padding: 0; text-align: center; overflow: hidden; box-shadow: 0 0 50px rgba(0,255,170,0.2);">
        <div style="background: rgba(0,255,170,0.1); padding: 30px 20px; border-bottom: 1px solid rgba(0,255,170,0.2); position: relative;">
            <span class="close" onclick="closeProfileVerifyModal()" style="position: absolute; top: 15px; right: 20px; color: #00ffaa; font-size: 28px; cursor: pointer;">&times;</span>
            <div style="font-size: 40px; margin-bottom: 10px;">🛡️</div>
            <h3 style="color: #fff; margin: 0; text-transform: uppercase; letter-spacing: 3px; font-weight: 900;">Verify Identity</h3>
        </div>
        <div style="padding: 40px;">
            <p style="color: #b0fce0; font-size: 14px; margin-bottom: 20px;">To change your contact info, we sent a code to your current email:<br><strong id="currentEmailMasked" style="color:#fff;"></strong></p>
            <div style="display: flex; gap: 10px; justify-content: center; margin-bottom: 30px;">
                <input type="text" maxlength="1" class="otp-box-modern pv-otp" oninput="moveNextPV(this, 1)" onkeydown="moveBackPV(this, event, 0)">
                <input type="text" maxlength="1" class="otp-box-modern pv-otp" oninput="moveNextPV(this, 2)" onkeydown="moveBackPV(this, event, 1)">
                <input type="text" maxlength="1" class="otp-box-modern pv-otp" oninput="moveNextPV(this, 3)" onkeydown="moveBackPV(this, event, 2)">
                <input type="text" maxlength="1" class="otp-box-modern pv-otp" oninput="moveNextPV(this, 4)" onkeydown="moveBackPV(this, event, 3)">
                <input type="text" maxlength="1" class="otp-box-modern pv-otp" oninput="moveNextPV(this, 5)" onkeydown="moveBackPV(this, event, 4)">
                <input type="text" maxlength="1" class="otp-box-modern pv-otp" oninput="moveNextPV(this, 6)" onkeydown="moveBackPV(this, event, 5)">
            </div>
            <button id="pvVerifyBtn" onclick="pvVerifyOTP()" class="save-btn" style="width: 100%; margin: 0;">Validate & Update</button>
        </div>
    </div>
</div>

<!-- ================= Tagged Users Modal ================= -->
<div class="modal" id="taggedUsersModal">
    <div class="modal-content" style="max-width: 400px; background: #102e22; border: 1px solid #00ffaa; padding: 25px; border-radius: 15px;">
        <span class="close" onclick="closeTaggedUsersModal()">&times;</span>
        <h3 style="color:#00ffaa; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; font-size: 16px;">Tagged in this post</h3>
        <div id="taggedUsersList" style="max-height: 400px; overflow-y: auto;">
            <!-- Content loaded via JS -->
        </div>
    </div>
</div>

<!-- ================= Video Viewer Modal ================= -->
<div id="videoViewerModal" class="video-modal">
    <span class="close-video-modal" onclick="closeVideoViewer()">&times;</span>
    <div class="video-modal-content">
        <div class="video-section">
            <video id="viewerVideo" controls autoplay></video>
        </div>
        <div class="details-section">
            <div class="details-header">
                <img id="viewerProfilePic" src="" alt="">
                <div>
                    <h4 id="viewerUsername"></h4>
                    <span id="viewerTime"></span>
                </div>
            </div>
            <div class="details-body">
                <p id="viewerCaption" class="viewer-caption"></p>
                <div class="viewer-stats">
                    <span id="viewerViewCount" class="view-count-badge">👁 0 Views</span>
                    <span id="viewerLikeCount"></span>
                </div>
                <div class="viewer-actions">
                    <button id="viewerLikeBtn" class="viewer-action-btn" onclick="toggleViewerLike()">❤️ Like</button>
                    <button class="viewer-action-btn" onclick="document.getElementById('viewerCommentInput').focus()">💬 Comment</button>
                </div>
                <div class="viewer-comments" id="viewerCommentsList"></div>
            </div>
            <div class="details-footer">
                <input type="text" id="viewerCommentInput" class="viewer-input" placeholder="Write a comment..." onkeypress="handleViewerComment(event)">
            </div>
        </div>
    </div>
</div>

<!-- ================= Map Selection Modal ================= -->
<div class="modal" id="mapModal" style="display:none; background: rgba(0,0,0,0.9); z-index: 20001; align-items:center; justify-content:center;">
    <div class="modal-content" style="max-width: 800px; width: 90%; background: #1a3d2f; border: 1px solid #00ffaa; padding: 0; border-radius: 15px; overflow: hidden;">
        <div style="padding: 15px; border-bottom: 1px solid rgba(0,255,170,0.2); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin:0; color:#00ffaa;">Select Your Location</h3>
            <span class="close" onclick="closeMapModal()" style="color:#ff5555; cursor:pointer; font-size:24px;">&times;</span>
        </div>
        <div id="map" style="width: 100%; height: 450px; background: #000;"></div>
        <div style="padding: 20px; display: flex; flex-direction: column; gap: 10px; background: rgba(0,0,0,0.2);">
            <div id="mapStatus" style="font-size: 12px; color: #00ffaa; font-family: monospace;">Click on the map to set marker...</div>
            <div id="addressPreview" style="font-size: 14px; color: #fff; font-weight: bold; min-height: 20px;"></div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="action-btn-pill" style="background: rgba(255,255,255,0.1);" onclick="closeMapModal()">Cancel</button>
                <button type="button" id="confirmLocationBtn" class="action-btn-pill" style="background: #00ffaa; color: #0a1f16;" disabled onclick="confirmLocation()">Use This Location</button>
            </div>
        </div>
    </div>
</div>

<!-- ================= Custom Alert Modal ================= -->
<div class="modal" id="customAlertModal" style="display:none; z-index: 30001; align-items:center; justify-content:center;">
    <div class="modal-content" style="max-width: 300px; padding: 20px; text-align: center; background: #102e22; border: 1px solid #00ffaa;">
        <h3 style="color: #00ffaa; margin-bottom: 10px;">Notification</h3>
        <p id="customAlertText" style="color: #e4e6eb; margin-bottom: 20px;"></p>
        <button onclick="closeCustomAlert()" class="save-btn" style="padding: 8px 20px; border-radius: 15px; width: 100%; margin: 0;">OK</button>
    </div>
</div>

<!-- ================= Custom Confirm Modal ================= -->
<div class="modal" id="customConfirmModal" style="display:none; z-index: 30002; align-items:center; justify-content:center;">
    <div class="modal-content" style="max-width: 300px; padding: 20px; text-align: center; background: #102e22; border: 1px solid #00ffaa;">
        <h3 style="color: #00ffaa; margin-bottom: 10px;">Confirmation</h3>
        <p id="customConfirmText" style="color: #e4e6eb; margin-bottom: 20px;"></p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button id="confirmYesBtn" class="save-btn" style="padding: 8px 20px; border-radius: 15px; flex: 1; margin: 0;">Yes</button>
            <button id="confirmNoBtn" class="action-btn-pill" style="padding: 8px 20px; border-radius: 15px; flex: 1; justify-content: center; margin: 0;">Cancel</button>
        </div>
    </div>
</div>

<!-- ================= Custom Prompt Modal ================= -->
<div class="modal" id="customPromptModal" style="display:none; z-index: 30003; align-items:center; justify-content:center;">
    <div class="modal-content" style="max-width: 300px; padding: 20px; text-align: center; background: #102e22; border: 1px solid #00ffaa;">
        <h3 style="color: #00ffaa; margin-bottom: 10px;">Input Required</h3>
        <p id="customPromptText" style="color: #e4e6eb; margin-bottom: 10px;"></p>
        <input type="text" id="customPromptInput" class="form-input" style="margin-bottom: 20px; text-align: center;">
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button id="promptOkBtn" class="save-btn" style="padding: 8px 20px; border-radius: 15px; flex: 1; margin: 0;">OK</button>
            <button onclick="closeCustomPrompt()" class="action-btn-pill" style="padding: 8px 20px; border-radius: 15px; flex: 1; justify-content: center; margin: 0;">Cancel</button>
        </div>
    </div>
</div>

<!-- Scroll Top Button -->
<button class="scroll-top-btn"  onclick="window.scrollTo({top: 0, behavior: 'smooth'})">🢁</button>

<script>
/* --- Header Navigation & Notif Logic (SacliConnect Sync) --- */
/* --- Messenger & Concern Widgets (SacliConnect Sync) --- */
let currentChatId = null;
let currentChatType = 'direct';
let chatInterval = null;

function openChat(id, name, pic) {
    currentChatType = 'direct';
    currentChatId = id;
    document.getElementById('chatTitle').innerText = name;
    document.getElementById('chatHeaderImg').src = pic;
    document.getElementById('chatBox').style.display = 'flex';
    fetchMessages(true);
    if(chatInterval) clearInterval(chatInterval);
    chatInterval = setInterval(fetchMessages, 3000);
}

function openGroupChat(id, name, pic) {
    currentChatType = 'group';
    currentChatId = id;
    document.getElementById('chatTitle').innerText = name;
    document.getElementById('chatHeaderImg').src = pic;
    document.getElementById('chatBox').style.display = 'flex';
    fetchMessages(true);
    if(chatInterval) clearInterval(chatInterval);
    chatInterval = setInterval(fetchMessages, 3000);
}

function fetchMessages(forceScroll = false) {
    if(!currentChatId) return;
    let fd = new FormData();
    let handler = (currentChatType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
    fd.append('action', 'fetch');
    if(currentChatType === 'group') fd.append('group_id', currentChatId);
    else fd.append('receiver_id', currentChatId);
    fetch(handler, { method: 'POST', body: fd }).then(res => res.text()).then(html => {
        const body = document.getElementById('chatBody');
        body.innerHTML = html;
        if(forceScroll) body.scrollTop = body.scrollHeight;
    });
}

function closeChat() { 
    document.getElementById('chatBox').style.display = 'none'; 
    currentChatId = null;
    if(chatInterval) clearInterval(chatInterval);
}

function toggleDropdown(id) {
    let el = document.getElementById(id);
    let isVisible = el.style.display === 'flex';
    document.querySelectorAll('.dropdown-popover').forEach(d => d.style.display = 'none');
    if(!isVisible) el.style.display = 'flex';
}

function loadMyGroups() {
    let fd = new FormData();
    fd.append('action', 'get_my_groups');
    fetch('handlers/group_chat_handler.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
        let container = document.getElementById('groupListContainer');
        container.innerHTML = data.map(g => `<div class="dp-item" onclick="openGroupChat(${g.id}, '${g.name}', 'uploads/${g.group_icon}')"><img src="uploads/${g.group_icon}" style="width:30px;height:30px;border-radius:50%;"> <strong>${g.name}</strong></div>`).join('');
    });
}

function loadNotifications() {
    let formData = new FormData();
    formData.append('action', 'fetch_notifs');
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        let container = document.getElementById('notifListContainer');
        container.innerHTML = '';
        if(data.length === 0) container.innerHTML = '<div style="padding:15px;text-align:center;color:#aaa;">No notifications</div>';
        data.forEach(n => {
            let pic = n.profile_pic ? "uploads/"+n.profile_pic : "assets/images/3icons8-student-64.png";
            let style = (n.is_read == 0) ? "background:rgba(0, 255, 170, 0.15); border-left: 3px solid #00ffaa;" : "";
            container.innerHTML += `<div class="dp-item" style="${style}" onclick="window.location.href='SacliConnect.php?highlight_post=${n.post_id}'"><img src="${pic}" style="width:30px;height:30px;border-radius:50%;object-fit:cover;"> <div><strong>${n.student_name || n.actor_id}</strong> <small>interacted with your node.</small></div></div>`;
        });
        document.getElementById('notifCount').style.display = 'none';
        let markData = new FormData();
        markData.append('action', 'mark_all_notifs_read');
        fetch('handlers/post_interaction.php', { method: 'POST', body: markData });
    });
}

function loadMessagesDropdown() {
    let formData = new FormData();
    formData.append('action', 'fetch_msgs');
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        let container = document.getElementById('msgListContainer');
        container.innerHTML = '';
        if(data.length === 0) container.innerHTML = '<div style="padding:15px;text-align:center;color:#aaa;">No recent messages</div>';
        data.forEach(m => {
            let pic = m.profile_pic ? "uploads/"+m.profile_pic : "assets/images/3icons8-student-64.png";
            let style = (m.is_unread == 1) ? "background:rgba(0, 255, 170, 0.15); border-left: 3px solid #00ffaa;" : "";
            container.innerHTML += `<div class="dp-item" style="${style}" onclick="window.location.href='SacliChat_Full.php?id=${m.other_id}&type=direct'"><img src="${pic}" style="width:30px;height:30px;border-radius:50%;object-fit:cover;"> <div><strong>${m.student_name}</strong><small>${m.message.substring(0, 20)}...</small></div></div>`;
        });
    });
}

function checkAppNotifs() {
    let formData = new FormData();
    formData.append('action', 'check_unread');
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(count => {
        let badge = document.getElementById('notifCount');
        if(parseInt(count) > 0){ badge.innerText = count; badge.style.display = 'block'; }
        else { badge.style.display = 'none'; }
    });
}

function checkNewMessages() {
    let formData = new FormData();
    formData.append('action', 'check_new');
    fetch('handlers/chat_handler.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(unreadData => {
        let badge = document.getElementById('msgCount');
        if(unreadData.length > 0){ badge.innerText = unreadData.length; badge.style.display = 'block'; }
        else { badge.style.display = 'none'; }
    });
}

// Header Search Logic
const searchInput = document.getElementById('headerSearch');
const searchResults = document.getElementById('searchResults');

if(searchInput) {
    searchInput.addEventListener('input', function() {
        let query = this.value.trim();
        if(query.length > 0){
            fetch('search_handler.php?q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                searchResults.innerHTML = '';
                if(data.length > 0){
                    searchResults.style.display = 'block';
                    data.forEach(user => {
                        let pic = user.profile_pic ? "uploads/" + user.profile_pic : "assets/images/3icons8-student-64.png";
                        let div = document.createElement('div');
                        div.className = 'dp-item';
                        div.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
                        div.innerHTML = `<img src="${pic}" style="width:30px;height:30px;border-radius:50%;"><span class="search-name">${user.student_name}</span>`;
                        div.onclick = () => window.location.href = `Student_Profile.php?id=${user.student_id}`;
                        searchResults.appendChild(div);
                    });
                } else { searchResults.style.display = 'none'; }
            });
        } else { searchResults.style.display = 'none'; }
    });
}

function checkGroupNotifs() {
    let fd = new FormData();
    fd.append('action', 'get_my_groups');
    fetch('handlers/group_chat_handler.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
        let unread = data.reduce((sum, g) => sum + parseInt(g.unread_count || 0), 0);
        let badge = document.getElementById('groupBadge');
        if(unread > 0){ badge.innerText = unread; badge.style.display = 'block'; }
        else { badge.style.display = 'none'; }
    });
}

let concernChatInterval = null;
function openConcernChat() {
    document.getElementById('concernChatBox').style.display = 'flex';
    fetchConcerns();
    if(concernChatInterval) clearInterval(concernChatInterval);
    concernChatInterval = setInterval(fetchConcerns, 3000);
}

function closeConcernChat() {
    document.getElementById('concernChatBox').style.display = 'none';
    if(concernChatInterval) clearInterval(concernChatInterval);
}

function fetchConcerns() {
    fetch('handlers/concern_handler.php', { method: 'POST', body: new URLSearchParams('action=fetch') })
    .then(res => res.text()).then(html => { document.getElementById('concernBody').innerHTML = html; });
}

function checkConcernNotifs() {
    fetch('handlers/concern_handler.php', { method: 'POST', body: new URLSearchParams('action=check_unread') })
    .then(res => res.text()).then(count => {
        let badge = document.getElementById('concernBadge');
        if(parseInt(count) > 0) { badge.innerText = count; badge.style.display = 'block'; }
        else { badge.style.display = 'none'; }
    });
}

// Polling
setInterval(() => {
    checkAppNotifs();
    checkNewMessages();
    checkGroupNotifs();
    checkConcernNotifs();
}, 5000);

/* --- Essential Helper Functions --- */
function showFlash(msg, type = 'success') {
    const flash = document.getElementById('flashMsg');
    if (!flash) return;
    flash.innerText = msg;
    flash.className = 'flash-message ' + type + ' show';
    setTimeout(() => { flash.classList.remove('show'); }, 3000);
}

function focusOnSection(el) {
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.style.color = '#fff';
    setTimeout(() => { el.style.color = ''; }, 2000);
}

/* --- Video Player Logic --- */
function initVideoPlayers() {
    const videos = document.querySelectorAll('video.feed-video');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const video = entry.target;
                if (entry.isIntersecting) {
                    video.play().catch(e => {});
                } else {
                    video.pause();
                }
            });
        }, { threshold: 0.1 });
        videos.forEach(v => observer.observe(v));
    }

    // Sync initial Play/Pause Icon state
    videos.forEach(v => {
        v.addEventListener('play', () => { 
            const icon = v.parentElement.querySelector('.video-play-icon');
            if(icon) icon.innerText = 'Ⅱ'; // Pause Icon
        });
        v.addEventListener('pause', () => { 
            const icon = v.parentElement.querySelector('.video-play-icon');
            if(icon) icon.innerText = '▶'; // Play Icon
        });
    });
}

function togglePlay(btn) {
    const video = btn.closest('.media-item').querySelector('video');
    if (video.paused) video.play();
    else video.pause();
}

function updateProgress(video) {
    // Ensure this logic only runs for feed videos, not the modal viewer video
    const parent = video.parentElement;
    if(!parent || !parent.classList.contains('media-item')) return;

    const container = video.closest('.media-item');
    if (!container) return;
    const slider = container.querySelector('.video-seek-slider');
    const timeDisplay = container.querySelector('.video-time');
    if (slider && !isNaN(video.duration)) {
        const percent = (video.currentTime / video.duration) * 100;
        slider.value = percent;
        timeDisplay.innerText = formatTime(video.currentTime) + ' / ' + formatTime(video.duration);
    }
}

function formatTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s < 10 ? '0' : ''}${s}`;
}

function seekVideo(slider) {
    const video = slider.closest('.media-item').querySelector('video');
    if (video) video.currentTime = (slider.value / 100) * video.duration;
}

function toggleMute(btn) {
    const video = btn.closest('.media-item').querySelector('video');
    if (!video) return;
    video.muted = !video.muted;
    btn.querySelector('.icon-muted-state').style.display = video.muted ? 'block' : 'none';
    btn.querySelector('.icon-unmuted-state').style.display = video.muted ? 'none' : 'block';
}

function setVolume(slider) {
    const video = slider.closest('.media-item').querySelector('video');
    if (video) {
        video.volume = slider.value;
        video.muted = (slider.value == 0);
        
        // Update the mute button icons instantly
        const muteBtn = slider.closest('.video-volume-control').querySelector('.video-mute-button');
        if(muteBtn) {
            muteBtn.querySelector('.icon-muted-state').style.display = video.muted ? 'block' : 'none';
            muteBtn.querySelector('.icon-unmuted-state').style.display = video.muted ? 'none' : 'block';
        }
    }
}

let currentViewerPostId = null;
function openVideoViewer(postId, videoSrc) {
    currentViewerPostId = postId;
    const modal = document.getElementById('videoViewerModal');
    const video = document.getElementById('viewerVideo');
    const postEl = document.getElementById('post-' + postId);
    
    if (!postEl) return;

    // Sync details
    document.getElementById('viewerProfilePic').src = postEl.querySelector('.post-profile-img').src;
    document.getElementById('viewerUsername').innerText = postEl.querySelector('h4').innerText;
    document.getElementById('viewerTime').innerText = postEl.querySelector('.time').innerText;
    document.getElementById('viewerCaption').innerText = postEl.querySelector('.post-content-container').innerText;
    
    video.src = videoSrc;
    modal.style.display = 'block';
    
    // Load comments
    document.getElementById('viewerCommentsList').innerHTML = document.getElementById('comment-list-' + postId).innerHTML;

    // Increment views via AJAX
    let fd = new FormData();
    fd.append('action', 'increment_view');
    fd.append('post_id', postId);
    fetch('handlers/post_interaction.php', { method: 'POST', body: fd })
    .then(res => res.text())
    .then(views => {
        document.getElementById('viewerViewCount').innerText = '👁 ' + views + ' Views';
    });
}

function closeVideoViewer() {
    const modal = document.getElementById('videoViewerModal');
    const video = document.getElementById('viewerVideo');
    video.pause();
    video.src = "";
    modal.style.display = 'none';
}
</script>

<script>
function previewFile(input){
    var file = input.files[0];
    if(file){
        var reader = new FileReader();
        reader.onload = function(){
            document.getElementById('previewImg').src = reader.result;
        }
        reader.readAsDataURL(file);
    }
}

/* --- Enhanced Cover Photo Functions --- */
let isDragging = false;
let startY, startTop;

/* --- New Modal-based Reposition Functions --- */
let isRepoDragging = false;
let repoStartY, repoStartTop;

function previewCover(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function() {
            document.getElementById('coverPreview').src = reader.result;
            document.getElementById('coverPreview').style.top = '0px';
            document.getElementById('coverOffsetInput').value = 0;
            // Open modal automatically for new photo
            setTimeout(openRepositionModal, 500);
        };
        reader.readAsDataURL(file);
    }
}

function openRepositionModal() {
    const mainImg = document.getElementById('coverPreview');
    const repoImg = document.getElementById('repositionImg');
    
    // Hintayin muna nating lumabas ang modal bago i-set ang src para accurate ang calculation
    document.getElementById('repositionModal').style.display = 'flex';
    
    repoImg.onload = function() {
        // I-sync ang kasalukuyang pwesto mula sa banner
        repoImg.style.top = mainImg.style.top || '0px';
    };
    repoImg.src = mainImg.src;
}

function closeRepositionModal() {
    document.getElementById('repositionModal').style.display = 'none';
}

function startDragReposition(e) {
    const repoImg = document.getElementById('repositionImg');
    const container = document.getElementById('repositionContainer');
    
    // Huwag payagan ang drag kung ang image ay mas maliit sa container
    if (repoImg.offsetHeight <= container.offsetHeight) return;

    isRepoDragging = true;
    repoStartY = e.pageY;
    repoStartTop = parseInt(repoImg.style.top) || 0;
}

window.addEventListener('mousemove', (e) => {
    if (!isRepoDragging) return;
    
    const repoImg = document.getElementById('repositionImg');
    const container = document.getElementById('repositionContainer');
    
    const deltaY = e.pageY - repoStartY;
    let newTop = repoStartTop + deltaY;
    
    // Boundary Logic: Iwasang lumabas ang image o magkaroon ng gap
    const limit = container.clientHeight - repoImg.offsetHeight;

    if (newTop > 0) newTop = 0;
    if (newTop < limit) newTop = limit;
    
    repoImg.style.top = newTop + 'px';
});

window.addEventListener('mouseup', () => {
    isRepoDragging = false;
});

function applyReposition() {
    const repoTop = document.getElementById('repositionImg').style.top;
    document.getElementById('coverPreview').style.top = repoTop;
    document.getElementById('coverOffsetInput').value = parseInt(repoTop);
    closeRepositionModal();
    showCustomAlert("Position set! Click 'Update Profile' to save permanently.");
}

function cancelCoverAdjust() { location.reload(); }

function stopDrag() {
    isDragging = false;
}

function cancelCoverAdjust() {
    location.reload(); // Simplest way to revert
}

function viewCoverPhoto() {
    const src = document.getElementById('coverPreview').src;
    if (src.includes('Adobe Express - file.png')) return; // Wag buksan kung default placeholder lang

    currentMediaList = [{ file_path: src, file_type: 'image' }];
    currentMediaIndex = 0;
    showLightboxItem();
    document.getElementById('lightboxModal').style.display = 'block';
}

// --- CUSTOM MODAL FUNCTIONS ---
function showCustomAlert(msg) {
    document.getElementById('customAlertText').innerText = msg;
    document.getElementById('customAlertModal').style.display = 'flex';
}
function closeCustomAlert() {
    document.getElementById('customAlertModal').style.display = 'none';
}
let confirmCallback = null;
let cancelCallback = null;
function showCustomConfirm(msg, callback, noCallback = null, yesText = "Yes", noText = "Cancel") {
    document.getElementById('customConfirmText').innerText = msg;
    document.getElementById('customConfirmModal').style.display = 'flex';
    document.getElementById('confirmYesBtn').innerText = yesText;
    document.getElementById('confirmNoBtn').innerText = noText;
    confirmCallback = callback;
    cancelCallback = noCallback;
}
function closeCustomConfirm() {
    document.getElementById('customConfirmModal').style.display = 'none';
    confirmCallback = null;
    cancelCallback = null;
}
document.getElementById('confirmYesBtn').onclick = function() {
    if(confirmCallback) confirmCallback();
    closeCustomConfirm();
};
document.getElementById('confirmNoBtn').onclick = function() {
    if(cancelCallback) cancelCallback();
    closeCustomConfirm();
};
window.onclick = function(e) {
    if (e.target === document.getElementById("lightboxModal")) closeLightbox();
    if (e.target === document.getElementById("customAlertModal")) closeCustomAlert();
    if (document.getElementById("profileEvaluationModal") && e.target === document.getElementById("profileEvaluationModal")) {
        document.getElementById("profileEvaluationModal").style.display='none';
    } // Removed profileVerifyModal
    if (e.target === document.getElementById("customConfirmModal")) closeCustomConfirm();
    if (e.target === document.getElementById("customPromptModal")) closeCustomPrompt();
    if (e.target === document.getElementById("videoViewerModal")) closeVideoViewer();
    if (e.target === document.getElementById("mapModal")) closeMapModal();
    if (e.target === document.getElementById("requestChangeModal")) closeRequestChangeModal();
    if (e.target === document.getElementById("taggedUsersModal")) closeTaggedUsersModal();
};

// --- MAP FUNCTIONS ---
let map, marker;
let selectedAddress = "";

function openMapModal() {
    document.getElementById('mapModal').style.display = 'flex';

    // Give the browser a moment to render the flex container before initializing Leaflet
    setTimeout(() => {
    if (!map) {
        // Initialize Map (Lucena City as default)
        map = L.map('map', {
            center: [13.9373, 121.6146],
            zoom: 13
        });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        map.on('click', function(e) {
            updateMarker(e.latlng.lat, e.latlng.lng);
        });
    }
    
    // Kunin ang current location ng user (Geolocation)
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(position => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            map.setView([lat, lng], 16);
            updateMarker(lat, lng);
        });
    }
    
    // Mahalaga ito para lumabas agad ang mga tiles ng mapa
    map.invalidateSize();
    }, 300);
}

function closeMapModal() {
    document.getElementById('mapModal').style.display = 'none';
}

function updateMarker(lat, lng) {
    if (marker) marker.setLatLng([lat, lng]);
    else marker = L.marker([lat, lng], {draggable: true}).addTo(map);
    
    document.getElementById('mapStatus').innerText = "// FETCHING_ADDRESS_FROM_NODE...";
    document.getElementById('confirmLocationBtn').disabled = true;

    // Reverse Geocoding via Nominatim (Free OpenStreetMap API)
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
    .then(res => res.json())
    .then(data => {
        selectedAddress = data.display_name;
        // Simplify address (get key parts)
        let parts = data.address;
        let simple = (parts.suburb || parts.neighbourhood || "") + ", " + (parts.city || parts.town || parts.municipality || "") + ", " + (parts.province || parts.state || "");
        simple = simple.replace(/^, /, "");
        
        selectedAddress = simple || selectedAddress;
        document.getElementById('addressPreview').innerText = "📍 " + selectedAddress;
        document.getElementById('locationInput').value = selectedAddress; // Auto-fill while picking
        document.getElementById('mapStatus').innerText = "// LOCATION_RESOLVED";
        document.getElementById('confirmLocationBtn').disabled = false;
    })
    .catch(err => {
        document.getElementById('mapStatus').innerText = "// ERROR_FETCHING_ADDRESS";
    });
}

function confirmLocation() {
    const address = selectedAddress;
    if (address) {
        document.getElementById('locationInput').value = address;
        closeMapModal();
    }
}

window.addEventListener('scroll', () => {
    const btn = document.querySelector('.scroll-top-btn');
    if (window.scrollY > 300) btn.classList.add('visible'); else btn.classList.remove('visible');
});

function confirmResetEvaluation(formId) {
    showCustomConfirm("Are you sure you want to reset your evaluation for this teacher?", function() {
        document.getElementById(formId).submit();
    });
}


// --- LIGHTBOX FUNCTIONS ---
let currentMediaList = [];
let currentMediaIndex = 0;

function openLightbox(postId, index) {
    // Get the specific media array for this post
    const mediaData = window['post_media_' + postId];
    if (!mediaData || mediaData.length === 0) return;
    currentMediaList = mediaData;
    currentMediaIndex = index;
    openLightboxModal();
}

function closeLightbox() {
    document.getElementById('lightboxModal').style.display = 'none';
    document.getElementById('lightboxContent').innerHTML = '';
}

function showLightboxItem() {
    if (!currentMediaList || !currentMediaList[currentMediaIndex]) return;
    let item = currentMediaList[currentMediaIndex];
    let container = document.getElementById('lightboxContent');
    
    // Handle both 'video' and 'photo' types correctly
    if (item.file_type === 'video') {
        container.innerHTML = `<video src="${item.file_path}" controls autoplay style="max-width:100%; max-height:80vh; border-radius:10px; box-shadow: 0 0 30px rgba(0,255,170,0.2);"></video>`;
    } else {
        container.innerHTML = `<img src="${item.file_path}" style="max-width:100%; max-height:80vh; border-radius:10px; box-shadow: 0 0 30px rgba(0,255,170,0.2);">`;
    }
    
    const counter = document.getElementById('lightboxCounter');
    if (counter) counter.innerText = (currentMediaIndex + 1) + " / " + currentMediaList.length;
}

/* --- Tab System Logic --- */
function switchTab(tabName, element) {
    // 1. Update Active Class on Buttons
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    element.classList.add('active');

    // 2. Hide all contents
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');

    // 3. Show selected content
    const target = document.getElementById(tabName + '-content');
    if(target) {
        target.style.display = (tabName === 'posts') ? 'grid' : 'block';
    }

    // Smooth scroll to tabs
    window.scrollTo({ top: document.querySelector('.profile-nav-tabs').offsetTop - 100, behavior: 'smooth' });
}

function openGalleryLightbox(index) {
    if (!window.userGallery || window.userGallery.length === 0) return;
    currentMediaList = window.userGallery;
    currentMediaIndex = index;
    openLightboxModal();
}

// Helper to initialize and show the lightbox modal
function openLightboxModal() {
    showLightboxItem();
    const modal = document.getElementById('lightboxModal');
    modal.style.display = 'flex'; // Use flex for centering
    modal.focus();
}

// Function para i-expand ang text ng post (See More)
function expandPost(element) {
    const container = element.closest('.post-content-container');
    const truncatedText = container.querySelector('.content-text-truncated');
    const fullText = container.querySelector('.content-text-full');
    if (truncatedText) truncatedText.style.display = 'none';
    if (fullText) fullText.style.display = 'block';
}

function collapsePost(element) {
    const container = element.closest('.post-content-container');
    const truncatedText = container.querySelector('.content-text-truncated');
    const fullText = container.querySelector('.content-text-full');
    if (truncatedText) truncatedText.style.display = 'block';
    if (fullText) fullText.style.display = 'none';
}

function nextSlide() {
    currentMediaIndex = (currentMediaIndex + 1) % currentMediaList.length;
    showLightboxItem();
}
function prevSlide() {
    currentMediaIndex = (currentMediaIndex - 1 + currentMediaList.length) % currentMediaList.length;
    showLightboxItem();
}

// --- INTERACTION FUNCTIONS ---
function toggleLike(btn, postId) {
    let formData = new FormData();
    formData.append('action', 'react');
    formData.append('post_id', postId);

    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(status => {
        let icon = btn.querySelector('.heart-icon');
        let countSpan = btn.querySelector('.like-count');
        let currentCount = parseInt(countSpan.innerText) || 0;

        if(status.trim() === 'liked') {
            btn.classList.add('liked');
            icon.innerText = '❤️';
            countSpan.innerText = currentCount + 1;
        } else {
            btn.classList.remove('liked');
            icon.innerText = '🤍';
            countSpan.innerText = currentCount > 1 ? currentCount - 1 : 'Like';
        }
    });
}

function deleteComment(commentId) {
    showCustomConfirm("Do you want to delete this comment?", function() {
        let formData = new FormData();
        formData.append('action', 'delete_comment');
        formData.append('comment_id', commentId);

        fetch('handlers/post_interaction.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(response => {
            if (response.trim() === 'success') {
                let commentElement = document.getElementById('comment-' + commentId);
                if (commentElement) {
                    
                    commentElement.style.transition = 'all 0.3s ease';
                    commentElement.style.opacity = '0';
                    commentElement.style.transform = 'scale(0.9)';
                    commentElement.style.height = '0px';
                    commentElement.style.marginBottom = '0px';
                    commentElement.style.padding = '0px';
                    commentElement.style.overflow = 'hidden';
                    setTimeout(() => commentElement.remove(), 300);
                }
            } else {
                showCustomAlert('Error: Could not delete comment.');
            }
        });
    });
}

function deletePost(postId) {
    showCustomConfirm("Are you sure you want to delete this post?", function() {
        let formData = new FormData();
        formData.append('action', 'delete_post');
        formData.append('post_id', postId);

        fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(resp => {
            if(resp.trim() === 'success') {
                let post = document.getElementById('post-' + postId);
                post.style.transition = 'all 0.5s ease';
                post.style.opacity = '0';
                post.style.transform = 'scale(0.9)';
                setTimeout(() => post.remove(), 500);
            } else {
                showCustomAlert("Error deleting post.");
            }
        });
    });
}

function handleComment(e, postId) {
    if(e.key === 'Enter') {
        let input = document.getElementById('comment-input-' + postId);
        let comment = input.value.trim();
        if(!comment) return;

        let formData = new FormData();
        formData.append('action', 'comment');
        formData.append('post_id', postId);
        formData.append('comment', comment);

        fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(html => {
            document.getElementById('comment-list-' + postId).insertAdjacentHTML('beforeend', html);
            input.value = '';
        });
    }
}

function toggleViewerLike() {
    if(!currentViewerPostId) return;
    let feedLikeBtn = document.querySelector(`#post-${currentViewerPostId} .action-btn`);
    if(feedLikeBtn) feedLikeBtn.click();
    
    let btn = document.getElementById('viewerLikeBtn');
    let isLiked = btn.classList.contains('liked');
    if(isLiked) {
        btn.classList.remove('liked');
        btn.innerHTML = '🤍 Like';
    } else {
        btn.classList.add('liked');
        btn.innerHTML = '❤️ Liked';
    }
}

// Tagging Logic for Viewer
viewerInput.addEventListener('input', function(e) {
    let text = this.value;
    let cursor = this.selectionStart;
    let lastAt = text.lastIndexOf('@', cursor - 1);
    
    if(lastAt !== -1) {
        let query = text.substring(lastAt + 1, cursor);
        if(!query.includes(' ')) {
            fetch('search_handler.php?q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(users => {
                viewerMentionDropdown.innerHTML = '';
                if(users.length > 0) {
                    viewerMentionDropdown.style.display = 'block';
                    users.forEach(u => {
                        let pic = u.profile_pic ? "uploads/"+u.profile_pic : "assets/images/3icons8-student-64.png";
                        let div = document.createElement('div');
                        div.className = 'viewer-mention-item';
                        div.innerHTML = `<img src="${pic}"><span>${u.student_name}</span>`;
                        div.onclick = () => {
                            let before = text.substring(0, lastAt);
                            let after = text.substring(cursor);
                            viewerInput.value = before + '@' + u.student_name + ' ' + after;
                            viewerMentionDropdown.style.display = 'none';
                            viewerInput.focus();
                        };
                        viewerMentionDropdown.appendChild(div);
                    });
                } else viewerMentionDropdown.style.display = 'none';
            });
            return;
        }
    }
    viewerMentionDropdown.style.display = 'none';
});

function handleViewerComment(e) {
    if(e.key === 'Enter' && currentViewerPostId) {
        let input = document.getElementById('viewerCommentInput');
        let comment = input.value.trim();
        if(!comment) return;

        let feedInput = document.getElementById('comment-input-' + currentViewerPostId);
        if(feedInput) {
            feedInput.value = comment;
            let event = new KeyboardEvent('keypress', {'key': 'Enter'});
            handleComment(event, currentViewerPostId);
            input.value = '';
            setTimeout(() => {
                let newComments = document.getElementById('comment-list-' + currentViewerPostId).innerHTML;
                document.getElementById('viewerCommentsList').innerHTML = newComments;
                let list = document.getElementById('viewerCommentsList');
                list.scrollTop = list.scrollHeight;
            }, 500);
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize components
    initVideoPlayers();
    
    // Handle potential alerts from URL
    if (document.querySelector('.alert')) { setTimeout(() => { document.querySelector('.alert').style.display='none'; }, 5000); }

    // --- Profile Form Logic ---
    const profileForm = document.getElementById('profileEditForm');
    
    // --- Profile Verification Logic (Existing Email Flow) ---
    window.sendProfileVerification = function(inputId) {
        console.log("Initiating security handshake...");
        
        let formData = new FormData();
        formData.append('action', 'profile_verify_send');

        fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                document.getElementById('currentEmailMasked').innerText = data.email;
                // Clear previous OTP inputs
                document.querySelectorAll('.pv-otp').forEach(input => input.value = '');
                document.getElementById('profileVerifyModal').style.display = 'flex';
                showCustomAlert("Verification sent to " + data.email);
            } else {
                showCustomAlert(data.message);
            }
        })
        .catch(err => {
            showCustomAlert("Connection error. Please check your network.");
        });
    };

    window.closeProfileVerifyModal = function() {
        document.getElementById('profileVerifyModal').style.display = 'none';
    };

    window.moveNextPV = function(curr, index) {
        if (curr.value.length >= 1 && index < 6) {
            document.querySelectorAll('.pv-otp')[index].focus();
        }
    };

    window.moveBackPV = function(curr, e, index) {
        if (e.key === "Backspace" && curr.value === "" && index >= 0) {
            document.querySelectorAll('.pv-otp')[index].focus();
        }
    };

    window.pvVerifyOTP = function() {
        let otp = "";
        document.querySelectorAll('.pv-otp').forEach(input => otp += input.value);
        
        if(otp.length !== 6) {
            showCustomAlert("Please enter the 6-digit code.");
            return;
        }

        const btn = document.getElementById('pvVerifyBtn');
        btn.disabled = true;
        btn.innerText = "Verifying...";

        let formData = new FormData();
        formData.append('action', 'profile_verify_otp');
        formData.append('otp', otp);

        fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                showCustomAlert("Identity verified. Fields unlocked.");
                setTimeout(() => location.reload(), 1500); // Reload to remove readonly attributes
            } else {
                showCustomAlert(data.message);
                btn.disabled = false;
                btn.innerText = "Validate & Update";
            }
        })
        .catch(err => {
            showCustomAlert("Error validating code.");
            btn.disabled = false;
            btn.innerText = "Validate & Update";
        });
    };

    let currentChangeType = ''; // Declare inside but make it accessible to window functions
    
    // New functions for email/phone change
    window.openRequestChangeModal = function(type, currentValue) {
        currentChangeType = type;
        document.getElementById('requestChangeTitle').innerText = 'Change ' + type.charAt(0).toUpperCase() + type.slice(1);
        document.getElementById('requestChangeMessage').innerText = 'Enter your new ' + type + ' below. A verification link will be sent to it.';
        document.getElementById('newContactValue').value = ''; // Clear previous value
        document.getElementById('newContactValue').placeholder = 'new ' + type + '...';
        document.getElementById('requestChangeModal').style.display = 'flex';
    };

    window.closeRequestChangeModal = function() {
        document.getElementById('requestChangeModal').style.display = 'none';
    };

    window.sendNewContactVerification = function() {
        const newValue = document.getElementById('newContactValue').value.trim();
        if (!newValue) {
            showCustomAlert("Please enter a new " + currentChangeType + ".");
            return;
        }

        showFlash("Sending verification link...", "success");
        let formData = new FormData();
        formData.append('action', 'request_new_contact_verification');
        formData.append('change_type', currentChangeType);
        formData.append('new_value', newValue);

        fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
        .then(res => res.text()) // Kunin muna bilang text para ma-debug
        .then(data => {
            try {
                const json = JSON.parse(data);
                if(json.status === 'success') {
                closeRequestChangeModal();
                    showCustomAlert(json.message, "success");
                } else {
                    showCustomAlert(json.message);
                }
            } catch(e) {
                console.error("Server Response Error:", data);
                showCustomAlert("Server connection error. Check console for details.");
            }
        })
        .catch(err => {
            console.error("Fetch Error:", err);
            showCustomAlert("Uplink failed: " + err.message);
        });
    };

    // --- TAGGED USERS MODAL FUNCTIONS ---
    window.openTaggedUsersModal = function(postId) {
        const modal = document.getElementById('taggedUsersModal');
        const container = document.getElementById('taggedUsersList');
        container.innerHTML = '<div style="text-align:center; padding:20px; color:#aaa;">Loading users...</div>';
        modal.style.display = 'flex';

        let formData = new FormData();
        formData.append('action', 'fetch_tagged_users');
        formData.append('post_id', postId);

        fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(users => {
            container.innerHTML = '';
            users.forEach(u => {
                container.innerHTML += `
                    <div class="tagged-item-row" onclick="window.location.href='Student_Profile.php?id=${u.user_id}'" style="display:flex; align-items:center; gap:15px; padding:12px; border-bottom:1px solid rgba(255,255,255,0.05); cursor:pointer; transition: 0.2s;">
                        <img src="${u.profile_pic}" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid #00ffaa;">
                        <strong style="color:#fff;">${u.student_name}</strong>
                    </div>`;
            });
        });
    };

    window.closeTaggedUsersModal = function() {
        document.getElementById('taggedUsersModal').style.display = 'none';
    };
});
</script>
</body>
</html> 