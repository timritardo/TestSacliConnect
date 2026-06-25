<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: SacliConnect_LOG_IN.php");
    exit();
}

$my_id = $_SESSION['student_id'];
$user_type = $_SESSION['user_type'] ?? 'student';

// Fetch Current User Info (for profile pic)
if ($user_type === 'teacher') {
    $real_id = str_replace("T-", "", $my_id);
    $me_res = $conn->query("SELECT *, name as student_name FROM teachers WHERE id='$real_id'");
} else {
    $me_res = $conn->query("SELECT * FROM students WHERE student_id='$my_id'");
}
$me = $me_res->fetch_assoc();
$my_pic = !empty($me['profile_pic']) ? "uploads/".$me['profile_pic'] : "assets/images/3icons8-student-64.png";

$page = $_GET['page'] ?? 'stream';


$room_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($room_id === 0) {
    die("Invalid Room ID.");
}

// Fetch all submissions by the current student for this room to avoid N+1 queries
$my_submissions = [];
if ($user_type === 'student') {
    $sub_stmt = $conn->prepare("SELECT s.post_id, s.submitted_at, s.grade FROM sacli_room_submissions s JOIN sacli_room_posts p ON s.post_id = p.id WHERE p.room_id = ? AND s.student_id = ?");
    $sub_stmt->bind_param("is", $room_id, $my_id);
    $sub_stmt->execute();
    $sub_res = $sub_stmt->get_result();
    while ($sub = $sub_res->fetch_assoc()) {
        $my_submissions[$sub['post_id']] = $sub;
    }
    $sub_stmt->close();
}

// Get total number of students in the room
$total_students_stmt = $conn->prepare("SELECT COUNT(*) as total FROM sacli_room_members WHERE room_id = ? AND role = 'student'");
$total_students_stmt->bind_param("i", $room_id);
$total_students_stmt->execute();
$total_students = $total_students_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$total_students_stmt->close();

// AUTO-FIX: Create table for post attachments
$conn->query("CREATE TABLE IF NOT EXISTS sacli_room_post_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(post_id)
)");


// AUTO-FIX: Create tables for posts and submissions
$conn->query("CREATE TABLE IF NOT EXISTS sacli_room_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    due_date DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX(room_id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS sacli_room_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    grade VARCHAR(10) NULL,
    comments TEXT NULL,
    UNIQUE KEY (post_id, student_id)
)");

// Security Check: Ensure user is a member of this room
$check_member = $conn->prepare("SELECT id FROM sacli_room_members WHERE room_id = ? AND student_id = ?");
$check_member->bind_param("is", $room_id, $my_id);
$check_member->execute();
if ($check_member->get_result()->num_rows === 0) {
    die("Access Denied. You are not a member of this room.");
}
$check_member->close();

// Get user's role in this room
$my_role_stmt = $conn->prepare("SELECT role FROM sacli_room_members WHERE room_id = ? AND student_id = ?");
$my_role_stmt->bind_param("is", $room_id, $my_id);
$my_role_stmt->execute();
$my_role = $my_role_stmt->get_result()->fetch_assoc()['role'];
$my_role_stmt->close();

// Blackout Protocol Check
$blackout_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='blackout_mode'");
$blackout_active = ($blackout_res && $blackout_res->num_rows > 0 && $blackout_res->fetch_assoc()['setting_value'] == '1');

// Fetch Room Details
$room_stmt = $conn->prepare("SELECT * FROM sacli_rooms WHERE id = ?");
$room_stmt->bind_param("i", $room_id);
$room_stmt->execute();
$room = $room_stmt->get_result()->fetch_assoc();

if (!$room) {
    die("Room not found.");
}
$room_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($room['name']); ?> - SacliRoom</title>
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Inherit some base styles from SacliConnect.css if needed */
        :root { --bg-light: #1a3d2f; }

        /* Google Classroom UI */
        body {
            background: radial-gradient(circle at center, #0a1f16 0%, #020806 100%) !important;
            color: #e4e6eb;
            font-family: 'Google Sans', 'Segoe UI', sans-serif;
            height: 100vh; /* Make body fill viewport height */
            overflow: hidden; /* Alisin ang scrollbar sa body */
            display: flex; /* Use flex to manage children layout */
            flex-direction: column; /* Stack children vertically */
        }

        /* --- Passing Light Animation (Background Glow) --- */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                110deg, 
                transparent 0%, 
                rgba(0, 255, 170, 0.1) 15%, 
                rgba(0, 255, 170, 0.5) 50%, 
                rgba(0, 255, 170, 0.1) 85%, 
                transparent 100%
            );
            z-index: -1; 
            pointer-events: none;
            filter: blur(120px);
            animation: passingLight 3s infinite ease-in-out;
        }

        .classroom-container {
            width: 95%; /* Fill available width */
            margin: 20px auto;
            padding: 0 20px 50px;
            flex: 1; /* Allow it to grow and shrink in flex container */
            display: flex; /* Make it a flex container for its internal layout */
            flex-direction: column; /* Stack internal elements vertically */
        }
        .classroom-main {
            width: 100%;
        }
        .classroom-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .classroom-tabs {
            display: flex;
            gap: 20px;
        }
        .classroom-tabs a {
            color: #aaa;
            text-decoration: none;
            padding: 10px 5px;
            font-weight: 500;
            border-bottom: 2px solid transparent;
            transition: 0.2s;
        }
        .classroom-tabs a.active, .classroom-tabs a:hover {
            color: #00ffaa;
            border-bottom-color: #00ffaa;
        }
        .back-link {
            color: #00ffaa;
            text-decoration: none;
            font-weight: bold;
        }
        .classroom-banner {
            background: linear-gradient(135deg, #0d47a1, #1976d2);
            border-radius: 10px;
            margin-top: 20px;
            padding: 30px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .banner-content h1 {
            font-size: 36px;
            margin: 0;
        }
        .banner-illustration {
            position: absolute;
            right: 20px;
            bottom: -20px;
            width: 200px;
            opacity: 0.2;
        }
        .classroom-content-wrapper {
            display: grid;
            grid-template-columns: 240px 1fr 280px;
            gap: 20px;
            margin-top: 20px;
            align-items: start; /* Keep sidebars at the top */
        }
        .stream, .classwork-container, .people-list-container {
            transition: all 0.6s cubic-bezier(0.22, 1, 0.36, 1);
            height: calc(100vh - 280px); /* Dynamic height para mag-fit sa screen minus header/banner */
            overflow-y: auto;
            padding-right: 10px;
            scrollbar-width: thin;
            scrollbar-color: #00ffaa transparent;
        }
        /* Custom Scrollbar for Chrome/Safari */
        .stream::-webkit-scrollbar, .classwork-container::-webkit-scrollbar, .people-list-container::-webkit-scrollbar {
            width: 6px;
        }
        .stream::-webkit-scrollbar-thumb, .classwork-container::-webkit-scrollbar-thumb, .people-list-container::-webkit-scrollbar-thumb {
            background: #00ffaa;
            border-radius: 10px;
        }
        .upcoming-work {
            background: var(--bg-light);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            height: fit-content;
            position: sticky; /* Make sidebar stay while scrolling main feed */
            top: 20px;
            transition: all 0.5s ease;
        }
        .upcoming-work h4 {
            margin: 0 0 15px;
            color: #fff;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 10px;
        }
        .upcoming-work p {
            font-size: 14px;
            color: #aaa;
            margin-bottom: 20px;
        }
        .upcoming-work a {
            color: #0a1f16; /* Dark text for neon background */
            background: #00ffaa; /* Filled background */
            padding: 8px 15px;
            border-radius: 5px;
            display: inline-block; /* Essential for padding to work */
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            font-weight: bold; /* Make it bold */
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(0, 255, 170, 0.3); /* Subtle glow */
        }
        .upcoming-work a:hover {
            background: #fff; /* Lighter on hover */
            color: #00ffaa; /* Neon text on hover */
            box-shadow: 0 0 20px rgba(0, 255, 170, 0.6); /* Stronger glow */
            transform: translateY(-2px); /* Slight lift */
        }

        /* --- SCROLL EXPANSION & GLASSMORPHISM --- */
        body.scrolled-past .classroom-content-wrapper {
            grid-template-columns: 1fr;
        }
        body.scrolled-past .upcoming-work, body.scrolled-past .ranking-sidebar {
            display: none;
        }
        body.scrolled-past .stream-item, 
        body.scrolled-past .classroom-banner,
        body.scrolled-past .classwork-item, 
        body.scrolled-past .announcement-box,
        body.scrolled-past .create-post-container {
            max-width: 100% !important;
            transform: scale(1.01);
            background: rgba(26, 61, 47, 0.4) !important;
            backdrop-filter: blur(15px) saturate(150%);
            -webkit-backdrop-filter: blur(15px) saturate(150%);
            border: 1px solid rgba(0, 255, 170, 0.3) !important;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.5);
        }
        body.scrolled-past .classroom-banner {
            margin-bottom: 20px;
            transform: scale(1);
        }
        .stream-item, .classwork-item, .announcement-box {
            transition: all 0.6s cubic-bezier(0.22, 1, 0.36, 1);
        }

        /* People Page */
        .people-list-container { width: 100%; margin: 20px auto; }
        .people-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #00ffaa;
            font-size: 24px;
            padding-bottom: 10px;
            border-bottom: 2px solid #00ffaa;
            margin-bottom: 20px;
        }
        .people-item {
            display: flex;
            align-items: center;
            gap: 10px; /* Adjusted gap for smaller images */
            padding: 12px 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .people-item img {
            width: 32px; /* Reduced size */
            height: 32px; /* Reduced size */
            border-radius: 50%;
            object-fit: cover;
        }
        .people-item span {
            font-size: 16px;
            font-weight: 500;
            color: #e4e6eb;
        }

        /* Classwork Page */
        .classwork-container { width:100%; margin: 20px auto; }
        .classwork-header { display: flex; justify-content: flex-end; margin-bottom: 20px; }
        .classwork-item {
            background: var(--bg-light);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
            gap: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            cursor: pointer;
            transition: background 0.2s;
        }
        .classwork-item:hover { background: #2a5c46; }
        .classwork-item .item-icon { background: #6a1b9a; /* Purple for classwork */ }
        .classwork-content { flex: 1; }
        .classwork-content h4 { margin: 0 0 5px; font-size: 18px; color: #fff; }
        .classwork-content p { margin: 0; font-size: 13px; color: #aaa; }
        .submission-status { flex: 0 0 150px; text-align: right; }
        .submission-status .status-text { font-size: 13px; font-weight: 500; }
        .status-missing { color: #ff8a80; }
        .status-turned-in { color: #00ffaa; }
        .status-graded { color: #8c9eff; }

        /* Create Post Page */
        .create-post-container {
            max-width: 800px;
            margin: 40px auto;
            background: var(--bg-light);
            padding: 30px 40px;
            border-radius: 15px;
            border: 1px solid rgba(0, 255, 170, 0.2);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            animation: fadeInUp 0.5s ease;
            /* Added for scrollability */
            max-height: calc(100vh - 160px); /* Adjust based on top/bottom margins and padding */
            overflow-y: auto;
            scrollbar-width: thin; /* Firefox */
        }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .create-post-container h3 {
            font-size: 28px;
            color: #00ffaa;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .create-post-container .form-group {
            margin-bottom: 20px;
        }
        .create-post-container .form-label {
            display: block;
            color: #b0fce0;
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .create-post-container input[type="text"],
        .create-post-container input[type="datetime-local"],
        .create-post-container textarea {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #00ffaa;
            background: #0a1f16;
            color: white;
            font-size: 15px;
        }
        .create-post-container textarea {
            resize: vertical;
            min-height: 120px;
        }
        #filePreviewContainer {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .file-preview-item {
            background: #0a1f16; padding: 8px 12px; border-radius: 5px; border: 1px solid #00ffaa; font-size: 13px; color: #b0fce0;
        }
        /* Custom Scrollbar for Chrome/Safari for create-post-container */
        .create-post-container::-webkit-scrollbar {
            width: 6px;
        }
        .create-post-container::-webkit-scrollbar-thumb {
            background: #00ffaa;
            border-radius: 10px;
        }
        .attachments-container {
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .attachment-link {
            background: rgba(0, 255, 170, 0.1); border: 1px solid rgba(0, 255, 170, 0.3); padding: 8px 12px;
            border-radius: 8px; color: #b0fce0; text-decoration: none; font-size: 13px; font-weight: 500;
            display: block; transition: background 0.2s;
        }
        .ranking-sidebar {
            background: var(--bg-light);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px; padding: 20px; height: fit-content;
            position: sticky; /* Make leaderboard stay while scrolling main feed */
            top: 20px;
            transition: all 0.5s ease;
        }
        .rank-card {
            display: flex; align-items: center; gap: 10px; padding: 10px;
            background: rgba(0, 255, 170, 0.1); /* More filled, but still transparent */
            border-radius: 8px; margin-bottom: 8px;
            border: 1px solid rgba(0, 255, 170, 0.2); /* Stronger border */
            transition: 0.3s;
            box-shadow: 0 0 8px rgba(0, 255, 170, 0.1); /* Subtle glow */
        }
        .rank-card:hover { border-color: #00ffaa; background: rgba(0,255,170,0.05); transform: scale(1.02); }
        .rank-top-1 { animation: pulseGlow 2s infinite alternate; border-color: #ffd700; box-shadow: 0 0 15px rgba(255, 215, 0, 0.2); }

        @keyframes pulseGlow {
            0% { box-shadow: 0 0 5px rgba(0, 255, 170, 0.1); border-color: rgba(0, 255, 170, 0.2); }
            50% { box-shadow: 0 0 15px rgba(0, 255, 170, 0.4); border-color: #00ffaa; }
            100% { box-shadow: 0 0 5px rgba(0, 255, 170, 0.1); border-color: rgba(0, 255, 170, 0.2); }
        }
        .sr-submission-hub {
            animation: pulseGlow 4s infinite alternate;
            transition: all 0.3s ease;
        }
        .attachment-link:hover { background: rgba(0, 255, 170, 0.2); color: #fff; }
        .classwork-content p { margin: 0; font-size: 13px; color: #aaa; }
        .stream-item, .announcement-box {
            background: var(--bg-light);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2); position: relative;
            cursor: pointer;
            transition: background 0.2s;
        }
        .stream-item:hover, .announcement-box:hover {
            background: #2a5c46;
        }
        .item-icon {
            background: #0d47a1;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .item-content p {
            margin: 0;
            font-size: 14px;
        }
        .item-content .meta {
            font-size: 12px;
            color: #aaa;
            margin-top: 5px;
        }
        .announcement-box {
            color: #aaa;
        }
        .announcement-box .item-icon {
            background: transparent;
        }
        .announcement-box .item-icon img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
        }
        .sr-btn {
            background: #00ffaa; color: #0a1f16; border: none; padding: 10px 20px; border-radius: 8px;
            font-weight: bold; cursor: pointer; transition: 0.2s;
        }
        .sr-btn:hover { background: #fff; }
        /* Modal styles from SacliConnect.css are used */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; }
        .modal-content { background: #102e22; padding: 20px; border-radius: 10px; border: 1px solid #00ffaa; width: 90%; max-width: 500px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }

        /* Flash Message (from SacliConnect.php) */
        .flash-message {
            position: fixed;
            top: 80px; /* Below header */
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
        }
        .flash-message.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .flash-message.error { background: rgba(255, 85, 85, 0.95); color: white; }

        /* Requested Background Pattern Style */
        .background-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2; /* Mas mababa sa logo para nasa likod talaga */
            pointer-events: none;
            opacity: 0.15; /* Ginawang transparent para makita pa rin ang green flow background */
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
            linear-gradient(150deg, #282828 24%, #2c3539 0, #2c3539 26%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 74%, #2c3539 0, #2c3539 76%, #282828 0) 0 0,
            linear-gradient(30deg, #282828 24%, #2c3539 0, #2c3539 26%, rgba(240, 166, 17, 0) 0, rgba(240, 166, 17, 0) 74%, #2c3539 0, #2c3539 76%, #282828 0) 0 0,
            linear-gradient(90deg, #2c3539 2%, #282828 0, #282828 98%, #2c3539 0%) 0 0 #282828;
            background-size: 40px 60px;
        }

        /* System Background Logo Style */
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

        @keyframes passingLight {
            0% { transform: translateX(-150%) skewX(-20deg); opacity: 0; }
            25% { opacity: 0; }
            50% { opacity: 1; }
            75% { opacity: 0; }
            100% { transform: translateX(150%) skewX(-20deg); opacity: 0; }
        }

        /* Total System Blackout Protocol */
        body.blackout-protocol {
            background: #000 !important;
            overflow: hidden !important;
        }
        body.blackout-protocol::after {
            content: "SYSTEM_OFFLINE: SECURITY_LOCK_ACTIVE";
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: #000;
            color: #00ffaa;
            z-index: 99999999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            letter-spacing: 5px;
            pointer-events: all;
        }
    </style>
</head>
<body class="<?php echo $blackout_active ? 'blackout-protocol' : ''; ?>">
    <div class="background-pattern"></div>
    <img class="background-logo" src="assets/images/St.Anne_logo.png" alt="System Background Logo">
    <div class="classroom-container">
        <header class="classroom-header">
            <a href="SacliConnect.php?page=sacli_room" class="back-link">&larr; Back to Rooms</a>
            <nav class="classroom-tabs">
                <a href="SacliRoom_view.php?id=<?php echo $room_id; ?>&page=stream" class="<?php if($page === 'stream') echo 'active'; ?>">Stream</a>
                <a href="SacliRoom_view.php?id=<?php echo $room_id; ?>&page=classwork" class="<?php if($page === 'classwork') echo 'active'; ?>">Classwork</a>
                <a href="SacliRoom_view.php?id=<?php echo $room_id; ?>&page=people" class="<?php if($page === 'people') echo 'active'; ?>">People</a>
            </nav>
        </header>

        <main class="classroom-main">
            <div class="classroom-banner">
                <div class="banner-content">
                    <h1><?php echo htmlspecialchars($room['name']); ?></h1>
                    <p style="margin-top: 10px; font-size: 16px; background: rgba(0,0,0,0.2); display: inline-block; padding: 5px 15px; border-radius: 5px; font-family: 'Courier New', monospace; border: 1px solid rgba(255,255,255,0.1);">
                        Room Code: <strong style="color: #00ffaa;"><?php echo htmlspecialchars($room['room_code']); ?></strong>
                    </p>
                </div>
                <img src="school_supplies.svg" alt="supplies" class="banner-illustration">
            </div>
            
            <?php if ($page === 'stream'): ?>
            <?php
            // Logic to calculate overall Room Leaderboard using the grade attribute
            $leaderboard_sql = "SELECT st.student_name, st.profile_pic, AVG(CAST(s.grade AS UNSIGNED)) as avg_grade
                                FROM sacli_room_submissions s
                                JOIN sacli_room_posts p ON s.post_id = p.id
                                JOIN students st ON s.student_id = st.student_id
                                WHERE p.room_id = ? AND s.grade IS NOT NULL
                                GROUP BY s.student_id
                                ORDER BY avg_grade DESC
                                LIMIT 10";
            $lb_stmt = $conn->prepare($leaderboard_sql);
            $lb_stmt->bind_param("i", $room_id);
            $lb_stmt->execute();
            $leaderboard_res = $lb_stmt->get_result();
            ?>
            <div class="classroom-content-wrapper">
                <aside class="upcoming-work">
                    <h4>Upcoming</h4>
                    <?php
                    if ($my_role === 'student') {
                        $upcoming_stmt = $conn->prepare("
                            SELECT p.id, p.title, p.due_date
                            FROM sacli_room_posts p
                            LEFT JOIN sacli_room_submissions s ON p.id = s.post_id AND s.student_id = ?
                            WHERE p.room_id = ?
                              AND p.due_date IS NOT NULL
                              AND s.id IS NULL
                            ORDER BY p.due_date ASC
                            LIMIT 5
                        ");
                        $upcoming_stmt->bind_param("si", $my_id, $room_id);
                        $upcoming_stmt->execute();
                        $upcoming_res = $upcoming_stmt->get_result();

                        if ($upcoming_res->num_rows > 0) {
                            while ($task = $upcoming_res->fetch_assoc()) {
                                echo '<p style="margin-bottom: 10px;"><a href="SacliRoom_view.php?id=' . $room_id . '&page=classwork#post-' . $task['id'] . '" style="color:#00ffaa; text-decoration:none; font-weight:bold; color:black;">' . htmlspecialchars($task['title']) . '</a><br><small style="color:#aaa;">Due: ' . date("M d, h:i A", strtotime($task['due_date'])) . '</small></p>';
                            }
                        } else {
                            echo '<p>Woohoo, no work due soon!</p>';
                        }
                        $upcoming_stmt->close();
                    } else {
                        echo '<p>No upcoming assignments for teachers.</p>';
                    }
                    ?>
                    <a href="SacliRoom_view.php?id=<?php echo $room_id; ?>&page=classwork">View all classwork</a>
                </aside>

                <div class="stream">
                    <?php if ($my_role === 'teacher'): ?>
                        <div class="announcement-box" onclick="location.href='SacliRoom_view.php?id=<?php echo $room_id; ?>&page=create_post&from=stream'">
                            <div class="item-icon">
                                <img src="<?php echo $my_pic; ?>" alt="user">
                            </div>
                            <div class="item-content">
                                <p>Announce something to your class</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php
                    // Fetch posts for the stream
                    $posts_stmt = $conn->prepare("SELECT p.*, COALESCE(t.name, s.student_name) as poster_name
                                                  FROM sacli_room_posts p 
                                                  LEFT JOIN teachers t ON p.user_id = CONCAT('T-', t.id)
                                                  LEFT JOIN students s ON p.user_id = s.student_id
                                                  WHERE p.room_id = ? ORDER BY p.created_at DESC");
                    $posts_stmt->bind_param("i", $room_id);
                    $posts_stmt->execute();
                    $posts_res = $posts_stmt->get_result();

                    if ($posts_res->num_rows > 0):
                        while ($post = $posts_res->fetch_assoc()):
                    ?>
                    <div class="stream-item" onclick="location.href='SacliRoom_view.php?id=<?php echo $room_id; ?>&page=classwork#post-<?php echo $post['id']; ?>'">
                        <?php if ($my_role === 'teacher'): ?>
                            <div title="Delete Post" style="position: absolute; top: 15px; right: 15px; cursor: pointer; padding: 5px; border-radius: 50%; background: rgba(0,0,0,0.2); line-height: 1;" onclick="event.stopPropagation(); deleteRoomPost(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars(addslashes($post['title']), ENT_QUOTES); ?>')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ff8a80" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                            </div>
                        <?php endif; ?>
                        <?php
                        // Logic to fetch submissions and rankings for this assignment
                        $post_id = $post['id'];
                        $is_assignment = !empty($post['due_date']);
                        $top_students = [];
                        $all_submitters_data = []; // Changed to store data, not just names

                        if ($is_assignment) {
                            $subs_sql = "SELECT s.grade, st.student_name, st.profile_pic 
                                         FROM sacli_room_submissions s
                                         JOIN students st ON s.student_id = st.student_id
                                         WHERE s.post_id = ? 
                                         ORDER BY 
                                            CASE WHEN s.grade IS NULL THEN 1 ELSE 0 END, 
                                            CAST(s.grade AS UNSIGNED) DESC, 
                                            s.submitted_at ASC";
                            $subs_stmt = $conn->prepare($subs_sql);
                            $subs_stmt->bind_param("i", $post_id);
                            $subs_stmt->execute();
                            $subs_res = $subs_stmt->get_result();
                            
                            $rank = 1;
                            while($s = $subs_res->fetch_assoc()) {
                                if ($rank <= 3 && !empty($s['grade'])) {
                                    $s['rank'] = $rank++;
                                    $top_students[] = $s;
                                }
                                $all_submitters_data[] = $s; // Store full data for marquee
                            }
                            $subs_stmt->close();
                        }
                        ?>
                        <div class="item-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        </div>
                        <div class="item-content">
                            <p style="margin-bottom: 5px;"><strong><?php echo htmlspecialchars($post['poster_name']); ?></strong> posted a new <?php echo ($post['due_date']) ? 'assignment' : 'material'; ?>: <strong><?php echo htmlspecialchars($post['title']); ?></strong></p>
                            
                            <?php if (!empty($post['content'])): ?>
                                <p style="font-size: 14px; color: #ccc; margin-bottom: 10px; white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                            <?php endif; ?>

                            <div class="meta"><?php echo date("M d", strtotime($post['created_at'])); ?>
                                <?php if ($post['due_date']): ?>
                                    <span style="margin-left: 15px; color: #ff8a80;">Due: <?php echo date("M d, Y", strtotime($post['due_date'])); ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if ($is_assignment && !empty($all_submitters_data)): ?>
                                <div class="sr-submission-hub" style="margin-top: 15px; padding: 15px; background: rgba(0,0,0,0.3); border-radius: 12px; border: 1px solid rgba(0,255,170,0.15);">
                                    <?php if (!empty($top_students)): ?>
                                        <div style="display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap;">
                                            <span style="font-size: 10px; color: #00ffaa; font-weight: 800; width: 100%; margin-bottom: 5px; font-family: 'Courier New', monospace; letter-spacing: 1px;">// TOP_PERFORMERS_DETECTED:</span>
                                            <?php foreach($top_students as $top): 
                                                $rank_color = ($top['rank'] == 1) ? '#ffd700' : (($top['rank'] == 2) ? '#c0c0c0' : '#cd7f32');
                                                $rank_label = ($top['rank'] == 1) ? '🥇 1ST' : (($top['rank'] == 2) ? '🥈 2ND' : '🥉 3RD');
                                                $profile_pic_path = !empty($top['profile_pic']) ? "uploads/" . htmlspecialchars($top['profile_pic']) : "assets/images/3icons8-student-64.png";
                                            ?>
                                                <div style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.05); padding: 5px 12px; border-radius: 20px; border: 1px solid <?php echo $rank_color; ?>80;">
                                                    <span style="color: <?php echo $rank_color; ?>; font-weight: 900; font-size: 11px;"><?php echo $rank_label; ?></span>
                                                    <img src="<?php echo $profile_pic_path; ?>" alt="<?php echo htmlspecialchars($top['student_name']); ?>" title="<?php echo htmlspecialchars($top['student_name']); ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid <?php echo $rank_color; ?>;">
                                                    <span style="font-size: 12px; color: #fff; font-weight: bold;"><?php echo htmlspecialchars($top['student_name']); ?></span>
                                                    <span style="font-size: 10px; color: #00ffaa; opacity: 0.8;"><?php echo $top['grade']; ?>%</span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="display: flex; align-items: center; gap: 10px; overflow: hidden; white-space: nowrap; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 10px;">
                                        <span style="font-size: 10px; color: #aaa; font-weight: 800; font-family: 'Courier New', monospace;">STATUS: SUBMITTED_BY ></span>
                                        <marquee scrollamount="4" style="font-size: 12px; font-weight: 500;">
                                            <?php
                                            $marquee_content_parts = [];
                                            foreach ($all_submitters_data as $submitter) {
                                                $profile_pic_path = !empty($submitter['profile_pic']) ? "uploads/" . htmlspecialchars($submitter['profile_pic']) : "assets/images/3icons8-student-64.png";
                                                $marquee_content_parts[] = '<img src="' . $profile_pic_path . '" alt="' . htmlspecialchars($submitter['student_name']) . '" title="' . htmlspecialchars($submitter['student_name']) . '" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; margin: 0 5px; vertical-align: middle; border: 1px solid rgba(0,255,170,0.5);">';
                                            }
                                            echo implode(' &nbsp; ', $marquee_content_parts);
                                            ?>
                                        </marquee>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php
                            // Fetch and display attachments
                            $attachments_stmt = $conn->prepare("SELECT * FROM sacli_room_post_attachments WHERE post_id = ?");
                            $attachments_stmt->bind_param("i", $post['id']);
                            $attachments_stmt->execute();
                            $attachments_res = $attachments_stmt->get_result();

                            if ($attachments_res->num_rows > 0) {
                                echo '<div class="attachments-container">';
                                while ($attachment = $attachments_res->fetch_assoc()) {
                                    echo '<a href="' . htmlspecialchars($attachment['file_path']) . '" class="attachment-link" onclick="event.stopPropagation();" style="display:flex; justify-content:space-between; align-items:center; gap:10px;">';
                                    echo '<span>📄 ' . htmlspecialchars($attachment['original_filename']) . '</span>';
                                    echo '<span style="font-size:10px; background:#00ffaa; color:#0a1f16; padding:2px 8px; border-radius:4px; font-weight:bold;">VIEW</span></a>';
                                }
                                echo '</div>';
                            }
                            $attachments_stmt->close();
                            ?>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="stream-item" style="justify-content: center; color: #aaa;">
                            No posts in this room yet.
                        </div>
                    <?php endif; $posts_stmt->close(); ?>
                </div>

                <aside class="ranking-sidebar">
                    <h4 style="color:#00ffaa; font-family:'Google Sans', sans-serif; font-size:11px; letter-spacing:2px; margin-top:0; border-bottom:1px solid rgba(0,255,170,0.2); padding-bottom:10px; text-transform: uppercase;">🏆 Room Leaderboard</h4>
                    <div style="margin-top: 15px;">
                        <?php 
                        $rank_pos = 1;
                        if($leaderboard_res->num_rows > 0):
                            while($lb = $leaderboard_res->fetch_assoc()):
                                $pic = !empty($lb['profile_pic']) ? "uploads/".$lb['profile_pic'] : "assets/images/3icons8-student-64.png";
                                $card_class = ($rank_pos === 1) ? 'rank-top-1' : '';
                                $medal = ($rank_pos === 1) ? '🥇' : (($rank_pos === 2) ? '🥈' : (($rank_pos === 3) ? '🥉' : $rank_pos.'.'));
                        ?>
                            <div class="rank-card <?php echo $card_class; ?>">
                                <span style="font-weight:900; color:#00ffaa; width:22px; font-size:12px;"><?php echo $medal; ?></span>
                                <img src="<?php echo $pic; ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border:1.5px solid rgba(0,255,170,0.3);">
                                <div style="flex:1; overflow:hidden;">
                                    <div style="font-size:12px; font-weight:bold; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#fff;"><?php echo htmlspecialchars($lb['student_name']); ?></div>
                                    <div style="font-size:10px; color:#aaa;"><?php echo number_format($lb['avg_grade'], 1); ?>% Average</div>
                                </div>
                            </div>
                        <?php $rank_pos++; endwhile; else: ?>
                            <p style="font-size:12px; color:#555; text-align:center; font-style: italic;">// NO_GRADED_NODES_YET</p>
                        <?php endif; $lb_stmt->close(); ?>
                    </div>
                </aside>
            </div>
            <?php elseif ($page === 'classwork'): ?>
                <div class="classwork-container">
                    <?php if ($my_role === 'teacher'): ?>
                    <div class="classwork-header">
                        <button class="sr-btn" onclick="event.stopPropagation(); location.href='SacliRoom_view.php?id=<?php echo $room_id; ?>&page=create_post&from=classwork'">+ Create</button>
                    </div>
                    <?php endif; ?>

                    <?php
                    $posts_stmt = $conn->prepare("SELECT p.*, 
                                                         COALESCE(t.name, s.student_name, 'Unknown') as poster_name,
                                                         COALESCE(t.profile_pic, s.profile_pic, '') as poster_pic,
                                                         (SELECT COUNT(*) FROM sacli_room_submissions WHERE post_id = p.id) as turned_in_count,
                                                         (SELECT COUNT(*) FROM sacli_room_submissions WHERE post_id = p.id AND grade IS NOT NULL) as graded_count
                                                  FROM sacli_room_posts p 
                                                  -- Join with teachers and students to get identity
                                                  LEFT JOIN teachers t ON (p.user_id = CONCAT('T-', t.id) OR p.user_id = CAST(t.id AS CHAR))
                                                  LEFT JOIN students s ON p.user_id = s.student_id
                                                  WHERE p.room_id = ? ORDER BY p.created_at DESC");
                    $posts_stmt->bind_param("i", $room_id);
                    $posts_stmt->execute();
                    $posts_res = $posts_stmt->get_result();

                    if ($posts_res->num_rows > 0):
                        while ($post = $posts_res->fetch_assoc()):
                            // Prepare the onclick action in a clean PHP block to avoid syntax errors
                            $onclick_action = "this.style.cursor='default'";
                            if ($my_role === 'teacher' && !empty($post['due_date'])) {
                                $onclick_action = "location.href='SacliRoom_submissions.php?post_id=" . $post['id'] . "'";
                            }
                    ?>
                    <div class="classwork-item" id="post-<?php echo $post['id']; ?>"
                         style="flex-direction: column; align-items: stretch; gap: 0; padding: 0; overflow: hidden;"
                         onclick="<?php echo $onclick_action; ?>">
                        <!-- Header Section -->
                        <div style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 15px; background: rgba(255,255,255,0.02);">
                            <div class="item-icon classwork-icon" style="background: <?php echo $post['due_date'] ? '#6a1b9a' : '#0d47a1'; ?>; width: 40px; height: 40px; margin:0;">
                                <?php if ($post['due_date']): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
                                <?php endif; ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <h4 style="margin:0; font-size: 17px; color: #fff;"><?php echo htmlspecialchars($post['title']); ?></h4>
                                    <?php if ($my_role === 'teacher'): ?>
                                        <div title="Delete Post" style="cursor: pointer; padding: 5px; border-radius: 50%;" onclick="event.stopPropagation(); deleteRoomPost(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars(addslashes($post['title']), ENT_QUOTES); ?>')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ff8a80" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex; align-items:center; gap:10px; margin-top:4px;">
                                    <img src="<?php echo !empty($post['poster_pic']) ? 'uploads/'.$post['poster_pic'] : 'assets/images/3icons8-student-64.png'; ?>" style="width:18px; height:18px; border-radius:50%; object-fit:cover; border:1px solid #00ffaa;">
                                    <span style="font-size: 11px; color: #00ffaa; font-weight: bold;"><?php echo htmlspecialchars($post['poster_name']); ?></span>
                                    <span style="color: rgba(255,255,255,0.2); font-size: 11px;">|</span>
                                    <span style="font-size: 11px; color: #aaa;">Posted <?php echo date("M d", strtotime($post['created_at'])); ?></span>
                                    <?php if ($post['due_date']): ?>
                                        <span style="color: rgba(255,255,255,0.2); font-size: 11px;">|</span>
                                        <span style="font-size: 11px; color: #ff8a80; font-weight: bold;">Due: <?php echo date("M d, Y h:i A", strtotime($post['due_date'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Content Section -->
                        <div class="classwork-content" style="padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <?php if (!empty($post['content'])): ?>
                                <p style="margin: 0; color: #e4e6eb; white-space: pre-wrap; line-height: 1.6; font-size: 14px;"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                            <?php else: ?>
                                <p style="margin: 0; color: #666; font-style: italic; font-size: 13px;">No instructions provided.</p>
                            <?php endif; ?>

                            <?php
                            // Fetch and display attachments for classwork
                            $attachments_stmt = $conn->prepare("SELECT * FROM sacli_room_post_attachments WHERE post_id = ?");
                            $attachments_stmt->bind_param("i", $post['id']);
                            $attachments_stmt->execute();
                            $attachments_res = $attachments_stmt->get_result();

                            if ($attachments_res->num_rows > 0) {
                                echo '<div class="attachments-container" style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;">';
                                while ($attachment = $attachments_res->fetch_assoc()) {
                                    echo '<a href="' . htmlspecialchars($attachment['file_path']) . '" class="attachment-link" style="display:flex; align-items:center; gap:10px; background: rgba(0,255,170,0.05); padding: 10px; border-radius: 8px; border: 1px solid rgba(0,255,170,0.1); text-decoration:none;">';
                                    echo '<span style="font-size: 20px;">📄</span>';
                                    echo '<div style="flex:1; overflow:hidden;"><div style="color: #fff; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . htmlspecialchars($attachment['original_filename']) . '</div><small style="color: #00ffaa; font-size: 10px; font-weight:bold;">CLICK TO VIEW</small></div></a>';
                                }
                                echo '</div>';
                            }
                            $attachments_stmt->close();
                            ?>
                        </div>

                        <!-- Footer Section (Actions/Status) -->
                        <div style="padding: 15px 20px; background: rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center;">
                            <?php if ($my_role === 'student' && !empty($post['due_date'])): 
                                $submission = $my_submissions[$post['id']] ?? null;
                            ?>
                            <div class="submission-status" style="display: flex; align-items: center; gap: 15px;">
                                <?php if ($submission): ?>
                                    <?php if ($submission['grade']): ?>
                                        <span class="status-text status-graded">Graded: <?php echo htmlspecialchars($submission['grade']); ?></span>
                                        <button class="sr-btn" style="margin-top: 10px;" onclick="event.stopPropagation(); alert('Viewing graded work is not yet implemented.')">View Submission</button>
                                    <?php else: ?>
                                        <span class="status-text status-turned-in">Turned In</span>
                                        <div style="display: flex; gap: 5px; margin-top: 10px;">
                                            <button class="sr-btn" onclick="event.stopPropagation(); openSubmissionModal(<?php echo $post['id']; ?>)">Resubmit</button>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="sr-btn" onclick="event.stopPropagation(); openSubmissionModal(<?php echo $post['id']; ?>)">Submit Work</button>
                                <?php endif; ?>
                            </div>
                            <?php elseif ($my_role === 'teacher' && !empty($post['due_date'])): ?>
                            <div class="submission-status" style="display:flex; gap: 25px; text-align:center;">
                                <div><span style="font-size: 16px; font-weight: bold; color:#00ffaa;"><?php echo $post['turned_in_count']; ?></span> <span style="font-size: 11px; color: #aaa;">Turned In</span></div>
                                <div><span style="font-size: 16px; font-weight: bold; color:#fff;"><?php echo $total_students - $post['turned_in_count']; ?></span> <span style="font-size: 11px; color: #aaa;">Assigned</span></div>
                                <div><span style="font-size: 16px; font-weight: bold; color:#8c9eff;"><?php echo $post['graded_count']; ?></span> <span style="font-size: 11px; color: #aaa;">Graded</span></div>
                            </div>
                            <?php else: ?>
                            <div style="font-size: 11px; color: #888;">Material shared with class</div>
                            <?php endif; ?>

                            <div style="font-size: 11px; color: #aaa; font-weight: bold;">NODE_REF: <?php echo str_pad($post['id'], 5, '0', STR_PAD_LEFT); ?></div>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="classwork-item" style="justify-content: center; color: #aaa;">
                            No classwork has been posted yet.
                        </div>
                    <?php endif; $posts_stmt->close(); ?>
                </div>
            <?php elseif ($page === 'people'): ?>
                <div class="people-list-container">
                    <div class="people-header">
                        <span>Teachers</span>
                    </div>
                    <?php
                    $teachers_stmt = $conn->prepare("SELECT t.name, t.profile_pic FROM sacli_room_members m JOIN teachers t ON SUBSTRING(m.student_id, 3) = t.id WHERE m.room_id = ? AND m.role = 'teacher'");
                    $teachers_stmt->bind_param("i", $room_id);
                    $teachers_stmt->execute();
                    $teachers_res = $teachers_stmt->get_result();
                    while($teacher = $teachers_res->fetch_assoc()):
                        $pic = !empty($teacher['profile_pic']) ? "uploads/".$teacher['profile_pic'] : "assets/images/3icons8-student-64.png";
                    ?>
                    <div class="people-item">
                        <img src="<?php echo $pic; ?>" alt="Teacher">
                        <span><?php echo htmlspecialchars($teacher['name']); ?></span>
                    </div>
                    <?php endwhile; $teachers_stmt->close(); ?>

                    <?php
                    $students_stmt = $conn->prepare("SELECT s.student_id, s.student_name, s.profile_pic FROM sacli_room_members m JOIN students s ON m.student_id = s.student_id WHERE m.room_id = ? AND m.role = 'student' ORDER BY s.student_name ASC");
                    $students_stmt->bind_param("i", $room_id);
                    $students_stmt->execute();
                    $students_res = $students_stmt->get_result();
                    $student_count = $students_res->num_rows;
                    ?>
                    <div class="people-header" style="margin-top: 40px;">
                        <span>Classmates</span>
                        <span><?php echo $student_count; ?> students</span>
                        <?php if ($my_role === 'teacher'): ?>
                            <button class="sr-btn" style="margin-left: 20px; font-size: 12px; padding: 5px 15px;" onclick="openInviteModal()">+ Invite Student</button>
                        <?php endif; ?>
                    </div>
                    <?php
                    if($student_count > 0):
                        while($student = $students_res->fetch_assoc()):
                            $pic = !empty($student['profile_pic']) ? "uploads/".$student['profile_pic'] : "assets/images/3icons8-student-64.png";
                    ?>
                    <div class="people-item">
                        <img src="<?php echo $pic; ?>" alt="Student">
                        <span><?php echo htmlspecialchars($student['student_name']); ?></span>
                        <?php if ($my_role === 'teacher'): ?>
                            <button class="sr-btn" style="margin-left: auto; background: #c9302c; color: white;" onclick='event.stopPropagation(); removeStudentFromRoom(<?php echo json_encode($student['student_id']); ?>, <?php echo json_encode($student['student_name']); ?>, <?php echo $room_id; ?>);'>Remove</button>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="people-item" style="justify-content: center; color: #aaa;">No students have joined this room yet.</div>
                    <?php endif; $students_stmt->close(); ?>
                </div>
            <?php elseif ($page === 'create_post'): 
                $from_page = $_GET['from'] ?? 'stream';
            ?>
                <div class="create-post-container">
                    <h3>Create New Post</h3>
                    <form id="createPostForm" method="POST" onsubmit="handlePostSubmit(event)" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create_room_post">
                        <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
                        <input type="hidden" name="from_page" value="<?php echo htmlspecialchars($from_page); ?>">
                        
                        <div class="form-group">
                            <label class="form-label" for="postTitle">Title (e.g., Activity 1)</label>
                            <input type="text" id="postTitle" name="title" placeholder="Title" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="postContent">Instructions (Optional)</label>
                            <textarea id="postContent" name="content" placeholder="Instructions..."></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="postDueDate">Due Date (Optional)</label>
                            <input type="datetime-local" id="postDueDate" name="due_date">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="postAttachments">Attach Files (Optional)</label>
                            <input type="file" id="postAttachments" name="attachments[]" multiple onchange="updateFilePreview(this)" style="background:transparent; border:none; padding:0;">
                            <div id="filePreviewContainer"></div>
                        </div>

                        <div style="display:flex; justify-content:flex-end; gap:15px; margin-top:30px;">
                            <button type="button" class="sr-btn join" onclick="history.back()">Cancel</button>
                            <button type="submit" class="sr-btn">Post</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Submission Modal -->
    <div id="submissionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeSubmissionModal()">&times;</span>
            <h3>Submit Assignment</h3>
            <form id="submissionForm" onsubmit="handleSubmission(event)" enctype="multipart/form-data" style="margin-top: 20px;">
                <input type="hidden" name="action" value="submit_assignment">
                <input type="hidden" name="post_id" id="submissionPostId">

                <!-- This will be the container for the uploaded file's name -->
                <div id="submissionPreview" style="display:none; background: #0a1f16; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #00ffaa; text-align: left; color: #e4e6eb; font-size: 14px;"></div>

                <!-- This button will trigger the file input -->
                <button type="button" class="sr-btn join" onclick="document.getElementById('submissionFile').click()" style="width: 100%; margin-bottom: 15px; justify-content: center;">+ Attach File</button>
                
                <!-- The actual file input is hidden -->
                <input type="file" id="submissionFile" name="submission_file" required style="display: none;" onchange="updateSubmissionPreview(this)">
                
                <!-- The submit button is initially disabled -->
                <button type="submit" id="submitWorkBtn" class="sr-btn" style="width: 100%; display: none;" disabled>Submit</button>
            </form>
        </div>
    </div>

    <!-- Invite Student Modal -->
    <div id="inviteModal" class="modal">
        <div class="modal-content" style="max-width: 450px; background: #05100c; border: 1px solid #00ffaa; box-shadow: 0 0 40px rgba(0, 255, 170, 0.2);">
            <span class="close" onclick="closeInviteModal()">&times;</span>
            <h3 style="color:#00ffaa; font-family:'Courier New'; letter-spacing: 2px;">// INVITE_NODES</h3>
            <p style="color:#b0fce0; font-size: 12px; margin-bottom: 15px; opacity: 0.8;">Select students to establish a neural link with this room.</p>
            
            <input type="text" id="inviteSearch" placeholder="Search student name..." onkeyup="filterInviteStudents()" style="width:100%; padding:12px; border-radius:10px; border:1px solid rgba(0, 255, 170, 0.3); background:rgba(0,0,0,0.4); color:white; margin-bottom:15px; outline:none; font-family:'Courier New';">
            
            <div id="inviteStudentList" style="max-height: 300px; overflow-y: auto; background: rgba(0,0,0,0.3); border-radius: 12px; padding: 10px; border: 1px solid rgba(255,255,255,0.05); scrollbar-width: thin;">
                <div style="text-align:center; padding:20px; color:#509b83; font-family:'Courier New'; font-size:12px;">INITIALIZING_DIRECTORY...</div>
            </div>
            
            <div style="margin-top:20px; display:flex; gap:10px;">
                <button class="sr-btn join" style="flex:1; justify-content:center;" onclick="closeInviteModal()">Cancel</button>
                <button class="sr-btn" style="flex:1; justify-content:center;" onclick="sendInvitations()">Send Invites</button>
            </div>
        </div>
    </div>

    <!-- Custom Confirm Modal -->
    <div id="customConfirmModal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <h3 style="color: #00ffaa; margin-bottom: 15px;">Confirmation</h3>
            <p id="customConfirmText" style="color: #e4e6eb; margin-bottom: 25px; font-size: 16px; line-height: 1.5;"></p>
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button id="confirmYesBtn" class="sr-btn" style="background: #ff5555; color: white;">Yes, Delete</button>
                <button id="confirmNoBtn" class="sr-btn join">Cancel</button>
            </div>
        </div>
    </div>

    <script>
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

        // --- CUSTOM CONFIRM MODAL ---
        window.confirmCallback = null; // Fix redeclaration error
        function showCustomConfirm(message, callback) {
            document.getElementById('customConfirmText').innerText = message;
            document.getElementById('customConfirmModal').style.display = 'flex';
            
            const yesBtn = document.getElementById('confirmYesBtn');
            const noBtn = document.getElementById('confirmNoBtn');

            yesBtn.onclick = () => {
                closeCustomConfirm();
                if (callback) callback();
            };
            noBtn.onclick = () => {
                closeCustomConfirm();
            };
        }

        function closeCustomConfirm() {
            document.getElementById('customConfirmModal').style.display = 'none';
        }

        // --- INVITE SYSTEM FUNCTIONS ---
        function openInviteModal() {
            document.getElementById('inviteModal').style.display = 'flex';
            fetch('chat_directory_fetch.php?type=student') // Re-using existing directory fetcher
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById('inviteStudentList');
                container.innerHTML = data.map(s => `
                    <div class="candidate-item" style="display:flex; align-items:center; gap:12px; padding:10px; border-bottom:1px solid rgba(255,255,255,0.03); transition: 0.3s; cursor:pointer;" onclick="this.querySelector('input').click()">
                        <input type="checkbox" class="invite-checkbox" value="${s.id}" onclick="event.stopPropagation()" style="accent-color:#00ffaa; width:18px; height:18px;">
                        <img src="${s.profile_pic ? 'uploads/'+s.profile_pic : 'assets/images/3icons8-student-64.png'}" style="width:35px; height:35px; border-radius:50%; object-fit:cover; border:1px solid rgba(0,255,170,0.3);">
                        <span class="c-name" style="font-size:13px; color:#eee; text-transform:capitalize;">${s.name.toLowerCase()}</span>
                    </div>
                `).join('');
            });
        }

        function closeInviteModal() { document.getElementById('inviteModal').style.display = 'none'; }

        function filterInviteStudents() {
            let q = document.getElementById('inviteSearch').value.toLowerCase();
            document.querySelectorAll('#inviteStudentList .candidate-item').forEach(item => {
                let name = item.querySelector('.c-name').innerText.toLowerCase();
                item.style.display = name.includes(q) ? 'flex' : 'none';
            });
        }

        function sendInvitations() {
            let selected = Array.from(document.querySelectorAll('.invite-checkbox:checked')).map(cb => cb.value);
            if(selected.length === 0) return showFlash("Please select at least one student.", "error");

            let fd = new FormData();
            fd.append('action', 'invite_to_room');
            fd.append('room_id', '<?php echo $room_id; ?>');
            selected.forEach(id => fd.append('student_ids[]', id));

            fetch('handlers/sacli_room_handler.php', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                showFlash(data.message, data.status);
                if(data.status === 'success') closeInviteModal();
            });
        }

        function updateFilePreview(input) {
            const previewContainer = document.getElementById('filePreviewContainer');
            previewContainer.innerHTML = '';
            if (input.files.length > 0) {
                Array.from(input.files).forEach(file => {
                    const fileDiv = document.createElement('div');
                    fileDiv.className = 'file-preview-item';
                    fileDiv.textContent = '📄 ' + file.name;
                    previewContainer.appendChild(fileDiv);
                });
            }
        }
        function handlePostSubmit(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerText = 'Posting...';

            // Explicitly point to the handler file to avoid 404 issues with form.action resolution
             fetch('/Capstone_Project_2026/sacli_room_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if the response is ok (status 200-299) and is JSON
                const contentType = response.headers.get("content-type");
                if (response.ok && contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    // If not, get the response text to see the PHP error
                    return response.text().then(text => {
                        throw new Error('Server response was not valid JSON. Response: \n' + text);
                    });
                }
            })
            .then(data => {
                showFlash(data.message, data.status);
                if (data.status === 'success' && data.redirect_to) {
                    setTimeout(() => { window.location.href = data.redirect_to; }, 1500);
                }
            })
            .catch(error => {
                console.error('Post Submission Error:', error);
                showFlash('An unexpected error occurred. Check console for details.', 'error');
            }).finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Post';
            });
        }

        function openSubmissionModal(postId) {
            document.getElementById('submissionPostId').value = postId;
            document.getElementById('submissionModal').style.display = 'flex';
            // Reset form state when opening
            document.getElementById('submissionForm').reset();
            document.getElementById('submissionPreview').style.display = 'none';
            document.getElementById('submitWorkBtn').disabled = true;
            document.getElementById('submitWorkBtn').style.display = 'none';
        }
        function closeSubmissionModal() {
            document.getElementById('submissionModal').style.display = 'none';
        }
        function handleSubmission(event) {
            event.preventDefault();
            const form = document.getElementById('submissionForm');
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerText = 'Submitting...';
            fetch('/Capstone_Project_2026/sacli_room_handler.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                showFlash(data.message, data.status);
                if (data.status === 'success') location.reload();
                else { submitBtn.disabled = false; submitBtn.innerText = 'Submit'; }
            }).catch(error => { console.error('Submission Error:', error); showFlash('An error occurred during submission.', 'error'); submitBtn.disabled = false; submitBtn.innerText = 'Submit'; });
        }
        function unsubmitAssignment(postId) {
            if (!confirm("Unsubmit your work? You can turn it in again after.")) return;
            let formData = new FormData();
            formData.append('action', 'unsubmit_assignment');
            formData.append('post_id', postId);
            fetch('/Capstone_Project_2026/sacli_room_handler.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                showFlash(data.message, data.status);
                if (data.status === 'success') location.reload();
            });
        }
        function updateSubmissionPreview(input) {
            const previewDiv = document.getElementById('submissionPreview');
            const submitBtn = document.getElementById('submitWorkBtn');
            if (input.files && input.files.length > 0) {
                previewDiv.textContent = '📄 ' + input.files[0].name;
                previewDiv.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.style.display = 'block';
            } else {
                previewDiv.style.display = 'none';
                submitBtn.disabled = true;
                submitBtn.style.display = 'none';
            }
        }

        function deleteRoomPost(postId, postTitle) {
            showCustomConfirm(`Are you sure you want to delete the post "${postTitle}"? This will also delete all associated submissions and cannot be undone.`, function() {
                let formData = new FormData();
                formData.append('action', 'delete_room_post');
                formData.append('post_id', postId);

                fetch('/Capstone_Project_2026/sacli_room_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    showFlash(data.message, data.status);
                    if (data.status === 'success') location.reload();
                })
                .catch(error => { console.error('Delete Error:', error); showFlash('An error occurred while deleting the post.', 'error'); });
            });
        }

        function removeStudentFromRoom(studentId, studentName, roomId) {
            showCustomConfirm(`Are you sure you want to remove "${studentName}" from this room?`, function() {
                let formData = new FormData();
                formData.append('action', 'remove_student');
                formData.append('student_id', studentId);
                formData.append('room_id', roomId);

                fetch('handlers/sacli_room_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    showFlash(data.message, data.status);
                    if (data.status === 'success') {
                        setTimeout(() => location.reload(), 1500);
                    }
                });
            });
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('submissionModal')) {
                closeSubmissionModal();
            }
            if (event.target == document.getElementById('customConfirmModal')) {
                closeCustomConfirm();
            }
        };

        // --- Scroll-Triggered Layout Expansion ---
        var banner = document.querySelector('.classroom-banner'); // Fix redeclaration error
        if (banner) {
            const scrollObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting && entry.boundingClientRect.top < 0) {
                        document.body.classList.add('scrolled-past');
                    } else {
                        document.body.classList.remove('scrolled-past');
                    }
                });
            }, { threshold: 0 });
            scrollObserver.observe(banner);
        }
    </script>
</body>
</html>