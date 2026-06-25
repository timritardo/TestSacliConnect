<?php 
session_start();
require_once __DIR__ . '/config/database.php';

// Admin POV Bridge: Siguraduhin na ang Admin identity ay maayos na naka-set
if(isset($_SESSION['admin_username']) && (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin')) {
    $_SESSION['student_name'] = 'Admin';
    $_SESSION['student_id'] = 'Admin';
    $_SESSION['user_type'] = 'admin';
}

if(!isset($_SESSION['student_name'])){
    header("Location: SacliConnect_LOG_IN.php");
    exit();
}

// Fetch Site Theme
$theme_q = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='site_theme'");
$current_theme = ($theme_q && $theme_q->num_rows > 0) ? $theme_q->fetch_assoc()['setting_value'] : 'default';

// Blackout Protocol Check
$blackout_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='blackout_mode'");
$blackout_active = ($blackout_res && $blackout_res->num_rows > 0 && $blackout_res->fetch_assoc()['setting_value'] == '1');


// Cleanup inactive users. This acts as a "poor man's cron".
// It sets users to offline if their last activity was more than 60 seconds ago.
$timeout_seconds = 60; 
$conn->query("UPDATE students SET is_online = 0 WHERE is_online = 1 AND last_activity < NOW() - INTERVAL $timeout_seconds SECOND");
$conn->query("UPDATE teachers SET is_online = 0 WHERE is_online = 1 AND last_activity < NOW() - INTERVAL $timeout_seconds SECOND");

// Fetch Current User Info (including profile_pic)
$my_id = $_SESSION['student_id'];

$my_pic = "assets/images/3icons8-student-64.png"; // Default profile picture
$user_sub_info = "Student";

$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'student';

if ($user_type === 'teacher') {
    $real_id = str_replace("T-", "", $my_id);
    $me_res = $conn->query("SELECT *, name as student_name FROM teachers WHERE id='$real_id'");
} elseif ($user_type === 'admin') {
    // Admin POV: Kunin ang info mula sa admins2 table gamit ang username
    $me_res = $conn->query("SELECT *, username as student_name FROM admins2 WHERE username='".$_SESSION['admin_username']."'");
} else {
    $me_res = $conn->query("SELECT * FROM students WHERE student_id='$my_id'");
}

$force_logout = false; // Flag for forced logout

// Security Check: If student ID no longer exists (changed by admin), logout immediately
if($me_res->num_rows == 0){
    $force_logout = true;
    session_destroy(); // Destroy the session so they can't do anything else 
} else {
    $me = $me_res->fetch_assoc();

    // Logic para paikliin ang Course at Year Level para sa display sa header
    $course_map = [
        "BS Information Technology" => "BSIT",
        "BS Hospitality Management" => "BSHM",
        "BS Business Administration" => "BSBA",
        "BS Nursing" => "BSN",
        "BS Criminology" => "BSCrim",
        "BS Physical Therapy" => "BSPT",
        "BS Medical Technology" => "BSMT",
        "BS Midwifery" => "BSM",
        "Doctor of Dental Medicine" => "DDM",
        "BS Tourism Management" => "BSTM",
        "BS Accountancy" => "BSA",
        "BS Management Accounting" => "BSMA",
        "BS Civil Engineering" => "BSCE",
        "BS Industrial Engineering" => "BSIE",
        "AB Communication" => "ABComm",
        "AB Psychology" => "ABPsych"
    ];
    $year_map = ["1st Year" => "1", "2nd Year" => "2", "3rd Year" => "3", "4th Year" => "4", "Alumni" => "Alumni"];

    if($user_type === 'student') {
        $c_short = isset($me['course']) ? ($course_map[$me['course']] ?? $me['course']) : '';
        $y_short = isset($me['year_level']) ? ($year_map[$me['year_level']] ?? $me['year_level']) : '';
        $user_sub_info = ($c_short && $y_short) ? "$c_short - $y_short" : ($c_short ?: $y_short);
    } elseif ($user_type === 'admin') {
        $user_sub_info = "Admin";
    } else {
        $user_sub_info = $me['position'] ?? 'Faculty Member';
    }





    if($user_type !== 'admin' && isset($me['force_logout']) && $me['force_logout'] == 1) {
        $table = ($user_type === 'teacher') ? 'teachers' : 'students';
        $id_col = ($user_type === 'teacher') ? 'id' : 'student_id';
        $real_id = ($user_type === 'teacher') ? str_replace('T-', '', $my_id) : $my_id;
        $conn->query("UPDATE $table SET force_logout = 0 WHERE $id_col = '$real_id'");
        session_destroy();
        header("Location: SacliConnect_LOG_IN.php?error=Security logout triggered remotely.");
        exit();
    }
    if ($user_type === 'admin') {
        $my_pic = !empty($me['profile_pic']) ? "uploads/".$me['profile_pic'] : "76946050_2554845197961929_5561337140505214976_n-removebg-preview.png";
    } else {
        if (!empty($me['profile_pic'])) {
            $my_pic = "uploads/".$me['profile_pic'];
        }
    }
}

// Fetch counts for dashboard
$student_count_res = $conn->query("SELECT COUNT(*) as total FROM students WHERE is_alumni = 0");
$student_count = $student_count_res->fetch_assoc()['total'];

$teacher_count_res = $conn->query("SELECT COUNT(*) as total FROM teachers");
$teacher_count = $teacher_count_res->fetch_assoc()['total'];

// Count alumni who have created an account (from students table)
$alumni_count_res = $conn->query("SELECT COUNT(*) as total FROM students WHERE is_alumni = 1");
$alumni_count = $alumni_count_res->fetch_assoc()['total'];

$total_registered = $student_count + $teacher_count;

// Handle Evaluation Submission
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'submit_evaluation'){
    // Check if evaluation is locked
    $eval_lock_chk = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='evaluation_locked'");
    $is_locked_submit = ($eval_lock_chk && $eval_lock_chk->num_rows > 0 && $eval_lock_chk->fetch_assoc()['setting_value'] == '1');
    
    if($is_locked_submit) {
        // Prevent submission if locked
        header("Location: SacliConnect.php?page=evaluates");
        exit();
    }

    $teacher_id = intval($_POST['teacher_id']);
    $comments = $conn->real_escape_string($_POST['comments']);
    $ratings = isset($_POST['rating']) ? $_POST['rating'] : []; // Array of question_id => rating
    
    if(!empty($ratings)){
        // Calculate average
        $total = 0;
        $count = 0;
        foreach($ratings as $r){
            $total += intval($r);
            $count++;
        }
        $average = $count > 0 ? round($total / $count) : 0;
        
        // Insert main evaluation
        $stmt = $conn->prepare("INSERT INTO evaluations (student_id, teacher_id, rating, comments) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siis", $my_id, $teacher_id, $average, $comments);
        if($stmt->execute()){
            $eval_id = $stmt->insert_id;
            // Insert details
            $stmt_det = $conn->prepare("INSERT INTO evaluation_answers (evaluation_id, question_id, rating) VALUES (?, ?, ?)");
            foreach($ratings as $qid => $score){
                $stmt_det->bind_param("iii", $eval_id, $qid, $score);
                $stmt_det->execute();
            }
            // Redirect or show success
            header("Location: SacliConnect.php?page=evaluates&success=1");
            exit();
        }
    }
}

// Handle Reset Evaluation
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reset_evaluation'){
    $teacher_id_reset = intval($_POST['teacher_id']);
    $student_id_reset = $_SESSION['student_id'];
    
    // Delete evaluation and answers
    $conn->query("DELETE FROM evaluation_answers WHERE evaluation_id IN (SELECT id FROM evaluations WHERE student_id='$student_id_reset' AND teacher_id='$teacher_id_reset')");
    $conn->query("DELETE FROM evaluations WHERE student_id='$student_id_reset' AND teacher_id='$teacher_id_reset'");
    header("Location: SacliConnect.php?page=evaluates&reset=1");
    exit();
}

// AUTO-FIX: Ensure posts table has category column
safeAddColumn($conn, 'posts', 'category', "VARCHAR(50) DEFAULT 'General'");
safeAddColumn($conn, 'group_chats', 'group_icon', "VARCHAR(255) DEFAULT ''");

// AUTO-FIX: Ensure interaction tables exist (Fix for Table 'sacliconnect.post_reactions' doesn't exist)
$conn->query("CREATE TABLE IF NOT EXISTS post_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT,
    student_id VARCHAR(50),
    type VARCHAR(20) DEFAULT 'heart',
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS post_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT,
    student_id VARCHAR(50),
    comment TEXT,
    is_pinned TINYINT(1) DEFAULT 0,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");

safeAddColumn($conn, 'post_comments', 'is_pinned', 'TINYINT(1) DEFAULT 0');

$conn->query("CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    actor_id VARCHAR(50),
    type VARCHAR(20),
    post_id INT,
    is_read TINYINT(1) DEFAULT 0,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS post_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT,
    student_id VARCHAR(50)
)");

// AUTO-FIX: Ensure calendar_events table exists
$conn->query("CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    event_date DATE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    department VARCHAR(50),
    position VARCHAR(100),
    profile_pic VARCHAR(255),
    email VARCHAR(100)
)");

// AUTO-FIX: Ensure alumni table exists and has new columns
$conn->query("CREATE TABLE IF NOT EXISTS alumni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    course VARCHAR(100),
    batch_year VARCHAR(20),
    profile_pic VARCHAR(255)
)");
safeAddColumn($conn, 'alumni', 'birthdate', 'DATE NULL');
safeAddColumn($conn, 'alumni', 'status', 'TEXT NULL');
safeAddColumn($conn, 'alumni', 'location', 'VARCHAR(255) NULL');
safeAddColumn($conn, 'alumni', 'student_id', 'VARCHAR(50) NULL');

// AUTO-FIX: Ensure login_history table exists
$conn->query("CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50),
    device_info TEXT,
    ip_address VARCHAR(45),
    login_time DATETIME DEFAULT CURRENT_TIMESTAMP
)");
// Ensure location column exists
safeAddColumn($conn, 'login_history', 'location', "VARCHAR(255) DEFAULT 'Unknown'");

// AUTO-FIX: Ensure achievements table exists for Assignments/Achievements page
$conn->query("CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(100),
    category VARCHAR(50),
    title VARCHAR(255),
    description TEXT,
    image VARCHAR(255),
    date_posted DATETIME DEFAULT CURRENT_TIMESTAMP
)");
// Insert dummy data if empty so the page isn't blank
$chk_ach = $conn->query("SELECT id FROM achievements LIMIT 1");
if($chk_ach->num_rows == 0){
    $conn->query("INSERT INTO achievements (student_name, category, title, description, image) VALUES 
    ('Sample Student', 'Featured', 'Student of the Month', 'Demonstrated outstanding academic performance and community service.', ''),
    ('Top Student 1', 'Top Student', 'Dean\'s Lister', 'GWA: 1.25', ''),
    ('Contest Winner 1', 'Contest', 'Science Quiz Bee Champion', '1st Place Regional Level', ''),
    ('Athlete 1', 'Sports', 'MVP Volleyball', 'Led the team to championship', '')");
}

// AUTO-FIX: Ensure evaluations table exists
$conn->query("CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50),
    teacher_id INT,
    rating INT,
    comments TEXT,
    date_evaluated DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS evaluation_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evaluation_id INT,
    question_id INT,
    rating INT
)");

// AUTO-FIX: Rename Assignments to Achievements in sidebar_menu if exists (Para magbago agad ang label)
$conn->query("UPDATE sidebar_menu SET label='Achievements' WHERE label='Assignments'");
$conn->query("UPDATE sidebar_menu SET label='History and Password' WHERE label='Settings'");

// Fetch Evaluation Lock Status for Display
$eval_lock_q = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='evaluation_locked'");
$is_eval_locked = ($eval_lock_q && $eval_lock_q->num_rows > 0 && $eval_lock_q->fetch_assoc()['setting_value'] == '1');
?>
<?php
// AUTO-FIX: SacliRoom Tables
$conn->query("CREATE TABLE IF NOT EXISTS sacli_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    room_code VARCHAR(10) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(teacher_id),
    INDEX(room_code)
)");
$conn->query("CREATE TABLE IF NOT EXISTS sacli_room_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    role ENUM('teacher', 'student') DEFAULT 'student',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (room_id, student_id)
)");
$conn->query("CREATE TABLE IF NOT EXISTS sacli_meeting_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(50),
    student_id VARCHAR(50),
    joined_at DATETIME,
    left_at DATETIME NULL,
    host_name VARCHAR(100),
    INDEX(student_id),
    INDEX(room_code)
)");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/1_SacliConnect.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <title>Sacli Connect</title>
</head>
<body class="theme-<?php echo htmlspecialchars($current_theme); ?> <?php echo $blackout_active ? 'blackout-protocol' : ''; ?>">

<?php if ($blackout_active): ?>
    <div class="blackout-overlay">
        <h1 style="font-size: 16px; letter-spacing: 5px; margin-bottom: 30px; text-align: center;">SYSTEM_OFFLINE: SECURITY_LOCK_ACTIVE</h1>
        <button onclick="window.location.href='Logout.php'" style="background: transparent; border: 2px solid #ff4757; color: #ff4757; padding: 12px 30px; cursor: pointer; font-weight: bold; letter-spacing: 2px; font-family: 'Orbitron', sans-serif; transition: 0.3s;" onmouseover="this.style.background='#ff4757'; this.style.color='#fff'" onmouseout="this.style.background='transparent'; this.style.color='#ff4757'">LOGOUT_TERMINAL</button>
    </div>
<?php endif; ?>

<script>
    // Apply initial load animations only once per session
    if (!sessionStorage.getItem('sacliHasLoaded')) {
        document.body.classList.add('initial-load');
        sessionStorage.setItem('sacliHasLoaded', 'true');
    }
</script>

<div class="background-pattern"></div>
<img class="background-logo" src="assets/images/St.Anne_logo.png" alt="">

<style>
    .background-pattern {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -2; /* Mas mababa sa logo para nasa likod talaga */
        pointer-events: none;
        opacity: 0.15;
        background: radial-gradient(circle farthest-side at 0% 50%, #282828 23.5%, rgba(255, 170, 0, 0) 0) 21px 30px,
                    radial-gradient(circle farthest-side at 0% 50%, #2c3539 24%, rgba(240, 166, 17, 0) 0) 19px 30px,
                    linear-gradient(#282828 14%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 85%, #282828 0) 0 0,
                    linear-gradient(150deg, #282828 24%, #2c3539 0, #2c3539 26%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 74%, #2c3539 0, #2c3539 76%, #282828 0) 0 0,
                    linear-gradient(30deg, #282828 24%, #2c3539 0, #2c3539 26%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 74%, #2c3539 0, #2c3539 76%, #282828 0) 0 0,
                    linear-gradient(90deg, #2c3539 2%, #282828 0, #282828 98%, #2c3539 0%) 0 0 #282828;
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

    @keyframes backgroundFlow {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

        /* Total System Blackout Protocol */
        body.blackout-protocol {
            background: #000 !important;
            overflow: hidden !important;
        }
    
    .blackout-overlay {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        background: #000; color: #00ffaa; z-index: 99999999;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        font-family: 'Courier New', monospace;
        }

    /* Global Custom Scrollbar (Green Theme) */
    ::-webkit-scrollbar {
        width: 10px;
    }
    ::-webkit-scrollbar-track {
        background: #102e22;
    }
    ::-webkit-scrollbar-thumb {
        background: #00ffaa;
        border-radius: 5px;
        border: 2px solid #102e22;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #00cc88;
    }


footer {
            max-width: 100%;
            height: 30px;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(5, 122, 83, 0.7);
            font-family: 'Cutive Mono', monospace;
            font-size: 8px;
            letter-spacing: 1.9px;
            z-index: 1000;
            text-transform: uppercase;
            text-shadow: 0 0 10px rgba(0, 255, 170, 0.3);
            
        }







    /* Facebook-style Header */
    * { 
        box-sizing: border-box; 
        -webkit-user-select: none;
        user-select: none;
    }
    input, textarea, select, [contenteditable="true"] { 
        -webkit-user-select: text;
        user-select: text; 
    }
    :root {
        --header-h: 58px;
        --bg-dark: #102e22; /* Dark green theme */
        --bg-light: #1a3d2f;
        --text-color: #e4e6eb;
        --accent: #00ffaa;
    }
    
    /* Adjust layout to accommodate fixed header */

    body { 
        padding-top: var(--header-h); 
        background: linear-gradient(120deg, #05100c 40%, #0d2b1f 50%, #05100c 60%);
        background-size: 400% 400%;
        animation: backgroundFlow 12s ease-in-out infinite;
    }
    /* body { padding-top: var(--header-h); } */ /* Replaced by margin-top on .main */
    .sidebar, .right-sidebar { top: var(--header-h) !important; height: calc(100vh - var(--header-h)) !important; }
    
    /* --- CENTER LAYOUT FIX (Desktop) --- */
    /* Improved Sidebar Styling */
    .sidebar { 
        width: 260px; 
        position: fixed; 
        left: 0; 
        z-index: 90; 
        background: linear-gradient(180deg, var(--bg-dark) 0%, #05100c 100%); 
        border-right: 1px solid rgba(255,255,255,0.05); 
        overflow: hidden;
        padding: 20px 15px 0 15px;
        box-shadow: 4px 0 15px rgba(0,0,0,0.2);
        scrollbar-width: none;
        display: flex;
        flex-direction: column;
        transition: transform 0.6s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.5s;
    }
    .sidebar::-webkit-scrollbar { display: none; }
    
    .sidebar-header {
        display: flex; 
        align-items: center; 
        gap: 12px; 
        margin-bottom: 30px; 
        padding-bottom: 20px; 
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar-header img { width: 45px; height: 45px; filter: drop-shadow(0 0 8px #00ffaa); transition: transform 0.3s; }
    .sidebar-header:hover img { transform: rotate(10deg); }
    .sidebar-header h2 { 
        font-size: 20px; 
        margin: 0; 
        background: linear-gradient(90deg, #fff, #00ffaa); 
        -webkit-background-clip: text; 
        background-clip: text;
        -webkit-text-fill-color: transparent; 
        font-weight: 800; 
        letter-spacing: 1px; 
    }

    .sidebar-scroll-area {
        flex: 1;
        overflow-y: auto;
        scrollbar-width: none;
        padding-bottom: 20px;
    }
    .sidebar-scroll-area::-webkit-scrollbar { display: none; }
    .sidebar ul { list-style: none; padding: 0; margin: 0; }
    .sidebar li {
        display: flex; align-items: center; padding: 14px 15px; margin-bottom: 8px;
        border-radius: 12px; cursor: pointer; transition: all 0.3s ease;
        color: #b0b3b8; font-weight: 600; font-size: 15px;
    }
    .sidebar li:hover { background: rgba(0, 255, 170, 0.15); color: #00ffaa; transform: translateX(5px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .sidebar li.active-item { background: linear-gradient(90deg, rgba(0, 255, 170, 0.2), transparent); color: #00ffaa; border-left: 4px solid #00ffaa; }
    
    .sidebar-icon-box { width: 30px; display: flex; justify-content: center; margin-right: 15px; flex-shrink: 0; }
    .sidebar li img.icon2 { width: 26px; height: 26px; filter: grayscale(100%) brightness(1.5); transition: 0.3s; }
    .sidebar li:hover img.icon2, .sidebar li.active-item img.icon2 { filter: grayscale(0%) drop-shadow(0 0 5px #00ffaa); transform: scale(1.1); }

    .right-sidebar { width: 260px; position: fixed; right: 0; z-index: 90; background: var(--bg-dark); border-left: 1px solid rgba(255,255,255,0.1); overflow-y: auto; transition: transform 0.6s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.5s; }
    
    .main {
        margin-left: 260px;
        margin-right: 260px;
        display: flex;
        flex-direction: column;
        align-items: center; /* Centers the posts horizontally */
        padding: 20px;
        min-height: 100vh;
        max-width: none;
        transition: all 0.6s cubic-bezier(0.22, 1, 0.36, 1);
    }
    
    .post, .create-post {
        width: 100%;
        max-width: 680px; /* Limit width like Facebook feed */
        margin-bottom: 20px;
        transition: all 0.7s cubic-bezier(0.22, 1, 0.36, 1);
    }

    /* --- SCROLL EXPANSION & GLASSMORPHISM --- */
    body.scrolled-past .sidebar {
        transform: translateX(-260px);
        opacity: 0;
        pointer-events: none;
    }
    body.scrolled-past .right-sidebar {
        transform: translateX(260px);
        opacity: 0;
        pointer-events: none;
    }
    body.scrolled-past .main {
        margin-left: 0;
        margin-right: 0;
    }
    body.scrolled-past .post, body.scrolled-past .create-post {
        max-width: 1000px;
        transform: scale(1.02);
        background: rgba(26, 61, 47, 0.4) !important;
        backdrop-filter: blur(15px) saturate(150%);
        -webkit-backdrop-filter: blur(15px) saturate(150%);
        border: 1px solid rgba(0, 255, 170, 0.3) !important;
        box-shadow: 0 40px 100px rgba(0, 0, 0, 0.5);
    }
    
    /* Post Styling */
    .post { background: #1a3d2f; padding: 15px; border-radius: 10px; border: 1px solid rgba(0, 255, 170, 0.1); color: #e4e6eb; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
    .create-post button { position: relative; top:12px; right: 30px; width: 95%; padding: 10px; border-radius: 5px; border: 1px solid #00ffaa; background: rgba(0,255,170,0.1); color: #00ffaa; text-align: center; cursor: pointer; font-weight: bold; transition: 0.2s; }
    .create-post button:hover { background: #00ffaa; color: #000; }
    .create-post { 
        background: #1a3d2f; 
        position: relative;
        padding: 12px 15px; 
        border-radius: 10px; 
        border: 1px solid rgba(0, 255, 170, 0.1); 
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        display: flex;
        flex-direction: column;
        gap: 10px;
        transition: 0.2s;
        animation: premiumEntrance 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    /* .create-post:hover { background: #2a5c46; } */
    .create-post-input {
        flex: 1;
        background: rgba(0,0,0,0.2);
        border-radius: 20px;
        padding: 10px 15px;
        color: #b0b3b8;
        font-weight: 500;
        cursor: pointer;
    }
    .post p { margin-top: 10px; margin-bottom: 10px; line-height: 1.5; }
    /* ----------------------------------- */

    header {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: var(--header-h);
        background-color: var(--bg-dark);
        border-bottom: 1px solid rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 16px;
        z-index: 10000;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }

    .hd-left { display: flex; align-items: center; gap: 10px; }
    .hd-logo { height: 40px; width: 40px; border-radius: 50%; cursor: pointer; }
    .hd-search {
        background: var(--bg-light);
        border-radius: 50px;
        padding: 0 12px;
        height: 40px;
        display: flex;
        align-items: center;
        width: 240px;
    }
    .hd-search input {
        background: transparent;
        border: none;
        color: var(--text-color);
        margin-left: 8px;
        outline: none;
        width: 100%;
    }

    .hd-right { display: flex; align-items: center; gap: 10px; justify-content: flex-end; }
    .hd-profile {
        display: flex; align-items: center; gap: 8px;
        padding: 4px 12px 4px 4px;
        border-radius: 20px;
        cursor: pointer;
        transition: 0.2s;
    }
    .hd-profile:hover { background: rgba(255,255,255,0.1); }
    .hd-profile img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }
    .hd-profile span { color: var(--text-color); font-weight: 600; font-size: 14px; }
    
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

    .create-gc-btn {
      --green: #1BFD9C;
      font-size: 13px;
      padding: 0.6em 1.2em;
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
    .create-gc-btn:hover {
      color: #82ffc9;
      box-shadow: inset 0 0 10px rgba(27, 253, 156, 0.6), 0 0 9px 3px rgba(27, 253, 156, 0.2);
    }
    .create-gc-btn:before {
      content: ""; position: absolute; left: -4em; width: 4em; height: 100%; top: 0;
      transition: transform .4s ease-in-out;
      background: linear-gradient(to right, transparent 1%, rgba(27, 253, 156, 0.1) 40%,rgba(27, 253, 156, 0.1) 60% , transparent 100%);
    }
    .create-gc-btn:hover:before { transform: translateX(15em); }

    /* Added Dropdown & Badge Styles */
    .icon-wrapper { position: relative; cursor: pointer; }
    .badge-count {
        position: absolute;
        top: -2px;
        right: -2px;
        background-color: #e41e3f;
        color: white;
        font-size: 10px;
        font-weight: bold;
        padding: 2px 5px;
        border-radius: 10px;
        border: 1px solid var(--bg-dark);
    }
    .dropdown-popover {
        position: fixed;
        top: 60px;
        background: var(--bg-light);
        border: 1px solid rgba(0, 255, 170, 0.3);
        border-radius: 8px;
        width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.5);
        display: none;
        flex-direction: column;
        z-index: 10001;
        overflow: hidden;
    }
    .dropdown-popover.active { display: flex; animation: fadeIn 0.2s; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .dp-header { padding: 10px 15px; background: rgba(0,0,0,0.2); color: var(--accent); font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .dp-item { padding: 12px 15px; border-bottom: 1px solid rgba(255,255,255,0.05); color: var(--text-color); cursor: pointer; display: flex; align-items: center; gap: 10px; }
    .dp-item:hover { background: rgba(255,255,255,0.05); }
    .dp-item small { display: block; color: #b0b3b8; font-size: 12px; margin-top: 2px; }

    /* Chat Box Styles */
    .chat-box-container {
        position: fixed;
        bottom: 0;
        right: 80px;
        width: 320px;
        background: var(--bg-light);
        border: 1px solid rgba(0, 255, 170, 0.3);
        border-radius: 10px 10px 0 0;
        box-shadow: 0 -5px 20px rgba(0,0,0,0.5);
        display: none; /* Hidden by default */
        flex-direction: column;
        z-index: 10002;
    }
    .cb-header {
        background: var(--bg-dark);
        padding: 10px 15px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        border-radius: 10px 10px 0 0;
        display: flex; justify-content: space-between; align-items: center;
        cursor: pointer;
    }
    .cb-title { font-weight: bold; color: var(--accent); }
    .cb-close { cursor: pointer; color: #ff5555; font-weight: bold; }
    .cb-body { height: 300px; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 8px; background: rgba(0,0,0,0.2); }
    .cb-footer { padding: 10px; display: flex; gap: 5px; border-top: 1px solid rgba(255,255,255,0.1); }
    .cb-input { flex: 1; padding: 8px; border-radius: 20px; border: none; outline: none; background: rgba(255,255,255,0.1); color: white; }
    .cb-send { background: var(--accent); border: none; padding: 8px 15px; border-radius: 20px; cursor: pointer; font-weight: bold; color: #0a1f16; }
    
    /* Chat Bubble Styles (Facebook Style) */
    .minimized-chats-container {
        position: fixed;
        right: 20px;
        bottom: 20px;
        display: flex;
        flex-direction: column-reverse;
        gap: 10px;
        z-index: 10001;
    }
    .chat-bubble {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: var(--bg-light);
        border: 2px solid var(--accent);
        box-shadow: 0 4px 15px rgba(0,0,0,0.4);
        cursor: pointer;
        position: relative;
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        animation: popInCard 0.4s ease-out;
    }
    .chat-bubble:hover { transform: scale(1.1); }
    .chat-bubble img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
    
    /* Red Notification Badge for Bubbles */
    .chat-bubble .bubble-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ff4757;
        color: white;
        font-size: 11px;
        font-weight: bold;
        min-width: 20px;
        height: 20px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid var(--bg-dark);
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        padding: 0 4px;
        z-index: 10;
    }

    .chat-bubble .close-bubble {
        position: absolute; top: -5px; right: -5px;
        background: #ff5555; color: white; border-radius: 50%;
        width: 20px; height: 20px; font-size: 14px;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid var(--bg-dark); opacity: 0; transition: 0.2s;
    }
    .chat-bubble:hover .close-bubble { opacity: 1; }

    /* Bubble Message Preview */
    .bubble-preview {
        position: absolute;
        right: 70px;
        bottom: 10px;
        background: var(--bg-dark);
        color: white;
        padding: 8px 15px;
        border-radius: 18px;
        border: 1px solid var(--accent);
        font-size: 13px;
        white-space: nowrap;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        box-shadow: 0 4px 15px rgba(0,0,0,0.6);
        z-index: 10003;
        pointer-events: none;
        animation: previewFade 2.8s forwards;
    }
    @keyframes previewFade {
        0% { opacity: 0; transform: translateX(15px); }
        10% { opacity: 1; transform: translateX(0); }
        85% { opacity: 1; transform: translateX(0); }
        100% { opacity: 0; transform: translateX(15px); }
    }
    
    /* Messages */
    .msg { padding: 8px 12px; border-radius: 15px; max-width: 75%; font-size: 14px; word-wrap: break-word; position: relative; }
    .my-msg { align-self: flex-end; background: var(--accent); color: #0a1f16; }
    .other-msg { align-self: flex-start; background: rgba(255,255,255,0.1); color: white; }

    /* Typing Indicator for Mini Chat */
    .typing-indicator-mini { 
        display: flex; 
        gap: 5px; 
        align-items: center; 
        background: rgba(255, 255, 255, 0.1);
        padding: 8px 12px;
        border-radius: 15px;
        border-bottom-left-radius: 2px;
        border: 1px solid rgba(0, 255, 170, 0.2);
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    }
    .typing-indicator-mini span {
        width: 5px; height: 5px; background: var(--accent);
        border-radius: 50%; animation: typingPulse 0.8s infinite ease-in-out; opacity: 0.5;
    }
    .typing-indicator-mini span:nth-child(2) { animation-delay: 0.15s; }
    .typing-indicator-mini span:nth-child(3) { animation-delay: 0.3s; }
    @keyframes typingPulse { 0%, 100% { transform: translateY(0); opacity: 0.4; } 50% { transform: translateY(-4px); opacity: 1; } }

    /* Message Options Menu (Synced with SacliChat_Full) */
    .msg-options-wrapper { position: relative; display: flex; align-items: center; }
    .msg-dots-btn {
        background: none; border: none; color: #aaa; cursor: pointer; padding: 5px;
        font-size: 16px; opacity: 0; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
    }
    .msg-container:hover .msg-dots-btn { opacity: 1; }
    .msg-dots-btn:hover { background: rgba(255,255,255,0.1); color: #fff; transform: scale(1.2) rotate(90deg); }
    
    .msg-controls { display: none; position: absolute; top: -25px; right: 0; background: #102e22; padding: 4px 8px; border-radius: 5px; border: 1px solid #00ffaa; gap: 10px; z-index: 100; box-shadow: 0 2px 5px rgba(0,0,0,0.5); }
    .msg:hover .msg-controls { display: flex; }
    .msg-controls span { cursor: pointer; font-size: 12px; color: #00ffaa; }
    .msg-controls span:hover { color: white; }
    .msg-menu {
        display: none; position: absolute; bottom: 100%; right: 0;
        background: #102e22; border: 1px solid #00ffaa; border-radius: 8px;
        padding: 5px; z-index: 10005; box-shadow: 0 5px 15px rgba(0,0,0,0.5); min-width: 90px;
    }
    .msg-menu.active { display: block; }
    .msg-menu div { padding: 8px 12px; color: #fff; font-size: 12px; cursor: pointer; border-radius: 5px; text-align: left; white-space: nowrap; }
    .msg-menu div:hover { background: rgba(0,255,170,0.15); color: #00ffaa; }

    /* Highlight Animation for New Messages */
    @keyframes popHighlight { 0% { background: #00ffaa; } 100% { background: transparent; } }
    .has-new-msg { animation: popHighlight 2s ease; border-left: 3px solid #00ffaa; background: rgba(0, 255, 170, 0.1); }

    /* Message Content Fixes (Mini Chat Media & Timestamps) */
    .message-media-grid { display: grid; gap: 4px; margin-top: 5px; width: 100%; max-width: 240px; }
    .message-media-grid.grid-1 { grid-template-columns: 1fr; }
    .message-media-grid.grid-2 { grid-template-columns: 1fr 1fr; }
    .message-media-grid.grid-3 { grid-template-columns: repeat(3, 1fr); }
    .message-media-item { border-radius: 10px; overflow: hidden; position: relative; border: 1px solid rgba(255,255,255,0.1); background: #000; }
    .message-media-item img, .message-media-item video { width: 100%; height: auto; max-height: 180px; object-fit: cover; display: block; }

    .msg-timestamp-separator {
        text-align: center;
        font-size: 10px;
        color: rgba(255, 255, 255, 0.4);
        margin: 15px 0 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
        width: 100%;
    }

    /* Mini Chat Themes Support */
    .cb-body.theme-flashlight { background-color: #111 !important; position: relative; overflow: hidden; }
    .cb-body.theme-flashlight::before {
        content: ""; width: 100%; height: 100%; position: absolute; inset: 0;
        background-color: #111;
        clip-path: circle(80px at center center); animation: miniFlashlight 10s ease infinite;
        z-index: 0; pointer-events: none;
        background-image: radial-gradient(circle, rgba(0,255,170,0.15) 0%, transparent 70%);
    }
    @keyframes miniFlashlight {
        0% { clip-path: circle(80px at 0% 0%); }
        50% { clip-path: circle(80px at 100% 100%); }
        100% { clip-path: circle(80px at 0% 0%); }
    }
    .cb-body.theme-space { background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%) !important; }
    .cb-body.theme-rain { background-color: #000 !important; position: relative; }
    .cb-body.theme-rain::after {
        content: ""; position: absolute; inset: 0; pointer-events: none;
        background-image: linear-gradient(to bottom, rgba(0,153,255,0.15) 1px, transparent 1px);
        background-size: 100% 20px; animation: miniRain 0.5s linear infinite;
    }
    @keyframes miniRain { from { background-position: 0 0; } to { background-position: 0 100%; } }

    /* Hide media date in mini-chat bubble, only visible in lightbox */
    .cb-body .media-date { display: none !important; }
    .cb-body .media-hover-overlay { background: transparent !important; }
    .cb-body .msg img, .cb-body .msg video { border: 1px solid rgba(255,255,255,0.1); }

    .post-profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px; border: 2px solid #00ffaa; }

    /* Search Results Dropdown (Facebook Style) */
    .search-results {
        position: absolute;
        top: 45px;
        left: 0;
        width: 100%;
        background: #1a3d2f;
        border: 1px solid rgba(0, 255, 170, 0.3);
        border-radius: 0 0 15px 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        display: none;
        z-index: 10001;
        overflow: hidden;
    }
    .search-item { padding: 10px 15px; display: flex; align-items: center; gap: 10px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.05); transition: 0.2s; }
    .search-item:hover { background: rgba(255,255,255,0.1); }
    .search-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid #00ffaa; }
    .search-name { color: #e4e6eb; font-size: 14px; font-weight: 500; }

    /* Student Directory Styles */
    .total-count-banner { position: relative; left: 0%; background: linear-gradient(45deg, #102e22, #004d33); padding: 20px; border-radius: 15px; text-align: center; margin-bottom: 30px; border: 1px solid #00ffaa; box-shadow: 0 0 15px rgba(0,255,170,0.2); width: 100%; }
    .total-count-banner h2 { margin: 0; font-size: 36px; color: #fff; font-weight: 800px; }
    .total-count-banner span { color: #00ffaa; font-size: 14px; text-transform: uppercase; letter-spacing: 2px; }
    
    .course-section { width: 100%; margin-bottom: 40px; }
    .course-header { color: #00ffaa; border-bottom: 2px solid rgba(0, 255, 170, 0.3); padding-bottom: 10px; margin-bottom: 20px; font-size: 22px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
    .student-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; }
    .student-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 15px; padding: 20px; text-align: center; transition: 0.3s; cursor: pointer; position: relative; overflow: hidden; }
    .student-card:hover { background: rgba(0, 255, 170, 0.1); transform: translateY(-5px); border-color: #00ffaa; box-shadow: 0 5px 15px rgba(0,255,170,0.2); }
    .student-card img { width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 3px solid #00ffaa; margin-bottom: 15px; }
    .student-card h4 { color: #fff; font-size: 16px; margin: 5px 0; font-weight: 600; }
    .student-card p { color: #b0fce0; font-size: 13px; margin: 0; opacity: 0.8; }
    .student-card .yr-badge { position: absolute; top: 10px; right: 10px; background: #00ffaa; color: #000; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 5px; }

    /* Creator Modal Styles */
    .creator-content {
        background: rgba(16, 46, 34, 0.95);
        border: 1px solid rgba(0, 255, 170, 0.5);
        box-shadow: 0 0 50px rgba(0, 255, 170, 0.2), inset 0 0 20px rgba(0, 255, 170, 0.1);
        border-radius: 30px;
        backdrop-filter: blur(10px);
        padding: 40px;
        position: relative;
        overflow: hidden;
        
        /* Animation Setup */
        width: 350px; /* Start width (Profile only) */
        max-width: 90vw;
        transition: width 0.8s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Triggered State */
    .creator-content.animating {
        width: 900px; /* Expanded width */
    
    }

    .creator-content.animating::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 750px;
        height: 750px;
        background: url('St.Anne_logo.png') no-repeat center center;
        background-size: contain;
        opacity: 0.05;
        z-index: 0;
        pointer-events: none;
    }
    
    .creator-flex {
        display: flex;
        align-items: center;
        width: 100%;
        justify-content: center;
        gap: 20px;
        position: relative;
        z-index: 1;
    }

    .creator-profile-section {
        flex-shrink: 0;
        width: 100%; /* Initially full width */
        text-align: center;
        transition: all 0.8s ease;
        position: relative;
        z-index: 2;
    }
    
    .creator-content.animating .creator-profile-section {
        width: 45%;
        border-right: none;
        padding-right: 30px;
    }

    .creator-brand-section {
        flex-shrink: 0;
        width: 0;
        opacity: 0;
        overflow: hidden;
        transform: translateX(50px);
        transition: all 0.8s ease;
        text-align: center;
    }
    
    .creator-content.animating .creator-brand-section {
        width: 55%;
        opacity: 1;
        transform: translateX(0);
        padding-left: 30px;
    }

    /* Cyber Connection Effects (New) */
    .connection-grid {
        position: absolute;
        top: 50%; left: 50%; 
        transform: translate(-50%, -50%);
        width: 100%; height: 100%;
        z-index: 0;
        pointer-events: none;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    
    /* Central Hub */
    .connection-hub {
        width: 14px; height: 14px;
        background: #00ffaa;
        border-radius: 50%;
        box-shadow: 0 0 20px #00ffaa;
        opacity: 0;
        transform: scale(0);
        position: absolute;
        z-index: 2;
    }

    .connect-line {
        position: absolute;
        height: 2px;
        background: #00ffaa;
        opacity: 0;
        width: 0;
        top: 50%;
        box-shadow: 0 0 8px #00ffaa;
    }

    .creator-content.animating .connection-hub {
        animation: hubPop 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275) 1s forwards, hubPulse 2s infinite 1.6s;
    }

    /* Left Line (To Profile) */
    .creator-content.animating .connect-line:nth-child(2) {
        right: 50%;
        transform-origin: right;
        animation: connectGrow 0.8s ease 1.2s forwards;
    }
    /* Right Line (To Brand) */
    .creator-content.animating .connect-line:nth-child(3) {
        left: 50%;
        transform-origin: left;
        animation: connectGrow 0.8s ease 1.2s forwards;
    }

    @keyframes hubPop { to { opacity: 1; transform: scale(1); } }
    @keyframes hubPulse { 0% { box-shadow: 0 0 15px #00ffaa; } 50% { box-shadow: 0 0 40px #00ffaa, 0 0 10px #fff; transform: scale(1.3); } 100% { box-shadow: 0 0 15px #00ffaa; transform: scale(1); } }
    @keyframes connectGrow { to { width: 220px; opacity: 0.6; } }
    
    /* Data Particles */
    .data-particle {
        position: absolute;
        width: 6px; height: 6px;
        background: #fff;
        border-radius: 50%;
        top: 50%; left: 50%;
        margin-top: -3px;
        opacity: 0;
        box-shadow: 0 0 10px #fff;
        z-index: 3;
    }
    
    .creator-content.animating .data-particle {
        animation: particleFlow 3s infinite linear 2s;
    }
    
    @keyframes particleFlow {
        0% { transform: translateX(0); opacity: 0; }
        10% { opacity: 1; }
        45% { transform: translateX(-220px); opacity: 0; } /* Move Left */
        50% { transform: translateX(0); opacity: 0; }
        60% { opacity: 1; }
        95% { transform: translateX(220px); opacity: 0; } /* Move Right */
        100% { transform: translateX(0); opacity: 0; }
    }
    
    .creator-img-box {
        width: 140px; height: 140px; margin: 0 auto 0; /* Start with 0 margin */
        border-radius: 50%; padding: 6px;
        background: linear-gradient(135deg, #00ffaa, #0088ff);
        box-shadow: 0 0 30px rgba(0, 255, 170, 0.6);
        position: relative;
        transition: margin 0.5s ease;
    }
    @keyframes connectedPulse {
        0% { box-shadow: 0 0 30px rgba(0, 255, 170, 0.6); border-color: #00ffaa; }
        50% { box-shadow: 0 0 60px rgba(0, 255, 170, 0.9), 0 0 20px #fff; border-color: #fff; transform: scale(1.02); }
        100% { box-shadow: 0 0 30px rgba(0, 255, 170, 0.6); border-color: #00ffaa; transform: scale(1); }
    }
    .creator-content.animating .creator-img-box {
        margin: 0 auto 20px; /* Add margin when animating */
        transition-delay: 0.6s;
        animation: connectedPulse 2s infinite 1.5s; /* Add pulse effect */
    }

    .creator-img-box::after {
        content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        border-radius: 50%;
        box-shadow: 0 0 0 0 rgba(0, 255, 170, 0.7);
        animation: pulseImg 2s infinite;
        z-index: -1;
    }
    @keyframes pulseImg {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 255, 170, 0.7); }
        70% { transform: scale(1.1); box-shadow: 0 0 0 20px rgba(0, 255, 170, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(0, 255, 170, 0); }
    }

    .creator-img-box img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; background: #000; border: 4px solid #102e22; }
    
    .creator-name { 
        font-size: 32px; color: #fff; margin: 0; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; text-shadow: 0 0 10px rgba(0, 255, 170, 0.5);
        opacity: 0; transform: translateY(20px); 
        max-height: 0; overflow: hidden; /* Collapse initially */
        transition: all 0.5s ease; 
    }
    .creator-role { 
        color: #00ffaa; font-size: 14px; margin-top: 0; text-transform: uppercase; letter-spacing: 3px; font-weight: 600; background: rgba(0, 255, 170, 0.1); display: inline-block; padding: 5px 15px; border-radius: 20px; margin-bottom: 0;
        opacity: 0; transform: translateY(20px); 
        max-height: 0; overflow: hidden; /* Collapse initially */
        transition: all 0.5s ease; 
    }

    /* Show when animating */
    .creator-content.animating .creator-name { 
        opacity: 1; transform: translateY(0); max-height: 50px; transition-delay: 0.8s; 
    }
    .creator-content.animating .creator-role { 
        opacity: 1; transform: translateY(0); max-height: 50px; margin-top: 8px; transition-delay: 1s; 
    }
    
    .neon-brand {
        padding: 10px 0;
        text-align: center;
        font-size: 70px;
        font-weight: 900;
        
        /* Gradient Text Style */
        background: linear-gradient(to bottom, #ffffff 0%, #00ffaa 100%);
        background-repeat: no-repeat;
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        color: #00ffaa;
        
        line-height: 0.9;
        text-transform: uppercase;
        margin: 0;
        opacity: 0;
        letter-spacing: -3px;

        /* Force hardware acceleration to fix potential rendering glitches with filter/glow */
        transform: translateZ(0);
        will-change: filter, transform;
    }
    
    .creator-content.animating .neon-brand {
        animation: flashText 0.8s cubic-bezier(0.2, 0.8, 0.2, 1) 0.6s forwards, neonPulse 2s infinite alternate 1.4s;
    }
    
    @keyframes flashText { 
        0% { opacity: 0; transform: scale(1.5); filter: blur(20px); } 
        100% { opacity: 1; transform: scale(1); filter: blur(0); } 
    }
    @keyframes neonPulse { 
        from { filter: drop-shadow(0 0 5px rgba(0, 255, 170, 0.5)); transform: scale(1); } 
        to { filter: drop-shadow(0 0 25px rgba(0, 255, 170, 1)); transform: scale(1.02); } 
    }
    
    .brand-subtitle { color: #b0fce0; font-size: 13px; margin-top: 15px; font-style: italic; opacity: 0.8; line-height: 1.4; }

    /* CALENDAR STYLES (Phone Style) */
    .calendar-wrapper { width: 100%; max-width: 800px; background: #1a3d2f; border-radius: 20px; padding: 20px; border: 1px solid rgba(0, 255, 170, 0.2); box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    .cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .cal-header h2 { margin: 0; color: #fff; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
    .cal-btn { background: rgba(0, 255, 170, 0.1); color: #00ffaa; border: 1px solid #00ffaa; padding: 5px 15px; border-radius: 20px; cursor: pointer; text-decoration: none; font-weight: bold; }
    .cal-btn:hover { background: #00ffaa; color: #000; }
    
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; text-align: center; }
    .cal-day-name { color: #00ffaa; font-weight: bold; padding-bottom: 10px; font-size: 14px; text-transform: uppercase; }
    
    .cal-date { 
        background: rgba(0,0,0,0.2); 
        border-radius: 10px; 
        min-height: 80px; 
        padding: 8px; 
        position: relative; 
        transition: 0.2s;
        border: 1px solid transparent;
        display: flex;  
        flex-direction: column;
        align-items: center;
        overflow: hidden; /* Fix: Prevent cell from expanding with long text */
    }
    .cal-date:hover { background: rgba(255,255,255,0.05); border-color: rgba(0, 255, 170, 0.3); }
    .cal-date.today { border: 1px solid #00ffaa; background: rgba(0, 255, 170, 0.05); }
    .cal-num { font-weight: bold; font-size: 16px; color: #e4e6eb; margin-bottom: 5px; }
    .cal-event-dot { 
        width: 8px; height: 8px; background: #e41e3f; border-radius: 50%; margin: 2px; display: inline-block; 
        box-shadow: 0 0 5px #e41e3f;
    }
    .cal-event-text { font-size: 10px; background: #00ffaa; color: #000; padding: 2px 5px; border-radius: 4px; margin-top: 2px; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; }
    
    .event-list-day { margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
    .event-item { background: rgba(0,0,0,0.3); padding: 15px; border-radius: 10px; border-left: 4px solid #00ffaa; margin-bottom: 10px; }

    /* FIX: Ensure Close Button is clickable and visible */
    .creator-content .close {
        position: absolute;
        top: 15px;
        right: 20px;
        z-index: 100;
        color: #00ffaa;
        font-size: 30px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.3s;
    }
    .creator-content .close:hover {
        color: #fff;
        transform: rotate(90deg);
    }

    /* Post Interactions */
    .post-actions { display: flex; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 10px; padding-top: 10px; }
    .action-btn { flex: 1; background: transparent; border: none; color: #b0b3b8; cursor: pointer; padding: 8px; border-radius: 5px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 5px; transition: 0.2s; }
    .action-btn:hover { background: rgba(255,255,255,0.05); }
    .action-btn.liked { color: #e41e3f; } /* Red Heart */
    
    .comment-section { margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px; }
    .comment-input-area { display: flex; gap: 10px; margin-bottom: 10px; }
    .comment-input { flex: 1; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 8px 12px; color: white; outline: none; }

    .view-all-comments {
        color: #b0b3b8;
        font-size: 13px;
        cursor: pointer;
        margin-bottom: 10px;
        display: block;
        font-weight: 500;
    }
    .view-all-comments:hover { text-decoration: underline; color: #fff; }

    .comment-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }
    .comment-bubble { background: rgba(255,255,255,0.1); padding: 8px 12px; border-radius: 15px; color: #e4e6eb; }
    .comment-bubble strong { display: block; color: #00ffaa; font-size: 12px; margin-bottom: 2px; }
    .comment-bubble p { margin: 0; }

    /* Comment Actions (Delete) */
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

    /* Notification Icons */
    .notif-icon-heart { color: #e41e3f; font-size: 16px; margin-right: 5px; }
    .notif-icon-comment { color: #00ffaa; font-size: 16px; margin-right: 5px; }

    /* Right Sidebar List Items Alignment Fix */
    .gc {
        display: flex;
        align-items: center;
        justify-content: space-between; /* Changed to space-between for better alignment of status dot */
        padding: 8px 10px;
        margin-bottom: 5px;
        border-radius: 8px;
        transition: background 0.2s;
        color: #b0b3b8;
        cursor: pointer;
        /* Holographic Entry */
        /* opacity: 0; Removed, animation will handle initial hidden state */
        animation: slideInHologram 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
    }
    .gc:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
    .gc img {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
        margin-right: 10px;
        flex-shrink: 0;
    }
    .gc span { font-size: 14px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .status { width: 8px; height: 8px; border-radius: 50%; background: #555; margin-left: auto; flex-shrink: 0; }
    .status.online { background: #00ffaa; animation: statusPulse 2s infinite; }

    /* Ensure animations start from hidden state */
    @keyframes slideInHologram {
        from { opacity: 0; transform: translateX(30px) scale(0.95); filter: blur(5px); }
        to { opacity: 1; transform: translateX(0) scale(1); filter: blur(0); }
    }

    /* --- RESPONSIVE & FLEXIBLE DESIGN --- */
    .mobile-menu-btn { display: none; font-size: 24px; cursor: pointer; color: var(--text-color); margin-right: 10px; padding: 5px; }
    .mobile-right-toggle { display: none; }

    /* Search Icon for Mobile */
    .search-icon-mobile { display: none; color: #b0b3b8; font-size: 18px; }

    /* Tablet & Small Laptop (Hide Right Sidebar) */
    @media (max-width: 900px) {
        .main { margin-right: 0 !important; }
        .post, .create-post { max-width: 100%; }
        .right-sidebar {
            display: none;
            position: fixed;
            right: 0;
            top: var(--header-h) !important;
            height: calc(100vh - var(--header-h)) !important;
            background: var(--bg-dark);
            width: 280px;
            z-index: 10001;
            box-shadow: -5px 0 15px rgba(0,0,0,0.5);
            overflow-y: auto;
            padding: 20px;
            border-left: 1px solid rgba(255,255,255,0.1);
        }
        .right-sidebar.active { display: block; animation: slideInRight 0.3s; }
        .mobile-right-toggle { display: flex !important; }
    }

    /* Mobile (Hide Left Sidebar & Adjust Layout) */
    @media (max-width: 900px) {
        .sidebar {
            display: none;
            position: fixed;
            left: 0;
            top: var(--header-h) !important;
            height: calc(100vh - var(--header-h)) !important;
            background: var(--bg-dark);
            width: 260px;
            z-index: 10001;
            box-shadow: 5px 0 15px rgba(0,0,0,0.5);
            overflow-y: auto;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar.active { display: block; animation: slideInLeft 0.3s; }
        .main { margin-left: 0 !important; width: 100% !important; padding: 10px; }
        .mobile-menu-btn { display: block; }
        .hd-logo { display: none; } /* Hide logo text on mobile to save space */
        
        /* Hide Clutter on Mobile */
        .create-gc-btn { display: none; }
        .header-gc-list { display: none !important; }
        .hd-profile span { display: none; }
        .hd-profile { padding: 0; background: transparent; }
        .hd-right { gap: 5px; }
        .hd-icon-btn { width: 35px; height: 35px; font-size: 16px; }
        .hd-icon-btn img { width: 20px; height: 20px; }

        /* Search Bar Mobile Optimization */
        .hd-search { width: 40px; padding: 0; justify-content: center; background: rgba(255,255,255,0.1); cursor: pointer; border-radius: 50%; transition: all 0.3s ease; }
        .search-icon-mobile { display: block; }
        .hd-search input { display: none; border: none; background: transparent; color: white; width: 100%; outline: none; }
        
        /* Expanded State */
        .hd-search.active { width: 220px; border-radius: 20px; justify-content: flex-start; padding: 0 15px; background: #1a3d2f; border: 1px solid #00ffaa; }
        .hd-search.active .search-icon-mobile { display: none; }
        .hd-search.active input { display: block; position: static; padding: 0; box-shadow: none; border-radius: 0; }
        
        /* Make modals take more width on mobile */
        .modal-content {
            width: 98%;
            max-width: 100%;
        }

        /* Creator Modal on Mobile */
        .creator-content {
            width: 95% !important;
            flex-direction: column;
            padding: 20px;
        }
        .creator-content.animating {
            width: 95% !important; /* Keep it from expanding */
        }
        .creator-content.animating .creator-profile-section,
        .creator-content.animating .creator-brand-section {
            width: 100%;
            padding: 0;
            border: none;
        }
        .creator-content.animating .creator-brand-section { padding-top: 20px; margin-top: 20px; border-top: 1px solid rgba(0, 255, 170, 0.2); }
        .neon-brand { font-size: 50px; }
        .connection-grid { display: none; } /* Hide complex animation on mobile */

        /* SacliRoom responsive */
        .sacli-room-container { flex-direction: column; }
        .sr-sidebar { position: static; width: 100%; }
        .search-results { top: 45px; width: 100%; left: 0; border-radius: 0 0 10px 10px; }

        /* Adjusted Grid for Mobile: 5 items per row horizontally */
        .student-grid, .alumni-grid, .ach-grid { 
            grid-template-columns: repeat(5, 1fr) !important; 
            gap: 5px;
            width: 100%;
        }
        
        /* Compact card styling to fit 5 items per row */
        .student-card, .alumni-card-premium, .ach-card {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            text-align: center !important;
            padding: 5px 2px !important;
            gap: 4px !important;
            min-height: auto !important;
            width: 100%;
        }

        .student-card img, .alumni-card-premium img, .ach-card img {
            width: 45px !important;
            height: 45px !important;
            margin-bottom: 2px !important;
            flex-shrink: 0;
            border-width: 1.5px !important;
        }

        .student-card h4, .alumni-name-premium, .ach-name {
            font-size: 9px !important;
            margin: 0 !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }

        .student-card p, .alumni-status-preview, .ach-desc {
            display: none !important; /* Hide text below name to save space */
        }
        
        .view-profile-btn { display: none !important; }
        .student-card .yr-badge { top: 4px; right: 2px; font-size: 6px; padding: 1px 3px; }

        /* Stats dashboard adjustment */
        .story-stats-container { gap: 8px; margin-bottom: 15px; }
        .story-stat-card { padding: 8px; min-height: 90px; }
        .story-stat-count { font-size: 22px; }
        .story-stat-label { font-size: 9px; }

        .total-count-banner h2 { font-size: 28px; }
        .course-header { font-size: 18px; margin-bottom: 15px; }
    }
    
    @keyframes slideInLeft { from { transform: translateX(-100%); } to { transform: translateX(0); } }
    @keyframes slideInRight { from { transform: translateX(100%); } to { transform: translateX(0); } }

    /* --- ADVANCED HOLOGRAM UI --- */
    .hologram-glow {
        text-shadow: 0 0 10px rgba(0, 255, 170, 0.8), 0 0 20px rgba(0, 255, 170, 0.5), 0 0 30px rgba(0, 255, 170, 0.3);
        color: #fff;
        letter-spacing: 4px !important;
    }
    
    .tech-border {
        position: relative;
        border: 1px solid rgba(0, 255, 170, 0.3);
    }
    .tech-border::before, .tech-border::after {
        content: ''; position: absolute; width: 15px; height: 15px; border-color: #00ffaa; border-style: solid;
    }
    .tech-border::before { top: -2px; left: -2px; border-width: 2px 0 0 2px; }
    .tech-border::after { bottom: -2px; right: -2px; border-width: 0 2px 2px 0; }

    .meeting-hud-stat {
        font-family: 'Courier New', Courier, monospace;
        font-size: 10px;
        color: rgba(0, 255, 170, 0.6);
        text-transform: uppercase;
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
    }

    .history-card {
        background: rgba(0, 20, 15, 0.4);
        border: 1px solid rgba(0, 255, 170, 0.2);
        border-radius: 5px;
        padding: 15px;
        margin-top: 20px;
        max-height: 250px;
        overflow-y: auto;
        box-shadow: inset 0 0 20px rgba(0, 255, 170, 0.05);
    }

    .history-item:hover {
        background: rgba(0, 255, 170, 0.1);
        transform: scale(1.02);
        transition: 0.3s;
    }

    /* --- MALUPIT NA MEETING UI STYLES --- */
    .meeting-container { 
        width: 100%; 
        height: calc(100vh - 100px); 
        animation: meetingEntrance 0.8s cubic-bezier(0.19, 1, 0.22, 1); 
        display: flex; 
        flex-direction: column; 
        position: relative;
        overflow: hidden;
        background: radial-gradient(circle at 50% 50%, #0a1f16 0%, #05100c 100%);
    }

    /* Hologram Scanlines Effect */
    .meeting-container::after {
        content: " ";
        position: absolute;
        top: 0; left: 0; bottom: 0; right: 0;
        background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 255, 170, 0.02) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.01), rgba(0, 255, 0, 0.005), rgba(0, 0, 255, 0.01));
        z-index: 5;
        background-size: 100% 4px, 3px 100%;
        pointer-events: none;
        animation: pulseScan 4s infinite linear;
    }

    @keyframes pulseScan {
        0% { opacity: 0.5; }
        50% { opacity: 0.8; }
        100% { opacity: 0.5; }
    }

    @keyframes meetingEntrance {
        from { opacity: 0; filter: blur(10px); transform: scale(1.05); }
        to { opacity: 1; filter: blur(0); transform: scale(1); }
    }

    .lobby-card {
        background: rgba(16, 46, 34, 0.4);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(0, 255, 170, 0.3);
        border-radius: 30px;
        padding: 40px;
        text-align: center;
        max-width: 550px;
        margin: auto;
        box-shadow: 0 25px 50px rgba(0,0,0,0.5), inset 0 0 20px rgba(0,255,170,0.1);
        position: relative;
        overflow: hidden;
    }

    .lobby-preview-box {
        width: 100%;
        aspect-ratio: 16/9;
        background: #000;
        border-radius: 20px;
        margin-bottom: 25px;
        border: 2px solid #00ffaa;
        overflow: hidden;
        position: relative;
    }
    .lobby-preview-box video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
    
    /* Scanning animation for lobby */
    .scanner-line {
        position: absolute; top: 0; left: 0; width: 100%; height: 4px;
        background: #00ffaa; box-shadow: 0 0 15px #00ffaa;
        animation: scanMove 3s linear infinite;
        z-index: 5; opacity: 0.6;
    }
    @keyframes scanMove { 0% { top: 0; } 100% { top: 100%; } }

    /* Meeting Room Layout */
    #meetingRoom { 
        height: 100%; 
        display: grid; 
        grid-template-columns: 1fr; 
        grid-template-rows: 60px 1fr 100px;
        transition: all 0.5s ease;
    }

    .meeting-top-bar {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0 30px; background: rgba(0,0,0,0.3); border-bottom: 1px solid rgba(255,255,255,0.05);
    }

    .meeting-main-area {
        display: flex; gap: 20px; padding: 20px; overflow: hidden;
    }

    .video-grid-container {
        flex: 1; display: grid; gap: 15px; 
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        align-content: center; justify-items: center;
    }

    /* Holographic Video Tile */
    .video-tile {
        background: rgba(10, 31, 22, 0.6);
        border-radius: 24px;
        overflow: hidden;
        position: relative;
        aspect-ratio: 16/9;
        border: 1px solid rgba(0, 255, 170, 0.2);
        box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        width: 100%; max-width: 600px;
        animation: tilePop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) backwards;
    }
    @keyframes tilePop { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    .video-tile video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
    
    .tile-overlay {
        position: absolute; inset: 0; 
        background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, transparent 30%);
        pointer-events: none; z-index: 2;
    }

    /* Animated Mic Indicator */
    .audio-visualizer {
        position: absolute; bottom: 20px; right: 20px;
        display: flex; gap: 3px; align-items: flex-end; height: 20px;
    }
    .bar-v { width: 3px; background: #00ffaa; border-radius: 2px; animation: barGrow 0.5s ease-in-out infinite alternate; }
    @keyframes barGrow { from { height: 4px; } to { height: 18px; } }

    /* Sidebars (Chat/People) */
    .meeting-sidebar {
        width: 320px; background: rgba(10, 31, 22, 0.8);
        border-left: 1px solid rgba(0, 255, 170, 0.2);
        display: none; flex-direction: column;
        border-radius: 20px; overflow: hidden;
        animation: slideInRight 0.4s ease;
    }

    /* Controls Overhaul */
    .control-dock {
        position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
        background: rgba(15, 30, 25, 0.85); backdrop-filter: blur(25px);
        border: 1px solid rgba(255,255,255,0.1);
        border-radius: 30px; padding: 12px 25px;
        display: flex; gap: 15px; align-items: center;
        box-shadow: 0 20px 50px rgba(0,0,0,0.6), 0 0 20px rgba(0,255,170,0.1);
        z-index: 1000;
    }

    .dock-btn {
        width: 52px; height: 52px; border-radius: 18px;
        background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
        color: #e4e6eb; cursor: pointer; transition: 0.3s;
        display: flex; align-items: center; justify-content: center;
    }
    .dock-btn:hover { background: rgba(0, 255, 170, 0.2); border-color: #00ffaa; transform: translateY(-5px); color: #00ffaa; }
    .dock-btn.active { background: #00ffaa; color: #0a1f16; box-shadow: 0 0 15px #00ffaa; }
    .dock-btn.danger { background: #ff4757; color: white; width: 70px; border-radius: 20px; }
    .dock-btn.danger:hover { background: #ff2e3e; box-shadow: 0 0 20px rgba(255, 71, 87, 0.5); }
    
    /* Reaction Animations */
    .reaction-fly {
        position: absolute; bottom: 100px; font-size: 30px;
        animation: flyUp 2s ease-out forwards; pointer-events: none; z-index: 2000;
    }
    @keyframes flyUp {
        0% { transform: translateY(0) scale(0.5); opacity: 0; }
        20% { opacity: 1; transform: translateY(-20px) scale(1.2); }
        100% { transform: translateY(-300px) translateX(var(--rx)) scale(1); opacity: 0; }
    }

    /* Rec Indicator */
    .rec-dot { width: 10px; height: 10px; background: #ff4757; border-radius: 50%; animation: recBlink 1s infinite; }
    @keyframes recBlink { 50% { opacity: 0.3; } }

    /* Mobile Optimizations */
    @media (max-width: 768px) {
        .video-grid-container { grid-template-columns: 1fr; }
        .control-dock { width: 95%; padding: 10px; gap: 8px; }
        .dock-btn { width: 40px; height: 40px; border-radius: 12px; }
        .dock-btn svg { width: 18px; }
        .meeting-top-bar { padding: 0 15px; }
        .meeting-sidebar { position: fixed; right: 0; top: 0; height: 100%; z-index: 2000; width: 85%; }
    }

    .event-meta-grid { display: flex; gap: 10px; margin-bottom: 20px; }
    
    .meta-item {
        flex: 1;
        background: rgba(255,255,255,0.03);
        padding: 12px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.05);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: 0.3s;
    }
    .meta-item:hover { background: rgba(0,255,170,0.05); border-color: rgba(0,255,170,0.3); transform: translateY(-2px); }
    
    .meta-icon { font-size: 22px; margin-bottom: 5px; filter: drop-shadow(0 0 5px rgba(0,255,170,0.5)); }
    .meta-item span:last-child { font-size: 12px; color: #b0fce0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .event-desc-box {
        background: rgba(0,0,0,0.4);
        padding: 15px;
        border-radius: 12px;
        border-left: 3px solid #00ffaa;
        text-align: left;
        max-height: 150px;
        overflow-y: auto;
    }
    
    #evModalDesc {
        margin: 0;
        color: #e4e6eb;
        font-size: 14px;
        line-height: 1.6;
        white-space: pre-wrap;
    }
    
    /* Scrollbar for desc */
    .event-desc-box::-webkit-scrollbar { width: 4px; }
    .event-desc-box::-webkit-scrollbar-thumb { background: #00ffaa; border-radius: 2px; }

    /* Dashboard Stats (Story Style) */
    .story-stats-container {
        display: flex;
        gap: 15px;
        justify-content: center;
        width: 100%;
        max-width: 680px;
        margin-bottom: 20px;
        animation: cyberStagger 1s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    .story-stat-card {
        flex: 1;
        min-height: 120px;
        border-radius: 15px;
        background: linear-gradient(135deg, #1a3d2f, #102e22);
        border: 1px solid rgba(0, 255, 170, 0.2);
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 15px;
        text-align: center;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        cursor: pointer;
        animation: staggeredPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    .story-stat-card:nth-child(1) { animation-delay: 0.1s; }
    .story-stat-card:nth-child(2) { animation-delay: 0.2s; }
    .story-stat-card:nth-child(3) { animation-delay: 0.3s; }

    .story-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 255, 170, 0.1);
        border-color: #00ffaa;
    }
    .story-stat-count {
        color: #fff;
        font-size: 36px;
        font-weight: 800;
        line-height: 1;
        z-index: 2;
    }
    .story-stat-label {
        color: #00ffaa;
        font-size: 14px;
        font-weight: 600;
        margin-top: 5px;
        z-index: 2;
    }
    .story-stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background-repeat: no-repeat;
        background-position: 0 0;
        background-image: conic-gradient(from 180deg at 50% 50%, rgba(0, 255, 170, 0.3) 0deg, rgba(0, 255, 170, 0) 60deg, rgba(0, 255, 170, 0) 360deg);
        animation: rotateGlow 5s linear infinite;
        opacity: 1;
        transition: opacity 0.3s;
        z-index: 1;
    }
    .story-stat-card:hover::before {
        opacity: 1;
    }

    /* Upcoming Event Card on Dashboard */
    .upcoming-event-card {
        height: 15%;
        min-height: 130px;
        width: 100%;
        max-width: 680px;
        background: linear-gradient(135deg, #004d33, #102e22);
        border: 1px solid #00ffaa;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        color: #fff;
        text-align: center;
        box-shadow: 0 5px 15px rgba(0, 255, 170, 0.1);
        transition: transform 0.3s, box-shadow 0.3s;
        animation: eventCardSlide 1s cubic-bezier(0.19, 1, 0.22, 1) both;
        position: relative;
        overflow: hidden;
    }
    .upcoming-event-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 255, 170, 0.2);
        border-color: #fff;
    }
    .upcoming-event-card::after {
        content: '';
        position: absolute;
        top: -50%; left: -50%; width: 200%; height: 200%;
        background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
        transform: rotate(45deg);
        animation: shimmer 3s infinite;
    }
    @keyframes shimmer {
        0% { transform: translateX(-100%) rotate(45deg); }
        100% { transform: translateX(100%) rotate(45deg); }
    }

    /* Special Animation for Post 134 */
    #post-134 {
        border: 2px solid #ffd700 !important;
        box-shadow: 0 0 40px rgba(255, 215, 0, 0.3) !important;
        animation: ultimateGlowFlow 5s linear infinite, featuredEntrance 1s ease-out !important;
        position: relative;
        z-index: 5;
    }
    #post-134::before {
        content: '★ FEATURED';
        position: absolute;
        top: -10px;
        left: 20px;
        background: #ffd700;
        color: #000;
        font-size: 10px;
        font-weight: 900;
        padding: 2px 10px;
        border-radius: 5px;
        z-index: 10;
        box-shadow: 0 0 15px #ffd700;
    }

    /* --- New Premium Keyframes --- */
    
    @keyframes premiumEntrance {
        0% { opacity: 0; transform: translateY(-30px) scale(0.95); filter: blur(10px) brightness(2); }
        100% { opacity: 1; transform: translateY(0) scale(1); filter: blur(0) brightness(1); }
    }

    @keyframes staggeredPop {
        0% { opacity: 0; transform: scale(0.5) translateY(40px); }
        100% { opacity: 1; transform: scale(1) translateY(0); }
    }

    @keyframes eventCardSlide {
        0% { opacity: 0; transform: translateX(100px) skewX(-10deg); filter: blur(5px); }
        100% { opacity: 1; transform: translateX(0) skewX(0); filter: blur(0); }
    }

    @keyframes cyberStagger {
        0% { opacity: 0; transform: translateX(-50px) skewX(10deg); }
        70% { transform: translateX(5px) skewX(-2deg); }
        100% { opacity: 1; transform: translateX(0) skewX(0); }
    }

    @keyframes featuredEntrance {
        0% { transform: scale(0.8); filter: brightness(5) gold; }
        100% { transform: scale(1); filter: brightness(1); }
    }

    @keyframes neonFlickerEntry {
        0% { opacity: 0; background: rgba(0, 255, 170, 0.2); }
        10% { opacity: 0.5; }
        15% { opacity: 0.2; }
        20% { opacity: 0.8; }
        25% { opacity: 0.3; }
        30% { opacity: 1; transform: scale(1.05); }
        100% { opacity: 1; transform: scale(1); }
    }

    @keyframes ultimateGlowFlow {
        0% { 
            border-color: #ffd700; 
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.4), inset 0 0 10px rgba(255, 215, 0, 0.1);
        }
        33% { 
            border-color: #00ffaa; 
            box-shadow: 0 0 40px rgba(0, 255, 170, 0.6), inset 0 0 20px rgba(0, 255, 170, 0.2);
        }
        66% { 
            border-color: #00ccff; 
            box-shadow: 0 0 40px rgba(0, 204, 255, 0.6), inset 0 0 20px rgba(0, 204, 255, 0.2);
        }
        100% { 
            border-color: #ffd700; 
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.4), inset 0 0 10px rgba(255, 215, 0, 0.1);
        }
    }

    /* --- End of New Keyframes --- */

    .upcoming-event-card h3 {
        margin: 0 0 5px 0;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: #00ffaa;
        font-weight: bold;
    }
    .upcoming-event-card h2 { margin: 0 0 10px 0; font-size: 24px; }
    .upcoming-event-card p { margin: 0; color: #b0fce0; font-size: 16px; }

    /* Alumni Page Grouping */
    .alumni-batch-header {
        font-size: 28px;
        color: #fff;
        font-weight: 800;
        margin-bottom: 25px;
        padding-bottom: 10px;
        border-bottom: 3px solid #00ffaa;
        text-align: left;
        width: 100%;
    }
    .alumni-course-group {
        margin-bottom: 35px;
        width: 100%;
    }

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
    .video-play-icon {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 60px;
        height: 60px;
        background: rgba(0,0,0,0.6);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
            pointer-events: auto;
            cursor: pointer;
        transition: opacity 0.3s ease, transform 0.3s ease, background 0.3s;
        backdrop-filter: blur(4px);
        border: 2px solid rgba(255,255,255,0.5);
        opacity: 0; /* Hidden by default */
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
    }
    .video-play-icon::before {
        content: '▶';
        margin-left: 5px;
    }
    .video-play-icon.playing::before {
        content: '❚❚';
        margin-left: 0;
    }
    .media-item:hover .video-play-icon {
        transform: translate(-50%, -50%) scale(1.1);
        background: rgba(0, 255, 170, 0.5);
        border-color: #fff;
        opacity: 1; /* Show on hover */
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
        -webkit-appearance: none;
        appearance: none;
        width: 100%;
        height: 4px;
        background: rgba(255,255,255,0.3);
        border-radius: 2px;
        outline: none;
        cursor: pointer;
        transition: height 0.1s;
    }
    .video-seek-slider:hover { height: 6px; }
    .video-seek-slider::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 12px;
        height: 12px;
        background: #00ffaa; /* Green */
        border-radius: 50%;
        cursor: pointer;
        transition: transform 0.2s;
        border: none;
    }
    .video-seek-slider:hover::-webkit-slider-thumb { transform: scale(1.3); }

    .video-bottom-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .video-time {
        color: #fff;
        font-size: 12px;
        font-family: sans-serif;
        font-weight: 500;
        text-shadow: 0 1px 2px rgba(0,0,0,0.5);
    }
    .video-volume-control {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .video-mute-button {
        width: 24px;
        height: 24px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        background: transparent;
        border: none;
        padding: 0;
    }
    .video-mute-button svg {
        width: 20px;
        height: 20px;
        fill: white;
        transition: fill 0.2s;
    }
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
        -webkit-appearance: none;
        appearance: none;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #00ffaa;
        cursor: pointer;
        border: 2px solid #fff;
    }
    .video-expand-btn {
        color: white;
        cursor: pointer;
        font-size: 18px;
        margin-left: 10px;
        display: flex;
        align-items: center;
    }
    .video-expand-btn:hover { color: #00ffaa; transform: scale(1.1); }
    .more-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0.6);
        color: #fff;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 28px;
        font-weight: bold;
        backdrop-filter: blur(2px);
    }

    /* NEW STYLES FOR GC MANAGEMENT LIST */
    .management-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
        width: 100%;
        max-width: 680px;
    }
    .management-item {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(228, 30, 63, 0.3);
        border-radius: 12px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: all 0.3s ease;
    }
    .management-item:hover {
        background: rgba(228, 30, 63, 0.1);
        border-color: #e41e3f;
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(228, 30, 63, 0.2);
    }
    .management-item-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e41e3f;
        flex-shrink: 0;
    }
    .management-item-info { flex-grow: 1; }
    .management-item-info h4 { margin: 0; font-size: 18px; color: #fff; font-weight: 600; }
    .management-item-action .leave-btn {
        background: #c9302c; color: white; border: none; padding: 8px 15px; border-radius: 20px;
        cursor: pointer; font-weight: bold; transition: all 0.2s; white-space: nowrap;
    }
    .management-item-action .leave-btn:hover {
        background: #e41e3f; box-shadow: 0 0 10px rgba(228, 30, 63, 0.5);
    }

    /* --- GLOBAL ANIMATIONS (Premium Effects) --- */
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes slideInHologram {
        from { opacity: 0; transform: translateX(30px) scale(0.95); filter: blur(5px); }
        to { opacity: 1; transform: translateX(0) scale(1); filter: blur(0); }
    }
    @keyframes statusPulse {
        0% { box-shadow: 0 0 0 0 rgba(0, 255, 170, 0.7); }
        70% { box-shadow: 0 0 0 10px rgba(0, 255, 170, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 255, 170, 0); }
    }
    @keyframes shimmerEffect {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }
    @keyframes slideInStagger {
        from { opacity: 0; transform: translateX(-20px); }
        to { opacity: 1; transform: translateX(0); }
    }
    @keyframes popInCard {
        0% { opacity: 0; transform: scale(0.9); }
        70% { transform: scale(1.02); }
        100% { opacity: 1; transform: scale(1); }
    }
    @keyframes pulseGlowBorder {
        0% { box-shadow: 0 0 0 0 rgba(0, 255, 170, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(0, 255, 170, 0); }
        100% { box-shadow: 0 0 0 0 rgba(0, 255, 170, 0); }
    }
    @keyframes rotateGlow {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Apply Animations to Elements */

    /* Sidebar Items Staggered Entrance */
    
    /* Main Content Elements */
    .initial-load .post { animation: fadeInUp 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) backwards; }
    .post:hover { 
        border-color: #00ffaa !important;
        box-shadow: 0 0 20px rgba(0, 255, 170, 0.2) !important;
        background: linear-gradient(90deg, #1a3d2f, #1e4d3a, #1a3d2f) !important;
        background-size: 200% 100% !important;
        animation: shimmerEffect 3s infinite linear !important;
    }

    .initial-load .create-post { animation: popInCard 0.6s ease-out backwards; }
    .story-stat-card { transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    .story-stat-card:hover { transform: scale(1.05) rotate(-1deg); }
    .initial-load .story-stat-card { animation: popInCard 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275) both; }
    .initial-load .story-stat-card:nth-child(1) { animation-delay: 0.1s; }
    .initial-load .story-stat-card:nth-child(2) { animation-delay: 0.2s; }
    .initial-load .story-stat-card:nth-child(3) { animation-delay: 0.3s; }
    
    .initial-load .upcoming-event-card { animation: fadeInUp 0.8s ease-out backwards; animation-delay: 0.4s; }
    .student-card { transition: transform 0.3s, box-shadow 0.3s; }
    .initial-load .student-card { animation: popInCard 0.5s ease-out backwards; }
    
    /* Right Sidebar Items */
    .initial-load .right-sidebar .gc { 
        animation: slideInStagger 0.5s ease-out backwards; 
    }
    .initial-load .right-sidebar .gc:nth-child(1) { animation-delay: 0.4s; }
    .initial-load .right-sidebar .gc:nth-child(2) { animation-delay: 0.45s; }
    .initial-load .right-sidebar .gc:nth-child(3) { animation-delay: 0.5s; }
    .initial-load .right-sidebar .gc:nth-child(4) { animation-delay: 0.55s; }

    /* Interactive Hover Effects */
    .create-post button:hover { animation: pulseGlowBorder 1.5s infinite; }
    
    /* Smooth Modal Transitions */
    .modal { transition: opacity 0.3s ease;   width: 100%;}
    .modal-content { width: 25%; animation: popInCard 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

    /* SETTINGS PANEL (Premium UI with Animation) */
    .settings-panel {
        width: 400px;
        position: fixed;
        top: var(--header-h);
        right: 0;
        height: calc(100vh - var(--header-h));
        background: rgba(10, 31, 22, 0.98);
        backdrop-filter: blur(15px);
        border-left: 1px solid rgba(0, 255, 170, 0.15);
        display: flex;
        flex-direction: column;
        z-index: 95;
        box-shadow: -10px 0 40px rgba(0,0,0,0.6);
        animation: slideInRightPanel 0.6s cubic-bezier(0.19, 1, 0.22, 1);
    }
    
    @keyframes slideInRightPanel {
        0% { transform: translateX(100%); opacity: 0; }
        1200% { transform: translateX(0); opacity: 1; }
    }

    .sp-half {
        flex: 1;
        overflow-y: auto;
        padding: 25px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        position: relative;
    }
    
    /* Smooth Scrollbar */
    .sp-half::-webkit-scrollbar { width: 5px; }
    .sp-half::-webkit-scrollbar-track { background: transparent; }
    .sp-half::-webkit-scrollbar-thumb { background: rgba(0, 255, 170, 0.2); border-radius: 10px; }
    .sp-half::-webkit-scrollbar-thumb:hover { background: rgba(0, 255, 170, 0.5); }

    .sp-title { 
        font-size: 14px; 
        font-weight: 800; 
        color: #00ffaa; 
        margin-bottom: 20px; 
        position: sticky; 
        top: 0; 
        background: rgba(10, 31, 22, 0.98); 
        padding: 15px 0; 
        z-index: 10; 
        border-bottom: 2px solid rgba(0, 255, 170, 0.3); 
        display: flex; 
        align-items: center; 
        gap: 10px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }

    /* Post Card Design */
    .sp-post-card {
        background: linear-gradient(145deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01));
        border: 1px solid rgba(255,255,255,0.05);
        border-radius: 15px;
        padding: 15px;
        margin-bottom: 15px;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        position: relative;
    }
    .sp-post-card:hover {
        transform: translateY(-5px) scale(1.02);
        background: linear-gradient(145deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02));
        border-color: rgba(0, 255, 170, 0.4);
        box-shadow: 0 10px 20px rgba(0,0,0,0.3);
    }
    .sp-post-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        border-bottom: 1px solid rgba(255,255,255,0.05);
        padding-bottom: 8px;
    }
    .sp-date { font-size: 11px; color: #aaa; font-style: italic; }
    .sp-content { 
        font-size: 13px; 
        color: #e4e6eb; 
        line-height: 1.5; 
        margin-bottom: 10px; 
        max-height: 80px; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        display: -webkit-box;
        -webkit-line-clamp: 3;
        line-clamp: 3;
        -webkit-box-orient: vertical;
    }
    .sp-delete-btn {
        background: transparent;
        color: #ff5555;
        border: 1px solid rgba(255, 85, 85, 0.3);
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.3s;
        text-transform: uppercase;
    }
    .sp-delete-btn:hover {
        background: #ff5555;
        color: white;
        box-shadow: 0 0 15px rgba(255, 85, 85, 0.5);
    }

    /* Group Card Design */
    .sp-group-card {
        display: flex;
        align-items: center;
        gap: 15px;
        background: rgba(255,255,255,0.02);
        padding: 12px;
        border-radius: 12px;
        border: 1px solid rgba(255,255,255,0.05);
        margin-bottom: 12px;
        transition: all 0.3s ease;
    }
    .sp-group-card:hover {
        background: rgba(0, 255, 170, 0.05);
        border-color: #00ffaa;
        transform: translateX(5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    .sp-group-img {
        width: 45px; height: 45px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid rgba(255,255,255,0.1);
        transition: 0.3s;
    }
    .sp-group-card:hover .sp-group-img { border-color: #00ffaa; transform: rotate(10deg); }
    
    .sp-group-info h4 { margin: 0; font-size: 14px; color: #fff; font-weight: 600; }
    .sp-leave-btn {
        margin-left: auto;
        background: rgba(255,255,255,0.05);
        color: #aaa;
        border: none;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 11px;
        cursor: pointer;
        transition: 0.3s;
        font-weight: 600;
    }
    .sp-leave-btn:hover {
        background: rgba(228, 30, 63, 0.2);
        color: #e41e3f;
    }

    .settings-mode .main { margin-right: 400px; } /* Adjust main content */

    /* ALUMNI MODAL STYLES (Premium/Gold Theme) */
    .alumni-modal-content {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        border: 1px solid rgba(255, 215, 0, 0.3);
        box-shadow: 0 0 50px rgba(255, 215, 0, 0.15), inset 0 0 20px rgba(255, 215, 0, 0.05);
        max-width: 450px;
        width: 90%;
        text-align: center;
        padding: 40px 30px;
        position: relative;
        overflow: hidden;
        border-radius: 25px;
        color: #fff;
        animation: popInCard 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    .alumni-modal-content::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,215,0,0.05) 0%, transparent 70%);
        animation: rotateGlow 10s linear infinite;
        z-index: 0;
        pointer-events: none;
    }

    .alumni-profile-wrapper { position: relative; z-index: 1; }

    .alumni-img-box {
        width: 140px; height: 140px; margin: 0 auto 20px;
        border-radius: 50%; padding: 4px;
        background: linear-gradient(45deg, #ffd700, #fdb931, #bf953f, #fdb931);
        background-size: 200% 200%;
        animation: gradientShift 3s ease infinite;
        box-shadow: 0 0 25px rgba(255, 215, 0, 0.3);
        position: relative;
    }
    
    .alumni-img-box img {
        width: 100%; height: 100%; border-radius: 50%; object-fit: cover;
        border: 4px solid #102e22;
        background: #000;
    }

    .alumni-badge {
        background: linear-gradient(90deg, #bf953f, #fdb931, #ffd700);
        color: #0a1f16;
        font-weight: 800;
        padding: 6px 18px;
        border-radius: 20px;
        display: inline-block;
        margin-bottom: 15px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }

    .alumni-name {
        font-size: 26px;
        font-weight: 800;
        margin: 0 0 5px;
        background: linear-gradient(to right, #fff, #ffd700, #fff);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        text-transform: uppercase;
        letter-spacing: 1px;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }

    .alumni-course {
        color: #b0c4de;
        font-size: 15px;
        margin-bottom: 20px;
        font-weight: 500;
        line-height: 1.4;
    }

    .alumni-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255,215,0,0.4), transparent);
        margin: 20px 0;
    }

    .alumni-status-box {
        background: rgba(0,0,0,0.2);
        border: 1px solid rgba(255,215,0,0.15);
        padding: 20px;
        border-radius: 15px;
        margin-top: 10px;
        position: relative;
    }
    
    .alumni-status-box::after {
        content: '"';
        position: absolute;
        top: 5px;
        left: 15px;
        font-size: 40px;
        color: rgba(255,215,0,0.1);
        font-family: serif;
    }

    .alumni-status-label {
        color: #ffd700;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 8px;
        display: block;
        font-weight: 700;
    }
    
    .alumni-status-text {
        color: #e4e6eb;
        font-style: italic;
        font-size: 15px;
        line-height: 1.6;
        margin: 0;
    }

    /* Alumni Card Click Animation */
    @keyframes alumniPress {
        0% { transform: scale(1); filter: brightness(1); }
        50% { transform: scale(0.92); filter: brightness(1.2); border-color: #ffd700; box-shadow: 0 0 25px rgba(255, 215, 0, 0.4); }
        100% { transform: scale(1); filter: brightness(1); }
    }
    .alumni-click-effect { animation: alumniPress 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

    /* Flash Animation for Highlighted Post */
    @keyframes flashPost {
        0% { 
            background-color: rgba(0, 255, 170, 0.1); 
            border-color: #00ffaa; 
            box-shadow: 0 0 0 0 rgba(0, 255, 170, 0.7); 
            transform: scale(1);
        }
        20% {
            background-color: rgba(0, 255, 170, 0.25);
            box-shadow: 0 0 30px 10px rgba(0, 255, 170, 0.5);
            transform: scale(1.03);
        }
        60% {
            background-color: rgba(0, 255, 170, 0.15);
            box-shadow: 0 0 15px 5px rgba(0, 255, 170, 0.3);
            transform: scale(1.01);
        }
        100% { 
            background-color: #1a3d2f; 
            border-color: rgba(0, 255, 170, 0.1); 
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); 
            transform: scale(1); 
        }
    }
    .flash-post {
        animation: flashPost 2.5s ease-in-out forwards !important;
        z-index: 5;
        position: relative;
    }

    /* Directory Filter Tabs (Login Style - Mini Version) */
    .dir-wrap {
        position: relative;
        display: flex;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 18px;
        padding: 3px;
        border: 1px solid rgba(0, 255, 170, 0.2);
        margin-bottom: 15px;
    }
    .dir-wrap input { display: none; }
    .dir-label {
        flex: 1;
        text-align: center;
        padding: 4px 0;
        font-size: 10px;
        font-weight: bold;
        color: #888;
        cursor: pointer;
        z-index: 2;
        transition: color 0.3s;
    }
    .dir-wrap input:checked + .dir-label { color: #0a1f16; }
    .dir-slidebar {
        position: absolute;
        top: 3px; left: 3px;
        width: calc((100% - 6px) / 3);
        height: calc(100% - 6px);
        background: #00ffaa;
        border-radius: 16px;
        z-index: 1;
        transition: transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    }
    .dir-wrap.two-cols .dir-slidebar { width: calc((100% - 6px) / 2); }
    .dir-rd-1:checked ~ .dir-slidebar { transform: translateX(0); }
    .dir-rd-2:checked ~ .dir-slidebar { transform: translateX(100%); }
    .dir-rd-3:checked ~ .dir-slidebar { transform: translateX(200%); }

    /* Add Member Modal Styles */
    .candidate-item { display: flex; align-items: center; gap: 10px; padding: 8px; border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.2s; }
    .candidate-item:hover { background: rgba(255,255,255,0.05); }
    .candidate-item img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; }
    .candidate-item .c-name { color: #e4e6eb; font-size: 14px; }

    /* File Attachment Style in Chat */
    .file-attachment {
        background: rgba(0, 255, 170, 0.15);
        border: 1px solid #00ffaa;
        padding: 8px 12px;
        border-radius: 10px;
        margin-top: 5px;
        cursor: pointer;
        display: flex; align-items: center; gap: 5px;
        font-size: 13px; color: #fff; transition: 0.2s;
    }
    .file-attachment:hover { background: rgba(0, 255, 170, 0.25); }

    /* Chat Message with Avatar */
    .msg-container { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; max-width: 85%; }
    .msg-container.mine { align-self: flex-end; flex-direction: row-reverse; }
    .msg-container.other { align-self: flex-start; flex-direction: row; }
    .msg-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid #00ffaa; flex-shrink: 0; }
    .msg-container .msg { align-self: auto; max-width: 100%; margin: 0; }

    /* Mention Dropdown */
    .mention-dropdown { position: absolute; background: #1a3d2f; border: 1px solid #00ffaa; width: 100%; max-height: 150px; overflow-y: auto; display: none; z-index: 1000; top: 100%; left: 0; border-radius: 0 0 10px 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.5); }
    .mention-item { padding: 10px; cursor: pointer; display: flex; align-items: center; gap: 10px; color: white; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .mention-item:hover { background: rgba(0, 255, 170, 0.2); }
    .mention-item img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid #00ffaa; }
    .mention-item span { font-size: 14px; font-weight: bold; }

    /* Fix for Create Post modal media preview overflow */
    .photo-preview {
        margin-top: 15px;
        padding: 10px;
        background: rgba(0,0,0,0.2);
        border-radius: 10px;
        border: 1px dashed rgba(0, 255, 170, 0.3);
        max-height: 240px; /* Increased height to show more files (around 3-4 rows) */
        overflow-y: auto; /* Add scrollbar if content overflows */
    }
    .photo-preview:empty { min-height: 85px; /* Give it some space when empty */ }
    
    /* Highlight tagged users in textarea (simulated via JS logic, but CSS for dropdown mostly) */
    #postContent { position: relative; z-index: 2; }

    /* Flash Message Animation */
    .flash-message {
        position: fixed;
        top: 100px;
        left: 50%;
        transform: translateX(-50%) translateY(-20px);
        background: rgba(0, 255, 170, 0.95);
        color: #0a1f16;
        padding: 15px 30px;
        border-radius: 50px;
        font-weight: bold;
        z-index: 20000;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        opacity: 0;
        transition: all 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        pointer-events: none;
        border: 1px solid #fff;
    }
    .flash-message.show {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    .flash-message.error {
        background: rgba(255, 85, 85, 0.95);
        color: white;
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
    
    /* Draggable Chat Head Style */
    .floating-chat-head {
        position: fixed;
        top: 80%;
        left: 20px;
        width: 65px;
        height: 65px;
        background: var(--bg-light);
        border: 2px solid var(--accent);
        border-radius: 50%;
        box-shadow: 0 8px 25px rgba(0,0,0,0.6);
        cursor: grab;
        z-index: 100005;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.3s;
    }
    .floating-chat-head:active { cursor: grabbing; transform: scale(0.95); }
    .floating-chat-head img { width: 75%; height: 75%; object-fit: contain; pointer-events: none; }

    /* Improved Comment UI */
    .comment-item { display: flex; gap: 10px; margin-bottom: 12px; font-size: 13px; align-items: flex-start; position: relative; transition: background 0.2s; padding: 5px; border-radius: 8px; }
    .comment-item:hover { background: rgba(255,255,255,0.02); }
    .comment-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1px solid #00ffaa; }
    .comment-bubble { background: rgba(255,255,255,0.08); padding: 8px 12px; border-radius: 18px; color: #e4e6eb; max-width: 85%; position: relative; }
    .comment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px; gap: 10px; }
    .comment-header strong { color: #00ffaa; font-size: 12px; }
    .comment-time { font-size: 10px; color: #aaa; }
    .comment-menu-item.delete { color: #ff5555; }
    
    /* Pinned Comment */
    .comment-item.pinned { background: rgba(255, 215, 0, 0.05); border-left: 3px solid #ffd700; }
    .pinned-label { font-size: 9px; background: #ffd700; color: #000; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-bottom: 4px; font-weight: bold; text-transform: uppercase; }

    /* Floating Hearts */
    .floating-heart { position: absolute; color: #e41e3f; font-size: 24px; user-select: none; pointer-events: none; animation: floatUp 1.5s ease-out forwards; z-index: 9999; }
    @keyframes floatUp { 0% { transform: translateY(0) scale(1); opacity: 1; } 50% { transform: translateY(-50px) scale(1.2); opacity: 0.8; } 100% { transform: translateY(-100px) scale(1.5); opacity: 0; } }

    /* Viewer Tagging Dropdown */
    .viewer-mention-dropdown { position: absolute; bottom: 60px; left: 15px; width: calc(100% - 30px); background: #1a3d2f; border: 1px solid #00ffaa; border-radius: 10px; max-height: 150px; overflow-y: auto; display: none; z-index: 20002; box-shadow: 0 -5px 20px rgba(0,0,0,0.5); }
    .viewer-mention-item { padding: 10px; cursor: pointer; display: flex; align-items: center; gap: 10px; color: white; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .viewer-mention-item:hover { background: rgba(0, 255, 170, 0.2); }
    .viewer-mention-item img { width: 25px; height: 25px; border-radius: 50%; }

    /* View Counter */
    .view-count-badge { display: inline-flex; align-items: center; gap: 4px; color: #b0b3b8; font-size: 12px; }
    .view-count-badge svg { width: 14px; height: 14px; fill: currentColor; }

    /* Highlight Tagged User */
    .tagged-user { color: #00ffaa; font-weight: bold; background: rgba(0, 255, 170, 0.1); padding: 0 4px; border-radius: 4px; }

    /* --- THEME STYLES --- */
    /* Halloween Theme */
    body.theme-halloween { background: #050202 !important; }
    body.theme-halloween::before { content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, rgba(0,0,0,0.6), rgba(20,0,0,0.9)), repeating-linear-gradient(45deg, rgba(20,0,0,0.1) 0px, rgba(20,0,0,0.1) 2px, transparent 2px, transparent 10px), radial-gradient(circle at 50% 50%, rgba(40,0,0,0.2), transparent 80%); background-size: cover; z-index: -2; pointer-events: none; }
    body.theme-halloween::after { content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at 50% 0%, rgba(255, 0, 0, 0.1), transparent 70%); opacity: 0.6; animation: lightningFlash 10s infinite; pointer-events: none; z-index: -2; mix-blend-mode: overlay; }
    @keyframes lightningFlash { 0%, 85% { opacity: 0.3; background-color: transparent; } 86% { opacity: 0.8; background-color: rgba(255, 0, 0, 0.15); } 87% { opacity: 0.3; background-color: transparent; } 92% { opacity: 0.3; background-color: transparent; } 93% { opacity: 1; background-color: rgba(255, 50, 50, 0.2); } 94% { opacity: 0.3; background-color: transparent; } 100% { opacity: 0.3; } }
    body.theme-halloween .sidebar, body.theme-halloween .right-sidebar, body.theme-halloween header { background: rgba(10, 5, 5, 0.95) !important; border-color: #800000 !important; }
    body.theme-halloween .main { background: transparent; }
    body.theme-halloween .post, body.theme-halloween .create-post, body.theme-halloween .hd-search, body.theme-halloween .hd-icon-btn, body.theme-halloween .dropdown-popover { background: rgba(20, 5, 5, 0.8) !important; border-color: #800000 !important; }
    body.theme-halloween .sidebar li:hover, body.theme-halloween .sidebar li.active-item { background: rgba(255, 85, 85, 0.15) !important; border-left-color: #ff5555 !important; }
    body.theme-halloween .sidebar li:hover, body.theme-halloween .sidebar li.active-item, body.theme-halloween .sidebar-header h2, body.theme-halloween .sidebar-header img, body.theme-halloween .sidebar li:hover img.icon2, body.theme-halloween .sidebar li.active-item img.icon2 { color: #ff5555 !important; filter: drop-shadow(0 0 5px #ff5555) !important; }
    body.theme-halloween ::-webkit-scrollbar-thumb { background: #ff5555; border-color: #050202; }

   
    body.theme-christmas { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #87ceeb 100%) !important; }
    body.theme-christmas::before { 
        content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-image: 
            radial-gradient(circle, #fff 1.2px, transparent 1.2px),
            radial-gradient(circle, #fff 1.8px, transparent 1.8px);
        background-size: 50px 50px, 120px 120px;
        animation: snow-fall 25s linear infinite;
        z-index: -2; pointer-events: none; opacity: 0.8;
    }
    @keyframes snow-fall {
        0% { background-position: 0 0, 0 0; }
        100% { background-position: 300px 800px, 150px 400px; }
    }
    body.theme-christmas .sidebar, body.theme-christmas .right-sidebar, body.theme-christmas header { background: rgba(135, 206, 235, 0.2) !important; border-color: #fff !important; backdrop-filter: blur(20px); }
    body.theme-christmas .main { background: transparent; }
    
    body.theme-christmas .post, body.theme-christmas .create-post, body.theme-christmas .hd-search, body.theme-christmas .hd-icon-btn, body.theme-christmas .dropdown-popover { background: rgba(255, 255, 255, 0.1) !important; border-color: #fff !important; backdrop-filter: blur(15px); }
    body.theme-christmas .post:hover { border-color: #87ceeb !important; background: rgba(255, 255, 255, 0.15) !important; }
    body.theme-christmas .sidebar li:hover, body.theme-christmas .sidebar li.active-item { background: rgba(255, 255, 255, 0.2) !important; color: #fff !important; }
    body.theme-christmas .sidebar li { color: #fff !important; }
    body.theme-christmas .sidebar-header h2 { background: linear-gradient(90deg, #fff, #87ceeb) !important; -webkit-background-clip: text !important; background-clip: text !important; -webkit-text-fill-color: transparent !important; }
    body.theme-new_year .sidebar li:hover, body.theme-new_year .sidebar li.active-item { background: rgba(255, 215, 0, 0.15) !important; border-left-color: #ffd700 !important; }
    body.theme-new_year .sidebar li:hover, body.theme-new_year .sidebar li.active-item, body.theme-new_year .sidebar-header h2, body.theme-new_year .sidebar-header img, body.theme-new_year .sidebar li:hover img.icon2, body.theme-new_year .sidebar li.active-item img.icon2 { color: #ffd700 !important; filter: drop-shadow(0 0 5px #ffd700) !important; }
    body.theme-new_year ::-webkit-scrollbar-thumb { background: #ffd700; border-color: #090a0f; }
    /* --- END THEME STYLES --- */

    @media (max-width: 900px) {
        .video-modal-content { flex-direction: column; }
        .video-section { height: 40%; flex: none; }
        .details-section { width: 100%; height: 60%; border-left: none; border-top: 1px solid rgba(255,255,255,0.1); }
    }

    
    /* SacliRoom */
    .sacli-room-hero {
        background: linear-gradient(135deg, #004d33, #102e22);
        border-radius: 20px;
        padding: 40px 20px;
        text-align: center;
        margin-bottom: 20px;
        border: 1px solid #00ffaa;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        position: relative;
        overflow: hidden;
    }
    .sacli-room-hero { width: 100%; max-width: 1200px; }
    .sacli-room-hero h2 {
        font-size: 3rem;
        margin: 0;
        background: linear-gradient(to right, #fff, #00ffaa, #fff);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 3px;
        text-shadow: 0 2px 10px rgba(0,0,0,0.5);
    }
    .sacli-room-hero p { color: #b0fce0; font-size: 1.1rem; margin-top: 10px; letter-spacing: 1px; }
    .sacli-room-container {
        display: flex;
        width: 100%;
        max-width: 1200px; /* Limit max width for large screens */
        gap: 20px;
        animation: fadeInUp 0.5s ease-out;
    }

    .sr-sidebar {
        flex: 0 0 240px;
        background: var(--bg-light);
        border-radius: 15px;
        padding: 15px;
        height: calc(100vh - 100px); /* 80px top offset + 20px bottom padding */
        position: sticky;
        top: 78px; /* Pantay sa top alignment ng main content area (58px header + 20px padding) */
    }

    .sr-sidebar-menu { list-style: none; padding: 0; margin: 0; }
    .sr-sidebar-menu li {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        margin-bottom: 5px;
        border-radius: 10px;
        cursor: pointer;
        transition: background 0.2s;
        color: #b0b3b8;
        font-weight: 500;
    }
    .sr-sidebar-menu li:hover { background: rgba(255,255,255,0.05); color: #fff; }
    .sr-sidebar-menu li.active { background: rgba(0, 255, 170, 0.15); color: #00ffaa; }
    .sr-sidebar-menu li svg {
        width: 22px;
        height: 22px;
        margin-right: 15px;
        stroke: currentColor;
        stroke-width: 1.5;               
        fill: none;
    }

    .sr-main-content {
        flex: 1;
    }

    .sr-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        gap: 10px;
    }

    .sr-btn {
        background: #00ffaa;
        color: #0a1f16;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .sr-btn:hover { background: #fff; }
    .sr-btn.join { background: transparent; border: 1px solid #00ffaa; color: #00ffaa; }
    .sr-btn.join:hover { background: rgba(0, 255, 170, 0.1); }

    .sr-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .sr-class-card {
        background: var(--bg-light);
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        border: 1px solid rgba(255,255,255,0.1);
        display: flex;
        flex-direction: column;
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
        z-index: 1;
    }

    .sr-class-card::before {
        content: "";
        position: absolute;
        bottom: -20px;
        right: -20px;
        width: 140px;
        height: 140px;
        background: url('St.Anne_logo.png') no-repeat center center;
        background-size: contain;
        opacity: 0.07;
        z-index: -1;
        pointer-events: none;
        transform: rotate(-15deg);
        animation: pulseCardLogo 10s infinite alternate;
    }

    @keyframes pulseCardLogo {
        from { transform: rotate(-15deg) scale(1); opacity: 0.07; }
        to { transform: rotate(-10deg) scale(1.1); opacity: 0.1; }
    }

    .sr-class-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    }

    .sr-card-header {
        height: 100px;
        padding: 15px;
        position: relative;
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .sr-card-header a { color: inherit; text-decoration: none; }
    .sr-card-title {
        font-size: 20px;
        font-weight: 600;
        text-shadow: 0 1px 3px rgba(0,0,0,0.4);
        z-index: 2;
    }
    .sr-card-teacher {
        font-size: 14px;
        font-weight: 500;
        text-shadow: 0 1px 3px rgba(0,0,0,0.4);
        z-index: 2;
    }
    .sr-card-avatar {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #fff;
        color: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 18px;
        z-index: 2;
    }

    .sr-card-content {
        flex-grow: 1;
        min-height: 80px;
    }

    .sr-card-footer {
        border-top: 1px solid rgba(255,255,255,0.1);
        padding: 8px;
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    .sr-card-footer svg {
        width: 24px;
        height: 24px;
        color: #b0b3b8;
        cursor: pointer;
        transition: color 0.2s;
    }
    .sr-card-footer a { color: inherit; }
    .sr-options-container {
        position: relative;
    }
    .sr-options-menu {
        display: none;
        position: absolute;
        bottom: 120%; /* Position above the icon */
        right: 0;
        background: #102e22;
        border: 1px solid #00ffaa;
        border-radius: 8px;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.5);
        padding: 5px;
        z-index: 10;
        width: 120px;
    }
    .sr-options-menu.show {
        display: block;
    }
    .sr-menu-item {
        padding: 8px 12px;
        cursor: pointer;
        color: #e4e6eb;
        border-radius: 5px;
        font-size: 14px;
    }
    .sr-menu-item:hover {
        background: rgba(255,255,255,0.1);
    }
    .sr-menu-item.delete:hover {
        background: #e41e3f;
        color: #fff;
    }
    .sr-card-footer svg:hover { color: #fff; }

    @media (max-width: 900px) {
        .sacli-room-container { flex-direction: column; }
        .sr-sidebar { position: static; width: 100%; }
    }

    /* Sidebar Menu Animations */
    .sidebar-scroll-area { position: relative; overflow-x: hidden; }
    
    .anim-slide-left-out { animation: slideOutLeft 0.3s forwards; }
    .anim-slide-right-in { animation: slideInRight 0.3s forwards; }
    .anim-slide-right-out { animation: slideOutRight 0.3s forwards; }
    .anim-slide-left-in { animation: slideInLeft 0.3s forwards; }

    @keyframes slideOutLeft { to { transform: translateX(-100%); opacity: 0; } }

    /* Floating Button para sa Zoom Mode (Un-zoom) */
    #focusModeBtnZoomed {
        position: fixed;
        top: 80px; /* Sa ibaba ng header */
        right: 30px;
        background: #00ffaa;
        border: 1px solid #00ffaa;
        color: #0a1f16;
        padding: 10px;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: none; /* Lilitaw lang via scrolled-past class */
        align-items: center;
        justify-content: center;
        width: 45px;
        height: 45px;
        z-index: 10005; /* Mas mataas sa header */
        box-shadow: 0 0 20px rgba(0, 255, 170, 0.4);
    }
    #focusModeBtnZoomed:hover { transform: scale(1.1); box-shadow: 0 0 30px rgba(0, 255, 170, 0.6); }
    body.scrolled-past #focusModeBtnZoomed { display: flex; }

    @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    /* --- PROFILE TRANSITION ANIMATION --- */
    .hd-profile.transitioning {
        pointer-events: none;
        animation: profilePulse 0.5s cubic-bezier(0.22, 1, 0.36, 1) forwards;
    }
    @keyframes profilePulse {
        0% { transform: scale(1); filter: brightness(1); }
        40% { transform: scale(0.9); filter: brightness(2) drop-shadow(0 0 10px #00ffaa); }
        100% { transform: scale(1.2); opacity: 0; filter: blur(10px); }
    }
    .body-exit-gate {
        transition: filter 0.6s ease, transform 0.6s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.4s ease;
        filter: blur(10px) brightness(1.5);
        transform: scale(1.05);
        opacity: 0.5;
        pointer-events: none;
    }

    @keyframes slideOutRight { to { transform: translateX(100%); opacity: 0; } }
    @keyframes slideInLeft { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
</style>

<header>
    <!-- Left: Logo & Search -->
    <div class="hd-left">
        <div class="mobile-menu-btn" onclick="toggleLeftSidebar()">☰</div>
        <img src="assets/images/St.Anne_logo.png" class="hd-logo" alt="Sacli Logo">
        
        <?php if(isset($_SESSION['admin_username'])): ?>
            <button onclick="window.location.href='admin/SACLICONNECT2.php'" style="margin-left:10px; padding:0 15px; height:36px; border-radius:20px; background:rgba(255,85,85,0.1); color:#ff5555; border:1px solid #ff5555; font-weight:bold; cursor:pointer; transition:0.2s; white-space:nowrap; font-size:13px;" onmouseover="this.style.background='#ff5555';this.style.color='#fff'" onmouseout="this.style.background='rgba(255,85,85,0.1)';this.style.color='#ff5555'">Back to Admin Control</button>
        <?php endif; ?>

        <div class="hd-search" style="position: relative;" onclick="toggleMobileSearch()">
            <span class="search-icon-mobile">🔍</span>
            <input type="text" id="headerSearch" placeholder="Search students..." autocomplete="off" onclick="event.stopPropagation()">
            <div id="searchResults" class="search-results"></div>
        </div>
        <button class="create-gc-btn" onclick="openCreateGroupModal()">+ Create GC</button>

        <!-- Display Group Chats in Header -->
        <div style="display:flex; gap:8px; margin-left:15px; overflow-x:auto; max-width:400px; align-items:center; padding-bottom:2px;" class="header-gc-list">
            <?php
            $header_groups = $conn->query("SELECT g.id, g.name, g.group_icon FROM group_chats g JOIN group_chat_members m ON g.id = m.group_id WHERE m.user_id = '$my_id' ORDER BY g.created_at DESC");
            if($header_groups){
                while($hg = $header_groups->fetch_assoc()){
                    $hg_pic = !empty($hg['group_icon']) ? "uploads/".$hg['group_icon'] : "7icons8-organization-64.png";
                    $hg_name = htmlspecialchars($hg['name'], ENT_QUOTES);
                    echo '<div onclick="openGroupChat('.$hg['id'].', \''.$hg_name.'\', \''.$hg_pic.'\')" title="'.$hg_name.'" style="cursor:pointer; width:36px; height:36px; border-radius:50%; border:1px solid #00ffaa; overflow:hidden; flex-shrink:0; transition:0.2s;" onmouseover="this.style.transform=\'scale(1.1)\'" onmouseout="this.style.transform=\'scale(1)\'">
                            <img src="'.$hg_pic.'" style="width:100%; height:100%; object-fit:cover;">
                          </div>';
                }
            }
            ?>
        </div>
    </div>

    <!-- Right: Profile & Menu -->
    <div class="hd-right">
        <div class="hd-profile" id="headerProfileBtn" onclick="navigateToProfile(this)">
            <img src="<?php echo $my_pic; ?>" alt="User">
            <div style="display: flex; flex-direction: column; align-items: flex-start; justify-content: center;">
                <span style="line-height: 1.2;"><?php echo htmlspecialchars($_SESSION['student_name']); ?></span>
                <small style="font-size: 10px; color: var(--accent); font-weight: bold; opacity: 0.8; line-height: 1;"><?php echo htmlspecialchars($user_sub_info); ?></small>
            </div>
        </div>
        
        <div class="hd-icon-btn mobile-right-toggle" onclick="toggleRightSidebar()">=</div>
        
        <div class="icon-wrapper" onclick="toggleDropdown('groupDropdown'); loadMyGroups();">
            <div class="hd-icon-btn group-btn">
                <div class="shimmer"></div>
                <img src="group.png" alt="group">
            </div>
            <span class="badge-count" id="groupBadge" style="display:none;">0</span>
        </div>
        
        <div class="icon-wrapper" onclick="toggleDropdown('msgDropdown'); loadMessagesDropdown(); checkNewMessages();">
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
        
        <!-- New Concern Button -->
        <div class="icon-wrapper" onclick="openConcernChat()" title="Tell a Concern">
            <div class="hd-icon-btn concern-btn">
                <div class="shimmer"></div>
                <img src="important.png" alt="Help">
            </div>
            <span class="badge-count" id="concernBadge" style="display:none;">0</span>
        </div>
    </div>
</header>

<!-- Dropdown Menus (Hidden by default) -->
<div id="groupDropdown" class="dropdown-popover" style="right: 160px;">
    <div class="dp-header">Group Chats</div>
    <div class="dp-item" onclick="openCreateGroupModal()" style="color:#00ffaa; justify-content:center; font-weight:bold;">+ Create New Group</div>
    <div id="groupListContainer" style="max-height:300px; overflow-y:auto;"></div>
</div>

<div id="msgDropdown" class="dropdown-popover" style="right: 110px;">
    <div class="dp-header">Messages</div>
    <div class="dp-item"><img src="assets/images/4icons8-teacher-50.png" style="width:30px;border-radius:50%;"> <div><strong>Mr. Smith</strong><small>Please pass your assignment.</small></div></div>
    <a href="SacliChat_Full.php" class="dp-item" style="text-decoration: none; color: inherit;"><img src="communication.png" style="width:30px;border-radius:50%;"> <div><strong>Messenger open</strong><small>Click to open messenger</small></div></a>
    <div id="msgListContainer" style="max-height:300px; overflow-y:auto;">
        <div style="padding:15px; text-align:center; color:#aaa;">Loading...</div>
    </div>
</div>

<div id="notifDropdown" class="dropdown-popover" style="right: 60px;">
    <div class="dp-header">Notifications</div>
    <div class="dp-item">📢 <div><strong>New Announcement</strong><small>Classes suspended tomorrow.</small></div></div>
    <div class="dp-item">✅ <div><strong>Post Approved</strong><small>Your post is now live.</small></div></div>
    <div class="dp-item">🎉 <div><strong>Welcome!</strong><small>Thanks for joining SacliConnect.</small></div></div>
    <div id="notifListContainer" style="max-height:300px; overflow-y:auto;">
        <div style="padding:15px; text-align:center; color:#aaa;">Loading...</div>
    </div>
</div>




<?php
// Load sidebar menu from DB (admin-editable)
$sidebarItems = [];
$sm = $conn->query("SELECT * FROM sidebar_menu GROUP BY label ORDER BY sort_order, id");
if ($sm && $sm->num_rows > 0) {
    while ($r = $sm->fetch_assoc()) $sidebarItems[] = $r;
}
if (empty($sidebarItems)) { 
    $sidebarItems = [
        ['label'=>'Dashboard','icon'=>'analytics.png'],
        ['label'=>'Announcements','icon'=>'2icons8-announcement-50.png'],
        ['label'=>'Students','icon'=>'assets/images/3icons8-student-64.png'],
        ['label'=>'Teachers','icon'=>'4icons8-teacher-50.png'],
        ['label'=>'Alumni','icon'=>'book1.png'],
        ['label'=>'Achievements','icon'=>'5icons8-assignment-50.png'],
        ['label'=>'Calendar','icon'=>'6icons8-calendar-50.png'],
        ['label'=>'Organizations','icon'=>'7icons8-organization-64.png'],
        ['label'=>'Settings','icon'=>'8icons8-setting-50.png'],
    ];
}

$is_alumni_posts_page = (isset($_GET['page']) && ($_GET['page'] == 'alumni_posts' || $_GET['page'] == 'evaluates' || $_GET['page'] == 'sacli_room' || $_GET['page'] == 'meeting' || $_GET['page'] == 'sc_storage'));
?>
<!-- ================= Sidebar ================= -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="Adobe Express - file.png" alt="Logo">
        <h2>SACLICONNECT</h2>
    </div>
    
    <div class="dir-wrap two-cols" style="margin: -25px 15px 20px;">
        <input <?php echo !$is_alumni_posts_page ? 'checked' : ''; ?> type="radio" id="sb-menu-1" name="sb-filter" class="dir-rd-1" onchange="toggleSidebarMenu(1)">
        <label for="sb-menu-1" class="dir-label">Menu 1</label>
        <input <?php echo $is_alumni_posts_page ? 'checked' : ''; ?> type="radio" id="sb-menu-2" name="sb-filter" class="dir-rd-2" onchange="toggleSidebarMenu(2)">
        <label for="sb-menu-2" class="dir-label">Menu 2</label>
        <div class="dir-slidebar"></div>
    </div>

    <div class="sidebar-scroll-area">
    <ul id="sb-content-1" style="<?php echo $is_alumni_posts_page ? 'display: none;' : ''; ?>">
        <?php foreach ($sidebarItems as $item): ?>
        <?php 
            // Determine link based on label
            $link = "SacliConnect.php"; // Default Dashboard (Show All)
            $activeClass = "";
            if (trim($item['label']) === 'Announcements') {
                $link = "SacliConnect.php?category=Announcement";
                if(isset($_GET['category']) && $_GET['category'] == 'Announcement') $activeClass = "active-item";
            } else if (trim($item['label']) === 'Students') {
                $link = "SacliConnect.php?page=students";
                if(isset($_GET['page']) && $_GET['page'] == 'students') $activeClass = "active-item";
            } else if (trim($item['label']) === 'Teachers') {
                $link = "SacliConnect.php?page=teachers";
                if(isset($_GET['page']) && $_GET['page'] == 'teachers') $activeClass = "active-item";
            } else if (trim($item['label']) === 'Calendar') {
                $link = "SacliConnect.php?page=calendar";
                if(isset($_GET['page']) && $_GET['page'] == 'calendar') $activeClass = "active-item";
            } else if (trim($item['label']) === 'Alumni' || trim($item['label']) === 'Alumni') {
                $link = "SacliConnect.php?page=alumni";
                if(isset($_GET['page']) && $_GET['page'] == 'alumni') $activeClass = "active-item";
            } else if (trim($item['label']) === 'Assignments' || trim($item['label']) === 'Achievements') {
                $link = "SacliConnect.php?page=achievements";
                if(isset($_GET['page']) && $_GET['page'] == 'achievements') $activeClass = "active-item";
            } else if (trim($item['label']) === 'Settings') {
                $link = "SacliConnect.php?page=my_posts";
                if(isset($_GET['page']) && $_GET['page'] == 'my_posts') $activeClass = "active-item";
            } else if (trim($item['label']) === 'History and Password') {
                $link = "SacliConnect.php?page=security";
                if(isset($_GET['page']) && $_GET['page'] == 'security') $activeClass = "active-item";
            } else if (trim($item['label']) === 'Dashboard') {
                if(!isset($_GET['category']) && !isset($_GET['page'])) $activeClass = "active-item";
            }
        ?>
        <li onclick="window.location.href='<?php echo $link; ?>'" class="<?php echo $activeClass; ?>" style="cursor:pointer;">
            <div class="sidebar-icon-box">
                <?php if ($item['icon'] !== '') { ?><img class="icon2" src="<?php echo htmlspecialchars($item['icon']); ?>" alt=""><?php } ?>
            </div>
            <?php echo htmlspecialchars($item['label']); ?>
        </li>
        <?php endforeach; ?>
        
        <!-- Creator Info Item -->
        <li onclick="openCreatorModal()" style="cursor:pointer;">
            <div class="sidebar-icon-box">
                <img class="icon2" src="coding.png" alt="Creator">
            </div>
            Creator Sacli Connect
        </li>
    </ul>
    
    <ul id="sb-content-2" style="<?php echo $is_alumni_posts_page ? 'display: block;' : 'display: none;'; ?>">
        <!-- Alumni Posts Item -->
        <li onclick="window.location.href='SacliConnect.php?page=alumni_posts'" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'alumni_posts') ? 'active-item' : ''; ?>" style="cursor:pointer;">
            <div class="sidebar-icon-box">
                <img class="icon2" src="post.png" alt="Alumni Posts">
            </div>
            Alumni Posts
        </li>
        <?php if ($user_type !== 'teacher' && $user_type !== 'admin'): ?>
        <li onclick="window.location.href='SacliConnect.php?page=evaluates'" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'evaluates') ? 'active-item' : ''; ?>" style="cursor:pointer;">
            <div class="sidebar-icon-box">
                <img class="icon2" src="checklist.png" alt="Evaluates">
            </div>
            Evaluates
        </li>
        <?php endif; ?>
        <?php if ($user_type !== 'admin'): ?>
        <li onclick="window.location.href='SacliConnect.php?page=sacli_room'" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'sacli_room') ? 'active-item' : ''; ?>" style="cursor:pointer;">
            <div class="sidebar-icon-box">
                <img class="icon2" src="folder.png" alt="SacliRoom">
            </div>
            SacliRoom
        </li>
        <?php endif; ?>
        <li onclick="window.location.href='SacliConnect.php?page=meeting'" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'meeting') ? 'active-item' : ''; ?>" style="cursor:pointer;">
            <div class="sidebar-icon-box">
                <img class="icon2" src="video-calling.png" onerror="this.src='communication.png'" alt="Meeting">
            </div>
            Meeting
        </li>
        <li onclick="window.location.href='SC_Storage.php'" class="<?php echo (isset($_GET['page']) && $_GET['page'] == 'sc_storage') ? 'active-item' : ''; ?>" style="cursor:pointer;">
            <div class="sidebar-icon-box">
                <img class="icon2" src="folder.png" onerror="this.src='5icons8-assignment-50.png'" alt="SC Storage">
            </div>
            SC Storage
        </li>
    </ul>
    </div>
    
<footer>
        &copy; 2026 SACLI CONNECT---------------- // CREATE BY: Justin Ritardo
    </footer>
</div>

<?php
$is_settings_page = (isset($_GET['page']) && ($_GET['page'] == 'my_posts' || $_GET['page'] == 'security'));
?>

<!-- ================= Main Content ================= -->
<div class="main <?php echo $is_settings_page ? 'settings-mode' : ''; ?>">
    <!-- Floating Button na lalabas lang kapag naka-zoom (Focus Mode) -->
    <button id="focusModeBtnZoomed" onclick="toggleFocusMode()" title="Exit Focus Mode (Un-zoom)">
        <svg class="icon-maximize" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
        </svg>
        <svg class="icon-minimize" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path>
        </svg>
    </button>

    <?php if(isset($_GET['page']) && $_GET['page'] == 'students'): ?>
        <!-- STUDENTS DIRECTORY VIEW -->
         <?php
        // 1. Get Total Count
        $count_res = $conn->query("SELECT COUNT(*) as total FROM students WHERE is_alumni = 0");
        $total_students = $count_res->fetch_assoc()['total'];
        ?>
        
        <div class="total-count-banner">
            <h2><?php echo $total_students; ?></h2>
            <span>Registered Students & Teachers</span>
        </div>

        <?php
        // 2. Fetch Students Grouped by Course & Sorted by Year
        // We fetch all and group in PHP to handle dynamic courses easily
        $s_sql = "SELECT * FROM students WHERE is_alumni = 0 ORDER BY course ASC, year_level ASC, student_name ASC";
        $s_res = $conn->query($s_sql);
        
        $grouped_students = [];
        while($row = $s_res->fetch_assoc()){
            $c = strtoupper(trim($row['course']));
            if(empty($c)) $c = "UNASSIGNED";
            $grouped_students[$c][] = $row;
        }

        // Display Groups
        foreach($grouped_students as $course => $students):
        ?>
        <div class="course-section">
            <div class="course-header"><?php echo htmlspecialchars($course); ?></div>
            <div class="student-grid">
                <?php foreach($students as $s): 
                    $pic = !empty($s['profile_pic']) ? "uploads/".$s['profile_pic'] : "assets/images/3icons8-student-64.png";
                ?>
                <div class="student-card" onclick="window.location.href='Student_Profile.php?id=<?php echo $s['student_id']; ?>'">
                    <div class="yr-badge"><?php echo htmlspecialchars($s['year_level']); ?></div>
                    <img src="<?php echo $pic; ?>" alt="Pic">
                    <h4><?php echo htmlspecialchars($s['student_name']); ?></h4>
                    <p><?php echo htmlspecialchars($s['course']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    <?php elseif(isset($_GET['page']) && $_GET['page'] == 'teachers'): ?>
        <!-- TEACHERS DIRECTORY VIEW -->
        <?php
        // 1. Get Total Count
        $count_res = $conn->query("SELECT COUNT(*) as total FROM teachers");
        $total_teachers = $count_res->fetch_assoc()['total'];
        ?>
        
        <div class="total-count-banner" style="border-color: #00ffaa; box-shadow: 0 0 15px rgba(0, 255, 170, 0.2);">
            <h2><?php echo $total_teachers; ?></h2>
            <span style="color: #00ffaa;">Faculty Members</span>
        </div>

        <?php
        // 2. Fetch Teachers
        $t_res = $conn->query("SELECT * FROM teachers ORDER BY department ASC, name ASC");
        
        $grouped_teachers = [
            'Elementary Department' => [], 
            'Junior & Senior High School' => [], 
            'College Department' => []
        ];
        
        if($t_res) {
            while($row = $t_res->fetch_assoc()){
                $dept = $row['department'];
                if(stripos($dept, 'Elementary') !== false) $grouped_teachers['Elementary Department'][] = $row;
                elseif(stripos($dept, 'JHS') !== false || stripos($dept, 'SHS') !== false || stripos($dept, 'High') !== false) $grouped_teachers['Junior & Senior High School'][] = $row;
                else $grouped_teachers['College Department'][] = $row;
            }
        }

        foreach($grouped_teachers as $dept => $teachers):
        ?>
        <div class="course-section">
            <div class="course-header" style="color: #00ffaa; border-color: rgba(0, 255, 170, 0.3) ;"><?php echo htmlspecialchars($dept); ?></div>
            <div class="student-grid">
                <?php foreach($teachers as $t): $pic = !empty($t['profile_pic']) ? "uploads/".$t['profile_pic'] : "4icons8-teacher-50.png"; ?>
                <div class="student-card" style="border-color: rgba(0, 255, 170, 0.3) ;" onclick="window.location.href='Student_Profile.php?id=T-<?php echo $t['id']; ?>'">
                    <div class="yr-badge" style="background: #00ffaa; color: #000;"><?php echo htmlspecialchars($t['department']); ?></div>
                    <img src="<?php echo $pic; ?>" alt="Pic" style="border-color: #00ffaa;">
                    <h4><?php echo htmlspecialchars($t['name']); ?></h4>
                    <p style="color: #b0fce0;"><?php echo htmlspecialchars($t['position']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    <?php elseif(isset($_GET['page']) && $_GET['page'] == 'alumni'): ?>
        <!-- ALUMNI VIEW -->
        <style>
            /* Premium Alumni Interface Styles */
            .alumni-view-container { animation: fadeIn 0.5s ease-out; width: 100%; }
            .alumni-hero {
                background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
                border-radius: 20px;
                padding: 40px 20px;
                text-align: center;
                margin-bottom: 40px;
                border: 1px solid rgba(255, 215, 0, 0.3);
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                position: relative;
                overflow: hidden;
            }
            .alumni-hero::before {
                content: '';
                position: absolute;
                top: -50%; left: -50%; width: 200%; height: 200%;
                background: radial-gradient(circle, rgba(255,215,0,0.05) 0%, transparent 60%);
                animation: rotateGlow 15s linear infinite;
                pointer-events: none;
            }
            .alumni-hero h2 {
                font-size: 3rem;
                margin: 0;
                background: linear-gradient(to right, #bf953f, #fdb931, #ffd700, #fdb931, #bf953f);
                -webkit-background-clip: text;
                background-clip: text;
                -webkit-text-fill-color: transparent;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: 3px;
                text-shadow: 0 2px 10px rgba(0,0,0,0.5);
            }
            .alumni-hero p { color: #b0c4de; font-size: 1.1rem; margin-top: 10px; letter-spacing: 1px; }
            
            .batch-container { margin-bottom: 50px; width: 100%; }
            .batch-title {
                font-size: 1.8rem;
                color: #ffd700;
                font-weight: 800;
                margin-bottom: 25px;
                display: flex;
                align-items: center;
                text-transform: uppercase;
                letter-spacing: 2px;
            }
            .batch-title::after {
                content: ''; flex: 1; height: 2px;
                background: linear-gradient(to right, #ffd700, transparent);
                margin-left: 20px; opacity: 0.5;
            }
            
            .course-group { margin-bottom: 30px; padding-left: 15px; border-left: 2px solid rgba(255, 215, 0, 0.2); }
            .course-title { font-size: 1.2rem; color: #fff; margin-bottom: 15px; font-weight: 600; opacity: 0.9; }
            
            .alumni-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; }
            
            .alumni-card-premium {
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 215, 0, 0.1);
                border-radius: 15px;
                padding: 20px;
                text-align: center;
                transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                cursor: pointer;
                position: relative;
                overflow: hidden;
                user-select: none;
            }
            .alumni-card-premium:hover {
                transform: translateY(-10px);
                background: rgba(255, 215, 0, 0.08);
                border-color: rgba(255, 215, 0, 0.5);
                box-shadow: 0 15px 30px rgba(0,0,0,0.4);
            }
            .alumni-card-premium img {
                width: 90px; height: 90px; border-radius: 50%; object-fit: cover;
                border: 3px solid #ffd700; margin-bottom: 15px;
                box-shadow: 0 0 20px rgba(255, 215, 0, 0.2); transition: transform 0.3s;
            }
            .alumni-card-premium:hover img { transform: scale(1.05); box-shadow: 0 0 25px rgba(255, 215, 0, 0.4); }
            .alumni-name-premium { color: #fff; font-size: 1rem; font-weight: 700; margin: 0 0 5px; }
            .alumni-age-premium { color: #888; font-size: 0.85rem; margin-bottom: 15px; }
            .alumni-status-preview {
                font-size: 0.9rem; color: #b0c4de; font-style: italic; line-height: 1.4;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden; opacity: 0.8;
            }
            .view-profile-btn {
                margin-top: 15px; background: transparent; border: 1px solid #ffd700;
                color: #ffd700; padding: 8px 20px; border-radius: 20px;
                font-size: 0.8rem; font-weight: bold; text-transform: uppercase;
                letter-spacing: 1px; transition: 0.3s; display: inline-block;
            }
            .alumni-card-premium:hover .view-profile-btn { background: #ffd700; color: #000; }
        </style>

        <div class="alumni-view-container">
        <?php
        // 1. Get Total Count
        $count_res = $conn->query("SELECT COUNT(*) as total FROM alumni");
        $alumni_count = $count_res->fetch_assoc()['total'];

        // Fetch Analytics Data for Chart
        $an_years = [];
        $an_counts = [];
        $an_details = [];
        
        $an_res = $conn->query("SELECT batch_year, course, COUNT(*) as c FROM alumni GROUP BY batch_year, course ORDER BY batch_year ASC, c DESC");
        $temp_stats = [];

        if($an_res){
            while($r = $an_res->fetch_assoc()) {
                $yr = $r['batch_year'] ? "Batch ".$r['batch_year'] : "Unknown";
                if(!isset($temp_stats[$yr])) $temp_stats[$yr] = ['total'=>0, 'courses'=>[]];
                $temp_stats[$yr]['total'] += $r['c'];
                $temp_stats[$yr]['courses'][] = ['name' => $r['course'] ? $r['course'] : 'Unspecified', 'count' => $r['c']];
            }
        }
        foreach($temp_stats as $yr => $data) {
            $an_years[] = $yr;
            $an_counts[] = $data['total'];
            $an_details[$yr] = $data['courses'];
        }
        ?>
        
        <div class="alumni-hero">
            <h2>Hall of Alumni</h2>
            <p>Celebrating <?php echo $alumni_count; ?> Graduates of Excellence</p>
            <button onclick="openAnalyticsModal()" style="margin-top: 20px; background: linear-gradient(90deg, #bf953f, #fdb931); color: #0a1f16; padding: 10px 25px; border: none; border-radius: 25px; font-weight: bold; cursor: pointer; box-shadow: 0 5px 15px rgba(0,0,0,0.3); transition: transform 0.2s; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">View Alumni Analysis</button>
        </div>

        <!-- FULLSCREEN ANALYTICS MODAL -->
        <div id="analyticsModal" style="display:none; position:fixed; z-index:20000; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); backdrop-filter:blur(5px); animation: fadeIn 0.3s ease;">
            <div style="position:relative; width:100%; height:100%; padding:30px; display:flex; flex-direction:column; box-sizing:border-box;">
                <span onclick="document.getElementById('analyticsModal').style.display='none'" style="position:absolute; top:20px; right:30px; font-size:40px; color:#fff; cursor:pointer; z-index:100; transition:0.2s;" onmouseover="this.style.color='#ff5555'">&times;</span>
                
                <h2 id="anTitle" style="color:#00ffaa; text-align:center; font-family:'Segoe UI', sans-serif; text-transform:uppercase; letter-spacing:2px; margin:0 0 20px 0; text-shadow:0 0 10px rgba(0,255,170,0.5);">Alumni Graduates Analytics</h2>
                
                <!-- Chart Container (Filled Height/Width) -->
                <div id="anChartContainer" style="flex:1; width:100%; background: linear-gradient(135deg, #1a3d2f, #0a1f16); border: 1px solid #00ffaa; border-radius:15px; padding:20px; position:relative; box-shadow: 0 0 30px rgba(0,255,170,0.1); transition: 0.5s;">
                    <canvas id="alumniChart"></canvas>
                </div>

                <!-- Detailed View (Hidden by default) -->
                <div id="anDetailView" style="display:none; flex:1; width:100%; background: linear-gradient(135deg, #1a3d2f, #0a1f16); border: 1px solid #00ffaa; border-radius:15px; padding:40px; position:relative; overflow-y:auto; animation: zoomIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);">
                    <button onclick="closeAnDetails()" style="position:absolute; top:20px; left:20px; background:transparent; border:1px solid #00ffaa; color:#00ffaa; padding:8px 20px; border-radius:20px; cursor:pointer; font-weight:bold; display:flex; align-items:center; gap:5px;">&larr; Back to Graph</button>
                    
                    <div style="text-align:center; margin-top:10px;">
                        <h1 id="anDetailBatch" style="color:#fff; font-size:48px; margin:0; text-shadow: 0 0 20px rgba(0,255,170,0.6);">Batch 20XX</h1>
                        <p style="color:#b0fce0; font-size:18px; margin-top:5px;">Total Graduates: <span id="anDetailTotal" style="color:#ffd700; font-weight:bold;">0</span></p>
                    </div>

                    <div style="display:flex; flex-wrap:wrap; justify-content:center; gap:40px; margin-top:40px;">
                        <div style="flex:1; min-width:300px; max-width:500px; height:400px; position:relative;">
                            <canvas id="coursePieChart"></canvas>
                        </div>
                        <div id="anCourseList" style="flex:1; min-width:300px; max-width:600px;">
                            <!-- Courses will be injected here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            let myCoursePieChart = null; // Variable for Pie Chart instance

            function openAnalyticsModal() {
                document.getElementById('analyticsModal').style.display = 'block';
                closeAnDetails(); // Ensure reset
                if (myAlumniChart) {
                    myAlumniChart.destroy();
                    myAlumniChart = null;
                }
                renderChart();
            }

            let myAlumniChart = null;
            const anDetails = <?php echo json_encode($an_details); ?>;

            function renderChart() {

                const ctx = document.getElementById('alumniChart').getContext('2d');
                
                // Create Gradient
                let gradient = ctx.createLinearGradient(0, 0, 0, 600);
                gradient.addColorStop(0, 'rgba(0, 255, 170, 0.8)');
                gradient.addColorStop(1, 'rgba(0, 255, 170, 0.1)');

                myAlumniChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($an_years); ?>,
                        datasets: [{
                            label: 'Total Graduates',
                            data: <?php echo json_encode($an_counts); ?>,
                            backgroundColor: gradient,
                            borderColor: '#00ffaa',
                            borderWidth: 1,
                            borderRadius: 5,
                            hoverBackgroundColor: '#fff',
                            hoverBorderColor: '#fff',
                            barThickness: 'flex',
                            maxBarThickness: 60
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false, // Allows filling the container
                        animation: {
                            delay: (context) => {
                                let delay = 0;
                                if (context.type === 'data' && context.mode === 'default') {
                                    delay = context.dataIndex * 200;
                                }
                                return delay;
                            },
                            duration: 1000,
                            easing: 'easeOutQuart'
                        },
                        plugins: {
                            legend: { 
                                labels: { color: '#fff', font: { size: 14, family: 'Segoe UI' } } 
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 20, 10, 0.9)',
                                titleColor: '#00ffaa',
                                bodyColor: '#fff',
                                borderColor: '#00ffaa',
                                borderWidth: 1
                            }
                        },
                        onClick: (e, elements) => {
                            if(elements.length > 0) {
                                const index = elements[0].index;
                                const label = myAlumniChart.data.labels[index];
                                const count = myAlumniChart.data.datasets[0].data[index];
                                showAnDetails(label, count);
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { color: '#b0fce0', font: { size: 12 } },
                                grid: { color: 'rgba(255,255,255,0.05)' }
                            },
                            x: {
                                ticks: { color: '#b0fce0', font: { size: 12, weight: 'bold' } },
                                grid: { display: false }
                            }
                        }
                    }
                });
            }

            function showAnDetails(batch, total) {
                document.getElementById('anChartContainer').style.display = 'none';
                const detailView = document.getElementById('anDetailView');
                detailView.style.display = 'block';
                
                document.getElementById('anTitle').style.opacity = '0'; // Hide main title
                document.getElementById('anDetailBatch').innerText = batch;
                document.getElementById('anDetailTotal').innerText = total;

                const list = document.getElementById('anCourseList');
                list.innerHTML = '';

                const courses = anDetails[batch] || [];
                const pieLabels = [];
                const pieData = [];
                const pieColors = [];
                
                // Staggered animation for courses
                courses.forEach((c, i) => {
                    const pct = Math.round((c.count / total) * 100);
                    pieLabels.push(c.name);
                    pieData.push(c.count);
                    pieColors.push(`hsl(${i * 137.5 % 360}, 70%, 50%)`); // Golden Angle approximation for distinct colors

                    const div = document.createElement('div');
                    div.style.cssText = `background: rgba(255,255,255,0.05); margin-bottom: 15px; padding: 15px; border-radius: 10px; display: flex; align-items: center; justify-content: space-between; opacity: 0; animation: slideUpFade 0.5s ease forwards ${i * 0.1}s; border-left: 4px solid #00ffaa;`;
                    div.innerHTML = `
                        <div style="flex:1;">
                            <div style="color:#fff; font-weight:bold; font-size:16px;">${c.name}</div>
                            <div style="background:rgba(255,255,255,0.1); height:6px; width:100%; border-radius:3px; margin-top:8px; overflow:hidden;">
                                <div style="width:${pct}%; height:100%; background:#00ffaa; box-shadow: 0 0 10px #00ffaa;"></div>
                            </div>
                        </div>
                        <div style="margin-left:20px; text-align:right;">
                            <div style="color:#ffd700; font-weight:900; font-size:24px;">${c.count}</div>
                            <div style="color:#aaa; font-size:12px;">Graduates</div>
                        </div>
                    `;
                    list.appendChild(div);
                });

                // Render Pie Chart
                const ctxPie = document.getElementById('coursePieChart').getContext('2d');
                if (myCoursePieChart) {
                    myCoursePieChart.destroy();
                }
                myCoursePieChart = new Chart(ctxPie, {
                    type: 'doughnut',
                    data: {
                        labels: pieLabels,
                        datasets: [{
                            data: pieData,
                            backgroundColor: pieColors,
                            borderColor: '#1a3d2f',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#fff', font: { size: 12 }, padding: 20 }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 20, 10, 0.9)',
                                titleColor: '#00ffaa',
                                bodyColor: '#fff',
                                borderColor: '#00ffaa',
                                borderWidth: 1
                            }
                        },
                        animation: {
                            animateScale: true,
                            animateRotate: true
                        }
                    }
                });
            }

            function closeAnDetails() {
                document.getElementById('anChartContainer').style.display = 'block';
                document.getElementById('anDetailView').style.display = 'none';
                document.getElementById('anTitle').style.opacity = '1';
            }

            // Add style for animations
            const styleSheet = document.createElement("style");
            styleSheet.innerText = `
                @keyframes zoomIn { from { transform: scale(0.9); opacity: 0; } to { transform: scale(1); opacity: 1; } }
                @keyframes slideUpFade { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
            `;
            document.head.appendChild(styleSheet);
        </script>

        <?php
        // 2. Fetch and group alumni
        $alumni_res = $conn->query("SELECT * FROM alumni ORDER BY batch_year DESC, course ASC, name ASC");
        $grouped_alumni = [];
        if ($alumni_res) {
            while($row = $alumni_res->fetch_assoc()){
                $batch = $row['batch_year'] ?: "Unknown Batch";
                $course = $row['course'] ?: "General";
                $grouped_alumni[$batch][$course][] = $row;
            }
        }

        // 3. Display grouped alumni
        if(empty($grouped_alumni)): ?>
            <div style="text-align:center; padding:50px; color:#888;">
                <h3>No alumni records found.</h3>
            </div>
        <?php else:
        foreach($grouped_alumni as $batch_year => $courses):
        ?>
        <div class="batch-container">
            <div class="batch-title">Batch <?php echo htmlspecialchars($batch_year); ?></div>
            
            <?php foreach($courses as $course_name => $students): ?>
            <div class="course-group">
                <div class="course-title"><?php echo htmlspecialchars($course_name); ?></div>
                <div class="alumni-grid">
                    <?php foreach($students as $a): 
                        $pic = !empty($a['profile_pic']) ? "uploads/".$a['profile_pic'] : "assets/images/3icons8-student-64.png";
                        
                        // Calculate Age
                        $age = '';
                        if (!empty($a['birthdate'])) {
                            $birthDate = new DateTime($a['birthdate']);
                            $today = new DateTime('today');
                            $age = $birthDate->diff($today)->y . " years old";
                        }
                        
                        $status = $a['status'] ?? 'No status provided.';
                    ?>
                    <div class="alumni-card-premium" onclick='handleAlumniClick(this, <?php echo htmlspecialchars(json_encode($a), ENT_QUOTES, 'UTF-8'); ?>)'>
                        <img src="<?php echo $pic; ?>" alt="Profile">
                        <h4 class="alumni-name-premium"><?php echo htmlspecialchars($a['name']); ?></h4>
                        <?php if($age): ?><div class="alumni-age-premium"><?php echo $age; ?></div><?php endif; ?>
                        
                        <div class="alumni-status-preview">"<?php echo htmlspecialchars($status); ?>"</div>
                        
                        <div class="view-profile-btn">View Profile</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; endif; ?>
        </div>

    <?php elseif(isset($_GET['page']) && $_GET['page'] == 'achievements'): ?>
        <!-- ACHIEVEMENTS & HIGHLIGHTS VIEW -->
        <style>
            /* Achievements Premium Styles (Matching Alumni Sizing) */
            .ach-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
            .ach-grid { animation: fadeInUp 0.5s ease-out backwards; }
            
            .ach-card {
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 15px;
                padding: 25px;
                text-align: center;
                transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                cursor: pointer;
                position: relative;
                overflow: hidden;
                user-select: none;
            }
            .ach-card:hover {
                transform: translateY(-10px);
                background: rgba(255, 255, 255, 0.08);
                box-shadow: 0 15px 30px rgba(0,0,0,0.4);
            }
            .ach-card img {
                width: 110px; height: 110px; border-radius: 50%; object-fit: cover;
                border: 3px solid #fff; margin-bottom: 15px;
                box-shadow: 0 0 20px rgba(0,0,0,0.2); transition: transform 0.3s;
            }
            .ach-card:hover img { transform: scale(1.05); }
            
            .ach-name { color: #fff; font-size: 1.2rem; font-weight: 700; margin: 0 0 5px; }
            .ach-title { font-weight: bold; margin-bottom: 10px; font-size: 0.95rem; }
            .ach-desc { font-size: 0.9rem; color: #b0c4de; font-style: italic; line-height: 1.4; opacity: 0.8; }
            
            .ach-badge {
                position: absolute; top: 15px; right: 15px;
                padding: 4px 10px; border-radius: 10px;
                font-size: 10px; font-weight: bold; color: #000;
                text-transform: uppercase;
            }

            /* Category Specifics */
            .ach-card.top-student { border-color: rgba(0, 255, 170, 0.3); }
            .ach-card.top-student:hover { border-color: #00ffaa; }
            .ach-card.top-student img { border-color: #00ffaa; box-shadow: 0 0 15px rgba(0, 255, 170, 0.3); }
            .ach-card.top-student .ach-title { color: #00ffaa; }
            .ach-card.top-student .ach-badge { background: #00ffaa; }

            .ach-card.contest { border-color: rgba(255, 85, 85, 0.3); }
            .ach-card.contest:hover { border-color: #ff5555; }
            .ach-card.contest img { border-color: #ff5555; box-shadow: 0 0 15px rgba(255, 85, 85, 0.3); }
            .ach-card.contest .ach-title { color: #ff5555; }
            .ach-card.contest .ach-badge { background: #ff5555; color: #fff; }

            .ach-card.sports { border-color: rgba(0, 204, 255, 0.3); }
            .ach-card.sports:hover { border-color: #00ccff; }
            .ach-card.sports img { border-color: #00ccff; box-shadow: 0 0 15px rgba(0, 204, 255, 0.3); }
            .ach-card.sports .ach-title { color: #00ccff; }
            .ach-card.sports .ach-badge { background: #00ccff; }
        </style>

        <div class="total-count-banner" style="background: linear-gradient(135deg, #ffd700, #b8860b); border-color: #fff; box-shadow: 0 0 20px rgba(255, 215, 0, 0.4);">
            <h2 style="color: #0a1f16; text-shadow: none;">Achievements & Highlights</h2>
            <span style="color: #0a1f16; font-weight: bold;">Celebrating Excellence</span>
        </div>

        <!-- Featured Student -->
        <div class="course-section">
            <div class="course-header" style="color: #ffd700; border-color: rgba(255, 215, 0, 0.3);">Featured Student of the Month</div>
            <?php
            $featured = $conn->query("SELECT * FROM achievements WHERE category='Featured' ORDER BY date_posted DESC LIMIT 1");
            if($featured && $featured->num_rows > 0):
                $f = $featured->fetch_assoc();
                $pic = !empty($f['image']) ? "uploads/".$f['image'] : "assets/images/3icons8-student-64.png";
            ?>
            <div style="background: linear-gradient(to right, rgba(255,215,0,0.1), transparent); border: 1px solid #ffd700; border-radius: 20px; padding: 30px; display: flex; align-items: center; gap: 30px; margin-bottom: 30px; position: relative; overflow: hidden;">
                <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: #ffd700; opacity: 0.2; border-radius: 50%;"></div>
                <img src="<?php echo $pic; ?>" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #ffd700; box-shadow: 0 0 20px rgba(255,215,0,0.5);">
                <div>
                    <h2 style="margin: 0; color: #fff; font-size: 28px;"><?php echo htmlspecialchars($f['student_name']); ?></h2>
                    <span style="background: #ffd700; color: #000; padding: 4px 12px; border-radius: 15px; font-weight: bold; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Student of the Month</span>
                    <p style="color: #e4e6eb; margin-top: 15px; font-style: italic; font-size: 16px;">"<?php echo htmlspecialchars($f['description']); ?>"</p>
                    <div style="margin-top: 10px; color: #00ffaa; font-weight: bold;"><?php echo htmlspecialchars($f['title']); ?></div>
                </div>
            </div>
            <?php else: ?>
                <div style="padding: 20px; text-align: center; color: #888; background: rgba(0,0,0,0.2); border-radius: 10px;">No featured student selected yet.</div>
            <?php endif; ?>
        </div>

        <!-- Top Students -->
        <div class="course-section">
            <div class="course-header" style="color: #00ffaa;">Top Students</div>
            <div class="ach-grid">
                <?php
                $top = $conn->query("SELECT * FROM achievements WHERE category='Top Student' ORDER BY date_posted DESC LIMIT 5");
                if($top && $top->num_rows > 0):
                    while($t = $top->fetch_assoc()):
                        $pic = !empty($t['image']) ? "uploads/".$t['image'] : "assets/images/3icons8-student-64.png";
                ?>
                <div class="ach-card top-student">
                    <div class="ach-badge">TOP</div>
                    <img src="<?php echo $pic; ?>" alt="Pic">
                    <h4 class="ach-name"><?php echo htmlspecialchars($t['student_name']); ?></h4>
                    <div class="ach-title"><?php echo htmlspecialchars($t['title']); ?></div>
                    <div class="ach-desc"><?php echo htmlspecialchars($t['description']); ?></div>
                </div>
                <?php endwhile; else: ?>
                    <p style="color: #888;">No top students listed.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Contest Winners -->
        <div class="course-section">
            <div class="course-header" style="color: #ff5555;">Contest Winners</div>
            <div class="ach-grid">
                <?php
                $contest = $conn->query("SELECT * FROM achievements WHERE category='Contest' ORDER BY date_posted DESC LIMIT 5");
                if($contest && $contest->num_rows > 0):
                    while($c = $contest->fetch_assoc()):
                        $pic = !empty($c['image']) ? "uploads/".$c['image'] : "assets/images/3icons8-student-64.png";
                ?>
                <div class="ach-card contest">
                    <div class="ach-badge">WINNER</div>
                    <img src="<?php echo $pic; ?>" alt="Pic">
                    <h4 class="ach-name"><?php echo htmlspecialchars($c['student_name']); ?></h4>
                    <div class="ach-title"><?php echo htmlspecialchars($c['title']); ?></div>
                    <div class="ach-desc"><?php echo htmlspecialchars($c['description']); ?></div>
                </div>
                <?php endwhile; else: ?>
                    <p style="color: #888;">No contest winners listed.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sports Achievements -->
        <div class="course-section">
            <div class="course-header" style="color: #00ccff;">Sports Achievements</div>
            <div class="ach-grid">
                <?php
                $sports = $conn->query("SELECT * FROM achievements WHERE category='Sports' ORDER BY date_posted DESC LIMIT 5");
                if($sports && $sports->num_rows > 0):
                    while($s = $sports->fetch_assoc()):
                        $pic = !empty($s['image']) ? "uploads/".$s['image'] : "assets/images/3icons8-student-64.png";
                ?>
                <div class="ach-card sports">
                    <div class="ach-badge">ATHLETE</div>
                    <img src="<?php echo $pic; ?>" alt="Pic">
                    <h4 class="ach-name"><?php echo htmlspecialchars($s['student_name']); ?></h4>
                    <div class="ach-title"><?php echo htmlspecialchars($s['title']); ?></div>
                    <div class="ach-desc"><?php echo htmlspecialchars($s['description']); ?></div>
                </div>
                <?php endwhile; else: ?>
                    <p style="color: #888;">No sports achievements listed.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif(isset($_GET['page']) && $_GET['page'] == 'calendar'): ?>
        <!-- CALENDAR VIEW -->
        <?php
        $ym = isset($_GET['ym']) ? $_GET['ym'] : date('Y-m');
        $timestamp = strtotime($ym . '-01');
        if ($timestamp === false) $timestamp = time();
        
        $today = date('Y-m-d', time());
        $html_title = date('F Y', $timestamp);
        $prev = date('Y-m', mktime(0, 0, 0, date('m', $timestamp)-1, 1, date('Y', $timestamp)));
        $next = date('Y-m', mktime(0, 0, 0, date('m', $timestamp)+1, 1, date('Y', $timestamp)));
        
        $day_count = date('t', $timestamp);
        $str = date('w', mktime(0, 0, 0, date('m', $timestamp), 1, date('Y', $timestamp)));
        
        // Fetch Events for this month
        $events = [];
        $m_start = date('Y-m-01', $timestamp);
        $m_end = date('Y-m-t', $timestamp);
        $ev_res = $conn->query("SELECT * FROM calendar_events WHERE event_date BETWEEN '$m_start' AND '$m_end'");
        if($ev_res) while($r = $ev_res->fetch_assoc()) $events[$r['event_date']][] = $r;
        ?>
        
        <div class="calendar-wrapper">
            <div class="cal-header">
                <a href="?page=calendar&ym=<?php echo $prev; ?>" class="cal-btn">&lt; Prev</a>
                <h2><?php echo $html_title; ?></h2>
                <a href="?page=calendar&ym=<?php echo $next; ?>" class="cal-btn">Next &gt;</a>
            </div>
            
            <div class="cal-grid">
                <div class="cal-day-name">Sun</div>
                <div class="cal-day-name">Mon</div>
                <div class="cal-day-name">Tue</div>
                <div class="cal-day-name">Wed</div>
                <div class="cal-day-name">Thu</div>
                <div class="cal-day-name">Fri</div>
                <div class="cal-day-name">Sat</div>
                
                <?php
                // Empty slots before start
                for ($i = 0; $i < $str; $i++) echo '<div></div>';
                
                // Days
                for ($day = 1; $day <= $day_count; $day++) {
                    $date = date('Y-m-d', mktime(0, 0, 0, date('m', $timestamp), $day, date('Y', $timestamp)));
                    $is_today = ($date == $today) ? 'today' : '';
                    
                    echo "<div class='cal-date $is_today'>";
                    echo "<span class='cal-num'>$day</span>";
                    
                    if (isset($events[$date])) {
                        foreach ($events[$date] as $ev) {
                            echo "<div class='cal-event-text' style='cursor:pointer;' onclick='openEventModal(".htmlspecialchars(json_encode($ev), ENT_QUOTES, 'UTF-8').")'>".htmlspecialchars($ev['title'])."</div>";
                        }
                    }
                    echo "</div>";
                }
                ?>
            </div>
            
            <div class="event-list-day">
                <h3 style="color:#00ffaa;">Upcoming Events</h3>
                <?php
                $upcoming = $conn->query("SELECT * FROM calendar_events WHERE event_date >= '$today' ORDER BY event_date ASC LIMIT 5");
                if($upcoming->num_rows > 0):
                    while($up = $upcoming->fetch_assoc()):
                ?>
                <div class="event-item">
                    <div style="display:flex; justify-content:space-between;">
                        <strong style="color:#fff; font-size:18px;"><?php echo htmlspecialchars($up['title']); ?></strong>
                        <span style="color:#00ffaa; font-weight:bold;"><?php echo date("M d", strtotime($up['event_date'])); ?></span>
                    </div>
                    <p style="color:#ccc; margin:5px 0 0;"><?php echo htmlspecialchars($up['description']); ?></p>
                </div>
                <?php endwhile; else: ?>
                    <p style="color:#888;">No upcoming events.</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif(isset($_GET['page']) && $_GET['page'] == 'evaluates'): ?>
        <!-- EVALUATES PAGE -->
        
        <div class="total-count-banner" style="background: linear-gradient(135deg, #004d33, #00ffaa); border-color: #fff; box-shadow: 0 0 20px rgba(0, 255, 170, 0.4); width: 100%; max-width: 100%; right: 0;">
            <h2 style="color: #fff; text-shadow: none;">Faculty Evaluation</h2>
            <span style="color: #0a1f16; font-weight: bold;">Select a teacher to evaluate</span>
        </div>

        <?php if($is_eval_locked): ?>
            <div style="text-align:center; padding:60px 20px; background:rgba(255,255,255,0.05); border-radius:15px; border:1px solid rgba(255,85,85,0.3); margin-top:20px; max-width:800px; margin-left:auto; margin-right:auto;">
                <div style="font-size:50px; margin-bottom:20px;">🔒</div>
                <h2 style="color:#ff5555; margin-bottom:10px;">Evaluation Section is Not Available Right Now</h2>
                <p style="color:#ccc; font-size:16px;">The faculty evaluation period is currently closed. Please wait for the admin to open the evaluation schedule.</p>
            </div>
        <?php else: ?>

        <div class="course-section">
            <div class="student-grid">
                <?php
                $eval_teachers = $conn->query("SELECT * FROM teachers ORDER BY name ASC");
                if($eval_teachers && $eval_teachers->num_rows > 0):
                    while($t = $eval_teachers->fetch_assoc()):
                        $pic = !empty($t['profile_pic']) ? "uploads/".$t['profile_pic'] : "4icons8-teacher-50.png";
                        
                        // Check if already evaluated
                        $is_evaluated = $conn->query("SELECT id FROM evaluations WHERE student_id='$my_id' AND teacher_id='".$t['id']."'")->num_rows > 0;
                        $card_onclick = $is_evaluated ? "" : "onclick=\"openEvaluationModal(".$t['id'].", '".htmlspecialchars($t['name'], ENT_QUOTES)."', '".$pic."')\"";
                        $badge_text = $is_evaluated ? "DONE" : "EVALUATE";
                        $badge_style = $is_evaluated ? "background: #555; color: #fff;" : "background: #00ffaa; color: #000;";
                        $cursor_style = $is_evaluated ? "cursor: default;" : "cursor: pointer;";
                ?>
                <div class="student-card" <?php echo $card_onclick; ?> style="<?php echo $cursor_style; ?>">
                    <div class="yr-badge" style="<?php echo $badge_style; ?>"><?php echo $badge_text; ?></div>
                    <img src="<?php echo $pic; ?>" alt="Pic" style="border-color: #00ffaa;">
                    <h4><?php echo htmlspecialchars($t['name']); ?></h4>
                    <p style="color: #b0fce0;"><?php echo htmlspecialchars($t['department']); ?></p>
                    
                    <?php if($is_evaluated): ?>
                    <form method="POST" action="SacliConnect.php" id="resetEvalForm_<?php echo $t['id']; ?>" style="margin-top:10px;">
                        <input type="hidden" name="action" value="reset_evaluation">
                        <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                        <button type="button" onclick="confirmResetEvaluation('resetEvalForm_<?php echo $t['id']; ?>')" style="background: #ff5555; color: white; border: none; padding: 5px 15px; border-radius: 5px; cursor: pointer; font-size: 12px; font-weight: bold; width: 100%;">RESET</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endwhile; else: ?>
                    <p style="color: #888; text-align: center; width: 100%;">No teachers found.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php elseif(isset($_GET['page']) && $_GET['page'] == 'sacli_room'): ?>
          <?php
        // Define the filter for SacliRoom page to prevent undefined variable warning
        $sr_filter = $_GET['filter'] ?? 'home';
        ?>
        <!-- SACLI ROOM (Google Classroom UI) -->
        <div class="sacli-room-container">
            <aside class="sr-sidebar">
                <ul class="sr-sidebar-menu">
                    <li class="<?php if($sr_filter === 'home') echo 'active'; ?>" onclick="location.href='SacliConnect.php?page=sacli_room&filter=home'">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        <span>Home</span>
                    </li>
                    <li class="<?php if($sr_filter === 'calendar') echo 'active'; ?>" onclick="location.href='SacliConnect.php?page=sacli_room&filter=calendar'">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <span>Calendar</span>
                    </li>
                    <li class="<?php if($sr_filter === 'enrolled') echo 'active'; ?>" onclick="location.href='SacliConnect.php?page=sacli_room&filter=enrolled'">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                        <span>Enrolled</span>
                    </li>
                    <li class="<?php if($sr_filter === 'todo') echo 'active'; ?>" onclick="location.href='SacliConnect.php?page=sacli_room&filter=todo'">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        <span>To-do</span>
                    </li>
                    <li class="<?php if($sr_filter === 'settings') echo 'active'; ?>" onclick="location.href='SacliConnect.php?page=sacli_room&filter=settings'">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        <span>Settings</span>
                    </li>
                </ul>
            </aside>
            <main class="sr-main-content">
                <?php if ($sr_filter === 'todo'): ?>
                    <h2 style="color: #00ffaa; margin-bottom: 20px;">To-do List</h2>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php
                    if ($user_type === 'student') {
                        // Student: Assigned work not yet submitted
                        $todo_sql = "SELECT p.id, p.title, p.due_date, r.name as room_name, r.id as room_id 
                                     FROM sacli_room_posts p 
                                     JOIN sacli_rooms r ON p.room_id = r.id
                                     JOIN sacli_room_members m ON r.id = m.room_id
                                     LEFT JOIN sacli_room_submissions s ON p.id = s.post_id AND s.student_id = '$my_id'
                                     WHERE m.student_id = '$my_id' AND m.role = 'student' AND p.due_date IS NOT NULL AND s.id IS NULL
                                     ORDER BY p.due_date ASC";
                    } else {
                        // Teacher: Assignments to review
                        $todo_sql = "SELECT p.id, p.title, p.due_date, r.name as room_name, r.id as room_id,
                                     (SELECT COUNT(*) FROM sacli_room_submissions WHERE post_id = p.id) as turned_in_count,
                                     (SELECT COUNT(*) FROM sacli_room_members WHERE room_id = r.id AND role = 'student') as total_students
                                     FROM sacli_room_posts p
                                     JOIN sacli_rooms r ON p.room_id = r.id
                                     WHERE r.teacher_id = '$my_id' AND p.due_date IS NOT NULL
                                     ORDER BY p.due_date DESC";
                    }
                    
                    $todo_res = $conn->query($todo_sql);
                    if ($todo_res && $todo_res->num_rows > 0) {
                        while ($todo = $todo_res->fetch_assoc()) {
                            echo '<div style="background: var(--bg-light); padding: 20px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: 0.2s;" onmouseover="this.style.borderColor=\'#00ffaa\'" onmouseout="this.style.borderColor=\'rgba(255,255,255,0.1)\'" onclick="location.href=\'SacliRoom_view.php?id='.$todo['room_id'].'&page=classwork\'">';
                            echo '<div>';
                            echo '<h4 style="margin: 0 0 5px 0; color: #fff;">'.htmlspecialchars($todo['title']).'</h4>';
                            echo '<p style="margin: 0; color: #aaa; font-size: 13px;">'.htmlspecialchars($todo['room_name']).'</p>';
                            echo '</div>';
                            echo '<div style="text-align: right;">';
                            echo '<div style="font-size: 12px; color: #ff8a80; font-weight: bold;">Due: '.date("M d, Y h:i A", strtotime($todo['due_date'])).'</div>';
                            if ($user_type === 'teacher') {
                                echo '<div style="font-size: 12px; color: #00ffaa; margin-top: 5px;">Turned In: '.$todo['turned_in_count'].' / '.$todo['total_students'].'</div>';
                            }
                            echo '</div>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div style="text-align:center; padding: 50px; color: #aaa;">Woohoo, no work due!</div>';
                    }
                    ?>
                    </div>
                <?php elseif ($sr_filter === 'calendar'): ?>
                    <?php
                    $ym = isset($_GET['ym']) ? $_GET['ym'] : date('Y-m');
                    $timestamp = strtotime($ym . '-01');
                    if ($timestamp === false) $timestamp = time();
                    
                    $today = date('Y-m-d');
                    $html_title = date('F Y', $timestamp);
                    $prev = date('Y-m', strtotime('-1 month', $timestamp));
                    $next = date('Y-m', strtotime('+1 month', $timestamp));
                    
                    $day_count = date('t', $timestamp);
                    $str = date('w', $timestamp);
                    
                    $events = [];
                    $m_start = date('Y-m-01', $timestamp);
                    $m_end = date('Y-m-t', $timestamp);

                    // 2. Fetch SacliRoom assignments (To-do items)
                    $todo_sql = "SELECT p.id, p.title, p.due_date, r.name as room_name 
                                 FROM sacli_room_posts p 
                                 JOIN sacli_room_members m ON p.room_id = m.room_id
                                 JOIN sacli_rooms r ON p.room_id = r.id
                                 WHERE m.student_id = '$my_id' AND p.due_date BETWEEN '$m_start 00:00:00' AND '$m_end 23:59:59'";
                    $todo_res = $conn->query($todo_sql);
                    if($todo_res) {
                        while($r = $todo_res->fetch_assoc()) {
                            $due_date_only = date('Y-m-d', strtotime($r['due_date']));
                            $r['event_date'] = $due_date_only;
                            $events[$due_date_only][] = $r;
                        }
                    }
                    ?>
                    <div class="calendar-wrapper" style="max-width: none;">
                        <div class="cal-header">
                            <a href="?page=sacli_room&filter=calendar&ym=<?php echo $prev; ?>" class="cal-btn">&lt; Prev</a>
                            <h2><?php echo $html_title; ?></h2>
                            <a href="?page=sacli_room&filter=calendar&ym=<?php echo $next; ?>" class="cal-btn">Next &gt;</a>
                        </div>
                        <div class="cal-grid">
                            <div class="cal-day-name">Sun</div><div class="cal-day-name">Mon</div><div class="cal-day-name">Tue</div><div class="cal-day-name">Wed</div><div class="cal-day-name">Thu</div><div class="cal-day-name">Fri</div><div class="cal-day-name">Sat</div>
                            <?php
                            for ($i = 0; $i < $str; $i++) echo '<div></div>';
                            for ($day = 1; $day <= $day_count; $day++) {
                                $date = $ym . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                                $is_today = ($date == $today) ? 'today' : '';
                                echo "<div class='cal-date $is_today'><span class='cal-num'>$day</span>";
                                if (isset($events[$date])) {
                                    foreach ($events[$date] as $ev) {
                                        $color_class = ''; $extra_text = '';
                                        $due_datetime = new DateTime(date('Y-m-d', strtotime($ev['due_date'])));
                                        $today_datetime = new DateTime('today');
                                        $diff = (int)$today_datetime->diff($due_datetime)->format("%r%a");
                                        if ($diff < 0) { $color_class = 'missed'; $extra_text = ' (You missed the activity)'; }
                                        elseif ($diff == 0) { $color_class = 'due-red'; } 
                                        elseif ($diff <= 3) { $color_class = 'due-yellow'; }
                                        elseif ($diff <= 7) { $color_class = 'due-green'; }
                                        echo "<div class='cal-event-text ".$color_class."' title='".htmlspecialchars($ev['title'])."'>".htmlspecialchars($ev['title']).$extra_text."</div>";
                                    }
                                }
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </div>
                <?php elseif ($sr_filter === 'settings'): ?>
                    <div style="text-align:center; padding: 50px; color: #aaa; background:rgba(255,255,255,0.05); border-radius:15px;">Settings page is coming soon.</div>
                <?php elseif ($sr_filter === 'enrolled'): ?>
                    <h2 style="color: #00ffaa; margin-bottom: 20px;">Enrolled Classes</h2>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                    <?php
                    $rooms_sql = "SELECT r.*, 
                                    m.role as user_role_in_room,
                                    COALESCE(t.name, 'Unknown Teacher') as teacher_name,
                                    t.profile_pic as teacher_pic
                                  FROM sacli_rooms r
                                  JOIN sacli_room_members m ON r.id = m.room_id
                                  LEFT JOIN teachers t ON r.teacher_id = CONCAT('T-', t.id) 
                                  WHERE m.student_id = '$my_id' AND m.role = 'student'
                                  ORDER BY r.name ASC";
                    $rooms_res = $conn->query($rooms_sql);
                    if ($rooms_res && $rooms_res->num_rows > 0) {
                        while ($room = $rooms_res->fetch_assoc()) {
                            $teacher_pic = !empty($room['teacher_pic']) ? "uploads/".$room['teacher_pic'] : "4icons8-teacher-50.png";
                            echo '<div style="background: var(--bg-light); padding: 20px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: 0.2s;" onmouseover="this.style.borderColor=\'#00ffaa\'" onmouseout="this.style.borderColor=\'rgba(255,255,255,0.1)\'" onclick="location.href=\'SacliRoom_view.php?id='.$room['id'].'\'">';
                            echo '<div style="display:flex; align-items:center; gap:15px;">';
                            echo '<img src="'.$teacher_pic.'" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">';
                            echo '<div><h4 style="margin: 0 0 5px 0; color: #fff;">'.htmlspecialchars($room['name']).'</h4><p style="margin: 0; color: #aaa; font-size: 13px;">'.htmlspecialchars($room['teacher_name']).'</p></div>';
                            echo '</div>';
                            echo '<div class="sr-options-container" onclick="event.stopPropagation();"><svg onclick="toggleRoomMenu(this)" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg><div class="sr-options-menu"><div class="sr-menu-item delete" onclick="leaveRoom('.$room['id'].', \''.htmlspecialchars(addslashes($room['name']), ENT_QUOTES).'\')">Leave Room</div></div></div>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div style="text-align:center; padding: 50px; color: #aaa; background:rgba(255,255,255,0.05); border-radius:15px;">You are not enrolled in any classes.</div>';
                    }
                    ?>
                    </div>
                <?php else: // This will be for 'home' and 'enrolled' filters ?>
                <div class="sr-header">
                    <div>
                        <?php if ($user_type === 'teacher'): ?>
                        <button class="sr-btn" onclick="openCreateRoomModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            Create Room
                        </button>
                        <?php endif; ?>
                    </div>
                    <div>
                        <button class="sr-btn join" onclick="openJoinRoomModal()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.72"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.72-1.72"></path></svg>
                            Join Room
                        </button>
                    </div>
                </div>
                <div class="sr-grid">
                    <?php
                    // Fetch rooms the user is part of
                    $my_id = $_SESSION['student_id'];
                    $rooms_sql = "SELECT r.*, 
                                    m.role as user_role_in_room,
                                    COALESCE(t.name, 'Unknown Teacher') as teacher_name,
                                    t.profile_pic as teacher_pic
                                  FROM sacli_rooms r
                                  JOIN sacli_room_members m ON r.id = m.room_id
                                  LEFT JOIN teachers t ON r.teacher_id = CONCAT('T-', t.id) WHERE m.student_id = '$my_id'";
                    
                    // Add filter condition based on the sidebar selection
                    $rooms_sql .= " ORDER BY r.created_at DESC"; // Home shows all rooms, newest first
                    $rooms_res = $conn->query($rooms_sql);
                    
                    $colors = ['#00796b', '#d32f2f', '#512da8', '#303f9f', '#0288d1', '#e64a19', '#689f38'];
                    $color_index = 0;

                    if ($rooms_res && $rooms_res->num_rows > 0):
                        while($room = $rooms_res->fetch_assoc()):
                            $bg_color = $colors[$color_index % count($colors)];
                            $color_index++;
                            $teacher_initial = !empty($room['teacher_name']) ? strtoupper(substr($room['teacher_name'], 0, 1)) : '?';
                    ?>
                    <div class="sr-class-card" style="cursor: pointer;" onclick="location.href='SacliRoom_view.php?id=<?php echo $room['id']; ?>'">
                        <div class="sr-card-header" style="background-color: <?php echo $bg_color; ?>;">
                            <div>
                                <h3 class="sr-card-title"><?php echo htmlspecialchars($room['name']); ?></h3>
                                <p class="sr-card-teacher"><?php echo htmlspecialchars($room['teacher_name']); ?></p>
                            </div>
                            <div class="sr-card-avatar"><?php echo $teacher_initial; ?></div>
                        </div>
                        <div class="sr-card-content">
                            <!-- Future content like assignments can go here -->
                        </div>
                        <div class="sr-card-footer">
                            <a href="#" title="Open folder" onclick="event.stopPropagation();"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg></a>
                            <div class="sr-options-container" onclick="event.stopPropagation();">
                                <svg onclick="toggleRoomMenu(this)" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                                <div class="sr-options-menu">
                                    <?php if ($room['user_role_in_room'] === 'teacher'): ?>
                                        <div class="sr-menu-item delete" onclick="deleteRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars(addslashes($room['name']), ENT_QUOTES); ?>')">Delete</div>
                                    <?php else: ?>
                                        <div class="sr-menu-item delete" onclick="leaveRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars(addslashes($room['name']), ENT_QUOTES); ?>')">Leave Room</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                        <div style="grid-column: 1 / -1; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 50px; background: rgba(255,255,255,0.05); border-radius: 15px; min-height: 200px;">
                            <h3 style="color: #fff; margin: 0 0 10px 0;">No rooms joined yet.</h3>
                            <p style="color: #aaa; margin: 0;">Click "Join Room" to enter a code from your teacher.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </main>
        </div>
    <?php elseif(isset($_GET['page']) && $_GET['page'] == 'meeting'): ?>
        <!-- MEETING VIEW -->
        <style>
            .meeting-container { width: 100%; height: calc(100vh - 80px); animation: fadeInUp 0.5s ease-out; display: flex; flex-direction: column; justify-content: center; }
            
            .lobby-card {
                background: rgba(16, 46, 34, 0.7);
                backdrop-filter: blur(20px);
                border: 1px solid rgba(0, 255, 170, 0.2);
                border-radius: 24px;
                padding: 50px;
                text-align: center;
                max-width: 480px;
                margin: auto;
                box-shadow: 0 20px 50px rgba(0,0,0,0.4);
            }
            .lobby-input {
                width: 100%; padding: 15px; border-radius: 12px; border: 1px solid rgba(0, 255, 170, 0.3);
                background: rgba(0,0,0,0.3); color: white; font-size: 16px; margin-bottom: 25px;
                text-align: center; outline: none; transition: 0.3s;
            }
            .lobby-input:focus { border-color: #00ffaa; background: rgba(0,0,0,0.5); }
            
            /* Modern Room Styles */
            #meetingRoom { height: 100%; display: none; position: relative; }
            
            .meeting-grid-modern {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                height: 100%;
                width: 100%;
                padding: 20px 20px 100px 20px; /* Bottom padding for dock */
                align-content: center;
                justify-content: center;
            }
            
            .video-tile {
                background: linear-gradient(145deg, #0a1f16, #000000);
                border-radius: 24px;
                overflow: hidden;
                position: relative;
                aspect-ratio: 16/9;
                border: 1px solid rgba(255,255,255,0.05);
                box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                display: flex; align-items: center; justify-content: center;
                transition: transform 0.3s, box-shadow 0.3s;
                width: 100%;
                max-height: 100%;
            }
            .video-tile:hover { transform: scale(1.01); border-color: rgba(0,255,170,0.2); }
            
            .video-tile video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); display: block; }
            
            .avatar-placeholder {
                width: 100px; height: 100px; border-radius: 50%;
                background: linear-gradient(135deg, #1a3d2f, #004d33);
                display: flex; align-items: center; justify-content: center;
                font-size: 32px; color: #00ffaa; border: 2px solid #00ffaa;
                box-shadow: 0 0 30px rgba(0,255,170,0.2);
                position: absolute;
                z-index: 1;
            }
            
            .user-label-modern {
                position: absolute; bottom: 20px; left: 20px;
                background: rgba(0,0,0,0.6); backdrop-filter: blur(10px);
                padding: 8px 16px; border-radius: 12px;
                display: flex; align-items: center; gap: 8px;
                font-size: 14px; font-weight: 600; color: #fff;
                border: 1px solid rgba(255,255,255,0.1);
                z-index: 2;
            }
            .mic-dot { width: 8px; height: 8px; background: #00ffaa; border-radius: 50%; }
            .mic-dot.muted { background: #ff5555; }
            
            .control-dock {
                position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%);
                background: rgba(15, 30, 25, 0.85); backdrop-filter: blur(20px);
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 24px; padding: 10px 20px;
                display: flex; gap: 12px; align-items: center;
                box-shadow: 0 15px 40px rgba(0,0,0,0.6);
                z-index: 100;
            }
            
            .dock-btn {
                width: 48px; height: 48px; border-radius: 16px;
                background: rgba(255,255,255,0.05); border: none; color: #e4e6eb;
                cursor: pointer; display: flex; align-items: center; justify-content: center;
                transition: 0.2s; position: relative;
            }
            .dock-btn:hover { background: rgba(255,255,255,0.15); transform: translateY(-2px); }
            .dock-btn.active { background: #00ffaa; color: #0a1f16; }
            .dock-btn.active:hover { background: #00e699; }
            .dock-btn.danger { background: #ff4757; color: white; width: 64px; border-radius: 18px; }
            .dock-btn.danger:hover { background: #ff2e3e; box-shadow: 0 5px 15px rgba(255, 71, 87, 0.4); }
            
            .dock-btn svg { width: 22px; height: 22px; stroke: currentColor; stroke-width: 2; fill: none; }
            
            .room-info-badge {
                position: absolute; top: 20px; left: 20px;
                background: rgba(0,0,0,0.5); backdrop-filter: blur(5px);
                padding: 8px 15px; border-radius: 30px;
                border: 1px solid rgba(255,255,255,0.1);
                display: flex; align-items: center; gap: 10px;
                color: #b0fce0; font-size: 13px; z-index: 10;
            }
        </style>

        <div class="meeting-container">
            <!-- Lobby View -->
            <div id="meetingLobby" style="display:flex; flex-direction:column; gap:20px; align-items:center;">
                <div style="display:flex; gap:30px; width:100%; max-width:1200px; justify-content:center; align-items:flex-start; padding: 20px;">
                    <div class="lobby-card tech-border" style="margin:0; flex:1;">
                        <h2 class="hologram-glow" style="margin-top:0; font-weight:900; letter-spacing:5px;">SACLIMEET <span style="color:#00ffaa; font-size: 0.6em; vertical-align: middle;">v4.0</span></h2>
                        <p style="color:#00ffaa; margin-bottom:25px; font-size:10px; text-transform:uppercase; letter-spacing:3px; opacity: 0.7;">// NEURAL_LINK_ESTABLISHED_READY</p>
                        
                        <div class="lobby-preview-box">
                            <video id="lobbyVideo" autoplay muted playsinline></video>
                            <div class="scanner-line"></div>
                            <div style="position:absolute; bottom:15px; left:15px; background:rgba(0,0,0,0.6); padding:5px 12px; border-radius:15px; font-size:11px; color:#00ffaa; font-family:monospace;">
                                SOURCE: CAM_01
                            </div>
                        </div>

                        <input type="text" id="meetingCodeInput" class="lobby-input hologram-glow" placeholder="ENTER ACCESS CODE" style="border-radius: 0; background: rgba(0,255,170,0.05);">
                        <div style="display:flex; gap:15px;">
                            <button class="sr-btn join" style="flex:1; justify-content:center; padding:15px; font-size:11px; letter-spacing:2px; font-weight: bold; border-radius: 0;" onclick="joinMeeting()">ESTABLISH LINK</button>
                            <button class="sr-btn" style="flex:1; justify-content:center; padding:15px; font-size:11px; letter-spacing:2px; font-weight: bold; border-radius: 0;" onclick="startMeeting()">NEW INITIALIZATION</button>
                        </div>
                    </div>

                    <!-- Meeting History Section -->
                    <div class="lobby-card tech-border" style="margin:0; width:380px; flex:none; text-align:left; background: rgba(16, 46, 34, 0.2);">
                        <h3 style="color:#00ffaa; font-size:12px; margin-top:0; border-bottom:1px solid rgba(0,255,170,0.3); padding-bottom:10px; letter-spacing:2px; font-weight: 900;">SYSTEM_DIAGNOSTICS</h3>
                        
                        <div style="margin-top: 15px;">
                            <div class="meeting-hud-stat"><span>Uplink Status</span><span style="color:#fff;">Operational</span></div>
                            <div class="meeting-hud-stat"><span>Encryption</span><span style="color:#fff;">AES-256-GCM</span></div>
                            <div class="meeting-hud-stat"><span>Node Location</span><span style="color:#fff;">Lucena_Edge_01</span></div>
                        </div>

                        <h3 style="color:#00ffaa; font-size:12px; margin-top:25px; border-bottom:1px solid rgba(0,255,170,0.3); padding-bottom:10px; letter-spacing:2px; font-weight: 900;">ACCESS_LOGS</h3>
                        <div id="meetingHistoryContainer" class="history-card">
                            <div style="text-align:center; color:#555; padding:20px; font-size:12px;">FETCHING_ENCRYPTED_DATA...</div>
                        </div>
                        <div style="margin-top:15px; font-size:9px; color:#509b83; font-family: monospace; opacity: 0.6;">
                            >> SECURE_CONNECTION_STABLE<br>
                            >> JARVIS_OS_CORE_LOADED
                        </div>
                    </div>
                </div>
            </div>

            <!-- Room View -->
            <div id="meetingRoom">
                <div class="meeting-top-bar">
                    <div style="display:flex; align-items:center; gap:15px;">
                        <div class="rec-dot"></div>
                        <span style="font-weight:bold; font-size:12px; letter-spacing:1px;">REC <span id="meetingTimer" style="color:#aaa; margin-left:10px; font-family:monospace;">00:00:00</span></span>
                    </div>
                    <div style="background:rgba(0,255,170,0.1); padding:5px 15px; border-radius:20px; border:1px solid #00ffaa; font-size:12px;">
                        ACCESS_CODE: <b id="currentRoomCode">---</b>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button class="dock-btn" style="width:35px; height:35px; border-radius:50%;" title="Participants" onclick="toggleMeetingSidebar('people')"><img src="chat_24dp_FFFFFF_FILL0_wght400_GRAD0_opsz24.png" alt="Account"></button>
                        <button class="dock-btn" style="width:35px; height:35px; border-radius:50%;" title="Chat" onclick="toggleMeetingSidebar('chat')">💬</button>
                    </div>
                </div>

                <div class="meeting-main-area">
                    <div class="video-grid-container" id="videoGrid">
                        <!-- Local Video -->
                        <div class="video-tile" id="localTile">
                            <video id="localVideo" autoplay muted playsinline></video>
                            <div id="localAvatar" class="avatar-placeholder" style="display:none;">
                                <?php echo strtoupper(substr($_SESSION['student_name'], 0, 1)); ?>
                            </div>
                            <div class="tile-overlay"></div>
                            <div class="user-label-modern">
                                <div class="mic-dot" id="localMicDot"></div>
                                You (Admin)
                            </div>
                            <div class="audio-visualizer" id="localVisualizer">
                                <div class="bar-v"></div><div class="bar-v"></div><div class="bar-v"></div>
                            </div>
                        </div>
                        <!-- Remote Placeholder -->
                        <div class="video-tile" style="opacity:0.5; border-style:dashed;">
                            <div class="avatar-placeholder">?</div>
                            <div class="user-label-modern">Waiting for peer connection...</div>
                        </div>
                    </div>

                    <!-- Right Sidebar -->
                    <div class="meeting-sidebar" id="meetingSidebar">
                        <div style="padding:20px; border-bottom:1px solid rgba(255,255,255,0.1); display:flex; justify-content:space-between; align-items:center;">
                            <h4 id="sidebarTitle" style="margin:0; color:#00ffaa; text-transform:uppercase;">Chat</h4>
                            <button onclick="toggleMeetingSidebar()" style="background:none; border:none; color:#ff5555; cursor:pointer; font-size:20px;">&times;</button>
                        </div>
                        <div id="sidebarContent" style="flex:1; overflow-y:auto; padding:15px;">
                            <!-- Dynamic Content -->
                        </div>
                        <div id="sidebarFooter" style="padding:15px; border-top:1px solid rgba(255,255,255,0.1); display:none;">
                            <input type="text" placeholder="Broadcast message..." style="width:100%; padding:10px; border-radius:10px; background:rgba(0,0,0,0.3); border:1px solid #00ffaa; color:white;">
                        </div>
                    </div>
                </div>

                <div class="control-dock">
                    <button class="dock-btn active" id="btnMic" onclick="toggleMic()" title="Toggle Audio">
                        <img id="micIcon" src="mic_24dp_000000_FILL0_wght400_GRAD0_opsz24.png" style="width:24px; height:24px;">
                    </button>
                    <button class="dock-btn active" id="btnCam" onclick="toggleCam()" title="Toggle Video">
          
                        <img id="camIcon" src="videocam_24dp_000000_FILL0_wght400_GRAD0_opsz24.png" style="width:24px; height:24px;">
                    </button>
                    <button class="dock-btn" title="Share Screen" onclick="showFlash('Screen sharing link initializing...')"><svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg></button>
                    
                    <!-- Quick Reactions -->
                    <div style="display:flex; gap:5px; background:rgba(0,0,0,0.2); padding:5px; border-radius:15px; border:1px solid rgba(255,255,255,0.1);">
                        <button class="dock-btn" style="width:35px; height:35px;" onclick="sendReaction('❤️')">❤️</button>
                        <button class="dock-btn" style="width:35px; height:35px;" onclick="sendReaction('👏')">👏</button>
                        <button class="dock-btn" style="width:35px; height:35px;" onclick="sendReaction('🔥')">🔥</button>
                    </div>

                    <button class="dock-btn danger" onclick="leaveMeeting()" title="Terminate Link">
                        <svg viewBox="0 0 24 24"><path d="M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7 2 2 0 0 1 1.72 2v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-3.33-2.67m-2.67-3.34a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91"></path></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- UI Sound Effects -->
        <audio id="uiClick" src="https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3"></audio>

        <script>
            let localStream = null;
            let isMicOn = true;
            let isCamOn = true;
            let timerInterval = null;
            let currentMeetingLogId = null;
            let secondsElapsed = 0;

            // Initialize Lobby Video on Page Load
            document.addEventListener('DOMContentLoaded', () => {
                loadMeetingHistory();
                if(document.getElementById('lobbyVideo')) {
                    navigator.mediaDevices.getUserMedia({ video: true, audio: false })
                    .then(stream => { if(document.getElementById('lobbyVideo')) document.getElementById('lobbyVideo').srcObject = stream; })
                    .catch(e => console.log("Lobby video blocked"));
                }
            });

            function playUIClick() { document.getElementById('uiClick').play().catch(e=>{}); }

            function startMeeting() {
                playUIClick();
                let fd = new FormData();
                fd.append('action', 'create_meeting');
                fetch('handlers/meeting_handler.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        window.location.href = 'Meeting_Room.php?code=' + data.code;
                    }
                });
            }

            function joinMeeting() {
                playUIClick();
                const code = document.getElementById('meetingCodeInput').value.trim();
                if (!code) return showCustomAlert("Invalid Access Token.");
                
                let fd = new FormData();
                fd.append('action', 'request_join');
                fd.append('code', code);
                fetch('handlers/meeting_handler.php', { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'invalid') {
                        showCustomAlert("Meeting link does not exist or is inactive.");
                    } else {
                        window.location.href = 'Meeting_Room.php?code=' + code;
                    }
                });
            }

            function loadMeetingHistory() {
                let formData = new FormData();
                formData.append('action', 'get_meeting_history');
                fetch('handlers/sacli_room_handler.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    const container = document.getElementById('meetingHistoryContainer');
                    if(data.length === 0) {
                        container.innerHTML = '<div style="text-align:center; color:#555; padding:20px;">NO_DATA_FOUND</div>';
                        return;
                    }
                    container.innerHTML = data.map(log => `
                        <div class="history-item" style="border-bottom:1px solid rgba(0,255,170,0.1); padding:10px 5px; font-family:monospace; font-size:10px; cursor: pointer;">
                            <div style="color:#00ffaa; font-weight:bold; letter-spacing: 1px;">ID: ${log.room_code} <span style="color:#fff; float:right; opacity: 0.7;">[${log.host_name}]</span></div>
                            <div style="color:#888; margin-top:4px;">IN: ${log.joined_at} | OUT: ${log.left_at || 'ACTIVE'}</div>
                        </div>
                    `).join('');
                });
            }

            function startTimer() {
                secondsElapsed = 0;
                timerInterval = setInterval(() => {
                    secondsElapsed++;
                    let hrs = Math.floor(secondsElapsed / 3600).toString().padStart(2, '0');
                    let mins = Math.floor((secondsElapsed % 3600) / 60).toString().padStart(2, '0');
                    let secs = (secondsElapsed % 60).toString().padStart(2, '0');
                    document.getElementById('meetingTimer').innerText = `${hrs}:${mins}:${secs}`;
                }, 1000);
            }

            function sendReaction(emoji) {
                playUIClick();
                const div = document.createElement('div');
                div.className = 'reaction-fly';
                div.innerText = emoji;
                div.style.left = (Math.random() * 80 + 10) + '%';
                div.style.setProperty('--rx', (Math.random() * 100 - 50) + 'px');
                document.getElementById('meetingRoom').appendChild(div);
                setTimeout(() => div.remove(), 2000);
            }

            function toggleMeetingSidebar(type) {
                playUIClick();
                const sidebar = document.getElementById('meetingSidebar');
                const title = document.getElementById('sidebarTitle');
                const content = document.getElementById('sidebarContent');
                const footer = document.getElementById('sidebarFooter');
                
                if (sidebar.style.display === 'flex' && sidebar.dataset.type === type) {
                    sidebar.style.display = 'none';
                } else {
                    sidebar.style.display = 'flex';
                    sidebar.dataset.type = type;
                    if(type === 'chat') {
                        title.innerText = 'Neural Chat';
                        content.innerHTML = '<div style="color:#aaa; font-style:italic; font-size:12px;">Welcome to the room chat. Message encryption active.</div>';
                        footer.style.display = 'block';
                    } else {
                        title.innerText = 'Connected Peers';
                        content.innerHTML = '<div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;"><div style="width:8px; height:8px; background:#00ffaa; border-radius:50%;"></div> You (Host)</div>';
                        footer.style.display = 'none';
                    }
                }
            }

            async function enterRoom(code) {
                try {
                    // Request camera/mic access
                    localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                    document.getElementById('localVideo').srcObject = localStream;

                    // Log entry to database
                    let logData = new FormData();
                    logData.append('action', 'log_meeting_entry');
                    logData.append('room_code', code);
                    logData.append('host_name', '<?php echo addslashes($_SESSION['student_name']); ?>');
                    fetch('handlers/sacli_room_handler.php', { method: 'POST', body: logData }).then(res => res.json()).then(d => { if(d.log_id) currentMeetingLogId = d.log_id; });
                    
                    // Stop lobby video
                    const lobbyVid = document.getElementById('lobbyVideo');
                    if(lobbyVid.srcObject) lobbyVid.srcObject.getTracks().forEach(t => t.stop());

                    // Switch UI
                    document.getElementById('meetingLobby').style.display = 'none';
                    document.getElementById('meetingRoom').style.display = 'grid';
                    document.getElementById('currentRoomCode').innerText = code;
                    
                    startTimer();
                    showFlash("Access Link Established: " + code);
                } catch (error) {
                    console.error("Error accessing media devices:", error);
                    showCustomAlert("Could not access camera or microphone. Please allow permissions.");
                }
            }

            function toggleMic() {
                playUIClick();
                if (localStream) {
                    const audioTrack = localStream.getAudioTracks()[0];
                    if (audioTrack) {
                        isMicOn = !isMicOn;
                        audioTrack.enabled = isMicOn;
                        const btn = document.getElementById('btnMic');
                        const micIcon = document.getElementById('micIcon');
                        btn.classList.toggle('active', isMicOn);
                       
                        micIcon.src = isMicOn ? 'mic_24dp_000000_FILL0_wght400_GRAD0_opsz24.png' : 'mic_off_24dp_E3E3E3_FILL0_wght400_GRAD0_opsz24.png';
                        
                        const visualizer = document.getElementById('localVisualizer');
                        if(isMicOn) visualizer.style.display = 'flex'; 
                        else visualizer.style.display = 'none';
                        document.getElementById('localMicDot').classList.toggle('muted', !isMicOn);
                    }
                }
            }

            function toggleCam() {
                playUIClick();
                if (localStream) {
                    const videoEl = document.getElementById('localVideo');
                    const avatarEl = document.getElementById('localAvatar');
                    const videoTrack = localStream.getVideoTracks()[0];
                    if (videoTrack) {
                        isCamOn = !isCamOn;
                        videoTrack.enabled = isCamOn;
                        const btn = document.getElementById('btnCam');
                        const camIcon = document.getElementById('camIcon');
                        btn.classList.toggle('active', isCamOn);
                        camIcon.src = isCamOn ? 'videocam_24dp_000000_FILL0_wght400_GRAD0_opsz24.png' : 'videocam_off_24dp_FFFFFF_FILL0_wght400_GRAD0_opsz24.png';
                        
                        if (isCamOn) {
                            videoEl.style.display = 'block';
                            avatarEl.style.display = 'none';
                        } else {
                            videoEl.style.display = 'none';
                            avatarEl.style.display = 'flex';
                        }
                    }
                }
            }

            function leaveMeeting() {
                playUIClick();
                if (localStream) {
                    localStream.getTracks().forEach(track => track.stop());
                }
                
                if(currentMeetingLogId) {
                    let logData = new FormData();
                    logData.append('action', 'log_meeting_exit');
                    logData.append('log_id', currentMeetingLogId);
                    fetch('handlers/sacli_room_handler.php', { method: 'POST', body: logData }).then(() => loadMeetingHistory());
                }

                clearInterval(timerInterval);
                document.getElementById('meetingRoom').style.display = 'none';
                document.getElementById('meetingLobby').style.display = 'block';
                showFlash("You left the meeting.");
            }
        </script>
    <?php elseif(isset($_GET['page']) && $_GET['page'] == 'security'): ?>
        <!-- PASSWORD AND SECURITY VIEW -->
        <div class="security-page-wrapper" style="width: 100%; max-width: 900px; animation: fadeInUp 0.5s ease;">
            <div class="total-count-banner" style="background: linear-gradient(135deg, #1a3d2f, #05100c); border: 1px solid #00ffaa; margin-bottom: 30px; width: 100%; left: 0;">
                <h2 style="letter-spacing: 2px;">HISTORY AND PASSWORD</h2>
                <span style="color: #00ffaa; opacity: 0.8;">Account Integrity & Session Management</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Password Section -->
                <div class="post security-card-premium" style="margin: 0; background: rgba(26, 61, 47, 0.3); border: 1px solid rgba(0, 255, 170, 0.2); position: relative; overflow: hidden;">
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 2px; background: linear-gradient(90deg, transparent, #00ffaa, transparent); opacity: 0.5;"></div>
                    <h3 style="color: #00ffaa; font-size: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                        <img src="8icons8-setting-50.png" style="width: 20px; filter: drop-shadow(0 0 5px #00ffaa);"> Authentication Protocols
                    </h3>
                    <p style="font-size: 13px; color: #b0fce0; margin-bottom: 20px; opacity: 0.8;">Manage your credential integrity and neural link access.</p>
                    <button onclick="openChangePassModal()" class="sr-btn" style="width: 100%; justify-content: center; margin-bottom: 15px; background: linear-gradient(90deg, #00ffaa, #00cc88); color: #0a1f16; border: none; box-shadow: 0 0 15px rgba(0,255,170,0.3);">UPDATE ACCESS CODE</button>
                    <div style="padding: 15px; background: rgba(0,0,0,0.3); border-radius: 12px; border: 1px solid rgba(0,255,170,0.1);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 13px; font-weight: bold; color: #fff;">Dual-Auth (2FA)</span>
                            <label class="switch">
                                <input type="checkbox" id="mfaToggle" <?php echo (isset($me['mfa_enabled']) && $me['mfa_enabled'] == 1) ? 'checked' : ''; ?> onchange="toggleMFA(this.checked)">
                                <span class="slider round"></span>
                            </label>
                        </div>
                        <small style="color: #888; font-size: 11px;">Add an extra layer of security using Google Authenticator.</small>
                    </div>
                </div>

                <!-- Audit Logs Section -->
                <div class="post security-card-premium" style="margin: 0; background: rgba(26, 61, 47, 0.3); border: 1px solid rgba(0, 255, 170, 0.2);">
                    <h3 style="color: #00ffaa; font-size: 16px; margin-bottom: 20px;">Activity Stream</h3>
                    <div style="max-height: 200px; overflow-y: auto;" id="auditLogsContainer">
                        <?php
                        $logs = $conn->query("SELECT * FROM security_audit_logs WHERE user_id = '$my_id' ORDER BY timestamp DESC LIMIT 10");
                        while($log = $logs->fetch_assoc()):
                            $color = ($log['event_type'] == 'FAILED_LOGIN') ? '#ff5555' : '#00ffaa';
                        ?>
                        <div style="font-size: 12px; margin-bottom: 10px; padding-bottom: 5px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <span style="color: <?php echo $color; ?>; font-weight: bold;">[<?php echo $log['event_type']; ?>]</span>
                            <span style="color: #aaa; margin-left: 10px;"><?php echo date("M d, H:i", strtotime($log['timestamp'])); ?></span>
                            <div style="color: #eee; font-size: 10px;"><?php echo $log['ip_address']; ?> • <?php echo substr($log['user_agent'], 0, 40); ?>...</div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Active Sessions Section -->
            <div class="post security-card-premium" style="margin-top: 20px; width: 100%; max-width: 100%; border-color: rgba(0,255,170,0.1);">
                <h3 style="color: #00ffaa; font-size: 16px; margin-bottom: 20px; display: flex; justify-content: space-between;">
                    ACTIVE UPLINKS
                    <button onclick="logoutAllDevices()" style="font-size: 10px; padding: 5px 15px; border-radius: 20px; background: rgba(255,85,85,0.1); color: #ff5555; border: 1px solid #ff5555; cursor: pointer; font-weight: bold;">REVOKE ALL LINKS</button>
                </h3>
                <div style="display: grid; gap: 10px;">
                    <?php
                    $sessions = $conn->query("SELECT * FROM user_active_sessions WHERE user_id = '$my_id' ORDER BY last_activity DESC");
                    while($sess = $sessions->fetch_assoc()):
                        $is_current = (isset($_COOKIE['SECURE_SESS']) && $_COOKIE['SECURE_SESS'] === $sess['session_token']);
                    ?>
                    <div style="background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <span style="font-size: 24px;"><?php echo (strpos($sess['device_info'], 'Windows') !== false) ? '💻' : '📱'; ?></span>
                            <div>
                                <div style="font-weight: bold;"><?php echo $sess['ip_address']; ?> <?php if($is_current) echo '<span style="color:#00ffaa; font-size:10px;">[THIS_DEVICE]</span>'; ?></div>
                                <small style="color: #888;"><?php echo date("M d, Y • h:i A", strtotime($sess['last_activity'])); ?></small>
                            </div>
                        </div>
                        <?php if(!$is_current): ?>
                        <button onclick="terminateSession('<?php echo $sess['id']; ?>')" class="sp-delete-btn">Revoke</button>
                        <?php else: ?><span style="color:#00ffaa; font-size:10px; font-weight:bold;">SECURE</span><?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Login History Section (Combined) -->
            <div class="post security-card-premium" style="margin-top: 20px; width: 100%; max-width: 100%; border-color: rgba(0,255,170,0.1);">
                <h3 style="color: #00ffaa; font-size: 16px; margin-bottom: 20px;">LOGIN_HISTORY</h3>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; color: #e4e6eb;">
                        <thead>
                            <tr style="border-bottom: 2px solid rgba(0, 255, 170, 0.3); text-align: left;">
                                <th style="padding: 15px; color: #00ffaa; font-size: 12px; text-transform: uppercase;">Device</th>
                                <th style="padding: 15px; color: #00ffaa; font-size: 12px; text-transform: uppercase;">Location</th>
                                <th style="padding: 15px; color: #00ffaa; font-size: 12px; text-transform: uppercase;">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $hist_sql = "SELECT * FROM login_history WHERE student_id = '$my_id' ORDER BY login_time DESC LIMIT 20";
                            $hist_res = $conn->query($hist_sql);
                            
                            if($hist_res && $hist_res->num_rows > 0){
                                while($h = $hist_res->fetch_assoc()){
                                    $ua = $h['device_info'];
                                    $os = "Unknown OS";
                                    if (strpos($ua, 'Windows') !== false) $os = "Windows";
                                    elseif (strpos($ua, 'Mac') !== false) $os = "Mac";
                                    elseif (strpos($ua, 'Linux') !== false) $os = "Linux";
                                    elseif (strpos($ua, 'Android') !== false) $os = "Android";
                                    elseif (strpos($ua, 'iPhone') !== false) $os = "iPhone";
                                    
                                    $browser = "Unknown Browser";
                                    if (strpos($ua, 'Chrome') !== false) $browser = "Chrome";
                                    elseif (strpos($ua, 'Firefox') !== false) $browser = "Firefox";
                                    elseif (strpos($ua, 'Safari') !== false) $browser = "Safari";
                                    elseif (strpos($ua, 'Edge') !== false) $browser = "Edge";

                                    $device_name = "$os - $browser";
                                    $time = date("M d, Y • h:i A", strtotime($h['login_time']));
                                    $location = !empty($h['location']) ? $h['location'] : 'Unknown';
                                    $safe_loc = htmlspecialchars(addslashes($location));
                                    $safe_dev = htmlspecialchars(addslashes($device_name));
                                    $safe_time = htmlspecialchars(addslashes($time));

                                    echo "<tr style='border-bottom: 1px solid rgba(255,255,255,0.05); transition: 0.2s; cursor: pointer;' 
                                              onmouseover='this.style.background=\"rgba(0, 255, 170, 0.05)\"' 
                                              onmouseout='this.style.background=\"transparent\"'
                                              onclick=\"openLoginMapModal('$safe_loc', '$safe_dev', '$safe_time')\">";
                                    echo "<td style='padding: 15px; display:flex; align-items:center; gap:10px;'><span style='font-size:20px;'>📱</span> <b>$device_name</b></td>";
                                    echo "<td style='padding: 15px;'>".$location."</td>";
                                    echo "<td style='padding: 15px; color:#aaa;'>$time</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='3' style='padding:30px; text-align:center; color:#888;'>No login history found.</td></tr>";
                                echo "<tr><td colspan='3' style='padding:30px; text-align:center; color:#555;'>[!] NO_DATA_STREAMS_IN_BUFFER</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <style>
            /* Toggle Switch Style */
            .switch { position: relative; display: inline-block; width: 40px; height: 20px; }
            .switch input { opacity: 0; width: 0; height: 0; }
            .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #333; transition: .4s; border-radius: 34px; }
            .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
            input:checked + .slider { background-color: #00ffaa; }
            input:checked + .slider:before { transform: translateX(20px); }
        </style>
    <?php else: ?>
    <!-- DASHBOARD / FEED VIEW -->

    <div class="create-post">
        <div style="display:flex; align-items:center; gap:10px; width:100%;">
            <img src="<?php echo $my_pic; ?>" class="post-profile-img">
            <!-- Form for direct text posting on Enter -->
            <form id="textPostForm" method="POST" action="handlers/post_handler.php" style="flex:1;">
                <input type="text" name="content" class="create-post-input" placeholder="What's on your mind?" required style="border:none; outline:none; cursor:text; background: rgba(0,0,0,0.2); color: #b0b3b8; width:100%;">
            </form>
        </div>

        
        <div style="width:100%; height:1px; background:rgba(255,255,255,0.1);"></div>
        
        <div style="display:flex; justify-content:space-between; align-items:center; width:100%; gap:10px;">
            <!-- This button opens the modal for posts with media -->
            <button onclick="openModal()" style="width:auto; flex-grow:1;">Create Post</button>
            <div id="realtime-clock" style="color: #00ffaa; font-family: 'Courier New', Courier, monospace; font-size: 16px; font-weight: bold; background: rgba(0,0,0,0.2); padding: 8px 12px; border-radius: 5px; border: 1px solid rgba(0,255,170,0.1);"></div>
        </div>
    </div>

    <!-- Dashboard Stats -->
    <div class="story-stats-container">
        <div class="story-stat-card" onclick="window.location.href='SacliConnect.php?page=alumni'">
            <div class="story-stat-count"><?php echo $alumni_count; ?></div>
            <div class="story-stat-label">Alumni Accounts</div>
        </div>
        <div class="story-stat-card" onclick="openDirectoryChoiceModal()">
            <div class="story-stat-count" style="font-size: 28px;"><?php echo $student_count . " | " . $teacher_count; ?></div>
            <div class="story-stat-label">Students | Teachers</div>
        </div>
        <div class="story-stat-card" onclick="window.location.href='SacliConnect.php?page=students'">  
            <div class="story-stat-count"><?php echo $total_registered; ?></div>
            <div class="story-stat-label">Total Registered</div>
        </div>
    </div>

    <?php
    // Fetch the nearest upcoming event
    $today_for_event = date('Y-m-d');
    $upcoming_event_res = $conn->query("SELECT * FROM calendar_events WHERE event_date >= '$today_for_event' ORDER BY event_date ASC LIMIT 1");
    if ($upcoming_event_res && $upcoming_event_res->num_rows > 0):
        $event = $upcoming_event_res->fetch_assoc();
    ?>
    <!-- Upcoming Event Card -->
    <div class="upcoming-event-card" style="cursor:pointer;" onclick='openEventModal(<?php echo htmlspecialchars(json_encode($event), ENT_QUOTES, 'UTF-8'); ?>)'>
        <?php if ($_SESSION['student_name'] === 'Admin'): ?>
        <button onclick="event.stopPropagation(); broadcastEvent(<?php echo $event['id']; ?>)" 
                style="position: absolute; top: 10px; right: 10px; background: #00ffaa; border: none; border-radius: 5px; padding: 6px 12px; font-size: 11px; font-weight: bold; cursor: pointer; z-index: 10; color: #0a1f16; box-shadow: 0 0 15px rgba(0,255,170,0.4);" 
                onmouseover="this.style.background='#fff'" onmouseout="this.style.background='#00ffaa'">📢BROADCAST EMAIL</button>
        <?php endif; ?>
        <h3>Now Event</h3>
        <h2><?php echo htmlspecialchars($event['title']); ?></h2>
        <p><?php echo date("F d, Y", strtotime($event['event_date'])); ?></p>
    </div>
    <?php endif; ?>

    <?php
    // Check for highlighted post (from notification)
    $highlight_id = isset($_GET['highlight_post']) ? intval($_GET['highlight_post']) : 0;
    $is_alumni_posts = (isset($_GET['page']) && $_GET['page'] == 'alumni_posts');

    // JOIN posts with students table to get the latest profile picture
    $category_filter = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
    // Improved query to handle both Students and Teachers
    $sql = "SELECT p.*, 
            COALESCE(s.profile_pic, t.profile_pic) as profile_pic, 
            COALESCE(s.student_id, CONCAT('T-', t.id)) as poster_id, 
            p.id as post_id 
            FROM posts p 
            LEFT JOIN students s ON p.student_name = s.student_name
            LEFT JOIN teachers t ON p.student_name = t.name";
    
    $conditions = [];
    if ($category_filter) {
        $conditions[] = "p.category = '$category_filter'";
    }
    if ($is_alumni_posts) {
        $conditions[] = "s.is_alumni = 1";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY p.timestamp DESC";
    $posts = $conn->query($sql);
    
    if($posts->num_rows == 0 && $highlight_id > 0){
        echo '<p style="text-align:center;color:#aaa;padding:20px;">This post is no longer available.</p>';
    }

    $latest_post_id = 0;
    $first_post_check = true;

    echo '<div id="feed-posts-container">'; // Wrapper for posts
    while($post = $posts->fetch_assoc()){
        if($first_post_check){
            $latest_post_id = $post['post_id'];
            $first_post_check = false;
        }
        $post_id = $post['post_id'];
        $poster_pic = !empty($post['profile_pic']) ? "uploads/".$post['profile_pic'] : "assets/images/3icons8-student-64.png";
        $profile_link = !empty($post['poster_id']) ? "Student_Profile.php?id=".$post['poster_id'] : "#";
        $flash_class = ($highlight_id == $post_id) ? 'flash-post' : '';
        
        // Fetch Tags with IDs (Join both students and teachers)
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

        echo '<div class="post '.$flash_class.'" id="post-'.$post_id.'">';
        // Use the shared render file to display content
        // START of post render content
        $post_content = htmlspecialchars($post['content']);
        // Find and highlight @mentions
        $post_content = preg_replace('/@([\w\d\s.-]+)/', '<span class="tagged-user">@$1</span>', $post_content);
        ?>
        <!-- Post Header -->
        <div style="display: flex; align-items: center; margin-bottom: 10px;">
            <a href="<?php echo $profile_link; ?>">
                <img src="<?php echo $poster_pic; ?>" class="post-profile-img">
            </a>
            <div>
                <h4 style="margin: 0; color: #e4e6eb; font-size: 15px;">
                    <a href="<?php echo $profile_link; ?>" style="color: inherit; text-decoration: none; font-weight: 600;">
                        <?php echo htmlspecialchars($post['student_name']); ?>
                    </a>
                    <?php echo $tag_display; ?>
                </h4>
                <span class="time" style="color: #b0b3b8; font-size: 12px;"><?php echo date("M d, Y H:i", strtotime($post['timestamp'])); ?></span>
            </div>
        </div>

        <!-- Post Content -->
        <div class="post-content-container">
            <?php
            $threshold = 300;
            if (mb_strlen($post_content) > $threshold) {
                $truncated = mb_substr($post_content, 0, $threshold);
                echo '<p class="content-text-truncated" style="white-space: pre-wrap; margin-top: 10px; margin-bottom: 10px; line-height: 1.5;">' . $truncated . '... <span class="see-more-link" onclick="expandPost(this)" style="color: #00ffaa; cursor: pointer; font-weight: bold;">See More...</span></p>';
                echo '<p class="content-text-full" style="white-space: pre-wrap; margin-top: 10px; margin-bottom: 10px; line-height: 1.5; display: none;">' . $post_content . ' <span class="see-less-link" onclick="collapsePost(this)" style="color: #00ffaa; cursor: pointer; font-weight: bold;">See Less</span></p>';
            } else {
                echo '<p style="white-space: pre-wrap; margin-top: 10px; margin-bottom: 10px; line-height: 1.5;">'.$post_content.'</p>';
            }
            ?>
        </div>

        <!-- Media Grid -->
        <?php
        // --- MULTI-MEDIA DISPLAY LOGIC ---
        $media_res = $conn->query("SELECT * FROM post_media WHERE post_id='$post_id'");
        $media_files = [];
        if($media_res && $media_res->num_rows > 0){
            while($m = $media_res->fetch_assoc()) $media_files[] = $m;
        }

        // Backward compatibility for old single file posts
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

                $onclick_attr = $is_video ? 'onclick="openVideoViewer('.$post_id.', \''.$path.'\')"' : 'onclick="openLightbox('.$post_id.', '.$i.')"';

                echo '<div class="media-item" '.$onclick_attr.'>';
                if ($is_video) {
                    echo '<video class="feed-video" src="'.$path.'" loop playsinline autoplay ontimeupdate="updateProgress(this)" onloadedmetadata="updateProgress(this)"></video>';
                    echo '<div class="video-play-icon"></div>';
                    echo '<div class="video-interface" onclick="event.stopPropagation()">
                            <input type="range" class="video-seek-slider" min="0" max="100" value="0" oninput="seekVideo(this)">
                            <div class="video-bottom-row">
                                <span class="video-time">0:00 / 0:00</span>
                                <div class="video-volume-control">
                                    <div class="video-mute-button" onclick="toggleMute(this)"><svg class="icon-muted-state" style="display:none;" viewBox="0 0 24 24"><path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"></path></svg><svg class="icon-unmuted-state" style="display:block;" viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"></path></svg></div>
                                    <input type="range" class="volume-slider" min="0" max="1" step="0.1" value="1" oninput="setVolume(this)">
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
        ?>

        <!-- Interactions (Like & Comment) -->
        <?php
        $liked = $conn->query("SELECT id FROM post_reactions WHERE post_id='$post_id' AND student_id='$my_id'")->num_rows > 0;
        $like_count = $conn->query("SELECT COUNT(id) as c FROM post_reactions WHERE post_id='$post_id'")->fetch_assoc()['c'];
        $comment_count = $conn->query("SELECT COUNT(id) as c FROM post_comments WHERE post_id='$post_id'")->fetch_assoc()['c'];
        $like_class = $liked ? 'liked' : '';
        ?>
        <div class="post-actions">
            <button class="action-btn <?php echo $like_class; ?>" onclick="toggleLike(this, <?php echo $post_id; ?>)">
                <span class="heart-icon"><?php echo $liked ? '❤️' : '🤍'; ?></span> 
                <span class="like-count"><?php echo $like_count > 0 ? $like_count : 'Like'; ?></span>
            </button>
            <button class="action-btn" onclick="document.getElementById('comment-input-<?php echo $post_id; ?>').focus()">
                💬 Comment <?php if($comment_count > 0) echo '('.$comment_count.')'; ?>
            </button>
        </div>

        <!-- Comments Section -->
        <div class="comment-section">
            <?php
            $count_q = $conn->query("SELECT COUNT(*) as c FROM post_comments WHERE post_id='$post_id'");
            $total_c = $count_q->fetch_assoc()['c'];
            if($total_c > 2) {
                echo '<span class="view-all-comments" onclick="openAllCommentsModal('.$post_id.')">View all '.$total_c.' comments</span>';
            }
            ?>
            <div class="comment-list" id="comment-list-<?php echo $post_id; ?>">
                <?php
                $comments = $conn->query("SELECT * FROM (SELECT c.*, COALESCE(s.student_name, t.name) as student_name, COALESCE(s.profile_pic, t.profile_pic) as profile_pic FROM post_comments c LEFT JOIN students s ON c.student_id = s.student_id LEFT JOIN teachers t ON c.student_id = CONCAT('T-', t.id) WHERE c.post_id='$post_id' ORDER BY c.timestamp DESC LIMIT 2) AS sub ORDER BY timestamp ASC");
                while($c = $comments->fetch_assoc()){
                    $c_pic = !empty($c['profile_pic']) ? "uploads/".$c['profile_pic'] : "assets/images/3icons8-student-64.png";
                    $is_my_comment = ($c['student_id'] == $my_id);
                    $is_post_owner = ($post['student_name'] == $_SESSION['student_name']);
                    $is_pinned = $c['is_pinned'] == 1;

                    echo '<div class="comment-item '.($is_pinned ? 'pinned' : '').'" id="comment-'.$c['id'].'">
                            <img src="'.$c_pic.'" class="comment-avatar">
                            <div class="comment-bubble">';
                    if($is_pinned) echo '<div class="pinned-label">📌 Pinned</div>';
                    echo '      <div class="comment-header">
                                    <strong>'.htmlspecialchars($c['student_name']).'</strong>
                                    <span class="comment-time">'.date("M d H:i", strtotime($c['timestamp'])).'</span>
                                </div>
                                <p>'.htmlspecialchars($c['comment']).'</p>
                            </div>';
                    
                    if ($is_my_comment || $is_post_owner) {
                        echo '<div class="comment-actions">
                                <button class="dots-btn">•••</button>
                                <div class="comment-menu">';
                        if($is_post_owner && !$is_pinned) echo '<div class="comment-menu-item" onclick="pinComment('.$c['id'].', '.$post_id.')">Pin</div>';
                        if($is_post_owner && $is_pinned) echo '<div class="comment-menu-item" onclick="unpinComment('.$c['id'].', '.$post_id.')">Unpin</div>';
                        if($is_my_comment) echo '<div class="comment-menu-item delete" onclick="deleteComment('.$c['id'].')">Delete</div>';
                        echo '  </div>
                              </div>';
                    }
                    echo '</div>'; // End of .comment-item
                }
                ?>
            </div>
            <div class="comment-input-area">
                <img src="<?php echo $my_pic; ?>" class="comment-avatar">
                <input type="text" id="comment-input-<?php echo $post_id; ?>" class="comment-input" placeholder="Write a comment..." onkeypress="handleComment(event, <?php echo $post_id; ?>)">
            </div>
        </div>
        <?php
        // END of post render content
        echo '</div>';
    }
    echo '</div>'; // End of wrapper
    ?>

    <script>
        let currentLatestPostId = <?php echo $latest_post_id; ?>;
        let currentCategory = "<?php echo $category_filter; ?>";
        let isAlumniPage = "<?php echo $is_alumni_posts ? '1' : '0'; ?>";
        let isSyncing = false; // Prevent multiple syncs at once
        const feedContainer = document.getElementById('feed-posts-container');
 
        setInterval(function() {
            if (isSyncing) return;
 
            // Prevent sync if user is typing or a modal is open, to avoid disruption
            if (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA') return;
            if (document.querySelector('.modal[style*="display: flex"]')) return;
 
            isSyncing = true;
 
            // Get all post IDs currently visible to the client
            const postElements = feedContainer.querySelectorAll('.post[id^="post-"]');
            const clientIds = Array.from(postElements).map(el => parseInt(el.id.replace('post-', '')));
 
            let formData = new FormData();
            formData.append('action', 'sync_feed');
            formData.append('latest_id', currentLatestPostId);
            formData.append('client_ids', JSON.stringify(clientIds));
            formData.append('category', currentCategory);
            formData.append('is_alumni', isAlumniPage);
            
            fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                // Handle deleted posts
                if (data.deleted_ids && data.deleted_ids.length > 0) {
                    data.deleted_ids.forEach(id => {
                        const postEl = document.getElementById('post-' + id);
                        if (postEl) {
                            postEl.style.transition = 'opacity 0.5s, transform 0.3s';
                            postEl.style.opacity = '0';
                            postEl.style.transform = 'scale(0.95)';
                            setTimeout(() => postEl.remove(), 500);
                        }
                    });
                }
 
                // Handle new posts
                if (data.new_posts_html) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = data.new_posts_html;
                    // Prepend new posts, which are received in ASC order, so we reverse them
                    Array.from(tempDiv.children).reverse().forEach(newPost => {
                        feedContainer.prepend(newPost);
                        // Trigger fade-in animation
                        setTimeout(() => {
                            newPost.style.opacity = 1;
                            newPost.style.transform = 'translateY(0)';
                        }, 50);
                    });
                }
 
                // Update the latest ID to prevent re-fetching the same posts
                if (data.latest_id > currentLatestPostId) {
                    currentLatestPostId = data.latest_id;
                }
                isSyncing = false;
            }).catch(error => {
                console.error('Feed sync error:', error);
                isSyncing = false;
            });
        }, 5000); // Check every 5 seconds
    </script>
    <?php endif; ?>
</div>

<!-- ================= Right Sidebar ================= -->
<?php if($is_settings_page): ?>
    <!-- SETTINGS PANEL (Split View) -->
    <div class="settings-panel">
        <!-- Top Half: My Posts -->
        <div class="sp-half">
            <div class="sp-title">Manage My Posts</div>
            <?php
            $my_name = $_SESSION['student_name'];
            $my_posts_sql = "SELECT * FROM posts WHERE student_name = ? ORDER BY timestamp DESC";
            $stmt = $conn->prepare($my_posts_sql);
            $stmt->bind_param("s", $my_name);
            $stmt->execute();
            $my_posts_res = $stmt->get_result();

            if ($my_posts_res->num_rows > 0) {
                while($post = $my_posts_res->fetch_assoc()) {
                    echo '<div class="sp-post-card" id="my-post-'.$post['id'].'">';
                    echo '<div class="sp-post-header">';
                    echo '<span class="sp-date">'.date("M d, Y • H:i", strtotime($post['timestamp'])).'</span>';
                    echo '<button class="sp-delete-btn" onclick="deleteMyPost('.$post['id'].', this)">Delete</button></div>';
                    echo '<div class="sp-content">'.htmlspecialchars($post['content']).'</div>';
                    echo '</div>';
                }
            } else {
                echo '<p style="color:#888; text-align:center; padding:20px;">No posts yet.</p>';
            }
            $stmt->close();
            ?>
        </div>

        <!-- Bottom Half: My Groups -->
        <div class="sp-half">
            <div class="sp-title">Manage Group Chats</div>
            <div style="display:flex; flex-direction:column;">
            <?php
            $my_id = $_SESSION['student_id'];
            $groups_sql = "SELECT g.id, g.name, g.group_icon FROM group_chats g JOIN group_chat_members m ON g.id = m.group_id WHERE m.user_id = '$my_id'";
            $groups_res = $conn->query($groups_sql);

            if($groups_res && $groups_res->num_rows > 0){
                while($g = $groups_res->fetch_assoc()){
                    $g_pic = !empty($g['group_icon']) ? "uploads/".$g['group_icon'] : "7icons8-organization-64.png";
                    echo '<div class="sp-group-card" id="group-card-'.$g['id'].'">';
                    echo '  <img src="'.$g_pic.'" class="sp-group-img">';
                    echo '  <div class="sp-group-info">';
                    echo '      <h4>'.htmlspecialchars($g['name']).'</h4>';
                    echo '  </div>';
                    echo '  <button onclick="leaveGroup('.$g['id'].')" class="sp-leave-btn">Leave</button>';
                    echo '</div>';
                }
            } else {
                echo '<p style="color:#888; text-align:center; padding:20px;">No groups joined.</p>';
            }
            ?>
            </div>
        </div>
    </div>
<?php elseif(isset($_GET['page']) && $_GET['page'] == 'evaluates'): ?>
    <!-- EVALUATION HISTORY SIDEBAR -->
    <div class="right-sidebar">
        <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
            <!-- Focus Mode Toggle Button -->
            <button id="focusModeBtn" onclick="toggleFocusMode()" style="margin-right: 10px; background: rgba(0, 255, 170, 0.1); border: 1px solid #00ffaa; color: #00ffaa; padding: 10px; border-radius: 50%; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; width: 45px; height: 45px;" title="Toggle Post Zoom (Focus Mode)">
                <svg class="icon-maximize" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
                </svg>
                <svg class="icon-minimize" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path>
                </svg>
            </button>
            <button class="Btn1" onclick="showLogoutModal(); return false;">
                <div class="sign1">
                    <svg viewBox="0 0 512 512">
                        <path d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0 75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"></path>
                    </svg>
                </div>
                <div class="text">Logout</div>
            </button>
        </div>

        <h3 style="margin-top: 20px; color: #00ffaa; border-bottom: 1px solid #00ffaa; padding-bottom: 10px;">Finished Evaluates</h3>
        
        <div style="max-height: calc(100vh - 150px); overflow-y: auto;">
            <?php
            $history_sql = "SELECT e.*, t.name, t.department, t.profile_pic 
                            FROM evaluations e 
                            JOIN teachers t ON e.teacher_id = t.id 
                            WHERE e.student_id = '$my_id' 
                            ORDER BY e.date_evaluated DESC";
            $history_res = $conn->query($history_sql);
            
            if($history_res && $history_res->num_rows > 0):
                while($h = $history_res->fetch_assoc()):
                    $pic = !empty($h['profile_pic']) ? "uploads/".$h['profile_pic'] : "4icons8-teacher-50.png";
            ?>
            <div style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 10px; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.1); cursor: pointer; transition: 0.2s;" onclick="window.location.href='Student_Profile.php?id=T-<?php echo $h['teacher_id']; ?>'" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.05)'" title="Click to view profile and reset">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                    <img src="<?php echo $pic; ?>" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid #aaa;">
                    <div>
                        <div style="font-size: 13px; font-weight: bold; color: #fff;"><?php echo htmlspecialchars($h['name']); ?></div>
                        <div style="font-size: 11px; color: #aaa;"><?php echo date("M d, Y", strtotime($h['date_evaluated'])); ?></div>
                    </div>
                </div>
                <div style="color: #ffd700; font-size: 12px;">
                    <?php for($i=0; $i<$h['rating']; $i++) echo '★'; ?>
                    <span style="color: #888; font-size: 11px; margin-left: 5px;">(<?php echo $h['rating']; ?>/5)</span>
                </div>
            </div>
            <?php endwhile; else: ?>
                <p style="color: #888; text-align: center; font-size: 13px;">No evaluations yet.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <!-- Standard Right Sidebar -->
<div class="right-sidebar">
    <div style="display:flex;justify-content:flex-end;margin-bottom:20px;">
        <!-- Focus Mode Toggle Button -->
        <button id="focusModeBtnMain" onclick="toggleFocusMode()" style="margin-right: 10px; background: rgba(0, 255, 170, 0.1); border: 1px solid #00ffaa; color: #00ffaa; padding: 10px; border-radius: 50%; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; width: 45px; height: 45px;" title="Toggle Post Zoom (Focus Mode)">
            <svg class="icon-maximize" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path>
            </svg>
            <svg class="icon-minimize" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18v-3a2 2 0 0 1 2-2h3M3 16h3a2 2 0 0 1 2 2v3"></path>
            </svg>
        </button>
        <!-- From Uiverse.io by Lealdos -->
        <button class="Btn1" onclick="showLogoutModal(); return false;">
            <div class="sign1">
                <svg viewBox="0 0 512 512">
                    <path
                        d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0 75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"
                    ></path>
                </svg>
            </div>
            <div class="text">Logout</div>
        </button>

    </div>

    <h3 style="margin-top: 20px;">Access Shortcuts</h3>
    
    <?php
    $access_links = $conn->query("SELECT * FROM subject_chats ORDER BY sort_order");
    if($access_links) {
        while($row = $access_links->fetch_assoc()){
            $url = $row['is_online'] ? $row['url'] : 'javascript:void(0);';
            $onclick = $row['is_online'] ? '' : 'onclick="showCustomAlert(\'This site is currently unavailable right now.\')"';
            $status_class = $row['is_online'] ? 'status online' : 'status';
            $icon = !empty($row['icon']) ? (file_exists("uploads/".$row['icon']) ? "uploads/".$row['icon'] : $row['icon']) : 'St.Anne_logo.png';
            
            echo '<a href="'.$url.'" '.$onclick.' class="gc" style="text-decoration:none; color:inherit;">
                    <img src="'.$icon.'" onerror="this.src=\'St.Anne_logo.png\'" style="width:32px; height:32px; border-radius:50%; margin-right:10px;">
                    <span>'.htmlspecialchars($row['name']).'</span>
                    <div class="'.$status_class.'"></div>
                  </a>';
        }
    }
    ?>

    <!-- Student List for Chat -->
    <h3 style="margin-top: 20px;">School Directory</h3>
    <div class="dir-wrap">
        <input checked type="radio" id="dir-active" name="dir-filter" class="dir-rd-1" onchange="setDirFilter('active')">
        <label for="dir-active" class="dir-label">Active</label>
        <input type="radio" id="dir-student" name="dir-filter" class="dir-rd-2" onchange="setDirFilter('student')">
        <label for="dir-student" class="dir-label">Students</label>
        <input type="radio" id="dir-teacher" name="dir-filter" class="dir-rd-3" onchange="setDirFilter('teacher')">
        <label for="dir-teacher" class="dir-label">Teachers</label>
        <div class="dir-slidebar"></div>
    </div>
    <div style="margin-bottom: 10px;">
        <input type="text" id="studentSearch" placeholder="Search name..." onkeyup="filterStudents()" style="width:100%; padding:8px; border-radius:20px; border:none; background:rgba(255,255,255,0.1); color:white; outline:none;">
    </div>
    
    <div style="max-height: 500px; overflow-y: auto;" id="studentListContainer">
        <!-- User list will be loaded here by AJAX -->
        <div style="text-align:center; color:#888; padding: 20px;">Loading directory...</div>
    </div>
</div>
<?php endif; ?>

<!-- ================= Create Post Modal ================= -->
<div class="modal" id="postModal">
    <div class="modal-content" style="width:70%;">
        <span class="close" onclick="closeModal()">&times;</span>
            <h3 style="color:#00ffaa;">Create Post...</h3>
        <form method="POST" action="handlers/post_handler.php" enctype="multipart/form-data">
            <div style="position:relative;">
                <textarea name="content" id="postContent" placeholder="Write something... Use @ to tag people" required></textarea>
                <div id="mentionSuggestions" class="mention-dropdown"></div>
            </div>
            <div id="taggedUsersContainer"></div>
            <div class="upload-section">
                <input type="file" name="media[]" id="mediaUpload" accept="image/*,video/*" multiple onchange="handleFileSelect(event)">
                <label for="mediaUpload">📷 Upload Photos / Videos</label>
            </div>
            <div id="mediaPreview" class="photo-preview"></div>
            <select name="category">
                <option value="General">General</option>
                <option value="Announcement">Announcement</option>
                <option value="Academic">Academic</option>
                <option value="Event">Event</option>
                <option value="Emergency">Emergency</option>
                <option value="Organization">Organization</option>
            </select>

            <button type="submit" id="postSubmitButton" style="background: linear-gradient(90deg, #00ffaa, #00cc88); color: #0a1f16; font-weight: 800; border: none; padding: 12px; border-radius: 30px; width: 100%; margin-top: 15px; cursor: pointer; font-size: 16px; letter-spacing: 1px; box-shadow: 0 5px 15px rgba(0, 255, 170, 0.3); transition: 0.3s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(0, 255, 170, 0.5)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 5px 15px rgba(0, 255, 170, 0.3)'">POST</button>
        </form>
    </div>
</div>

<!-- ================= Event Details Modal ================= -->
<div class="modal" id="eventModal">
    <div class="modal-content event-card-style">
        <span class="close-btn-event" onclick="closeEventModal()">&times;</span>
        
        <div class="event-header-section">
            <h3 id="evModalTitle"></h3>
        </div>

        <div class="event-body-section">
            <img id="evModalImg" src="" alt="Event Image">
            
            <div class="event-meta-grid">
                <div class="meta-item">
                    <span class="meta-icon">📅</span>
                    <span id="evModalDate"></span>
                </div>
                <div class="meta-item">
                    <span class="meta-icon">⏰</span>
                    <span id="evModalTime"></span>
                </div>
            </div>
            
            <div class="event-desc-box">
                <p id="evModalDesc"></p>
            </div>
        </div>
    </div>
</div>

<!-- ================= Logout Confirmation Modal ================= -->
<div class="modal" id="logoutModal">
    <div class="modal-content logout-modal-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout?</p>  
        <div class="logout-buttons">
            <button class="logout-confirm-btn" onclick="confirmLogout()">Logout</button>
            <button class="logout-cancel-btn" onclick="closeLogoutModal()">Cancel</button>
        </div>
    </div>
</div>

<!-- ================= Student/Teacher Choice Modal ================= -->
<div class="modal" id="directoryChoiceModal">
    <div class="modal-content" style="max-width: 400px; text-align: center; background: #102e22; border: 1px solid #00ffaa;">
        <span class="close" onclick="closeDirectoryChoiceModal()">&times;</span>
        <h3 style="color: #00ffaa; margin-bottom: 25px;">View Directory</h3>
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <button onclick="window.location.href='SacliConnect.php?page=students'" style="background: #00ffaa; color: #0a1f16; border: none; padding: 15px; border-radius: 10px; font-weight: bold; cursor: pointer; font-size: 16px; transition: 0.2s;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                View Students
            </button>
            <button onclick="window.location.href='SacliConnect.php?page=teachers'" style="background: rgba(0, 255, 170, 0.2); color: #00ffaa; border: 1px solid #00ffaa; padding: 15px; border-radius: 10px; font-weight: bold; cursor: pointer; font-size: 16px; transition: 0.2s;" onmouseover="this.style.transform='scale(1.03)'" onmouseout="this.style.transform='scale(1)'">
                View Teachers
            </button>
        </div>
    </div>
</div>

<!-- ================= Creator Info Modal ================= -->
<div class="modal" id="creatorModal">
    <div class="modal-content creator-content">
        <span class="close" onclick="closeCreatorModal()">&times;</span>
        <div class="connection-grid">
            <div class="connection-hub"></div>
            <div class="connect-line"></div> <!-- Left Line -->
            <div class="connect-line"></div> <!-- Right Line -->
            <div class="data-particle"></div>
            <div class="data-particle" style="animation-delay: 3.5s;"></div>
        </div>
        <div class="creator-flex">
            <div class="creator-profile-section">
                <div class="creator-img-box">
                    <img src="Screenshot 2024-09-05 221942.png" alt="Creator">
                </div>
                <h2 class="creator-name">Justin Ritardo</h2>
                <p class="creator-role">System Developer & Creator</p>
            </div>
            <div class="creator-brand-section">
                <h1 class="neon-brand">SACLI<br>CONNECT</h1>
                <p class="brand-subtitle">Centralized School Communication And Social Platform</p>
            </div>
        </div>
    </div>
</div>

<!-- ================= Alumni Details Modal ================= -->
<div class="modal" id="alumniModal">
    <div class="modal-content alumni-modal-content">
        <span class="close" onclick="closeAlumniModal()" style="color: #ffd700; z-index: 10;">&times;</span>
        <div class="alumni-profile-wrapper">
            <div class="alumni-img-box">
                <img id="alumniModalPic" src="" alt="Alumni">
            </div>
            <div class="alumni-badge" id="alumniModalBatch">Batch 202X</div>
            <h2 id="alumniModalName" class="alumni-name"></h2>
            <p id="alumniModalAge" style="color: #aaa; font-size: 14px; margin-bottom: 5px;"></p>
            <div class="alumni-course" id="alumniModalCourse"></div>
            <div class="alumni-course" id="alumniModalLocation" style="font-size: 13px; color: #9ab; display: flex; align-items: center; justify-content: center; gap: 5px; margin-top: 5px;"></div>
            
            <div class="alumni-divider"></div>
            
            <div class="alumni-status-box">
                <span class="alumni-status-label">Current Status / Achievement</span>
                <p id="alumniModalStatus" class="alumni-status-text"></p>
            </div>
        </div>
    </div>
</div>

<!-- ================= Create Group Modal ================= -->
<div class="modal" id="createGroupModal">
    <div class="modal-content" style="width:80%;">
        <span class="close" onclick="closeCreateGroupModal()">&times;</span>
        <h3>Create Group Chat</h3>
        <input type="text" id="newGroupName" placeholder="Group Name" style="width:100%; padding:10px; margin-bottom:10px; border-radius:5px; border:1px solid #00ffaa; background:#1a3d2f; color:white;">
        
        <div style="margin-bottom:10px;">
            <label style="color:#b0fce0; font-size:13px; display:block; margin-bottom:5px;">Group Icon (Optional)</label>
            <input type="file" id="newGroupIcon" accept="image/*" style="width:100%; padding:8px; background:rgba(0,0,0,0.2); border:1px solid rgba(0,255,170,0.3); border-radius:5px; color:white;">
        </div>
        
        <input type="text" id="gcSearch" placeholder="Search students to add..." onkeyup="filterCandidates('gcList', 'gcSearch')" style="width:100%; padding:10px; border-radius:5px; border:1px solid #00ffaa; background:#1a3d2f; color:white;">
        
        <div style="max-height: 200px; overflow-y: auto; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 10px; margin-top: 10px;" id="gcList">
            <?php
            $all_students = $conn->query("SELECT student_id, student_name, profile_pic FROM students WHERE student_id != '$my_id' ORDER BY student_name");
            if($all_students){
                while($s = $all_students->fetch_assoc()){
                    $pic = !empty($s['profile_pic']) ? "uploads/".$s['profile_pic'] : "assets/images/3icons8-student-64.png";
                    echo '<div class="candidate-item" style="display: flex; align-items: center; gap: 10px; padding: 5px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <input type="checkbox" class="gc-checkbox" value="'.$s['student_id'].'">
                            <img src="'.$pic.'" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                            <span class="c-name" style="color: #e4e6eb; font-size: 14px;">'.htmlspecialchars($s['student_name']).'</span>
                          </div>';
                }
            }
            ?>
        </div>
        <button onclick="submitCreateGroup()" style="width:100%; margin-top:10px; padding:10px; background:#00ffaa; border:none; border-radius:5px; font-weight:bold; cursor:pointer;">Create Group</button>
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

<!-- ================= All Comments Modal ================= -->
<div class="modal" id="allCommentsModal">
    <div class="modal-content" style="max-width: 500px; background: #102e22; border: 1px solid #00ffaa; border-radius: 15px; display: flex; flex-direction: column; height: 80vh; overflow: hidden; padding:0;">
        <div style="padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="color:#00ffaa; margin:0;">Comments</h3>
            <span class="close" onclick="closeAllCommentsModal()" style="float:none; line-height:1;">&times;</span>
        </div>
        <div id="modalCommentsList" style="flex:1; overflow-y:auto; padding: 20px;"></div>
        <div class="comment-input-area" style="padding: 15px; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2);">
            <img src="<?php echo $my_pic; ?>" class="comment-avatar">
            <input type="text" id="modalCommentInput" class="comment-input" placeholder="Write a comment..." onkeypress="handleModalComment(event)">
        </div>
    </div>
</div>

<!-- ================= Add Member Modal ================= -->
<div class="modal" id="addMemberModal">
    <div class="modal-content" style="max-width: 400px;">
        <span class="close" onclick="closeAddMemberModal()">&times;</span>
        <h3 style="color:#00ffaa;">Add Members</h3>
        <input type="text" id="addMemberSearch" placeholder="Search students..." onkeyup="filterCandidates('addMemberList', 'addMemberSearch')" style="width:100%; padding:10px; border-radius:5px; border:1px solid #00ffaa; background:#1a3d2f; color:white; margin-bottom:10px;">
        
        <div style="max-height: 250px; overflow-y: auto; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; padding: 10px;" id="addMemberList">
            <div style="text-align:center; color:#aaa; padding:10px;">Loading...</div>
        </div>
        <button onclick="submitAddMembers()" style="width:100%; margin-top:15px; padding:12px; background:#00ffaa; border:none; border-radius:5px; font-weight:bold; cursor:pointer; color:#0a1f16;">Add Selected</button>
    </div>
</div>

<!-- ================= Create Room Modal ================= -->
<div class="modal" id="createRoomModal">
    <div class="modal-content">
        <span class="close" onclick="closeCreateRoomModal()">&times;</span>
        <h3>Create New Room</h3>
        <input type="text" id="newRoomName" placeholder="Room Name (e.g., Web Development 101)" style="width:100%; padding:10px; margin-bottom:10px; border-radius:5px; border:1px solid #00ffaa; background:#1a3d2f; color:white;">
        <input type="text" id="newRoomCode" placeholder="Set a unique Room Code" style="width:100%; padding:10px; margin-bottom:10px; border-radius:5px; border:1px solid #00ffaa; background:#1a3d2f; color:white; text-transform:uppercase;">
        <textarea id="newRoomDesc" placeholder="Description (Optional)" rows="3" style="width:100%; padding:10px; margin-bottom:10px; border-radius:5px; border:1px solid #00ffaa; background:#1a3d2f; color:white; resize:vertical;"></textarea>
        <button onclick="submitCreateRoom()" style="width:100%; margin-top:10px; padding:10px; background:#00ffaa; border:none; border-radius:5px; font-weight:bold; cursor:pointer;">Create Room</button>
    </div>
</div>

<!-- ================= Join Room Modal ================= -->
<div class="modal" id="joinRoomModal">
    <div class="modal-content" style="width:60%;">
        <span class="close" onclick="closeJoinRoomModal()">&times;</span>
        <h3>Join Room</h3>
        <p style="color:#b0fce0; font-size:14px; margin-bottom:15px;">Ask your teacher for the room code, then enter it here.</p>
        <input type="text" id="joinRoomCode" placeholder="Room Code" style="width:100%; padding:10px; margin-bottom:10px; border-radius:5px; border:1px solid #00ffaa; background:#1a3d2f; color:white; text-transform:uppercase;">
        <button onclick="submitJoinRoom()" style="width:100%; margin-top:10px; padding:10px; background:#00ffaa; border:none; border-radius:5px; font-weight:bold; cursor:pointer;">Join</button>
    </div>
</div>

<!-- ================= Custom Alert Modal ================= -->
<div class="modal" id="customAlertModal" style="z-index: 20000;">
    <div class="modal-content" style="max-width: 300px; padding: 20px;">
        <h3 style="color: #00ffaa; margin-bottom: 10px;">Notification</h3>
        <p id="customAlertText" style="color: #e4e6eb; margin-bottom: 20px;"></p>
        <button id="customAlertOkBtn" onclick="closeCustomAlert()" style="background: #00ffaa; color: #0a1f16; border: none; padding: 8px 20px; border-radius: 15px; font-weight: bold; cursor: pointer; width: 100%;">OK</button>
    </div>
</div>

<!-- ================= Custom Confirm Modal ================= -->
<div class="modal" id="customConfirmModal" style="z-index: 20001;">
    <div class="modal-content" style="max-width: 300px; padding: 20px;">
        <h3 style="color: #00ffaa; margin-bottom: 10px;">Confirmation</h3>
        <p id="customConfirmText" style="color: #e4e6eb; margin-bottom: 20px;"></p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button id="confirmYesBtn" style="background: #00ffaa; color: #0a1f16; border: none; padding: 8px 20px; border-radius: 15px; font-weight: bold; cursor: pointer; flex: 1;">Yes</button>
            <button id="confirmNoBtn" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 15px; font-weight: bold; cursor: pointer; flex: 1;">Cancel</button>
        </div>
    </div>
</div>

<!-- ================= Custom Prompt Modal ================= -->
<div class="modal" id="customPromptModal" style="z-index: 20002;">
    <div class="modal-content" style="max-width: 300px; padding: 20px;">
        <h3 style="color: #00ffaa; margin-bottom: 10px;">Edit</h3>
        <p id="customPromptText" style="color: #e4e6eb; margin-bottom: 10px;"></p>
        <input type="text" id="customPromptInput" style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #00ffaa; background: #1a3d2f; color: white; margin-bottom: 20px; outline: none;">
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button id="promptOkBtn" style="background: #00ffaa; color: #0a1f16; border: none; padding: 8px 20px; border-radius: 15px; font-weight: bold; cursor: pointer; flex: 1;">Save</button>
            <button onclick="closeCustomPrompt()" style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 15px; font-weight: bold; cursor: pointer; flex: 1;">Cancel</button>
        </div>
    </div>
</div>

<!-- ================= Lightbox Modal (Premium Post Viewer) ================= -->
<div id="lightboxModal" class="video-modal">
    <span class="close-video-modal" onclick="closeLightbox()">&times;</span>
    <div class="video-modal-content">
        <div class="video-section">
            <button onclick="prevSlide()" style="position: absolute; left: 20px; background: rgba(255,255,255,0.1); color: white; border: none; padding: 15px; font-size: 24px; cursor: pointer; border-radius: 50%; z-index: 1000; backdrop-filter: blur(5px);">&#10094;</button>
            
            <div id="lightboxContent" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                <!-- Image/Video goes here -->
            </div>

            <button onclick="nextSlide()" style="position: absolute; right: 20px; background: rgba(255,255,255,0.1); color: white; border: none; padding: 15px; font-size: 24px; cursor: pointer; border-radius: 50%; z-index: 1000; backdrop-filter: blur(5px);">&#10095;</button>
            <div id="lightboxCounter" style="position: absolute; bottom: 20px; color: white; font-size: 14px; background: rgba(0,0,0,0.5); padding: 5px 15px; border-radius: 20px; font-family: 'Orbitron', sans-serif;"></div>
        </div>
        
        <div class="details-section" id="lightboxDetailsSection">
            <div class="details-header">
                <img id="lightboxProfilePic" src="" alt="">
                <div>
                    <h4 id="lightboxUsername"></h4>
                    <span id="lightboxTime"></span>
                </div>
            </div>
            <div class="details-body">
                <p id="lightboxCaption" class="viewer-caption"></p>
                <div class="viewer-stats">
                    <span id="lightboxViewCount" class="view-count-badge">👁️‍🗨️ 0 Views</span>
                    <span id="lightboxLikeCount"></span>
                </div>
                <div class="viewer-actions">
                    <button id="lightboxLikeBtn" class="viewer-action-btn" onclick="toggleLightboxLike()">❤️ Like</button>
                    <button class="viewer-action-btn" onclick="document.getElementById('lightboxCommentInput').focus()">💬 Comment</button>
                </div>
                <div class="viewer-comments" id="lightboxCommentsList"></div>
            </div>
            <div class="details-footer">
                <input type="text" id="lightboxCommentInput" class="viewer-input" placeholder="Write a comment..." onkeypress="handleLightboxComment(event)">
            </div>
        </div>
    </div>
</div>

<!-- ================= Chat Box Widget ================= -->
<div id="chatBox" class="chat-box-container">
    <div class="cb-header">
        <div style="display:flex; align-items:center; gap:10px; flex:1;" onclick="goToChatProfile()">
            <img id="chatHeaderImg" src="assets/images/3icons8-student-64.png" style="width:30px; height:30px; border-radius:50%; object-fit:cover; border:1px solid #00ffaa;">
            <span class="cb-title" id="chatTitle">Chat</span>
        </div>
        
        <!-- Dropdown Toggle Button -->
        <div id="chatOptionsToggle" onclick="toggleChatOptions(event)" style="cursor:pointer; color:#00ffaa; font-size:18px; margin-right:10px; font-weight:bold; line-height:1; position:relative;" title="Chat Options">
            &#x25BC; <!-- Down arrow character -->
            <div id="chatOptionsMenu" class="cb-options-menu">
                <!-- Open Full Chat Link (moved inside dropdown) -->
                <div id="openFullChatBtn" onclick="openFullChat()" class="cb-options-item" style="display:none; color:#00ffaa; font-weight:bold; text-transform: uppercase; white-space: nowrap;" title="Open in Messenger Style">
                    Open in Messenger
                </div>
                <!-- Add Member Icon (moved inside dropdown) -->
                <div id="addMemberBtn" onclick="openAddMemberModal()" class="cb-options-item" style="display:none; color:#00ffaa; font-weight:bold; white-space: nowrap;" title="Add Member">Add Member</div>
            </div>
        </div>

        <span class="cb-minimize" onclick="minimizeChat()" style="cursor:pointer; color:#00ffaa; font-size:24px; margin-right:10px; font-weight:bold; line-height:1;" title="Minimize">−</span>
        <span class="cb-close" onclick="closeChat()">×</span>
    </div>
    <div class="cb-body" id="chatBody"></div>

    <!-- Typing Indicator for Mini Chat -->
    <div id="miniTypingIndicator" style="display:none; padding: 10px 15px; background: transparent; border: none; margin-bottom: 5px;">
        <div style="display:flex; align-items:center; gap:8px;">
            <img id="miniTypingPic" src="" style="width:24px; height:24px; border-radius:50%; border:1.5px solid var(--accent); object-fit: cover; flex-shrink: 0;">
            <div class="typing-indicator-mini">
                <span></span><span></span><span></span>
            </div>
        </div>
    </div>
    
    <div id="chatFilePreviewArea" style="display:none; padding:10px; background:rgba(0,0,0,0.5); border-top:1px solid rgba(255,255,255,0.1); position:relative;">
        <img id="chatPreviewImg" style="max-height:100px; max-width:100px; border-radius:5px; border:1px solid #00ffaa; display:none;">
        <video id="chatPreviewVideo" style="max-height:100px; max-width:100px; border-radius:5px; border:1px solid #00ffaa; display:none;"></video>
        <div id="chatPreviewFile" style="color:white; display:none; padding:8px; border:1px solid #00ffaa; border-radius:5px; background:rgba(0,255,170,0.1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px;"></div>
        <span onclick="clearChatFile()" style="position:absolute; top:5px; right:10px; cursor:pointer; color:#ff5555; font-weight:bold; font-size:20px;">&times;</span>
    </div>

    <div class="cb-footer">
        <label for="chatFileInput" style="cursor:pointer; margin-right:8px; display:flex; align-items:center;" title="Send Photo/Video"><img src="gallery.png" alt="Media" style="width:24px; height:24px;"></label>
        <input type="file" id="chatFileInput" style="display:none;" accept="image/*,video/*" onchange="previewChatFile('media')">
        
        <label for="chatDocInput" style="cursor:pointer; margin-right:8px; display:flex; align-items:center;" title="Send File"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#00ffaa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg></label>
        <input type="file" id="chatDocInput" style="display:none;" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar" onchange="previewChatFile('doc')">
        
        <input type="text" id="chatInput" class="cb-input" placeholder="Type a message..." onkeypress="handleEnter(event)">
        <button class="cb-send" onclick="sendMessage()">➤</button>
    </div>
</div>

<!-- ================= Evaluation Modal (Google Form Style - Dark Theme) ================= -->
<div class="modal" id="evaluationModal" style="background: rgba(0,0,0,0.8);">
    <div class="modal-content" style="max-width: 640px; width: 95%; padding: 0; background: #0a1f16; border-radius: 8px; overflow: hidden; color: #e4e6eb; font-family: 'Roboto', Arial, sans-serif; border: 1px solid #00ffaa;">
        
        <div style="max-height: 90vh; overflow-y: auto;">
            <!-- Header Image/Strip -->
            <div style="height: 10px; background: #00ffaa; border-top-left-radius: 8px; border-top-right-radius: 8px; box-shadow: 0 0 10px #00ffaa;"></div>
            
            <div style="padding: 20px;">
                <!-- Title Card -->
                <div style="background: #1a3d2f; border-radius: 8px; padding: 24px; margin-bottom: 12px; border: 1px solid rgba(0, 255, 170, 0.3); border-top: none; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">
                    <span class="close" onclick="closeEvaluationModal()" style="position: absolute; top: 10px; right: 15px; color: #00ffaa; font-size: 28px; cursor: pointer;">&times;</span>
                    <h2 style="margin: 0 0 10px; font-size: 32px; font-weight: 400; color: #00ffaa;">Faculty Evaluation</h2>
                    <div style="height: 1px; background: rgba(0, 255, 170, 0.3); margin: 15px 0;"></div>
                    <div style="display:flex; align-items:center; gap:15px; margin-top:15px;">
                        <img id="evalTeacherPic" src="" style="width:60px; height:60px; border-radius:50%; object-fit:cover; border: 2px solid #00ffaa;">
                        <div>
                            <h4 id="evalTeacherName" style="margin:0; font-size:18px; color: #fff; font-weight: 500;"></h4>
                            <p style="margin:5px 0 0; color: #b0fce0; font-size: 14px;">Please evaluate the faculty member based on their performance.</p>
                        </div>
                    </div>
                    <p style="color: #ff5555; font-size: 12px; margin-top: 10px;">* Required</p>
                </div>
                
                <form method="POST" action="SacliConnect.php">
                    <input type="hidden" name="action" value="submit_evaluation">
                    <input type="hidden" name="teacher_id" id="evalTeacherId">
                    
                    <?php 
                    $eval_questions = [];
                    $eq_res = $conn->query("SELECT * FROM evaluation_questions ORDER BY category ASC, id ASC");
                    if($eq_res) while($row = $eq_res->fetch_assoc()) $eval_questions[] = $row;
                    
                    if(empty($eval_questions)): ?>
                        <div style="background: #1a3d2f; border-radius: 8px; padding: 24px; text-align:center; color:#aaa; border: 1px solid rgba(0, 255, 170, 0.3);">No evaluation questions available.</div>
                    <?php else: ?>
                        <?php 
                        $current_cat = "";
                        foreach($eval_questions as $index => $q): 
                            if($q['category'] != $current_cat):
                                $current_cat = $q['category'];
                        ?>
                            <div style="margin: 20px 0 10px; padding: 10px; background: rgba(0, 255, 170, 0.1); border-left: 4px solid #00ffaa; border-radius: 4px;">
                                <h3 style="margin:0; color: #00ffaa; font-size: 18px; text-transform: uppercase;"><?php echo htmlspecialchars($current_cat); ?></h3>
                            </div>
                        <?php endif; ?>
                        <div style="background: #1a3d2f; border-radius: 8px; padding: 24px; margin-bottom: 12px; border: 1px solid rgba(0, 255, 170, 0.3); transition: box-shadow 0.3s; box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                            <p style="margin:0 0 20px; font-size: 16px; color: #fff; font-weight: 500;"><?php echo htmlspecialchars($q['question']); ?> <span style="color: #ff5555;">*</span></p>
                            
                            <div style="display: flex; justify-content: space-between; align-items: flex-end; max-width: 100%; overflow-x: auto;">
                                <span style="font-size: 12px; color: #b0fce0; margin-bottom: 5px; margin-right: 10px;">Poor</span>
                                <?php for($i=1; $i<=5; $i++): ?>
                                <label style="cursor:pointer; display:flex; flex-direction:column; align-items:center; margin: 0 10px;">
                                    <span style="margin-bottom: 15px; font-size: 14px; color: #fff;"><?php echo $i; ?></span>
                                    <input type="radio" name="rating[<?php echo $q['id']; ?>]" value="<?php echo $i; ?>" required style="accent-color: #00ffaa; width: 20px; height: 20px; cursor: pointer;">
                                </label>
                                <?php endfor; ?>
                                <span style="font-size: 12px; color: #b0fce0; margin-bottom: 5px; margin-left: 10px;">Excellent</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Comments Card -->
                    <div style="background: #1a3d2f; border-radius: 8px; padding: 24px; margin-bottom: 12px; border: 1px solid rgba(0, 255, 170, 0.3); box-shadow: 0 2px 5px rgba(0,0,0,0.2);">
                        <p style="margin:0 0 15px; font-size: 16px; color: #fff; font-weight: 500;">Comments / Feedback</p>
                        <div style="position: relative;">
                            <textarea name="comments" rows="1" placeholder="Your answer" style="width: 100%; border: none; border-bottom: 1px solid rgba(0, 255, 170, 0.5); padding: 5px 0; outline: none; font-family: inherit; font-size: 14px; resize: none; background: transparent; color: #fff;" oninput="this.style.height = ''; this.style.height = this.scrollHeight + 'px'" onfocus="this.style.borderBottom = '2px solid #00ffaa'" onblur="this.style.borderBottom = '1px solid rgba(0, 255, 170, 0.5)'"></textarea>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-bottom: 20px;">
                        <button type="submit" style="background: #00ffaa; color: #004d33; border: none; padding: 10px 24px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 0 10px rgba(0, 255, 170, 0.4);">Submit</button>
                        <span style="color: #00ffaa; font-size: 14px; cursor: pointer; font-weight: 500;" onclick="document.querySelector('#evaluationModal form').reset()">Clear form</span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ================= Concern Chat Box ================= -->
<div id="concernChatBox" class="chat-box-container" style="right: 20px;">
    <div class="cb-header">
        <div style="display:flex; align-items:center; gap:10px;">
            <img src="Adobe Express - file.png" style="width:30px; height:30px; border-radius:50%; object-fit:cover;">
            <span class="cb-title">Admin Support</span>
        </div>
        <span class="cb-close" onclick="closeConcernChat()">×</span>
    </div>
    <div class="cb-body" id="concernBody">
        <!-- Messages will be loaded here -->
        <div class='msg other-msg'>Hello! How can we help you with your account or any other concerns?</div>
    </div>
    <div class="cb-footer">
        <input type="text" id="concernInput" class="cb-input" placeholder="Type your concern..." onkeypress="handleConcernEnter(event)">
        <button class="cb-send" onclick="sendConcern()">➤</button>
    </div>
</div>

<!-- ================= Change Password Modal (Authentication Flow) ================= -->
<div class="modal" id="changePassModal">
    <div class="modal-content" style="max-width: 450px; background: linear-gradient(135deg, #05100c 0%, #0a2a1f 100%); border: 1px solid #00ffaa; border-radius: 12px; padding: 0; text-align: center; overflow: hidden; box-shadow: 0 0 50px rgba(0,255,170,0.2);">
        
        <!-- Modal Header -->
        <div style="background: rgba(0,255,170,0.1); padding: 30px 20px; border-bottom: 1px solid rgba(0,255,170,0.2); position: relative;">
            <span class="close" onclick="closeChangePassModal()" style="position: absolute; top: 15px; right: 20px; color: #00ffaa; font-size: 28px; cursor: pointer;">&times;</span>
            <div id="cpIconAnim" style="font-size: 40px; margin-bottom: 10px; animation: logoFloat 3s infinite ease-in-out;">🔐</div>
            <h3 id="cpModalTitle" style="color: #fff; margin: 0; text-transform: uppercase; letter-spacing: 3px; font-weight: 900; text-shadow: 0 0 10px #00ffaa;">Update Access</h3>
        </div>
        
        <!-- Step 1: Current Password -->
        <div id="cpStep1" class="cp-step" style="padding: 40px;">
            <div style="font-family: 'Courier New', monospace; font-size: 11px; color: #00ffaa; margin-bottom: 10px; text-align: left;">// IDENTITY_VERIFICATION_REQUIRED</div>
            <p style="color: #b0fce0; font-size: 14px; margin-bottom: 20px;">Enter your current <strong style="color:#0088ff">Password</strong> to receive a verification code.</p>
            <div style="position: relative; margin-bottom: 25px;">
                <input type="password" id="cpCurrentPass" class="form-input" placeholder="••••••••" style="width: 100%; padding: 15px; background: rgba(0,0,0,0.4); border: 1px solid rgba(0,255,170,0.3); color: #fff; border-radius: 8px; outline: none; text-align: center; font-size: 18px; letter-spacing: 2px;">
            </div>
            <button onclick="cpVerifyPassword(event)" class="save-btn" style="width: 100%; margin: 0; padding: 15px; background: #00ffaa; color: #0a1f16; font-weight: bold; border-radius: 8px; cursor: pointer;">INITIALIZE UPLINK</button>
        </div>

        <!-- Step 2: OTP Code -->
        <div id="cpStep2" class="cp-step" style="display:none; padding: 40px;">
            <div style="font-family: 'Courier New', monospace; font-size: 11px; color: #00ffaa; margin-bottom: 10px; text-align: left;">// VERIFICATION_PACKET_SENT</div>
            <p style="color: #b0fce0; font-size: 13px; margin-bottom: 20px;">A secure link has been sent to:<br><strong id="cpSentEmail" style="color: #fff; font-size: 14px;"></strong></p>
            
            <!-- Individual Boxes for OTP -->
            <div style="display: flex; gap: 10px; justify-content: center; margin-bottom: 30px;">
                <input type="text" maxlength="1" class="otp-box-modern" oninput="moveNextCP(this, 1)" onkeydown="moveBackCP(this, event, 0)">
                <input type="text" maxlength="1" class="otp-box-modern" oninput="moveNextCP(this, 2)" onkeydown="moveBackCP(this, event, 1)">
                <input type="text" maxlength="1" class="otp-box-modern" oninput="moveNextCP(this, 3)" onkeydown="moveBackCP(this, event, 2)">
                <input type="text" maxlength="1" class="otp-box-modern" oninput="moveNextCP(this, 4)" onkeydown="moveBackCP(this, event, 3)">
                <input type="text" maxlength="1" class="otp-box-modern" oninput="moveNextCP(this, 5)" onkeydown="moveBackCP(this, event, 4)">
                <input type="text" maxlength="1" class="otp-box-modern" oninput="moveNextCP(this, 6)" onkeydown="moveBackCP(this, event, 5)">
            </div>
            
            <input type="hidden" id="cpOTP">
            <button onclick="cpVerifyOTP(event)" class="save-btn" style="width: 100%; margin: 0; padding: 15px; background: #00ffaa; color: #0a1f16; font-weight: bold; border-radius: 8px;">VALIDATE CODE</button>
           
            <div style="margin-top: 15px; font-size: 11px; color: #888;">Code expires in <span id="cpTimer" style="color: #ff5555;">01:00</span></div>
        </div>

        <!-- Step 3: New Passwords -->
        <div id="cpStep3" class="cp-step" style="display:none; padding: 40px;">
            <div style="font-family: 'Courier New', monospace; font-size: 11px; color: #00ffaa; margin-bottom: 10px; text-align: left;">// ENCRYPTION_REWRITE_READY</div>
            <p style="color: #b0fce0; font-size: 14px; margin-bottom: 20px;">Neural match confirmed. Set your new access code.</p>
            <input type="password" id="cpNewPass" class="form-input" placeholder="New Password" style="width: 100%; padding: 15px; background: rgba(0,0,0,0.4); border: 1px solid rgba(0,255,170,0.2); color: white; border-radius: 8px; margin-bottom: 15px; outline: none; text-align: center;">
            <input type="password" id="cpConfirmPass" class="form-input" placeholder="Confirm Password" style="width: 100%; padding: 15px; background: rgba(0,0,0,0.4); border: 1px solid rgba(0,255,170,0.2); color: white; border-radius: 8px; margin-bottom: 25px; outline: none; text-align: center;">
            <button onclick="cpFinalize(event)" class="save-btn" style="width: 100%; margin: 0; padding: 15px; background: #00ffaa; color: #0a1f16; font-weight: bold; border-radius: 8px;">OVERWRITE PROTOCOL</button>
        </div>
    </div>
</div>

<style>
    .otp-box-modern { width: 45px; height: 55px; background: rgba(0,0,0,0.5); border: 1px solid rgba(0,255,170,0.3); border-radius: 8px; color: #00ffaa; font-size: 24px; font-weight: bold; text-align: center; outline: none; transition: 0.3s; font-family: monospace; }
    .otp-box-modern:focus { border-color: #00ffaa; box-shadow: 0 0 15px rgba(0,255,170,0.4); transform: scale(1.1); background: rgba(0,255,170,0.05); }
            /* --- PREMIUM SECURITY INTERFACE ENHANCEMENTS --- */
            @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap');

            .security-page-wrapper {
                width: 100%;
                max-width: 950px !important;
                margin: 0 auto;
                padding-bottom: 60px;
            }

            /* Custom banner for Security */
            .security-page-wrapper .total-count-banner {
                background: linear-gradient(135deg, #05100c 0%, #0a2a1f 100%) !important;
                border-bottom: 2px solid #00ffaa !important;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5) !important;
            }

            .security-page-wrapper .total-count-banner h2 {
                font-family: 'Orbitron', sans-serif;
                text-shadow: 0 0 15px rgba(0, 255, 170, 0.6);
            }

            .security-card-premium {
                background: rgba(16, 46, 34, 0.3) !important;
                backdrop-filter: blur(15px);
                border: 1px solid rgba(0, 255, 170, 0.15) !important;
                border-radius: 20px !important;
                padding: 30px !important;
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3) !important;
            }

            .security-card-premium:hover {
                transform: translateY(-8px);
                border-color: #00ffaa !important;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5), 0 0 20px rgba(0, 255, 170, 0.15) !important;
            }

            .security-card-premium h3 {
                font-family: 'Orbitron', sans-serif;
                letter-spacing: 2px;
                text-transform: uppercase;
                font-size: 15px !important;
                color: #00ffaa !important;
                border-bottom: 1px solid rgba(0, 255, 170, 0.2);
                padding-bottom: 15px;
                margin-bottom: 25px !important;
            }

            /* Active Sessions List Styling */
            .security-card-premium div[style*="display: grid"] > div[style*="background"] {
                background: rgba(0, 0, 0, 0.4) !important;
                border: 1px solid rgba(255, 255, 255, 0.05) !important;
                border-radius: 15px !important;
                padding: 18px !important;
                margin-bottom: 5px;
                transition: all 0.3s ease;
            }

            .security-card-premium div[style*="display: grid"] > div[style*="background"]:hover {
                background: rgba(0, 255, 170, 0.05) !important;
                border-color: rgba(0, 255, 170, 0.3) !important;
                transform: scale(1.01);
            }

            /* Activity Stream Logs */
            #auditLogsContainer div {
                font-family: 'Consolas', 'Courier New', monospace;
                background: rgba(0, 0, 0, 0.25) !important;
                padding: 15px !important;
                border-radius: 10px !important;
                border-left: 3px solid #00ffaa !important;
                margin-bottom: 12px !important;
                font-size: 11px !important;
                line-height: 1.4;
            }

            /* Revoke/Delete Button */
            .sp-delete-btn {
                background: rgba(255, 85, 85, 0.1) !important;
                color: #ff8a80 !important;
                border: 1px solid rgba(255, 85, 85, 0.3) !important;
                padding: 8px 18px !important;
                border-radius: 20px !important;
                font-size: 10px !important;
                font-weight: 800 !important;
                text-transform: uppercase;
                letter-spacing: 1px;
                transition: 0.3s;
            }

            .sp-delete-btn:hover {
                background: #ff4757 !important;
                color: #fff !important;
                box-shadow: 0 0 15px rgba(255, 71, 87, 0.4);
            }

    .cp-step { animation: stepSlideIn 0.6s cubic-bezier(0.23, 1, 0.32, 1) both; }
    @keyframes stepSlideIn { from { opacity: 0; transform: translateX(30px); filter: blur(5px); } to { opacity: 1; transform: translateX(0); filter: blur(0); } }
    .security-card-premium { transition: all 0.3s ease; }
    .security-card-premium:hover { transform: translateY(-5px); border-color: #00ffaa !important; box-shadow: 0 10px 30px rgba(0,255,170,0.1); }
</style>

<!-- ================= Video Viewer Modal (Facebook Style) ================= -->
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
                    <span id="viewerViewCount" class="view-count-badge">👁️‍🗨️ 0 Views</span>
                    <span id="viewerLikeCount"></span>
                </div>
                <div class="viewer-actions">
                    <button id="viewerLikeBtn" class="viewer-action-btn" onclick="toggleViewerLike()">❤️ Like</button>
                    <button class="viewer-action-btn" onclick="document.getElementById('viewerCommentInput').focus()">💬 Comment</button>
                </div>
                <div class="viewer-comments" id="viewerCommentsList"></div>
            </div>
            <div class="details-footer">
                <div id="viewerMentionDropdown" class="viewer-mention-dropdown"></div>
                <input type="text" id="viewerCommentInput" class="viewer-input" placeholder="Write a comment..." onkeypress="handleViewerComment(event)">
            </div>
        </div>
    </div>
</div>

<!-- ================= Login Detail Map Modal ================= -->
<div class="modal" id="loginMapModal" style="z-index: 20005;">
    <div class="modal-content" style="max-width: 900px; width: 95%; padding: 0; background: #0a1f16; border: 1px solid #00ffaa; overflow: hidden; border-radius: 20px;">
        <div style="padding: 20px; border-bottom: 1px solid rgba(0, 255, 170, 0.2); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="color: #00ffaa; margin: 0; font-family: 'Orbitron'; font-size: 14px;">// ACCESS_POINT_LOCATED</h3>
                <p id="mapModalDetail" style="margin: 5px 0 0; color: #fff; font-size: 12px; opacity: 0.8;"></p>
            </div>
            <span class="close" onclick="closeLoginMapModal()" style="float: none; margin: 0;">&times;</span>
        </div>
        <div id="loginMap" style="width: 100%; height: 550px; background: #000;"></div>
        <div style="padding: 15px; background: rgba(0, 255, 170, 0.05); border-top: 1px solid rgba(0, 255, 170, 0.2); display: flex; justify-content: space-between; align-items: center;">
            <div style="font-family: 'Courier New', monospace; font-size: 11px; color: #509b83;">
                <div>DEVICE: <span id="mapDevice" style="color: #fff;"></span></div>
                <div>TIME: <span id="mapTime" style="color: #fff;"></span></div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 10px; color: #00ffaa; font-weight: bold;">NODE_ENCRYPTION: ACTIVE</div>
                <button onclick="closeLoginMapModal()" class="sr-btn" style="padding: 5px 15px; font-size: 11px; margin-top: 5px;">CLOSE</button>
            </div>
        </div>
    </div>
</div>

<!-- Minimized Chat Bubbles Container -->
<div id="minimizedChatsContainer" class="minimized-chats-container"></div>

<!-- Draggable Chat Head -->
<div id="floatingChatHead" class="floating-chat-head" title="Drag me anywhere!">
    <img src="assets/images/St.Anne_logo.png" alt="Sacli">
</div>

<!-- ================= Scripts ================= -->
<script>
let shownMessageIds = new Set();
let accumulatedFiles = [];
let miniTypingInterval = null;

function openModal() { document.getElementById("postModal").style.display = "flex"; }
function closeModal() { 
    document.getElementById("postModal").style.display = "none"; 
    // Reset form on close
    document.getElementById('mediaUpload').value = '';
    accumulatedFiles = [];
    document.getElementById('mediaPreview').innerHTML = '';
    document.getElementById('taggedUsersContainer').innerHTML = ''; 
    document.getElementById('postContent').value = '';
}

// Function to disable the post button on submit
document.querySelector('#postModal form').addEventListener('submit', function() {
    const submitBtn = document.getElementById('postSubmitButton');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...'; // Optional: Add a spinner icon
    submitBtn.style.cursor = 'not-allowed';
});


function phi() {document.getElementById("alumniModal").style.display = "flex";
    function openElemet() {

    document.getElementById("SacliRoom").value
    }
}

function openDirectoryChoiceModal() { document.getElementById("directoryChoiceModal").style.display = "flex"; }
function closeDirectoryChoiceModal() { document.getElementById("directoryChoiceModal").style.display = "none"; }
function openCreateGroupModal() { document.getElementById("createGroupModal").style.display = "flex"; }
function closeCreateGroupModal() { document.getElementById("createGroupModal").style.display = "none"; }
function openCreateRoomModal() { document.getElementById('createRoomModal').style.display = 'flex'; }
function closeCreateRoomModal() { document.getElementById('createRoomModal').style.display = 'none'; }
function openJoinRoomModal() { document.getElementById('joinRoomModal').style.display = 'flex'; }
function closeJoinRoomModal() { document.getElementById('joinRoomModal').style.display = 'none'; }
function openCreatorModal() { 
    // Play intro sound
    var audio = new Audio('sound intro.mp3');
    audio.volume = 0.7;
    audio.play().catch(function(error) { console.log("Audio play blocked:", error); });

    var modal = document.getElementById("creatorModal");
    var content = modal.querySelector('.creator-content');
    modal.style.display = "flex";
    content.classList.remove('animating'); // Reset animation
    setTimeout(() => {
        content.classList.add('animating'); // Trigger animation
    }, 1100); // Delay before sliding
}
function closeCreatorModal() { document.getElementById("creatorModal").style.display = "none"; }

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

// Concern Chat Box Functions
let concernChatInterval = null;

function openConcernChat() {
    document.getElementById('concernChatBox').style.display = 'flex';
    fetchConcerns();
    checkConcernNotifs(); // Refresh badge immediately
    if(concernChatInterval) clearInterval(concernChatInterval);
    concernChatInterval = setInterval(fetchConcerns, 3000); // Poll every 3 seconds
}

function closeConcernChat() {
    document.getElementById('concernChatBox').style.display = 'none';
    if(concernChatInterval) clearInterval(concernChatInterval);
}

function sendConcern() {
    let msg = document.getElementById('concernInput').value;
    if(!msg.trim()) return;
    
    let formData = new FormData();
    formData.append('action', 'send');
    formData.append('message', msg);
    
    fetch('handlers/concern_handler.php', { method: 'POST', body: formData })
    .then(() => {
        document.getElementById('concernInput').value = '';
        fetchConcerns(); // Fetch immediately after sending
    });
}

function fetchConcerns() {
    fetch('handlers/concern_handler.php', { method: 'POST', body: new URLSearchParams('action=fetch') })
    .then(res => res.text())
    .then(html => {
        document.getElementById('concernBody').innerHTML = html;
    });
}

function checkConcernNotifs() {
    let formData = new FormData();
    formData.append('action', 'check_unread');
    fetch('handlers/concern_handler.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(count => {
        let badge = document.getElementById('concernBadge');
        if(parseInt(count) > 0) {
            badge.innerText = count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    });
}

function deleteConcernMsg(id) {
    showCustomConfirm("Delete this message?", function() {
        let formData = new FormData();
        formData.append('action', 'delete');
        formData.append('msg_id', id);
        fetch('handlers/concern_handler.php', { method: 'POST', body: formData }).then(fetchConcerns);
    });
}

function editConcernMsg(id, oldText) {
    showCustomPrompt("Edit message:", oldText, function(newText) {
        if(newText !== null && newText.trim() !== "") {
            let formData = new FormData();
            formData.append('action', 'edit');
            formData.append('msg_id', id);
            formData.append('message', newText);
            fetch('handlers/concern_handler.php', { method: 'POST', body: formData }).then(fetchConcerns);
        }
    });
}

document.getElementById('confirmYesBtn').onclick = function() {
    if(confirmCallback) confirmCallback();
    closeCustomConfirm();
};

document.getElementById('confirmNoBtn').onclick = function() {
    if(cancelCallback) cancelCallback();
    closeCustomConfirm();
};

let promptCallback = null;
function showCustomPrompt(msg, value, callback) {
    document.getElementById('customPromptText').innerText = msg;
    document.getElementById('customPromptInput').value = value;
    document.getElementById('customPromptModal').style.display = 'flex';
    promptCallback = callback;
}
function closeCustomPrompt() {
    document.getElementById('customPromptModal').style.display = 'none';
    promptCallback = null;
}
document.getElementById('promptOkBtn').onclick = function() {
    let val = document.getElementById('customPromptInput').value;
    if(promptCallback) promptCallback(val);
    closeCustomPrompt();
};

window.onclick = function (e) {
    if(e.target == document.getElementById("alumniModal")) closeAlumniModal();
    if(e.target == document.getElementById("postModal")) closeModal();
    if(e.target == document.getElementById("eventModal")) closeEventModal();
    if(e.target == document.getElementById("createGroupModal")) closeCreateGroupModal();
    if(e.target == document.getElementById("createRoomModal")) closeCreateRoomModal();
    if(e.target == document.getElementById("joinRoomModal")) closeJoinRoomModal();
    if(e.target == document.getElementById("taggedUsersModal")) closeTaggedUsersModal();
    // Close room options menu
    if (!e.target.closest('.sr-options-container')) {
        document.querySelectorAll('.sr-options-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
    }
    if(e.target == document.getElementById("creatorModal")) closeCreatorModal();
    if(e.target == document.getElementById("logoutModal")) closeLogoutModal();
    if(e.target == document.getElementById("customAlertModal")) closeCustomAlert();
    if(e.target == document.getElementById("directoryChoiceModal")) closeDirectoryChoiceModal();
    if(e.target == document.getElementById("customConfirmModal")) closeCustomConfirm();
    if(e.target == document.getElementById("customPromptModal")) closeCustomPrompt();
    if(e.target == document.getElementById("concernChatBox")) closeConcernChat();
    if(e.target == document.getElementById("lightboxModal")) closeLightbox();
    if(e.target == document.getElementById("addMemberModal")) closeAddMemberModal();
    if(e.target == document.getElementById("allCommentsModal")) closeAllCommentsModal();
    if(e.target == document.getElementById("evaluationModal")) closeEvaluationModal();
    if(e.target == document.getElementById("loginMapModal")) closeLoginMapModal();
    if(e.target == document.getElementById("videoViewerModal")) closeVideoViewer();
}

function minimizeChat() {
    if (!currentChatId) return;
    
    const name = document.getElementById('chatTitle').innerText;
    const pic = document.getElementById('chatHeaderImg').src;
    const id = currentChatId;
    const type = currentChatType;

    // Create bubble if it doesn't exist
    if (!document.getElementById('bubble-' + id)) {
        const container = document.getElementById('minimizedChatsContainer');
        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        bubble.id = 'bubble-' + id;
        bubble.title = name;
        bubble.onclick = () => {
            if (type === 'group') openGroupChat(id, name, pic);
            else openChat(id, name, pic);
            bubble.remove();
        };
        
        bubble.innerHTML = `
            <img src="${pic}">
            <div class="close-bubble" onclick="event.stopPropagation(); document.getElementById('bubble-${id}').remove(); if(currentChatId=='${id}') closeChat();">&times;</div>
        `;
        container.appendChild(bubble);
    }

    document.getElementById('chatBox').style.display = 'none';
    // We stop the polling interval but the bubble stays
    if(chatInterval) clearInterval(chatInterval);
    // Important: currentChatId remains set so we know which chat is "active but minimized"
    // but we clear it slightly differently to allow other chats to open
    const tempId = currentChatId;
    currentChatId = null; 
}

function openEvaluationModal(id, name, pic) {
    document.getElementById('evalTeacherId').value = id;
    document.getElementById('evalTeacherName').innerText = name;
    document.getElementById('evalTeacherPic').src = pic;
    document.getElementById('evaluationModal').style.display = 'flex';
}
function closeEvaluationModal() {
    document.getElementById('evaluationModal').style.display = 'none';
}

function toggleRoomMenu(iconElement) {
    // Close all other menus first
    document.querySelectorAll('.sr-options-menu.show').forEach(menu => {
        if (menu !== iconElement.nextElementSibling) {
            menu.classList.remove('show');
        }
    });
    // Toggle the current one
    const menu = iconElement.nextElementSibling;
    menu.classList.toggle('show');
}

function deleteRoom(roomId, roomName) {
    showCustomConfirm(`Are you sure you want to delete the room "${roomName}"? This will remove all members and cannot be undone.`, function() {
        let formData = new FormData();
        formData.append('action', 'delete_room');
        formData.append('room_id', roomId);

        fetch('handlers/sacli_room_handler.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            showFlash(data.message, data.status);
            if (data.status === 'success') {
                setTimeout(() => location.reload(), 1500);
            }
        });
    });
}

function leaveRoom(roomId, roomName) {
    showCustomConfirm(`Are you sure you want to leave the room "${roomName}"?`, function() {
        let formData = new FormData();
        formData.append('action', 'leave_room');
        formData.append('room_id', roomId);

        fetch('handlers/sacli_room_handler.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            showFlash(data.message, data.status);
            if (data.status === 'success') {
                setTimeout(() => location.reload(), 1500);
            }
        });
    });
}

function openCreateRoomModal() { document.getElementById('createRoomModal').style.display = 'flex'; }
function closeCreateRoomModal() { document.getElementById('createRoomModal').style.display = 'none'; }
function openJoinRoomModal() { document.getElementById('joinRoomModal').style.display = 'flex'; }
function closeJoinRoomModal() { document.getElementById('joinRoomModal').style.display = 'none'; }

function submitCreateRoom() {
    let name = document.getElementById('newRoomName').value;
    let code = document.getElementById('newRoomCode').value;
    let desc = document.getElementById('newRoomDesc').value;

    if (!name.trim()) {
        showFlash('Room name is required.', 'error');
        return;
    }
    if (!code.trim()) {
        showFlash('Room code is required.', 'error');
        return;
    }

    let formData = new FormData();
    formData.append('action', 'create_room');
    formData.append('name', name);
    formData.append('code', code);
    formData.append('description', desc);

    fetch('handlers/sacli_room_handler.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        showFlash(data.message, data.status);
        if (data.status === 'success') {
            closeCreateRoomModal();
            setTimeout(() => location.reload(), 1500);
        }
    });
}

function submitJoinRoom() {
    let code = document.getElementById('joinRoomCode').value;
    if (!code.trim()) {
        showFlash('Please enter a room code.', 'error');
        return;
    }
    let formData = new FormData();
    formData.append('action', 'join_room');
    formData.append('code', code);
    fetch('handlers/sacli_room_handler.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        showFlash(data.message, data.status);
        if (data.status === 'success') { closeJoinRoomModal(); setTimeout(() => location.reload(), 1500); }
    });
}


function handleFileSelect(event) {
    const newFiles = Array.from(event.target.files);
    accumulatedFiles = accumulatedFiles.concat(newFiles);
    updateFileInput();
    renderPreviews();
}

function updateFileInput() {
    const dataTransfer = new DataTransfer();
    accumulatedFiles.forEach(file => dataTransfer.items.add(file));
    document.getElementById('mediaUpload').files = dataTransfer.files;
}

function removeFile(index) {
    accumulatedFiles.splice(index, 1);
    updateFileInput();
    renderPreviews();
}

function renderPreviews() {
    const previewContainer = document.getElementById('mediaPreview');
    previewContainer.innerHTML = "";
    
    accumulatedFiles.forEach((file, index) => {
        let container = document.createElement('div');
        container.style.position = 'relative';
        container.style.display = 'inline-block';
        container.style.margin = '5px';
        
        let mediaEl;
        if(file.type.startsWith('image/')){
            mediaEl = document.createElement('img');
        } else {
            mediaEl = document.createElement('video');
        }
        mediaEl.src = URL.createObjectURL(file);
        mediaEl.style.height = "60px";
        mediaEl.style.width = "60px";
        mediaEl.style.objectFit = "cover";
        mediaEl.style.borderRadius = "10px";
        mediaEl.style.border = "1px solid #00ffaa";
        
        let removeBtn = document.createElement('div');
        removeBtn.innerHTML = "×";
        removeBtn.style.position = 'absolute';
        removeBtn.style.top = '-8px';
        removeBtn.style.right = '-8px';
        removeBtn.style.background = '#ff5555';
        removeBtn.style.color = 'white';
        removeBtn.style.borderRadius = '50%';
        removeBtn.style.width = '20px';
        removeBtn.style.height = '20px';
        removeBtn.style.display = 'flex';
        removeBtn.style.alignItems = 'center';
        removeBtn.style.justifyContent = 'center';
        removeBtn.style.cursor = 'pointer';
        removeBtn.style.fontSize = '16px';
        removeBtn.style.fontWeight = 'bold';
        removeBtn.style.boxShadow = '0 2px 5px rgba(0,0,0,0.5)';
        removeBtn.onclick = function() { removeFile(index); };
        
        container.appendChild(mediaEl);
        container.appendChild(removeBtn);
        previewContainer.appendChild(container);
    });
}

// Event Modal Functions
function openEventModal(ev) {
    document.getElementById("evModalTitle").innerText = ev.title;
    document.getElementById("evModalDate").innerText = ev.event_date;
    document.getElementById("evModalDesc").innerText = ev.description;
    
    let img = document.getElementById("evModalImg");
    if(ev.event_image && ev.event_image !== "") {
        img.src = ev.event_image;
        img.style.display = "inline-block";
    } else {
        img.style.display = "none";
    }

    let timeStr = (ev.time_in ? ev.time_in : "") + (ev.time_out ? " - " + ev.time_out : "");
    document.getElementById("evModalTime").innerText = timeStr || "All Day";

    document.getElementById("eventModal").style.display = "flex";
}
function closeEventModal() {
    document.getElementById("eventModal").style.display = "none";
}

// Alumni Modal Functions
function handleAlumniClick(element, data) {
    element.classList.add('alumni-click-effect');
    // Wait slightly for the animation to play before opening the modal
    setTimeout(() => {
        openAlumniModal(data);
        // Remove class after animation completes
        setTimeout(() => element.classList.remove('alumni-click-effect'), 400);
    }, 200);
}
function openAlumniModal(alumniData) {
    // Calculate age from birthdate
    let ageText = 'Age not available';
    if (alumniData.birthdate) {
        const birthDate = new Date(alumniData.birthdate);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        ageText = age + ' years old';
    }

    document.getElementById('alumniModalPic').src = alumniData.profile_pic ? 'uploads/' + alumniData.profile_pic : 'assets/images/3icons8-student-64.png';
    document.getElementById('alumniModalName').innerText = alumniData.name;
    document.getElementById('alumniModalAge').innerText = ageText;
    document.getElementById('alumniModalCourse').innerText = alumniData.course;
    document.getElementById('alumniModalLocation').innerHTML = alumniData.location ? `📍 ${alumniData.location}` : '';
    document.getElementById('alumniModalStatus').innerText = alumniData.status || 'No status provided.';
    document.getElementById('alumniModalBatch').innerText = 'Batch ' + (alumniData.batch_year || 'N/A');
    
    document.getElementById('alumniModal').style.display = 'flex';
}
function closeAlumniModal() { document.getElementById("alumniModal").style.display = "none"; }

// Logout Modal Functions
function showLogoutModal() {
    document.getElementById("logoutModal").style.display = "flex";
}

function closeLogoutModal() {
    document.getElementById("logoutModal").style.display = "none";
}

function confirmLogout() {
    window.location.href = "Logout.php";
}

// Chat Functions
let currentChatId = null;
let currentChatType = 'direct';
let chatInterval = null;
// Check global notifications (Messages & Concerns) every 3s
let notifInterval = setInterval(() => { checkNewMessages(); checkConcernNotifs(); checkAppNotifs(); checkGroupNotifs(); }, 3000);

function openChat(id, name, pic) {
    currentChatType = 'direct';
    currentChatId = id;
    document.getElementById('chatTitle').innerText = name;
    document.getElementById('chatHeaderImg').src = pic;
    document.getElementById('chatBody').innerHTML = '<div style="padding:20px;text-align:center;color:#aaa;">Loading...</div>';
    document.getElementById('chatBox').style.display = 'flex';
    
    // Remove bubble if it exists when opening normally
    if (document.getElementById('bubble-' + id)) document.getElementById('bubble-' + id).remove();

    // Hide Add Member button
    document.getElementById('addMemberBtn').style.display = 'none'; // Inside dropdown
    document.getElementById('openFullChatBtn').style.display = 'none'; // Inside dropdown
    
    fetchChatTheme(); // Sync theme on open
    fetchMessages(true);
    if(chatInterval) clearInterval(chatInterval);
    chatInterval = setInterval(fetchMessages, 2000); // Poll every 2 seconds
    if(miniTypingInterval) clearInterval(miniTypingInterval);
    miniTypingInterval = setInterval(checkTypingStatus, 1500);
    
    // Remove highlight if exists
    let row = document.getElementById('student-row-' + id);
    if(row) row.classList.remove('has-new-msg');
}

function goToChatProfile() {
    if(currentChatId) {
        window.location.href = 'Student_Profile.php?id=' + currentChatId;
    }
}

function openFullChat() {
    if(!currentChatId) return;
    window.location.href = 'SacliChat_Full.php?id=' + currentChatId + '&type=' + currentChatType;
}

// New function to toggle chat options dropdown
function toggleChatOptions(event) {
    event.stopPropagation(); // Prevent click from propagating to document and closing immediately
    const menu = document.getElementById('chatOptionsMenu');
    menu.classList.toggle('active');
}

// Close dropdown if clicked outside
document.addEventListener('click', function(event) {
    const menu = document.getElementById('chatOptionsMenu');
    const toggleBtn = document.getElementById('chatOptionsToggle');
    if (menu && toggleBtn && !menu.contains(event.target) && !toggleBtn.contains(event.target)) {
        menu.classList.remove('active');
    }
});

// Function to toggle message options menu (Edit/Delete)
function toggleMsgMenu(event, id) {
    event.stopPropagation();
    // Close other open message menus
    document.querySelectorAll('.msg-menu.active').forEach(m => {
        if(m.id !== 'msg-menu-'+id) m.classList.remove('active');
    });
    const menu = document.getElementById('msg-menu-' + id);
    if(menu) menu.classList.toggle('active');
}

// Close message menu when clicking anywhere else
document.addEventListener('click', () => {
    document.querySelectorAll('.msg-menu.active').forEach(m => m.classList.remove('active'));
});

// Add CSS for the dropdown menu
const style = document.createElement('style');
style.innerHTML = `
.cb-options-menu { position: absolute; top: 100%; right: 0; background: var(--bg-light); border: 1px solid rgba(0, 255, 170, 0.3); border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.5); display: none; flex-direction: column; z-index: 10003; min-width: 150px; }
.cb-options-menu.active { display: flex; }
.cb-options-item { padding: 8px 12px; color: var(--text-color); cursor: pointer; transition: background 0.2s; font-size: 13px; }
.cb-options-item:hover { background: rgba(0, 255, 170, 0.1); }
`;
document.head.appendChild(style);

function closeChat() {
    document.getElementById('chatBox').style.display = 'none';
    currentChatId = null;
    clearChatFile();
    if(chatInterval) clearInterval(chatInterval);
    if(miniTypingInterval) clearInterval(miniTypingInterval);
}

function sendMessage() {
    let msg = document.getElementById('chatInput').value;
    let fileInput = document.getElementById('chatFileInput');
    let docInput = document.getElementById('chatDocInput');
    
    if((!msg.trim() && fileInput.files.length === 0 && docInput.files.length === 0) || !currentChatId) return;
    
    let formData = new FormData();
    let handler = 'handlers/chat_handler.php';

    if (currentChatType === 'group') {
        handler = 'handlers/group_chat_handler.php';
        formData.append('action', 'send');
        formData.append('group_id', currentChatId);
    } else {
        formData.append('action', 'send');
        formData.append('receiver_id', currentChatId);
    }
    formData.append('message', msg);
    
    if(fileInput.files.length > 0){
        formData.append('media', fileInput.files[0]);
    } else if(docInput.files.length > 0){
        formData.append('media', docInput.files[0]);
    }
    
    let sendBtn = document.querySelector('.cb-send');
    let originalText = sendBtn.innerText;
    sendBtn.innerText = '...';
    sendBtn.disabled = true;
    
    fetch(handler, { method: 'POST', body: formData })
    .then(() => {
        document.getElementById('chatInput').value = '';
        clearChatFile();
        fetchMessages(true);
        sendBtn.innerText = originalText;
        sendBtn.disabled = false;
    });
}

function previewChatFile(type) {
    let input = (type === 'doc') ? document.getElementById('chatDocInput') : document.getElementById('chatFileInput');
    let file = input.files[0];
    
    if(file){
        // Clear the other input to avoid confusion
        if(type === 'doc') document.getElementById('chatFileInput').value = '';
        else document.getElementById('chatDocInput').value = '';

        document.getElementById('chatFilePreviewArea').style.display = 'block';
        let img = document.getElementById('chatPreviewImg');
        let vid = document.getElementById('chatPreviewVideo');
        let filePrev = document.getElementById('chatPreviewFile');
        
        img.style.display = 'none';
        vid.style.display = 'none';
        filePrev.style.display = 'none';

        if(file.type.startsWith('image/')){
            img.src = URL.createObjectURL(file);
            img.style.display = 'block';
        } else if(file.type.startsWith('video/')){
            vid.src = URL.createObjectURL(file);
            vid.style.display = 'block';
        } else {
            // Document preview
            filePrev.innerHTML = "📄 " + file.name;
            filePrev.style.display = 'block';
        }
    }
}

function clearChatFile() {
    document.getElementById('chatFileInput').value = '';
    document.getElementById('chatDocInput').value = '';
    document.getElementById('chatFilePreviewArea').style.display = 'none';
}

        let typingSignalTimeout = null;
        function signalTyping() {
            if(!currentChatId) return;
            let formData = new FormData();
            formData.append('action', 'update_typing');
            formData.append('target_id', currentChatId);
            formData.append('chat_type', currentChatType);
            fetch('handlers/chat_handler.php', { method: 'POST', body: formData });

            clearTimeout(typingSignalTimeout);
            typingSignalTimeout = setTimeout(stopTyping, 4000);
        }

        function stopTyping() {
            let formData = new FormData();
            formData.append('action', 'stop_typing');
            fetch('handlers/chat_handler.php', { method: 'POST', body: formData });
        }

        function checkTypingStatus() {
            if(!currentChatId) return;
            let formData = new FormData();
            formData.append('action', 'check_typing');
            formData.append('receiver_id', currentChatId);
            formData.append('chat_type', currentChatType);
            fetch('handlers/chat_handler.php', { method: 'POST', body: formData })
            .then(res => res.text()).then(status => {
                const indicator = document.getElementById('miniTypingIndicator');
                const picImg = document.getElementById('miniTypingPic');
                const chatBody = document.getElementById('chatBody');

                if(status === 'false') {
                    indicator.style.display = 'none';
                } else {
                    const isAtBottom = (chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight) < 100;
                    indicator.style.display = 'block';
                    // In mini chat context, the header image is the avatar of the other participant
                    picImg.src = document.getElementById('chatHeaderImg').src;
                    if(isAtBottom) chatBody.scrollTop = chatBody.scrollHeight;
                }
            });
        }

function fetchMessages(forceScroll = false) {
    if(!currentChatId) return;
    let formData = new FormData();
    let handler = 'handlers/chat_handler.php';

    if (currentChatType === 'group') {
        handler = 'handlers/group_chat_handler.php';
        formData.append('action', 'fetch');
        formData.append('group_id', currentChatId);
    } else {
        formData.append('action', 'fetch');
        formData.append('receiver_id', currentChatId);
    }
    
    fetch(handler, { method: 'POST', body: formData })
    .then(res => res.text())
    .then(html => {
        let body = document.getElementById('chatBody');
        
        // Check if user is near bottom (within 100px) before updating content
        let isAtBottom = (body.scrollHeight - body.scrollTop - body.clientHeight) < 100;
        
        body.innerHTML = html;
        
        // Scroll to bottom only if forced OR if user was already at bottom
        if(forceScroll || isAtBottom){
            body.scrollTop = body.scrollHeight; 
        }
    });
}

function deleteMsg(id) {
    showCustomConfirm("Delete this message?", function() {
        let formData = new FormData();
        formData.append('action', 'delete');
        formData.append('msg_id', id);
        
        let handler = (currentChatType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
        fetch(handler, { method: 'POST', body: formData }).then(fetchMessages);
    });
}

function editMsg(id, oldText) {
    showCustomPrompt("Edit message:", oldText, function(newText) {
        if(newText !== null && newText.trim() !== "") {
            let formData = new FormData();
            formData.append('action', 'edit');
            formData.append('msg_id', id);
            formData.append('message', newText);
            
            let handler = (currentChatType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
            fetch(handler, { method: 'POST', body: formData }).then(fetchMessages);
        }
    });
}

function checkNewMessages() {
    let formData = new FormData();
    formData.append('action', 'check_new');
    fetch('handlers/chat_handler.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(unreadData => {
        let container = document.getElementById('studentListContainer');
        
        // Group by sender to calculate counts for badges
        let senderCounts = {};
        unreadData.forEach(m => {
            senderCounts[m.sender_id] = (senderCounts[m.sender_id] || 0) + 1;
        });

        unreadData.forEach(data => {
            const senderId = data.sender_id;
            const msgText = data.message;
            const msgId = data.id;

            // Don't highlight if currently chatting with them
            if(currentChatId === senderId) return;

            let row = document.getElementById('student-row-' + senderId);
            if(row) {
                row.classList.add('has-new-msg');
                container.prepend(row); // Pop to top
            }

            // Show Preview & Badge on Bubble if minimized
            let bubble = document.getElementById('bubble-' + senderId);
            if (bubble) {
                // Handle Red Badge
                let badge = bubble.querySelector('.bubble-badge');
                if(!badge) {
                    badge = document.createElement('div');
                    badge.className = 'bubble-badge';
                    bubble.appendChild(badge);
                }
                badge.innerText = senderCounts[senderId];

                // Handle Message Preview
                if (!shownMessageIds.has(msgId)) {
                    shownMessageIds.add(msgId);
                    const preview = document.createElement('div');
                    preview.className = 'bubble-preview';
                    preview.innerText = msgText;
                    bubble.appendChild(preview);
                    setTimeout(() => preview.remove(), 3000);
                }
            }
        });
        
        // Update Message Badge
        let badge = document.getElementById('msgCount');
        if(unreadData.length > 0){
            badge.innerText = unreadData.length;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    });
}


function checkAppNotifs() {
    let formData = new FormData();
    formData.append('action', 'check_unread');
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(count => {
        let badge = document.getElementById('notifCount');
        if(parseInt(count) > 0){
            badge.innerText = count;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    });
}

let currentDirFilter = 'active';
let searchTimeout;

function loadDirectory(filter = 'active', search = '', isSilent = false) {
    const container = document.getElementById('studentListContainer');
    if (!isSilent) {
        container.innerHTML = '<div style="text-align:center; color:#888; padding: 20px;">Loading...</div>'; // Loading state
    }

    fetch(`directory_handler.php?filter=${filter}&search=${encodeURIComponent(search)}`)
        .then(response => response.text())
        .then(html => {
            if (container.innerHTML !== html) {
                container.innerHTML = html;
                
                // Force reflow and re-apply animations for newly loaded gc elements
                const newGcElements = container.querySelectorAll('.gc');
                newGcElements.forEach((gc, index) => {
                    // Remove existing animation properties
                    gc.style.animation = 'none';
                    // Force reflow
                    void gc.offsetWidth;
                    // Re-apply animation with staggered delay
                    gc.style.animation = `slideInStagger 0.5s ease-out backwards ${0.5 + index * 0.05}s`;
                });
            }
            updateActiveUsers(); 
        })
        .catch(error => {
            container.innerHTML = '<div style="text-align:center; color:red; padding: 20px;">Error loading directory.</div>';
            console.error('Error loading directory:', error);
        });
}

function toggleSidebarMenu(id) {
    const menu1 = document.getElementById('sb-content-1');
    const menu2 = document.getElementById('sb-content-2');
    
    // Remove animation classes
    menu1.classList.remove('anim-slide-left-out', 'anim-slide-right-in', 'anim-slide-right-out', 'anim-slide-left-in');
    menu2.classList.remove('anim-slide-left-out', 'anim-slide-right-in', 'anim-slide-right-out', 'anim-slide-left-in');

    if (id === 1) {
        // Switching to Menu 1 (Going Left / Back)
        if (menu2.style.display !== 'none') {
            menu2.classList.add('anim-slide-right-out');
            setTimeout(() => {
                menu2.style.display = 'none';
                menu2.classList.remove('anim-slide-right-out');
                
                menu1.style.display = 'block';
                menu1.classList.add('anim-slide-left-in');
            }, 280); 
        } else {
            menu1.style.display = 'block';
            menu2.style.display = 'none';
        }
    } else {
        // Switching to Menu 2 (Going Right / Forward)
        if (menu1.style.display !== 'none') {
            menu1.classList.add('anim-slide-left-out');
            setTimeout(() => {
                menu1.style.display = 'none';
                menu1.classList.remove('anim-slide-left-out');
                
                menu2.style.display = 'block';
                menu2.classList.add('anim-slide-right-in');
            }, 280);
        } else {
            menu2.style.display = 'block';
            menu1.style.display = 'none';
        }
    }
}

function setDirFilter(type) {
    currentDirFilter = type;
    const search = document.getElementById('studentSearch').value;
    loadDirectory(type, search);
}

function filterStudents() {
    clearTimeout(searchTimeout);
    const searchInput = document.getElementById('studentSearch');
    searchTimeout = setTimeout(() => {
        loadDirectory(currentDirFilter, searchInput.value);
    }, 300); // Wait 300ms after user stops typing
}

// Header Search Functionality (Facebook-like)
const searchInput = document.getElementById('headerSearch');
const searchResults = document.getElementById('searchResults');

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
                    div.className = 'search-item';
                    div.innerHTML = `<img src="${pic}" class="search-avatar"><span class="search-name">${user.student_name}</span>`;
                    div.onclick = () => window.location.href = `Student_Profile.php?id=${user.student_id}`;
                    searchResults.appendChild(div);
                });
            } else {
                searchResults.style.display = 'none';
            }
        });
    } else {
        searchResults.style.display = 'none';
    }
});

// Close search when clicking outside
document.addEventListener('click', function(e) {
    const searchContainer = document.querySelector('.hd-search');
    // Kapag nag-click sa labas ng search container (icon, input, or results)
    if (!searchContainer.contains(e.target)) {
        searchResults.style.display = 'none';
        // Ibalik sa maliit na size (remove active class)
        if (searchContainer.classList.contains('active')) {
            searchContainer.classList.remove('active');
        }
    }
});

function handleEnter(e) {
    if(e.key === 'Enter') sendMessage();
}

function handleConcernEnter(e) {
    if(e.key === 'Enter') sendConcern();
}

// Responsive Toggles
function toggleLeftSidebar() {
    document.querySelector('.sidebar').classList.toggle('active');
    // Close right sidebar if open
    document.querySelector('.right-sidebar').classList.remove('active');
}

function toggleRightSidebar() {
    document.querySelector('.right-sidebar').classList.toggle('active');
    // Close left sidebar if open
    document.querySelector('.sidebar').classList.remove('active');
}

function toggleMobileSearch() {
    if(window.innerWidth <= 850) {
        document.querySelector('.hd-search').classList.toggle('active');
    }
}

// --- NEW INTERACTION FUNCTIONS ---

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
                    // Smooth slide-up animation
                    commentElement.style.height = commentElement.offsetHeight + 'px';
                    void commentElement.offsetHeight; // Force reflow

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
            // Append to feed list
            let feedList = document.getElementById('comment-list-' + postId);
            if(feedList) feedList.insertAdjacentHTML('beforeend', html);
            
            // Also append to viewers if open
            if(typeof currentViewerPostId !== 'undefined' && currentViewerPostId == postId) {
                const list = document.getElementById('viewerCommentsList');
                if(list) {
                    list.insertAdjacentHTML('beforeend', html);
                    list.scrollTop = list.scrollHeight;
                }
            }
            if(typeof currentLightboxPostId !== 'undefined' && currentLightboxPostId == postId) {
                const list = document.getElementById('lightboxCommentsList');
                if(list) {
                    list.insertAdjacentHTML('beforeend', html);
                    list.scrollTop = list.scrollHeight;
                }
            }

            input.value = '';
        });
    }
}

let currentModalPostId = null;
function openAllCommentsModal(postId) {
    currentModalPostId = postId;
    document.getElementById('allCommentsModal').style.display = 'flex';
    document.getElementById('modalCommentsList').innerHTML = '<div style="text-align:center; padding:20px; color:#aaa;">Loading...</div>';
    
    let formData = new FormData();
    formData.append('action', 'fetch_comments');
    formData.append('post_id', postId);
    
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        // Combine post content and comments for the modal view
        let content = data.post_html + '<hr style="border: 0.5px solid rgba(255,255,255,0.1); margin: 15px 0;">' + data.comments_html;
        document.getElementById('modalCommentsList').innerHTML = content;
        const list = document.getElementById('modalCommentsList');
        list.scrollTop = list.scrollHeight;
    });
}

function closeAllCommentsModal() {
    document.getElementById('allCommentsModal').style.display = 'none';
    currentModalPostId = null;
}

function handleModalComment(e) {
    if(e.key === 'Enter' && currentModalPostId) {
        let input = document.getElementById('modalCommentInput');
        let comment = input.value.trim();
        if(!comment) return;

        let formData = new FormData();
        formData.append('action', 'comment');
        formData.append('post_id', currentModalPostId);
        formData.append('comment', comment);

        fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(html => {
            document.getElementById('modalCommentsList').insertAdjacentHTML('beforeend', html);
            input.value = '';
            const list = document.getElementById('modalCommentsList');
            list.scrollTop = list.scrollHeight;
            
            // Synchronize with feed list if it exists
            let feedList = document.getElementById('comment-list-' + currentModalPostId);
            if(feedList) feedList.insertAdjacentHTML('beforeend', html);
        });
    }
}

function handleNotificationClick(element, notifId, postId, type) {
    // 1. Mark as read in DB
    let formData = new FormData();
    formData.append('action', 'mark_notif_read');
    formData.append('notif_id', notifId);
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(() => {
        // 2. Refresh badge count after a short delay to allow DB to update
        setTimeout(checkAppNotifs, 500);
    });

    // 3. Remove highlight from the clicked item
    element.style.background = 'transparent';
    element.style.borderLeft = 'none';

    // 4. Execute original action
    if (type === 'pass_change_request') {
        handlePasswordRequest(postId); // postId here is actually the request_id  '(>_<)'
    } else {
        scrollToAndHighlight(postId);
    }
}

function loadNotifications() {
    // Hide the badge immediately for better UX 
    if(document.getElementById('notifCount')) document.getElementById('notifCount').style.display = 'none';

    let formData = new FormData();
    formData.append('action', 'fetch_notifs');
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        let container = document.getElementById('notifListContainer');
        container.innerHTML = '';
        if(data.length === 0) container.innerHTML = '<div style="padding:15px;text-align:center;color:#aaa;">No notifications</div>';
        data.forEach(n => {
            let icon = '🔔'; 
            let text = '';
       
            if (n.type === 'reaction') {
                icon = '<span class="notif-icon-heart">❤️</span>';
                text = 'loved your post.';
            } else if (n.type === 'comment') {
                icon = '<span class="notif-icon-comment">💬</span>';
                text = 'commented on your post.';
            } else if (n.type === 'tag') {
                icon = '🏷️';
                text = 'tagged you in a post.';
            } else if (n.type === 'mention') {
                icon = '🗣️';
                text = 'mentioned you in a chat.';
            } else if (n.type === 'pass_change_request') {
                icon = '🔑';
                text = 'requested to change your password.';
            } else if (n.type === 'assignment' || n.type === 'room_post') {
                icon = '📝';
                text = 'posted a new assignment or task.';
            } else if (n.type === 'join' || n.type === 'room_join') {
                icon = '👤➕';
                text = 'joined the room.';
            } else if (n.type === 'leave' || n.type === 'room_leave') {
                icon = '👤➖';
                text = 'left the room.';
            } else if (n.type === 'event') {
                icon = '📅';
                text = 'announced a school event.';
            }

            let onclick = `handleNotificationClick(this, ${n.id}, ${n.post_id}, '${n.type}')`;
            let actorName = n.student_name || n.actor_id;

            let pic = n.profile_pic ? "uploads/"+n.profile_pic : "assets/images/3icons8-student-64.png";
            let style = (n.is_read == 0) ? "background:rgba(0, 255, 170, 0.15); border-left: 3px solid #00ffaa;" : "";
            container.innerHTML += `<div class="dp-item" style="${style}" onclick="${onclick}"><img src="${pic}" style="width:30px;height:30px;border-radius:50%;object-fit:cover;"> <div><strong>${actorName}</strong> <small>${icon} ${text}</small></div></div>`;
        });

        // After fetching, tell the server to mark all as read so the badge stays hidden
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
            // Highlight if unread (Green background + Left Border)
            let style = (m.is_unread == 1) ? "background:rgba(0, 255, 170, 0.15); border-left: 3px solid #00ffaa;" : "";
            container.innerHTML += `<div class="dp-item" style="${style}" onclick="openChat('${m.other_id}', '${m.student_name}', '${pic}')"><img src="${pic}" style="width:30px;height:30px;border-radius:50%;object-fit:cover;"> <div><strong>${m.student_name}</strong><small>${m.message.substring(0, 20)}...</small></div></div>`;
        });
    });
}

// --- GROUP CHAT FUNCTIONS ---
function filterCandidates(listId, inputId) {
    let input = document.getElementById(inputId || 'gcSearch').value.toLowerCase();
    let items = document.querySelectorAll('#' + listId + ' .candidate-item');
    items.forEach(item => {
        let name = item.querySelector('.c-name')?.innerText.toLowerCase() || "";
        item.style.display = name.includes(input) ? "flex" : "none";
    });
}

function submitCreateGroup() {
    let name = document.getElementById('newGroupName').value.trim();
    let iconInput = document.getElementById('newGroupIcon');
    if(!name) { showCustomAlert("Please enter a group name"); return; }
    
    let checkboxes = document.querySelectorAll('#gcList .gc-checkbox:checked');
    let members = [];
    checkboxes.forEach(cb => members.push(cb.value));
    
    let formData = new FormData();
    formData.append('action', 'create');
    formData.append('group_name', name);
    if(iconInput.files.length > 0){
        formData.append('group_icon', iconInput.files[0]);
    }
    members.forEach(m => formData.append('members[]', m));
    
    fetch('handlers/group_chat_handler.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(resp => {
        if(resp.trim() === 'success'){
            showCustomAlert("Group created!");
            setTimeout(function(){ location.reload(); }, 1500);
        } else {
            showCustomAlert("Error creating group");
        }
    });
}

function loadMyGroups() {
    let formData = new FormData();
    formData.append('action', 'get_my_groups');
    fetch('handlers/group_chat_handler.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        let container = document.getElementById('groupListContainer');
        container.innerHTML = '';
        if(data.length === 0) container.innerHTML = '<div style="padding:15px;text-align:center;color:#aaa;">No groups yet</div>';
        data.forEach(g => {
            let pic = g.group_icon ? "uploads/"+g.group_icon : "7icons8-organization-64.png";
            
            // Highlight logic & Badge
            let badgeHtml = '';
            let style = '';
            if(g.unread_count > 0){
                badgeHtml = `<span style="background:#e41e3f; color:white; font-size:10px; padding:2px 6px; border-radius:10px; margin-left:5px;">${g.unread_count}</span>`;
                style = 'background:rgba(0, 255, 170, 0.15); border-left: 3px solid #00ffaa;';
            }

            container.innerHTML += `<div class="dp-item" style="${style}" onclick="openGroupChat(${g.id}, '${g.name.replace(/'/g, "\\'").replace(/"/g, "&quot;")}', '${pic}')"><img src="${pic}" style="width:30px;height:30px;border-radius:50%;object-fit:cover;"> <div><strong>${g.name}</strong>${badgeHtml}</div></div>`;
        });
    });
}

function checkGroupNotifs() {
    let formData = new FormData();
    formData.append('action', 'get_my_groups');
    fetch('handlers/group_chat_handler.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        let totalUnread = 0;
        data.forEach(g => {
            totalUnread += parseInt(g.unread_count || 0);
        });
        
        let badge = document.getElementById('groupBadge');
        if(totalUnread > 0){
            badge.innerText = totalUnread;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    });
}

function openGroupChat(id, name, pic) {
    currentChatType = 'group';
    currentChatId = id;
    document.getElementById('chatTitle').innerText = name;
    document.getElementById('chatHeaderImg').src = pic || '7icons8-organization-64.png'; 
    document.getElementById('chatBody').innerHTML = '<div style="padding:20px;text-align:center;color:#aaa;">Loading...</div>';
    document.getElementById('chatBox').style.display = 'flex';

    // Remove bubble if it exists
    if (document.getElementById('bubble-' + id)) document.getElementById('bubble-' + id).remove();
    
    // Show Add Member button and Open Full Chat button for GC (inside dropdown)
    document.getElementById('addMemberBtn').style.display = 'block'; // Inside dropdown
    document.getElementById('openFullChatBtn').style.display = 'block'; // Inside dropdown
    
    fetchMessages(true);
    if(chatInterval) clearInterval(chatInterval);
    chatInterval = setInterval(fetchMessages, 2000);
    if(miniTypingInterval) clearInterval(miniTypingInterval);
    miniTypingInterval = setInterval(checkTypingStatus, 1500);
}

function fetchChatTheme() {
    if(!currentChatId) return;
    let formData = new FormData();
    let handler = (currentChatType === 'group') ? 'handlers/group_chat_handler.php' : 'handlers/chat_handler.php';
    formData.append('action', 'get_theme');
    if(currentChatType === 'group') formData.append('group_id', currentChatId);
    else formData.append('receiver_id', currentChatId);

    fetch(handler, { method: 'POST', body: formData })
    .then(res => res.text())
    .then(theme => {
        applyMiniChatTheme(theme.trim());
    });
}

function applyMiniChatTheme(theme) {
    const body = document.getElementById('chatBody');
    if(!body) return;
    body.classList.remove('theme-flashlight', 'theme-space', 'theme-rain');
    if(theme !== 'default' && theme !== '') {
        body.classList.add('theme-' + theme);
    }
}


function toggleDropdown(id) {
    let el = document.getElementById(id);
    let isVisible = el.style.display === 'flex';
    document.querySelectorAll('.dropdown-popover').forEach(d => d.style.display = 'none');
    if(!isVisible) el.style.display = 'flex';
}
window.addEventListener('click', function(e) {
    if(!e.target.closest('.icon-wrapper') && !e.target.closest('.dropdown-popover')) {
        document.querySelectorAll('.dropdown-popover').forEach(d => d.style.display = 'none');
    }
});

function openAddMemberModal() {
    if(!currentChatId || currentChatType !== 'group') return;
    
    document.getElementById('addMemberModal').style.display = 'flex';
    
    // Load candidates
    let formData = new FormData();
    formData.append('action', 'get_candidates');
    formData.append('group_id', currentChatId);
    
    fetch('handlers/group_chat_handler.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(html => {
        document.getElementById('addMemberList').innerHTML = html;
    });
}

// --- TAGGED USERS MODAL FUNCTIONS ---
function openTaggedUsersModal(postId) {
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
}

function closeTaggedUsersModal() {
    document.getElementById('taggedUsersModal').style.display = 'none';
}

function closeAddMemberModal() {
    document.getElementById('addMemberModal').style.display = 'none';
}

function submitAddMembers() {
    let checkboxes = document.querySelectorAll('#addMemberList input[type="checkbox"]:checked');
    let members = [];
    checkboxes.forEach(cb => members.push(cb.value));
    
    if(members.length === 0) {
        showCustomAlert("Please select at least one member.");
        return;
    }
    
    let formData = new FormData();
    formData.append('action', 'add_member');
    formData.append('group_id', currentChatId);
    members.forEach(m => formData.append('members[]', m));
    
    fetch('handlers/group_chat_handler.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(resp => {
        if(resp.trim() === 'success'){
            showCustomAlert("Members added!");
            closeAddMemberModal();
        } else {
            showCustomAlert("Error adding members.");
        }
    });
}

// --- LIGHTBOX FUNCTIONS ---
let currentMediaList = [];
let currentMediaIndex = 0;
let currentLightboxPostId = null;

function openLightbox(postId, index) {
    currentLightboxPostId = postId;
    // Get the media list from the global variable created by PHP
    currentMediaList = window['post_media_' + postId];
    currentMediaIndex = index;

    // Populate details (name, pfp, time, caption, likes, comments)
    let postEl = document.getElementById('post-' + postId);
    let detailsSection = document.getElementById('lightboxDetailsSection');
    
    if(postEl) {
        detailsSection.style.display = 'flex';
        document.getElementById('lightboxProfilePic').src = postEl.querySelector('.post-profile-img').src;
        document.getElementById('lightboxUsername').innerText = postEl.querySelector('h4').innerText;
        document.getElementById('lightboxTime').innerText = postEl.querySelector('.time').innerText;
        
        // Extract caption without "See More/Less"
        let container = postEl.querySelector('.post-content-container');
        let fullTextEl = container.querySelector('.content-text-full');
        let caption = "";
        if (fullTextEl) {
            caption = fullTextEl.innerText.replace('See Less', '').trim();
        } else {
            caption = container.innerText.replace('See More...', '').trim();
        }
        document.getElementById('lightboxCaption').innerText = caption;

        let likeBtn = postEl.querySelector('.action-btn');
        let isLiked = likeBtn.classList.contains('liked');
        let likeCount = likeBtn.querySelector('.like-count').innerText;
        document.getElementById('lightboxLikeCount').innerText = likeCount;
        
        let viewerLikeBtn = document.getElementById('lightboxLikeBtn');
        if(isLiked) {
            viewerLikeBtn.classList.add('liked');
            viewerLikeBtn.innerHTML = '❤️ Liked';
        } else {
            viewerLikeBtn.classList.remove('liked');
            viewerLikeBtn.innerHTML = '🤍 Like';
        }
        
        // Show immediate comments (last 2) from feed while loading all
        const lList = document.getElementById('lightboxCommentsList');
        lList.innerHTML = document.getElementById('comment-list-' + postId).innerHTML;

        // Fetch all comments asynchronously to show the full list in the lightbox
        let commentFormData = new FormData();
        commentFormData.append('action', 'fetch_comments');
        commentFormData.append('post_id', postId);
        fetch('handlers/post_interaction.php', { method: 'POST', body: commentFormData })
        .then(res => res.json())
        .then(data => {
            lList.innerHTML = data.comments_html;
            lList.scrollTop = lList.scrollHeight;
        }).catch(err => console.log("Comments fetch failed"));
        
        // Fetch views count
        let fd = new FormData();
        fd.append('action', 'increment_view');
        fd.append('post_id', postId);
        fetch('handlers/post_interaction.php', { method: 'POST', body: fd })
        .then(res => res.text())
        .then(views => {
            document.getElementById('lightboxViewCount').innerHTML = '👁️‍🗨️ ' + formatViewCount(views) + ' Views';
        });
    } else {
        detailsSection.style.display = 'none';
    }

    showLightboxItem();
    document.getElementById('lightboxModal').style.display = 'flex';
}

function closeLightbox() {
    document.getElementById('lightboxModal').style.display = 'none';
    currentLightboxPostId = null;
    document.getElementById('lightboxContent').innerHTML = ''; // Stop videos
}

function toggleLightboxLike() {
    if(!currentLightboxPostId) return;
    let btn = document.getElementById('lightboxLikeBtn');
    let feedLikeBtn = document.querySelector(`#post-${currentLightboxPostId} .action-btn`);
    if(feedLikeBtn) feedLikeBtn.click();
    
    let isLiked = btn.classList.contains('liked');
    btn.classList.toggle('liked');
    btn.innerHTML = isLiked ? '🤍 Like' : '❤️ Liked';
    
    setTimeout(() => {
        let count = document.querySelector(`#post-${currentLightboxPostId} .like-count`).innerText;
        document.getElementById('lightboxLikeCount').innerText = count;
    }, 300);
}

function handleLightboxComment(e) {
    if(e.key === 'Enter' && currentLightboxPostId) {
        let input = document.getElementById('lightboxCommentInput');
        let comment = input.value.trim();
        if(!comment) return;

        let feedInput = document.getElementById('comment-input-' + currentLightboxPostId);
        if(feedInput) {
            feedInput.value = comment;
            let event = new KeyboardEvent('keypress', {'key': 'Enter'});
            handleComment(event, currentLightboxPostId);
            input.value = '';
            setTimeout(() => {
                document.getElementById('lightboxCommentsList').innerHTML = document.getElementById('comment-list-' + currentLightboxPostId).innerHTML;
                let list = document.getElementById('lightboxCommentsList');
                list.scrollTop = list.scrollHeight;
            }, 500);
        }
    }
}

function openMessageLightbox(messageId, idx, chatType) {
    let formData = new FormData();
    formData.append('action', 'fetch_message_media');
    formData.append('message_id', messageId);
    formData.append('chat_type', chatType);

    fetch('handlers/chat_handler.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        currentMediaList = data; // Reusing global currentMediaList from post lightbox
        currentMediaIndex = idx;
        currentLightboxPostId = null;
        document.getElementById('lightboxDetailsSection').style.display = 'none';
        showLightboxItem(); 
        document.getElementById('lightboxModal').style.display = 'flex';
    });
}

function showLightboxItem() {
    let item = currentMediaList[currentMediaIndex];
    let container = document.getElementById('lightboxContent');
    container.innerHTML = '';

    if(item.file_type === 'video') {
        container.innerHTML = `<video src="${item.file_path}" controls autoplay style="max-width:100%; max-height:80vh; border-radius:10px; box-shadow:0 0 20px rgba(0,255,170,0.5);"></video>`;
    } else {
        container.innerHTML = `<img src="${item.file_path}" style="max-width:100%; max-height:80vh; border-radius:10px; box-shadow:0 0 20px rgba(0,255,170,0.5);">`;
    }
    
    let dateInfo = item.date ? " | SENT: " + item.date : "";
    document.getElementById('lightboxCounter').innerText = (currentMediaIndex + 1) + " / " + currentMediaList.length + dateInfo;
}

function nextSlide() {
    currentMediaIndex = (currentMediaIndex + 1) % currentMediaList.length;
    showLightboxItem();
}

function prevSlide() {
    currentMediaIndex = (currentMediaIndex - 1 + currentMediaList.length) % currentMediaList.length;
    showLightboxItem();
}

// --- VIDEO VIEWER FUNCTIONS ---
let currentViewerPostId = null;  
let viewerMentionDropdown = document.getElementById('viewerMentionDropdown');
let viewerInput = document.getElementById('viewerCommentInput');

function formatViewCount(num) {
    if(num >= 1000000) return (num/1000000).toFixed(1) + 'M';
    if(num >= 1000) return (num/1000).toFixed(1) + 'k';
    return num;
}

function openVideoViewer(postId, videoSrc) {
    currentViewerPostId = postId;
    
    // 1. Pause feed video if playing
    let feedVideo = document.querySelector(`#post-${postId} video[src="${videoSrc}"]`);
    if(feedVideo) feedVideo.pause();

    // 2. Get Post Data from DOM
    let postEl = document.getElementById('post-' + postId);
    let profilePic = postEl.querySelector('.post-profile-img').src;
    let username = postEl.querySelector('h4').innerText; // Includes tags, which is fine
    let time = postEl.querySelector('.time').innerText;
    
    // Get Clean Caption
    let container = postEl.querySelector('.post-content-container');
    let fullTextEl = container.querySelector('.content-text-full');
    let caption = fullTextEl ? fullTextEl.innerText.replace('See Less', '').trim() : container.innerText.replace('See More...', '').trim();

    let likeBtn = postEl.querySelector('.action-btn');
    let isLiked = likeBtn.classList.contains('liked');
    let likeCount = likeBtn.querySelector('.like-count').innerText;
    let commentsHtml = document.getElementById('comment-list-' + postId).innerHTML;

    // 3. Populate Viewer
    document.getElementById('viewerVideo').src = videoSrc;
    document.getElementById('viewerProfilePic').src = profilePic;
    document.getElementById('viewerUsername').innerText = username;
    document.getElementById('viewerTime').innerText = time;
    document.getElementById('viewerCaption').innerText = caption;
    document.getElementById('viewerLikeCount').innerText = likeCount;

    // Show immediate comments (last 2) from feed while loading all
    const vList = document.getElementById('viewerCommentsList');
    vList.innerHTML = commentsHtml;

    // Fetch all comments asynchronously to show the full list in the video viewer
    let commentFormData = new FormData();
    commentFormData.append('action', 'fetch_comments');
    commentFormData.append('post_id', postId);
    fetch('handlers/post_interaction.php', { method: 'POST', body: commentFormData })
    .then(res => res.json())
    .then(data => {
        vList.innerHTML = data.comments_html;
        vList.scrollTop = vList.scrollHeight;
    }).catch(err => console.log("Comments fetch failed"));

    // 3.5 Increment and Fetch Views
    let formData = new FormData();
    formData.append('action', 'increment_view');
    formData.append('post_id', postId);
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(views => {
        document.getElementById('viewerViewCount').innerHTML = '👁️‍🗨️ ' + formatViewCount(views) + ' Views';
    });

    // 4. Set Like Button State
    let viewerLikeBtn = document.getElementById('viewerLikeBtn');
    if(isLiked) {
        viewerLikeBtn.classList.add('liked');
        viewerLikeBtn.innerHTML = '❤️ Liked';
    } else {
        viewerLikeBtn.classList.remove('liked');
        viewerLikeBtn.innerHTML = '🤍 Like';
    }

    // 5. Show Modal
    document.getElementById('videoViewerModal').style.display = 'block';
}

function closeVideoViewer() {
    document.getElementById('videoViewerModal').style.display = 'none';
    document.getElementById('viewerVideo').pause();
    document.getElementById('viewerVideo').src = "";
    currentViewerPostId = null;
}

function toggleViewerLike() {
    if(!currentViewerPostId) return;
    
    // Floating Hearts Animation
    let btn = document.getElementById('viewerLikeBtn');
    for(let i=0; i<5; i++) {
        let heart = document.createElement('div');
        heart.className = 'floating-heart';
        heart.innerText = '❤️';
        heart.style.left = (btn.getBoundingClientRect().left + Math.random() * 50) + 'px';
        heart.style.top = (btn.getBoundingClientRect().top - Math.random() * 20) + 'px';
        document.body.appendChild(heart);
        setTimeout(() => heart.remove(), 1500);
    }

    // Trigger the actual like button on the feed to handle logic/DB
    let feedLikeBtn = document.querySelector(`#post-${currentViewerPostId} .action-btn`);
    if(feedLikeBtn) feedLikeBtn.click();
    
    // Update Viewer UI immediately (toggle state)
    let isLiked = btn.classList.contains('liked');
    if(isLiked) {
        btn.classList.remove('liked');
        btn.innerHTML = '🤍 Like';
    } else {
        btn.classList.add('liked');
        btn.innerHTML = '❤️ Liked';
    }
    // Note: Count update is tricky without fetching, but feed update handles DB.
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

        // Use existing handleComment logic by simulating input on feed
        let feedInput = document.getElementById('comment-input-' + currentViewerPostId);
        if(feedInput) {
            feedInput.value = comment;
            // Trigger Enter key event on feed input
            let event = new KeyboardEvent('keypress', {'key': 'Enter'});
            handleComment(event, currentViewerPostId);
            
            // Clear viewer input
            input.value = '';
            
            // Wait a bit for AJAX to finish then update viewer comments
            setTimeout(() => {
                let newComments = document.getElementById('comment-list-' + currentViewerPostId).innerHTML;
                document.getElementById('viewerCommentsList').innerHTML = newComments;
                // Scroll to bottom
                let list = document.getElementById('viewerCommentsList');
                list.scrollTop = list.scrollHeight;
            }, 500);
        }
    }
}

function pinComment(commentId, postId) {
    showCustomConfirm("Pin this comment to the top?", function() {
        let formData = new FormData();
        formData.append('action', 'pin_comment');
        formData.append('comment_id', commentId);
        formData.append('post_id', postId);
        fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(resp => {
            if(resp.trim() === 'success') {
                // Reload viewer comments if open, or reload page
                if(currentViewerPostId == postId) {
                    // Close and reopen to refresh (simplest way to re-sort)
                    let src = document.getElementById('viewerVideo').src;
                    closeVideoViewer();
                    setTimeout(() => location.reload(), 500); 
                } else {
                    location.reload();
                }
            }
        });
    });
}

function unpinComment(commentId, postId) {
    let formData = new FormData();
    formData.append('action', 'unpin_comment');
    formData.append('comment_id', commentId);
    formData.append('post_id', postId);
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(resp => {
        if(resp.trim() === 'success') location.reload();
    });
}

function updateActiveUsers() {
    fetch('handlers/active_users_handler.php')
    .then(response => response.json())
    .then(activeIds => {
        const container = document.getElementById('studentListContainer');
        if (!container) return;

        const allUserItems = Array.from(container.querySelectorAll('.student-item'));
        const activeFilterIsOn = document.getElementById('dir-active')?.checked;

        let changed = false;
        allUserItems.forEach(item => {
            const userId = item.id.replace('student-row-', '');
            const statusDiv = item.querySelector('.status');
            const isOnline = activeIds.includes(userId);
            
            if (statusDiv) {
                const wasOnline = statusDiv.classList.contains('online');
                if (wasOnline !== isOnline) {
                    statusDiv.classList.toggle('online', isOnline);
                    changed = true;
                }
            }
        });

        // If on the 'active' tab AND someone's status changed, re-sort the items
        if (activeFilterIsOn && changed) {
            const onlineUsers = [];
            const offlineUsers = [];

            allUserItems.forEach(item => {
                const statusDiv = item.querySelector('.status');
                if (statusDiv?.classList.contains('online')) {
                    onlineUsers.push(item);
                } else {
                    offlineUsers.push(item);
                }
            });

            // Re-append users in order (online first)
            onlineUsers.forEach(user => container.appendChild(user));
            offlineUsers.forEach(user => container.appendChild(user));
        }
    })
    .catch(error => console.error('Error fetching active users:', error));
}

// Real-time Clock Function
function updateClock() {
    const now = new Date();
    // Format to HH:MM:SS AM/PM
    const timeString = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const clockElement = document.getElementById('realtime-clock');
    if (clockElement) {
        clockElement.textContent = timeString;
    }
}
setInterval(updateClock, 1000); // Update every second

document.addEventListener('DOMContentLoaded', function() {
    updateClock(); // Initial call for clock

    // Initial call for active users, then poll every 5 seconds for real-time status
    updateActiveUsers();
    setInterval(updateActiveUsers, 5000);

    loadDirectory(); // Initial AJAX load for the directory

    // Typing listener for mini chat
    const miniInput = document.getElementById('chatInput');
    if(miniInput) {
        miniInput.addEventListener('input', signalTyping);
    }
    
    // Background silent refresh for the directory content every 60 seconds to catch new members
    setInterval(() => {
        const search = document.getElementById('studentSearch').value;
        if (search === '') { 
            loadDirectory(currentDirFilter, '', true);
        }
    }, 60000);

    // Add heartbeat to keep user session active
    setInterval(function() {
        if (document.hasFocus()) {
            fetch('handlers/heartbeat.php')
            .then(res => res.text())
            .then(data => { if(data.trim() === 'logout') window.location.href = 'Logout.php'; })
            .catch(err => console.error('Heartbeat failed:', err));
        }
    }, 30000); // every 30 seconds

    <?php if (isset($force_logout) && $force_logout): ?>
    showCustomAlert('Your account credentials have been updated by an administrator. You will be logged out for security.');
    
    const okBtn = document.getElementById('customAlertOkBtn');
    okBtn.innerText = "Logout Now";
    okBtn.onclick = function() {
        window.location.href = 'Logout.php';
    };

    // Auto-logout after 4 seconds
    setTimeout(function() {
        window.location.href = 'Logout.php';
    }, 4000);
    <?php else: ?>
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight_post');

    if (highlightId) {
        const postElement = document.getElementById('post-' + highlightId);
        if (postElement) {
            // Scroll to the post. The 'flash-post' class is already added by PHP.
            postElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
    <?php endif; ?>

    // --- Scroll-Triggered Layout Expansion ---
    let focusModeEnabled = localStorage.getItem('sacliFocusMode') !== 'false'; // Default to enabled
    updateFocusModeUI();

    const dashboardStats = document.querySelector('.story-stats-container');
    if (dashboardStats) {
        const scrollObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (focusModeEnabled && !entry.isIntersecting && entry.boundingClientRect.top < 0) {
                    document.body.classList.add('scrolled-past');
                } else {
                    document.body.classList.remove('scrolled-past');
                }
            });
        }, { threshold: 0 });
        scrollObserver.observe(dashboardStats);
    }

    window.toggleFocusMode = function() {
        focusModeEnabled = !focusModeEnabled;
        localStorage.setItem('sacliFocusMode', focusModeEnabled);
        
        if (!focusModeEnabled) {
            document.body.classList.remove('scrolled-past');
        } else if (dashboardStats && dashboardStats.getBoundingClientRect().top < 0) {
            // Re-apply if already scrolled down
            document.body.classList.add('scrolled-past');
        }
        
        updateFocusModeUI();
        showFlash(focusModeEnabled ? "Zoom effect enabled" : "Zoom effect disabled");
    };

    function updateFocusModeUI() {
        [document.getElementById('focusModeBtn'), document.getElementById('focusModeBtnMain'), document.getElementById('focusModeBtnZoomed')].forEach(btn => {
            if (!btn) return;
            btn.querySelector('.icon-maximize').style.display = focusModeEnabled ? 'none' : 'block';
            btn.querySelector('.icon-minimize').style.display = focusModeEnabled ? 'block' : 'none';
            btn.style.background = focusModeEnabled ? '#00ffaa' : 'rgba(0, 255, 170, 0.1)';
            btn.style.color = focusModeEnabled ? '#0a1f16' : '#00ffaa';
        });
    }
});

function scrollToAndHighlight(postId) {
    document.querySelectorAll('.dropdown-popover').forEach(d => d.style.display = 'none');
    const postElement = document.getElementById('post-' + postId);
    if (postElement) {
        postElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        postElement.classList.add('flash-post');
        setTimeout(() => { postElement.classList.remove('flash-post'); }, 2500); // Remove after animation
    } else {
        // Fallback: If post is not on the page, reload to it.
        window.location.href = 'SacliConnect.php?highlight_post=' + postId;
    }
}
function deleteMyPost(postId, btnElement) {
    showCustomConfirm('Are you sure you want to permanently delete this post? This cannot be undone.', function() {
        let formData = new FormData();
        formData.append('action', 'delete_post');
        formData.append('post_id', postId);

        // Show loading state on button
        btnElement.disabled = true;
        btnElement.innerText = 'Deleting...';

        fetch('handlers/post_interaction.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(response => {
            if (response.trim() === 'success') {
                // Find the parent post container and remove it with a fade-out effect
                let postElement = document.getElementById('my-post-' + postId);
                if (postElement) {
                    postElement.style.transition = 'opacity 0.5s, transform 0.5s';
                    postElement.style.opacity = '0';
                    postElement.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        postElement.remove();
                    }, 500);
                }
            } else {
                showCustomAlert('Error: Could not delete post. ' + response);
                // Re-enable button
                btnElement.disabled = false;
                btnElement.innerText = 'Delete';
            }
        });
    });
}

function leaveGroup(groupId) {
    showCustomConfirm("Are you sure you want to leave this group?", function() {
        let formData = new FormData();
        formData.append('action', 'leave_group');
        formData.append('group_id', groupId);

        fetch('handlers/group_chat_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(resp => {
            if(resp.trim() === 'success'){
                let card = document.getElementById('group-card-' + groupId);
                if(card) card.remove();
            } else {
                showCustomAlert("Error leaving group.");
            }
        });
    });
}

// Autoplay videos on scroll
document.addEventListener('DOMContentLoaded', function () {
    const videos = document.querySelectorAll('video.feed-video');

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const video = entry.target;
                if (entry.isIntersecting) {
                    // Use a try-catch block for play() as it can be interrupted
                    video.play().catch(error => { /* console.log("Video play prevented."); */ });
                } else {
                    video.pause();
                }
            });
        }, { threshold: 0.5 }); // Play when 50% of the video is visible

        videos.forEach(video => {
            observer.observe(video);
            
            // Toggle play icon visibility
            const playIcon = video.nextElementSibling;
            if (playIcon && playIcon.classList.contains('video-play-icon')) {
                // Set initial state based on current playback
                if (!video.paused) playIcon.classList.add('playing');
                
                video.addEventListener('play', () => { 
                    playIcon.classList.add('playing'); 
                });
                video.addEventListener('pause', () => { 
                    playIcon.classList.remove('playing'); 
                });
                
                // Click center icon to play/pause (no expand)
                playIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    if (video.paused) video.play();
                    else video.pause();
                });
            }
        });
    }
});

// Video Controls Functions
function toggleMute(button) {
    const container = button.closest('.video-volume-control');
    const video = button.closest('.media-item').querySelector('video.feed-video');
    const iconMuted = button.querySelector('.icon-muted-state');
    const iconUnmuted = button.querySelector('.icon-unmuted-state');
    const slider = container.querySelector('.volume-slider');

    video.muted = !video.muted;

    if (video.muted) {
        iconMuted.style.display = 'block';
        iconUnmuted.style.display = 'none';
    } else {
        iconUnmuted.style.display = 'block';
        iconMuted.style.display = 'none';
        if(video.volume === 0) {
             video.volume = 1;
             slider.value = 1;
        }
    }
}

function setVolume(slider) {
    const video = slider.closest('.media-item').querySelector('video.feed-video');
    const container = slider.closest('.video-volume-control');
    const btn = container.querySelector('.video-mute-button');
    const iconMuted = btn.querySelector('.icon-muted-state');
    const iconUnmuted = btn.querySelector('.icon-unmuted-state');

    video.volume = slider.value;

    if (video.volume == 0) {
        video.muted = true;
        iconMuted.style.display = 'block';
        iconUnmuted.style.display = 'none';
    } else {
        video.muted = false;
        iconUnmuted.style.display = 'block';
        iconMuted.style.display = 'none';
    }
}

function updateProgress(video) {
    const interface = video.parentElement.querySelector('.video-interface');
    const slider = interface.querySelector('.video-seek-slider');
    const timeDisplay = interface.querySelector('.video-time');
    
    if (!isNaN(video.duration)) {
        const percent = (video.currentTime / video.duration) * 100;
        slider.value = percent;
        slider.style.background = `linear-gradient(to right, #00ffaa ${percent}%, rgba(255,255,255,0.3) ${percent}%)`;
        timeDisplay.innerText = formatTime(video.currentTime) + ' / ' + formatTime(video.duration);
    }
}

function seekVideo(slider) {
    const video = slider.closest('.media-item').querySelector('video.feed-video');
    const time = (slider.value / 100) * video.duration;
    video.currentTime = time;
}

function formatTime(seconds) {
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s < 10 ? '0' : ''}${s}`;
}

function handlePasswordRequest(requestId) {
    document.querySelectorAll('.dropdown-popover').forEach(d => d.style.display = 'none');

    const yesFunc = function() {
        let formData = new FormData();
        formData.append('action', 'approve_pass_change');
        formData.append('request_id', requestId);
        fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(resp => {
            if(resp.trim() === 'success'){
                showCustomAlert("Password change approved. Your new password is now active. You will be logged out.", function() {
                    window.location.href = "Logout.php";
                });
            } else {
                showCustomAlert("An error occurred: " + resp);
            }
        });
    };

    const noFunc = function() {
        let formData = new FormData();
        formData.append('action', 'deny_pass_change');
        formData.append('request_id', requestId);
        fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
        .then(res => res.text())
        .then(resp => {
            if(resp.trim() === 'success'){
                showCustomAlert("Password change request has been denied.");
            }
        });
    };

    showCustomConfirm("Do you want to allow the admin to change your password?", yesFunc, noFunc, "Yes", "Decline");
}

// --- CHANGE PASSWORD AUTHENTICATION FUNCTIONS ---
function openChangePassModal() {
    document.getElementById('cpStep1').style.display = 'block';
    document.getElementById('cpStep2').style.display = 'none';
    document.getElementById('cpStep3').style.display = 'none';
    document.getElementById('cpCurrentPass').value = '';
    document.getElementById('cpTimer').innerText = '01:00';
    document.getElementById('changePassModal').style.display = 'flex';
    if(cpCountdownInterval) clearInterval(cpCountdownInterval);
}

function closeChangePassModal() {
    document.getElementById('changePassModal').style.display = 'none';
    if(cpCountdownInterval) clearInterval(cpCountdownInterval);
}

function cpVerifyPassword(event) {
    let pass = document.getElementById('cpCurrentPass').value;
    if(!pass) return showCustomAlert("Please enter your current password.");
    
    const btn = event.target;
    const originalText = btn.innerText;
    btn.innerText = "Verifying...";
    btn.disabled = true;

    let formData = new FormData();
    formData.append('action', 'cp_verify_password');
    formData.append('password', pass);
    
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => {
        return res.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                // If there are PHP warnings, attempt to extract the JSON part
                const match = text.match(/\{"status".*\}/);
                if(match) return JSON.parse(match[0]);
                console.error("Server raw response:", text);
                throw new Error("Server error. Please check your internet or database connection.");
            }
        });
    })
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('cpStep1').style.display = 'none';
            document.getElementById('cpStep2').style.display = 'block';
            document.getElementById('cpSentEmail').innerText = data.email;
            startCPCountdown();
        } else {
            showCustomAlert(data.message);
        }
    })
    .catch(err => {
        showCustomAlert("Error: " + err.message);
    })
    .finally(() => {
        btn.innerText = originalText;
        btn.disabled = false;
    });
}

let cpCountdownInterval = null;
function startCPCountdown() {
    let timeLeft = 60;
    const timerDisplay = document.getElementById('cpTimer');
    const validateBtn = document.querySelector('#cpStep2 .save-btn');
    
    if(cpCountdownInterval) clearInterval(cpCountdownInterval);
    
    validateBtn.disabled = false;
    validateBtn.style.opacity = "1";

    cpCountdownInterval = setInterval(() => {
        timeLeft--;
        let minutes = Math.floor(timeLeft / 60);
        let seconds = timeLeft % 60;
        timerDisplay.innerText = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        
        if (timeLeft <= 0) {
            clearInterval(cpCountdownInterval);
            timerDisplay.innerText = "EXPIRED";
            validateBtn.disabled = true;
            validateBtn.style.opacity = "0.5";
        }
    }, 1000);
}

/* --- OTP INPUT HANDLERS FOR CHANGE PASSWORD --- */
function moveNextCP(curr, index) {
    if (curr.value.length >= 1 && index < 6) {
        document.querySelectorAll('.otp-box-modern')[index].focus();
    }
    let val = "";
    document.querySelectorAll('.otp-box-modern').forEach(box => { val += box.value; });
    document.getElementById('cpOTP').value = val;
}

function moveBackCP(curr, e, index) {
    if (e.key === "Backspace" && curr.value === "" && index >= 0) {
        document.querySelectorAll('.otp-box-modern')[index].focus();
    }
}

function cpVerifyOTP(event) {
    let otp = document.getElementById('cpOTP').value;
    if(otp.length !== 6) return showCustomAlert("Please enter the 6-digit code.");
    
    const btn = event.target;
    btn.disabled = true;

    let formData = new FormData();
    formData.append('action', 'cp_verify_otp');
    formData.append('otp', otp);
    
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => res.text().then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            const match = text.match(/\{"status".*\}/);
            if(match) return JSON.parse(match[0]);
            throw new Error("Invalid response from server.");
        }
    }))
    .then(data => {
        if(data.status === 'success') {
            if(cpCountdownInterval) clearInterval(cpCountdownInterval);
            document.getElementById('cpStep2').style.display = 'none';
            document.getElementById('cpStep3').style.display = 'block';
        } else {
            showCustomAlert(data.message);
        }
    })
    .catch(err => showCustomAlert(err.message))
    .finally(() => btn.disabled = false);
}

function cpFinalize(event) {
    let newPass = document.getElementById('cpNewPass').value;
    let confPass = document.getElementById('cpConfirmPass').value;

    if(!newPass) return showCustomAlert("Enter new password.");
    if(newPass !== confPass) return showCustomAlert("Passwords do not match.");
    if(newPass.length < 6) return showCustomAlert("Password must be at least 6 characters.");

    const btn = event.target;
    btn.disabled = true;
    btn.innerText = "Updating...";

    let formData = new FormData();
    formData.append('action', 'cp_finalize');
    formData.append('new_password', newPass);
    
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            showCustomAlert("Password updated successfully! Logging out...");
            setTimeout(() => { window.location.href = 'Logout.php'; }, 2000);
        } else {
            showCustomAlert(data.message);
        }
    });
}

// --- MENTION / TAGGING LOGIC ---
const postTextarea = document.getElementById('postContent');
const mentionDropdown = document.getElementById('mentionSuggestions');
const taggedContainer = document.getElementById('taggedUsersContainer');

postTextarea.addEventListener('input', function(e) {
    const cursorPosition = this.selectionStart;
    const text = this.value;
    const textBeforeCursor = text.substring(0, cursorPosition);
    const lastAt = textBeforeCursor.lastIndexOf('@');
    
    if (lastAt !== -1) {
        const charBeforeAt = lastAt > 0 ? textBeforeCursor[lastAt - 1] : ' ';
        // Check if @ is at start or preceded by space/newline
        if (charBeforeAt === ' ' || charBeforeAt === '\n' || lastAt === 0) {   
            const query = textBeforeCursor.substring(lastAt + 1);
            // Simple check: query shouldn't contain newlines
            if (!query.includes('\n')) {
                fetch('search_handler.php?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(users => {
                    mentionDropdown.innerHTML = '';
                    if (users.length > 0) {
                        mentionDropdown.style.display = 'block';
                        users.forEach(user => {
                            let pic = user.profile_pic ? "uploads/" + user.profile_pic : (user.student_id.startsWith('T-') ? "4icons8-teacher-50.png" : "assets/images/3icons8-student-64.png");
                            const div = document.createElement('div');
                            div.className = 'mention-item';
                            div.innerHTML = `<img src="${pic}"><span>${user.student_name}</span>`;
                            div.onclick = () => selectMention(user, lastAt, query.length);
                            mentionDropdown.appendChild(div);
                        });
                    } else {
                        mentionDropdown.style.display = 'none';
                    }
                });
                return;
            }
        }
    }
    mentionDropdown.style.display = 'none';
});

function selectMention(user, atIndex, queryLen) {
    const text = postTextarea.value;
    const before = text.substring(0, atIndex);
    const after = text.substring(atIndex + 1 + queryLen);
    
    postTextarea.value = before + user.student_name + ' ' + after;
    mentionDropdown.style.display = 'none';
    postTextarea.focus();
    
    // Add hidden input for backend processing
    if (!document.querySelector(`input[name="tagged_users[]"][value="${user.student_id}"]`)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'tagged_users[]';
        input.value = user.student_id;
        taggedContainer.appendChild(input);
    }
}

function broadcastEvent(eventId) {
    showCustomConfirm("Do you want to send an email notification to ALL students and teachers for this event?", function() {
        // Magpapakita ng flash message habang nag-sesend dahil matagal ito
        showFlash("Sending broadcast... Please do not close the window.");
        
        let formData = new FormData();
        formData.append('action', 'broadcast_event');
        formData.append('event_id', eventId);

        fetch('handlers/post_interaction.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            showFlash(data.message, data.status);
        }).catch(err => showFlash("An error occurred during broadcast.", "error"));
    });
}

function confirmResetEvaluation(formId) {
    showCustomConfirm("Are you sure you want to reset your evaluation for this teacher?", function() {
        document.getElementById(formId).submit();
    });
}

// --- FLASH MESSAGE FUNCTION ---
function showFlash(msg, type = 'success') {
    let flash = document.createElement('div');
    flash.className = 'flash-message ' + type;
    flash.innerText = msg;
    document.body.appendChild(flash);
    
    // Trigger reflow
    void flash.offsetWidth;
    
    flash.classList.add('show');
    
    setTimeout(() => {
        flash.classList.remove('show');
        setTimeout(() => flash.remove(), 500);
    }, 3000);
}

// Check URL for flash triggers
const urlParams = new URLSearchParams(window.location.search);
if(urlParams.has('success')) { showFlash("Evaluation submitted successfully!"); window.history.replaceState({}, document.title, window.location.pathname + "?page=evaluates"); }
if(urlParams.has('reset')) { showFlash("Evaluation reset. You can now evaluate again.", "error"); window.history.replaceState({}, document.title, window.location.pathname + "?page=evaluates"); }
if(urlParams.has('poll_created')) { showFlash("Poll launched successfully!", "success"); window.history.replaceState({}, document.title, window.location.pathname); }

function openPollModal() { document.getElementById('pollModal').style.display = 'flex'; }
function closePollModal() { document.getElementById('pollModal').style.display = 'none'; }
function addPollOption() {
    const container = document.getElementById('pollOptionsContainer');
    const count = container.querySelectorAll('input').length + 1;
    if(count > 10) return;
    const div = document.createElement('div');
    div.style.display = 'flex'; div.style.gap = '10px'; div.style.marginBottom = '10px';
    div.innerHTML = `<input type="text" name="options[]" placeholder="Option ${count}" required style="flex:1; padding:10px; border-radius:5px; border:1px solid rgba(255,255,255,0.2); background:rgba(0,0,0,0.2); color:white;">`;
    container.appendChild(div);
}
function votePoll(postId, optionId) {
    let formData = new FormData();
    formData.append('action', 'vote_poll');
    formData.append('post_id', postId);
    formData.append('option_id', optionId);
    fetch('handlers/post_interaction.php', { method: 'POST', body: formData }).then(res => res.text()).then(resp => {
        if(resp.trim() === 'success') location.reload(); else showCustomAlert(resp);
    });
}
function navigateToProfile(element) {
    // Play sound effect (optional, matching current theme)
    const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3');
    audio.volume = 0.3;
    audio.play().catch(e => {});

    // Add animation to the profile button
    element.classList.add('transitioning');

    // Create a holographic flash overlay
    const flash = document.createElement('div');
    flash.style.cssText = "position:fixed; top:0; left:0; width:100%; height:100%; background:radial-gradient(circle, rgba(0,255,170,0.2) 0%, transparent 70%); z-index:100000; pointer-events:none; opacity:0; transition: opacity 0.4s;";
    document.body.appendChild(flash);
    
    // Add blur/scale effect to the whole page
    document.body.classList.add('body-exit-gate');
    setTimeout(() => flash.style.opacity = '1', 50);

    // Redirect after animation completes
    setTimeout(() => {
        window.location.href = 'Student_Profile.php';
    }, 550);
}

// Function para i-expand ang text ng post (See More)
function expandPost(element) {
    const container = element.closest('.post-content-container');
    const truncatedText = container.querySelector('.content-text-truncated');
    const fullText = container.querySelector('.content-text-full');
    truncatedText.style.display = 'none';
    fullText.style.display = 'block';
}

function collapsePost(element) {
    const container = element.closest('.post-content-container');
    const truncatedText = container.querySelector('.content-text-truncated');
    const fullText = container.querySelector('.content-text-full');
    truncatedText.style.display = 'block';
    fullText.style.display = 'none';
}

let loginMap = null;
let loginMarker = null;

function openLoginMapModal(location, device, time) {
    if (!location || location === 'Unknown' || location === 'Localhost') {
        showCustomAlert("Geolocation trace failed: No coordinates found for local/unknown uplink.");
        return;
    }

    document.getElementById('mapModalDetail').innerText = location;
    document.getElementById('mapDevice').innerText = device;
    document.getElementById('mapTime').innerText = time;
    document.getElementById('loginMapModal').style.display = 'flex';

    setTimeout(() => {
        if (!loginMap) {
            loginMap = L.map('loginMap').setView([13.9373, 121.6146], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(loginMap);
        }
        
        // Geocode the location string using Nominatim
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(location)}`)
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                const lat = data[0].lat;
                const lon = data[0].lon;
                loginMap.setView([lat, lon], 15);
                
                if (loginMarker) {
                    loginMarker.setLatLng([lat, lon]);
                } else {
                    loginMarker = L.marker([lat, lon]).addTo(loginMap);
                }
                loginMarker.bindPopup(`<b>Login Location</b><br>${location}`).openPopup();
            } else {
                // Fallback to default if geocoding fails
                loginMap.setView([13.9373, 121.6146], 13);
            }
            loginMap.invalidateSize();
        });
    }, 300);
}

function closeLoginMapModal() {
    document.getElementById('loginMapModal').style.display = 'none';
    if (loginMarker) {
        loginMap.removeLayer(loginMarker);
        loginMarker = null;
    }
}

/* --- DRAGGABLE CHAT HEAD LOGIC --- */
dragElement(document.getElementById("floatingChatHead"));

function dragElement(elmnt) {
  var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
  elmnt.onmousedown = dragMouseDown;
  elmnt.ontouchstart = dragMouseDown; // Support for touch devices

  function dragMouseDown(e) {
    e = e || window.event;
    // Get initial cursor position
    pos3 = e.type === 'touchstart' ? e.touches[0].clientX : e.clientX;
    pos4 = e.type === 'touchstart' ? e.touches[0].clientY : e.clientY;
    
    document.onmouseup = closeDragElement;
    document.ontouchend = closeDragElement;
    document.onmousemove = elementDrag;
    document.ontouchmove = elementDrag;
  }

  function elementDrag(e) {
    e = e || window.event;
    var clientX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
    var clientY = e.type === 'touchmove' ? e.touches[0].clientY : e.clientY;

    pos1 = pos3 - clientX;
    pos2 = pos4 - clientY;
    pos3 = clientX;
    pos4 = clientY;
    
    elmnt.style.top = (elmnt.offsetTop - pos2) + "px";
    elmnt.style.left = (elmnt.offsetLeft - pos1) + "px";
    elmnt.style.bottom = "auto";
    elmnt.style.right = "auto";
  }

  function closeDragElement() {
    document.onmouseup = null;
    document.onmousemove = null;
    document.ontouchend = null;
    document.ontouchmove = null;
  }
}

</script>

</body>
</html>                