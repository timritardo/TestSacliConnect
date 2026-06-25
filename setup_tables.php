<?php
/**
 * setup_tables.php
 * Run this ONCE on a fresh database to create all required tables.
 * Access via: https://your-domain/setup_tables.php?key=SETUP_KEY
 * Delete this file after running.
 */

// Simple protection - set SETUP_KEY in Railway environment variables
$key = getenv('SETUP_KEY') ?: 'sacli_setup_2026';
if (!isset($_GET['key']) || $_GET['key'] !== $key) {
    die("Access denied. Add ?key=YOUR_SETUP_KEY to the URL.");
}

require_once __DIR__ . '/config/database.php';

$tables = [];
$errors = [];

function run($conn, $sql, &$tables, &$errors) {
    if ($conn->query($sql)) {
        $tables[] = "✅ OK";
    } else {
        $errors[] = "❌ " . $conn->error . " — " . substr($sql, 0, 80);
    }
}

// ── Core Tables ───────────────────────────────────────────────────────────────

run($conn, "CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) UNIQUE NOT NULL,
    student_name VARCHAR(100),
    password VARCHAR(255),
    email VARCHAR(100),
    course VARCHAR(100),
    year_level VARCHAR(20),
    profile_pic VARCHAR(255) DEFAULT NULL,
    cover_photo VARCHAR(255) DEFAULT NULL,
    is_alumni TINYINT(1) DEFAULT 0,
    is_online TINYINT(1) DEFAULT 0,
    is_restricted TINYINT(1) DEFAULT 0,
    force_logout TINYINT(1) DEFAULT 0,
    logout_token VARCHAR(255) DEFAULT NULL,
    mfa_enabled TINYINT(1) DEFAULT 0,
    otp_code VARCHAR(10) DEFAULT NULL,
    otp_expiry DATETIME DEFAULT NULL,
    last_activity DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    email VARCHAR(100),
    password VARCHAR(255),
    subject VARCHAR(100),
    profile_pic VARCHAR(255) DEFAULT NULL,
    is_online TINYINT(1) DEFAULT 0,
    is_restricted TINYINT(1) DEFAULT 0,
    force_logout TINYINT(1) DEFAULT 0,
    logout_token VARCHAR(255) DEFAULT NULL,
    otp_code VARCHAR(10) DEFAULT NULL,
    otp_expiry DATETIME DEFAULT NULL,
    last_activity DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS admins2 (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255),
    email VARCHAR(100),
    otp_code VARCHAR(10) DEFAULT NULL,
    otp_expiry DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id VARCHAR(50),
    author_name VARCHAR(100),
    author_type ENUM('student','teacher','admin') DEFAULT 'student',
    content TEXT,
    post_type VARCHAR(50) DEFAULT 'normal',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS post_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT,
    media_path VARCHAR(255),
    media_type VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS post_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT,
    user_id VARCHAR(50),
    reaction VARCHAR(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reaction (post_id, user_id)
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS post_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT,
    author_id VARCHAR(50),
    author_name VARCHAR(100),
    content TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    message TEXT,
    link VARCHAR(255),
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS direct_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id VARCHAR(50),
    receiver_id VARCHAR(50),
    message TEXT,
    media VARCHAR(255) DEFAULT NULL,
    media_type VARCHAR(20) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_pinned TINYINT(1) DEFAULT 0,
    is_unsent TINYINT(1) DEFAULT 0,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS group_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    created_by VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS group_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT,
    sender_id VARCHAR(50),
    sender_name VARCHAR(100),
    message TEXT,
    media VARCHAR(255) DEFAULT NULL,
    media_type VARCHAR(20) DEFAULT NULL,
    is_unsent TINYINT(1) DEFAULT 0,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    description TEXT,
    event_date DATE,
    event_type VARCHAR(50),
    image VARCHAR(255) DEFAULT NULL,
    created_by VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50),
    device_info TEXT,
    ip_address VARCHAR(45),
    location VARCHAR(255) DEFAULT 'Unknown',
    login_time DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS user_active_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    session_token VARCHAR(255),
    ip_address VARCHAR(45),
    device_info TEXT,
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS sacli_rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    subject VARCHAR(100),
    teacher_id VARCHAR(50),
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS sacli_room_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT,
    student_id VARCHAR(50),
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS sacli_room_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT,
    author_id VARCHAR(50),
    author_name VARCHAR(100),
    content TEXT,
    file_path VARCHAR(255) DEFAULT NULL,
    post_type VARCHAR(50) DEFAULT 'stream',
    due_date DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS sacli_room_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_post_id INT,
    student_id VARCHAR(50),
    file_path VARCHAR(255),
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS sidebar_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(255) DEFAULT '',
    sort_order INT DEFAULT 0
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS subject_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(255) DEFAULT '#',
    icon VARCHAR(255) DEFAULT '',
    is_online TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT,
    student_id VARCHAR(50),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS admin_concerns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50),
    subject VARCHAR(255),
    message TEXT,
    status ENUM('open','resolved') DEFAULT 'open',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    description TEXT,
    image VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS alumni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50),
    name VARCHAR(100),
    course VARCHAR(100),
    year_graduated INT,
    profile_pic VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS user_storage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    file_name VARCHAR(255),
    file_path VARCHAR(255),
    file_size INT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
)", $tables, $errors);

run($conn, "CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) UNIQUE,
    attempts INT DEFAULT 0,
    last_attempt DATETIME,
    lockout_until DATETIME DEFAULT NULL
)", $tables, $errors);

// ── Insert default admin account ──────────────────────────────────────────────
$admin_pass = password_hash('Admin@2026', PASSWORD_DEFAULT);
$conn->query("INSERT IGNORE INTO admins2 (username, password, email) 
              VALUES ('admin', '$admin_pass', 'sacliconnect@gmail.com')");

// ── Insert default site settings ──────────────────────────────────────────────
$defaults = [
    ['site_theme',      'default'],
    ['blackout_mode',   '0'],
    ['signup_enabled',  '1'],
];
foreach ($defaults as $d) {
    $conn->query("INSERT IGNORE INTO site_settings (setting_key, setting_value) 
                  VALUES ('{$d[0]}', '{$d[1]}')");
}

// ── Output results ────────────────────────────────────────────────────────────
echo "<style>body{font-family:monospace;background:#0a1f16;color:#00ffaa;padding:30px;}</style>";
echo "<h2>SacliConnect — Database Setup</h2>";
echo "<p>Tables created: " . count($tables) . "</p>";
if (!empty($errors)) {
    echo "<h3 style='color:#ff4757'>Errors:</h3>";
    foreach ($errors as $e) echo "<p>$e</p>";
} else {
    echo "<p style='color:#00ffaa'>✅ All tables created successfully!</p>";
    echo "<p>Default admin: <b>username: admin</b> / <b>password: Admin@2026</b></p>";
    echo "<p><a href='SacliConnect_LOG_IN.php' style='color:#00ffaa'>→ Go to Login Page</a></p>";
    echo "<p style='color:#ff4757'>⚠️ Delete or protect this file after setup!</p>";
}
