 <?php
/**
 * SACLICONNECT2.php — Admin Control.
 * Lahat ng binago dito ay makikita sa SacliConnect.php (same DB).
 */
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if (empty($_SESSION['admin_username'])) {
    header("Location: ../SacliConnect_LOG_IN.php?show=admin");
    exit();
}

// Reset PIN sessions if navigating away from protected pages (Auto-Lock)
// Skip this check for background AJAX calls (like auto-reload check) to prevent session loss
if (! (isset($_POST['action']) && $_POST['action'] === 'check_admin_updates') ) {
    $currentPage = $_GET['page'] ?? 'main';
    if ($currentPage !== 'chats') unset($_SESSION['chat_pin_verified']);
    if ($currentPage !== 'passwords') unset($_SESSION['pass_pin_verified']);
}

$conn->query("CREATE TABLE IF NOT EXISTS sidebar_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(255) DEFAULT '',
    sort_order INT DEFAULT 0
)");
$conn->query("CREATE TABLE IF NOT EXISTS subject_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    is_online TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0
)");
// Add columns for URL and Icon if they don't exist
safeAddColumn($conn, 'subject_chats', 'url', "VARCHAR(255) DEFAULT '#'");
safeAddColumn($conn, 'subject_chats', 'icon', "VARCHAR(255) DEFAULT ''");

$conn->query("CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    event_date DATE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    actor_id VARCHAR(50),
    type VARCHAR(20),
    post_id INT,
    is_read TINYINT(1) DEFAULT 0,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// AUTO-FIX: Ensure calendar columns exist for Image and Time
safeAddColumn($conn, 'calendar_events', 'event_image', "VARCHAR(255)");
safeAddColumn($conn, 'calendar_events', 'time_in', "TIME");
safeAddColumn($conn, 'calendar_events', 'time_out', "TIME");

$conn->query("CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    department VARCHAR(50),
    position VARCHAR(100),
    profile_pic VARCHAR(255),
    email VARCHAR(100)
)");
safeAddColumn($conn, 'teachers', 'password', "VARCHAR(255)");

// AUTO-FIX: Ensure alumni table exists
$conn->query("CREATE TABLE IF NOT EXISTS alumni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    course VARCHAR(100),
    batch_year VARCHAR(20),
    profile_pic VARCHAR(255)
)");
safeAddColumn($conn, 'alumni', 'birthdate', "DATE NULL");
safeAddColumn($conn, 'alumni', 'status', "TEXT NULL");
safeAddColumn($conn, 'alumni', 'student_id', "VARCHAR(50) NULL");

// AUTO-FIX: Ensure achievements table exists
$conn->query("CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(100),
    category VARCHAR(50),
    title VARCHAR(255),
    description TEXT,
    image VARCHAR(255),
    date_posted DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Ensure admins2 table exists (for password viewing)
$conn->query("CREATE TABLE IF NOT EXISTS admins2 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
)");
safeAddColumn($conn, 'admins2', 'profile_pic', "VARCHAR(255)");
safeAddColumn($conn, 'admins2', 'email', "VARCHAR(100)");
safeAddColumn($conn, 'admins2', 'otp_code', "VARCHAR(6) NULL");
safeAddColumn($conn, 'admins2', 'otp_expiry', "DATETIME NULL");

// AUTO-FIX: Create System File Registry Table (Master List for Persistent Records)
$conn->query("CREATE TABLE IF NOT EXISTS system_file_registry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    source_folder ENUM('uploads', 'storage', 'submissions') NOT NULL,
    file_size VARCHAR(50),
    status ENUM('present', 'purged') DEFAULT 'present',
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (file_path)
)");

// Create Security Table for Chat PIN
$conn->query("CREATE TABLE IF NOT EXISTS admin_security (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pin_code VARCHAR(255) NOT NULL
)");
$chk_pin = $conn->query("SELECT id FROM admin_security LIMIT 1");
if($chk_pin->num_rows == 0){
    $conn->query("INSERT INTO admin_security (pin_code) VALUES ('092025')");
}

// AUTO-FIX: Poll System Tables
safeAddColumn($conn, 'posts', 'post_type', "ENUM('text', 'poll') DEFAULT 'text'");
$conn->query("CREATE TABLE IF NOT EXISTS poll_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    INDEX(post_id)
)");
$conn->query("CREATE TABLE IF NOT EXISTS poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    option_id INT NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    UNIQUE KEY (post_id, user_id)
)");

// Create Admin Concerns Table
$conn->query("CREATE TABLE IF NOT EXISTS admin_concerns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    sender_type ENUM('student', 'admin') NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) DEFAULT 0,
    INDEX(student_id)
)");

// Create Site Settings Table (For Login Video, etc.)
$conn->query("CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT
)");

// Create Password Change Request Table
$conn->query("CREATE TABLE IF NOT EXISTS password_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_username VARCHAR(50),
    student_id VARCHAR(50),
    new_password VARCHAR(255),
    status ENUM('pending', 'approved', 'denied') DEFAULT 'pending',
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");
safeAddColumn($conn, 'password_change_requests', 'approved_at', "DATETIME NULL");

// AUTO-FIX: Create Evaluation Questions Table
$conn->query("CREATE TABLE IF NOT EXISTS evaluation_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
safeAddColumn($conn, 'evaluation_questions', 'category', "VARCHAR(255) DEFAULT 'General'");

// Populate default evaluation questions if empty
$chk_eval_q = $conn->query("SELECT id FROM evaluation_questions LIMIT 1");
if($chk_eval_q->num_rows == 0){
    $defaults = [
        ['Instructional Competence', 'Demonstrates mastery of the subject matter.'],
        ['Instructional Competence', 'Explains concepts clearly and effectively.'],
        ['Instructional Competence', 'Uses varied teaching strategies to enhance learning.'],
        ['Instructional Competence', 'Relates the subject matter to real-life situations.'],
        ['Classroom Management', 'Starts and ends classes on time.'],
        ['Classroom Management', 'Maintains a classroom atmosphere conducive to learning.'],
        ['Classroom Management', 'Imposes discipline effectively and fairly.'],
        ['Personal Qualities', 'Shows respect and concern for students.'],
        ['Personal Qualities', 'Is approachable and available for consultation.'],
        ['Personal Qualities', 'Observes professional ethics and proper grooming.']
    ];
    $stmt = $conn->prepare("INSERT INTO evaluation_questions (category, question) VALUES (?, ?)");
    foreach($defaults as $d){
        $stmt->bind_param("ss", $d[0], $d[1]);
        $stmt->execute();
    }
}

$conn->query("CREATE TABLE IF NOT EXISTS evaluation_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT,
    question_id INT,
    rating INT
)");

// AUTO-FIX: Ensure student columns exist (Same logic as Student_Profile.php)
$cols = ['year_level' => 'VARCHAR(50) DEFAULT ""', 'course' => 'VARCHAR(100) DEFAULT ""', 'bio' => 'TEXT', 'profile_pic' => 'VARCHAR(255) DEFAULT ""', 'email' => 'VARCHAR(100) DEFAULT ""', 'is_alumni' => 'TINYINT(1) DEFAULT 0', 'password' => 'VARCHAR(255) NULL'];
foreach ($cols as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM students LIKE '$col'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE students ADD COLUMN $col $def");
    }
}
safeAddColumn($conn, 'students', 'created_at', "DATETIME DEFAULT CURRENT_TIMESTAMP");
safeAddColumn($conn, 'students', 'is_restricted', "TINYINT(1) DEFAULT 0");
safeAddColumn($conn, 'students', 'restriction_end_date', "DATETIME NULL");

safeAddColumn($conn, 'students', 'phone', "VARCHAR(20) DEFAULT NULL");
safeAddColumn($conn, 'students', 'hide_phone', "TINYINT(1) DEFAULT 0");
safeAddColumn($conn, 'teachers', 'is_restricted', "TINYINT(1) DEFAULT 0");
safeAddColumn($conn, 'teachers', 'restriction_end_date', "DATETIME NULL");


$updated = false;
$requestSent = isset($_GET['request_sent']) && $_GET['request_sent'] == '1';
$requestExists = isset($_GET['request_exists']) && $_GET['request_exists'] == '1';

// Fetch Admin Info
$admin_id = $_SESSION['admin_id'];
$admin_res = $conn->query("SELECT * FROM admins2 WHERE id='$admin_id'");
$admin_data = $admin_res->fetch_assoc();
$admin_pic = !empty($admin_data['profile_pic']) ? "uploads/".$admin_data['profile_pic'] : "76946050_2554845197961929_5561337140505214976_n-removebg-preview.png";

// Fetch counts for dashboard stats
$student_count_res = $conn->query("SELECT COUNT(*) as total FROM students WHERE is_alumni = 0");
$student_count = $student_count_res ? $student_count_res->fetch_assoc()['total'] : 0;
$teacher_count_res = $conn->query("SELECT COUNT(*) as total FROM teachers");
$teacher_count = $teacher_count_res ? $teacher_count_res->fetch_assoc()['total'] : 0;
$alumni_count_res = $conn->query("SELECT COUNT(*) as total FROM students WHERE is_alumni = 1");
$alumni_count = $alumni_count_res ? $alumni_count_res->fetch_assoc()['total'] : 0;
$total_registered = $student_count + $teacher_count;

// Fetch Site Theme
$theme_q = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='site_theme'");
$current_theme = ($theme_q && $theme_q->num_rows > 0) ? $theme_q->fetch_assoc()['setting_value'] : 'default';


$blackout_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='blackout_mode'");
$blackout_active = ($blackout_res && $blackout_res->num_rows > 0 && $blackout_res->fetch_assoc()['setting_value'] == '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Handle Check Admin Updates (Auto-Reload)
    if (isset($_POST['action']) && $_POST['action'] === 'check_admin_updates') {
        $req_q = $conn->query("SELECT COUNT(*) as c FROM password_change_requests WHERE status = 'pending'");
        $req_count = $req_q ? $req_q->fetch_assoc()['c'] : 0;

        $con_q = $conn->query("SELECT COUNT(*) as c FROM admin_concerns WHERE sender_type = 'student' AND is_read = 0");
        $con_count = $con_q ? $con_q->fetch_assoc()['c'] : 0;

        echo json_encode(['req' => $req_count, 'con' => $con_count]);
        exit();
    }

    // Handle Toggle Signup System
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_signup') {
        $status = $_POST['status']; // '1' or '0'
        $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('signup_enabled', '$status') ON DUPLICATE KEY UPDATE setting_value='$status'");
        $updated = true;
    }

    // Handle Fetch Network Activity (For Live Topology)
    if (isset($_POST['action']) && $_POST['action'] === 'fetch_network_activity') {
        $req_q = $conn->query("SELECT COUNT(*) as c FROM password_change_requests WHERE status = 'pending'");
        $req_count = $req_q ? $req_q->fetch_assoc()['c'] : 0;

        $con_q = $conn->query("SELECT COUNT(*) as c FROM admin_concerns WHERE sender_type = 'student' AND is_read = 0");
        $con_count = $con_q ? $con_q->fetch_assoc()['c'] : 0;

        echo json_encode(['req' => $req_count, 'con' => $con_count]);
        exit();
    }

    // Handle Fetch Network Activity (For Live Topology)
    if (isset($_POST['action']) && $_POST['action'] === 'fetch_network_activity') {
        $events = [];
        $interval = "4 SECOND"; // Time window for live activity
        
        // Logins (Last 4 seconds) - Exclude LOGOUT
        $logins = $conn->query("SELECT student_id, login_time, 'LOGIN' as type FROM login_history WHERE device_info != 'LOGOUT' AND login_time >= DATE_SUB(NOW(), INTERVAL $interval)");
        if($logins) while($l = $logins->fetch_assoc()) $events[] = $l;

        // Logouts
        $logouts = $conn->query("SELECT student_id, login_time, 'LOGOUT' as type FROM login_history WHERE device_info = 'LOGOUT' AND login_time >= DATE_SUB(NOW(), INTERVAL $interval)");
        if($logouts) while($lo = $logouts->fetch_assoc()) $events[] = $lo;
        
        // Posts
        $posts = $conn->query("SELECT p.student_name as student_id, p.timestamp as login_time, IF(s.is_alumni=1, 'ALUMNI_POST', 'POST') as type FROM posts p LEFT JOIN students s ON p.student_name = s.student_name WHERE p.timestamp >= DATE_SUB(NOW(), INTERVAL $interval)");
        if($posts) while($p = $posts->fetch_assoc()) $events[] = $p;

        // Chats
        $chats = $conn->query("SELECT sender_id as student_id, timestamp as login_time, 'CHAT' as type FROM direct_messages WHERE timestamp >= DATE_SUB(NOW(), INTERVAL $interval)");
        if($chats) while($c = $chats->fetch_assoc()) $events[] = $c;

        // Group Chats
        $gchats = $conn->query("SELECT sender_id as student_id, timestamp as login_time, 'GROUP_CHAT' as type FROM group_chat_messages WHERE timestamp >= DATE_SUB(NOW(), INTERVAL $interval)");
        if($gchats) while($gc = $gchats->fetch_assoc()) $events[] = $gc;

        // Concerns (Support)
        $concerns = $conn->query("SELECT student_id, timestamp as login_time, 'CONCERN' as type, sender_type FROM admin_concerns WHERE timestamp >= DATE_SUB(NOW(), INTERVAL $interval)");
        if($concerns) while($cn = $concerns->fetch_assoc()) $events[] = $cn;

        // Comments
        $comments = $conn->query("SELECT student_id, timestamp as login_time, 'COMMENT' as type FROM post_comments WHERE timestamp >= DATE_SUB(NOW(), INTERVAL $interval)");
        if($comments) while($cm = $comments->fetch_assoc()) $events[] = $cm;

        // Reactions (Likes)
        $reacts = $conn->query("SELECT student_id, timestamp as login_time, 'REACTION' as type FROM post_reactions WHERE timestamp >= DATE_SUB(NOW(), INTERVAL $interval)");
        if($reacts) while($rc = $reacts->fetch_assoc()) $events[] = $rc;

        // Alumni Signups
        $signups = $conn->query("SELECT student_id, created_at as login_time, 'ALUMNI_SIGNUP' as type FROM students WHERE is_alumni=1 AND created_at >= DATE_SUB(NOW(), INTERVAL $interval)");
        if($signups) while($s = $signups->fetch_assoc()) $events[] = $s;

        echo json_encode($events);
        exit();
    }

    // Handle Fetch Node Details (New Feature for Clickable Map)
    if (isset($_POST['action']) && $_POST['action'] === 'fetch_node_details') {
        $node = $_POST['node'];
        $output = "";
        
        if($node == 'DATABASE'){
            $s = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
            $p = $conn->query("SELECT COUNT(*) FROM posts")->fetch_row()[0];
            $m = $conn->query("SELECT COUNT(*) FROM direct_messages")->fetch_row()[0];
            $output = "<div style='text-align:left;'>
                <div style='color:#00ffaa; margin-bottom:5px; font-weight:bold; border-bottom:1px solid #00ffaa;'>DATABASE STATS</div>
                Students: <b style='color:#fff'>$s</b><br>
                Posts: <b style='color:#fff'>$p</b><br>
                Messages: <b style='color:#fff'>$m</b>
            </div>";
        }
        elseif($node == 'LOGIN_PORTAL'){
            $res = $conn->query("SELECT student_id, login_time FROM login_history ORDER BY login_time DESC LIMIT 3");
            $output = "<div style='text-align:left;'><div style='color:#00ccff; margin-bottom:5px; font-weight:bold; border-bottom:1px solid #00ccff;'>RECENT LOGINS</div>";
            while($r = $res->fetch_assoc()){
                $output .= "<div style='font-size:10px; border-bottom:1px solid #333; padding:2px;'>".htmlspecialchars($r['student_id'])." <span style='float:right; color:#aaa'>".date('H:i:s', strtotime($r['login_time']))."</span></div>";
            }
            $output .= "</div>";
        }
        elseif($node == 'STUDENT_FEED'){
            $res = $conn->query("SELECT student_name, timestamp FROM posts ORDER BY timestamp DESC LIMIT 3");
            $output = "<div style='text-align:left;'><div style='color:#00ffaa; margin-bottom:5px; font-weight:bold; border-bottom:1px solid #00ffaa;'>LATEST POSTS</div>";
            while($r = $res->fetch_assoc()){
                $output .= "<div style='font-size:10px; border-bottom:1px solid #333; padding:2px;'>".htmlspecialchars(substr($r['student_name'],0,15)).".. <span style='float:right; color:#aaa'>".date('H:i', strtotime($r['timestamp']))."</span></div>";
            }
            $output .= "</div>";
        }
        else {
            // Generic info for others
            $output = "<div style='text-align:center;'><div style='color:#ffd700; margin-bottom:5px; font-weight:bold;'>$node</div>Status: <span style='color:#00ffaa'>ACTIVE</span><br>Latency: <span style='color:#fff'>".rand(2, 15)."ms</span></div>";
        }
        echo $output;
        exit();
    }

    // Handle Fetch User Posts (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'fetch_user_posts') {
        $name = $_POST['student_name'];
        $stmt = $conn->prepare("SELECT * FROM posts WHERE student_name = ? ORDER BY timestamp DESC");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $posts = $stmt->get_result();
        
        if($posts->num_rows > 0){
            while($p = $posts->fetch_assoc()){
                echo '<div class="admin-post-item" id="post-row-'.$p['id'].'" style="background:rgba(255,255,255,0.05); padding:15px; margin-bottom:10px; border-radius:10px; border:1px solid rgba(255,255,255,0.1);">';
                echo '<div style="display:flex; justify-content:space-between; margin-bottom:5px;">';
                echo '<small style="color:#aaa;">'.date("M d, Y H:i", strtotime($p['timestamp'])).'</small>';
                echo '<button onclick="deleteUserPost('.$p['id'].')" style="background:#ff5555; color:white; border:none; padding:4px 10px; border-radius:3px; cursor:pointer; font-size:11px;">Delete</button>';
                echo '</div>';
                echo '<p style="color:#fff; margin:0; white-space: pre-wrap;">'.htmlspecialchars($p['content']).'</p>';
                
                // Check for media
                $pid = $p['id'];
                $media = $conn->query("SELECT * FROM post_media WHERE post_id='$pid'");
                if($media && $media->num_rows > 0){
                     echo '<div style="margin-top:10px; display:flex; gap:5px; overflow-x:auto;">';
                     while($m = $media->fetch_assoc()){
                         if($m['file_type'] == 'video'){
                             echo '<video src="'.$m['file_path'].'" style="height:60px; border-radius:5px;"></video>';
                         } else {
                             echo '<img src="'.$m['file_path'].'" style="height:60px; border-radius:5px;">';
                         }
                     }
                     echo '</div>';
                } elseif(!empty($p['media'])) {
                     // Legacy media
                     echo '<div style="margin-top:10px; color:#00ffaa; font-size:12px;">[Has Attachment]</div>';
                }
                echo '</div>';
            }
        } else {
            echo '<p style="color:#aaa; text-align:center; padding:20px;">No posts found for this user.</p>';
        }
        exit();
    }

    // Handle Delete Post (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_post_ajax') {
        $id = intval($_POST['id']);
        
        // Delete files
        $media_res = $conn->query("SELECT file_path FROM post_media WHERE post_id = $id");
        if($media_res){
            while($m = $media_res->fetch_assoc()){
                if(!empty($m['file_path']) && file_exists($m['file_path'])) @unlink($m['file_path']);
            }
        }
        $old_media = $conn->query("SELECT media FROM posts WHERE id = $id")->fetch_assoc();
        if($old_media && !empty($old_media['media']) && file_exists($old_media['media'])) @unlink($old_media['media']);

        $conn->query("DELETE FROM posts WHERE id=$id");
        $conn->query("DELETE FROM post_media WHERE post_id=$id");
        $conn->query("DELETE FROM post_comments WHERE post_id=$id");
        $conn->query("DELETE FROM post_reactions WHERE post_id=$id");
        $conn->query("DELETE FROM notifications WHERE post_id=$id");
        
        echo "success";
        exit();
    }

    // Handle PIN Verification
    if (isset($_POST['action']) && $_POST['action'] === 'verify_chat_pin') {
        $pin = $_POST['pin'];
        $res = $conn->query("SELECT pin_code FROM admin_security LIMIT 1");
        $row = $res->fetch_assoc();
        if ($row && $pin === $row['pin_code']) {
            $_SESSION['chat_pin_verified'] = true;
            header("Location: SACLICONNECT2.php?page=chats");
            exit();
        } else {
            $pin_error = "Incorrect PIN! Please try again.";
        }
    }

    // Handle PIN Verification for Passwords
    if (isset($_POST['action']) && $_POST['action'] === 'verify_pass_pin') {
        $pin = $_POST['pin'];
        $res = $conn->query("SELECT pin_code FROM admin_security LIMIT 1");
        $row = $res->fetch_assoc();
        if ($row && $pin === $row['pin_code']) {
            $_SESSION['pass_pin_verified'] = true;
            header("Location: SACLICONNECT2.php?page=passwords");
            exit();
        } else {
            $pass_pin_error = "Incorrect PIN! Please try again.";
        }
    }

    // Handle Admin Reply to Concern
    if (isset($_POST['action']) && $_POST['action'] === 'reply_concern') {
        $student_id = $_POST['student_id'];
        $message = trim($_POST['message']);
        if(!empty($message)){
            $stmt = $conn->prepare("INSERT INTO admin_concerns (student_id, message, sender_type) VALUES (?, ?, 'admin')");
            $stmt->bind_param("ss", $student_id, $message);
            $stmt->execute();
            $stmt->close();
        }
        // Redirect back to the same conversation
        header("Location: SACLICONNECT2.php?page=concerns&view_concern=" . urlencode($student_id));
        exit();
    }

    // Handle Admin Delete Concern Message
    if (isset($_POST['action']) && $_POST['action'] === 'delete_concern_msg') {
        $msg_id = $_POST['msg_id'];
        $student_id = $_POST['student_id'];
        $conn->query("DELETE FROM admin_concerns WHERE id = $msg_id");
        // Redirect back to the same conversation
        header("Location: SACLICONNECT2.php?page=concerns&view_concern=" . urlencode($student_id));
        exit();
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save_sidebar') {
        $conn->query("DELETE FROM sidebar_menu");
        $stmt = $conn->prepare("INSERT INTO sidebar_menu (label, icon, sort_order) VALUES (?, ?, ?)");
        $order = 0;
        
        if (!empty($_POST['sidebar_label'])) {
            foreach ($_POST['sidebar_label'] as $i => $label) {
                $label = trim($label);
                if ($label === '') continue;
                $icon = isset($_POST['sidebar_icon'][$i]) ? trim($_POST['sidebar_icon'][$i]) : '';
                $order++;
                $stmt->bind_param("ssi", $label, $icon, $order);
                $stmt->execute();
            }
        }
        $stmt->close();
        $updated = true;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save_subjects') {
        $conn->query("DELETE FROM subject_chats");
        $stmt = $conn->prepare("INSERT INTO subject_chats (name, url, icon, is_online, sort_order) VALUES (?, ?, ?, ?, ?)");
        $order = 0;
        if (!empty($_POST['subject_name'])) {
            foreach ($_POST['subject_name'] as $i => $name) {
                $name = trim($name);
                if ($name === '') continue;
                $url = isset($_POST['subject_url'][$i]) ? trim($_POST['subject_url'][$i]) : '#';
                
                // Handle Icon (Existing or New Upload)
                $icon = isset($_POST['existing_icon'][$i]) ? $_POST['existing_icon'][$i] : '';
                
                if (isset($_FILES['subject_icon_file']['name'][$i]) && $_FILES['subject_icon_file']['error'][$i] == 0) {
                    $ext = pathinfo($_FILES['subject_icon_file']['name'][$i], PATHINFO_EXTENSION);
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
                    if (in_array(strtolower($ext), $allowed)) {
                        $new_filename = "link_icon_" . time() . "_$i." . $ext;
                        if (!is_dir('uploads')) mkdir('uploads');
                        if (move_uploaded_file($_FILES['subject_icon_file']['tmp_name'][$i], "uploads/" . $new_filename)) {
                            $icon = $new_filename;
                        }
                    }
                }

                $online = isset($_POST['subject_online'][$i]) && $_POST['subject_online'][$i] == 1 ? 1 : 0;
                $order++;
                $stmt->bind_param("sssii", $name, $url, $icon, $online, $order);
                $stmt->execute();
            }
        }
        $stmt->close();
        $updated = true;
    }

    // Handle Update Student (Admin Edit)
    if (isset($_POST['action']) && $_POST['action'] === 'update_student') {
        $original_id = $_POST['original_student_id'];
        $new_id = $_POST['student_id'];
        $name = $_POST['student_name'];
        $course = $_POST['course'];
        $year = $_POST['year_level'];
        $bio = $_POST['bio'];
        $email = $_POST['email'];
        $phone = $_POST['phone'] ?? null;
        $hide_phone = isset($_POST['hide_phone']) ? 1 : 0;
        $new_password = $_POST['new_password'] ?? ''; // Get the new password
        
        $pic_filename = null;
        if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION)); // Fix: Use $_FILES['profile_pic']['name']
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if(in_array($ext, $allowed)){
                $pic_filename = "profile_" . $new_id . "_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $pic_filename);
            }
        }

        $sql_parts = [];
        $bind_params = [];
        $bind_types = "";

        // Update student_id if it changed
        if ($original_id !== $new_id) {
            $sql_parts[] = "student_id=?";
            $bind_params[] = $new_id;
            $bind_types .= "s";
        }
        $sql_parts[] = "student_name=?"; $bind_params[] = $name; $bind_types .= "s";
        $sql_parts[] = "course=?"; $bind_params[] = $course; $bind_types .= "s";
        $sql_parts[] = "year_level=?"; $bind_params[] = $year; $bind_types .= "s";
        $sql_parts[] = "bio=?"; $bind_params[] = $bio; $bind_types .= "s";
        $sql_parts[] = "email=?"; $bind_params[] = $email; $bind_types .= "s";
        $sql_parts[] = "phone=?"; $bind_params[] = $phone; $bind_types .= "s";
        $sql_parts[] = "hide_phone=?"; $bind_params[] = $hide_phone; $bind_types .= "i";
        if ($pic_filename) { $sql_parts[] = "profile_pic=?"; $bind_params[] = $pic_filename; $bind_types .= "s"; }
        // Only update password if a new one is provided
        if (!empty($new_password)) {
            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_parts[] = "password=?"; $bind_params[] = $hashed_new_password; $bind_types .= "s";
        }
        $sql = "UPDATE students SET " . implode(", ", $sql_parts) . " WHERE student_id=?";
        $bind_params[] = $original_id; $bind_types .= "s";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($bind_types, ...$bind_params);
        
        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            header("Location: SACLICONNECT2.php?page=students&error=" . urlencode("Conflict: Student ID '$new_id' is already assigned to another user."));
            exit();
        }

        // If ID (Password) changed, update all references in other tables
        if ($original_id !== $new_id) {
            $oid = $conn->real_escape_string($original_id);
            $nid = $conn->real_escape_string($new_id);
            
            $conn->query("UPDATE post_reactions SET student_id='$nid' WHERE student_id='$oid'");
            $conn->query("UPDATE post_comments SET student_id='$nid' WHERE student_id='$oid'");
            $conn->query("UPDATE notifications SET user_id='$nid' WHERE user_id='$oid'");
            $conn->query("UPDATE notifications SET actor_id='$nid' WHERE actor_id='$oid'");
            $conn->query("UPDATE direct_messages SET sender_id='$nid' WHERE sender_id='$oid'");
            $conn->query("UPDATE direct_messages SET receiver_id='$nid' WHERE receiver_id='$oid'");
            $conn->query("UPDATE group_chat_members SET user_id='$nid' WHERE user_id='$oid'");
            $conn->query("UPDATE group_chat_messages SET sender_id='$nid' WHERE sender_id='$oid'");
            $conn->query("UPDATE login_history SET student_id='$nid' WHERE student_id='$oid'");
            $conn->query("UPDATE post_tags SET student_id='$nid' WHERE student_id='$oid'");
            $conn->query("UPDATE evaluations SET student_id='$nid' WHERE student_id='$oid'");
            $conn->query("UPDATE sacli_room_members SET student_id='$nid' WHERE student_id='$oid'");
            $conn->query("UPDATE sacli_meeting_logs SET student_id='$nid' WHERE student_id='$oid'");
            $conn->query("UPDATE security_audit_logs SET user_id='$nid' WHERE user_id='$oid'");
        }
        $stmt->close();
        $updated = true;
    }

    // Handle Add Student (Admin Create)
    if (isset($_POST['action']) && $_POST['action'] === 'add_student') {
        $id = trim($_POST['student_id']);
        $name = trim($_POST['student_name']);
        $course = trim($_POST['course']);
        $year = trim($_POST['year_level']);
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $hide_phone = isset($_POST['hide_phone']) ? 1 : 0;
        $password = $_POST['password'] ?? ''; // Get the new password
       
        
        // Check if ID exists
        $chk_stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id=?");
        $chk_stmt->bind_param("s", $id);
        $chk_stmt->execute();
        if($chk_stmt->get_result()->num_rows == 0){
            $pic_filename = "";
            if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0){
                $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION)); // Fix: Use $_FILES['profile_pic']['name']
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                if(in_array($ext, $allowed)){
                    $pic_filename = "profile_" . $id . "_" . time() . "." . $ext;
                    if (!is_dir('uploads')) mkdir('uploads'); // Ensure directory exists
                    move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $pic_filename);
                }
            }
            try {
                $stmt = $conn->prepare("INSERT INTO students (student_id, student_name, course, year_level, profile_pic, email, phone, hide_phone, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param("sssssssis", $id, $name, $course, $year, $pic_filename, $email, $phone, $hide_phone, $password);
                $stmt->execute();
                $stmt->close();
                $updated = true;
            } catch (mysqli_sql_exception $e) {
                header("Location: SACLICONNECT2.php?page=students&error=" . urlencode("Database Error: Student ID '$id' is already registered."));
                exit();
            }
        } else {
            header("Location: SACLICONNECT2.php?page=students&error=" . urlencode("Error: A student with ID '$id' already exists."));
            exit();
        }
        $chk_stmt->close();
    }

    // Handle Delete Student
    if (isset($_POST['action']) && $_POST['action'] === 'delete_student') {
        $id = $_POST['student_id'];
        
        // Get student info for cleanup
        $s_query = $conn->query("SELECT student_name, profile_pic FROM students WHERE student_id='$id'");
        if ($s_query->num_rows > 0) {
            $s_row = $s_query->fetch_assoc();
            $s_name = $s_row['student_name'];
            $s_pic = $s_row['profile_pic'];

            // 1. Delete Profile Pic
            if (!empty($s_pic) && file_exists("uploads/" . $s_pic)) unlink("uploads/" . $s_pic);

            // 2. Delete Posts by this student (and media files)
            $p_query = $conn->query("SELECT id, media FROM posts WHERE student_name='$s_name'");
            while ($p = $p_query->fetch_assoc()) {
                if (!empty($p['media']) && file_exists($p['media'])) unlink($p['media']);
                // Delete related data for this post
                $pid = $p['id'];
                $conn->query("DELETE FROM post_comments WHERE post_id='$pid'");
                $conn->query("DELETE FROM post_reactions WHERE post_id='$pid'");
                $conn->query("DELETE FROM notifications WHERE post_id='$pid'");
            }
            $conn->query("DELETE FROM posts WHERE student_name='$s_name'");

            // 3. Delete Interactions (Comments, Reactions, Messages, Notifs)
            $conn->query("DELETE FROM post_comments WHERE student_id='$id'");
            $conn->query("DELETE FROM post_reactions WHERE student_id='$id'");
            $conn->query("DELETE FROM direct_messages WHERE sender_id='$id' OR receiver_id='$id'");
            $conn->query("DELETE FROM notifications WHERE user_id='$id' OR actor_id='$id'");
            
            // 4. Delete Student Record
            $conn->query("DELETE FROM students WHERE student_id='$id'");
            $updated = true;
        }
    }

    // Handle Admin Delete Chat Message
    if (isset($_POST['action']) && $_POST['action'] === 'admin_delete_msg') {
        $msg_id = $_POST['msg_id'];
        $conn->query("DELETE FROM direct_messages WHERE id = $msg_id");
        // Stay on page, maybe add anchor
        $updated = true;
    }

    // Handle Admin Delete Entire Conversation
    if (isset($_POST['action']) && $_POST['action'] === 'admin_delete_convo') {
        $p1 = $_POST['p1'];
        $p2 = $_POST['p2'];
        $stmt = $conn->prepare("DELETE FROM direct_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
        $stmt->bind_param("ssss", $p1, $p2, $p2, $p1);
        $stmt->execute();
        $stmt->close();
        $updated = true;
    }

    // Handle Add Calendar Event (Admin)
    if (isset($_POST['action']) && $_POST['action'] === 'add_event') {
        $title = $_POST['title'];
        $date = $_POST['event_date'];
        $desc = $_POST['description'];
        $imagePath = "";

        if(isset($_FILES['event_image']) && $_FILES['event_image']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if(in_array($ext, $allowed)){
                $imagePath = "uploads/event_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                move_uploaded_file($_FILES['event_image']['tmp_name'], $imagePath);
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO calendar_events (title, event_date, description, event_image) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $date, $desc, $imagePath);
        $stmt->execute();
        $stmt->close();

        // Send Email Notification to Students
        $s_emails = $conn->query("SELECT email FROM students WHERE email != '' AND email IS NOT NULL");
        if($s_emails->num_rows > 0){
            $subject = "New Event: " . $title;
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: <admin@sacliconnect.com>' . "\r\n";
            
            $msgContent = "
            <div style='background:#0a1f16; color:#fff; padding:20px; font-family:Arial, sans-serif;'>
                <h2 style='color:#00ffaa;'>📅 New Event: ".htmlspecialchars($title)."</h2>
                <p><strong>Date:</strong> ".date("F d, Y", strtotime($date))."</p>
                <p style='background:rgba(255,255,255,0.1); padding:15px; border-radius:10px;'>".nl2br(htmlspecialchars($desc))."</p>
                <p><small>Log in to SacliConnect for more details.</small></p>
            </div>";

            while($row = $s_emails->fetch_assoc()){
                @mail($row['email'], $subject, $msgContent, $headers);
            }
        }

        // Notify All Students (Internal Notification)
        $students = $conn->query("SELECT student_id FROM students");
        while($s = $students->fetch_assoc()){
            $conn->query("INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES ('".$s['student_id']."', 'Admin', 'event', 0)");
        }
        $updated = true;
    }

    // Handle Delete Event
    if (isset($_POST['action']) && $_POST['action'] === 'delete_event') {
        $id = (int)$_POST['event_id'];
        // Delete image file if exists
        $q = $conn->query("SELECT event_image FROM calendar_events WHERE id=$id");
        if($q && $r=$q->fetch_assoc()){
            if(!empty($r['event_image']) && file_exists($r['event_image'])) unlink($r['event_image']);
        }
        $conn->query("DELETE FROM calendar_events WHERE id=$id");
        $updated = true;
    }

    // Handle Add Teacher
    if (isset($_POST['action']) && $_POST['action'] === 'add_teacher') {
        $name = $_POST['name'];
        $dept = $_POST['department'];
        $pos = $_POST['position'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $phone = trim($_POST['phone'] ?? '');
        $hide_phone = isset($_POST['hide_phone']) ? 1 : 0;
        $pic_filename = "";
        
        if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if(in_array($ext, $allowed)){
                $pic_filename = "teacher_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $pic_filename);
            }
        }
        $stmt = $conn->prepare("INSERT INTO teachers (name, department, position, email, profile_pic, password, phone, hide_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssi", $name, $dept, $pos, $email, $pic_filename, $password, $phone, $hide_phone);
        $stmt->execute();
        $updated = true;
    }

    // Handle Delete Teacher
    if (isset($_POST['action']) && $_POST['action'] === 'delete_teacher') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM teachers WHERE id=$id");
        $updated = true;
    }

    // Handle Update Teacher
    if (isset($_POST['action']) && $_POST['action'] === 'update_teacher') {
        $id = (int)$_POST['teacher_id'];
        $name = $_POST['name'];
        $dept = $_POST['department'];
        $pos = $_POST['position'];
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $hide_phone = isset($_POST['hide_phone']) ? 1 : 0;
        
        $sql = "UPDATE teachers SET name=?, department=?, position=?, email=?, phone=?, hide_phone=? WHERE id=?";
        $stmt = $conn->prepare($sql); // Prepare the statement here
        if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if(in_array($ext, $allowed)){
                $pic_filename = "teacher_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $pic_filename);
                $conn->query("UPDATE teachers SET profile_pic='$pic_filename' WHERE id=$id");
            }
        }
        $stmt->bind_param("sssssii", $name, $dept, $pos, $email, $phone, $hide_phone, $id);
        $stmt->execute();
        $updated = true;
    }

    // Handle Add Alumni
    if (isset($_POST['action']) && $_POST['action'] === 'add_alumni') {
        $name = $_POST['name'];
        $student_id = $_POST['student_id'];
        $course = $_POST['course'];
        $batch = $_POST['batch_year'];
        $birthdate = $_POST['birthdate'];
        $status = $_POST['status'];
        $location = $_POST['location'];
        $phone = trim($_POST['phone'] ?? '');
        $pic_filename = "";

        if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if(in_array($ext, $allowed)){
                $pic_filename = "alumni_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $pic_filename);
            }
        }
        $stmt = $conn->prepare("INSERT INTO alumni (name, student_id, course, batch_year, birthdate, status, location, profile_pic, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $name, $student_id, $course, $batch, $birthdate, $status, $location, $pic_filename, $phone);
        $stmt->execute();
        $updated = true;
    }

    // Handle Delete Alumni
    if (isset($_POST['action']) && $_POST['action'] === 'delete_alumni') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM alumni WHERE id=$id");
        $updated = true;
    }

    // Handle Add Achievement
    if (isset($_POST['action']) && $_POST['action'] === 'add_achievement') {
        $name = $_POST['student_name'];
        $category = $_POST['category'];
        $title = $_POST['title'];
        $desc = $_POST['description'];
        $imagePath = "";

        if(isset($_FILES['image']) && $_FILES['image']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if(in_array($ext, $allowed)){
                $imagePath = "achievement_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                move_uploaded_file($_FILES['image']['tmp_name'], "uploads/" . $imagePath);
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO achievements (student_name, category, title, description, image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $category, $title, $desc, $imagePath);
        $stmt->execute();
        $updated = true;
    }

    // Handle Delete Achievement
    if (isset($_POST['action']) && $_POST['action'] === 'delete_achievement') {
        $id = (int)$_POST['id'];
        // Delete image file if exists
        $q = $conn->query("SELECT image FROM achievements WHERE id=$id");
        if($q && $r=$q->fetch_assoc()){
            if(!empty($r['image']) && file_exists("uploads/".$r['image'])) unlink("uploads/".$r['image']);
        }
        $conn->query("DELETE FROM achievements WHERE id=$id");
        $updated = true;
    }

    // Handle Change Student ID (Password Reset)
    if (isset($_POST['action']) && $_POST['action'] === 'change_student_id') {
        $student_id = $_POST['old_id'];
        $new_password = trim($_POST['new_id']);
        $admin_user = $_SESSION['admin_username'];
        
        if (!empty($new_password)) {
            // Check if a pending request already exists for this student
            $chk_stmt = $conn->prepare("SELECT id FROM password_change_requests WHERE student_id = ? AND status = 'pending'");
            $chk_stmt->bind_param("s", $student_id);
            $chk_stmt->execute();
            $chk_res = $chk_stmt->get_result();

            if ($chk_res->num_rows > 0) {
                header("Location: SACLICONNECT2.php?page=passwords&request_exists=1");
                exit();
            }

            // 1. Insert the request
            $stmt = $conn->prepare("INSERT INTO password_change_requests (admin_username, student_id, new_password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $admin_user, $student_id, $new_password);
            $stmt->execute();
            $request_id = $stmt->insert_id;
            $stmt->close();

            // 2. Send notification to the student
            if ($request_id > 0) {
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES (?, ?, 'pass_change_request', ?)");
                $notif_stmt->bind_param("ssi", $student_id, $admin_user, $request_id);
                $notif_stmt->execute();
                $notif_stmt->close();
            }
            
            header("Location: SACLICONNECT2.php?page=passwords&request_sent=1");
            exit();
        }
    }

    // Handle Clear Password Request
    if (isset($_POST['action']) && $_POST['action'] === 'clear_pass_request') {
        $request_id = $_POST['request_id'];
        $admin_user = $_SESSION['admin_username'];
        $stmt = $conn->prepare("DELETE FROM password_change_requests WHERE id = ? AND admin_username = ?");
        $stmt->bind_param("is", $request_id, $admin_user);
        if($stmt->execute()){
            $updated = true;
        }
    }

    // Handle Change Admin Password
    if (isset($_POST['action']) && $_POST['action'] === 'change_admin_pass') {
        $id = $_POST['admin_id'];
        $pass = $_POST['new_pass'];
        if (!empty($pass)) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins2 SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hash, $id);
            $stmt->bind_param("si", $pass, $id);
            $stmt->execute();
            $updated = true;
        }
    }

    // Handle Change Teacher Password
    if (isset($_POST['action']) && $_POST['action'] === 'change_teacher_pass') {
        $id = $_POST['teacher_id'];
        $pass = $_POST['new_pass'];
        if (!empty($pass)) {
            $stmt = $conn->prepare("UPDATE teachers SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $pass, $id);
            $stmt->execute();
            $updated = true;
        }
    }

    // Handle Move Student to Alumni
    if (isset($_POST['action']) && $_POST['action'] === 'move_to_alumni') {
        $id = $_POST['student_id'];
        $course = $_POST['course'];
        $batch = $_POST['batch_year'];
        $birthdate = $_POST['birthdate'];
        $email = $_POST['email'];
        $status = $_POST['status'];
        $location = $_POST['location'];

        // Mark as alumni in students table (so they can still login but profile changes)
        $stmt = $conn->prepare("UPDATE students SET is_alumni = 1, year_level = 'Alumni', course = ?, email = ? WHERE student_id = ?");
        $stmt->bind_param("sss", $course, $email, $id);
        $stmt->execute();
        $stmt->close();
        
        // Copy to alumni table (so they appear in Alumni directory)
        $s = $conn->query("SELECT * FROM students WHERE student_id = '$id'")->fetch_assoc();
        if($s){
            $name = $conn->real_escape_string($s['student_name']);
            $pic = $conn->real_escape_string($s['profile_pic']);
            
            // Check if already in alumni table to avoid duplicates
            $chk = $conn->query("SELECT id FROM alumni WHERE student_id = '$id'");
            if($chk->num_rows == 0){
                $stmt = $conn->prepare("INSERT INTO alumni (student_id, name, course, batch_year, birthdate, profile_pic, status, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $id, $name, $course, $batch, $birthdate, $pic, $status, $location);
                $stmt->execute();
                $stmt->close();
            }
        }
        $updated = true;
    }

    // Handle Update Alumni
    if (isset($_POST['action']) && $_POST['action'] === 'update_alumni') {
        $id = (int)$_POST['alumni_id'];
        $name = $_POST['name'];
        $student_id = $_POST['student_id'];
        $course = $_POST['course'];
        $batch = $_POST['batch_year'];
        $birthdate = $_POST['birthdate'];
        $status = $_POST['status'];
        $phone = $_POST['phone'] ?? '';
        $location = $_POST['location'];
        
        $sql = "UPDATE alumni SET name=?, student_id=?, course=?, batch_year=?, birthdate=?, status=?, location=? WHERE id=?";
        
        if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if(in_array($ext, $allowed)){
                $pic_filename = "alumni_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $pic_filename);
                $conn->query("UPDATE alumni SET profile_pic='$pic_filename' WHERE id=$id");
            }
        }
        $stmt = $conn->prepare("UPDATE alumni SET name=?, student_id=?, course=?, batch_year=?, birthdate=?, status=?, location=?, phone=? WHERE id=?");
        $stmt->bind_param("ssssssssi", $name, $student_id, $course, $batch, $birthdate, $status, $location, $phone, $id);
        $stmt->execute();
        $updated = true;
    }

    // Handle Revert Student from Alumni
    if (isset($_POST['action']) && $_POST['action'] === 'revert_to_student') {
        $id = $_POST['student_id'];
        // Revert is_alumni flag and reset year level to 4th Year (Admin can edit later)
        $check_student = $conn->query("SELECT student_id FROM students WHERE student_id = '$id'");

        if ($check_student->num_rows > 0) {
            $conn->query("UPDATE students SET is_alumni = 0, year_level = '4th Year' WHERE student_id = '$id'");
        } else {
            // If not in students table, create a new student record from alumni data
            $alumni_data = $conn->query("SELECT * FROM alumni WHERE student_id = '$id'")->fetch_assoc();
            if ($alumni_data) {
                $name = $conn->real_escape_string($alumni_data['name']);
                $course = $conn->real_escape_string($alumni_data['course']);
                $pic = $conn->real_escape_string($alumni_data['profile_pic']);
                // Insert with student_id as password default logic (login check)
                $stmt = $conn->prepare("INSERT INTO students (student_id, student_name, course, year_level, profile_pic, is_alumni) VALUES (?, ?, ?, '4th Year', ?, 0)");
                $stmt->bind_param("ssss", $id, $name, $course, $pic);
                $stmt->execute();
            }
        }
        // Remove from alumni table
        $conn->query("DELETE FROM alumni WHERE student_id = '$id'");
        
        // Fallback for legacy records
        $s = $conn->query("SELECT student_name FROM students WHERE student_id = '$id'")->fetch_assoc();
        if($s){
            $name = $conn->real_escape_string($s['student_name']);
            $conn->query("DELETE FROM alumni WHERE name = '$name' AND (student_id IS NULL OR student_id = '')");
        }
        $updated = true;
    }

    // Handle Admin Email Change Request (Send OTP to current email)
    if (isset($_POST['action']) && $_POST['action'] === 'admin_email_verify_send') {
        header('Content-Type: application/json');
        $current_email = $admin_data['email'] ?? '';
        
        if (empty($current_email)) {
            echo json_encode(['status' => 'error', 'message' => 'No email found to verify.']);
            exit();
        }

        $otp = rand(100000, 999999);
        $_SESSION['admin_email_otp'] = $otp;
        $_SESSION['admin_email_otp_expiry'] = time() + 300; // Valid for 5 mins

        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'sacliconnect20@gmail.com';
                $mail->Password   = 'umrrmsyujepjopbo'; 
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->SMTPOptions = array('ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true));
                $mail->setFrom('sacliconnect20@gmail.com', 'SacliConnect Admin');
                $mail->addAddress($current_email);
                $mail->isHTML(true);
                $mail->Subject = 'Admin Email Change Authorization';
                $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; padding: 30px; border: 2px solid #00ffaa; border-radius: 15px; background-color: #0a1f16; color: #fff; text-align: center; max-width: 500px; margin: auto;'>
                        <h2 style='color: #00ffaa; text-transform: uppercase; letter-spacing: 3px; margin: 0 0 15px;'>Identity Authorization</h2>
                        <p style='font-size: 15px; color: #b0fce0; line-height: 1.6;'>A request has been made to update the administrative email uplink. To authorize this change, please use the secure token below:</p>
                        <div style='margin: 30px 0; padding: 20px; background: rgba(0, 255, 170, 0.05); border: 1px dashed #00ffaa; border-radius: 10px;'>
                            <div style='font-size: 11px; color: #00ffaa; margin-bottom: 8px; font-family: monospace; letter-spacing: 1px;'>// SECURE_TOKEN</div>
                            <div style='font-size: 42px; font-weight: 900; color: #fff; letter-spacing: 8px; font-family: \"Courier New\", Courier, monospace; text-shadow: 0 0 15px rgba(0, 255, 170, 0.5);'>$otp</div>
                        </div>
                        <p style='font-size: 13px; color: #888;'>This verification sequence will expire in 5 minutes. If you did not initiate this request, please investigate the system access logs immediately.</p>
                        <div style='margin-top: 25px; font-size: 10px; color: #509b83; font-family: monospace; opacity: 0.6;'>
                            SACLICONNECT_SECURE_UPLINK_PROTOCOL_v4.0
                        </div>
                    </div>";
                $mail->send();
                
                $parts = explode("@", $current_email);
                $masked = substr($parts[0], 0, 2) . str_repeat('*', strlen($parts[0])-2) . "@" . $parts[1];
                
                echo json_encode(['status' => 'success', 'masked' => $masked]);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'Uplink Failed: ' . $mail->ErrorInfo]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Security library not found.']);
        }
        exit();
    }

    // Handle Admin OTP Verification
    if (isset($_POST['action']) && $_POST['action'] === 'admin_email_verify_otp') {
        header('Content-Type: application/json');
        $otp = $_POST['otp'];
        if (isset($_SESSION['admin_email_otp']) && $_SESSION['admin_email_otp'] == $otp && time() < $_SESSION['admin_email_otp_expiry']) {
            $_SESSION['admin_email_verified'] = true;
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code.']);
        }
        exit();
    }

    // Handle Update Admin Profile (Pic & Email)
    if (isset($_POST['action']) && $_POST['action'] === 'update_admin_profile') {
        $email = $conn->real_escape_string($_POST['admin_email']);
        
        // Security Lock: If an email exists, check for verification flag before allowing change
        $current_email = $admin_data['email'] ?? '';
        if (!empty($current_email) && $email !== $current_email) {
            if (!isset($_SESSION['admin_email_verified']) || $_SESSION['admin_email_verified'] !== true) {
                header("Location: SACLICONNECT2.php?updated=0&error=" . urlencode("Action Denied: Identity verification required for email change."));
                exit();
            }
            unset($_SESSION['admin_email_verified']); // Reset flag after use
        }

        $conn->query("UPDATE admins2 SET email = '$email' WHERE id = '".$_SESSION['admin_id']."'");
        
        if(isset($_FILES['admin_pic']) && $_FILES['admin_pic']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['admin_pic']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if(in_array($ext, $allowed)){
                $new_name = "admin_" . $_SESSION['admin_id'] . "_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                move_uploaded_file($_FILES['admin_pic']['tmp_name'], "uploads/" . $new_name);
                $conn->query("UPDATE admins2 SET profile_pic = '$new_name' WHERE id = '".$_SESSION['admin_id']."'");
            }
        }
        $updated = true;
    }

    // Handle Update Video Mute Setting
    if (isset($_POST['action']) && $_POST['action'] === 'update_video_mute') {
        $muted = isset($_POST['video_muted']) ? '1' : '0';
        $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('login_video_muted', '$muted') ON DUPLICATE KEY UPDATE setting_value='$muted'");
        $updated = true;
    }

    // Handle Update Login Video
    if (isset($_POST['action']) && $_POST['action'] === 'update_login_video') {
        if(isset($_FILES['login_video_file']) && $_FILES['login_video_file']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['login_video_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['mp4', 'webm', 'ogg', 'mov'];
            if(in_array($ext, $allowed)){
                $new_filename = "login_video_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                if(move_uploaded_file($_FILES['login_video_file']['tmp_name'], "uploads/" . $new_filename)){
                    $db_path = "uploads/" . $new_filename;
                    
                    // Delete old video if it exists in uploads
                    $old_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='login_video'");
                    if($old_res && $row = $old_res->fetch_assoc()){
                        if(strpos($row['setting_value'], 'uploads/') === 0 && file_exists($row['setting_value'])) @unlink($row['setting_value']);
                    }

                    $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('login_video', '$db_path') ON DUPLICATE KEY UPDATE setting_value='$db_path'");
                    $updated = true;
                }
            }
        }
    }

    // Handle Update Halloween Video
    if (isset($_POST['action']) && $_POST['action'] === 'update_halloween_video') {
        if(isset($_FILES['halloween_video_file']) && $_FILES['halloween_video_file']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['halloween_video_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['mp4', 'webm', 'ogg', 'mov'];
            if(in_array($ext, $allowed)){
                $new_filename = "halloween_video_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                if(move_uploaded_file($_FILES['halloween_video_file']['tmp_name'], "uploads/" . $new_filename)){
                    $db_path = "uploads/" . $new_filename;
                    $key = 'halloween_video';
                    $old_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='$key'");
                    if($old_res && $row = $old_res->fetch_assoc()) if(strpos($row['setting_value'], 'uploads/') === 0 && file_exists($row['setting_value'])) @unlink($row['setting_value']);
                    $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('$key', '$db_path') ON DUPLICATE KEY UPDATE setting_value='$db_path'");
                    $updated = true;
                }
            }
        }
    }

    // Handle Update Christmas Video
    if (isset($_POST['action']) && $_POST['action'] === 'update_christmas_video') {
        if(isset($_FILES['christmas_video_file']) && $_FILES['christmas_video_file']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['christmas_video_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['mp4', 'webm', 'ogg', 'mov'];
            if(in_array($ext, $allowed)){
                $new_filename = "christmas_video_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                if(move_uploaded_file($_FILES['christmas_video_file']['tmp_name'], "uploads/" . $new_filename)){
                    $db_path = "uploads/" . $new_filename;
                    $key = 'christmas_video';
                    $old_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='$key'");
                    if($old_res && $row = $old_res->fetch_assoc()) if(strpos($row['setting_value'], 'uploads/') === 0 && file_exists($row['setting_value'])) @unlink($row['setting_value']);
                    $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('$key', '$db_path') ON DUPLICATE KEY UPDATE setting_value='$db_path'");
                    $updated = true;
                }
            }
        }
    }

    // Handle Update Summer Video
    if (isset($_POST['action']) && $_POST['action'] === 'update_summer_video') {
        if(isset($_FILES['summer_video_file']) && $_FILES['summer_video_file']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['summer_video_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['mp4', 'webm', 'ogg', 'mov'];
            if(in_array($ext, $allowed)){
                $new_filename = "summer_video_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                if(move_uploaded_file($_FILES['summer_video_file']['tmp_name'], "uploads/" . $new_filename)){
                    $db_path = "uploads/" . $new_filename;
                    $key = 'summer_video';
                    $old_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='$key'");
                    if($old_res && $row = $old_res->fetch_assoc()) if(strpos($row['setting_value'], 'uploads/') === 0 && file_exists($row['setting_value'])) @unlink($row['setting_value']);
                    $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('$key', '$db_path') ON DUPLICATE KEY UPDATE setting_value='$db_path'");
                    $updated = true;
                }
            }
        }
    }

    // Handle Update New Year Video
    if (isset($_POST['action']) && $_POST['action'] === 'update_new_year_video') {
        if(isset($_FILES['new_year_video_file']) && $_FILES['new_year_video_file']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['new_year_video_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['mp4', 'webm', 'ogg', 'mov'];
            if(in_array($ext, $allowed)){
                $new_filename = "new_year_video_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                if(move_uploaded_file($_FILES['new_year_video_file']['tmp_name'], "uploads/" . $new_filename)){
                    $db_path = "uploads/" . $new_filename;
                    $key = 'new_year_video';
                    $old_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='$key'");
                    if($old_res && $row = $old_res->fetch_assoc()) if(strpos($row['setting_value'], 'uploads/') === 0 && file_exists($row['setting_value'])) @unlink($row['setting_value']);
                    $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('$key', '$db_path') ON DUPLICATE KEY UPDATE setting_value='$db_path'");
                    $updated = true;
                }
            }
        }
    }

    // Handle Update Login Background Logo 1 (Top Left)
    if (isset($_POST['action']) && $_POST['action'] === 'update_bg_logo1') {
        if(isset($_FILES['bg_logo1_file']) && $_FILES['bg_logo1_file']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['bg_logo1_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            if(in_array($ext, $allowed)){
                $new_filename = "bg_logo1_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                if(move_uploaded_file($_FILES['bg_logo1_file']['tmp_name'], "uploads/" . $new_filename)){
                    $db_path = "uploads/" . $new_filename;
                    $key = 'login_bg_logo1';
                    $old_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='$key'");
                    if($old_res && $row = $old_res->fetch_assoc()) if(strpos($row['setting_value'], 'uploads/') === 0 && file_exists($row['setting_value'])) @unlink($row['setting_value']);
                    $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('$key', '$db_path') ON DUPLICATE KEY UPDATE setting_value='$db_path'");
                    $updated = true;
                }
            }
        }
    }

    // Handle Update Login Background Logo 2 (Bottom Right)
    if (isset($_POST['action']) && $_POST['action'] === 'update_bg_logo2') {
        if(isset($_FILES['bg_logo2_file']) && $_FILES['bg_logo2_file']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES['bg_logo2_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            if(in_array($ext, $allowed)){
                $new_filename = "bg_logo2_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads');
                if(move_uploaded_file($_FILES['bg_logo2_file']['tmp_name'], "uploads/" . $new_filename)){
                    $db_path = "uploads/" . $new_filename;
                    $key = 'login_bg_logo';
                    $old_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='$key'");
                    if($old_res && $row = $old_res->fetch_assoc()) if(strpos($row['setting_value'], 'uploads/') === 0 && file_exists($row['setting_value'])) @unlink($row['setting_value']);
                    $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('$key', '$db_path') ON DUPLICATE KEY UPDATE setting_value='$db_path'");
                    $updated = true;
                }
            }
        }
    }

    // Handle Update Site Theme
    if (isset($_POST['action']) && $_POST['action'] === 'update_site_theme') {
        $theme = $_POST['site_theme'];
        $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('site_theme', '$theme') ON DUPLICATE KEY UPDATE setting_value='$theme'");
        if (isset($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'theme' => $theme]);
            exit();
        }
        $updated = true;
    }

    // Handle Toggle Evaluation Lock
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_eval_lock') {
        $status = $_POST['status']; // '1' or '0'
        $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('evaluation_locked', '$status') ON DUPLICATE KEY UPDATE setting_value='$status'");
        $updated = true;
    }

    // Handle Add Evaluation Question
    if (isset($_POST['action']) && $_POST['action'] === 'add_eval_question') {
        $q = trim($_POST['question']);
        $cat = trim($_POST['category']);
        if(empty($cat)) $cat = 'General';
        if(!empty($q)){
            $stmt = $conn->prepare("INSERT INTO evaluation_questions (question, category) VALUES (?, ?)");
            $stmt->bind_param("ss", $q, $cat);
            $stmt->execute();
            $updated = true;
        }
    }
    // Handle Delete Evaluation Question
    if (isset($_POST['action']) && $_POST['action'] === 'delete_eval_question') {
        $id = (int)$_POST['id'];
        $conn->query("DELETE FROM evaluation_questions WHERE id=$id");
        $updated = true;
    }

    // Handle Reset All Evaluations
    if (isset($_POST['action']) && $_POST['action'] === 'reset_all_evaluations') {
        $conn->query("TRUNCATE TABLE evaluation_answers");
        $conn->query("TRUNCATE TABLE evaluations");
        $updated = true;
    }

    // Handle Restrict User (Student/Teacher/Alumni)
    if (isset($_POST['action']) && $_POST['action'] === 'restrict_user') {
        $user_type = $_POST['user_type']; // 'student' or 'teacher'
        $id = $_POST['user_id'];
        $mode = $_POST['restriction_mode']; // 'lift' or 'set'

        if ($mode === 'lift') {
            $sql = ($user_type === 'teacher') ? "UPDATE teachers SET is_restricted = 0, restriction_end_date = NULL WHERE id = ?" : "UPDATE students SET is_restricted = 0, restriction_end_date = NULL WHERE student_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(($user_type === 'teacher' ? 'i' : 's'), $id);
            $stmt->execute();
        } elseif ($mode === 'set') {
            $amount = (int)$_POST['duration_val'];
            $unit = $_POST['duration_unit']; // 'days', 'months', 'years'
            if ($amount > 0) {
                $end_date = date('Y-m-d H:i:s', strtotime("+$amount $unit"));
                $sql = ($user_type === 'teacher') ? "UPDATE teachers SET is_restricted = 1, restriction_end_date = ? WHERE id = ?" : "UPDATE students SET is_restricted = 1, restriction_end_date = ? WHERE student_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param(($user_type === 'teacher' ? 'si' : 'ss'), $end_date, $id);
                $stmt->execute();
            }
        }
        $updated = true;
    }

    // Handle Deactivate User Indefinitely (Transfer/Left School)
    if (isset($_POST['action']) && $_POST['action'] === 'deactivate_user_indefinitely') {
        $user_type = $_POST['user_type']; // 'student' or 'teacher'
        $id = $_POST['user_id'];
        
        $sql = ($user_type === 'teacher') ? "UPDATE teachers SET is_restricted = 1, restriction_end_date = NULL WHERE id = ?" : "UPDATE students SET is_restricted = 1, restriction_end_date = NULL WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        if ($user_type === 'teacher') {
            $stmt->bind_param("i", $id);
        } else {
            $stmt->bind_param("s", $id);
        }
        $stmt->execute();
        $updated = true;
    }

    // Handle Fetch Users for Deactivation Modal (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'fetch_users_deactivate') {
        $search = $_POST['search'] ?? '';
        $term = "%$search%";
        $found = false;

        // Fetch Students
        $stmt = $conn->prepare("SELECT student_id as id, student_name as name, 'student' as type, profile_pic FROM students WHERE is_alumni = 0 AND is_restricted = 0 AND (student_name LIKE ? OR student_id LIKE ?) LIMIT 10");
        $stmt->bind_param("ss", $term, $term);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()){
            $found = true;
            $pic = !empty($row['profile_pic']) ? "uploads/".$row['profile_pic'] : "assets/images/3icons8-student-64.png";
            echo '<div style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid rgba(255,255,255,0.05);">';
            echo '<div style="display:flex; align-items:center; gap:10px;"><img src="'.$pic.'" style="width:35px; height:35px; border-radius:50%; object-fit:cover;"><div><div style="font-weight:bold; color:#fff;">'.htmlspecialchars($row['name']).'</div><small style="color:#aaa;">Student • '.htmlspecialchars($row['id']).'</small></div></div>';
            echo '<button onclick="confirmDeactivate(\'student\', \''.$row['id'].'\', \''.htmlspecialchars(addslashes($row['name'])).'\')" style="background:#ff5555; color:white; border:none; padding:6px 12px; border-radius:5px; cursor:pointer; font-size:12px; font-weight:bold;">Deactivate</button>';
            echo '</div>';
        }

        // Fetch Teachers
        $stmt = $conn->prepare("SELECT id, name, 'teacher' as type, profile_pic FROM teachers WHERE is_restricted = 0 AND name LIKE ? LIMIT 10");
        $stmt->bind_param("s", $term);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()){
            $found = true;
            $pic = !empty($row['profile_pic']) ? "uploads/".$row['profile_pic'] : "4icons8-teacher-50.png";
            echo '<div style="display:flex; justify-content:space-between; align-items:center; padding:10px; border-bottom:1px solid rgba(255,255,255,0.05);">';
            echo '<div style="display:flex; align-items:center; gap:10px;"><img src="'.$pic.'" style="width:35px; height:35px; border-radius:50%; object-fit:cover;"><div><div style="font-weight:bold; color:#fff;">'.htmlspecialchars($row['name']).'</div><small style="color:#aaa;">Teacher</small></div></div>';
            echo '<button onclick="confirmDeactivate(\'teacher\', \''.$row['id'].'\', \''.htmlspecialchars(addslashes($row['name'])).'\')" style="background:#ff5555; color:white; border:none; padding:6px 12px; border-radius:5px; cursor:pointer; font-size:12px; font-weight:bold;">Deactivate</button>';
            echo '</div>';
        }
        if(!$found) echo '<div style="text-align:center; padding:15px; color:#888;">No active users found matching your search.</div>';
        exit();
    }

    // Handle Reactivate User
    if (isset($_POST['action']) && $_POST['action'] === 'reactivate_user') {
        $user_type = $_POST['user_type'];
        $id = $_POST['user_id'];
        
        $sql = ($user_type === 'teacher') ? "UPDATE teachers SET is_restricted = 0, restriction_end_date = NULL WHERE id = ?" : "UPDATE students SET is_restricted = 0, restriction_end_date = NULL WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        if ($user_type === 'teacher') {
            $stmt->bind_param("i", $id);
        } else {
            $stmt->bind_param("s", $id);
        }
        $stmt->execute();
        $updated = true;
    }

    // Handle Purge Folder (Disk Cleanup - Deletes files but keeps DB records)
    if (isset($_POST['action']) && $_POST['action'] === 'purge_folder') {
        $view = $_POST['view_type'];
        $target_dir = '';
        if ($view === 'submissions') $target_dir = 'submissions/';
        elseif ($view === 'storage') $target_dir = 'uploads/storage/';
        else $target_dir = 'uploads/';

        if (is_dir($target_dir)) {
            $files = array_diff(scandir($target_dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $target_dir . $file;
                if (!is_file($path)) continue;

                // PROTECTION LOGIC: Do NOT delete profile pics, covers, videos, or system icons
                $is_protected = preg_match('/^(profile_|teacher_|cover_|admin_|admin_pic_|bg_logo|login_video_|halloween_video_|christmas_video_|summer_video_|new_year_video_|link_icon_|achievement_|st40|St\.Anne_logo)/i', $file);
                $is_core_asset = in_array($file, ['St.Anne_logo.png', 'Adobe Express - file.png', 'communication.png', 'folder.png', 'network-access.png', 'anxiety.png', 'block-user.png', 'ST40.png']);
                
                // If it's the main uploads folder, apply protection. 
                // (Storage and Submissions are usually cleared entirely to save space)
                if ($view === 'uploads' && ($is_protected || $is_core_asset)) {
                    continue;
                }

                @unlink($path);
            }
            $updated = true;
        }
    }

    // Handle Sync Disk to Registry (Scans folders and populates DB)
    if (isset($_POST['action']) && $_POST['action'] === 'sync_file_registry') {
        $folders = [
            'uploads' => 'uploads/',
            'storage' => 'uploads/storage/',
            'submissions' => 'submissions/'
        ];

        foreach ($folders as $key => $dir) {
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $files = array_diff(scandir($dir), array('.', '..'));
            foreach ($files as $file) {
                $path = $dir . $file;
                if (!is_file($path)) continue;
                
                // Skip subdirectories like 'storage/' when scanning 'uploads/'
                if ($key === 'uploads' && is_dir($path)) continue;

                $size = round(filesize($path) / 1024, 2) . ' KB';
                $type = mime_content_type($path);
                
                $stmt = $conn->prepare("INSERT IGNORE INTO system_file_registry (file_name, file_path, file_type, source_folder, file_size, status) VALUES (?, ?, ?, ?, ?, 'present')");
                $stmt->bind_param("sssss", $file, $path, $type, $key, $size);
                $stmt->execute();
                $stmt->close();
            }
        }
        $updated = true;
    }

    // Handle Persistent Deletion (Archive Record + Physical Purge)
    if (isset($_POST['action']) && ($_POST['action'] === 'delete_selected_files' || $_POST['action'] === 'delete_single_physical')) {
        $view = $_POST['view_type'];

        // Ensure target directory exists for mapping
        $target_dir = '';
        if ($view === 'submissions') $target_dir = 'submissions/';
        elseif ($view === 'storage') $target_dir = 'uploads/storage/';
        else $target_dir = 'uploads/';

        if ($_POST['action'] === 'delete_single_physical') {
            $selected_files = [$_POST['file_name']];
        } else {
            $selected_files = $_POST['selected_files'] ?? [];
        }
        
        if (is_dir($target_dir) && !empty($selected_files)) {
            foreach ($selected_files as $file) {
                $file = basename($file); // Security: only allow filename
                $path = $target_dir . $file;
                if (!is_file($path)) continue;

                // PROTECTION LOGIC: Do NOT delete profile pics, covers, videos, or system icons
                $is_protected = preg_match('/^(profile_|teacher_|cover_|admin_|admin_pic_|bg_logo|login_video_|halloween_video_|christmas_video_|summer_video_|new_year_video_|link_icon_|achievement_|st40|St\.Anne_logo)/i', $file);
                $is_core_asset = in_array($file, ['St.Anne_logo.png', 'Adobe Express - file.png', 'communication.png', 'folder.png', 'network-access.png', 'anxiety.png', 'block-user.png', 'ST40.png']);
                
                if ($view === 'uploads' && ($is_protected || $is_core_asset)) {
                    continue;
                }

                @unlink($path);
                
                // Update DB: Change status to 'purged' but DO NOT DELETE the row
                $stmt = $conn->prepare("UPDATE system_file_registry SET status = 'purged' WHERE file_path = ?");
                $stmt->bind_param("s", $path);
                $stmt->execute();
            }
            $updated = true;
        }
    }

    if ($updated) {
        $act = $_POST['action'] ?? '';
        
        // Only logout if Admin changed THEIR OWN password
        if ($act === 'change_admin_pass' && isset($_POST['admin_id']) && $_POST['admin_id'] == $_SESSION['admin_id']) {
            header("Location: SACLICONNECT2.php?pass_changed=1");
            exit();
        } 

        $redirect = "SACLICONNECT2.php?updated=1";
        
        if ($act === 'change_student_id' || $act === 'change_admin_pass' || $act === 'change_teacher_pass' || $act === 'clear_pass_request') {
            $redirect .= "&page=passwords";
        } elseif(strpos($act, 'alumni') !== false){
            $redirect .= "&page=alumni";
        } elseif(strpos($act, 'student') !== false){
            $redirect .= "&page=students";
        } elseif(strpos($act, 'teacher') !== false){
            $redirect .= "&page=teachers";
        } elseif(strpos($act, 'restrict') !== false && isset($_POST['redirect_page'])){
            $redirect .= "&page=" . $_POST['redirect_page'];
        } elseif($act === 'deactivate_user_indefinitely'){
            $redirect .= "&page=deactivated"; 
        } elseif($act === 'reactivate_user'){
            $redirect .= "&page=deactivated";
        } elseif(strpos($act, 'event') !== false){
            $redirect .= "&page=calendar";
        } elseif(strpos($act, 'subjects') !== false){
            $redirect .= "&page=access_links";
        } elseif(strpos($act, 'login_video') !== false || strpos($act, 'site_theme') !== false || strpos($act, 'bg_logo') !== false){
            $redirect .= "&page=login_video_settings";
        } elseif(strpos($act, 'admin_delete') !== false){
            $redirect .= "&page=chats";
        } elseif(strpos($act, 'reply_concern') !== false){
            $redirect .= "&page=concerns&view_concern=" . urlencode($_POST['student_id']);
        } elseif(strpos($act, 'achievement') !== false){
            $redirect .= "&page=achievements_manage";
        } elseif(strpos($act, 'eval_question') !== false || $act === 'toggle_eval_lock'){
            $redirect .= "&page=eval_questions";
        } elseif($act === 'reset_all_evaluations'){
            $redirect = "SACLICONNECT2.php?page=evaluation_results&reset_success=1";
        } elseif($act === 'toggle_signup'){
            $redirect .= "&page=signup_manage";
        }
        
        header("Location: " . $redirect);
        exit();
    }
}

// Remove 'Passwords' menu item from Student Sidebar if it exists (Cleanup)
$conn->query("DELETE FROM sidebar_menu WHERE label='Passwords'");

$sidebarItems = [];
$res = $conn->query("SELECT * FROM sidebar_menu ORDER BY sort_order, id");
if ($res) while ($row = $res->fetch_assoc()) $sidebarItems[] = $row;
if (empty($sidebarItems)) {
    $conn->query("INSERT IGNORE INTO sidebar_menu (label, icon, sort_order) VALUES
        ('Dashboard', '1icons8-dashboard-50.png', 1),
        ('Announcements', '2icons8-announcement-50.png', 2),
        ('Students', 'assets/images/3icons8-student-64.png', 3),
        ('Teachers', '4icons8-teacher-50.png', 4),
        ('Subjects', '', 5),
        ('Achievements', '5icons8-assignment-50.png', 6),
        ('Calendar', '6icons8-calendar-50.png', 7),
        ('Organizations', '7icons8-organization-64.png', 8),
        ('Settings', '8icons8-setting-50.png', 9)");
    $res = $conn->query("SELECT * FROM sidebar_menu ORDER BY sort_order, id");
    while ($row = $res->fetch_assoc()) $sidebarItems[] = $row;
}

$subjectChats = [];
$res = $conn->query("SELECT * FROM subject_chats ORDER BY sort_order, id");
if ($res) while ($row = $res->fetch_assoc()) $subjectChats[] = $row;
if (empty($subjectChats)) {
    $conn->query("INSERT INTO subject_chats (name, url, icon, is_online, sort_order) VALUES
        ('Sacli Portal', 'https://sacli.edu.ph/', 'St.Anne_logo.png', 1, 1),
        ('Sacli Facebook Page', 'https://www.facebook.com/St.AnneCollegeLucenaIncOfficial', 'child-4.svg', 1, 2),
        ('Sacli Youtube page', 'https://www.youtube.com/results?search_query=st+anne+college+lucena+inc', 'youtube.png', 1, 3),
        ('Sacli Instagram page', 'https://www.instagram.com/explore/locations/258657334/st-anne-college-lucena-inc/', 'instagram.png', 1, 4)");
    $res = $conn->query("SELECT * FROM subject_chats ORDER BY sort_order, id");
    while ($row = $res->fetch_assoc()) $subjectChats[] = $row;
}

$showUpdated = isset($_GET['updated']) && $_GET['updated'] == '1';
$passChanged = isset($_GET['pass_changed']) && $_GET['pass_changed'] == '1';
$page = $_GET['page'] ?? 'main';
?>
<?php if (!isset($_GET['ajax_content'])): ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/1_SacliConnect.css">
    <title>Admin Control — Sacli Connect</title>
    <style>
        /* ================= PREMIUM ADMIN UI STYLES ================= */
        html { background: #05100c; } /* Prevent white flash during load */
        body {
            background: radial-gradient(circle at center, #0f3526 0%, #05100c 100%);
            font-family: 'Segoe UI', sans-serif;
            color: #e4e6eb;
            transition: background 1.5s ease, background-color 1.5s ease, filter 0.5s ease;
            overflow-x: hidden;
            <?php if(isset($_GET['intro']) && $_GET['intro'] == '1'): ?>
            animation: adminEntrance 1.5s cubic-bezier(0.19, 1, 0.22, 1) forwards;
            <?php endif; ?>
        }

        @keyframes adminEntrance {
            0% { opacity: 0; transform: scale(1.05); filter: blur(10px); }
            100% { opacity: 1; transform: scale(1); filter: blur(0); }
        }

        /* Smooth blur effect for background transition */
        body.theme-changing { filter: blur(10px) brightness(0.6); }

        .admin-error {
            background: radial-gradient(circle at center, #0f3526 0%, #05100c 100%);
            font-family: 'Segoe UI', sans-serif;
            color: #e4e6eb;
            overflow-x: hidden;
        }

        /* --- Sidebar Enhancements --- */
        .sidebar {
            width: 260px !important;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
           
            background: rgba(10, 31, 22, 0.95) !important;
            backdrop-filter: blur(15px);
            border-right: 1px solid rgba(0, 255, 170, 0.15);
            box-shadow: 5px 0 20px rgba(0,0,0,0.3);
            z-index: 100;
            padding-top: 20px;
            display: flex;
            flex-direction: column;
        }

        .sidebar ul {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: none;
            padding: 0;
            margin: 0;
        }
        .sidebar ul::-webkit-scrollbar { display: none; }
        
        .sidebar h2 {
            color: #fff;
            text-shadow: 0 0 10px rgba(0, 255, 170, 0.5);
            letter-spacing: 2px;
            margin-bottom: 30px;
            text-align: center;
            font-size: 20px;
        }
        
        .sidebar ul li {
            padding: 0 !important;
            margin: 0 !important;
        }

        .sidebar ul li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            margin: 8px 15px;
            border-radius: 12px;
            color: #b0fce0;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            border: 1px solid transparent;
        }

        .sidebar ul li a:hover, .sidebar ul li a.admin-active {
            background: linear-gradient(90deg, rgba(0, 255, 170, 0.15), transparent);
            color: #00ffaa;
            border-left: 4px solid #00ffaa;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transform: translateX(5px);
        }
        
        .sidebar ul li a img {
            width: 24px;
            height: 24px;
            margin-right: 15px;
            filter: grayscale(100%) brightness(1.5);
            transition: 0.3s;
        }
        
        .sidebar ul li a:hover img, .sidebar ul li a.admin-active img {
            filter: none;
            transform: scale(1.1);
        }

        /* --- Main Content Area --- */
        .main {
            margin-left: 260px; /* Sidebar width */
            margin-right: 260px; /* Right sidebar width */
            padding: 40px;
            min-height: 100vh;
            max-width: none; /* Override external CSS max-width to fill space */
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            overflow-x: hidden;
        }
        
        .main > * {
            width: 100%;
            max-width: 1200px;
        }

        /* --- Admin Section Cards --- */
        .admin-section {
            background: rgba(20, 50, 40, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 255, 170, 0.2);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            position: relative;
            overflow: visible;
            min-height: auto;
            
            transition: transform 0.3s ease;
        }
        
        .admin-section:hover {
            transform: translateY(-2px);
            border-color: rgba(0, 255, 170, 0.4);
        }
        
        .admin-section::before {
            content: '';
            position: absolute;
            top: 0; left: 10px; width: 100%; height: 0px;
            background: linear-gradient(90deg, #00ffaa, transparent);
            opacity: 0.7;
        }

        .admin-section h3 {
            font-size: 1.4rem;
            color: #fff;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }

        /* --- Form Elements --- */
        input[type="text"], input[type="password"], input[type="email"], input[type="date"], select, textarea {
            background: rgba(0, 0, 0, 0.3) !important;
            border: 1px solid rgba(0, 255, 170, 0.3) !important;
            color: #fff !important;
            border-radius: 10px !important;
            padding: 12px 15px !important;
            font-size: 14px !important;
            transition: 0.3s !important;
            width: 100%;
            box-sizing: border-box;
        }
        
        .admin-form-row > input, .admin-form-row > select {
            flex: 1;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #00ffaa !important;
            box-shadow: 0 0 15px rgba(0, 255, 170, 0.2) !important;
            background: rgba(0, 0, 0, 0.5) !important;
            outline: none;
        }

        .admin-form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* --- PIN Code Styles --- */
        .pin-wrapper { display: flex; justify-content: center; align-items: center; height: 400px; background: rgba(0,0,0,0.2); border-radius: 15px; }
        .pin-box { text-align: center; width: 100%; height: 100%; padding: 40px; background: #102e22; border: 1px solid #00ffaa; border-radius: 20px; box-shadow: 0 0 30px rgba(0, 255, 170, 0.15); animation: popIn 0.5s ease; }
        .lock-icon { font-size: 50px; margin-bottom: 10px; }
        .pin-box h2 { color: #00ffaa; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 2px; }
        .pin-box p { color: #b0fce0; margin-bottom: 25px; font-size: 14px; }
        .pin-input { 
            width: 200px; padding: 15px; font-size: 24px; text-align: center; letter-spacing: 8px; 
            background: #071b12; border: 2px solid rgba(0, 255, 170, 0.3); color: #00ffaa; 
            border-radius: 10px; outline: none; transition: 0.3s; margin-bottom: 20px;
        }
        .pin-input:focus { border-color: #00ffaa; box-shadow: 0 0 15px rgba(0, 255, 170, 0.3); }
        .pin-btn { width: 100%; padding: 12px; background: linear-gradient(45deg, #00ffaa, #00cc88); color: #0a1f16; font-weight: bold; border: none; border-radius: 10px; cursor: pointer; font-size: 16px; letter-spacing: 1px; transition: 0.3s; }
        .pin-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 255, 170, 0.4); }
        .pin-error { color: #ff5555; background: rgba(255, 85, 85, 0.1); padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px; border: 1px solid rgba(255, 85, 85, 0.3); }

        /* --- Buttons --- */
        .admin-btn {
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 12px;
            transition: 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .admin-btn-add, .admin-btn-save {
            background: linear-gradient(45deg, #00ffaa, #00cc88);
            color: #0a1f16;
            box-shadow: 0 5px 15px rgba(0, 255, 170, 0.3);
        }
        
        .admin-btn-add:hover, .admin-btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 255, 170, 0.5);
            filter: brightness(1.1);
        }
        
        .admin-btn-delete {
            background: rgba(255, 85, 85, 0.15);
            color: #ff5555;
            border: 1px solid rgba(255, 85, 85, 0.3);
        }
        
        .admin-btn-delete:hover {
            background: #ff5555;
            color: #fff;
            box-shadow: 0 0 15px rgba(255, 85, 85, 0.4);
        }

        /* --- Tables --- */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 6px;
        }
        
        th {
            text-align: left;
            padding: 15px;
            color: #00ffaa;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.8;
        }
        
        td {
            background: rgba(255, 255, 255, 0.03);
            padding: 15px;
            vertical-align: middle;
        }
        
        tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        
        tr:hover td {
            background: rgba(255, 255, 255, 0.08);
            transform: scale(1.005);
            transition: 0.2s;
        }

        /* --- Right Sidebar --- */
        .right-sidebar {
            width: 260px !important;
            position: fixed;
            top: 0;
            right: 0;
            height: 100vh;
            overflow-y: auto;
            background: rgba(10, 31, 22, 0.95) !important;
            backdrop-filter: blur(15px);
            border-left: 1px solid rgba(0, 255, 170, 0.15);
            padding: 25px;
            box-shadow: -5px 0 20px rgba(0,0,0,0.3);
        }
        
        .right-sidebar h3 {
            color: #00ffaa;
            font-size: 16px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* --- Scrollbar --- */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #05100c; }
        ::-webkit-scrollbar-thumb { background: #1a3d2f; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #00ffaa; }

        /* --- Modals --- */
        .modal-content {
            background: #102e22;
            border: 1px solid #00ffaa;
            border-radius: 20px;
            box-shadow: 0 0 50px rgba(0, 255, 170, 0.2);
            padding: 30px;
        }

        .admin-list-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            gap: 15px;
        }

        /* --- REPLICATED FROM SACLICONNECT.PHP DASHBOARD UI --- */
        .story-stats-container {
            display: flex; gap: 15px; justify-content: center; width: 100%;
            max-width: 100% !important; margin-bottom: 25px;
        }
        .story-stat-card {
            flex: 1; min-height: 120px; border-radius: 15px;
            background: linear-gradient(135deg, #1a3d2f, #102e22);
            border: 1px solid rgba(0, 255, 170, 0.2); position: relative;
            overflow: hidden; display: flex; flex-direction: column;
            justify-content: center; align-items: center; padding: 15px;
            text-align: center; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); cursor: pointer;
        }
        .story-stat-card:hover { transform: translateY(-5px) scale(1.02); border-color: #00ffaa; box-shadow: 0 10px 20px rgba(0, 255, 170, 0.2); }
        .story-stat-count { color: #fff; font-size: 32px; font-weight: 800; line-height: 1; z-index: 2; }
        .story-stat-label { color: #00ffaa; font-size: 12px; font-weight: 600; margin-top: 5px; z-index: 2; text-transform: uppercase; }
        .story-stat-card::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background-image: conic-gradient(from 180deg at 50% 50%, rgba(0, 255, 170, 0.2) 0deg, transparent 60deg, transparent 360deg);
            animation: rotateGlow 5s linear infinite; z-index: 1;
        }
        @keyframes rotateGlow { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        .upcoming-event-card {
            width: 100%; max-width: 100% !important; background: linear-gradient(135deg, #004d33, #102e22);
            border: 1px solid #00ffaa; border-radius: 15px; padding: 20px; margin-bottom: 30px;
            color: #fff; text-align: center; position: relative; overflow: hidden;
        }
        .upcoming-event-card h3 { margin: 0 0 5px 0; font-size: 12px; text-transform: uppercase; color: #00ffaa; border-bottom: none; letter-spacing: 2px; }
        .upcoming-event-card h2 { margin: 0 0 5px 0; font-size: 22px; font-weight: 800; }
        .upcoming-event-card p { color: #b0fce0; font-size: 14px; margin: 0; }

        .post { background: rgba(26, 61, 47, 0.4); padding: 20px; border-radius: 15px; border: 1px solid rgba(0, 255, 170, 0.15); color: #e4e6eb; box-shadow: 0 4px 15px rgba(0,0,0,0.3); margin-bottom: 25px; transition: 0.3s; }
        .post:hover { border-color: rgba(0,255,170,0.4); box-shadow: 0 8px 25px rgba(0,0,0,0.4); }
        .post-profile-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 2px solid #00ffaa; }
        
        .media-grid { display: grid; gap: 4px; width: 100%; border-radius: 10px; overflow: hidden; margin: 15px 0; background: #000; }
        .media-item { position: relative; aspect-ratio: 16/9; overflow: hidden; }
        .media-item img, .media-item video { width: 100%; height: 100%; object-fit: cover; }
        
        .post-actions { display: flex; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 15px; padding-top: 10px; gap: 10px; }
        .action-stat { flex: 1; text-align: center; font-size: 12px; color: #aaa; font-weight: 600; padding: 5px; background: rgba(0,0,0,0.2); border-radius: 5px; }
        .action-stat b { color: #00ffaa; }

        .comment-section { margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px; }
        .comment-item { display: flex; gap: 10px; margin-bottom: 10px; font-size: 13px; }
        .comment-avatar { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid #00ffaa; }
        .comment-bubble { background: rgba(255,255,255,0.05); padding: 8px 12px; border-radius: 12px; flex: 1; }
        .comment-bubble strong { color: #00ffaa; display: block; font-size: 11px; margin-bottom: 2px; }
        
        .section-title-hud {
            color: #00ffaa; font-family: 'Courier New', monospace; font-size: 14px; font-weight: bold;
            margin-bottom: 15px; display: flex; align-items: center; gap: 10px;
            text-transform: uppercase; letter-spacing: 2px;
        }
        .section-title-hud::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, rgba(0,255,170,0.3), transparent); }

        .admin-feed-container {
            width: 100%; max-height: 800px; overflow-y: auto; padding-right: 10px;
        }
        .admin-feed-container::-webkit-scrollbar { width: 5px; }
        .admin-feed-container::-webkit-scrollbar-thumb { background: rgba(0,255,170,0.2); border-radius: 10px; }

        /* Halloween (Stranger Things / Upside Down) Theme */
        body.theme-halloween { background: #050202 !important; position: relative; overflow-x: hidden; }
        body.theme-halloween::before { content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, rgba(0,0,0,0.6), rgba(20,0,0,0.9)), repeating-linear-gradient(45deg, rgba(20,0,0,0.1) 0px, rgba(20,0,0,0.1) 2px, transparent 2px, transparent 10px), radial-gradient(circle at 50% 50%, rgba(40,0,0,0.2), transparent 80%); background-size: cover; z-index: -1; pointer-events: none; }
        body.theme-halloween::after { content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at 50% 0%, rgba(255, 0, 0, 0.1), transparent 70%); opacity: 0.6; animation: lightningFlash 10s infinite; pointer-events: none; z-index: -1; mix-blend-mode: overlay; }
        @keyframes lightningFlash { 0%, 85% { opacity: 0.3; background-color: transparent; } 86% { opacity: 0.8; background-color: rgba(255, 0, 0, 0.15); } 87% { opacity: 0.3; background-color: transparent; } 92% { opacity: 0.3; background-color: transparent; } 93% { opacity: 1; background-color: rgba(255, 50, 50, 0.2); } 94% { opacity: 0.3; background-color: transparent; } 100% { opacity: 0.3; } }
        
        /* Ash Particle */
        .ash { position: fixed; background: rgba(255, 255, 255, 0.7); border-radius: 50%; pointer-events: none; z-index: 9999; box-shadow: 0 0 5px rgba(255,255,255,0.5); animation: fallAsh linear forwards; }
        @keyframes fallAsh { 0% { transform: translateY(-10vh) translateX(0) rotate(0deg); opacity: 0; } 10% { opacity: 0.8; } 100% { transform: translateY(110vh) translateX(20px) rotate(180deg); opacity: 0; } }

        /* Halloween Admin UI Overrides */
        body.theme-halloween .sidebar, body.theme-halloween .right-sidebar { background: rgba(10, 5, 5, 0.95) !important; border-color: #800000 !important; }
        body.theme-halloween .admin-section { background: rgba(20, 5, 5, 0.6) !important; border-color: #800000 !important; box-shadow: 0 0 20px rgba(128, 0, 0, 0.2) !important; }
        body.theme-halloween h2, body.theme-halloween h3, body.theme-halloween .sidebar h2, body.theme-halloween .sidebar ul li a:hover, body.theme-halloween .sidebar ul li a.admin-active { color: #ff5555 !important; text-shadow: 0 0 10px #800000 !important; }
        body.theme-halloween .sidebar ul li a.admin-active { border-left-color: #ff5555 !important; background: linear-gradient(90deg, rgba(255, 85, 85, 0.15), transparent) !important; }
        body.theme-halloween .admin-btn-add, body.theme-halloween .admin-btn-save { background: linear-gradient(45deg, #800000, #ff0000) !important; color: #fff !important; box-shadow: 0 5px 15px rgba(255, 0, 0, 0.3) !important; }
        body.theme-halloween input, body.theme-halloween select, body.theme-halloween textarea { border-color: #800000 !important; }
        body.theme-halloween input:focus { border-color: #ff0000 !important; box-shadow: 0 0 15px rgba(255, 0, 0, 0.2) !important; }

        /* Christmas Theme */

        body.theme-christmas { background: linear-gradient(to bottom, #0f2027, #203a43, #2c5364) !important; position: relative; overflow-x: hidden; }
        body.theme-christmas::before { content: ''; position: fixed; top: -50px; left: 0; width: 10px; height: 10px; border-radius: 50%; background: transparent; box-shadow: 5vw 10vh 2px 2px #fff, 15vw 25vh 1px 3px #fff, 25vw 5vh 3px 2px #fff, 35vw 15vh 1px 1px #fff, 45vw 10vh 2px 2px #fff, 55vw 25vh 1px 3px #fff, 65vw 5vh 3px 2px #fff, 75vw 15vh 1px 1px #fff, 85vw 10vh 2px 2px #fff, 95vw 25vh 1px 3px #fff, 10vw 40vh 2px 2px #fff, 30vw 60vh 1px 3px #fff, 50vw 50vh 3px 2px #fff, 70vw 70vh 1px 1px #fff, 90vw 80vh 2px 2px #fff; opacity: 0.8; pointer-events: none; animation: snow 10s linear infinite; z-index: -1; }
        body.theme-christmas::after { content: ''; display: none; }
        @keyframes snow { 0% { transform: translateY(-10vh); } 100% { transform: translateY(110vh); } }

        /* Christmas Admin UI Overrides */
        body.theme-christmas .sidebar, body.theme-christmas .right-sidebar { background: rgba(10, 30, 20, 0.95) !important; border-color: #fff !important; }
        body.theme-christmas .admin-section { background: rgba(10, 40, 30, 0.6) !important; border-color: #fff !important; box-shadow: 0 0 20px rgba(255, 255, 255, 0.2) !important; }
        body.theme-christmas h2, body.theme-christmas h3, body.theme-christmas .sidebar h2, body.theme-christmas .sidebar ul li a:hover, body.theme-christmas .sidebar ul li a.admin-active { color: #00ffaa !important; text-shadow: 0 0 5px #fff !important; }
        body.theme-christmas .sidebar ul li a.admin-active { border-left-color: #ff0000 !important; background: linear-gradient(90deg, rgba(255, 0, 0, 0.15), transparent) !important; }
        body.theme-christmas .admin-btn-add, body.theme-christmas .admin-btn-save { background: linear-gradient(45deg, #ff0000, #008000) !important; color: #fff !important; box-shadow: 0 5px 15px rgba(255, 255, 255, 0.3) !important; }
        body.theme-christmas input, body.theme-christmas select, body.theme-christmas textarea { border-color: #fff !important; }
        body.theme-christmas input:focus { border-color: #00ff00 !important; box-shadow: 0 0 15px rgba(255, 255, 255, 0.5) !important; }

        /* New Year Theme */
        body.theme-new_year { background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%) !important; overflow-x: hidden; }
        /* Firework Particle */
        .firework-particle { position: fixed; width: 4px; height: 4px; border-radius: 50%; pointer-events: none; animation: explode 1.5s ease-out forwards; z-index: 9999; }
        @keyframes explode { 0% { transform: translate(0, 0) scale(1); opacity: 1; } 50% { opacity: 1; } 100% { transform: translate(var(--tx), var(--ty)) scale(0); opacity: 0; } }

        /* New Year Admin UI Overrides */
        body.theme-new_year .sidebar, body.theme-new_year .right-sidebar { background: rgba(10, 10, 10, 0.95) !important; border-color: #ffd700 !important; }
        body.theme-new_year .admin-section { background: rgba(20, 20, 20, 0.6) !important; border-color: #ffd700 !important; box-shadow: 0 0 20px rgba(255, 215, 0, 0.2) !important; }
        body.theme-new_year h2, body.theme-new_year h3, body.theme-new_year .sidebar h2, body.theme-new_year .sidebar ul li a:hover, body.theme-new_year .sidebar ul li a.admin-active { color: #ffd700 !important; text-shadow: 0 0 10px #fff !important; }
        body.theme-new_year .sidebar ul li a.admin-active { border-left-color: #ffd700 !important; background: linear-gradient(90deg, rgba(255, 215, 0, 0.15), transparent) !important; }
        body.theme-new_year .admin-btn-add, body.theme-new_year .admin-btn-save { background: linear-gradient(45deg, #ffd700, #b8860b) !important; color: #000 !important; box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3) !important; }
        body.theme-new_year input, body.theme-new_year select, body.theme-new_year textarea { border-color: #ffd700 !important; color: #ffd700 !important; }
        body.theme-new_year input:focus { border-color: #fff !important; box-shadow: 0 0 15px rgba(255, 215, 0, 0.5) !important; }

        /* Summer Theme */
        body.theme-summer { background: #2980b9 !important; overflow-x: hidden; }
        body.theme-summer::before { content:''; position:fixed; top: -100px; right: -100px; width:600px; height:600px; background:radial-gradient(circle, #ffcc00 0%, transparent 60%); opacity:0.6; animation:pulseGlow 4s infinite; z-index: -1; pointer-events: none; }

        /* Summer Admin UI Overrides */
        body.theme-summer .sidebar, body.theme-summer .right-sidebar { background: rgba(41, 128, 185, 0.95) !important; border-color: #ffcc00 !important; }
        body.theme-summer .admin-section { background: rgba(255, 255, 255, 0.1) !important; border-color: #ffcc00 !important; box-shadow: 0 0 20px rgba(255, 204, 0, 0.3) !important; }
        body.theme-summer h2, body.theme-summer h3, body.theme-summer .sidebar h2, body.theme-summer .sidebar ul li a:hover, body.theme-summer .sidebar ul li a.admin-active { color: #ffcc00 !important; text-shadow: 0 0 5px #000 !important; }
        body.theme-summer .sidebar ul li a.admin-active { border-left-color: #ffcc00 !important; background: linear-gradient(90deg, rgba(255, 204, 0, 0.2), transparent) !important; }
        body.theme-summer .admin-btn-add, body.theme-summer .admin-btn-save { background: linear-gradient(45deg, #ffcc00, #ff9900) !important; color: #000 !important; box-shadow: 0 5px 15px rgba(255, 204, 0, 0.3) !important; }
        body.theme-summer input, body.theme-summer select, body.theme-summer textarea { border-color: #ffcc00 !important; background: rgba(0,0,0,0.2) !important; }
        body.theme-summer input:focus { border-color: #fff !important; box-shadow: 0 0 15px rgba(255, 204, 0, 0.5) !important; }

        /* Mobile Responsiveness */
        @media (max-width: 900px) {
            .sidebar {
                display: none; /* Hidden by default */
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 280px !important;
                z-index: 2000; /* Top of everything */
                box-shadow: 5px 0 30px rgba(0,0,0,0.8);
            }
            .sidebar.active {
                display: flex; /* Show when toggled */
            }
            .right-sidebar {
                display: none !important; /* Hide right sidebar on mobile */
            }
            .main {
                margin-left: 0;
                margin-right: 0;
                padding: 15px;
                width: 100%;
            }
            .modal-content {
                width: 95%;
                max-width: 95%;
                padding: 20px;
            }
            .proto-interface {
                grid-template-columns: 1fr;
                grid-template-rows: auto;
            }
            .map-area, .metrics-area, .log-area {
                grid-column: 1 / -1;
            }
            .map-area { height: 300px; }
            
            /* Mobile Header Styles */
            .mobile-admin-header {
                display: flex !important;
            }
            
            /* Form adjustments for mobile */
            .admin-form-row {
                flex-direction: column;
                align-items: stretch !important;
            }
            .admin-form-row > input, .admin-form-row > select, .admin-form-row > div {
                width: 100% !important;
            }
            
            /* Table adjustments */
            table { font-size: 12px; }
            th, td { padding: 10px 5px !important; }
        }
        
        .mobile-admin-header {
            display: none; /* Hidden on desktop */
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            background: rgba(0,0,0,0.2);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid rgba(0, 255, 170, 0.2);
        }
        .mobile-menu-toggle {
            font-size: 24px;
            color: #00ffaa;
            cursor: pointer;
            padding: 5px;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1500;
            backdrop-filter: blur(3px);
        }
        .change-link-modern {
            display: inline-block;
            font-size: 11px;
            color: #00ffaa;
            text-decoration: none;
            font-weight: bold;
            margin-top: 5px;
            cursor: pointer;
            opacity: 0.8;
            transition: 0.3s;
        }

        /* --- Theme Option Animations --- */
        @keyframes neon-glow-loop {
            0%, 100% { box-shadow: 0 0 5px rgba(0, 255, 170, 0.2); border-color: rgba(0, 255, 170, 0.3); }
            50% { box-shadow: 0 0 20px rgba(0, 255, 170, 0.6); border-color: #00ffaa; }
        }
        @keyframes spooky-flicker {
            0%, 100% { box-shadow: 0 0 10px #8000ff, inset 0 0 5px #8000ff; border-color: #8000ff; color: #ff5500; filter: brightness(1) contrast(1.2); }
            50% { box-shadow: 0 0 35px #ff5500, inset 0 0 15px #ff5500; border-color: #ff5500; color: #8000ff; transform: scale(1.02) skewX(-1deg); filter: brightness(1.5) contrast(1.5); }
            25%, 75% { opacity: 0.7; transform: skewX(1deg); }
        }
        @keyframes snowy-pulse {
            0%, 100% { box-shadow: 0 0 10px #fff; border-color: #ff4d4d; background: rgba(255, 0, 0, 0.2); }
            50% { box-shadow: 0 0 30px #fff; border-color: #2e7d32; background: rgba(0, 128, 0, 0.3); }
        }
        @keyframes summer-sun {
            0%, 100% { box-shadow: 0 0 10px #ffcc00; border-color: #ffcc00; background: rgba(255, 204, 0, 0.1); }
            50% { box-shadow: 0 0 40px #ff9900; border-color: #fff; background: rgba(255, 153, 0, 0.3); transform: scale(1.05); }
        }
        @keyframes newyear-sparkle {
            0%, 100% { filter: brightness(1); box-shadow: 0 0 5px #ffd700; border-color: #ffd700; }
            50% { filter: brightness(1.8); box-shadow: 0 0 45px #ffd700, 0 0 10px #fff; border-color: #fff; transform: translateY(-5px); }
        }

        .theme-opt-label {
            display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 15px 20px; 
            border-radius: 12px; border: 2px solid rgba(255,255,255,0.05); transition: all 0.3s ease; 
            background: rgba(0,0,0,0.3); margin-bottom: 8px; position: relative;
        }
        .theme-opt-label span { font-weight: 800; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; color: #eee; transition: all 0.3s ease; }
        .theme-opt-label .live-badge { font-size: 9px; background: #00ffaa; color: #0a1f16; padding: 2px 8px; border-radius: 4px; margin-left: auto; font-weight: 900; box-shadow: 0 0 10px #00ffaa; }
        
        /* --- Static Theme Styling (Always Visible) --- */
        .theme-opt-default { border-color: rgba(0, 255, 170, 0.3); background: rgba(0, 255, 170, 0.05); }
        .theme-opt-default span { color: #00ffaa; }
        .theme-opt-default:hover { animation: neon-glow-loop 1.5s infinite; transform: scale(1.03); background: rgba(0, 255, 170, 0.15); }
        .theme-opt-default:hover span { text-shadow: 0 0 15px #00ffaa, 0 0 5px #fff; color: #fff; }

        .theme-opt-halloween { background: linear-gradient(135deg, #1a0033 0%, #000 100%); border-color: #8000ff; }
        .theme-opt-halloween span { color: #ff5500; font-family: 'Courier New', monospace; letter-spacing: 2px; }
        .theme-opt-halloween:hover { animation: spooky-flicker 1s infinite; transform: scale(1.03); }
        .theme-opt-halloween:hover span { text-shadow: 0 0 20px #ff5500, 0 0 10px #fff; color: #fff; }
        
        .theme-opt-christmas { background: linear-gradient(135deg, #0a2e1f 0%, #4a0404 100%); border-color: #fff; }
        .theme-opt-christmas span { color: #fff; text-shadow: 1px 1px 2px #000; }
        .theme-opt-christmas:hover { animation: snowy-pulse 1.5s infinite; transform: scale(1.03); }
        .theme-opt-christmas:hover span { text-shadow: 0 0 15px #ff0000, 0 0 10px #00ff00; }

        .theme-opt-summer { background: linear-gradient(135deg, #2980b9 0%, #f1c40f 100%); border-color: #ffcc00; }
        .theme-opt-summer span { color: #fff; text-shadow: 1px 1px 5px rgba(0,0,0,0.5); }
        .theme-opt-summer:hover { animation: summer-sun 2s infinite; transform: scale(1.03); }
        .theme-opt-summer:hover span { text-shadow: 0 0 20px #ffcc00, 0 0 10px #fff; }

        .theme-opt-new_year { background: radial-gradient(circle at top right, #333, #000); border-color: #ffd700; }
        .theme-opt-new_year span { color: #ffd700; }
        .theme-opt-new_year:hover { animation: newyear-sparkle 1s infinite; transform: scale(1.03); }
        .theme-opt-new_year:hover span { text-shadow: 0 0 20px #ffd700, 0 0 10px #fff; color: #fff; }
        
        /* Active State Highlighting */
        .active-opt { border-width: 3px !important; border-style: double !important; filter: brightness(1.2); z-index: 5; box-shadow: 0 0 15px rgba(255,255,255,0.1); }
        .active-opt::after { content: 'SELECTED'; position: absolute; right: 10px; top: -8px; background: #fff; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 8px; font-weight: 900; letter-spacing: 1px; }
        .theme-opt-label input[type="radio"] { accent-color: #00ffaa; width: 18px; height: 18px; cursor: pointer; }

        /* Total System Blackout Protocol */
        body.blackout-protocol {
            background: #000 !important;
            overflow: hidden !important;
        }

        /* --- FINAL PRINT OPTIMIZATION --- */
        @media print {
            html, body { 
                height: auto !important; 
                margin: 0 !important; 
                padding: 0 !important; 
                display: block !important; 
                background: #fff !important; 
            }
            /* Hide everything except the report container */
            body > *:not(.main), .sidebar, .right-sidebar, header, .mobile-admin-header, .no-print, .admin-btn { 
                display: none !important; 
            }
            .main { 
                margin: 0 !important; 
                padding: 0 !important; 
                display: block !important; 
                width: 100% !important; 
            }
            .print-report-area { 
                display: block !important;
                position: relative !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 10mm !important; /* Standard print margin */
                background: #fff !important;
                color: #000 !important;
                box-shadow: none !important;
                border: none !important;
            }
            .print-report-area * { visibility: visible !important; color: #000 !important; }
            .print-header { display: block !important; text-align: center; border-bottom: 2px solid #000; margin-bottom: 25px; padding-bottom: 15px; }
            .print-table { width: 100%; border-collapse: collapse; margin: 10px 0; border: 1px solid #000; }
            .print-table th, .print-table td { border: 1px solid #000; padding: 5px 10px; text-align: left; font-size: 9pt !important; }
            .print-table th { background: #eee !important; font-weight: bold; }
            .print-category { background: #f9f9f9 !important; font-weight: bold; font-size: 10pt !important; }
            .grade-display-print { font-size: 60pt !important; font-weight: 900 !important; color: #000 !important; margin: 0; }
            .print-summary-section { border: 1px solid #000 !important; padding: 15px; margin-top: 20px; display: block !important; border-radius: 5px; page-break-inside: avoid; }
            .signature-line { display: block !important; margin-top: 60px; }
        }

        /* --- DOWNLOAD BUTTON STYLE (.botao) --- */
        .botao {
            width: 125px;
            height: 45px;
            border-radius: 20px;
            border: none;
            box-shadow: 1px 1px rgba(107, 221, 215, 0.37);
            padding: 5px 10px;
            background: rgb(47,93,197);
            background: linear-gradient(160deg, rgb(47, 197, 62) 0%, rgb(255, 255, 255) 5%, rgb(235, 236, 237) 11%, rgb(82, 230, 59) 57%, rgb(0, 255, 85) 71%);
            color: #fff;
            font-family: Roboto, sans-serif;
            font-weight: 505;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            filter: drop-shadow(0 0 10px rgba(59, 190, 230, 0.568));
            transition: .5s linear;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .botao .mysvg { display: none; }
        .botao:hover {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            transition: .5s linear;
        }
        .botao:hover .texto { display: none; }
        .botao:hover .mysvg { display: inline; }
        .botao:hover::before {
            content: '';
            position: absolute;
            top: -3px;
            left: -3px;
            width: 100%;
            height: 100%;
            border: 3.5px solid transparent;
            border-top: 3.5px solid #fff;
            border-right: 3.5px solid #fff;
            border-radius: 50%;
            animation: animateC 2s linear infinite;
            padding: 3px;
        }

        @keyframes animateC {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
        
        .blackout-overlay {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: #000; color: #00ffaa; z-index: 99999999;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body class="theme-<?php echo htmlspecialchars($current_theme); ?> <?php echo $blackout_active ? 'blackout-protocol' : ''; ?>">

<?php if ($blackout_active): ?>
    <div class="blackout-overlay">
        <h1 style="font-size: 16px; letter-spacing: 5px; margin-bottom: 30px; text-align: center;">SYSTEM OFFLINE: SECURITY LOCK_ACTIVE</h1>
        <button onclick="window.location.href='admin_logout.php'" style="background: transparent; border: 2px solid #ff4757; color: #ff4757; padding: 12px 30px; cursor: pointer; font-weight: bold; letter-spacing: 2px; font-family: 'Orbitron', sans-serif; transition: 0.3s;" onmouseover="this.style.background='#ff4757'; this.style.color='#fff'" onmouseout="this.style.background='transparent'; this.style.color='#ff4757'">LOGOUT_TERMINAL</button>
    </div>
<?php endif; ?>

<!-- Overlay for Mobile Sidebar -->
<div class="sidebar-overlay" onclick="toggleAdminSidebar()"></div>

<div class="sidebar">
    <div style="text-align: center; margin-bottom: 20px; cursor: pointer;" onclick="openAdminProfileModal()" title="Click to change profile picture">
        <img class="icon" src="<?php echo $admin_pic; ?>" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid #00ffaa; box-shadow: 0 0 15px rgba(0,255,170,0.3);">
        <div style="color: #00ffaa; font-weight: bold; margin-top: 10px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">Admin</div>
    </div>
    <!-- Close button for mobile inside sidebar -->
    <div class="mobile-menu-toggle" onclick="toggleAdminSidebar()" style="position: absolute; top: 10px; right: 10px; display: none; @media(max-width:900px){display:block;}">×</div>
    
    <h2>SACLICONNECT</h2>
    <ul>
        <li><a href="SacliConnect.php" class="sidebar-link"><img class="icon2" src="assets/images/1icons8-dashboard-50.png" alt="">Visit Dashboard (POV)</a></li>
        <li><a href="SACLICONNECT2.php?page=main" onclick="loadAjaxPage('main', event, this)" class="sidebar-link <?php if($page === 'main') echo 'admin-active'; ?>"><img class="icon2" src="Adobe Express - file.png" alt="">User Dashboard</a></li>
        <li><a href="SACLICONNECT2.php?page=main_control" onclick="loadAjaxPage('main_control', event, this)" class="sidebar-link <?php if($page === 'main_control') echo 'admin-active'; ?>"><img class="icon2" src="assets/images/1icons8-dashboard-50.png" alt="">System Topology</a></li>
        <li><a href="SACLICONNECT2.php?page=students" onclick="loadAjaxPage('students', event, this)" class="sidebar-link <?php if($page === 'students') echo 'admin-active'; ?>"><img class="icon2" src="male-student_11285864.png" alt="">Student Management</a></li>
        <li><a href="SACLICONNECT2.php?page=teachers" onclick="loadAjaxPage('teachers', event, this)" class="sidebar-link <?php if($page === 'teachers') echo 'admin-active'; ?>"><img class="icon2" src="school_11033562.png" alt="">Teacher Management</a></li>
        <li><a href="SACLICONNECT2.php?page=alumni" onclick="loadAjaxPage('alumni', event, this)" class="sidebar-link <?php if($page === 'alumni') echo 'admin-active'; ?>"><img class="icon2" src="student.png" alt="">Alumni Management</a></li>
         <li><a href="SACLICONNECT2.php?page=alumni_accounts" onclick="loadAjaxPage('alumni_accounts', event, this)" class="sidebar-link <?php if($page === 'alumni_accounts') echo 'admin-active'; ?>"><img class="icon2" src="student.png" alt="">Alumni Accounts</a></li>
        <li><a href="SACLICONNECT2.php?page=access_links" onclick="loadAjaxPage('access_links', event, this)" class="sidebar-link <?php if($page === 'access_links') echo 'admin-active'; ?>"><img class="icon2" src="network-access.png" alt="">Accesses page Manage</a></li>
        <li><a href="SACLICONNECT2.php?page=login_video_settings" onclick="loadAjaxPage('login_video_settings', event, this)" class="sidebar-link <?php if($page === 'login_video_settings') echo 'admin-active'; ?>"><img class="icon2" src="video-camera.png" onerror="this.src='network-access.png'" alt="">Update LOG IN video</a></li>
        <li><a href="SACLICONNECT2.php?page=achievements_manage" onclick="loadAjaxPage('achievements_manage', event, this)" class="sidebar-link <?php if($page === 'achievements_manage') echo 'admin-active'; ?>"><img class="icon2" src="badge.png" alt="">Achievements</a></li>
        <li><a href="SACLICONNECT2.php?page=eval_questions" onclick="loadAjaxPage('eval_questions', event, this)" class="sidebar-link <?php if($page === 'eval_questions') echo 'admin-active'; ?>"><img class="icon2" src="5icons8-assignment-50.png" alt="">Evaluation Questions</a></li>
        <li><a href="SACLICONNECT2.php?page=calendar" onclick="loadAjaxPage('calendar', event, this)" class="sidebar-link <?php if($page === 'calendar') echo 'admin-active'; ?>"><img class="icon2" src="calendar.png" alt="">Calendar Events</a></li>
        <li><a href="SACLICONNECT2.php?page=passwords" onclick="loadAjaxPage('passwords', event, this)" class="sidebar-link <?php if($page === 'passwords') echo 'admin-active'; ?>"><img class="icon2" src="access-control.png" onerror="this.src='access-control.png'" alt="">Passwords</a></li>
        <li><a href="SACLICONNECT2.php?page=chats" onclick="loadAjaxPage('chats', event, this)" class="sidebar-link <?php if($page === 'chats') echo 'admin-active'; ?>"><img class="icon2" src="communication.png" alt="">Chat Monitoring</a></li>
        <li><a href="SACLICONNECT2.php?page=concerns" onclick="loadAjaxPage('concerns', event, this)" class="sidebar-link <?php if($page === 'concerns') echo 'admin-active'; ?>"><img class="icon2" src="anxiety.png" alt="">Student Concerns</a></li>
        <li><a href="SACLICONNECT2.php?page=signup_manage" onclick="loadAjaxPage('signup_manage', event, this)" class="sidebar-link <?php if($page === 'signup_manage') echo 'admin-active'; ?>"><img class="icon2" src="Adobe Express - file.png" alt="">Signup Management</a></li>
        <li><a href="SACLICONNECT2.php?page=chat_themes" onclick="loadAjaxPage('chat_themes', event, this)" class="sidebar-link <?php if($page === 'chat_themes') echo 'admin-active'; ?>"><img class="icon2" src="communication.png" alt="">Chat Themes Manage</a></li>
        <li><a href="SACLICONNECT2.php?page=uploads" onclick="loadAjaxPage('uploads', event, this)" class="sidebar-link <?php if($page === 'uploads') echo 'admin-active'; ?>"><img class="icon2" src="folder.png" onerror="this.src='network-access.png'" alt="">Uploads</a></li>
        <li><a href="SACLICONNECT2.php?page=deactivated" onclick="loadAjaxPage('deactivated', event, this)" class="sidebar-link <?php if($page === 'deactivated') echo 'admin-active'; ?>"><img class="icon2" src="block-user.png" onerror="this.src='network-access.png'" alt="">Deactivated Accounts</a></li>
    </ul>
</div>

<div class="main">
<?php endif; // End header/sidebar check ?>

    <!-- Mobile Header (Visible only on mobile) -->
    <div class="mobile-admin-header">
        <div class="mobile-menu-toggle" onclick="toggleAdminSidebar()">☰</div>
        <h3 style="margin:0; color:#fff; font-size: 18px;">Admin Panel</h3>
        <div class="mobile-menu-toggle" onclick="window.location.href='admin_logout.php'" title="Logout" style="color:#ff5555;">⏻</div>
    </div>

    <h2 style="color:#00ffaa; margin-bottom:20px; text-align:center;">Admin Control</h2>

    <?php if ($showUpdated): ?>
    <div class="admin-success">✓ Save Saccessfully.</div>
    <?php elseif (isset($_GET['error'])): ?>
    <div class="admin-error" style="background:#ff5555; color:white; padding:15px; border-radius:10px; margin-bottom:20px; font-weight:bold; animation: popInCard 0.5s ease-out; box-shadow: 0 0 20px rgba(255,85,85,0.4);">! <?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php elseif (isset($_GET['reset_success'])): ?>
    <div class="admin-success" style="background:rgba(255,85,85,0.2); color:#ff5555; border:1px solid #ff5555; animation: popInCard 0.5s ease-out; font-weight:bold; text-align:center; box-shadow: 0 0 20px rgba(255,85,85,0.4);">✓ ALL EVALUATION DATA HAS BEEN RESET SUCCESSFULLY!</div>
    <?php elseif ($requestSent): ?>
    <div class="admin-success">✓ Request sent to user for approval.</div>
    <?php elseif ($requestExists): ?>
    <div class="admin-error" style="background: #ffc107; color: black;">! A request for this user is already pending.</div>
    <?php endif; ?>
    
    <?php // Main Admin Control Page
    if ($page === 'main'): ?>

    <!-- Dashboard Stats (SacliConnect Style) -->
    <div class="story-stats-container">
        <div class="story-stat-card" onclick="loadAjaxPage('alumni', null, null)">
            <div class="story-stat-count"><?php echo $alumni_count; ?></div>
            <div class="story-stat-label">Alumni Accounts</div>
        </div>
        <div class="story-stat-card" onclick="loadAjaxPage('students', null, null)">
            <div class="story-stat-count" style="font-size: 28px;"><?php echo $student_count . " | " . $teacher_count; ?></div>
            <div class="story-stat-label">Students | Teachers</div>
        </div>
        <div class="story-stat-card" onclick="loadAjaxPage('students', null, null)">  
            <div class="story-stat-count"><?php echo $total_registered; ?></div>
            <div class="story-stat-label">Total Registered</div>
        </div>
    </div>

    <?php
    $today_for_event = date('Y-m-d');
    $upcoming_event_res = $conn->query("SELECT * FROM calendar_events WHERE event_date >= '$today_for_event' ORDER BY event_date ASC LIMIT 1");
    if ($upcoming_event_res && $upcoming_event_res->num_rows > 0):
        $event = $upcoming_event_res->fetch_assoc();
    ?>
    <div class="upcoming-event-card">
        <h3>Active Event Sequence</h3>
        <h2><?php echo htmlspecialchars($event['title']); ?></h2>
        <p><?php echo date("F d, Y", strtotime($event['event_date'])); ?></p>
        <div style="margin-top:10px; font-size: 11px; opacity:0.8;">// <?php echo htmlspecialchars($event['description']); ?></div>
    </div>
    <?php endif; ?>

    <div class="section-title-hud">Live Feed Monitor</div>

    <div class="admin-feed-container">
        <?php
        $sql = "SELECT p.*, 
                COALESCE(s.profile_pic, t.profile_pic) as profile_pic, 
                COALESCE(s.student_id, CONCAT('T-', t.id)) as poster_id 
                FROM posts p 
                LEFT JOIN students s ON p.student_name = s.student_name
                LEFT JOIN teachers t ON p.student_name = t.name
                ORDER BY p.timestamp DESC LIMIT 15";
        $posts = $conn->query($sql);

        while($post = $posts->fetch_assoc()){
            $post_id = $post['id'];
            $pic = !empty($post['profile_pic']) ? "uploads/".$post['profile_pic'] : "assets/images/3icons8-student-64.png";
            $like_count = $conn->query("SELECT COUNT(*) FROM post_reactions WHERE post_id='$post_id'")->fetch_row()[0];
            $comm_count = $conn->query("SELECT COUNT(*) FROM post_comments WHERE post_id='$post_id'")->fetch_row()[0];
        ?>
        <div class="post" id="post-row-<?php echo $post_id; ?>">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div style="display:flex; align-items:center;">
                    <img src="<?php echo $pic; ?>" class="post-profile-img">
                    <div>
                        <h4 style="margin:0; font-size:15px; color:#fff;"><?php echo htmlspecialchars($post['student_name']); ?></h4>
                        <small style="color:#aaa;"><?php echo date("M d, Y • h:i A", strtotime($post['timestamp'])); ?></small>
                    </div>
                </div>
                <button onclick="deleteUserPost(<?php echo $post_id; ?>)" class="admin-btn admin-btn-delete" style="padding:6px 12px; font-size:11px;">Remove Post</button>
            </div>
            
            <div class="post-content-container">
                <?php
                $post_content = htmlspecialchars($post['content']);
                $threshold = 300;
                if (mb_strlen($post_content) > $threshold) {
                    $truncated = mb_substr($post_content, 0, $threshold);
                    echo '<p class="content-text-truncated" style="margin:15px 0; font-size:14px; line-height:1.6; white-space:pre-wrap;">' . $truncated . '... <span class="see-more-link" onclick="expandPost(this)" style="color: #00ffaa; cursor: pointer; font-weight: bold;">See More...</span></p>';
                    echo '<p class="content-text-full" style="margin:15px 0; font-size:14px; line-height:1.6; white-space:pre-wrap; display: none;">' . $post_content . ' <span class="see-less-link" onclick="collapsePost(this)" style="color: #00ffaa; cursor: pointer; font-weight: bold;">See Less</span></p>';
                } else {
                    echo '<p style="margin:15px 0; font-size:14px; line-height:1.6; white-space:pre-wrap;">'.$post_content.'</p>';
                }
                ?>
            </div>

            <?php
            $media = $conn->query("SELECT * FROM post_media WHERE post_id='$post_id'");
            if($media && $media->num_rows > 0){
                echo '<div class="media-grid grid-1">';
                while($m = $media->fetch_assoc()){
                    echo '<div class="media-item">';
                    if($m['file_type'] == 'video') echo '<video src="'.$m['file_path'].'" controls style="width:100%;"></video>';
                    else echo '<img src="'.$m['file_path'].'" style="width:100%;">';
                    echo '</div>';
                }
                echo '</div>';
            }
            ?>

            <div class="post-actions">
                <div class="action-stat">❤️ <b><?php echo $like_count; ?></b> Reactions</div>
                <div class="action-stat">💬 <b><?php echo $comm_count; ?></b> Comments</div>
                <div class="action-stat">👁 <b><?php echo $post['views'] ?? 0; ?></b> Views</div>
            </div>
        </div>
        <?php } ?>
    </div>

    <!-- Existing UI Management moved to bottom for better organization -->
    <div class="section-title-hud" style="margin-top:50px;">Navigation Controls</div>
    <div class="admin-section">
        <h3>Sidebar Menu Structure</h3>
        <form method="POST" action="SACLICONNECT2.php">
            <input type="hidden" name="action" value="save_sidebar">
            <div id="sidebar-rows">
                <?php foreach ($sidebarItems as $item): ?>
                <div class="admin-form-row">
                    <input type="text" name="sidebar_label[]" value="<?php echo htmlspecialchars($item['label']); ?>" placeholder="Menu label">
                    <input type="text" name="sidebar_icon[]" value="<?php echo htmlspecialchars($item['icon']); ?>" placeholder="Icon filename">
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:15px; display:flex; gap:10px;">
                <button type="button" class="admin-btn admin-btn-add" onclick="addSidebarRow()">+ Add item</button>
                <button type="submit" class="admin-btn admin-btn-save">Save Sidebar</button>
            </div>
        </form>
    </div>

    <?php elseif ($page === 'main_control'): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #00ffaa; padding-bottom: 10px; margin-bottom: 20px; position: relative; z-index: 2;">
            <h3 style="font-family: 'Courier New'; letter-spacing: 4px; color: #fff; margin: 0; text-shadow: 0 0 10px #00ffaa, 0 0 20px #00ffaa; font-size: 36px; font-weight: 900;">// SYSTEM_CORE // SACLI_NETWORK</h3>
            <div style="font-family: 'Courier New'; color: #00ffaa; font-size: 12px; border: 1px solid #00ffaa; padding: 5px 10px; border-radius: 4px; background: rgba(0, 255, 170, 0.1);">SECURE_CONN_ESTABLISHED</div>
        </div>
        
        <div class="proto-interface" style="position: relative; z-index: 2;">
            
            <!-- Network Visualizer (Left Side) -->
            <div class="proto-box map-area">
                <div class="proto-header">LIVE_NETWORK_TOPOLOGY</div>
                <div id="networkMap" style="width: 100%; height: calc(100% - 35px); position: relative; overflow: hidden;">
                    <div class="center-node">ADMIN</div>
                    <!-- Nodes generated by JS -->
                    <!-- Popover for Node Details -->
                    <div id="nodePopover" class="node-popover" style="display:none;"></div>
                </div>
            </div>

            <!-- Metrics (Right Side) -->
            <div class="proto-box metrics-area">
                <div class="proto-header">SYSTEM_METRICS</div>
                <div class="proto-content">
                    <div class="metric-row">
                        <span>STATUS</span>
                        <span style="color:#00ffaa; animation: blink 1s infinite;">ONLINE</span>
                    </div>
                    <div class="metric-row">
                        <span>DB_LATENCY</span>
                        <span id="p-lat">24ms</span>
                    </div>
                    
                    <div class="metric-group">
                        <span>ACTIVE_SESSIONS</span>
                        <div class="progress-bar"><div class="fill" style="width: <?php echo min(100, $conn->query("SELECT COUNT(*) FROM login_history WHERE login_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->fetch_row()[0] * 5); ?>%;"></div></div>
                        <small style="float:right; color:#fff;"><?php echo $conn->query("SELECT COUNT(*) FROM login_history WHERE login_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)")->fetch_row()[0]; ?></small>
                    </div>

                    <div class="metric-group">
                        <span>TOTAL_RECORDS</span>
                        <div class="progress-bar"><div class="fill" style="width: 85%;"></div></div>
                        <small style="float:right; color:#fff;"><?php echo $conn->query("SELECT (SELECT COUNT(*) FROM students) + (SELECT COUNT(*) FROM posts) + (SELECT COUNT(*) FROM direct_messages)")->fetch_row()[0]; ?></small>
                    </div>

                    <div class="metric-group">
                        <span>SERVER_LOAD</span>
                        <div class="progress-bar"><div class="fill" id="cpu-bar" style="width: 12%;"></div></div>
                        <small style="float:right; color:#fff;" id="cpu-text">12%</small>
                    </div>
                </div>
            </div>
            
            <!-- Activity Log (Bottom Full Width) -->
            <div class="proto-box log-area">
                <div class="proto-header">ACTIVITY_STREAM</div>
                <div class="proto-console" id="realActivityLog">
                    <?php
                    // Fetch real recent logins and posts for the log
                    $logs = [];
                    $logins = $conn->query("SELECT student_id, login_time, 'LOGIN' as type FROM login_history ORDER BY login_time DESC LIMIT 5");
                    if($logins) while($l = $logins->fetch_assoc()) $logs[] = $l;
                    
                    $posts = $conn->query("SELECT student_name as student_id, timestamp as login_time, 'POST' as type FROM posts ORDER BY timestamp DESC LIMIT 5");
                    if($posts) while($p = $posts->fetch_assoc()) $logs[] = $p;
                    
                    // Sort by time
                    usort($logs, function($a, $b) { return strtotime($b['login_time']) - strtotime($a['login_time']); });
                    $logs = array_slice($logs, 0, 8); // Keep top 8

                    foreach($logs as $log) {
                        $time = date("H:i:s", strtotime($log['login_time']));
                        $user = htmlspecialchars($log['student_id']);
                        $action = $log['type'] == 'LOGIN' ? "AUTH_SUCCESS" : "DATA_ENTRY";
                        $color = $log['type'] == 'LOGIN' ? "#00ffaa" : "#ff00ff";
                        echo "<div><span style='color:#555;'>[$time]</span> <span style='color:$color;'>$action</span> : $user</div>";
                    }
                    ?>
                    <span class="blink">_</span>
                </div>
            </div>
        </div>

        <style>
            .scan-overlay {
                position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                background: linear-gradient(to bottom, transparent 50%, rgba(0, 255, 170, 0.05) 51%, transparent 51%);
                background-size: 100% 4px;
                pointer-events: none;
                z-index: 1;
            }
            
            .proto-interface { 
                display: grid; 
                grid-template-columns: 2fr 1fr; 
                grid-template-rows: 500px 250px; 
                gap: 25px; 
                font-family: 'Courier New', monospace; 
                color: #00ffaa; 
            }
            
            .proto-box { 
                border: 1px solid rgba(0, 255, 170, 0.3); 
                background: rgba(5, 15, 10, 0.9); 
                position: relative;
                box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(10px);
                border-radius: 4px;
                overflow: hidden;
                transition: box-shadow 0.3s ease, border-color 0.3s ease;
            }
            .proto-box:hover {
                box-shadow: 0 0 40px rgba(0, 255, 170, 0.15);
                border-color: rgba(0, 255, 170, 0.6);
            }
            
            /* Corner accents */
            .proto-box::before { content: ''; position: absolute; top: -1px; left: -1px; width: 10px; height: 10px; border-top: 2px solid #00ffaa; border-left: 2px solid #00ffaa; }
            .proto-box::after { content: ''; position: absolute; bottom: -1px; right: -1px; width: 10px; height: 10px; border-bottom: 2px solid #00ffaa; border-right: 2px solid #00ffaa; }

            .map-area { 
                grid-column: 1 / 2; 
                grid-row: 1 / 2; 
                background: radial-gradient(circle at 50% 50%, #0a2a1f 0%, #000502 100%);
                background-image: 
                    linear-gradient(rgba(0, 255, 170, 0.03) 1px, transparent 1px), 
                    linear-gradient(90deg, rgba(0, 255, 170, 0.03) 1px, transparent 1px);
                background-size: 30px 30px;
                animation: panGrid 20s linear infinite;
            }
            @keyframes panGrid {
                0% { background-position: 0 0; }
                100% { background-position: 30px 30px; }
            }
            .metrics-area { grid-column: 2 / 3; grid-row: 1 / 2; }
            .log-area { grid-column: 1 / 3; grid-row: 2 / 3; }

            .proto-header { 
                background: linear-gradient(90deg, rgba(0, 255, 170, 0.1), transparent); 
                color: #00ffaa; 
                padding: 10px 15px; 
                font-weight: bold; 
                font-size: 14px; 
                border-bottom: 1px solid rgba(0, 255, 170, 0.2); 
                letter-spacing: 2px; 
                display: flex;
                justify-content: space-between;
                align-items: center;
                text-shadow: 0 0 5px rgba(0, 255, 170, 0.5);
            }
            .proto-header::after { content: '●'; color: #00ffaa; animation: blink 2s infinite; }

            .proto-content { padding: 25px; font-size: 13px; line-height: 1.8; }
            .metric-row { display: flex; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding-bottom: 8px; }
            .metric-row span:first-child { color: #888; font-weight: 600; }
            .metric-row span:last-child { color: #fff; font-weight: bold; text-shadow: 0 0 5px rgba(255, 255, 255, 0.3); }
            
            .metric-group { margin-bottom: 20px; }
            .metric-group span { display: block; margin-bottom: 8px; font-size: 11px; color: #aaa; letter-spacing: 1px; }
            .progress-bar { width: 100%; height: 8px; background: #111; border: 1px solid #333; border-radius: 2px; overflow: hidden; position: relative; }
            .progress-bar .fill { 
                height: 100%; background: linear-gradient(90deg, #00ffaa, #00cc88); width: 0%; transition: width 0.5s ease; box-shadow: 0 0 15px rgba(0, 255, 170, 0.6); 
                position: relative;
            }
            .progress-bar .fill::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: linear-gradient(45deg, rgba(0,0,0,0.2) 25%, transparent 25%, transparent 50%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0.2) 75%, transparent 75%, transparent); background-size: 10px 10px; }

            .proto-console { padding: 15px; height: calc(100% - 42px); overflow-y: auto; font-size: 12px; color: #ccc; background: #000; border-top: 1px solid rgba(0, 255, 170, 0.2); }
            .proto-console div { margin-bottom: 6px; border-left: 2px solid transparent; padding-left: 8px; transition: 0.2s; font-family: 'Consolas', monospace; }
            .proto-console div:hover { border-left-color: #00ffaa; background: rgba(0, 255, 170, 0.05); color: #fff; }

            .blink { animation: blinker 1s linear infinite; }
            @keyframes blinker { 50% { opacity: 0; } }
            
            /* Network Map Styles */
            .center-node {
                position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
                width: 100px; height: 100px; 
                background: rgba(0, 30, 20, 0.9);
                border: 2px solid #00ffaa;
                border-radius: 50%; 
                display: flex; flex-direction: column; align-items: center; justify-content: center;
                font-weight: bold; font-size: 10px; z-index: 10;
                box-shadow: 0 0 60px rgba(0, 255, 170, 0.4), inset 0 0 30px rgba(0, 255, 170, 0.2);
                color: #fff;
                text-shadow: 0 0 8px #00ffaa;
                backdrop-filter: blur(5px);
                cursor: pointer; transition: transform 0.3s;
            }
            .center-node::before { content: '💽'; font-size: 28px; margin-bottom: 4px; }
            
            /* Rotating ring around center */
            .center-ring {
                position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
                width: 160px; height: 160px;
                border: 1px dashed rgba(0, 255, 170, 0.3);
                border-radius: 50%;
                animation: rotateRing 20s linear infinite;
                pointer-events: none;
            }
            @keyframes rotateRing { from { transform: translate(-50%, -50%) rotate(0deg); } to { transform: translate(-50%, -50%) rotate(360deg); } }

            .net-node {
                position: absolute; 
                width: 65px; height: 65px; 
                background: rgba(5, 25, 20, 0.9); 
                border: 1px solid;
                border-radius: 50%;
                padding: 5px;
                font-size: 9px;
                color: #fff;
                box-shadow: 0 0 20px rgba(0, 255, 170, 0.15); 
                z-index: 5; 
                transform: translate(-50%, -50%);
                text-align: center;
                white-space: nowrap;
                transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                backdrop-filter: blur(2px);
                cursor: pointer;
            }
            .net-node:hover {
                background: #00ffaa;
                color: #000;
                box-shadow: 0 0 35px #00ffaa;
                z-index: 20;
                transform: translate(-50%, -50%) scale(1.1);
            }
            .net-node.node-flash {
                background: #fff !important;
                color: #000 !important;
                box-shadow: 0 0 40px #fff !important;
                border-color: #fff !important;
                transform: translate(-50%, -50%) scale(1.3);
                z-index: 50;
            }
            .net-node .icon { display: block; font-size: 20px; margin-bottom: 3px; }

            /* --- NEW STYLES FOR EDIT STUDENT MODAL --- */
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
            textarea.form-input { resize: vertical; min-height: 100px; }

            /* Toggle Switch Style */
            .switch {
                position: relative;
                display: inline-block;
                width: 40px;
                height: 20px;
            }
            .switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #333;
                transition: .4s;
                border-radius: 34px;
            }
            .slider:before {
                position: absolute;
                content: "";
                height: 14px;
                width: 14px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
            }
            input:checked + .slider {
                background-color: #00ffaa;
            }
            input:checked + .slider:before {
                transform: translateX(20px);
            }
            
            /* Animations for modal elements */
            @keyframes modalFadeIn {
                from { opacity: 0; transform: translateY(-20px) scale(0.95); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
            @keyframes elementSlideIn {
                from { opacity: 0; transform: translateX(-20px); }
                to { opacity: 1; transform: translateX(0); }
            }
            .modal-content.student-edit-modal,
            .modal-content.teacher-edit-modal,
            .modal-content.alumni-edit-modal {
                animation: modalFadeIn 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
            }
            .student-edit-modal .form-group, .student-edit-modal .profile-pic-upload-section, .student-edit-modal .privacy-locks-section, .student-edit-modal .admin-btn-save,
            .teacher-edit-modal .form-group, .teacher-edit-modal .profile-pic-upload-section, .teacher-edit-modal .privacy-locks-section, .teacher-edit-modal .admin-btn-save,
            .alumni-edit-modal .form-group, .alumni-edit-modal .profile-pic-upload-section, .alumni-edit-modal .privacy-locks-section, .alumni-edit-modal .admin-btn-save {
                animation: elementSlideIn 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) backwards;
            }
            .student-edit-modal .profile-pic-upload-section { animation-delay: 0.1s; }
            .student-edit-modal .form-group:nth-of-type(1) { animation-delay: 0.2s; }
            .student-edit-modal .form-group:nth-of-type(2) { animation-delay: 0.3s; }
            /* ... add more delays for other elements if needed ... */
            .student-edit-modal .admin-btn-save { animation-delay: 0.8s; }


            .net-line {
                position: absolute; height: 2px; 
                background: linear-gradient(90deg, rgba(0, 255, 170, 0.2), transparent); 
                transform-origin: 0 50%;
                z-index: 1;
            }
            .data-packet {
                /* This class is now only for LIVE traffic */
                position: absolute;
                background: white;
                border-radius: 50%;
                z-index: 100;
                transition: all 1s linear;
                pointer-events: none;
                box-shadow: 0 0 10px currentColor;
            }
            .data-packet::before { /* The "comet" tail */
                content: '';
                position: absolute;
                top: 50%;
                right: 5px; /* Start tail behind the packet head */
                transform: translateY(-50%);
                width: 40px;
                height: 2px;
                border-radius: 2px;
                background: linear-gradient(90deg, currentColor, transparent);
                opacity: 0.8;
            }
            
            /* Legend Styles */
            .map-legend {
                position: absolute;
                bottom: 10px;
                left: 10px;
                background: rgba(0, 20, 10, 0.9);
                border: 1px solid #00ffaa;
                padding: 8px;
                border-radius: 5px;
                font-size: 10px;
                z-index: 10;
                pointer-events: none;
            }
            .legend-item { display: flex; align-items: center; gap: 6px; margin-bottom: 3px; color: #fff; }
            .legend-dot { width: 8px; height: 8px; border-radius: 50%; }

            /* Node Popover */
            .node-popover {
                position: absolute;
                top: 50%; left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(0, 20, 10, 0.95);
                border: 1px solid #00ffaa;
                padding: 15px;
                border-radius: 10px;
                color: #fff; z-index: 200; min-width: 180px; box-shadow: 0 0 30px rgba(0, 255, 170, 0.4); font-size: 11px;
            }
        </style>
        
        <script>
            let networkNodes = {}; // Store node positions
            setInterval(() => {
                document.getElementById('p-lat').innerText = Math.floor(Math.random() * 40 + 10) + "ms";
                let cpu = Math.floor(Math.random() * 30 + 10);
                document.getElementById('cpu-bar').style.width = cpu + "%";
                document.getElementById('cpu-text').innerText = cpu + "%";
            }, 1000);

            // Network Map Animation
            function initNetworkMap() {
                const map = document.getElementById('networkMap');
                if(!map) return;
                map.innerHTML = ''; // Clear all
                networkNodes = {};

                const w = map.offsetWidth;
                const h = map.offsetHeight;
                const cx = w / 2;
                const cy = h / 2;

                // 1. Create Center Node (DB)
                const centerNode = document.createElement('div');
                centerNode.className = 'center-node';
                centerNode.innerHTML = "DATABASE<br>(MySQL)";
                map.appendChild(centerNode);
                centerNode.onclick = () => showNodeDetails('DATABASE');
                networkNodes['DATABASE'] = {x: cx, y: cy};
                
                const ring = document.createElement('div');
                ring.className = 'center-ring';
                map.appendChild(ring);

                // Create Legend
                const legend = document.createElement('div');
                legend.className = 'map-legend';
                legend.innerHTML = `
                    <div class="legend-item"><div class="legend-dot" style="background:#00ccff; box-shadow:0 0 5px #00ccff;"></div> Login</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#ff5555; box-shadow:0 0 5px #ff5555;"></div> Logout / Concern</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#00ffaa; box-shadow:0 0 5px #00ffaa;"></div> Post</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#ff00ff; box-shadow:0 0 5px #ff00ff;"></div> Chat</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#ffd700; box-shadow:0 0 5px #ffd700;"></div> Alumni / GC</div>
                `;
                map.appendChild(legend);

                // Define Nodes
                const nodes = [
                    // Handlers (Inner Layer)
                    { label: 'POST_PROCESSOR', type: 'handler', icon: '⚙️', color: '#ffd700' },
                    { label: 'CHAT_ENGINE', type: 'handler', icon: '⚙️', color: '#ffd700' },
                    { label: 'SUPPORT_SYS', type: 'handler', icon: '⚙️', color: '#ffd700' },
                    { label: 'GROUP_SYNC', type: 'handler', icon: '⚙️', color: '#ffd700' },
                    { label: 'INTERACTION_API', type: 'handler', icon: '⚙️', color: '#ffd700' },
                    
                    // Pages (Outer Layer)
                    { label: 'STUDENT_FEED', type: 'page', icon: '📄', color: '#00ffaa' },
                    { label: 'ADMIN_CONTROL', type: 'page', icon: '🛡️', color: '#ff5555' },
                    { label: 'LOGIN_PORTAL', type: 'page', icon: '🔑', color: '#00ccff' },
                    { label: 'USER_PROFILE', type: 'page', icon: '👤', color: '#00ffaa' },
                    { label: 'ALUMNI_HUB', type: 'page', icon: '🎓', color: '#ffd700' }
                ];

                const handlers = nodes.filter(n => n.type === 'handler');
                const pages = nodes.filter(n => n.type === 'page');

                // Helper to draw line
                function drawLine(x1, y1, x2, y2, color, dashed = false) {
                    const length = Math.sqrt((x2-x1)**2 + (y2-y1)**2);
                    const angle = Math.atan2(y2-y1, x2-x1);
                    const line = document.createElement('div');
                    line.className = 'net-line';
                    line.style.width = length + 'px';
                    line.style.left = x1 + 'px';
                    line.style.top = y1 + 'px';
                    line.style.transform = `rotate(${angle}rad)`;
                    line.style.background = `linear-gradient(90deg, ${color}, transparent)`;
                    if(dashed) {
                        return; // Skip mesh lines for cleaner look
                    }
                    
                    // REMOVED: Ambient packet animation for cleaner look
                    map.appendChild(line);
                }

                // Helper to create node
                function createNode(x, y, data) {
                    const node = document.createElement('div');
                    node.id = 'node-' + data.label;
                    node.className = 'net-node';
                    node.style.left = x + 'px';
                    node.style.top = y + 'px';
                    node.style.borderColor = data.color;
                    node.innerHTML = `<span class="icon">${data.icon}</span><div style="line-height:1; font-size:9px;">${data.label.replace('_', '_<br>')}</div>`;
                    map.appendChild(node);
                    node.onclick = () => showNodeDetails(data.label);
                    networkNodes[data.label] = {x, y};
                    return {x, y};
                }

                // 2. Place Handlers (Inner Circle)
                // FIX: Use percentage of container size to prevent overflow ("lampas")
                const innerRadius = Math.min(w, h) * 0.25; 
                handlers.forEach((hData, i) => {
                    const angle = (i / handlers.length) * Math.PI * 2;
                    const x = cx + Math.cos(angle) * innerRadius;
                    const y = cy + Math.sin(angle) * innerRadius;
                    createNode(x, y, hData);
                    drawLine(cx, cy, x, y, hData.color); // Connect to DB
                    hData.x = x; hData.y = y;
                });

                // 3. Place Pages (Outer Circle)
                // FIX: Use percentage of container size to prevent overflow
                const outerRadius = Math.min(w, h) * 0.40; 
                pages.forEach((pData, i) => {
                    const angle = (i / pages.length) * Math.PI * 2 + (Math.PI / pages.length); // Offset
                    const x = cx + Math.cos(angle) * outerRadius;
                    const y = cy + Math.sin(angle) * outerRadius;
                    createNode(x, y, pData);
                    
                    // Connect Page to Handlers (Mesh)
                    handlers.forEach(hData => {
                        const d = Math.sqrt((x - hData.x)**2 + (y - hData.y)**2);
                    });
                });
            }

            // Function to show node details on click
            function showNodeDetails(label) {
                const popover = document.getElementById('nodePopover');
                popover.style.display = 'block';
                popover.innerHTML = '<div style="text-align:center;">Loading data...</div>';
                
                let formData = new FormData();
                formData.append('action', 'fetch_node_details');
                formData.append('node', label);
                
                fetch('SACLICONNECT2.php', { method: 'POST', body: formData })
                .then(res => res.text())
                .then(html => {
                    popover.innerHTML = html + '<div style="text-align:center; margin-top:10px; cursor:pointer; color:#ff5555;" onclick="this.parentElement.style.display=\'none\'">[CLOSE]</div>';
                });
            }

            // Function to animate a packet between two nodes
            function visualizeTraffic(fromNode, toNode, color = '#fff') {
                const map = document.getElementById('networkMap');
                if(!map || !networkNodes[fromNode] || !networkNodes[toNode]) return;

                const start = networkNodes[fromNode];
                const end = networkNodes[toNode];

                // Calculate angle for the tail
                const angle = Math.atan2(end.y - start.y, end.x - start.x);

                const packet = document.createElement('div');
                packet.className = 'data-packet'; // This class will have the ::before for the tail
                packet.style.background = color;
                packet.style.color = color; // For currentColor usage in CSS
                // packet.style.filter = `drop-shadow(0 0 8px ${color})`;
                packet.style.width = '6px';
                packet.style.height = '6px';
                packet.style.left = start.x + 'px';
                packet.style.top = start.y + 'px';
                packet.style.transform = `translate(-50%, -50%) rotate(${angle}rad)`; // Center and rotate
                packet.style.transition = 'all 1s linear';
                packet.style.zIndex = '100';
                packet.style.position = 'absolute';
                packet.style.borderRadius = '50%';
                
                map.appendChild(packet);

                // Force reflow to ensure animation triggers
                void packet.offsetWidth;

                // Trigger animation
                requestAnimationFrame(() => {
                    packet.style.left = end.x + 'px';
                    packet.style.top = end.y + 'px';
                });

                // Remove after animation
                setTimeout(() => {
                    packet.remove();
                    // Flash effect on destination node
                    const targetEl = document.getElementById('node-' + toNode);
                    if(targetEl) {
                        targetEl.classList.add('node-flash');
                        setTimeout(() => targetEl.classList.remove('node-flash'), 200);
                    }
                }, 1000);
            }

            let processedEventIds = new Set();
            // Polling for live activity
            setInterval(() => {
                let formData = new FormData();
                formData.append('action', 'fetch_network_activity');
                
                fetch('SACLICONNECT2.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(events => {
                    events.forEach(ev => {
                        let uid = ev.type + '_' + ev.student_id + '_' + ev.login_time;
                        if(processedEventIds.has(uid)) return;
                        processedEventIds.add(uid);
                        
                        if(ev.type === 'LOGIN') {
                            visualizeTraffic('LOGIN_PORTAL', 'DATABASE', '#00ccff');
                        } else if(ev.type === 'LOGOUT') {
                            visualizeTraffic('DATABASE', 'LOGIN_PORTAL', '#ff5555');
                        } else if(ev.type === 'POST') {
                            visualizeTraffic('STUDENT_FEED', 'POST_PROCESSOR', '#00ffaa');
                            setTimeout(() => visualizeTraffic('POST_PROCESSOR', 'DATABASE', '#00ffaa'), 800);
                        } else if(ev.type === 'ALUMNI_POST') {
                            visualizeTraffic('ALUMNI_HUB', 'POST_PROCESSOR', '#ff8800');
                            setTimeout(() => visualizeTraffic('POST_PROCESSOR', 'DATABASE', '#ff8800'), 800);
                        } else if(ev.type === 'ALUMNI_SIGNUP') {
                            visualizeTraffic('LOGIN_PORTAL', 'ALUMNI_HUB', '#ffd700');
                            setTimeout(() => visualizeTraffic('ALUMNI_HUB', 'DATABASE', '#ffd700'), 800);
                        } else if(ev.type === 'CHAT') {
                            visualizeTraffic('STUDENT_FEED', 'CHAT_ENGINE', '#ff00ff');
                            setTimeout(() => visualizeTraffic('CHAT_ENGINE', 'DATABASE', '#ff00ff'), 800);
                        } else if(ev.type === 'GROUP_CHAT') {
                            visualizeTraffic('STUDENT_FEED', 'GROUP_SYNC', '#ffff00');
                            setTimeout(() => visualizeTraffic('GROUP_SYNC', 'DATABASE', '#ffff00'), 800);
                        } else if(ev.type === 'CONCERN') {
                            if(ev.sender_type === 'admin') {
                                visualizeTraffic('ADMIN_CONTROL', 'SUPPORT_SYS', '#ff5555');
                            } else {
                                visualizeTraffic('STUDENT_FEED', 'SUPPORT_SYS', '#ff5555');
                            }
                            setTimeout(() => visualizeTraffic('SUPPORT_SYS', 'DATABASE', '#ff5555'), 800);
                        } else if(ev.type === 'COMMENT') {
                            visualizeTraffic('STUDENT_FEED', 'INTERACTION_API', '#00ccff');
                            setTimeout(() => visualizeTraffic('INTERACTION_API', 'DATABASE', '#00ccff'), 800);
                        } else if(ev.type === 'REACTION') {
                            visualizeTraffic('STUDENT_FEED', 'INTERACTION_API', '#ff0055');
                            setTimeout(() => visualizeTraffic('INTERACTION_API', 'DATABASE', '#ff0055'), 800);
                        }
                    });
                    if(processedEventIds.size > 500) processedEventIds.clear();
                })
                .catch(err => {});
            }, 3000);

            window.addEventListener('resize', initNetworkMap);
            setTimeout(initNetworkMap, 100);
        </script>

    <?php // Alumni Management Page
    elseif ($page === 'alumni'): ?>
    <div class="admin-section">
        <h3>Alumni Management (Subjects View)</h3>
        <button class="admin-btn admin-btn-add" onclick="openAddAlumniModal()" style="margin-bottom: 10px;">+ Add Alumni</button>
        
        <div style="margin-bottom: 15px;">
            <input type="text" id="alumniSearch" onkeyup="filterAlumni()" placeholder="Search alumni name, course, or batch..." style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #00ffaa; background: rgba(0,0,0,0.3); color: white; outline: none;">
        </div>

        <div style="max-height: 300px; overflow-y: auto;">
            <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                <?php $alumni = $conn->query("SELECT * FROM alumni ORDER BY batch_year DESC, name ASC"); ?>
                <?php if($alumni): while($a = $alumni->fetch_assoc()): 
                    $pic = !empty($a['profile_pic']) ? "uploads/".$a['profile_pic'] : "assets/images/3icons8-student-64.png";
                ?>
                <tr class="alumni-row" style="border-bottom:1px solid rgba(255,255,255,0.1);">
                    <td style="padding:10px; display:flex; align-items:center; gap:15px;">
                        <img src="<?php echo $pic; ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover; border: 2px solid #00ffaa;">
                        <div>
                            <strong><?php echo htmlspecialchars($a['name']); ?></strong>
                            <small style="color:#00ffaa; margin-left: 10px; font-weight: bold;"><?php echo htmlspecialchars($a['student_id'] ?? 'No ID'); ?></small><br>
                            <small><?php echo htmlspecialchars($a['course']); ?> (Batch <?php echo htmlspecialchars($a['batch_year']); ?>)</small><br>
                            <small style="color:#aaa;">Status: <?php echo htmlspecialchars($a['status'] ?? 'N/A'); ?></small>
                        </div>
                    </td>
                    <td style="padding:10px; text-align:right; vertical-align: middle;">
                        <?php if(!empty($a['student_id'])): ?>
                        <form method="POST" style="margin:0; display:inline-block;" onsubmit="return confirmFormSubmit(event, 'Revert this Alumni back to Student?');">
                            <input type="hidden" name="action" value="revert_to_student">
                            <input type="hidden" name="student_id" value="<?php echo $a['student_id']; ?>">
                            <button type="submit" class="admin-btn" style="background:#00ccff; color:black; margin-right:5px; padding: 6px 12px; font-size: 12px;">To Student</button>
                        </form>
                        <?php endif; ?>
                        <button class="admin-btn" style="background:#e67e22; color:white; margin-right:5px; padding: 6px 12px; font-size: 12px;" onclick='openEditAlumniModal(<?php echo json_encode($a); ?>)'>Edit</button>
                        <form method="POST" style="margin:0; display:inline-block;" onsubmit="return confirmFormSubmit(event, 'Delete this alumni?');">
                            <input type="hidden" name="action" value="delete_alumni">
                            <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                            <button type="submit" class="admin-btn admin-btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </table>
        </div>
    </div>

    <?php // Alumni Accounts Page (Registered Accounts)
    elseif ($page === 'alumni_accounts'): ?>
    <div class="admin-section">
        <h3>Registered Alumni Accounts</h3>
        <p style="color:#aaa; margin-top:-15px; margin-bottom:15px;">List of alumni who have successfully signed up and created an account.</p>
        
        <div style="margin-bottom: 15px;">
            <input type="text" id="alumniAccountSearch" onkeyup="filterAlumniAccounts()" placeholder="Search name, ID, or course..." style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #00ffaa; background: rgba(0,0,0,0.3); color: white; outline: none;">
        </div>

        <div style="max-height: 600px; overflow-y: auto; padding-right: 5px;">
            <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                <tbody>
                    <?php
                    $res = $conn->query("SELECT * FROM students WHERE is_alumni = 1 ORDER BY student_name");
                    if($res && $res->num_rows > 0):
                        while($row = $res->fetch_assoc()):
                            $pic = !empty($row['profile_pic']) ? "uploads/".$row['profile_pic'] : "assets/images/3icons8-student-64.png";
                            $is_restricted = $row['is_restricted'] == 1;
                            $restrict_text = $is_restricted ? "Restricted until " . date("M d, Y", strtotime($row['restriction_end_date'])) : "";
                    ?>
                    <tr class="alumni-account-row" style="border-bottom:1px solid rgba(255,255,255,0.1);">
                        <td style="padding:10px; display:flex; align-items:center; gap:15px;">
                            <img src="<?php echo $pic; ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover; border: 2px solid #00ffaa;">
                            <div>
                                <strong><?php echo htmlspecialchars($row['student_name']); ?></strong>
                                <?php if($is_restricted): ?><br><small style="color:#ff5555;">⚠️ <?php echo $restrict_text; ?></small><?php endif; ?>
                                <small style="color:#00ffaa; margin-left: 10px; font-weight: bold;"><?php echo htmlspecialchars($row['student_id']); ?></small><br>
                                <small><?php echo htmlspecialchars($row['course']); ?></small><br>
                                <small style="color:#aaa;"><?php echo htmlspecialchars($row['email'] ?? ''); ?></small>
                            </div>
                        </td>
                        <td style="padding:10px; text-align:right; vertical-align: middle;">
                            <div style="display:flex; gap:5px; justify-content:flex-end;">
                                <button type="button" class="admin-btn admin-btn-add" style="padding:6px 12px; font-size:14px; background:#00ffaa; color:#0a1f16;" onclick='openViewPostsModal("<?php echo htmlspecialchars($row['student_name'], ENT_QUOTES); ?>")'>View Posts</button>
                                <button type="button" class="admin-btn" style="padding:6px 12px; font-size:14px; background:<?php echo $is_restricted ? '#ff5555' : '#e67e22'; ?>; color:white;" onclick='openRestrictModal("student", "<?php echo $row['student_id']; ?>", "<?php echo htmlspecialchars($row['student_name'], ENT_QUOTES); ?>", "alumni_accounts", <?php echo $is_restricted ? 1 : 0; ?>)'>Restrict</button>
                                <form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Revert this Alumni back to Student Account? They will be removed from Alumni list.', '<?php echo htmlspecialchars($row['student_name'], ENT_QUOTES); ?>', '<?php echo $pic; ?>');">
                                    <input type="hidden" name="action" value="revert_to_student">
                                    <input type="hidden" name="student_id" value="<?php echo $row['student_id']; ?>">
                                    <button type="submit" class="admin-btn" style="background:#00ccff; color:black; padding:6px 12px; font-size:14px;">To Student</button>
                                </form>
                                <form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Delete this alumni account? This will remove their login access.', '<?php echo htmlspecialchars($row['student_name'], ENT_QUOTES); ?>', '<?php echo $pic; ?>');">
                                    <input type="hidden" name="action" value="delete_student">
                                    <input type="hidden" name="student_id" value="<?php echo $row['student_id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-delete">Delete Account</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="2" style="padding:20px; text-align:center; color:#888;">No registered alumni accounts yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php 
    // Achievements Management Page - Separated Editors
    elseif ($page === 'achievements_manage'): ?>

    <div class="admin-section" style="border-top: 4px solid #ffd700;">
        <h3 style="color: #ffd700;">Featured Student of the Month</h3>
        <p style="color:#aaa; margin-top:-15px; margin-bottom:15px;">Only the latest entry will be displayed on the main page.</p>
        <button class="admin-btn admin-btn-add" onclick="openAddAchievementModal('Featured', 'Set Featured Student')">+ Set/Update Featured Student</button>
        
        <table style="width:100%; margin-top:15px; border-collapse:collapse; color:#ccffeb;">
            <thead>
                <tr style="border-bottom:1px solid #ffd700; text-align:left;">
                    <th style="padding:10px;">Student</th>
                    <th style="padding:10px;">Title/Desc</th>
                    <th style="padding:10px; text-align:right;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php $ach = $conn->query("SELECT * FROM achievements WHERE category='Featured' ORDER BY date_posted DESC"); ?>
            <?php if($ach && $ach->num_rows > 0): while($a = $ach->fetch_assoc()): 
                $pic = !empty($a['image']) ? "uploads/".$a['image'] : "assets/images/3icons8-student-64.png";
            ?>
            <tr style="border-bottom:1px solid rgba(255,255,255,0.1);">
                <td style="padding:10px; display:flex; align-items:center; gap:10px;">
                    <img src="<?php echo $pic; ?>" style="width:40px; height:40px; border-radius:5px; object-fit:cover;">
                    <?php echo htmlspecialchars($a['student_name']); ?>
                </td>
                <td style="padding:10px;">
                    <strong><?php echo htmlspecialchars($a['title']); ?></strong><br>
                    <small style="color:#aaa;"><?php echo htmlspecialchars($a['description']); ?></small>
                </td>
                <td style="padding:10px; text-align:right;">
                    <form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Delete this achievement?');">
                        <input type="hidden" name="action" value="delete_achievement">
                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                        <button type="submit" class="admin-btn admin-btn-delete">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="3" style="padding:20px; text-align:center; color:#888;">No featured student set.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="admin-section" style="border-top: 4px solid #00ffaa;">
        <h3 style="color: #00ffaa;">Top Students</h3>
        <button class="admin-btn admin-btn-add" onclick="openAddAchievementModal('Top Student', 'Add Top Student')">+ Add Top Student</button>
        <div style="max-height: 300px; overflow-y: auto; margin-top:15px;">
            <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                <thead>
                    <tr style="border-bottom:1px solid #00ffaa; text-align:left;">
                        <th style="padding:10px;">Student</th>
                        <th style="padding:10px;">Title/Desc</th>
                        <th style="padding:10px; text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php $ach = $conn->query("SELECT * FROM achievements WHERE category='Top Student' ORDER BY date_posted DESC"); ?>
                <?php if($ach && $ach->num_rows > 0): while($a = $ach->fetch_assoc()): $pic = !empty($a['image']) ? "uploads/".$a['image'] : "assets/images/3icons8-student-64.png"; ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.1);">
                    <td style="padding:10px; display:flex; align-items:center; gap:10px;"><img src="<?php echo $pic; ?>" style="width:40px; height:40px; border-radius:5px; object-fit:cover;"><?php echo htmlspecialchars($a['student_name']); ?></td>
                    <td style="padding:10px;"><strong><?php echo htmlspecialchars($a['title']); ?></strong><br><small style="color:#aaa;"><?php echo htmlspecialchars($a['description']); ?></small></td>
                    <td style="padding:10px; text-align:right;"><form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Delete this achievement?');"><input type="hidden" name="action" value="delete_achievement"><input type="hidden" name="id" value="<?php echo $a['id']; ?>"><button type="submit" class="admin-btn admin-btn-delete">Delete</button></form></td>
                </tr>
                <?php endwhile; else: ?><tr><td colspan="3" style="padding:20px; text-align:center; color:#888;">No top students added.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-section" style="border-top: 4px solid #ff5555;">
        <h3 style="color: #ff5555;">Contest Winners</h3>
        <button class="admin-btn admin-btn-add" onclick="openAddAchievementModal('Contest', 'Add Contest Winner')">+ Add Contest Winner</button>
        <div style="max-height: 300px; overflow-y: auto; margin-top:15px;">
            <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                <thead><tr style="border-bottom:1px solid #ff5555; text-align:left;"><th style="padding:10px;">Student</th><th style="padding:10px;">Title/Desc</th><th style="padding:10px; text-align:right;">Action</th></tr></thead>
                <tbody>
                <?php $ach = $conn->query("SELECT * FROM achievements WHERE category='Contest' ORDER BY date_posted DESC"); ?>
                <?php if($ach && $ach->num_rows > 0): while($a = $ach->fetch_assoc()): $pic = !empty($a['image']) ? "uploads/".$a['image'] : "assets/images/3icons8-student-64.png"; ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.1);">
                    <td style="padding:10px; display:flex; align-items:center; gap:10px;"><img src="<?php echo $pic; ?>" style="width:40px; height:40px; border-radius:5px; object-fit:cover;"><?php echo htmlspecialchars($a['student_name']); ?></td>
                    <td style="padding:10px;"><strong><?php echo htmlspecialchars($a['title']); ?></strong><br><small style="color:#aaa;"><?php echo htmlspecialchars($a['description']); ?></small></td>
                    <td style="padding:10px; text-align:right;"><form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Delete this achievement?');"><input type="hidden" name="action" value="delete_achievement"><input type="hidden" name="id" value="<?php echo $a['id']; ?>"><button type="submit" class="admin-btn admin-btn-delete">Delete</button></form></td>
                </tr>
                <?php endwhile; else: ?><tr><td colspan="3" style="padding:20px; text-align:center; color:#888;">No contest winners added.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-section" style="border-top: 4px solid #00ccff;">
        <h3 style="color: #00ccff;">Sports Achievements</h3>
        <button class="admin-btn admin-btn-add" onclick="openAddAchievementModal('Sports', 'Add Sports Achievement')">+ Add Sports Achievement</button>
        <div style="max-height: 300px; overflow-y: auto; margin-top:15px;">
            <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                <thead><tr style="border-bottom:1px solid #00ccff; text-align:left;"><th style="padding:10px;">Student</th><th style="padding:10px;">Title/Desc</th><th style="padding:10px; text-align:right;">Action</th></tr></thead>
                <tbody>
                <?php $ach = $conn->query("SELECT * FROM achievements WHERE category='Sports' ORDER BY date_posted DESC"); ?>
                <?php if($ach && $ach->num_rows > 0): while($a = $ach->fetch_assoc()): $pic = !empty($a['image']) ? "uploads/".$a['image'] : "assets/images/3icons8-student-64.png"; ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.1);">
                    <td style="padding:10px; display:flex; align-items:center; gap:10px;"><img src="<?php echo $pic; ?>" style="width:40px; height:40px; border-radius:5px; object-fit:cover;"><?php echo htmlspecialchars($a['student_name']); ?></td>
                    <td style="padding:10px;"><strong><?php echo htmlspecialchars($a['title']); ?></strong><br><small style="color:#aaa;"><?php echo htmlspecialchars($a['description']); ?></small></td>
                    <td style="padding:10px; text-align:right;"><form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Delete this achievement?');"><input type="hidden" name="action" value="delete_achievement"><input type="hidden" name="id" value="<?php echo $a['id']; ?>"><button type="submit" class="admin-btn admin-btn-delete">Delete</button></form></td>
                </tr>
                <?php endwhile; else: ?><tr><td colspan="3" style="padding:20px; text-align:center; color:#888;">No sports achievements added.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php // Evaluation Questions Management
    elseif ($page === 'eval_questions'): ?>
    <div class="admin-section">
        <h3>Faculty Evaluation Questionnaire</h3>
        
        <?php
        $lock_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='evaluation_locked'");
        $is_locked = ($lock_res && $lock_res->num_rows > 0 && $lock_res->fetch_assoc()['setting_value'] == '1');
        ?>
        
        <div style="background:rgba(0,0,0,0.2); padding:15px; border-radius:10px; margin-bottom:20px; border:1px solid <?php echo $is_locked ? '#ff5555' : '#00ffaa'; ?>; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <strong style="color:<?php echo $is_locked ? '#ff5555' : '#00ffaa'; ?>; font-size:16px;">Evaluation Status: <?php echo $is_locked ? 'LOCKED' : 'OPEN'; ?></strong>
                <p style="color:#aaa; margin:5px 0 0; font-size:12px;">
                    <?php echo $is_locked ? 'Students CANNOT access the evaluation form.' : 'Students CAN access and submit evaluations.'; ?>
                </p>
            </div>
            <form method="POST" action="SACLICONNECT2.php" style="margin:0;">
                <input type="hidden" name="action" value="toggle_eval_lock">
                <input type="hidden" name="status" value="<?php echo $is_locked ? '0' : '1'; ?>">
                <button type="submit" class="admin-btn" style="background:<?php echo $is_locked ? '#00ffaa' : '#ff5555'; ?>; color:<?php echo $is_locked ? '#0a1f16' : '#fff'; ?>; font-weight:bold;">
                    <?php echo $is_locked ? 'UNLOCK EVALUATION' : 'LOCK EVALUATION'; ?>
                </button>
            </form>
        </div>

        <p style="color:#aaa; margin-top:-15px; margin-bottom:15px;">Create questions for students to rate teachers (1-5 Scale). You can group them by Category/Title.</p>
        
        <form method="POST" action="SACLICONNECT2.php" style="margin-bottom:20px; background:rgba(0,0,0,0.2); padding:15px; border-radius:10px;">
            <input type="hidden" name="action" value="add_eval_question">
            <div style="display:flex; gap:10px; flex-wrap: wrap;">
                <input type="text" name="category" placeholder="Title / Category (e.g. Teaching Performance)" list="existing_categories" style="width: 30%; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                <datalist id="existing_categories">
                    <?php 
                    $cats = $conn->query("SELECT DISTINCT category FROM evaluation_questions");
                    while($c = $cats->fetch_assoc()) echo "<option value='".htmlspecialchars($c['category'])."'>";
                    ?>
                </datalist>
                <input type="text" name="question" placeholder="Enter evaluation question (e.g., 'Does the teacher explain the lesson clearly?')" required style="flex:1; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                <button type="submit" class="admin-btn admin-btn-save">Add</button>
            </div>
        </form>

        <div style="max-height: 400px; overflow-y: auto;">
            <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                <?php 
                $qs = $conn->query("SELECT * FROM evaluation_questions ORDER BY category ASC, id ASC"); 
                $current_cat = "";
                ?>
                <?php if($qs && $qs->num_rows > 0): while($q = $qs->fetch_assoc()): 
                    if($q['category'] != $current_cat){
                        $current_cat = $q['category'];
                        echo "<tr><td colspan='2' style='padding:10px; background:rgba(0,255,170,0.1); color:#00ffaa; font-weight:bold; text-transform:uppercase; border-bottom:1px solid rgba(255,255,255,0.1);'>".htmlspecialchars($current_cat)."</td></tr>";
                    }
                ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.1);">
                    <td style="padding:15px; font-size:14px; padding-left: 30px;">• <?php echo htmlspecialchars($q['question']); ?></td>
                    <td style="padding:15px; text-align:right; width:100px;">
                        <form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Delete this question?');">
                            <input type="hidden" name="action" value="delete_eval_question">
                            <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                            <button type="submit" class="admin-btn admin-btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="2" style="padding:20px; text-align:center; color:#888;">No questions added yet.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <?php // Evaluation Results Page
    elseif ($page === 'evaluation_results'): ?>
    <div class="admin-section">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px; margin-bottom:25px;">
            <h3 style="margin:0; border:none; padding:0;">Faculty Evaluation Results</h3>
            <form method="POST" action="SACLICONNECT2.php" onsubmit="return confirm('WARNING: This will delete ALL evaluation records from all students. This action cannot be undone. Are you sure?');" style="margin:0;">
                <input type="hidden" name="action" value="reset_all_evaluations">
                <button type="submit" class="admin-btn admin-btn-delete" style="background:#ff0000; color:white; border:1px solid #ff5555; font-weight:bold;">RESET ALL DATA</button>
            </form>
        </div>
        
        <?php if(isset($_GET['view_teacher'])): 
            $tid = intval($_GET['view_teacher']);
            $t_info = $conn->query("SELECT * FROM teachers WHERE id=$tid")->fetch_assoc();
            
            // Calculate Score
            $avg_rating = 0;
            // Try to get precise average from answers
            $score_q = $conn->query("SELECT AVG(ea.rating) as avg_r FROM evaluation_answers ea JOIN evaluations e ON ea.evaluation_id = e.id WHERE e.teacher_id=$tid");
            if($score_q && $row = $score_q->fetch_assoc()){
                $avg_rating = $row['avg_r'];
            }
            // Fallback if no detailed answers
            if(!$avg_rating){
                $score_q2 = $conn->query("SELECT AVG(rating) as avg_r FROM evaluations WHERE teacher_id=$tid");
                if($score_q2 && $row = $score_q2->fetch_assoc()) $avg_rating = $row['avg_r'];
            }
            
            $percentage = $avg_rating ? ($avg_rating / 5) * 100 : 0;
            $grade = '';
            $color = '';
            
            // Grading Scale
            if($percentage >= 100) { $grade = 'A+'; $color = '#00ffaa'; } // 100%
            elseif($percentage >= 90) { $grade = 'A'; $color = '#00ccff'; } // 90-99%
            elseif($percentage >= 80) { $grade = 'B'; $color = '#ffd700'; } // 80-89%
            elseif($percentage >= 75) { $grade = 'C'; $color = '#ffaa00'; } // 75-79%
            else { $grade = 'Below C'; $color = '#ff5555'; } // Below 75%
            
            $pic = !empty($t_info['profile_pic']) ? "uploads/".$t_info['profile_pic'] : "4icons8-teacher-50.png";
        ?>
            <div class="no-print" style="display:flex; justify-content:space-between; margin-bottom:20px;">
                <button onclick="location.href='SACLICONNECT2.php?page=evaluation_results'" class="admin-btn" style="background:rgba(255,255,255,0.1);">&larr; Back to List</button>
                <div style="display:flex; gap:15px; align-items: center;">
                    <button onclick="window.print()" class="admin-btn" style="background:rgba(255,255,255,0.1); border:1px solid #fff; height:45px;">🖨️ Print Report</button>
                    
                    <button class="botao" onclick="downloadEvaluationReport('<?php echo addslashes($t_info['name']); ?>')">
                        <svg width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="mysvg"><g id="SVGRepo_bgCarrier" stroke-width="0">
                            </g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier">
                            <g id="Interface / Download"> 
                            <path id="Vector" d="M6 21H18M12 3V17M12 17L17 12M12 17L7 12" stroke="#f1f1f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                            </g> </g>
                        </svg>
                        <span class="texto">Download</span>
                    </button>
                </div>
            </div>

            <div class="print-report-area" style="text-align:center; background:rgba(0,0,0,0.2); padding:40px; border-radius:20px; border:1px solid <?php echo $color; ?>; box-shadow: 0 0 30px <?php echo $color; ?>40;">
                <div class="print-header" style="display:none;">
                    <img src="assets/images/St.Anne_logo.png" style="width:60px; margin-bottom:10px;">
                    <h2 style="margin:0; color:#000;">St. Anne College Lucena, Inc.</h2>
                    <p style="margin:5px 0 0; color:#000;">FACULTY EVALUATION OFFICIAL REPORT</p>
                    <hr style="margin:20px 0; border:1px solid #000;">
                </div>

                <img class="no-print" src="<?php echo $pic; ?>" style="width:120px; height:120px; border-radius:50%; object-fit:cover; border:4px solid <?php echo $color; ?>; margin-bottom:20px;">
                <h2 style="color:white; margin:0; font-size:28px;"><?php echo htmlspecialchars($t_info['name']); ?></h2>
                <p style="color:#aaa; margin:5px 0 30px; font-size:16px;"><?php echo htmlspecialchars($t_info['department']); ?></p>
                
                <h4 style="color:#00ffaa; margin-top:20px; border-bottom:1px solid #00ffaa; padding-bottom:10px; text-align:left;">I. Questionnaire Breakdown</h4>
                <table class="print-table" style="width:100%; border-collapse:collapse; color:#ccffeb; text-align:left;">
                    <thead>
                        <tr style="border-bottom:1.5px solid rgba(255,255,255,0.2);">
                            <th style="padding:10px;">Question Description</th>
                            <th style="padding:10px; text-align:center; width:80px;">Rating</th>
                            <th style="padding:10px; text-align:center; width:100px;">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        /**
                         * Query in-update para siguraduhin na lumabas ang LAHAT ng tanong mula sa database
                         * kahit wala pang rating ang guro sa partikular na tanong na iyon.
                         */
                        $breakdown_q = $conn->query("
                            SELECT eq.question, eq.category, 
                                   AVG(CASE WHEN e.teacher_id = $tid THEN ea.rating ELSE NULL END) as avg_q_rating
                            FROM evaluation_questions eq
                            LEFT JOIN evaluation_answers ea ON eq.id = ea.question_id
                            LEFT JOIN evaluations e ON ea.evaluation_id = e.id
                            GROUP BY eq.id
                            ORDER BY eq.category, eq.id
                        ");
                        $current_cat_print = "";
                        if($breakdown_q) while($bq = $breakdown_q->fetch_assoc()):
                            if($bq['category'] != $current_cat_print):
                                $current_cat_print = $bq['category'];
                                echo "<tr><td colspan='3' class='print-category' style='padding:8px 15px; background:rgba(0,255,170,0.1); color:#00ffaa; font-weight:bold; font-size:12px;'>".htmlspecialchars($current_cat_print)."</td></tr>";
                            endif;
                            $q_avg = $bq['avg_q_rating'] ?: 0;
                            $q_pct = ($q_avg / 5) * 100;
                        ?>
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                            <td style="padding:10px; font-size:13px;"><?php echo htmlspecialchars($bq['question']); ?></td>
                            <td style="padding:10px; text-align:center; font-weight:bold;"><?php echo number_format($q_avg, 2); ?></td>
                            <td style="padding:10px; text-align:center; color:#00ffaa; font-weight:bold;"><?php echo number_format($q_pct, 1); ?>%</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- Final Summary Section (NAKA-PUWESTO SA BABA NG MGA TANONG) -->
                <div class="print-summary-section" style="margin-top:40px; padding:30px; border-top:2px solid <?php echo $color; ?>; background:rgba(255,255,255,0.05); border-radius: 15px;">
                    <h4 style="color:#00ffaa; margin-top:0; text-align:left; margin-bottom: 20px;">II. Final Evaluation Summary</h4>
                    <div style="display:flex; justify-content:center; gap:60px; align-items:center;">
                        <div style="text-align:center;">
                            <div style="font-size:12px; color:#aaa; text-transform:uppercase; letter-spacing:1px; margin-bottom:5px;">FINAL GRADE</div>
                            <div class="grade-display-print" style="font-size:90px; font-weight:900; color:<?php echo $color; ?>; text-shadow:0 0 20px <?php echo $color; ?>; line-height:1;"><?php echo $grade; ?></div>
                        </div>
                        <div class="no-print" style="height:80px; width:1px; background:rgba(255,255,255,0.1);"></div>
                        <div style="text-align:left;">
                            <div style="font-size:24px; color:white; margin-bottom:5px;">Rating: <b style="color:white;"><?php echo number_format($percentage, 2); ?>%</b></div>
                            <div style="font-size:14px; color:#aaa;">Numerical Average: <b><?php echo number_format($avg_rating, 2); ?> / 5.00</b></div>
                        </div>
                    </div>
                </div>

                <!-- Signature Area (Lilitaw lang sa Print) -->
                <div class="signature-line" style="display:none; margin-top:80px; width:100%;">
                    <table style="width:100%; border:none !important;">
                        <tr style="border:none !important;">
                            <td style="border:none !important; width:40%; text-align:center; padding:0;">
                                <div style="border-top:1px solid #000; padding-top:5px; margin-top:40px;">
                                    <b style="font-size:12px; text-transform:uppercase; color:#000;">Department Dean / Head</b>
                                </div>
                            </td>
                            <td style="border:none !important; width:20%;"></td>
                            <td style="border:none !important; width:40%; text-align:center; padding:0;">
                                <div style="border-top:1px solid #000; padding-top:5px; margin-top:40px;">
                                    <b style="font-size:12px; text-transform:uppercase; color:#000;">Date of Issuance</b>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <h4 class="no-print" style="color:#00ffaa; margin-top:40px; border-bottom:1px solid #00ffaa; padding-bottom:10px;">Student Feedback / Comments (Anonymous)</h4>
            <div class="no-print" style="display:grid; gap:15px;">
                <?php 
                $comments_q = $conn->query("SELECT comments, date_evaluated FROM evaluations WHERE teacher_id=$tid AND comments != '' ORDER BY date_evaluated DESC");
                if($comments_q && $comments_q->num_rows > 0):
                    while($c = $comments_q->fetch_assoc()):
                ?>
                <div style="background:rgba(255,255,255,0.05); padding:15px; border-radius:10px; border-left:3px solid #00ffaa;">
                    <p style="margin:0; color:#e4e6eb; font-style:italic;">"<?php echo htmlspecialchars($c['comments']); ?>"</p>
                    <small style="color:#aaa; display:block; margin-top:5px;"><?php echo date("M d, Y", strtotime($c['date_evaluated'])); ?></small>
                </div>
                <?php endwhile; else: ?>
                    <p style="color:#888; font-style:italic;">No written feedback provided for this teacher.</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <p style="color:#aaa; margin-bottom:20px;">Select a teacher to view their evaluation performance.</p>
            
            <div style="margin-bottom: 20px;">
                <input type="text" id="evalSearch" onkeyup="filterEvalResults()" placeholder="Search teacher name or department..." style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #00ffaa; background: rgba(0,0,0,0.3); color: white; outline: none;">
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:20px;">
                <?php
                $teachers = $conn->query("SELECT DISTINCT t.id, t.name, t.department, t.profile_pic FROM teachers t JOIN evaluations e ON t.id = e.teacher_id ORDER BY t.name");
                if($teachers->num_rows > 0):
                    while($t = $teachers->fetch_assoc()):
                        $pic = !empty($t['profile_pic']) ? "uploads/".$t['profile_pic'] : "4icons8-teacher-50.png";
                ?>
                <div class="eval-result-item" onclick="location.href='SACLICONNECT2.php?page=evaluation_results&view_teacher=<?php echo $t['id']; ?>'" style="background:rgba(255,255,255,0.05); padding:25px; border-radius:15px; text-align:center; cursor:pointer; border:1px solid rgba(255,255,255,0.1); transition:0.3s; position:relative; overflow:hidden;" onmouseover="this.style.borderColor='#00ffaa'; this.style.background='rgba(0,255,170,0.1)';" onmouseout="this.style.borderColor='rgba(255,255,255,0.1)'; this.style.background='rgba(255,255,255,0.05)';">
                    <img src="<?php echo $pic; ?>" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid #00ffaa; margin-bottom:15px;">
                    <h4 style="color:white; margin:0; font-size:16px;"><?php echo htmlspecialchars($t['name']); ?></h4>
                    <small style="color:#aaa; display:block; margin-top:5px;"><?php echo htmlspecialchars($t['department']); ?></small>
                    <div style="margin-top:15px; color:#00ffaa; font-size:11px; font-weight:bold; border:1px solid #00ffaa; padding:5px 10px; border-radius:20px; display:inline-block;">VIEW RESULT</div>
                </div>
                <?php endwhile; else: ?>
                    <div style="grid-column:1/-1; text-align:center; padding:40px; color:#888; background:rgba(0,0,0,0.2); border-radius:10px;">
                        No evaluations have been submitted yet.
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php // Access Links Page
    elseif ($page === 'access_links'): ?>
    <div class="admin-section">
        <h3>Access Other Links (kanan sa SacliConnect)</h3>
        <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_subjects">
            <div>
                <div id="subject-rows">
                    <?php foreach ($subjectChats as $i => $s): ?>
                    <div class="admin-form-row" style="align-items: flex-start;">
                        <div style="flex:1; display:flex; flex-direction:column; gap:5px;">
                            <input type="text" name="subject_name[]" value="<?php echo htmlspecialchars($s['name']); ?>" placeholder="Name (e.g. Portal)" style="width:100%;">
                            <input type="text" name="subject_url[]" value="<?php echo htmlspecialchars($s['url']); ?>" placeholder="URL (https://...)" style="width:100%;">
                        </div>
                        <div style="display:flex; flex-direction:column; align-items:center; gap:5px; width: 150px;">
                            <?php $iconSrc = !empty($s['icon']) ? (file_exists("uploads/".$s['icon']) ? "uploads/".$s['icon'] : $s['icon']) : 'St.Anne_logo.png'; ?>
                            <img src="<?php echo $iconSrc; ?>" style="width:32px; height:32px; object-fit:cover; border-radius:5px; border:1px solid #00ffaa;">
                            <input type="hidden" name="existing_icon[]" value="<?php echo htmlspecialchars($s['icon']); ?>">
                            <input type="file" name="subject_icon_file[]" accept="image/*" style="width:100%; font-size:10px; color:#ccc;">
                        </div>
                        <label style="display:flex; align-items:center; gap:8px; color:#66ffd9; margin-top:10px;">
                            <input type="checkbox" name="subject_online[<?php echo $i; ?>]" value="1" <?php echo $s['is_online'] ? 'checked' : ''; ?>>
                            Online
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="margin-top:15px; display:flex; gap:10px;">
                <button type="button" class="admin-btn admin-btn-add" onclick="addSubjectRow()">+ Add Link</button>
                <button type="submit" class="admin-btn admin-btn-save">Save Subjects</button>
            </div>
        </form>
    </div>

    <?php // Login Video Settings Page
    elseif ($page === 'login_video_settings'): ?>
    <div class="admin-section">        
        <?php
        // Fetch all settings
        $settings = [];
        $settings_res = $conn->query("SELECT setting_key, setting_value FROM site_settings");
        if($settings_res) while($row = $settings_res->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value'];

        $cur_theme = $settings['site_theme'] ?? 'default';
        $themes = [
            'default' => 'Default (Dark Green)',
            'halloween' => 'Halloween (Spooky Purple/Orange)',
            'christmas' => 'Christmas (Red/Green/Snow)',
            'summer' => 'Summer (Sunny Blue/Yellow)',
            'new_year' => 'New Year (Gold/Black Fireworks)'
        ];

        $video_themes = [
            'default'   => ['label' => 'Default Video', 'key' => 'login_video', 'file_key' => 'login_video_file', 'action' => 'update_login_video'],
            'halloween' => ['label' => 'Halloween Video', 'key' => 'halloween_video', 'file_key' => 'halloween_video_file', 'action' => 'update_halloween_video'],
            'christmas' => ['label' => 'Christmas Video', 'key' => 'christmas_video', 'file_key' => 'christmas_video_file', 'action' => 'update_christmas_video'],
            'summer'    => ['label' => 'Summer Video', 'key' => 'summer_video', 'file_key' => 'summer_video_file', 'action' => 'update_summer_video'],
            'new_year'  => ['label' => 'New Year Video', 'key' => 'new_year_video', 'file_key' => 'new_year_video_file', 'action' => 'update_new_year_video'],
        ];
        ?>

        <h3 style="margin-bottom: 20px;">Site Theme (Seasonal Design)</h3>
        <p style="color:#aaa; font-size:13px; margin-bottom:15px;">Select a theme to change the look of the Login Page and Intro Screen.</p>
        <form id="themeForm" method="POST" action="SACLICONNECT2.php" onsubmit="applyThemeAjax(event)">
            <input type="hidden" name="action" value="update_site_theme">
            <input type="hidden" name="is_ajax" value="1">
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach($themes as $key => $label): ?>
                <label class="theme-opt-label theme-opt-<?php echo $key; ?> <?php echo ($cur_theme == $key) ? 'active-opt' : ''; ?>">
                    <input type="radio" name="site_theme" value="<?php echo $key; ?>" <?php echo ($cur_theme == $key) ? 'checked' : ''; ?> style="width:auto !important;">
                    <span><?php echo $label; ?></span>
                    <?php if ($cur_theme == $key): ?>
                        <div class="live-badge">LIVE</div>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="admin-btn admin-btn-save" style="margin-top:15px;">Apply Theme</button>
        </form>

        <h3 style="margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">Video Playback Settings</h3>
        <div style="background:rgba(0,0,0,0.2); padding:20px; border-radius:15px; border:1px solid #00ffaa; margin-top: 15px;">
            <form method="POST" action="SACLICONNECT2.php">
                <input type="hidden" name="action" value="update_video_mute">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:#b0fce0; font-size:14px;">Mute video by default on Login Page?</span>
                    <label class="switch">
                        <input type="checkbox" name="video_muted" <?php echo ($settings['login_video_muted'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <span class="slider round"></span>
                    </label>
                </div>
                <small style="color:#888; display:block; margin-top:10px;">Note: Modern browsers (Chrome/Brave) might block unmuted videos from playing automatically unless the user interacts with the page first.</small>
                <button type="submit" class="admin-btn admin-btn-save" style="width:100%; margin-top:20px;">Update Behavior</button>
            </form>
        </div>

        <h3 style="margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">Seasonal Login Videos</h3>
        <p style="color:#aaa; font-size:13px; margin-bottom:25px;">Upload a specific video for each theme. If no video is set for a theme, it will use the default video.</p>

        <div style="display:flex; flex-direction:column; gap:20px;">
        <?php foreach($video_themes as $id => $theme): 
            $current_video = $settings[$theme['key']] ?? 'Not set';
        ?>
            <div style="background:rgba(0,0,0,0.2); padding:15px; border-radius:10px; border:1px solid rgba(255,255,255,0.1);">
                <label style="color:#ccffeb; font-weight:bold;"><?php echo $theme['label']; ?></label>
                <p style="color:#aaa; font-size:11px; margin-top:2px; margin-bottom:10px;">Current: <?php echo basename($current_video); ?></p>
                <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $theme['action']; ?>">
                    <input type="file" name="<?php echo $theme['file_key']; ?>" accept="video/*" required style="width:100%; padding:8px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px; margin-bottom:10px;">
                    <button type="submit" class="admin-btn admin-btn-save" style="width:100%; padding:8px;">Update Video</button>
                </form>
            </div>
        <?php endforeach; ?>
        </div>

        <h3 style="margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">Login Background Branding</h3>
        <p style="color:#aaa; font-size:13px; margin-bottom:25px;">Upload background images for the login portal (Holographic style).</p>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
            <div style="background:rgba(0,0,0,0.2); padding:15px; border-radius:10px; border:1px solid rgba(255,255,255,0.1);">
                <label style="color:#ccffeb; font-weight:bold;">Background Logo 1 (Large - Left)</label>
                <p style="color:#aaa; font-size:11px; margin-bottom:10px;">Current: <?php echo basename($settings['login_bg_logo1'] ?? 'ST40.png'); ?></p>
                <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_bg_logo1">
                    <input type="file" name="bg_logo1_file" accept="image/*" required style="width:100%; padding:8px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px; margin-bottom:10px;">
                    <button type="submit" class="admin-btn admin-btn-save" style="width:100%; padding:8px;">Update Logo 1</button>
                </form>
            </div>
            <div style="background:rgba(0,0,0,0.2); padding:15px; border-radius:10px; border:1px solid rgba(255,255,255,0.1);">
                <label style="color:#ccffeb; font-weight:bold;">Background Logo 2 (Large - Right)</label>
                <p style="color:#aaa; font-size:11px; margin-bottom:10px;">Current: <?php echo basename($settings['login_bg_logo'] ?? 'St.Anne_logo.png'); ?></p>
                <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_bg_logo2">
                    <input type="file" name="bg_logo2_file" accept="image/*" required style="width:100%; padding:8px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px; margin-bottom:10px;">
                    <button type="submit" class="admin-btn admin-btn-save" style="width:100%; padding:8px;">Update Logo 2</button>
                </form>
            </div>
        </div>
    </div>

    <?php // Calendar Events Page
    elseif ($page === 'calendar'): ?>
    <div class="admin-section">
        <h3>Calendar Events (Notify Students)</h3>
        <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data" style="margin-bottom:20px; background:rgba(0,0,0,0.2); padding:15px; border-radius:10px;">
            <input type="hidden" name="action" value="add_event">
            <div style="display:flex; gap:10px; margin-bottom:10px;">
                <input type="date" name="event_date" required style=" width: 20%; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                <input type="text" name="title" placeholder="Event Title" required style="flex:1; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            </div>
            <div style="margin-bottom:10px;">
                <label style="color:#ccffeb; font-size:12px;">Event Photo (Optional):</label>
                <input type="file" name="event_image" accept="image/*" style="width:100%; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            </div>
            <textarea name="description" placeholder="Event Description (Will be sent to students)" rows="2" style="width:100%; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px; margin-bottom:10px;"></textarea>
            <button type="submit" class="admin-btn admin-btn-save">Set Event & Notify Students</button>
        </form>

        <div style="max-height: 300px; overflow-y: auto;">
            <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                <?php
                $events = $conn->query("SELECT * FROM calendar_events ORDER BY event_date DESC");
                if($events && $events->num_rows > 0):
                    while($ev = $events->fetch_assoc()):
                ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.1);">
                    <td style="padding:10px; color:#00ffaa; font-weight:bold;"><?php echo date("M d, Y", strtotime($ev['event_date'])); ?></td>
                    <td style="padding:10px;">
                        <strong><?php echo htmlspecialchars($ev['title']); ?></strong><br>
                        <small style="color:#aaa;"><?php echo htmlspecialchars($ev['description']); ?></small>
                        <?php if(!empty($ev['event_image'])): ?>
                            <br><img src="<?php echo htmlspecialchars($ev['event_image']); ?>" style="height:50px; margin-top:5px; border-radius:5px;">
                        <?php endif; ?>
                    </td>
                    <td style="padding:10px; text-align:right;">
                        <form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this event?');">
                            <input type="hidden" name="action" value="delete_event">
                            <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                            <button type="submit" class="admin-btn admin-btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="3" style="padding:10px; text-align:center; color:#aaa;">No events set.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <?php // Teacher Management Page
    elseif ($page === 'teachers'): ?>
    <div class="admin-section">
        <h3>Teacher Management</h3>
        <button class="admin-btn admin-btn-add" onclick="openAddTeacherModal()" style="margin-bottom: 10px;">+ Add Teacher</button>
        
        <div style="margin-bottom: 15px;">
            <input type="text" id="teacherSearch" onkeyup="filterTeachers()" placeholder="Search teacher name or department..." style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #00ffaa; background: rgba(0,0,0,0.3); color: white; outline: none;">
        </div>

        <div style="max-height: 300px; overflow-y: auto;">
            <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                <?php $teachers = $conn->query("SELECT * FROM teachers ORDER BY department, name"); ?>
                <?php if($teachers): while($t = $teachers->fetch_assoc()): 
                    $pic = !empty($t['profile_pic']) ? "uploads/".$t['profile_pic'] : "4icons8-teacher-50.png";
                    $is_restricted = $t['is_restricted'] == 1;
                    $restrict_text = $is_restricted ? "Restricted until " . date("M d, Y", strtotime($t['restriction_end_date'])) : "";
                ?>
                <tr class="teacher-row" style="border-bottom:1px solid rgba(255,255,255,0.1);">
                    <td style="padding:10px; display:flex; align-items:center; gap:15px;">
                        <img src="<?php echo $pic; ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover; border: 2px solid #00ffaa;">
                        <div>
                            <strong><?php echo htmlspecialchars($t['name']); ?></strong><br>
                            <?php if($is_restricted): ?><small style="color:#ff5555;">⚠️ <?php echo $restrict_text; ?></small><br><?php endif; ?>
                            <small style="color:#aaa;"><?php echo htmlspecialchars($t['department']); ?></small>
                            <?php if(!empty($t['position'])): ?><br><small style="color:#00ffaa;"><?php echo htmlspecialchars($t['position']); ?></small><?php endif; ?>
                        </div>
                    </td>
                    <td style="padding:10px; text-align:right; vertical-align: middle;">
                        <button type="button" class="admin-btn admin-btn-add" style="padding:6px 12px; font-size:14px; background:#00ffaa; color:#0a1f16;" onclick='openViewPostsModal("<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>")'>View Posts</button>
                        <button type="button" class="admin-btn admin-btn-add" style="padding:6px 12px; font-size:14px;" onclick='openTeacherModal(<?php echo htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8'); ?>, "<?php echo $pic; ?>")'>Edit</button>
                        <button type="button" class="admin-btn" style="padding:6px 12px; font-size:14px; background:<?php echo $is_restricted ? '#ff5555' : '#e67e22'; ?>; color:white;" onclick='openRestrictModal("teacher", "<?php echo $t['id']; ?>", "<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>", "teachers", <?php echo $is_restricted ? 1 : 0; ?>)'>Restrict</button>
                        <form method="POST" style="margin:0; display:inline-block;" onsubmit="return confirmFormSubmit(event, 'Delete this teacher?');">
                            <input type="hidden" name="action" value="delete_teacher">
                            <input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                            <button type="submit" class="admin-btn admin-btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; endif; ?>
            </table>
        </div>
    </div>

    <?php // Uploads Explorer Page
    elseif ($page === 'uploads'): 
        $view = $_GET['view'] ?? 'uploads';
        $folder_map = [
            'submissions' => ['dir' => 'submissions/', 'title' => "LMS Submissions"],
            'storage'     => ['dir' => 'uploads/storage/', 'title' => "SC Storage"],
            'uploads'     => ['dir' => 'uploads/', 'title' => "System Uploads"]
        ];

        $target_dir = $folder_map[$view]['dir'];
        $display_title = $folder_map[$view]['title'];
        
        // Ensure directories exist
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        // Fetch from Database Registry instead of direct folder scanning
        $files_query = $conn->query("SELECT * FROM system_file_registry WHERE source_folder = '$view' ORDER BY registered_at DESC");
        
        // Check if DB is empty, suggest sync
        $registry_empty = ($files_query->num_rows === 0);
        
        // Calculate total space from DB
        $space_q = $conn->query("SELECT COUNT(*) as total, SUM(CAST(REPLACE(file_size, ' KB', '') AS DECIMAL(10,2))) as size FROM system_file_registry WHERE source_folder = '$view' AND status = 'present'");
        $space_data = $space_q->fetch_assoc();
        $total_files = $space_data['total'] ?? 0;
        $total_kb = $space_data['size'] ?? 0;
        $total_mb = round($total_kb / 1024, 2);
        $purged_count = $conn->query("SELECT COUNT(*) FROM system_file_registry WHERE source_folder = '$view' AND status = 'purged'")->fetch_row()[0];
    ?>
    <div class="admin-section">
        <h3>File Explorer — <?php echo $display_title; ?></h3>
        <p style="color:#aaa; margin-top:-15px; margin-bottom:20px;">Registry Mode: Tracking all system assets (<?php echo $total_files; ?> active, <?php echo $purged_count; ?> archived).</p>
        
        <div style="display:flex; gap:10px; margin-bottom:25px;">
            <div style="flex:3; display:flex; gap:10px;">
                <a href="SACLICONNECT2.php?page=uploads&view=uploads" onclick="loadAjaxPage('uploads&view=uploads', event, this)" class="admin-btn <?php echo ($view === 'uploads') ? 'admin-btn-save' : 'admin-btn-add'; ?>" style="flex:1; text-decoration:none; height:45px;">🖼️ System Uploads</a>
                <a href="SACLICONNECT2.php?page=uploads&view=storage" onclick="loadAjaxPage('uploads&view=storage', event, this)" class="admin-btn <?php echo ($view === 'storage') ? 'admin-btn-save' : 'admin-btn-add'; ?>" style="flex:1; text-decoration:none; height:45px;">📁 SC Storage</a>
                <a href="SACLICONNECT2.php?page=uploads&view=submissions" onclick="loadAjaxPage('uploads&view=submissions', event, this)" class="admin-btn <?php echo ($view === 'submissions') ? 'admin-btn-save' : 'admin-btn-add'; ?>" style="flex:1; text-decoration:none; height:45px;">📥 LMS Submissions</a>
            </div>
            <div style="flex:1; display:flex; gap:10px;">
                <form method="POST" style="flex:1; margin:0;">
                    <input type="hidden" name="action" value="sync_file_registry">
                    <button type="submit" class="admin-btn" style="width:100%; height:45px; background:rgba(0,204,255,0.1); color:#00ccff; border:1px solid #00ccff;">🔄 Sync Disk</button>
                </form>
                <div style="flex:1; background:rgba(0,255,170,0.1); border:1px solid #00ffaa; border-radius:10px; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:5px;">
                    <span style="font-size:10px; color:#aaa;">DISK_USE</span>
                    <b style="color:#fff;"><?php echo $total_mb; ?> MB</b>
                </div>
            </div>
            <form method="POST" style="flex:1; margin:0;" onsubmit="return confirm('WARNING: This will PHYSICALLY DELETE all unprotected files in this folder to save disk space. Records will stay in the Registry (DB). Proceed?')">
                <input type="hidden" name="action" value="purge_folder">
                <input type="hidden" name="view_type" value="<?php echo $view; ?>">
                    <button type="submit" class="admin-btn admin-btn-delete" style="width:100%; height:45px; background:#ff4757; color:white; border-radius:10px;">🔥 Disk Cleanup</button>
            </form>
        </div>

        <form method="POST" onsubmit="return confirm('PURGE_PROTOCOL: This will PHYSICALLY DELETE the selected files from the server folder to save space.\n\nNOTE: The records (posts, drive entries, etc.) will still exist in the system, but the actual files will be gone. Proceed?')">
            <input type="hidden" name="action" value="delete_selected_files">
            <input type="hidden" name="view_type" value="<?php echo $view; ?>">
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; background:rgba(0,255,170,0.05); padding:10px; border-radius:10px; border:1px solid rgba(0,255,170,0.1);">
                <div style="display:flex; align-items:center; gap:20px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:#00ffaa; font-weight:bold; font-size:13px;">
                        <input type="checkbox" id="selectAllFiles" onclick="toggleSelectAllFiles(this)" style="width:18px; height:18px; accent-color:#00ffaa;" <?php echo $registry_empty ? 'disabled' : ''; ?>>
                        Select All
                    </label>
                    <span style="font-size:11px; color:#888; font-family:monospace; letter-spacing:1px;">// MODE: PHYSICAL_FILE_PURGE (DB_RECORDS_PERSIST)</span>
                </div>
                <button type="submit" id="bulkDeleteBtn" class="admin-btn admin-btn-delete" style="display:none; padding:8px 25px; background:#ff4757; color:white;">🔥 Purge Selected Files</button>
            </div>

            <div style="max-height: 600px; overflow-y: auto; background:rgba(0,0,0,0.2); border-radius:10px; border:1px solid rgba(0,255,170,0.1);">
                <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                    <thead>
                        <tr style="border-bottom:1px solid #00ffaa; text-align:left; position:sticky; top:0; background:#102e22; z-index:5;">
                            <th style="padding:15px; width:40px;"></th>
                            <th style="padding:15px;">Name / Identity</th>
                            <th style="padding:15px;">Status</th>
                            <th style="padding:15px;">Payload</th>
                            <th style="padding:15px;">Registry Date</th>
                            <th style="padding:15px; text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($registry_empty): ?>
                            <tr><td colspan="6" style="padding:40px; text-align:center; color:#888;">No registry records found. <br><br> <button type="button" onclick="document.querySelector('[name=action][value=sync_file_registry]').parentElement.submit()" style="background:transparent; border:1px solid #00ffaa; color:#00ffaa; padding:10px 20px; border-radius:5px; cursor:pointer;">Initialize Registry Sync</button></td></tr>
                        <?php else: 
                            while($file_data = $files_query->fetch_assoc()): 
                                $file = $file_data['file_name'];
                                $path = $file_data['file_path'];
                                $size = $file_data['file_size'];
                                $date = date("M d, Y H:i", strtotime($file_data['registered_at']));
                                $is_img = preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file);
                                $is_vid = preg_match('/\.(mp4|webm|ogg)$/i', $file);
                                $is_purged = $file_data['status'] === 'purged';
                                
                                $row_opacity = $is_purged ? 'opacity: 0.5;' : '';
                        ?>
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.05); <?php echo $row_opacity; ?>">
                            <td style="padding:15px; text-align:center;">
                                <input type="checkbox" name="selected_files[]" value="<?php echo htmlspecialchars($file); ?>" class="file-checkbox" onclick="toggleBulkDeleteBtn()" style="width:18px; height:18px; accent-color:#00ffaa; cursor:pointer;" <?php echo $is_purged ? 'disabled' : ''; ?>>
                            </td>
                            <td style="padding:12px 15px; display:flex; align-items:center; gap:12px;">
                                <div style="width:40px; height:40px; background:rgba(255,255,255,0.05); border-radius:5px; overflow:hidden; display:flex; align-items:center; justify-content:center; border:1px solid rgba(0,255,170,0.2);">
                                    <?php if($is_img && !$is_purged): ?>
                                        <img src="<?php echo $path; ?>" style="width:100%; height:100%; object-fit:cover;">
                                    <?php elseif($is_vid): ?>
                                        <span style="font-size:18px;">🎬</span>
                                    <?php else: ?>
                                        <span style="font-size:18px;">📄</span>
                                    <?php endif; ?>
                                </div>
                                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:300px;">
                                    <strong title="<?php echo htmlspecialchars($file); ?>"><?php echo htmlspecialchars($file); ?></strong>
                                </div>
                            </td>
                            <td style="padding:15px;">
                                <span style="font-size:11px; padding:3px 8px; border-radius:10px; background:<?php echo $is_purged ? '#333' : '#1a3d2f'; ?>; color:<?php echo $is_purged ? '#888' : '#00ffaa'; ?>; font-weight:bold;">
                                    <?php echo $is_purged ? 'ARCHIVED' : 'ACTIVE_NODE'; ?>
                                </span>
                            </td>
                            <td style="padding:15px; font-size:13px; color:#aaa;"><?php echo $is_purged ? 'Cleared' : $size; ?></td>
                            <td style="padding:15px; font-size:13px; color:#aaa;"><?php echo $date; ?></td>
                            <td style="padding:15px; text-align:right; display:flex; gap:5px; justify-content:flex-end;">
                                <?php if(!$is_purged): ?>
                                    <a href="<?php echo $path; ?>" download class="admin-btn" style="background:rgba(0,204,255,0.1); color:#00ccff; border:1px solid #00ccff; padding:6px 10px; font-size:11px; text-decoration:none; border-radius:5px;">Download</a>
                                    <button type="button" class="admin-btn admin-btn-delete" style="padding:6px 10px; font-size:11px; background:#ff4757; color:white; border:none; border-radius:5px;" onclick="confirmSinglePurge('<?php echo htmlspecialchars($file); ?>', '<?php echo $view; ?>')">Purge</button>
                                <?php else: ?>
                                    <span style="font-size:10px; color:#555; font-family:monospace;">PAYLOAD_REMOVED</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <?php // Deactivated Accounts Page
    elseif ($page === 'deactivated'): ?>
    <div class="admin-section">
        <h3>Deactivated Accounts (Indefinite)</h3>
        <p style="color:#aaa; margin-top:-15px; margin-bottom:25px;">List of accounts that have been deactivated (e.g. transferred, left school). These users cannot log in.</p>

        <h4 style="color:#ffd700; border-bottom:1px solid #ffd700; padding-bottom:10px;">Deactivated Students (Grouped by Course)</h4>
        
        <?php
        $ds_res = $conn->query("SELECT * FROM students WHERE is_restricted = 1 AND restriction_end_date IS NULL ORDER BY course ASC, student_name ASC");
        $grouped_students = [];
        if($ds_res){
            while($row = $ds_res->fetch_assoc()){
                $c = empty($row['course']) ? 'Unassigned Course' : $row['course'];
                $grouped_students[$c][] = $row;
            }
        }
        
        if(empty($grouped_students)): 
            echo '<p style="color:#888; text-align:center; padding:20px;">No deactivated students.</p>';
        else:
            foreach($grouped_students as $course => $students):
        ?>
            <div style="margin-bottom:20px; background:rgba(255,255,255,0.02); border-radius:10px; border:1px solid rgba(255,255,255,0.05); overflow:hidden;">
                <div style="padding:10px 15px; background:rgba(0,255,170,0.1); color:#00ffaa; font-weight:bold; font-size:14px; border-bottom:1px solid rgba(255,255,255,0.05);">
                    <?php echo htmlspecialchars($course); ?> <span style="font-weight:normal; color:#aaa; font-size:12px;">(<?php echo count($students); ?>)</span>
                </div>
                <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                    <?php foreach($students as $s): 
                        $pic = !empty($s['profile_pic']) ? "uploads/".$s['profile_pic'] : "assets/images/3icons8-student-64.png";
                    ?>
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                        <td style="padding:10px; width:60px;"><img src="<?php echo $pic; ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid #555;"></td>
                        <td style="padding:10px;"><strong><?php echo htmlspecialchars($s['student_name']); ?></strong><br><small style="color:#aaa;"><?php echo htmlspecialchars($s['student_id']); ?></small></td>
                        <td style="padding:10px; text-align:right;"><form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Reactivate this student account?', '<?php echo htmlspecialchars($s['student_name'], ENT_QUOTES); ?>', '<?php echo $pic; ?>');"><input type="hidden" name="action" value="reactivate_user"><input type="hidden" name="user_type" value="student"><input type="hidden" name="user_id" value="<?php echo $s['student_id']; ?>"><button type="submit" class="admin-btn" style="background:#00ffaa; color:#0a1f16; font-size:11px; padding:5px 10px;">Reactivate</button></form></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endforeach; endif; ?>

        <h4 style="color:#00ccff; border-bottom:1px solid #00ccff; padding-bottom:10px; margin-top:40px;">Deactivated Teachers</h4>
        <div style="background:rgba(255,255,255,0.02); border-radius:10px; border:1px solid rgba(255,255,255,0.05); overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
            <?php
            $dt_res = $conn->query("SELECT * FROM teachers WHERE is_restricted = 1 AND restriction_end_date IS NULL ORDER BY name ASC");
            if($dt_res && $dt_res->num_rows > 0):
                while($t = $dt_res->fetch_assoc()):
                    $pic = !empty($t['profile_pic']) ? "uploads/".$t['profile_pic'] : "4icons8-teacher-50.png";
            ?>
                <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                    <td style="padding:10px; width:60px;"><img src="<?php echo $pic; ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:1px solid #555;"></td>
                    <td style="padding:10px;"><strong><?php echo htmlspecialchars($t['name']); ?></strong><br><small style="color:#aaa;"><?php echo htmlspecialchars($t['department']); ?></small></td>
                    <td style="padding:10px; text-align:right;"><form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Reactivate this teacher account?', '<?php echo htmlspecialchars($t['name'], ENT_QUOTES); ?>', '<?php echo $pic; ?>');"><input type="hidden" name="action" value="reactivate_user"><input type="hidden" name="user_type" value="teacher"><input type="hidden" name="user_id" value="<?php echo $t['id']; ?>"><button type="submit" class="admin-btn" style="background:#00ccff; color:#0a1f16; font-size:11px; padding:5px 10px;">Reactivate</button></form></td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="3" style="padding:20px; text-align:center; color:#888;">No deactivated teachers.</td></tr>
            <?php endif; ?>
            </table>
        </div>
    </div>

    <?php // Student Management Page
    elseif ($page === 'students'): ?>
    <div class="admin-section">
        <h3>Student Management (Monitor & Edit)</h3>
        <button class="admin-btn admin-btn-add" onclick="openAddStudentModal()" style="margin-bottom: 10px;">+ Add Student</button>
        
        <div style="margin-bottom: 15px;">
            <input type="text" id="studentManagementSearch" onkeyup="filterStudentManagement()" placeholder="Search student name, ID, or course..." style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #00ffaa; background: rgba(0,0,0,0.3); color: white; outline: none;">
        </div>

        <div style="max-height: 600px; overflow-y: auto; padding-right: 5px;">
            <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                <thead>
                    <tr style="border-bottom:1px solid #00ffaa; text-align:left;">
                        <th style="padding:10px;">ID</th>
                        <th style="padding:10px;">Name</th>
                        <th style="padding:10px;">Email</th>
                        <th style="padding:10px;">Course/Year</th>
                        <th style="padding:10px; text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $res = $conn->query("SELECT * FROM students WHERE is_alumni = 0 ORDER BY student_name");
                    if($res && $res->num_rows > 0):
                        while($row = $res->fetch_assoc()):
                            $pic = !empty($row['profile_pic']) ? "uploads/".$row['profile_pic'] : "assets/images/3icons8-student-64.png";
                            $is_restricted = $row['is_restricted'] == 1;
                            $restrict_text = $is_restricted ? "Restricted until " . date("M d, Y", strtotime($row['restriction_end_date'])) : "";
                    ?>
                    <tr class="student-management-row" style="border-bottom:1px solid rgba(255,255,255,0.1);">
                        <td style="padding:10px;"><?php echo htmlspecialchars($row['student_id']); ?></td>
                        <td style="padding:10px; font-weight:bold; display:flex; align-items:center; gap:10px;">
                            <img src="<?php echo $pic; ?>" style="width:30px; height:30px; border-radius:50%; object-fit:cover;">
                            <div><?php echo htmlspecialchars($row['student_name']); ?><?php if($is_restricted): ?><br><small style="color:#ff5555;">⚠️ <?php echo $restrict_text; ?></small><?php endif; ?></div>
                        </td>
                        <td style="padding:10px; color:#aaa;"><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                        <td style="padding:10px;">
                            <?php echo htmlspecialchars($row['course']); ?><br>
                            <small style="color:#00ffaa;"><?php echo htmlspecialchars($row['year_level']); ?></small>
                        </td>
                        <td style="padding:10px;">
                            <div style="display:flex; gap:5px; justify-content:flex-end;">
                                <button class="admin-btn admin-btn-add" style="padding:6px 12px; font-size:14px; background:#00ffaa; color:#0a1f16;" onclick='openViewPostsModal("<?php echo htmlspecialchars($row['student_name'], ENT_QUOTES); ?>")'>View Posts</button>
                                <button class="admin-btn admin-btn-add" style="padding:6px 12px; font-size:14px;" onclick='openStudentModal(<?php echo json_encode($row); ?>, "<?php echo $pic; ?>")'>Edit Info</button>
                                <button class="admin-btn" style="padding:6px 12px; font-size:14px; background:<?php echo $is_restricted ? '#ff5555' : '#e67e22'; ?>; color:white;" onclick='openRestrictModal("student", "<?php echo $row['student_id']; ?>", "<?php echo htmlspecialchars($row['student_name'], ENT_QUOTES); ?>", "students", <?php echo $is_restricted ? 1 : 0; ?>)'>Restrict</button>
                                <?php if(isset($row['is_alumni']) && $row['is_alumni'] == 1): ?>
                                <form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Revert this Alumni back to Student? They will be removed from Alumni list.');">
                                    <input type="hidden" name="action" value="revert_to_student">
                                    <input type="hidden" name="student_id" value="<?php echo $row['student_id']; ?>">
                                    <button type="submit" class="admin-btn" style="background:#00ccff; color:black; padding:6px 12px; font-size:14px;">Revert Student</button>
                                </form>
                                <?php else: ?>
                                <button class="admin-btn" style="background:#ffd700; color:black; padding:6px 12px; font-size:14px;" onclick='openMoveToAlumniModal(<?php echo json_encode($row); ?>, "<?php echo $pic; ?>")'>To Alumni</button>
                                <?php endif; ?>
                                <form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this student? This will remove all their data.', '<?php echo htmlspecialchars($row['student_name'], ENT_QUOTES); ?>', '<?php echo $pic; ?>');">
                                    <input type="hidden" name="action" value="delete_student">
                                    <input type="hidden" name="student_id" value="<?php echo $row['student_id']; ?>">
                                    <button type="submit" class="admin-btn admin-btn-delete">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php // Chat Monitoring Page
    elseif ($page === 'chats'): ?>

    <div class="admin-section">
        <h3>Student Chat Monitoring (Organized)</h3>
        
        <?php if(!isset($_SESSION['chat_pin_verified']) || $_SESSION['chat_pin_verified'] !== true): ?>
            <div class="pin-wrapper">
                <div class="pin-box">
                    <div class="lock-icon">🔒</div>
                    <h2>Security Check</h2>
                    <p>Enter PIN to access chat monitoring</p>
                    <?php if(isset($pin_error)) echo "<div class='pin-error'>$pin_error</div>"; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="verify_chat_pin">
                        <input type="password" name="pin" class="pin-input" placeholder="• • • • • •" maxlength="6" autofocus required>
                        <button type="submit" class="pin-btn">UNLOCK</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
        
        <div style="display: flex; gap: 20px; height: 400px;">
            
            <!-- Conversation List -->
            <div style="width: 30%; background: rgba(0,0,0,0.2); overflow-y: auto; border-right: 1px solid #00ffaa;">
                <h4 style="padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); margin:0; color:#00ffaa;">Conversations</h4>
                
                <!-- Search Bar -->
                <div style="padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <input type="text" id="adminChatSearch" onkeyup="filterAdminChats()" placeholder="Search name..." style="width: 100%; padding: 8px; border-radius: 5px; border: 1px solid #00ffaa; background: rgba(0,0,0,0.3); color: white; outline: none;">
                </div>

                <?php
                // Get unique pairs
                $pairs_sql = "SELECT DISTINCT LEAST(sender_id, receiver_id) as p1, GREATEST(sender_id, receiver_id) as p2 FROM direct_messages";

                $pairs = $conn->query($pairs_sql);
                if($pairs) while($p = $pairs->fetch_assoc()):
                    $u1_id = $p['p1'];
                    $u2_id = $p['p2'];
                    
                    // Fetch User 1
                    $u1_name = $u1_id;
                    $u1_pic = "assets/images/3icons8-student-64.png";
                    if(strpos($u1_id, 'T-') === 0){
                        $tid = substr($u1_id, 2);
                        $q = $conn->query("SELECT name, profile_pic FROM teachers WHERE id='$tid'");
                        if($q && $r = $q->fetch_assoc()){ $u1_name = $r['name']; if($r['profile_pic']) $u1_pic = "uploads/".$r['profile_pic']; else $u1_pic = "4icons8-teacher-50.png"; }
                    } else {
                        $q = $conn->query("SELECT student_name, profile_pic FROM students WHERE student_id='$u1_id'");
                        if($q && $r = $q->fetch_assoc()){ $u1_name = $r['student_name']; if($r['profile_pic']) $u1_pic = "uploads/".$r['profile_pic']; }
                    }

                    // Fetch User 2
                    $u2_name = $u2_id;
                    $u2_pic = "assets/images/3icons8-student-64.png";
                    if(strpos($u2_id, 'T-') === 0){
                        $tid = substr($u2_id, 2);
                        $q = $conn->query("SELECT name, profile_pic FROM teachers WHERE id='$tid'");
                        if($q && $r = $q->fetch_assoc()){ $u2_name = $r['name']; if($r['profile_pic']) $u2_pic = "uploads/".$r['profile_pic']; else $u2_pic = "4icons8-teacher-50.png"; }
                    } else {
                        $q = $conn->query("SELECT student_name, profile_pic FROM students WHERE student_id='$u2_id'");
                        if($q && $r = $q->fetch_assoc()){ $u2_name = $r['student_name']; if($r['profile_pic']) $u2_pic = "uploads/".$r['profile_pic']; }
                    }
                ?>
                <div class="admin-chat-item" style="padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); cursor: pointer;" onclick="location.href='SACLICONNECT2.php?page=chats&view_chat=1&p1=<?php echo $p['p1']; ?>&p2=<?php echo $p['p2']; ?>'">
                    <small style="color:#aaa;">Convo:</small><br>
                    <div style="display:flex; align-items:center; gap:8px; margin-top:5px;">
                        <div style="display:flex; align-items:center; gap:5px;">
                            <img src="<?php echo $u1_pic; ?>" style="width:30px; height:30px; border-radius:50%; object-fit:cover; border:1px solid #00ffaa;">
                            <span style="color:#ccffeb; font-size:12px; font-weight:bold;"><?php echo htmlspecialchars($u1_name); ?></span>
                        </div>
                        <span style="color:#00ffaa; font-weight:bold;">&</span>
                        <div style="display:flex; align-items:center; gap:5px;">
                            <img src="<?php echo $u2_pic; ?>" style="width:30px; height:30px; border-radius:50%; object-fit:cover; border:1px solid #00ffaa;">
                            <span style="color:#ccffeb; font-size:12px; font-weight:bold;"><?php echo htmlspecialchars($u2_name); ?></span>
                        </div>
                    </div>
                    <!-- Hidden span for search filter -->
                    <span class="chat-names" style="display:none;"><?php echo htmlspecialchars($u1_name . " " . $u2_name); ?></span>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- Message View -->
            <div style="width: 70%; background: rgba(0,0,0,0.2); overflow-y: auto; padding: 10px;">
                <?php
                if(isset($_GET['view_chat']) && isset($_GET['p1']) && isset($_GET['p2'])):
                    $p1 = $_GET['p1']; $p2 = $_GET['p2'];
                    ?>
                    <div style="display:flex; justify-content:flex-end; padding-bottom:10px; margin-bottom:10px; border-bottom:1px solid rgba(255,255,255,0.1);">
                        <form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this ENTIRE conversation? This cannot be undone.');">
                            <input type="hidden" name="action" value="admin_delete_convo">
                            <input type="hidden" name="p1" value="<?php echo htmlspecialchars($p1); ?>">
                            <input type="hidden" name="p2" value="<?php echo htmlspecialchars($p2); ?>">
                            <button type="submit" style="background: #ff0000; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold;">Delete Entire Conversation</button>
                        </form>
                    </div>
                    <?php
                    $msgs = $conn->query("SELECT dm.*, s.student_name FROM direct_messages dm LEFT JOIN students s ON dm.sender_id = s.student_id WHERE (sender_id='$p1' AND receiver_id='$p2') OR (sender_id='$p2' AND receiver_id='$p1') ORDER BY timestamp ASC");
                    while($m = $msgs->fetch_assoc()):
                ?>
                <div style="margin-bottom: 10px; padding: 8px; background: rgba(255,255,255,0.05); border-radius: 5px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: #00ffaa; font-size: 12px;"><?php echo htmlspecialchars($m['student_name']); ?></strong>
                        <span style="color: #aaa; font-size: 10px; margin-left: 5px;"><?php echo date("M d H:i", strtotime($m['timestamp'])); ?></span>
                        <div style="color: white; margin-top: 2px;"><?php echo htmlspecialchars($m['message']); ?></div>
                    </div>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action" value="admin_delete_msg">
                        <input type="hidden" name="msg_id" value="<?php echo $m['id']; ?>">
                        <button type="submit" style="background: #ff5555; color: white; border: none; padding: 2px 8px; border-radius: 3px; cursor: pointer; font-size: 10px;">Delete</button>
                    </form>
                </div>
                <?php endwhile; else: ?>
                    <p style="color: #aaa; text-align: center; margin-top: 50px;">Select a conversation from the left to view messages.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php // Student Concerns Page
    elseif ($page === 'concerns'): ?>
    <div class="admin-section">
        <h3>Student Concerns</h3>
        
        <div style="display: flex; gap: 20px; height: 500px;">
            
            <!-- Conversation List -->
            <div style="width: 35%; background: rgba(0,0,0,0.2); overflow-y: auto; border-right: 1px solid #00ffaa;">
                <h4 style="padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); margin:0; color:#00ffaa;">Concern Threads</h4>
                <?php
                // Get unique student threads, ordered by latest message, with unread count
                $threads_sql = "SELECT 
                                    ac.student_id, 
                                    s.student_name, 
                                    s.profile_pic,
                                    (SELECT COUNT(*) FROM admin_concerns WHERE student_id = ac.student_id AND is_read = 0 AND sender_type = 'student') as unread_count,
                                    (SELECT MAX(timestamp) FROM admin_concerns WHERE student_id = ac.student_id) as latest_ts
                                FROM admin_concerns ac 
                                JOIN students s ON ac.student_id = s.student_id 
                                GROUP BY ac.student_id, s.student_name, s.profile_pic
                                ORDER BY latest_ts DESC";

                $threads = $conn->query($threads_sql);
                if($threads && $threads->num_rows > 0):
                    while($th = $threads->fetch_assoc()):
                        $pic = !empty($th['profile_pic']) ? "uploads/".$th['profile_pic'] : "assets/images/3icons8-student-64.png";
                        $active_class = (isset($_GET['view_concern']) && $_GET['view_concern'] == $th['student_id']) ? 'style="background:rgba(0,255,170,0.1);"' : '';
                ?>
                <div class="admin-chat-item" <?php echo $active_class; ?> style="padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); cursor: pointer; display:flex; align-items:center; gap:10px;" onclick="location.href='SACLICONNECT2.php?page=concerns&view_concern=<?php echo $th['student_id']; ?>'">
                    <img src="<?php echo $pic; ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                    <div style="flex:1;">
                        <strong style="color:#ccffeb;"><?php echo htmlspecialchars($th['student_name']); ?></strong>
                    </div>
                    <?php if($th['unread_count'] > 0): ?>
                        <span style="background:#e41e3f; color:white; font-size:10px; font-weight:bold; padding:2px 6px; border-radius:10px;"><?php echo $th['unread_count']; ?></span>
                    <?php endif; ?>
                </div>
                <?php endwhile; else: ?>
                    <p style="color:#888; text-align:center; padding:20px;">No concerns yet.</p>
                <?php endif; ?>
            </div>

            <!-- Message View -->
            <div style="width: 65%; background: rgba(0,0,0,0.2); display:flex; flex-direction:column;">
                <?php
                if(isset($_GET['view_concern'])):
                    $student_id_to_view = $_GET['view_concern'];
                    
                    // Mark as read
                    $conn->query("UPDATE admin_concerns SET is_read = 1 WHERE student_id = '$student_id_to_view' AND sender_type = 'student'");
                    
                    $student_info = $conn->query("SELECT student_name FROM students WHERE student_id='$student_id_to_view'")->fetch_assoc();
                ?>
                    <div style="padding:10px; border-bottom:1px solid #00ffaa; color:#fff; font-weight:bold;">
                        Conversation with <?php echo htmlspecialchars($student_info['student_name']); ?>
                    </div>
                    <div style="flex:1; overflow-y: auto; padding: 10px; display:flex; flex-direction:column; gap:8px;">
                    <?php
                    $msgs = $conn->query("SELECT * FROM admin_concerns WHERE student_id='$student_id_to_view' ORDER BY timestamp ASC");
                    while($m = $msgs->fetch_assoc()):
                        $align = ($m['sender_type'] == 'admin') ? 'align-self:flex-end;' : 'align-self:flex-start;';
                        $color = ($m['sender_type'] == 'admin') ? 'background:#00ffaa; color:#0a1f16;' : 'background:rgba(255,255,255,0.1); color:white;';
                    ?>
                    <div class="msg" style="padding: 8px 25px 8px 12px; border-radius: 15px; max-width: 75%; font-size: 14px; word-wrap: break-word; position: relative; <?php echo $align . $color; ?>">
                        <?php echo htmlspecialchars($m['message']); ?>
                        <form method="POST" style="position: absolute; top: 2px; right: 5px; margin: 0;" onsubmit="return confirmFormSubmit(event, 'Delete this message?');">
                            <input type="hidden" name="action" value="delete_concern_msg">
                            <input type="hidden" name="msg_id" value="<?php echo $m['id']; ?>">
                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id_to_view); ?>">
                            <button type="submit" style="background:none; border:none; color:inherit; opacity:0.6; cursor:pointer; font-size:16px; padding:0; line-height:1;" title="Delete">&times;</button>
                        </form>
                    </div>
                    <?php endwhile; ?>
                    </div>
                    <div style="padding:10px; border-top:1px solid #00ffaa;">
                        <form method="POST" style="display:flex; gap:10px;">
                            <input type="hidden" name="action" value="reply_concern">
                            <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id_to_view); ?>">
                            <input type="text" name="message" placeholder="Type your reply..." style="flex:1; padding:10px; border-radius:20px; border:1px solid #00ffaa; background:rgba(0,0,0,0.3); color:white; outline:none;" required>
                            <button type="submit" class="admin-btn admin-btn-save" style="border-radius:20px;">Send</button>
                        </form>
                    </div>
                <?php else: ?>
                    <p style="color: #aaa; text-align: center; margin: auto;">Select a conversation from the left to view concerns.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php // Passwords Page
    elseif ($page === 'passwords'): ?>
    <div class="admin-section">
        <h3>Registered Accounts & Passwords</h3>
        
        <?php if(!isset($_SESSION['pass_pin_verified']) || $_SESSION['pass_pin_verified'] !== true): ?>
            <div class="pin-wrapper">
                <div class="pin-box">
                    <div class="lock-icon">🔒</div>
                    <h2>Security Check</h2>
                    <p>Enter PIN to view passwords</p>
                    <?php if(isset($pass_pin_error)) echo "<div class='pin-error'>$pass_pin_error</div>"; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="verify_pass_pin">
                        <input type="password" name="pin" class="pin-input" placeholder="• • • • • •" maxlength="6" autofocus required>
                        <button type="submit" class="pin-btn">UNLOCK</button>
                    </form>
                </div>
            </div>
            <!-- Reuse styles from chats page -->
        <?php else: ?>
        <div style="max-height: 600px; overflow-y: auto; padding-right: 5px;">
            
            <h4 style="color:#ffd700; border-bottom:1px solid #ffd700; padding-bottom:10px; margin-top:0;">Password Change Requests</h4>
            <div style="margin-bottom: 30px; max-height: 250px; overflow-y: auto;">
                <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                    <tbody>
                        <?php
                        $admin_user = $_SESSION['admin_username'];
                        $req_sql = "SELECT pcr.*, s.student_name FROM password_change_requests pcr LEFT JOIN students s ON pcr.student_id = s.student_id WHERE pcr.admin_username = ? ORDER BY pcr.timestamp DESC";
                        $req_stmt = $conn->prepare($req_sql);
                        $req_stmt->bind_param("s", $admin_user);
                        $req_stmt->execute();
                        $requests = $req_stmt->get_result();
                        if($requests->num_rows > 0):
                            while($req = $requests->fetch_assoc()):
                                $status_color = 'orange';
                                if($req['status'] == 'approved') $status_color = '#00ffaa';
                                if($req['status'] == 'denied') $status_color = '#ff5555';
                        ?>
                        <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                            <td style="padding:10px;">
                                <strong><?php echo htmlspecialchars($req['student_name'] ?? 'N/A'); ?></strong><br>
                                <small style="color:#aaa;"><?php echo date("M d, Y H:i", strtotime($req['timestamp'])); ?></small>
                                <?php if($req['status'] == 'approved' && !empty($req['approved_at'])): ?>
                                    <br><small style="color:#00ffaa;">Approved: <?php echo date("M d H:i", strtotime($req['approved_at'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px; text-align:center;">
                                <span style="color: <?php echo $status_color; ?>; font-weight:bold; text-transform:uppercase; font-size:12px;"><?php echo htmlspecialchars($req['status']); ?></span>
                            </td>
                            <td style="padding:10px; text-align:right;">
                                <?php if($req['status'] != 'pending'): ?>
                                <form method="POST" style="margin:0;" onsubmit="return confirmFormSubmit(event, 'Clear this request from the list?');"><input type="hidden" name="action" value="clear_pass_request"><input type="hidden" name="request_id" value="<?php echo $req['id']; ?>"><button type="submit" class="admin-btn" style="padding:2px 8px; font-size:11px; background:rgba(255,255,255,0.1); color:#aaa;">Clear</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="3" style="padding:15px; text-align:center; color:#888;">No pending or recent requests.</td></tr>
                        <?php endif; $req_stmt->close(); ?>
                    </tbody>
                </table>
            </div>

            <!-- Search Bar for all tables on this page -->
            <div style="margin-bottom: 20px;">
                <input type="text" id="passwordSearch" onkeyup="filterPasswords()" placeholder="Search name or ID..." style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #00ffaa; background: rgba(0,0,0,0.3); color: white; outline: none;">
            </div>
            
            <h4 style="color:#00ffaa; border-bottom:1px solid #00ffaa; padding-bottom:10px;">Admin Accounts</h4>
            <div style="margin-bottom: 30px;">
                <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                    <tbody>
                        <?php 
                        $admins = $conn->query("SELECT * FROM admins2");
                        if($admins) while($a = $admins->fetch_assoc()): 
                        ?>
                        <tr class="admin-pass-row" style="border-bottom:1px solid rgba(255,255,255,0.05);">
                            <td style="padding:10px;">
                                <strong><?php echo htmlspecialchars($a['username']); ?></strong><br>
                              
                                <small style="font-family:monospace; color:#fff; word-break:break-all;"><?php echo htmlspecialchars($a['password']); ?></small>
                            </td>
                            <td style="padding:10px; text-align:right; vertical-align: middle;">
                                <button class="admin-btn" style="padding:2px 8px; font-size:11px; background:#00ffaa; color:#000;" onclick="changeAdminPass(<?php echo $a['id']; ?>)">Change Password</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <h4 style="color:#00ffaa; border-bottom:1px solid #00ffaa; padding-bottom:10px;">Student Credentials</h4>
            <div>
                <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                    <tbody>
                        <?php 
                        $stds = $conn->query("SELECT student_name, student_id, password, profile_pic FROM students WHERE is_alumni = 0 ORDER BY student_name");
                        if($stds) while($s = $stds->fetch_assoc()): 
                            $pic = !empty($s['profile_pic']) ? "uploads/".$s['profile_pic'] : "assets/images/3icons8-student-64.png";
       
                        ?>
                        <tr class="student-pass-row" style="border-bottom:1px solid rgba(255,255,255,0.05);">
                            <td style="padding:10px; display:flex; align-items:center; gap:15px;">
                                <img src="<?php echo $pic; ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover; border: 2px solid #00ffaa;">
                                <div>
                                    <strong><?php echo htmlspecialchars($s['student_name']); ?></strong><br>
                                   
                                    <small style="font-family:monospace; color:#fff; word-break: break-all;"><?php echo htmlspecialchars(!empty($s['password']) ? $s['password'] : $s['student_id']); ?></small>
                                </div>
                            </td>
                            <td style="padding:10px; text-align:right; vertical-align: middle;">
                                <button class="admin-btn" style="padding:2px 8px; font-size:11px; background:#00ffaa; color:#000;" onclick="changeStudentPassword('<?php echo $s['student_id']; ?>', '<?php echo htmlspecialchars($s['student_name'], ENT_QUOTES); ?>', '<?php echo $pic; ?>')">Change Password</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <h4 style="color:#00ffaa; border-bottom:1px solid #00ffaa; padding-bottom:10px; margin-top:30px;">Alumni Credentials</h4>
            <div>
                <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                    <tbody>
                        <?php 
                        $alums = $conn->query("SELECT student_name, student_id, password, profile_pic FROM students WHERE is_alumni = 1 ORDER BY student_name");
                        if($alums) while($al = $alums->fetch_assoc()): 
                            $pic = !empty($al['profile_pic']) ? "uploads/".$al['profile_pic'] : "assets/images/3icons8-student-64.png";

                        ?>
                        <tr class="alumni-pass-row" style="border-bottom:1px solid rgba(255,255,255,0.05);">
                            <td style="padding:10px; display:flex; align-items:center; gap:15px;">
                                <img src="<?php echo $pic; ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover; border: 2px solid #00ffaa;">
                                <div>
                                    <strong><?php echo htmlspecialchars($al['student_name']); ?></strong><br>
                                   
                                    <small style="font-family:monospace; color:#fff; word-break: break-all;"><?php echo htmlspecialchars(!empty($al['password']) ? $al['password'] : $al['student_id']); ?></small>
                                </div>
                            </td>
                            <td style="padding:10px; text-align:right; vertical-align: middle;">
                                <button class="admin-btn" style="padding:2px 8px; font-size:11px; background:#00ffaa; color:#000;" onclick="changeStudentPassword('<?php echo $al['student_id']; ?>', '<?php echo htmlspecialchars($al['student_name'], ENT_QUOTES); ?>', '<?php echo $pic; ?>')">Change Password</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <h4 style="color:#00ffaa; border-bottom:1px solid #00ffaa; padding-bottom:10px; margin-top:30px;">Teacher Credentials</h4>
            <div>
                <table style="width:100%; border-collapse:collapse; color:#ccffeb;">
                    <tbody>
                        <?php 
                        $techs = $conn->query("SELECT id, name, password, profile_pic FROM teachers ORDER BY name");
                        if($techs) while($t = $techs->fetch_assoc()): 
                            $pic = !empty($t['profile_pic']) ? "uploads/".$t['profile_pic'] : "4icons8-teacher-50.png";
                        ?>
                        <tr class="teacher-pass-row" style="border-bottom:1px solid rgba(255,255,255,0.05);">
                            <td style="padding:10px; display:flex; align-items:center; gap:15px;">
                                <img src="<?php echo $pic; ?>" style="width:50px; height:50px; border-radius:50%; object-fit:cover; border: 2px solid #00ffaa;">
                                <div>
                                    <strong><?php echo htmlspecialchars($t['name']); ?></strong><br>
                                    <small style="font-family:monospace; color:#fff; word-break: break-all;"><?php echo htmlspecialchars($t['password']); ?></small>
                                </div>
                            </td>
                            <td style="padding:10px; text-align:right; vertical-align: middle;">
                                <button class="admin-btn" style="padding:2px 8px; font-size:11px; background:#00ffaa; color:#000;" onclick="changeTeacherPass(<?php echo $t['id']; ?>)">Change Password</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php // Signup Management Page
    elseif ($page === 'signup_manage'): ?>
    <div class="admin-section">
        <h3>Sign Up System Management</h3>
        
        <?php
        $signup_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='signup_enabled'");
        $signup_enabled = ($signup_res && $signup_res->num_rows > 0 && $signup_res->fetch_assoc()['setting_value'] == '1');
        ?>
        
        <div style="background:rgba(0,0,0,0.2); padding:40px; border-radius:15px; border:1px solid <?php echo $signup_enabled ? '#00ffaa' : '#ff5555'; ?>; text-align:center;">
            <div style="font-size:60px; margin-bottom:20px;"><?php echo $signup_enabled ? '🔓' : '🔒'; ?></div>
            <h2 style="color:<?php echo $signup_enabled ? '#00ffaa' : '#ff5555'; ?>; margin-bottom:15px; font-size:28px;">Registration is <?php echo $signup_enabled ? 'OPEN' : 'LOCKED'; ?></h2>
            <p style="color:#aaa; margin-bottom:40px; font-size:16px; line-height:1.6;">
                <?php echo $signup_enabled ? 'New students and teachers can currently register for accounts via the public sign up page.' : 'The sign up form is currently disabled. New users cannot register themselves until you unlock it.'; ?>
            </p>
            
            <form method="POST" action="SACLICONNECT2.php" style="margin:0;">
                <input type="hidden" name="action" value="toggle_signup">
                <input type="hidden" name="status" value="<?php echo $signup_enabled ? '0' : '1'; ?>">
                <button type="submit" class="admin-btn" style="background:<?php echo $signup_enabled ? '#ff5555' : '#00ffaa'; ?>; color:<?php echo $signup_enabled ? '#fff' : '#0a1f16'; ?>; font-weight:900; padding: 15px 40px; font-size:16px;">
                    <?php echo $signup_enabled ? 'DISABLE SIGN UP SYSTEM' : 'ENABLE SIGN UP SYSTEM'; ?>
                </button>
            </form>
        </div>

        <div style="margin-top:40px; padding:25px; background:rgba(255,255,255,0.03); border-radius:15px; border-left:4px solid #00ffaa;">
            <h4 style="color:#fff; margin-top:0;">Protocol Information:</h4>
            <p style="color:#ccc; font-size:14px; line-height:1.6;">
                Disabling the sign-up system is a security measure typically used during system maintenance, enrollment breaks, or to prevent unauthorized account creation. Existing users will still be able to log in and access all features normally.
            </p>
        </div>
    </div>

    <?php // Chat Themes Management Page
    elseif ($page === 'chat_themes'): ?>
    <div class="admin-section">
        <h3>Chat Themes Management</h3>
        <p style="color:#aaa; margin-top:-15px; margin-bottom:25px;">Preview and manage the available themes for the Messenger system.</p>
        
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap:20px;">
            <?php
            $themes = [
                ['id' => 'default', 'name' => 'Default Neural', 'desc' => 'Standard dark green glassmorphism interface.'],
                ['id' => 'flashlight', 'name' => 'Flashlight Dark', 'desc' => 'Dynamic horror-style spotlight effect.'],
                ['id' => 'space', 'name' => 'Celestial Space', 'desc' => 'Deep space background with scrolling parallax stars.'],
                ['id' => 'rain', 'name' => 'Stormy Rain', 'desc' => 'Realistic rain animation over a solid black node.'],
                ['id' => 'midnight', 'name' => 'Midnight Sky', 'desc' => 'Night blue gradient with twinkling stars and shooting meteors.'],
                ['id' => 'geometric', 'name' => 'Night Geometric', 'desc' => 'Moving isometric patterns in deep obsidian shades.']
            ];
            
            foreach($themes as $t):
                $usage_q = $conn->query("SELECT COUNT(*) as c FROM (SELECT theme FROM direct_chat_themes WHERE theme='".$t['id']."' UNION ALL SELECT theme FROM group_chats WHERE theme='".$t['id']."') as combined");
                $usage = $usage_q ? $usage_q->fetch_assoc()['c'] : 0;
            ?>
            <div class="theme-opt-label theme-opt-<?php echo $t['id']; ?>" style="flex-direction:column; align-items:flex-start; height:auto; padding:25px;">
                <div style="display:flex; justify-content:space-between; width:100%; align-items:center; margin-bottom:15px;">
                    <span style="font-size:18px;"><?php echo $t['name']; ?></span>
                    <div class="live-badge" style="background:#00ccff; color:#000; box-shadow:0 0 10px rgba(0,204,255,0.4);"><?php echo $usage; ?> active</div>
                </div>
                <p style="color:#aaa; font-size:12px; line-height:1.5; margin:0 0 20px 0; font-family:var(--terminal-font); text-transform: none; letter-spacing: 0;">
                    // <?php echo $t['desc']; ?>
                </p>
                <div style="display:flex; gap:10px; width:100%;">
                    <button class="admin-btn admin-btn-save" style="flex:1; padding:8px; font-size:10px;" onclick="window.location.href='SacliChat_Full.php'">Test Theme</button>
                    <button class="admin-btn admin-btn-add" style="flex:1; padding:8px; font-size:10px; background:#ffd700; color:#000;" onclick="showCustomAlert('Global theme enforcement is currently handled by individual user selection.')">Config</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:40px; padding:25px; background:rgba(255,255,255,0.03); border-radius:15px; border-left:4px solid #00ccff;">
            <h4 style="color:#fff; margin-top:0;">Optimization Protocol:</h4>
            <p style="color:#ccc; font-size:14px; line-height:1.6;">
                Each theme uses hardware-accelerated CSS animations. The "active" count represents kung ilang individual direct chats at group chats ang kasalukuyang gumagamit ng style na ito.
            </p>
        </div>
    </div>
    <?php endif; // End of page router ?>

    <?php if($page === 'main'): // Continue showing these only on main page ?>
    <div class="admin-section">
        <h3>Recent Announcements (delete)</h3>
        <div style="max-height: 70vh; overflow-y: auto; padding-right: 5px;">
        <?php
        $posts = $conn->query("SELECT * FROM posts ORDER BY timestamp DESC");
        if ($posts && $posts->num_rows > 0):
            while ($post = $posts->fetch_assoc()):
        ?>
        <div class="admin-list-item">
            <div style="flex:1;">
                <strong><?php echo htmlspecialchars($post['student_name']); ?></strong>
                <span class="time"><?php echo date("M d, Y H:i", strtotime($post['timestamp'])); ?></span>
                <p style="margin:8px 0 0; color:#ccffeb; word-break: break-word;"><?php echo htmlspecialchars($post['content']); ?></p>
            </div>
            <form method="POST" action="../handlers/delete_post.php" style="margin:0;">
                <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
                <button type="submit" class="admin-btn admin-btn-delete">Delete</button>
            </form>
        </div>
        <?php
            endwhile;
        else:
            echo '<p style="color:#66ffd9;">Walang posts pa.</p>';
        endif;
        ?>
        </div>
    </div>
    <?php endif; // End of main page specific sections ?>
<?php if (!isset($_GET['ajax_content'])): ?>
</div>

<div class="right-sidebar">
    <div style="display:flex; justify-content:flex-end; margin-bottom:20px;">
        <button class="Btn1" onclick="showLogoutModal(); return false;">
            <div class="sign1">
                <svg viewBox="0 0 512 512">
                    <path d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0 75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"></path>
                </svg>
            </div>
            <div class="text">Logout</div>
        </button>
    </div>
    <div id="realtime-clock" style="color: #00ffaa; font-family: 'Courier New', Courier, monospace; font-size: 16px; font-weight: bold; background: rgba(0,0,0,0.2); padding: 8px 12px; border-radius: 5px; border: 1px solid rgba(0,255,170,0.1); text-align:center; margin-bottom:20px;"></div>
    <h3>Quick Links</h3>

    <!-- Admin Profile Section -->
    <div class="gc" onclick="openAdminProfileModal()" style="cursor:pointer; border: 1px solid #00ffaa; background: rgba(0, 255, 170, 0.05); flex-direction: column; align-items: flex-start; padding: 12px 15px; height: auto; margin-bottom: 10px;">
        <div style="display: flex; align-items: center; gap: 10px; width: 100%;">
            <img src="<?php echo $admin_pic; ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid #00ffaa;">
            <span style="color: #fff; font-weight: bold; font-size: 13px;">Admin Profile</span>
        </div>
        <div style="font-size: 10px; color: #00ffaa; opacity: 0.8; margin-top: 5px; font-family: monospace; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; width: 100%;">
            <?php echo htmlspecialchars($admin_data['email'] ?? 'admin@sacli.edu.ph'); ?>
        </div>
    </div>

    <?php
    // Check for unread concerns
    $uc_query = $conn->query("SELECT COUNT(*) as total FROM admin_concerns WHERE sender_type='student' AND is_read=0");
    $uc_count = $uc_query ? $uc_query->fetch_assoc()['total'] : 0;
    ?>
    <div class="gc" onclick="location.href='SACLICONNECT2.php?page=concerns'" style="cursor:pointer;">
        <span>Student Concerns</span>
        <?php if($uc_count > 0): ?>
            <div style="background:#ff5555; color:white; font-size:10px; font-weight:bold; padding:2px 6px; border-radius:10px; margin-left:auto; box-shadow: 0 0 5px #ff5555;">
                <?php echo $uc_count; ?> New
            </div>
        <?php endif; ?>
    </div>
    <div class="gc" onclick="location.href='admin_student_id_list.php'" style="cursor:pointer; border: 1px solid #00ccff; background: rgba(0, 204, 255, 0.05);">
        <span style="color: #00ccff;">Student ID list</span>
    </div>
    <div class="gc" onclick="location.href='SACLICONNECT2.php?page=evaluation_results'" style="cursor:pointer;">
        <span>Evaluation Results</span>
    </div>
    <div class="gc" onclick="location.href='SACLICONNECT2.php?page=signup_manage'" style="cursor:pointer; border: 1px solid #00ffaa; background: rgba(0, 255, 170, 0.05);">
        <span style="color: #00ffaa;">Sacli Sign Up</span>
    </div>
    <div class="gc" onclick="openDeactivateAccountModal()" style="cursor:pointer; border: 1px solid #ff5555; background: rgba(255, 85, 85, 0.1);">
        <span style="color: #ff5555;">Deactivate Account</span>
    </div>
</div>

<div class="modal" id="logoutModal">
    <div class="modal-content logout-modal-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout?</p>
        <div class="logout-buttons">
            <button class="logout-confirm-btn" onclick="confirmLogout()">Yes, Logout</button>
            <button class="logout-cancel-btn" onclick="closeLogoutModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- Custom Confirm Modal -->
<div class="modal" id="customConfirmModal" style="z-index: 20001;">
    <div class="modal-content" style="max-width: 400px; padding: 20px; text-align: center; background: #102e22; border: 1px solid #00ffaa;">
        <h3 style="color: #00ffaa; margin-bottom: 15px;">Confirmation</h3>
        <div id="confirmUserInfo" style="display:none; margin-bottom: 15px;">
            <img id="confirmUserImg" src="" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 2px solid #00ffaa; margin-bottom: 10px;">
            <h4 id="confirmUserName" style="color: #fff; margin: 0;"></h4>
        </div>
        <p id="customConfirmText" style="color: #e4e6eb; margin-bottom: 25px; font-size: 16px;"></p>
        <div style="display: flex; gap: 15px; justify-content: center;">
            <button id="confirmYesBtn" style="background: #ff5555; color: white; border: none; padding: 10px 25px; border-radius: 20px; font-weight: bold; cursor: pointer; transition: 0.3s;">Yes</button>
            <button onclick="closeCustomConfirm()" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 10px 25px; border-radius: 20px; font-weight: bold; cursor: pointer; transition: 0.3s;">Cancel</button>
        </div>
    </div>
</div>

<!-- Custom Prompt Modal -->
<div class="modal" id="customPromptModal" style="z-index: 20002;">
    <div class="modal-content" style="max-width: 400px; padding: 20px; text-align: center; background: #102e22; border: 1px solid #00ffaa;">
        <h3 style="color: #00ffaa; margin-bottom: 15px;">Update Credential</h3>
        <p id="customPromptText" style="color: #e4e6eb; margin-bottom: 15px; font-size: 16px;"></p>
        <input type="text" id="customPromptInput" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #00ffaa; background: #1a3d2f; color: white; margin-bottom: 20px; outline: none; text-align: center;">
        <div style="display: flex; gap: 15px; justify-content: center;">
            <button id="promptOkBtn" style="background: #00ffaa; color: #0a1f16; border: none; padding: 10px 25px; border-radius: 20px; font-weight: bold; cursor: pointer; transition: 0.3s;">Save</button>
            <button onclick="closeCustomPrompt()" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 10px 25px; border-radius: 20px; font-weight: bold; cursor: pointer; transition: 0.3s;">Cancel</button>
        </div>
        <form id="promptForm" method="POST" style="display:none;"></form>
    </div>
</div>

<!-- Custom Alert Modal -->
<div class="modal" id="customAlertModal" style="z-index: 20003;">
    <div class="modal-content" style="max-width: 400px; padding: 20px; text-align: center; background: #102e22; border: 1px solid #00ffaa;">
        <h3 style="color: #00ffaa; margin-bottom: 15px;">Notification</h3>
        <p id="customAlertText" style="color: #e4e6eb; margin-bottom: 25px; font-size: 16px;"></p>
        <button id="alertOkBtn" style="background: #00ffaa; color: #0a1f16; border: none; padding: 10px 25px; border-radius: 20px; font-weight: bold; cursor: pointer; transition: 0.3s;">OK</button>
    </div>
</div>

<!-- Add Student Modal -->
<div class="modal" id="addStudentModal">
    <div class="modal-content" style="background: #102e22; border: 1px solid #00ffaa;">
        <span class="close" onclick="closeAddStudentModal()">&times;</span>
        <h3 style="color:#00ffaa;">Add New Member</h3>
        
        <div style="display:flex; justify-content:center; gap:15px; margin-bottom:20px;">
            <button type="button" id="toggleStudentBtn" onclick="setAddMode('student')" style="padding:8px 20px; border-radius:20px; border:1px solid #00ffaa; background:#00ffaa; color:#0a1f16; font-weight:bold; cursor:pointer;">Student</button>
            <button type="button" id="toggleTeacherBtn" onclick="setAddMode('teacher')" style="padding:8px 20px; border-radius:20px; border:1px solid #00ffaa; background:transparent; color:#00ffaa; font-weight:bold; cursor:pointer;">Teacher</button>
        </div>

        <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data" id="addMemberForm">
            <input type="hidden" name="action" id="addMemberAction" value="add_student">
            
            <div style="text-align:center; margin-bottom:15px;">
                <div style="width:100px; height:100px; border-radius:50%; border:2px solid #00ffaa; margin:0 auto; overflow:hidden; cursor:pointer;" onclick="document.getElementById('add_pic').click()">
                    <img id="add_preview" src="assets/images/3icons8-student-64.png" style="width:100%; height:100%; object-fit:cover;">
                </div>
                <input type="file" name="profile_pic" id="add_pic" style="display:none;" accept="image/*" onchange="previewAddImage(this)">
                <small style="color:#aaa;">Click to upload photo</small>
            </div>

            <!-- Student Fields -->
            <div id="studentFields">
                <label style="color:#ccffeb;">Student ID Number</label>
                <input type="text" name="student_id" id="add_student_id" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                
                <label style="color:#ccffeb;">Set Password</label>
                <input type="password" name="password" id="add_student_password" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                <label style="color:#ccffeb;">Full Name</label>
                <input type="text" name="student_name" id="add_student_name" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                
                <label style="color:#ccffeb;">Email Address</label>
                <input type="email" name="email" placeholder="student@example.com" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                
                <label style="color:#ccffeb;">Contact Number</label>
                <input type="text" name="phone" placeholder="e.g., 09123456789" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                <label style="color:#ccffeb;">Course</label>
                <select name="course" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                    <option value="" selected>Select Course (Optional)</option>
                    <option value="" disabled selected>Select Course</option>
                    <option value="BS Nursing">BS Nursing</option>
                    <option value="BS Physical Therapy">BS Physical Therapy</option>
                    <option value="BS Medical Technology">BS Medical Technology</option>
                    <option value="BS Midwifery">BS Midwifery</option>
                    <option value="Doctor of Dental Medicine">Doctor of Dental Medicine</option>
                    <option value="BS Hospitality Management">BS Hospitality Management</option>
                    <option value="BS Tourism Management">BS Tourism Management</option>
                    <option value="BS Criminology">BS Criminology</option>
                    <option value="BS Accountancy">BS Accountancy</option>
                    <option value="BS Management Accounting">BS Management Accounting</option>
                    <option value="BS Business Administration">BS Business Administration</option>
                    <option value="BS Information Technology">BS Information Technology</option>
                    <option value="BS Civil Engineering">BS Civil Engineering</option>
                    <option value="BS Industrial Engineering">BS Industrial Engineering</option>
                    <option value="AB Communication">AB Communication</option>
                    <option value="AB Psychology">AB Psychology</option>
                </select>
                
                <label style="color:#ccffeb;">Year Level</label>
                <select name="year_level" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                    <option value="" selected>Select Year Level (Optional)</option>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                </select>
            </div>

            <!-- Teacher Fields -->
            <div id="teacherFields" style="display:none;">
                <label style="color:#ccffeb;">Name</label>
                <input type="text" name="name" id="add_teacher_name" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;" disabled>
                
                <label style="color:#ccffeb;">Department</label>
                <select name="department" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;" disabled>
                    <option value="Elementary Department">Elementary</option>
                    <option value="Junior High School">Junior High School</option>
                    <option value="Senior High School">Senior High School</option>
                    <option value="College Department">College</option>
                </select>
                
                <label style="color:#ccffeb;">Position</label>
                <input type="text" name="position" placeholder="e.g. Adviser, Dean" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;" disabled>
                
                <label style="color:#ccffeb;">Email</label>
                <input type="email" name="email" placeholder="Email Address" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;" disabled>

                <label style="color:#ccffeb;">Contact Number</label>
                <input type="text" name="phone" placeholder="e.g., 09123456789" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;" disabled>

                <label style="color:#ccffeb;">Set Password</label>
                <input type="password" name="password" placeholder="Create Password" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;" disabled>
            </div>
            
            <button type="submit" class="admin-btn admin-btn-save" style="width:100%;" id="addMemberSubmitBtn">Add Student</button>
        </form>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal" id="studentModal">
    <div class="modal-content student-edit-modal" style="background: #102e22; border: 1px solid #00ffaa; max-width: 850px; width: 95%;">
        <span class="close" onclick="closeStudentModal()">&times;</span>
        <h3 style="color:#00ffaa; margin-bottom: 25px; text-align: center;">Edit Student Info</h3>
        
        <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data">
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: flex-start;">
                
                <!-- Left Column: Profile Picture and Privacy -->
                <div class="profile-pic-upload-section" style="text-align: center; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 15px; border: 1px solid rgba(0,255,170,0.1);">
                    <div style="position: relative; width: 140px; height: 140px; margin: 0 auto 15px; border-radius: 50%; overflow: hidden; border: 3px solid #00ffaa; box-shadow: 0 0 25px rgba(0,255,170,0.3); cursor: pointer;" onclick="document.getElementById('edit_profile_pic_input').click()">
                        <img id="edit_student_pic" src="" style="width: 100%; height: 100%; object-fit: cover;">
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); color: #00ffaa; padding: 5px; font-size: 12px; font-weight: bold;">Change Photo</div>
                    </div>
                    <input type="file" name="profile_pic" id="edit_profile_pic_input" accept="image/*" style="display: none;" onchange="previewEditStudentImage(this)">
                    
                    <div class="privacy-locks-section" style="background: rgba(0,255,170,0.05); padding: 20px; border-radius: 15px; border: 1px solid rgba(0,255,170,0.1); text-align: left; margin-top: 20px;">
                         <label class="form-label" style="color:#fff; margin-bottom: 15px; font-family: 'Orbitron', sans-serif; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">PRIVACY_LOCKS</label>
                         <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
                            <span style="font-size:14px; color:#b0fce0;">Hide Phone Number</span>
                            <label class="switch">
                                <input type="checkbox" name="hide_phone" id="edit_hide_phone">
                                <span class="slider round"></span>
                            </label>
                        </div>
                        <small style="color:#888; font-size:11px;">If checked, contact number will not be visible to other users.</small>
                    </div>
                </div>

                <!-- Right Column: Info Fields -->
                <div style="flex: 1; min-width: 400px;">
                    <input type="hidden" name="action" value="update_student">
                    <input type="hidden" name="original_student_id" id="edit_original_id">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Student ID Number</label>
                            <input type="text" name="student_id" id="edit_id" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Set New Password</label>
                            <input type="password" name="new_password" id="edit_new_password" class="form-input" placeholder="Leave blank to keep current">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="student_name" id="edit_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" id="edit_email" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="phone" id="edit_phone" class="form-input" placeholder="e.g., 09123456789">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Course</label>
                            <select name="course" id="edit_course" class="form-input">
                                <option value="" disabled>Select Course</option>
                                <option value="BS Nursing">BS Nursing</option>
                                <option value="BS Physical Therapy">BS Physical Therapy</option>
                                <option value="BS Medical Technology">BS Medical Technology</option>
                                <option value="BS Midwifery">BS Midwifery</option>
                                <option value="Doctor of Dental Medicine">Doctor of Dental Medicine</option>
                                <option value="BS Hospitality Management">BS Hospitality Management</option>
                                <option value="BS Tourism Management">BS Tourism Management</option>
                                <option value="BS Criminology">BS Criminology</option>
                                <option value="BS Accountancy">BS Accountancy</option>
                                <option value="BS Management Accounting">BS Management Accounting</option>
                                <option value="BS Business Administration">BS Business Administration</option>
                                <option value="BS Information Technology">BS Information Technology</option>
                                <option value="BS Civil Engineering">BS Civil Engineering</option>
                                <option value="BS Industrial Engineering">BS Industrial Engineering</option>
                                <option value="AB Communication">AB Communication</option>
                                <option value="AB Psychology">AB Psychology</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Year Level</label>
                            <select name="year_level" id="edit_year" class="form-input">
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                                <option value="Alumni">Alumni</option>
                                <option value="TEACHER">TEACHER</option>
                            </select>
                        </div> 
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Current Location</label>
                            <input type="text" name="location" id="edit_location" class="form-input" placeholder="Address from map or manual entry">
                        </div>
                    </div>
 
                    <div style="margin-top: 20px;">
                        <label class="form-label">Student Bio</label>
                        <textarea name="bio" id="edit_bio" rows="3" placeholder="Tell something about the student..."></textarea>
                    </div>

                    <button type="submit" class="admin-btn admin-btn-save" style="width:100%; margin-top: 25px; padding: 18px; border-radius: 12px; font-size: 15px; font-family: 'Orbitron'; letter-spacing: 2px;">AUTHORIZE_CHANGES</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Teacher Modal -->
<div class="modal" id="addTeacherModal">
    <div class="modal-content" style="background: #102e22; border: 1px solid #00ffaa;">
        <span class="close" onclick="closeAddTeacherModal()">&times;</span>
        <h3 style="color:#00ffaa;">Add New Teacher</h3>
        <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_teacher">
            
            <label style="color:#ccffeb;">Name</label>
            <input type="text" name="name" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            
            <label style="color:#ccffeb;">Department</label>
            <select name="department" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                <option value="Elementary Department">Elementary</option>
                <option value="Junior High School">Junior High School</option>
                <option value="Senior High School">Senior High School</option>
                <option value="College Department">College</option>
            </select>
            
            <label style="color:#ccffeb;">Position</label>
            <input type="text" name="position" placeholder="e.g. Adviser, Dean" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            
            <label style="color:#ccffeb;">Email</label>
            <input type="email" name="email" placeholder="Email Address" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            
            <label style="color:#ccffeb;">Set Password</label>
            <input type="password" name="password" placeholder="Create Password" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            
            <label style="color:#ccffeb;">Profile Photo</label>
            <input type="file" name="profile_pic" accept="image/*" style="width:100%; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px; margin-bottom:15px;">
            
            <button type="submit" class="admin-btn admin-btn-save" style="width:100%;">Add Teacher</button>
        </form>
    </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal" id="teacherModal">
    <div class="modal-content teacher-edit-modal" style="background: #102e22; border: 1px solid #00ffaa; max-width: 850px; width: 95%;">
        <span class="close" onclick="closeTeacherModal()">&times;</span>
        <h3 style="color:#00ffaa; margin-bottom: 25px; text-align: center;">Edit Teacher Info</h3>
        <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data">
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: flex-start;">
                <!-- Left Column -->
                <div class="profile-pic-upload-section" style="text-align: center; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 15px; border: 1px solid rgba(0,255,170,0.1);">
                    <div style="position: relative; width: 140px; height: 140px; margin: 0 auto 15px; border-radius: 50%; overflow: hidden; border: 3px solid #00ffaa; box-shadow: 0 0 25px rgba(0,255,170,0.3); cursor: pointer;" onclick="document.getElementById('edit_teacher_pic_input').click()">
                        <img id="edit_teacher_pic" src="" style="width: 100%; height: 100%; object-fit: cover;">
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); color: #00ffaa; padding: 5px; font-size: 12px; font-weight: bold;">Change Photo</div>
                    </div>
                    <input type="file" name="profile_pic" id="edit_teacher_pic_input" accept="image/*" style="display: none;" onchange="previewEditTeacherImage(this)">
                    
                    <div class="privacy-locks-section" style="background: rgba(0,255,170,0.05); padding: 20px; border-radius: 15px; border: 1px solid rgba(0,255,170,0.1); text-align: left; margin-top: 20px;">
                         <label class="form-label" style="color:#fff; margin-bottom: 15px; font-family: 'Orbitron'; font-size: 11px;">PRIVACY_LOCKS</label>
                         <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:14px; color:#b0fce0;">Hide Phone</span>
                            <label class="switch">
                                <input type="checkbox" name="hide_phone" id="edit_teacher_hide_phone">
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>
                </div>
                <!-- Right Column -->
                <div style="flex: 1;">
                    <input type="hidden" name="action" value="update_teacher">
                    <input type="hidden" name="teacher_id" id="edit_teacher_id">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" id="edit_teacher_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select name="department" id="edit_teacher_dept" class="form-input">
                                <option value="Elementary Department">Elementary</option>
                                <option value="Junior High School">Junior High School</option>
                                <option value="Senior High School">Senior High School</option>
                                <option value="College Department">College</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" id="edit_teacher_pos" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" id="edit_teacher_email" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="phone" id="edit_teacher_phone" class="form-input">
                        </div>
                    </div>
                    <button type="submit" class="admin-btn admin-btn-save" style="width:100%; margin-top: 25px; padding: 18px; font-family: 'Orbitron';">AUTHORIZE_CHANGES</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Alumni Modal -->
<div class="modal" id="addAlumniModal">
    <div class="modal-content" style="background: #102e22; border: 1px solid #00ffaa;">
        <span class="close" onclick="closeAddAlumniModal()">&times;</span>
        <h3 style="color:#00ffaa;">Add Alumni</h3>
        <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_alumni">
            
            <label style="color:#ccffeb;">Name</label>
            <input type="text" name="name" placeholder="Full Name" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            
            <label style="color:#ccffeb;">Student ID</label>
            <input type="text" name="student_id" placeholder="Student ID Number" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">

            <label style="color:#ccffeb;">Course</label>
            <select name="course" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                <option value="BS Nursing">BS Nursing</option>
                <option value="BS Physical Therapy">BS Physical Therapy</option>
                <option value="BS Medical Technology">BS Medical Technology</option>
                <option value="BS Midwifery">BS Midwifery</option>
                <option value="Doctor of Dental Medicine">Doctor of Dental Medicine</option>
                    <option value="BS Hospitality Management">BS Hospitality Management</option>
                <option value="BS Tourism Management">BS Tourism Management</option>
                <option value="BS Criminology">BS Criminology</option>
                <option value="BS Accountancy">BS Accountancy</option>
                <option value="BS Management Accounting">BS Management Accounting</option>
                <option value="BS Business Administration">BS Business Administration</option>
                <option value="BS Information Technology">BS Information Technology</option>
                <option value="BS Civil Engineering">BS Civil Engineering</option>
                <option value="BS Industrial Engineering">BS Industrial Engineering</option>
                <option value="AB Communication">AB Communication</option>
                <option value="AB Psychology">AB Psychology</option>
            </select>

            
            
            <label style="color:#ccffeb;">Batch Year</label>
            <input type="text" name="batch_year" placeholder="e.g. 2026" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            
            <label style="color:#ccffeb;">Birthdate</label>
            <input type="date" name="birthdate" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">

            <label style="color:#ccffeb;">Location</label>
            <input type="text" name="location" placeholder="e.g. Lucena City, Quezon" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">

            <label style="color:#ccffeb;">Status sa Buhay</label>
            <textarea name="status" placeholder="e.g. Employed at Google as Software Engineer" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;" rows="2"></textarea>
            
            <label style="color:#ccffeb;">Profile Photo</label>
            <input type="file" name="profile_pic" accept="image/*" style="width:100%; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px; margin-bottom:15px;">
            
            <button type="submit" class="admin-btn admin-btn-save" style="width:100%;">Add Alumni</button>
        </form>
    </div>
</div>

<!-- Edit Alumni Modal -->
<div class="modal" id="editAlumniModal">
    <div class="modal-content alumni-edit-modal" style="background: #102e22; border: 1px solid #00ffaa; max-width: 850px; width: 95%;">
        <span class="close" onclick="closeEditAlumniModal()">&times;</span>
        <h3 style="color:#00ffaa; margin-bottom: 25px; text-align: center;">Edit Alumni Info</h3>
        <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data">
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: flex-start;">
                <!-- Left Column -->
                <div class="profile-pic-upload-section" style="text-align: center; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 15px; border: 1px solid rgba(0,255,170,0.1);">
                    <div style="position: relative; width: 140px; height: 140px; margin: 0 auto 15px; border-radius: 50%; overflow: hidden; border: 3px solid #ffd700; box-shadow: 0 0 25px rgba(255,215,0,0.3); cursor: pointer;" onclick="document.getElementById('edit_alumni_pic_input').click()">
                        <img id="edit_alumni_pic" src="" style="width: 100%; height: 100%; object-fit: cover;">
                        <div style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.6); color: #ffd700; padding: 5px; font-size: 12px; font-weight: bold;">Change Photo</div>
                    </div>
                    <input type="file" name="profile_pic" id="edit_alumni_pic_input" accept="image/*" style="display: none;" onchange="previewEditAlumniImage(this)">
                    
                    <div class="form-group" style="margin-top: 20px; text-align: left;">
                        <label class="form-label" style="color:#ffd700;">Status / Achievement</label>
                        <textarea name="status" id="edit_alumni_status" class="form-input" rows="4" placeholder="Current job, location, or awards..."></textarea>
                    </div>
                </div>
                <!-- Right Column -->
                <div style="flex: 1;">
                    <input type="hidden" name="action" value="update_alumni">
                    <input type="hidden" name="alumni_id" id="edit_alumni_id">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" id="edit_alumni_name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Student ID</label>
                            <input type="text" name="student_id" id="edit_alumni_student_id" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Course</label>
                            <input type="text" name="course" id="edit_alumni_course" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Batch Year</label>
                            <input type="text" name="batch_year" id="edit_alumni_batch" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Birthdate</label>
                            <input type="date" name="birthdate" id="edit_alumni_birthdate" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="phone" id="edit_alumni_phone" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="edit_alumni_location" class="form-input">
                        </div>
                    </div>
                    <button type="submit" class="admin-btn admin-btn-save" style="width:100%; margin-top: 25px; padding: 18px; font-family: 'Orbitron'; background: linear-gradient(45deg, #ffd700, #b8860b); color: #000;">AUTHORIZE_ALUMNI_UPDATE</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Move to Alumni Modal -->
<div class="modal" id="moveToAlumniModal">
    <div class="modal-content" style="background: #102e22; border: 1px solid #00ffaa;">
        <span class="close" onclick="closeMoveToAlumniModal()">&times;</span>
        <h3 style="color:#00ffaa;">Move to Alumni</h3>
        <p style="color:#ccc; font-size:13px;">Please update details before moving.</p>
        <div style="text-align:center; margin-bottom:15px;">
            <img id="move_student_pic" src="" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:2px solid #00ffaa;">
        </div>
        <form method="POST" action="SACLICONNECT2.php">
            <input type="hidden" name="action" value="move_to_alumni">
            <input type="hidden" name="student_id" id="move_student_id">
            
            <label style="color:#ccffeb;">Student Name</label>
            <input type="text" id="move_student_name" readonly style="width:100%; padding:10px; margin:5px 0 15px; background:rgba(255,255,255,0.1); border:1px solid #555; color:#aaa; border-radius:5px;">

            <label style="color:#ccffeb;">Course</label>
            <select name="course" id="move_course" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                <option value="BS Nursing">BS Nursing</option>
                <option value="BS Physical Therapy">BS Physical Therapy</option>
                <option value="BS Medical Technology">BS Medical Technology</option>
                <option value="BS Midwifery">BS Midwifery</option>
                <option value="Doctor of Dental Medicine">Doctor of Dental Medicine</option>
                <option value="BS Hospitality Management">BS Hospitality Management</option>
                <option value="BS Tourism Management">BS Tourism Management</option>
                <option value="BS Criminology">BS Criminology</option>
                <option value="BS Accountancy">BS Accountancy</option>
                <option value="BS Management Accounting">BS Management Accounting</option>
                <option value="BS Business Administration">BS Business Administration</option>
                <option value="BS Information Technology">BS Information Technology</option>
                <option value="BS Civil Engineering">BS Civil Engineering</option>
                <option value="BS Industrial Engineering">BS Industrial Engineering</option>
                <option value="AB Communication">AB Communication</option>
                <option value="AB Psychology">AB Psychology</option>
            </select>
            
            <label style="color:#ccffeb;">Batch Year</label>
            <input type="text" name="batch_year" id="move_batch" placeholder="e.g. 2026" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            
            <label style="color:#ccffeb;">Birthdate</label>
            <input type="date" name="birthdate" id="move_birthdate" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">

            <label style="color:#ccffeb;">Email Address</label>
            <input type="email" name="email" id="move_email" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            
            <label style="color:#ccffeb;">Location</label>
            <input type="text" name="location" placeholder="e.g. Lucena City, Quezon" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">

            <label style="color:#ccffeb;">Status / Work</label>
            <input type="text" name="status" placeholder="e.g. Employed at Google" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">

            <button type="submit" class="admin-btn admin-btn-save" style="width:100%;">Confirm Move</button>
        </form>
    </div>
</div>

<!-- Add Achievement Modal -->
<div class="modal" id="addAchievementModal">
    <div class="modal-content" style="background: #102e22; border: 1px solid #00ffaa;">
        <span class="close" onclick="closeAddAchievementModal()">&times;</span>
        <h3 id="addAchievementModalTitle" style="color:#00ffaa;">Add Achievement</h3>
        <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_achievement">
            <input type="hidden" name="category" id="achievement_category_input">

            <label style="color:#ccffeb;">Student Name</label>
            <input type="text" name="student_name" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            
            <label style="color:#ccffeb;">Title / Award</label>
            <input type="text" name="title" placeholder="e.g. Dean's Lister, MVP, Quiz Bee Champion" required style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
            
            <label style="color:#ccffeb;">Description</label>
            <textarea name="description" placeholder="Short description (e.g., GWA: 1.25, 1st Place Regional Level)" rows="2" style="width:100%; padding:10px; margin:5px 0 15px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;"></textarea>
            
            <label style="color:#ccffeb;">Photo (Optional)</label>
            <input type="file" name="image" accept="image/*" style="width:100%; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px; margin-bottom:15px;">
            
            <button type="submit" class="admin-btn admin-btn-save" style="width:100%;">Add Achievement</button>
        </form>
    </div>
</div>

<!-- View User Posts Modal -->
<div class="modal" id="viewUserPostsModal">
    <div class="modal-content" style="background: #102e22; border: 1px solid #00ffaa; width: 500px; max-width:90%;">
        <span class="close" onclick="closeViewPostsModal()">&times;</span>
        <h3 style="color:#00ffaa;">Posts by <span id="viewPostsUserName"></span></h3>
        <div id="userPostsContainer" style="max-height: 400px; overflow-y: auto; margin-top:15px;">
            <div style="text-align:center; color:#aaa;">Loading...</div>
        </div>
    </div>
</div>

<!-- Admin Profile Modal -->
<div class="modal" id="adminProfileModal">
    <div class="modal-content" style="background: #102e22; border: 1px solid #00ffaa; max-width: 400px; text-align: center;">
        <span class="close" onclick="closeAdminProfileModal()">&times;</span>
        <h3 style="color:#00ffaa;">Update Admin Profile</h3>
        <div style="width:120px; height:120px; border-radius:50%; border:3px solid #00ffaa; margin:0 auto 20px; overflow:hidden;">
            <img id="admin_preview" src="<?php echo $admin_pic; ?>" style="width:100%; height:100%; object-fit:cover;">
        </div>
        <form method="POST" action="SACLICONNECT2.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_admin_profile">
            
            <div style="text-align: left; margin-bottom: 15px;">
                <label style="color:#ccffeb; font-size: 12px; font-weight: bold;">Admin Email:</label>
                <input type="email" name="admin_email" id="admin_email_input" value="<?php echo htmlspecialchars($admin_data['email'] ?? ''); ?>" placeholder="admin@example.com" required style="margin-top:5px;" <?php echo !empty($admin_data['email']) ? 'readonly' : ''; ?>>
                <?php if(!empty($admin_data['email'])): ?>
                <div id="admin_email_change_wrapper">
                    <div class="change-link-modern" onclick="sendAdminEmailVerification()">Change Email</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- OTP Input for Admin Email Change -->
            <div id="admin_otp_container" style="display:none; text-align: left; margin-bottom: 15px; background: rgba(0,255,170,0.05); padding: 15px; border-radius: 8px; border: 1px solid rgba(0,255,170,0.2);">
                <label style="color:#ccffeb; font-size: 11px; font-weight: bold;">Verification Code:</label>
                <div style="display:flex; gap:10px; margin-top:5px;">
                    <input type="text" id="admin_otp_input" placeholder="000000" style="flex:1; text-align:center; letter-spacing:5px; font-family:monospace; background:rgba(0,0,0,0.3); color:#fff; border:1px solid #00ffaa; border-radius:5px;">
                    <button type="button" class="admin-btn admin-btn-save" style="padding:5px 15px; border-radius:5px;" onclick="verifyAdminOTP()">Accept</button>
                </div>
                <small style="color:#aaa; font-size:10px; display:block; margin-top:5px;">Check your current email inbox for the authorization code.</small>
            </div>

            <label for="admin_pic_input" style="display:block; background:rgba(0,255,170,0.1); color:#00ffaa; padding:10px; border-radius:5px; cursor:pointer; border:1px solid #00ffaa; margin-bottom:15px;">Choose Image</label>
            <input type="file" name="admin_pic" id="admin_pic_input" accept="image/*" style="display:none;" onchange="previewAdminImage(this)">
            <button type="submit" class="admin-btn admin-btn-save" style="width:100%;">Save Changes</button>
        </form>
    </div>
</div>

<!-- Restrict User Modal -->
<div class="modal" id="restrictUserModal">
    <div class="modal-content" style="background: #102e22; border: 1px solid #00ffaa; max-width: 400px; text-align: center;">
        <span class="close" onclick="closeRestrictModal()">&times;</span>
        <h3 style="color:#00ffaa; margin-bottom:10px;">Manage Restriction</h3>
        <p id="restrictUserName" style="color:#fff; margin-bottom:20px;"></p>
        
        <form method="POST" action="SACLICONNECT2.php">
            <input type="hidden" name="action" value="restrict_user">
            <input type="hidden" name="user_type" id="restrictUserType">
            <input type="hidden" name="user_id" id="restrictUserId">
            <input type="hidden" name="redirect_page" id="restrictRedirectPage">
            
            <div style="margin-bottom:20px; text-align:left;">
                <label style="color:#ccffeb; display:block; margin-bottom:5px;">Action:</label>
                <select name="restriction_mode" id="restrictionMode" style="width:100%; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;" onchange="toggleDurationInput()">
                    <option value="set">Restrict Account</option>
                    <option value="lift">Unrestrict / Lift Ban</option>
                </select>
            </div>
            
            <div id="durationInputs" style="margin-bottom:20px; text-align:left;">
                <label style="color:#ccffeb; display:block; margin-bottom:5px;">Duration:</label>
                <div style="display:flex; gap:10px;">
                    <input type="number" name="duration_val" value="1" min="1" style="width:80px; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                    <select name="duration_unit" style="flex:1; padding:10px; background:#1a3d2f; border:1px solid #00ffaa; color:white; border-radius:5px;">
                        <option value="days">Days</option>
                        <option value="months">Months</option>
                        <option value="years">Years</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="admin-btn admin-btn-save" style="width:100%;">Save</button>
        </form>
    </div>
</div>

<!-- Deactivate Account Modal (Transfer) -->
<div class="modal" id="deactivateAccountModal">
    <div class="modal-content" style="background: #102e22; border: 1px solid #ff5555; max-width: 500px;">
        <span class="close" onclick="closeDeactivateAccountModal()">&times;</span>
        <h3 style="color:#ff5555;">Deactivate Account (Transfer/Left)</h3>
        <p style="color:#ccc; font-size:13px; margin-bottom:15px;">Use this to deactivate accounts of students or teachers who have transferred to another school or left. Their data will remain, but they cannot log in.</p>
        
        <input type="text" id="deactivateSearch" placeholder="Search name or ID..." onkeyup="searchDeactivateUsers()" style="width:100%; padding:12px; border-radius:5px; border:1px solid #ff5555; background:#1a3d2f; color:white; margin-bottom:15px; outline:none;">
        
        <div id="deactivateList" style="max-height: 300px; overflow-y: auto; background:rgba(0,0,0,0.2); border-radius:5px; border:1px solid rgba(255,255,255,0.1);">
            <div style="text-align:center; color:#888; padding:20px;">Type to search users...</div>
        </div>
        <form id="deactivateForm" method="POST" action="SACLICONNECT2.php" style="display:none;"><input type="hidden" name="action" value="deactivate_user_indefinitely"><input type="hidden" name="user_type" id="deactUserType"><input type="hidden" name="user_id" id="deactUserId"></form>
        <div style="margin-top:15px; text-align:center; border-top:1px solid rgba(255,255,255,0.1); padding-top:10px;"><a href="SACLICONNECT2.php?page=deactivated" style="color:#00ffaa; text-decoration:none; font-size:13px; cursor:pointer;">View Deactivated List</a></div>
    </div>
</div>

<script>
function loadAjaxPage(page, event, element) {
    if(event) {
        event.preventDefault();
        window.history.pushState({page: page}, '', 'SACLICONNECT2.php?page=' + page);
    }

    // Sidebar Active State
    document.querySelectorAll('.sidebar-link').forEach(el => el.classList.remove('admin-active'));
    if (element) {
        element.classList.add('admin-active');
    } else {
        const link = document.querySelector(`.sidebar-link[href="SACLICONNECT2.php?page=${page}"]`) 
                  || (page==='main' ? document.querySelector(`.sidebar-link[href="SACLICONNECT2.php"]`) : null);
        if(link) link.classList.add('admin-active');
    }

    const main = document.querySelector('.main');
    main.style.opacity = '0.4';
    
    fetch('SACLICONNECT2.php?page=' + page + '&ajax_content=1')
        .then(res => res.text())
        .then(html => {
            main.innerHTML = html;
            main.style.opacity = '1';
            
            // Re-execute scripts inside the new content
            main.querySelectorAll('script').forEach(s => {
                const newScript = document.createElement("script");
                Array.from(s.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                newScript.appendChild(document.createTextNode(s.innerHTML));
                s.parentNode.replaceChild(newScript, s);
            });
        })
        .catch(err => {
            main.style.opacity = '1';
            main.innerHTML = '<p class="admin-error">Error loading content.</p>';
        });
}

function showLogoutModal() { document.getElementById("logoutModal").style.display = "flex"; }
function closeLogoutModal() { document.getElementById("logoutModal").style.display = "none"; }
function confirmLogout() { window.location.href = "admin_logout.php"; }
window.onclick = function(e) {
    if (e.target === document.getElementById("logoutModal")) closeLogoutModal();
    if (e.target === document.getElementById("studentModal")) closeStudentModal();
    if (e.target === document.getElementById("addStudentModal")) closeAddStudentModal();
    if (e.target === document.getElementById("teacherModal")) closeTeacherModal();
    if (e.target === document.getElementById("addTeacherModal")) closeAddTeacherModal();
    if (e.target === document.getElementById("addAlumniModal")) closeAddAlumniModal();
    if (e.target === document.getElementById("editAlumniModal")) closeEditAlumniModal();
    if (e.target === document.getElementById("addAchievementModal")) closeAddAchievementModal();
    if (e.target === document.getElementById("moveToAlumniModal")) closeMoveToAlumniModal();
    if (e.target === document.getElementById("customConfirmModal")) closeCustomConfirm();
    if (e.target === document.getElementById("customAlertModal")) closeCustomAlert();
    if (e.target === document.getElementById("customPromptModal")) closeCustomPrompt();
    if (e.target === document.getElementById("viewUserPostsModal")) closeViewPostsModal();
    if (e.target === document.getElementById("adminProfileModal")) closeAdminProfileModal();
    if (e.target === document.getElementById("restrictUserModal")) closeRestrictModal();
    if (e.target === document.getElementById("deactivateAccountModal")) closeDeactivateAccountModal();
};

function openStudentModal(data, pic) {
    if(pic) document.getElementById('edit_student_pic').src = pic;
    document.getElementById('edit_original_id').value = data.student_id;
    document.getElementById('edit_id').value = data.student_id;
    document.getElementById('edit_name').value = data.student_name;
    document.getElementById('edit_email').value = data.email || ''; // Populate email
    document.getElementById('edit_phone').value = data.phone || ''; // Populate phone
    document.getElementById('edit_hide_phone').checked = (data.hide_phone == 1); // Populate hide_phone checkbox
    document.getElementById('edit_location').value = data.location || ''; // Populate location
    document.getElementById('edit_course').value = data.course;
    document.getElementById('edit_year').value = data.year_level;
    document.getElementById('edit_bio').value = data.bio;
    document.getElementById('studentModal').style.display = "flex";
}
function closeStudentModal() { document.getElementById("studentModal").style.display = "none"; }

function openAddStudentModal() { 
    document.getElementById('addStudentModal').style.display = "flex"; 
    setAddMode('student'); // Default to student
}
function closeAddStudentModal() { document.getElementById('addStudentModal').style.display = "none"; }
function previewAddImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) { document.getElementById('add_preview').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}

function addSidebarRow() {
    var d = document.getElementById("sidebar-rows");
    d.appendChild(document.createElement("div")).className = "admin-form-row";
    d.lastElementChild.innerHTML = '<input type="text" name="sidebar_label[]" placeholder="Menu label"> <input type="text" name="sidebar_icon[]" placeholder="Icon filename">';
}
function addSubjectRow() {
    var d = document.getElementById("subject-rows");
    var newIndex = d.children.length;
    var div = document.createElement("div");
    div.className = "admin-form-row";
    div.style.alignItems = "flex-start";
    div.innerHTML = `
        <div style="flex:1; display:flex; flex-direction:column; gap:5px;">
            <input type="text" name="subject_name[]" placeholder="Name" style="width:100%;">
            <input type="text" name="subject_url[]" placeholder="URL" style="width:100%;">
        </div>
        <div style="display:flex; flex-direction:column; align-items:center; gap:5px; width: 150px;">
            <input type="hidden" name="existing_icon[]" value="">
            <input type="file" name="subject_icon_file[]" accept="image/*" style="width:100%; font-size:10px; color:#ccc;">
        </div>
        <label style="display:flex; align-items:center; gap:8px; color:#66ffd9; margin-top:10px;">
            <input type="checkbox" name="subject_online[${newIndex}]" value="1" checked> Online
        </label>
    `;
    d.appendChild(div);
}

// Teacher Modal Functions
function openAddTeacherModal() { document.getElementById('addTeacherModal').style.display = "flex"; }
function closeAddTeacherModal() { document.getElementById('addTeacherModal').style.display = "none"; }

function openTeacherModal(data, pic) {
    document.getElementById('edit_teacher_id').value = data.id;
    document.getElementById('edit_teacher_name').value = data.name;
    document.getElementById('edit_teacher_dept').value = data.department;
    document.getElementById('edit_teacher_pos').value = data.position;
    document.getElementById('edit_teacher_email').value = data.email;
    document.getElementById('edit_teacher_phone').value = data.phone || '';
    document.getElementById('edit_teacher_hide_phone').checked = (data.hide_phone == 1);
    
    // Set Preview
    if(pic) {
        document.getElementById('edit_teacher_pic').src = pic;
    } else {
        document.getElementById('edit_teacher_pic').src = data.profile_pic ? "uploads/" + data.profile_pic : "4icons8-teacher-50.png";
    }

    document.getElementById('teacherModal').style.display = "flex";
}
function closeTeacherModal() { document.getElementById('teacherModal').style.display = "none"; }

function previewEditTeacherImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) { document.getElementById('edit_teacher_pic').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}

// Alumni Modal Functions
function openAddAlumniModal() { document.getElementById('addAlumniModal').style.display = "flex"; }
function closeAddAlumniModal() { document.getElementById('addAlumniModal').style.display = "none"; }

function openEditAlumniModal(data) {
    document.getElementById('edit_alumni_id').value = data.id;
    document.getElementById('edit_alumni_name').value = data.name;
    document.getElementById('edit_alumni_student_id').value = data.student_id;
    document.getElementById('edit_alumni_course').value = data.course;
    document.getElementById('edit_alumni_batch').value = data.batch_year;
    document.getElementById('edit_alumni_birthdate').value = data.birthdate;
    document.getElementById('edit_alumni_location').value = data.location;
    document.getElementById('edit_alumni_phone').value = data.phone || '';
    document.getElementById('edit_alumni_status').value = data.status;
    
    // Set Preview
    document.getElementById('edit_alumni_pic').src = data.profile_pic ? "uploads/" + data.profile_pic : "assets/images/3icons8-student-64.png";
    
    document.getElementById('editAlumniModal').style.display = "flex";
}
function closeEditAlumniModal() { document.getElementById('editAlumniModal').style.display = "none"; }

function previewEditAlumniImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) { document.getElementById('edit_alumni_pic').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}

function openMoveToAlumniModal(student, pic) {
    if(pic) document.getElementById('move_student_pic').src = pic;
    document.getElementById('move_student_id').value = student.student_id;
    document.getElementById('move_student_name').value = student.student_name;
    document.getElementById('move_course').value = student.course;
    document.getElementById('move_email').value = student.email;
    document.getElementById('move_batch').value = new Date().getFullYear();
    document.getElementById('moveToAlumniModal').style.display = "flex";
}
function closeMoveToAlumniModal() { document.getElementById('moveToAlumniModal').style.display = "none"; }

// Achievement Modal Functions (Updated)
function openAddAchievementModal(category, title) {
    document.getElementById('addAchievementModalTitle').innerText = title;
    document.getElementById('achievement_category_input').value = category;
    document.getElementById('addAchievementModal').style.display = "flex";
}
function closeAddAchievementModal() { document.getElementById('addAchievementModal').style.display = "none"; }

// Custom Confirm Logic
let formToSubmit = null;
let confirmCallback = null;
function confirmFormSubmit(event, message, name = null, pic = null) {
    event.preventDefault();
    formToSubmit = event.target;
    document.getElementById('customConfirmText').innerText = message;
    
    const infoDiv = document.getElementById('confirmUserInfo');
    if(name && pic) {
        document.getElementById('confirmUserImg').src = pic;
        document.getElementById('confirmUserName').innerText = name;
        infoDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'none';
    }
    
    document.getElementById('customConfirmModal').style.display = 'flex';
    return false;
}

function showGenericConfirm(message, callback, name = null, pic = null) {
    document.getElementById('customConfirmText').innerText = message;
    
    const infoDiv = document.getElementById('confirmUserInfo');
    if(name && pic) {
        document.getElementById('confirmUserImg').src = pic;
        document.getElementById('confirmUserName').innerText = name;
        infoDiv.style.display = 'block';
    } else {
        infoDiv.style.display = 'none';
    }

    document.getElementById('customConfirmModal').style.display = 'flex';
    confirmCallback = callback;
}

function closeCustomConfirm() {
    document.getElementById('customConfirmModal').style.display = 'none';
    formToSubmit = null;
    confirmCallback = null;
}
document.getElementById('confirmYesBtn').onclick = function() {
    if (formToSubmit) {
        formToSubmit.submit();
    } else if (confirmCallback) {
        confirmCallback();
    }
    closeCustomConfirm();
};

// Custom Alert Logic
function showCustomAlert(msg) {
    document.getElementById('customAlertText').innerText = msg;
    document.getElementById('customAlertModal').style.display = 'flex';
}
function closeCustomAlert() {
    document.getElementById('customAlertModal').style.display = 'none';
}

// Custom Prompt Logic
let promptCallback = null;
function showCustomPrompt(msg, placeholder, callback) {
    document.getElementById('customPromptText').innerText = msg;
    document.getElementById('customPromptInput').value = '';
    document.getElementById('customPromptInput').placeholder = placeholder;
    document.getElementById('customPromptModal').style.display = 'flex';
    document.getElementById('customPromptInput').focus();
    promptCallback = callback;
}
function closeCustomPrompt() {
    document.getElementById('customPromptModal').style.display = 'none';
    promptCallback = null;
}
document.getElementById('promptOkBtn').onclick = function() {
    let val = document.getElementById('customPromptInput').value;
    if(val && promptCallback) promptCallback(val);
    closeCustomPrompt();
};

function changeStudentPassword(studentId, name, pic) { // Renamed function
    showGenericConfirm("Do you want to change the password for this user?", function() {
        showCustomPrompt("Enter new Password for " + name + ":", "New Password", function(newPass) {
            let form = document.getElementById('promptForm');
            form.innerHTML = `<input type="hidden" name="action" value="change_student_password_admin">
                              <input type="hidden" name="student_id" value="${studentId}">
                              <input type="hidden" name="new_password" value="${newPass}">`;
            form.submit();
        });
    }, name, pic);
}

function changeAdminPass(adminId) {
    showCustomPrompt("Enter new password for this Admin:", "New Password", function(newPass) {
        let form = document.getElementById('promptForm');
        form.innerHTML = `<input type="hidden" name="action" value="change_admin_pass">
                          <input type="hidden" name="admin_id" value="${adminId}">
                          <input type="hidden" name="new_pass" value="${newPass}">`;
        form.submit();
    });
}

function changeTeacherPass(teacherId) {
    showCustomPrompt("Enter new password for this Teacher:", "New Password", function(newPass) {
        let form = document.getElementById('promptForm');
        form.innerHTML = `<input type="hidden" name="action" value="change_teacher_pass">
                          <input type="hidden" name="teacher_id" value="${teacherId}">
                          <input type="hidden" name="new_pass" value="${newPass}">`;
        form.submit();
    });
}

function openAdminProfileModal() { document.getElementById('adminProfileModal').style.display = 'flex'; }
function closeAdminProfileModal() { document.getElementById('adminProfileModal').style.display = 'none'; }
function previewAdminImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) { document.getElementById('admin_preview').src = e.target.result; }
        reader.readAsDataURL(input.files[0]);
    }
}

function openRestrictModal(type, id, name, redirectPage, isRestricted) {
    document.getElementById('restrictUserType').value = type;
    document.getElementById('restrictUserId').value = id;
    document.getElementById('restrictRedirectPage').value = redirectPage;
    document.getElementById('restrictUserName').innerText = "For: " + name;
    
    const modeSelect = document.getElementById('restrictionMode');
    modeSelect.value = isRestricted ? 'lift' : 'set';
    toggleDurationInput();
    
    document.getElementById('restrictUserModal').style.display = 'flex';
}
function closeRestrictModal() { document.getElementById('restrictUserModal').style.display = 'none'; }
function toggleDurationInput() {
    const mode = document.getElementById('restrictionMode').value;
    document.getElementById('durationInputs').style.display = (mode === 'set') ? 'block' : 'none';
}

// Real-time Clock Function
function updateClock() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const clockElement = document.getElementById('realtime-clock');
    if (clockElement) {
        clockElement.textContent = timeString;
    }
}

// Fix: Maintain scroll position after saving (Para hindi bumalik sa taas)
document.addEventListener("DOMContentLoaded", function() {
    updateClock(); // Initial call for clock
    setInterval(updateClock, 1000); // Update every second
    var pos = localStorage.getItem('admin_scroll_pos');
    // Only scroll if not just loaded via AJAX (though this runs on full load)
    if (pos && !<?php echo isset($_GET['ajax_content']) ? 'true' : 'false'; ?>) { window.scrollTo(0, pos); localStorage.removeItem('admin_scroll_pos'); }

    // Remove 'intro' query param so animation doesn't play on refresh
    const url = new URL(window.location);
    if (url.searchParams.has('intro')) {
        url.searchParams.delete('intro');
        window.history.replaceState({}, document.title, url);
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('pass_changed')) {
        showCustomAlert('Password was changed successfully. You will be logged out for security.');
        
        const okBtn = document.getElementById('alertOkBtn');
        okBtn.innerText = "Logout Now";
        okBtn.onclick = function() {
            window.location.href = 'admin_logout.php';
        };

        // Auto-logout after 3 seconds
        setTimeout(function() {
            window.location.href = 'admin_logout.php';
        }, 3000);
    }

    // Handle Back/Forward Browser Buttons
    window.addEventListener('popstate', (event) => {
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page') || 'main';
        loadAjaxPage(page, null, null);
    });
});
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => { localStorage.setItem('admin_scroll_pos', window.scrollY); });
});

function filterAdminChats() {
    let input = document.getElementById('adminChatSearch').value.toLowerCase();
    let items = document.querySelectorAll('.admin-chat-item');
    items.forEach(item => {
        let names = item.querySelector('.chat-names').innerText.toLowerCase();
        item.style.display = names.includes(input) ? "block" : "none";
    });
}

function filterPasswords() {
    let input = document.getElementById('passwordSearch').value.toLowerCase();
    let rows = document.querySelectorAll('.admin-pass-row, .student-pass-row, .teacher-pass-row, .alumni-pass-row');
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}

function filterAlumni() {
    let input = document.getElementById('alumniSearch').value.toLowerCase();
    let rows = document.querySelectorAll('.alumni-row');
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}

function filterTeachers() {
    let input = document.getElementById('teacherSearch').value.toLowerCase();
    let rows = document.querySelectorAll('.teacher-row');
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}

function filterAlumniAccounts() {
    let input = document.getElementById('alumniAccountSearch').value.toLowerCase();
    let rows = document.querySelectorAll('.alumni-account-row');
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}

function filterStudentManagement() {
    let input = document.getElementById('studentManagementSearch').value.toLowerCase();
    let rows = document.querySelectorAll('.student-management-row');
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}

function filterEvalResults() {
    let input = document.getElementById('evalSearch').value.toLowerCase();
    let items = document.querySelectorAll('.eval-result-item');
    items.forEach(item => {
        let text = item.innerText.toLowerCase();
        item.style.display = text.includes(input) ? "" : "none";
    });
}

function setAddMode(mode) {
    const sBtn = document.getElementById('toggleStudentBtn');
    const tBtn = document.getElementById('toggleTeacherBtn');
    const sFields = document.getElementById('studentFields');
    const tFields = document.getElementById('teacherFields');
    const action = document.getElementById('addMemberAction');
    const submitBtn = document.getElementById('addMemberSubmitBtn');
    
    if(mode === 'student') {
        sBtn.style.background = '#00ffaa'; sBtn.style.color = '#0a1f16';
        tBtn.style.background = 'transparent'; tBtn.style.color = '#00ffaa';
        
        sFields.style.display = 'block';
        tFields.style.display = 'none';
        
        action.value = 'add_student';
        submitBtn.innerText = 'Add Student';
        
        toggleDisabled(sFields, false);
        toggleDisabled(tFields, true);
    } else {
        tBtn.style.background = '#00ffaa'; tBtn.style.color = '#0a1f16';
        sBtn.style.background = 'transparent'; sBtn.style.color = '#00ffaa';
        
        sFields.style.display = 'none';
        tFields.style.display = 'block';
        
        action.value = 'add_teacher';
        submitBtn.innerText = 'Add Teacher';
        
        toggleDisabled(sFields, true);
        toggleDisabled(tFields, false);
    }
}

function toggleDisabled(container, isDisabled) {
    const inputs = container.querySelectorAll('input, select');
    inputs.forEach(input => input.disabled = isDisabled);
}

function openViewPostsModal(name) {
    document.getElementById('viewPostsUserName').innerText = name;
    document.getElementById('viewUserPostsModal').style.display = 'flex';
    document.getElementById('userPostsContainer').innerHTML = '<div style="text-align:center; color:#aaa; padding:20px;">Loading...</div>';
    
    let formData = new FormData();
    formData.append('action', 'fetch_user_posts');
    formData.append('student_name', name);
    
    fetch('SACLICONNECT2.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(html => {
        document.getElementById('userPostsContainer').innerHTML = html;
    });
}

function closeViewPostsModal() {
    document.getElementById('viewUserPostsModal').style.display = 'none';
}

function deleteUserPost(id) {
    if(!confirm("Are you sure you want to delete this post?")) return;
    let formData = new FormData();
    formData.append('action', 'delete_post_ajax');
    formData.append('id', id);
    fetch('SACLICONNECT2.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(resp => {
        if(resp.trim() === 'success') {
            let row = document.getElementById('post-row-' + id);
            if(row) row.remove();
        } else {
            alert("Error deleting post.");
        }
    });
}

// Auto-reload logic for Admin Updates
let currentReqCount = 0; // Initialized to 0, will be updated by first poll
let currentConCount = 0; // Initialized to 0, will be updated by first poll
let isPollingAdminUpdates = false;

setInterval(function() {
    if (isPollingAdminUpdates) return;
    // Prevent sync if admin is typing or a modal is open
    if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') return;
    if (document.querySelector('.modal[style*="display: flex"]')) return;

    isPollingAdminUpdates = true;

    let formData = new FormData();
    formData.append('action', 'check_admin_updates');
    formData.append('current_page', '<?php echo $page; ?>'); // Send current page to fetch specific data
    
    fetch('SACLICONNECT2.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if ('<?php echo $page; ?>' === 'concerns' && data.con > currentConCount) {
            location.reload(); // Simple reload for new concerns for now
        }
        currentConCount = data.con;
        currentReqCount = data.req;
    }); // This closing brace was missing for the .then(data => { ... }) block
}, 3000); // Check every 3 seconds

// Theme Animations
if (document.body.classList.contains('theme-halloween')) {
    setInterval(() => {
        const p = document.createElement('div');
        p.classList.add('ash');
        p.style.left = Math.random() * window.innerWidth + 'px';
        p.style.top = -10 + 'px';
        p.style.width = p.style.height = (Math.random() * 3 + 2) + 'px';
        p.style.animationDuration = (Math.random() * 5 + 5) + 's';
        document.body.appendChild(p);
        setTimeout(() => p.remove(), 10000);
    }, 100);
}

if (document.body.classList.contains('theme-new_year')) {
    setInterval(() => {
        const x = Math.random() * window.innerWidth;
        const y = Math.random() * (window.innerHeight * 0.5);
        const colors = ['#ff0000', '#ffd700', '#00ff00', '#00ffff', '#ff00ff', '#ffffff'];
        const color = colors[Math.floor(Math.random() * colors.length)];
        for(let i=0; i<50; i++) {
            const p = document.createElement('div');
            p.classList.add('firework-particle');
            p.style.left = x + 'px';
            p.style.top = y + 'px';
            p.style.backgroundColor = color;
            p.style.boxShadow = `0 0 10px ${color}, 0 0 20px ${color}`;
            const angle = Math.random() * Math.PI * 2;
            const velocity = Math.random() * 200 + 50;
            p.style.setProperty('--tx', Math.cos(angle) * velocity + 'px');
            p.style.setProperty('--ty', Math.sin(angle) * velocity + 'px');
            document.body.appendChild(p);
            setTimeout(() => p.remove(), 1500);
        }
    }, 1000);
}

function toggleAdminSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    sidebar.classList.toggle('active');
    if(overlay) overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
}

// Deactivate Account Functions
function openDeactivateAccountModal() { document.getElementById('deactivateAccountModal').style.display = 'flex'; document.getElementById('deactivateSearch').focus(); }
function closeDeactivateAccountModal() { document.getElementById('deactivateAccountModal').style.display = 'none'; document.getElementById('deactivateSearch').value = ''; document.getElementById('deactivateList').innerHTML = '<div style="text-align:center; color:#888; padding:20px;">Type to search users...</div>'; }

let deactTimeout;
function searchDeactivateUsers() {
    clearTimeout(deactTimeout);
    const search = document.getElementById('deactivateSearch').value.trim();
    if(search.length < 2) return;
    
    deactTimeout = setTimeout(() => {
        document.getElementById('deactivateList').innerHTML = '<div style="text-align:center; color:#aaa; padding:20px;">Searching...</div>';
        let formData = new FormData();
        formData.append('action', 'fetch_users_deactivate');
        formData.append('search', search);
        
        fetch('SACLICONNECT2.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(html => {
            document.getElementById('deactivateList').innerHTML = html;
        });
    }, 500);
}

function confirmDeactivate(type, id, name) {
    showGenericConfirm(`Are you sure you want to DEACTIVATE ${name}'s account? They will no longer be able to log in.`, function() {
        document.getElementById('deactUserType').value = type;
        document.getElementById('deactUserId').value = id;
        document.getElementById('deactivateForm').submit();
    });
}

function sendAdminEmailVerification() {
    const btn = document.querySelector('#admin_email_change_wrapper .change-link-modern');
    const originalText = btn.innerText;
    btn.innerText = "INITIALIZING...";
    btn.style.pointerEvents = "none";

    let formData = new FormData();
    formData.append('action', 'admin_email_verify_send');

    fetch('SACLICONNECT2.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('admin_otp_container').style.display = 'block';
            btn.innerText = "CODE_SENT";
            showCustomAlert("Verification code sent to your current email: " + data.masked);
        } else {
            showCustomAlert(data.message);
            btn.innerText = originalText;
            btn.style.pointerEvents = "auto";
        }
    })
    .catch(err => {
        showCustomAlert("Server uplink error.");
        btn.innerText = originalText;
        btn.style.pointerEvents = "auto";
    });
}

function verifyAdminOTP() {
    const otp = document.getElementById('admin_otp_input').value;
    let formData = new FormData();
    formData.append('action', 'admin_email_verify_otp');
    formData.append('otp', otp);

    fetch('SACLICONNECT2.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('admin_email_input').readOnly = false;
            document.getElementById('admin_email_input').focus();
            document.getElementById('admin_otp_container').style.display = 'none';
            document.getElementById('admin_email_change_wrapper').style.display = 'none';
            showCustomAlert("Identity confirmed. You can now update your admin email.");
        } else {
            showCustomAlert(data.message);
        }
    });
}

function applyThemeAjax(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const btn = form.querySelector('button');
    const originalText = btn.innerText;

    btn.innerText = "SYNCHRONIZING...";
    btn.disabled = true;

    // Step 1: Start "Fade Out" effect (Blur and Darken)
    document.body.classList.add('theme-changing');

    // Wait for the blur transition to settle before changing the background
    setTimeout(() => {
        fetch('SACLICONNECT2.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                // Step 2: Switch Theme Classes (Fixed removal bug)
                const themes = ['theme-default', 'theme-halloween', 'theme-christmas', 'theme-summer', 'theme-new_year'];
                const body = document.body;
                themes.forEach(t => body.classList.remove(t));
                body.classList.add('theme-' + data.theme);
                
                // Step 3: Update UI Selection Highlights
                document.querySelectorAll('.theme-opt-label').forEach(label => {
                    label.classList.remove('active-opt');
                    const badge = label.querySelector('.live-badge');
                    if(badge) badge.remove();
                });
                
                const selectedLabel = document.querySelector(`.theme-opt-${data.theme}`);
                if(selectedLabel) {
                    selectedLabel.classList.add('active-opt');
                    const liveBadge = document.createElement('div');
                    liveBadge.className = 'live-badge';
                    liveBadge.innerText = 'LIVE';
                    selectedLabel.appendChild(liveBadge);
                }
                
                // Step 4: "Fade In" effect (Remove blur)
                setTimeout(() => {
                    document.body.classList.remove('theme-changing');
                    btn.innerText = "AUTHORIZED";
                    setTimeout(() => { btn.innerText = originalText; btn.disabled = false; }, 2000);
                }, 100);
            }
        })
        .catch(err => {
            document.body.classList.remove('theme-changing');
            btn.innerText = "UPLINK FAILED";
            btn.disabled = false;
        });
    }, 400); // Matches the start of the CSS transition
}

function toggleSelectAllFiles(source) {
    const checkboxes = document.querySelectorAll('.file-checkbox');
    checkboxes.forEach(cb => cb.checked = source.checked);
    toggleBulkDeleteBtn();
}

function toggleBulkDeleteBtn() {
    const anyChecked = document.querySelectorAll('.file-checkbox:checked').length > 0;
    const btn = document.getElementById('bulkDeleteBtn');
    if(btn) {
        btn.style.display = anyChecked ? 'inline-flex' : 'none';
    }
}

function confirmSinglePurge(fileName, viewType) {
    if(confirm('PURGE_PROTOCOL: Are you sure you want to PHYSICALLY DELETE "' + fileName + '" from the server folder? \n\nThe record will still exist in the system (posts/submissions), but the actual file content will be removed to save space.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.name = 'action';
        actionInput.value = 'delete_single_physical';
        
        const fileInput = document.createElement('input');
        fileInput.name = 'file_name';
        fileInput.value = fileName;
        
        const viewInput = document.createElement('input');
        viewInput.name = 'view_type';
        viewInput.value = viewType;
        
        form.appendChild(actionInput);
        form.appendChild(fileInput);
        form.appendChild(viewInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// Function para sa "Download" feel gamit ang Print to PDF
function downloadEvaluationReport(teacherName) {
    const originalTitle = document.title;
    // I-set ang title para ito ang maging default filename sa Save as PDF
    document.title = "Evaluation_Result_" + teacherName.replace(/\s+/g, '_');
    window.print();
    // Ibalik ang original title pagkatapos ng 1 segundo
    setTimeout(() => { document.title = originalTitle; }, 1000);
}
</script>
</body>
</html>
<?php endif; // End ajax check ?>
  