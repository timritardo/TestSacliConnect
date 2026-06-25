<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Check if signup is enabled
$signup_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='signup_enabled'");
$is_signup_enabled = ($signup_res && $signup_res->num_rows > 0 && $signup_res->fetch_assoc()['setting_value'] == '1');

// Fetch Site Theme
$theme_q = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='site_theme'");
$current_theme = ($theme_q && $theme_q->num_rows > 0) ? $theme_q->fetch_assoc()['setting_value'] : 'default';

$message = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_signup_enabled) {
    $type = $_POST['user_type']; // 'student' or 'teacher'
    
    if ($type === 'student') {
        $sid = trim($_POST['student_id']); // Student ID from the form
        $name = trim($_POST['student_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password']; // New password field
        $confirm_password = $_POST['confirm_password']; // New confirm password field
        $course = $_POST['course'];
        $year = $_POST['year_level'];

        // --- PROFILE PICTURE HANDLER ---
        $pic_filename = "";
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $pic_filename = "profile_" . $sid . "_" . time() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $pic_filename);
            }
        }
        
        // Validate password
        if (strlen($password) < 6) {
            $message = "Error: Password must be at least 6 characters long.";
            $msg_type = "error";
        } elseif ($password !== $confirm_password) {
            $message = "Error: Passwords do not match.";
            $msg_type = "error";
        } else {
            // Check if student ID exists in the pre-registered list and if it's already claimed
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $chk = $conn->prepare("SELECT student_id, password FROM students WHERE student_id=?");
            $chk->bind_param("s", $sid);
            $chk->execute();
            $res = $chk->get_result();

            if($res->num_rows == 0) {
                // Student ID does not exist, so it's not pre-registered by admin
                $message = "Error: Student ID not found. Please contact the admin to pre-register your ID.";
                $msg_type = "error";
            } else {
                $existing_student = $res->fetch_assoc();
                if (!empty($existing_student['password'])) {
                    // Student ID exists and already has a password, meaning it's fully registered
                    $message = "Error: Student ID already registered.";
                    $msg_type = "error";
                } else {
                    // Student ID exists but has no password, so it's pre-registered and available to claim
                    // Update the existing record with the new details and hashed password
                    $stmt = $conn->prepare("UPDATE students SET student_name=?, email=?, password=?, course=?, year_level=?, profile_pic=?, created_at=NOW() WHERE student_id=?");
                    $stmt->bind_param("sssssss", $name, $email, $hashed_password, $course, $year, $pic_filename, $sid);
                    if($stmt->execute()) {
                        $message = "Registration Successful! You can now log in with your new password.";
                        $msg_type = "success";
                    } else {
                        $message = "Error: Could not complete registration. Please try again.";
                        $msg_type = "error";
                    }
                }
            }
            $chk->close(); 
        }
    } else { // Teacher registration logic (unchanged)
        $name = trim($_POST['name']);
        $dept = $_POST['department'];
        $pos = trim($_POST['position']);
        $email = trim($_POST['email']);
        $pass = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // --- PROFILE PICTURE HANDLER ---
        $pic_filename = "";
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $pic_filename = "teacher_" . time() . "_" . uniqid() . "." . $ext;
                if (!is_dir('uploads')) mkdir('uploads', 0777, true);
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/" . $pic_filename);
            }
        }
        
        // Validate password
        if (strlen($pass) < 6) {
            $message = "Error: Password must be at least 6 characters long.";
            $msg_type = "error";
        } elseif ($pass !== $confirm_password) {
            $message = "Error: Passwords do not match.";
            $msg_type = "error";
        } else {
        // Check if Email exists
        $chk = $conn->query("SELECT id FROM teachers WHERE email='$email'");
        if($chk->num_rows > 0) {
            $message = "Error: Email already registered for a teacher account.";
            $msg_type = "error";
        } else {
            $hashed_pass = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO teachers (name, department, position, email, password, profile_pic, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssssss", $name, $dept, $pos, $email, $hashed_pass, $pic_filename);
            if($stmt->execute()) {
                $message = "Teacher Account Created! Login credentials are now active.";
                $msg_type = "success";
            }
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sacli Sign Up</title>
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/2_Login.css">
    <style>
        /* Hologram / JARVIS-inspired Design */
        body {
            background: #010a08 !important;
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(0, 255, 170, 0.1) 0%, transparent 80%),
                linear-gradient(to bottom, #010a08, #051a14) !important;
            height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            perspective: 1200px;
            overflow: hidden; /* Bawal ang scroll sa buong page */
        }

        /* Auto-scaling para magkasya ang card sa screen height */
        @media (max-height: 1080px) {
            .card {
                transform: scale(0.85);
                transform-origin: center;
            }
        }

        @media (max-height: 900px) {
            .card {
                transform: scale(0.7);
            }
            .form-side { padding: 30px !important; }
            .input-group { margin-bottom: 15px !important; }
            .logo { margin-bottom: 10px !important; }
            .tab-btns { margin-bottom: 20px !important; }
        }

        @media (max-height: 750px) {
            .card {
                transform: scale(0.55);
            }
            .form-side { padding: 20px !important; }
            .form-grid { gap: 10px !important; }
            .input-group { margin-bottom: 10px !important; }
        }

        @media (max-height: 600px) {
            .card {
                transform: scale(0.45);
            }
            .form-side p { margin-top: 5px !important; }
        }

        @media (max-height: 450px) {
            .card {
                transform: scale(0.35);
            }
        }

        /* Ayusin ang spacing para sa "Return to Log in" link */
        .form-side p {
            margin-top: 10px !important;
        }

        /* --- NEW UI ELEMENTS --- */
        .styled-wrapper {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 20;
        }

        .styled-wrapper .button {
            display: block;
            position: relative;
            width: 76px;
            height: 76px;
            margin: 0;
            overflow: hidden;
            outline: none;
            background-color: transparent;
            cursor: pointer;
            border: 0;
        }

        .styled-wrapper .button:before {
            content: "";
            position: absolute;
            border-radius: 50%;
            inset: 7px;
            border: 3px solid var(--neon-green); /* In-update para sa theme */
            transition: opacity 0.4s cubic-bezier(0.77, 0, 0.175, 1) 80ms,
                        transform 0.5s cubic-bezier(0.455, 0.03, 0.515, 0.955) 80ms;
        }

        .styled-wrapper .button:after {
            content: "";
            position: absolute;
            border-radius: 50%;
            inset: 7px;
            border: 4px solid #fff; /* Puti para sa highlight */
            transform: scale(1.3);
            transition: opacity 0.4s cubic-bezier(0.165, 0.84, 0.44, 1),
                        transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            opacity: 0;
        }

        .styled-wrapper .button:hover:before,
        .styled-wrapper .button:focus:before {
            opacity: 0;
            transform: scale(0.7);
        }

        .styled-wrapper .button:hover:after,
        .styled-wrapper .button:focus:after {
            opacity: 1;
            transform: scale(1);
        }

        .styled-wrapper .button-box {
            display: flex;
            position: absolute;
            top: 0;
            left: 0;
        }

        .styled-wrapper .button-elem {
            display: block;
            width: 30px;
            height: 30px;
            margin: 24px 18px 0 22px;
            transform: rotate(360deg);
            color: #00ffaa;
        }

        .styled-wrapper .button:hover .button-box,
        .styled-wrapper .button:focus .button-box {
            transition: 0.4s;
            transform: translateX(-69px);
        }

        /* Tech Grid Overlay */
        body::before {
            content: "";
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: 
                linear-gradient(rgba(0, 255, 170, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 170, 0.05) 1px, transparent 1px);
            background-size: 30px 30px;
            z-index: 0;
            pointer-events: none;
        }

        /* Background Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none; opacity: 0.6;
            overflow: hidden;
            transform-style: preserve-3d;
        }
        
        /* Neural Data Streams */
        .data-stream {
            position: absolute;
            width: 1px;
            height: 100px;
            background: linear-gradient(to bottom, transparent, #00ffaa, transparent);
            opacity: 0.3;
            animation: streamDown 5s linear infinite;
        }
        @keyframes streamDown {
            0% { transform: translateY(-100vh) translateZ(-200px); opacity: 0; }
            50% { opacity: 0.5; }
            100% { transform: translateY(100vh) translateZ(200px); opacity: 0; }
        }

        .particle {
            position: absolute;
            background: #fff;
            border-radius: 50%;
            box-shadow: 0 0 8px #00ffaa;
            animation-name: particleFlow;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }
        @keyframes particleFlow {
            from { transform: translateZ(-100px) translateY(var(--y-start)) translateX(var(--x-start)); opacity: 0; }
            20% { opacity: 0.8; }
            80% { opacity: 0.8; }
            to { transform: translateZ(600px) translateY(var(--y-end)) translateX(var(--x-end)); opacity: 0; }
        }

        .card {
            width: 1250px; /* Mas malapad para sa malaking preview */
            max-width: 95%;
            margin: auto;
            padding: 0;
            border-radius: 24px; 
            background: rgba(8, 22, 16, 0.85); 
            backdrop-filter: blur(30px); 
            -webkit-backdrop-filter: blur(30px);
            border: 1px solid rgba(0, 255, 170, 0.2); 
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.5), inset 0 0 2px rgba(0, 255, 170, 0.3); 
            position: relative;
            overflow: hidden;
            z-index: 2; /* Ensure card is above particles */
            display: none; /* Initially hidden for intro */
            margin: 20px 0;
            flex-direction: row; /* Split Layout */
        }

        .form-side {
            flex: 1; /* Pantay na hatian */
            padding: 50px;
            border-right: 1px solid rgba(0, 255, 170, 0.1);
            max-height: 85vh; /* Limit ang taas para magkasya sa screen */
            overflow-y: auto; /* Pinapayagan ang scroll sa loob nito */
            scrollbar-width: thin;
            scrollbar-color: #00ffaa transparent;
        }

        /* Custom Scrollbar para sa Form Side */
        .form-side::-webkit-scrollbar { width: 5px; }
        .form-side::-webkit-scrollbar-track { background: transparent; }
        .form-side::-webkit-scrollbar-thumb { 
            background: rgba(0, 255, 170, 0.3); 
            border-radius: 10px; 
        }
        .form-side::-webkit-scrollbar-thumb:hover { background: #00ffaa; }

        .preview-side {
            flex: 1; /* Lalong lalakihan ang preview side */
            padding: 50px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at center, rgba(0, 255, 170, 0.05) 0%, transparent 70%);
            position: relative;
            border-left: 1px solid rgba(0, 255, 170, 0.1);
        }

        .preview-badge {
            width: 100%;
            max-width: 440px; /* Mas malaking ID card */
            padding: 60px 40px;
            border: 1px solid rgba(0, 255, 170, 0.3);
            border-radius: 20px;
            text-align: center;
            background: rgba(5, 15, 10, 0.6);
            backdrop-filter: blur(10px);
            box-shadow: 0 0 60px rgba(0, 0, 0, 0.8), inset 0 0 30px rgba(0, 255, 170, 0.05);
            position: relative;
            overflow: hidden;
            animation: badgeFloat 6s ease-in-out infinite;
        }

        @keyframes badgeFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Holographic Scanline Effect */
        .preview-badge::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(rgba(0, 255, 170, 0.05) 1px, transparent 1px);
            background-size: 100% 3px;
            pointer-events: none;
            z-index: 1;
        }

        .preview-badge::after {
            content: '';
            position: absolute;
            top: -100%; left: 0; width: 100%; height: 50%;
            background: linear-gradient(to bottom, transparent, rgba(0, 255, 170, 0.1), transparent);
            animation: scanlineMove 4s linear infinite;
            z-index: 2;
        }

        @keyframes scanlineMove {
            0% { top: -100%; }
            100% { top: 100%; }
        }

        .pfp-wrap {
            position: relative;
            width: 180px; /* Mas malaking space para sa picture */
            height: 180px;
            margin: 0 auto 35px;
        }

        .pfp-ring {
            position: absolute;
            top: -15px; left: -15px; right: -15px; bottom: -15px;
            border: 3px dashed rgba(0, 255, 170, 0.4);
            border-radius: 50%;
            animation: spin 15s linear infinite;
        }
        
        /* Add holographic info rows */
        .p-row { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 8px; border-bottom: 1px solid rgba(0, 255, 170, 0.05); padding-bottom: 4px; }
        .p-row span:first-child { color: #509b83; font-family: 'Courier New', monospace; font-weight: bold; }
        .p-row span:last-child { color: #fff; font-weight: 600; text-transform: uppercase; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .preview-pfp {
            width: 100%; height: 100%;
            border-radius: 50%;
            border: 2px solid #00ffaa;
            background: rgba(0,0,0,0.4);
            display: flex; align-items: center; justify-content: center;
            font-size: 50px; color: rgba(0, 255, 170, 0.2);
            box-shadow: 0 0 30px rgba(0, 255, 170, 0.2);
            position: relative;
            z-index: 2;
            transition: 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        /* Animated Border Loop */
        .card::after {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 26px;
            padding: 2px;
            background: linear-gradient(90deg, transparent, #00ffaa, transparent, #00ffaa, transparent);
            background-size: 200% 100%;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: borderLoop 4s linear infinite;
            pointer-events: none;
        }
        @keyframes pulse-text {
            0% { opacity: 0.5; }
            100% { opacity: 1; text-shadow: 0 0 10px #00ffaa; }
        }

        #p-name { font-family: 'Orbitron', sans-serif; font-size: 28px; color: #fff; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 3px; }
        #p-info { font-family: 'Courier New', monospace; font-size: 12px; color: #00ffaa; text-transform: uppercase; opacity: 0.7; }
        .empty-placeholder { animation: pulse-text 1.5s infinite alternate; color: rgba(255,255,255,0.1) !important; }

        @keyframes borderLoop {
            0% { background-position: 0% 0%; }
            100% { background-position: 200% 0%; }
        }

        /* --- Grid System for Two-Column Layout --- */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            text-align: left;
        }
        .full-width {
            grid-column: span 2;
        }
        
        .form-grid .input-group {
            margin-bottom: 20px; /* Balanced spacing */
            width: 100%;
        }

        /* Scanline effect for the card */
        .card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 2px;
            background: linear-gradient(90deg, transparent, #00ffaa, transparent);
            box-shadow: 0 0 30px #00ffaa, 0 0 10px #fff;
            animation: scan-down 4s linear infinite;
            opacity: 0.5;
            z-index: 10;
        }
        @keyframes scan-down {
            0% { top: -10%; }
            100% { top: 110%; }
        }

        /* Rotating HUD Ring in background */
        .hud-ring {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 800px; height: 800px;
            border: 2px dashed rgba(0, 255, 170, 0.1);
            border-radius: 50%;
            z-index: 0;
            animation: spin 60s linear infinite;
            pointer-events: none;
        }
        @keyframes spin { from { transform: translate(-50%, -50%) rotate(0deg); } to { transform: translate(-50%, -50%) rotate(360deg); } }

        /* Tech HUD Corners */
        .corner {
            position: absolute;
            width: 30px;
            height: 30px;
            border-color: #00ffaa;
            border-style: solid;
            opacity: 0.8;
            z-index: 5;
        }
        .tl { top: 15px; left: 15px; border-width: 3px 0 0 3px; }
        .tr { top: 15px; right: 15px; border-width: 3px 3px 0 0; }
        .bl { bottom: 15px; left: 15px; border-width: 0 0 3px 3px; }
        .br { bottom: 15px; right: 15px; border-width: 0 3px 3px 0; }

        .logo {
            text-align: center;
            color: #fff;
            font-size: 34px; 
            margin-bottom: 10px;
            text-shadow: 0 0 15px rgba(0, 255, 170, 0.4); 
            letter-spacing: 6px;
            font-weight: 900;
            animation: logoFloat 4s ease-in-out infinite;
            font-family: 'Orbitron', sans-serif;
        }
        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); text-shadow: 0 0 30px rgba(0, 255, 170, 0.8); }
        }

        .tab-btns {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border: 1.5px solid rgba(0, 255, 170, 0.2); /* Border for the whole tab group */
            border-radius: 30px;
            overflow: hidden;
            background: rgba(0,0,0,0.2);
            width: fit-content;
            margin: 0 auto 30px auto;
        }
        .tab-btn {
            padding: 10px 40px; 
            border: none; /* Remove individual button borders */
            background: transparent;
            color: #b0fce0; /* Lighter text for inactive */
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 11px;
            font-family: 'Orbitron', sans-serif;
        }
        .tab-btn.active {
            background: #00ffaa; 
            color: #0a1f16; /* Dark text on neon */
            box-shadow: 0 0 15px rgba(0, 255, 170, 0.3);
        }
        .tab-btn:hover:not(.active) {
            background: rgba(0, 255, 170, 0.1); /* Subtle hover for inactive */
            color: #00ffaa;
        }

        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .success { background: rgba(0,255,170,0.15); color: #00ffaa; border: 1px solid #00ffaa; }
        .error { background: rgba(255,85,85,0.15); color: #ff5555; border: 1px solid #ff5555; }
        select { width: 100%; padding: 1rem; border-radius: 5px; border: 1px solid rgba(0, 255, 170, .3); background: rgba(0,0,0,0.4); color: white; margin-bottom: 20px; outline: none; transition: 0.3s; font-family: 'Courier New', Courier, monospace; }
        select:focus { border-color: #00ffaa; box-shadow: 0 0 15px rgba(0, 255, 170, .4); }
    </style>
    <style>
        /* Animations for elements */
        @keyframes hologram-reveal {
            0% { opacity: 0; transform: translateY(20px) scale(0.95); filter: blur(10px); }
            100% { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
        }
        .card > * {
            animation: hologram-reveal 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) backwards;
        }
        .card .logo { animation-delay: 0.1s; }
        .card .alert { animation-delay: 0.2s; }
        .card .tab-btns { animation-delay: 0.3s; }
        .card p { animation-delay: 0.4s; }
        .card form > .input-group:nth-child(1) { animation-delay: 0.5s; }
        .card form > .input-group:nth-child(2) { animation-delay: 0.6s; }
        .card form > .input-group:nth-child(3) { animation-delay: 0.7s; }
        .card form > label:nth-of-type(1) { animation-delay: 0.8s; }
        .card form > select:nth-of-type(1) { animation-delay: 0.9s; }
        .card form > label:nth-of-type(2) { animation-delay: 1.0s; }
        .card form > select:nth-of-type(2) { animation-delay: 1.1s; }
        .card form > button { animation-delay: 1.2s; }
        .card > p:last-of-type { animation-delay: 1.3s; }

        /* Input field glow on focus */
        .input:focus {
            border-color: #00ffaa !important;
            box-shadow: 0 0 25px rgba(0, 255, 170, 0.3) !important;
            background: rgba(0, 255, 170, 0.05) !important; 
        }
        .input {
            background: rgba(0,0,0,0.3) !important; /* More transparent */
            border-radius: 5px !important;
            border-color: rgba(0, 255, 170, 0.3) !important;
            font-family: 'Courier New', Courier, monospace;
            font-weight: 500;
        }
        .user-label {
            color: #b0fce0 !important; /* Lighter label */
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px !important;
        }
        .input:focus~.user-label, .input:valid~.user-label {
            background: #0d2b1f !important; /* Match body background for label cutout */
            color: #00ffaa !important; /* Neon green label */
            border-radius: 5px;
            padding: 2px 8px !important;
        }

        button[type="submit"] {
            background: linear-gradient(90deg, #00ffaa, #00cc88) !important;
            color: #0a1f16 !important;
            border: none !important;
            box-shadow: 0 0 25px rgba(0, 255, 170, 0.4) !important;
            border-radius: 5px !important;
            font-weight: 900 !important;
            text-transform: uppercase;
            letter-spacing: 3px;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
            padding: 16px !important;
            width: 100%; /* Siguraduhin na full width pa rin */
            margin-top: 10px !important; /* Nilagyan ng kaunting space dahil scrollable na */
            cursor: pointer;
        }
        button[type="submit"]:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 0 40px rgba(0, 255, 170, 0.8) !important;
            filter: brightness(1.1);
            color: #000 !important;
        }

        .hud-stat {
            position: absolute;
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            color: rgba(0, 255, 170, 0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
            pointer-events: none;
        }

        /* --- INTRO OVERLAY STYLES --- */
        .intro-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at center, #0a251c 0%, #010a08 100%);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #00ffaa;
            font-family: 'Courier New', monospace;
            overflow: hidden;
        }

        /* Tech Grid Overlay sa Loading Screen */
        .intro-overlay::before {
            content: "";
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: 
                linear-gradient(rgba(0, 255, 170, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 170, 0.05) 1px, transparent 1px);
            background-size: 50px 50px;
            background-position: center;
            z-index: -1;
            mask-image: radial-gradient(circle, black 30%, transparent 85%);
            -webkit-mask-image: radial-gradient(circle, black 30%, transparent 85%);
            animation: gridPulse 4s ease-in-out infinite alternate;
        }

        /* Dumadaang Scan Light sa background */
        .intro-overlay::after {
            content: "";
            position: absolute;
            top: 0; left: -100%; width: 60%; height: 100%;
            background: linear-gradient(to right, transparent, rgba(0, 255, 170, 0.04), transparent);
            transform: skewX(-30deg);
            animation: introScan 7s infinite ease-in-out;
            z-index: -1;
        }

        @keyframes introScan {
            0% { left: -150%; }
            100% { left: 250%; }
        }

        @keyframes gridPulse {
            from { opacity: 0.3; transform: scale(1); }
            to { opacity: 0.7; transform: scale(1.05); }
        }
        .boot-text {
            margin-top: 20px;
            font-size: 14px;
            letter-spacing: 2px;
            height: 20px;
        }
        .boot-loader {
            width: 300px;
            height: 2px;
            background: rgba(0, 255, 170, 0.1);
            position: relative;
            margin-top: 10px;
            overflow: hidden;
        }
        .boot-loader-fill {
            position: absolute;
            top: 0; left: 0; width: 0%; height: 100%;
            background: #00ffaa;
            box-shadow: 0 0 15px #00ffaa;
            transition: width 0.1s linear;
        }

        /* Mobile Responsiveness */
        @media (max-width: 850px) {
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            .card { padding: 30px 20px; }
            .logo { font-size: 28px; }
            button[type="submit"] { font-size: 14px; letter-spacing: 1px; }
            /* I-stack ang form at preview sa mobile */
            .card {
                flex-direction: column !important;
            }
        }
        
        /* Eye Icon for Password toggle */
        .pass-toggle {
            position: absolute;
            right: 15px; top: 15px;
            cursor: pointer;
            color: #00ffaa; opacity: 0.7; transition: 0.3s;
        }

        /* --- NEW SVG LOADER STYLES --- */
        .loader {
          --cell-size: 52px;
          --cell-spacing: 1px;
          --cells: 3;
          --total-size: calc(var(--cells) * (var(--cell-size) + 2 * var(--cell-spacing)));
          display: flex;
          flex-wrap: wrap;
          width: var(--total-size);
          height: var(--total-size);
        }

        .cell {
          flex: 0 0 var(--cell-size);
          margin: var(--cell-spacing);
          background-color: transparent;
          box-sizing: border-box;
          border-radius: 4px;
          animation: 1.5s ripple ease infinite;
        }
        .cell.d-1 {
          animation-delay: 100ms;
        }

        .cell.d-2 {
          animation-delay: 200ms;
        }

        .cell.d-3 {
          animation-delay: 300ms;
        }

        .cell.d-4 {
          animation-delay: 400ms;
        }

        .cell:nth-child(1) { --cell-color: #00FF87; }
        .cell:nth-child(2) { --cell-color: #0CFD95; }
        .cell:nth-child(3) { --cell-color: #17FBA2; }
        .cell:nth-child(4) { --cell-color: #23F9B2; }
        .cell:nth-child(5) { --cell-color: #30F7C3; }
        .cell:nth-child(6) { --cell-color: #3DF5D4; }
        .cell:nth-child(7) { --cell-color: #45F4DE; }
        .cell:nth-child(8) { --cell-color: #53F1F0; }
        .cell:nth-child(9) { --cell-color: #60EFFF; }

        /*Animation*/
        @keyframes ripple {
          0% {
            background-color: transparent;
          }
          30% {
            background-color: var(--cell-color);
          }
          60% {
            background-color: transparent;
          }
          100% {
            background-color: transparent;
          }
        }
    </style>
</head>
<body class="theme-<?php echo htmlspecialchars($current_theme); ?>">
    <div class="particles" id="particles"></div>
    <div class="hud-ring"></div>

    <!-- Intro Animation Overlay -->
    <div class="intro-overlay" id="introOverlay">
        <div class="loader">
          <div class="cell d-0"></div>
          <div class="cell d-1"></div>
          <div class="cell d-2"></div>

          <div class="cell d-1"></div>
          <div class="cell d-2"></div>
          <div class="cell d-2"></div>
          <div class="cell d-3"></div>
          <div class="cell d-3"></div>
          <div class="cell d-4"></div>
        </div>
        <div class="boot-text" id="bootText">INITIALIZING SYSTEM...</div>
        <div class="boot-loader">
            <div class="boot-loader-fill" id="bootFill"></div>
        </div>
    </div>

    <div class="card">
        <div class="form-side">
            <!-- HUD Decor -->
            <div class="styled-wrapper">
                <button class="button" onclick="window.location.href='SacliConnect_LOG_IN.php'">
                    <div class="button-box">
                        <span class="button-elem">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="arrow-icon" style="width: 30px; height: 30px;">
                                <path fill="#00ffaa" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path>
                            </svg>
                        </span>
                        <span class="button-elem">
                            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" class="arrow-icon" style="width: 30px; height: 30px;">
                                <path fill="#00ffaa" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"></path>
                            </svg>
                        </span>
                    </div>
                </button>
            </div>

            <div class="corner tl"></div>
            <div class="corner tr"></div>
            <div class="logo"><strong>SACLICONNECT</strong></div>
            <div style="text-align: center; color: #00ffaa; font-size: 10px; letter-spacing: 4px; margin-bottom: 30px; opacity: 0.8;">NEW_USER_REGISTRATION_PROTOCOL</div>
        
        <?php if (!$is_signup_enabled): ?>
            <div style="text-align: center; padding: 40px 20px;">
                <div style="font-size: 50px; margin-bottom: 20px;">🔒</div>
                <h2 style="color: #ff5555; margin-bottom: 10px;">System Locked</h2>
                <p style="color: #ccc; line-height: 1.6;">The sign up process is currently disabled by the administrator. Please try again later or contact the registrar office.</p>
                <button onclick="window.location.href='SacliConnect_LOG_IN.php'" style="margin-top: 30px; background: transparent; border: 1px solid #00ffaa; color: #00ffaa;">Back to Login</button>
            </div>
        <?php else: ?>
            
            <?php if($message): ?>
                <div class="alert <?php echo $msg_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="tab-btns">
                <button style="position: relative; top: 1px;" class="tab-btn active" id="btnStudent" onclick="switchMode('student')">STUDENT</button>
                <button style="position: relative; top: 1px;" class="tab-btn" id="btnTeacher" onclick="switchMode('teacher')">TEACHER</button>
            </div>

            <p style="text-align: center; color: #509b83; margin-top: -15px; margin-bottom: 25px; font-size: 14px;">Fill in your details to create a new account.</p>

            <!-- Student Form -->
            <form method="POST" id="studentForm" enctype="multipart/form-data">
                <input type="hidden" name="user_type" value="student">
                
                <div class="form-grid">
                    <div class="input-group full-width">
                        <label style="color: #b0fce0; font-size: 12px; margin-left: 5px; margin-bottom: 5px; display: block;">Profile Picture</label>
                        <input type="file" name="profile_pic" accept="image/*" class="input" style="padding: 10px;" onchange="previewPFP(this)">
                    </div>

                    <div class="input-group">
                        <input required type="text" name="student_id" class="input" autocomplete="off">
                        <label class="user-label">Student ID Number</label>
                    </div>

                    <div class="input-group">
                        <input required type="text" name="student_name" class="input" autocomplete="off">
                        <label class="user-label">Full Name</label>
                    </div>

                    <div class="input-group full-width">
                        <input required type="email" name="email" class="input" autocomplete="off">
                        <label class="user-label">Email Address</label>
                    </div>

                    <div class="input-group">
                        <input required type="password" name="password" id="student_pass" class="input" autocomplete="off">
                        <label class="user-label">Set Password</label>
                        <span class="pass-toggle" onclick="togglePass('student_pass')">👁</span>
                    </div>

                    <div class="input-group">
                        <input required type="password" name="confirm_password" id="student_conf" class="input" autocomplete="off">
                        <label class="user-label">Confirm Password</label>
                        <span class="pass-toggle" onclick="togglePass('student_conf')">👁</span>
                    </div>

                    <div class="input-group">
                        <label style="color: #b0fce0; font-size: 12px; margin-left: 5px; margin-bottom: 5px; display: block;">Course / Program</label>
                        <select name="course" required>
                            <option value="" disabled selected>Select your Course</option>
                            <optgroup label="Nursing & Allied Health">
                                <option value="BS Nursing">BS in Nursing (BSN)</option>
                                <option value="BS Radiologic Technology">BS in Radiologic Technology (BSRT)</option>
                                <option value="BS Physical Therapy">BS in Physical Therapy (BSPT)</option>
                                <option value="Diploma in Midwifery">Diploma in Midwifery</option>
                            </optgroup>
                            <optgroup label="IT & Computer Studies">
                                <option value="BS Information Technology">BS in Information Technology (BSIT)</option>
                                <option value="BS Computer Science">BS in Computer Science (BSCS)</option>
                            </optgroup>
                            <optgroup label="Hospitality & Tourism Management">
                                <option value="BS Hospitality Management">BS in Hospitality Management (BSHM)</option>
                                <option value="BS Tourism Management">BS in Tourism Management (BSTM)</option>
                            </optgroup>
                            <optgroup label="Business Administration & Accountancy">
                                <option value="BS Accountancy">BS in Accountancy (BSA)</option>
                                <option value="BS Management Accounting">BS in Management Accounting (BSMA)</option>
                                <option value="BS Business Administration">BS in Business Administration (BSBA)</option>
                            </optgroup>
                            <optgroup label="Arts, Sciences, & Education">
                                <option value="BS Psychology">BS in Psychology (BS Psych)</option>
                                <option value="BS Criminology">BS in Criminology (BS Crim)</option>
                                <option value="BEEd">Bachelor of Elementary Education (BEEd)</option>
                                <option value="BSEd">Bachelor of Secondary Education (BSEd)</option>
                            </optgroup>
                            <optgroup label="Integrated Basic Education (SHS)">
                                <option value="SHS - STEM">SHS - STEM Strand</option>
                                <option value="SHS - ABM">SHS - ABM Strand</option>
                                <option value="SHS - HUMSS">SHS - HUMSS Strand</option>
                                <option value="SHS - TVL">SHS - TVL (HE/ICT)</option>
                            </optgroup>
                        </select>
                    </div>

                    <div class="input-group">
                        <label style="color: #b0fce0; font-size: 12px; margin-left: 5px; margin-bottom: 5px; display: block;">Year Level</label>
                        <select name="year_level" required>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                </div>

                <button type="submit">CREATE ACCOUNT</button>
            </form>

            <!-- Teacher Form -->
            <form method="POST" id="teacherForm" style="display:none;" enctype="multipart/form-data">
                <input type="hidden" name="user_type" value="teacher">

                <div class="form-grid">
                    <div class="input-group full-width">
                        <label style="color: #b0fce0; font-size: 12px; margin-left: 5px; margin-bottom: 5px; display: block;">Profile Picture</label>
                        <input type="file" name="profile_pic" accept="image/*" class="input" style="padding: 10px;" onchange="previewPFP(this)">
                    </div>

                    <div class="input-group full-width">
                        <input required type="text" name="name" class="input" autocomplete="off">
                        <label class="user-label">Full Name</label>
                    </div>

                    <div class="input-group">
                        <label style="color: #b0fce0; font-size: 12px; margin-left: 5px; margin-bottom: 5px; display: block;">Department</label>
                        <select name="department" required>
                            <option value="College Department">College Department</option>
                            <option value="Senior High School">Senior High School</option>
                            <option value="Junior High School">Junior High School</option>
                            <option value="Elementary Department">Elementary Department</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <input required type="text" name="position" class="input" autocomplete="off">
                        <label class="user-label">Position</label>
                    </div>

                    <div class="input-group full-width">
                        <input required type="email" name="email" class="input" autocomplete="off">
                        <label class="user-label">Email Address</label>
                    </div>

                    <div class="input-group">
                        <input required type="password" name="password" id="teacher_pass" class="input" autocomplete="off">
                        <label class="user-label">Set Password</label>
                        <span class="pass-toggle" onclick="togglePass('teacher_pass')">👁</span>
                    </div>

                    <div class="input-group">
                        <input required type="password" name="confirm_password" id="teacher_conf" class="input" autocomplete="off">
                        <label class="user-label">Confirm Password</label>
                        <span class="pass-toggle" onclick="togglePass('teacher_conf')">👁</span>
                    </div>
                </div>

                <button type="submit">CREATE ACCOUNT</button>
            </form>
        </div> <!-- End Form Side -->

        <div class="preview-side">
            <div style="position: absolute; top: 20px; font-family: 'Courier New'; font-size: 10px; color: #509b83; letter-spacing: 2px;">LIVE_PROFILE_PREVIEW</div>
            <div class="preview-badge">
                <div class="pfp-wrap">
                    <div class="pfp-ring"></div>
                    <div class="preview-pfp" id="p-icon">👤</div>
                </div>
                <div id="p-name" class="empty-placeholder">GUEST_USER</div>
                <div id="p-info" class="empty-placeholder">AWAITING_DATA...</div>

                <div style="margin-top: 35px; text-align: left;">
                    <div class="p-row">
                        <span>SYSTEM_ID</span>
                        <span id="p-uid">0000-0000</span>
                    </div>
                    <div class="p-row">
                        <span>UPLINK_MAIL</span>
                        <span id="p-email">NULL@SACLI.EDU</span>
                    </div>
                    <div class="p-row">
                        <span>STATUS_LEVEL</span>
                        <span id="p-year">PENDING_INIT</span>
                    </div>
                </div>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid rgba(0, 255, 170, 0.1);">
                    <div style="display:flex; justify-content: space-between; font-size: 9px; font-family: 'Courier New'; color: #509b83;">
                        <span>AUTH_LEVEL</span>
                        <span id="p-role">PENDING</span>
                    </div>
                    <div style="background: rgba(0, 255, 170, 0.1); height: 2px; width: 100%; margin-top: 5px; position: relative; overflow: hidden;">
                        <div style="position: absolute; left: 0; top: 0; height: 100%; width: 40%; background: #00ffaa; box-shadow: 0 0 10px #00ffaa;"></div>
                    </div>
                </div>
            </div>
            <div class="hud-stat" style="bottom: 20px; right: 20px;">SCAN_STATUS: STABLE</div>
            <div class="hud-stat" style="bottom: 35px; right: 20px;">NODE: LUCENA_01</div>
        </div>

        <audio id="bootSound" src="https://assets.mixkit.co/active_storage/sfx/915/915-preview.mp3" preload="auto"></audio>
        <audio id="formRevealSound" src="https://assets.mixkit.co/active_storage/sfx/2561/2561-preview.mp3" preload="auto"></audio>
        <audio id="typingSound" src="https://assets.mixkit.co/active_storage/sfx/846/846-preview.mp3" preload="auto"></audio>

        <?php endif; ?>
    </div>

    <script>
        // --- INTRO SEQUENCE ---
        const bootMessages = [
            "CONNECTING TO NEURAL LINK...",
            "FETCHING DATABASE NODE...",
            "ENCRYPTING DATA STREAM...",
            "SECURITY PROTOCOLS READY",
            "ESTABLISHING UPLINK..."
        ];
        
        let bootIndex = 0;
        let bootProgress = 0;
        const bootText = document.getElementById('bootText');
        const bootFill = document.getElementById('bootFill');
        const introOverlay = document.getElementById('introOverlay');
        const bootSound = document.getElementById('bootSound');
        const formRevealSound = document.getElementById('formRevealSound');
        const typingSound = document.getElementById('typingSound');

        const signupCard = document.querySelector('.card');

        function startBoot() {
            const interval = setInterval(() => {
                bootProgress += Math.random() * 5;
                if (bootProgress >= 100) {
                    bootSound.pause();
                    bootSound.currentTime = 0;
                    bootProgress = 100;
                    clearInterval(interval);
                    
                    // Fade out intro and show card
                    setTimeout(() => {
                        formRevealSound.play().catch(e => console.warn("Form reveal sound blocked:", e));
                        introOverlay.style.transition = 'opacity 0.8s ease, filter 0.8s ease';
                        introOverlay.style.opacity = '0';
                        introOverlay.style.filter = 'blur(20px)';
                        
                        signupCard.style.display = 'flex';
                        signupCard.style.animation = 'hologram-reveal 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards';
                        
                        setTimeout(() => introOverlay.remove(), 800);
                    }, 500);
                }
                bootFill.style.width = bootProgress + '%';
                if (Math.floor(bootProgress / 20) > bootIndex && bootIndex < bootMessages.length) {
                    bootText.innerText = bootMessages[bootIndex++];
                }
                bootSound.play().catch(e => console.warn("Boot sound blocked:", e));
            }, 100);
        }

        // Generate floating particles
        const particlesContainer = document.querySelector('.particles');
        const particleCount = 40;
        for (let i = 0; i < particleCount; i++) {
            let p = document.createElement('div');
            p.classList.add('particle');
            let size = Math.random() * 2.5 + 1;
            p.style.width = size + 'px'; p.style.height = size + 'px';
            // Set CSS variables for the animation
            p.style.setProperty('--x-start', (Math.random() * 100 - 50) + 'vw');
            p.style.setProperty('--y-start', (Math.random() * 100 - 50) + 'vh');
            p.style.setProperty('--x-end', (Math.random() * 100 - 50) + 'vw');
            p.style.setProperty('--y-end', (Math.random() * 100 - 50) + 'vh');
            
            p.style.animationDuration = (Math.random() * 8 + 7) + 's';
            p.style.animationDelay = (Math.random() * -15) + 's'; // Negative delay
            particlesContainer.appendChild(p);
        }

        function previewPFP(input) {
            const pIcon = document.getElementById('p-icon');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    pIcon.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">`;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function togglePass(id) {
            const input = document.getElementById(id);
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }

        function switchMode(mode) {
            const sForm = document.getElementById('studentForm');
            const tForm = document.getElementById('teacherForm');
            const sBtn = document.getElementById('btnStudent');
            const tBtn = document.getElementById('btnTeacher');
            const pRole = document.getElementById('p-role');
            
            // --- DATA SYNC LOGIC ---
            // Kunin ang mga common inputs
            const sName = document.querySelector('#studentForm input[name="student_name"]');
            const sEmail = document.querySelector('#studentForm input[name="email"]');
            const sPass = document.querySelector('#studentForm input[name="password"]');
            const sConf = document.querySelector('#studentForm input[name="confirm_password"]');

            const tName = document.querySelector('#teacherForm input[name="name"]');
            const tEmail = document.querySelector('#teacherForm input[name="email"]');
            const tPass = document.querySelector('#teacherForm input[name="password"]');
            const tConf = document.querySelector('#teacherForm input[name="confirm_password"]');

            if (mode === 'student') {
                // I-copy ang data mula teacher patungong student bago lumipat
                sName.value = tName.value;
                sEmail.value = tEmail.value;
                sPass.value = tPass.value;
                sConf.value = tConf.value;

                sForm.style.display = 'block';
                tForm.style.display = 'none';
                sBtn.classList.add('active');
                tBtn.classList.remove('active');
                pRole.innerText = "STUDENT_ACCESS";
                updatePreview();
            } else {
                // I-copy ang data mula student patungong teacher bago lumipat
                tName.value = sName.value;
                tEmail.value = sEmail.value;
                tPass.value = sPass.value;
                tConf.value = sConf.value;

                sForm.style.display = 'none';
                tForm.style.display = 'block';
                sBtn.classList.remove('active');
                tBtn.classList.add('active');
                pRole.innerText = "TEACHER_ACCESS";
                updatePreview();
            }
        }

        // --- LIVE PREVIEW LOGIC ---
        function updatePreview() {
            const isStudent = document.getElementById('btnStudent').classList.contains('active');
            const formId = isStudent ? 'studentForm' : 'teacherForm';
            const nameInput = document.querySelector(`#${formId} input[name="${isStudent?'student_name':'name'}"]`);
            const emailInput = document.querySelector(`#${formId} input[name="email"]`);
            const idInput = isStudent ? document.querySelector(`#${formId} input[name="student_id"]`) : null;
            
            const pName = document.getElementById('p-name');
            const pInfo = document.getElementById('p-info');
            const pEmail = document.getElementById('p-email');
            const pYear = document.getElementById('p-year');
            const pUid = document.getElementById('p-uid');
            const pIcon = document.getElementById('p-icon');

            // Name Sync
            if (nameInput.value.trim() !== "") {
                pName.innerText = nameInput.value;
                pName.classList.remove('empty-placeholder');
                pIcon.style.color = "#00ffaa";
                pIcon.style.transform = "scale(1.1) rotate(5deg)";
            } else {
                pName.innerText = "GUEST_USER";
                pName.classList.add('empty-placeholder');
                pIcon.style.color = "rgba(0, 255, 170, 0.1)";
                pIcon.style.transform = "scale(1)";
            }

            // Email Sync
            pEmail.innerText = emailInput.value || "NOT_ASSIGNED";
            pEmail.classList.toggle('empty-placeholder', !emailInput.value);

            // Details Sync
            if (isStudent) {
                const course = document.querySelector('select[name="course"]').value;
                const year = document.querySelector('select[name="year_level"]').value;
                pInfo.innerText = course || "AWAITING_COURSE...";
                pYear.innerText = year || "LEVEL_PENDING";
                pUid.innerText = idInput.value || "XXXX-XXXX";
            } else {
                const dept = document.querySelector('select[name="department"]').value;
                const pos = document.querySelector('#teacherForm input[name="position"]').value;
                pInfo.innerText = dept || "AWAITING_DEPT...";
                pYear.innerText = pos || "POSITION_PENDING";
                pUid.innerText = "AUTH_NODE: TEACHER";
            }
            pInfo.classList.toggle('empty-placeholder', pInfo.innerText.includes('AWAITING'));
            pYear.classList.toggle('empty-placeholder', pYear.innerText.includes('PENDING'));
        }

        // Attach listeners to all relevant inputs and add typing sound effect
        document.querySelectorAll('input, select, textarea').forEach(el => {
            el.addEventListener('input', () => {
                updatePreview();
                if (typingSound) {
                    typingSound.currentTime = 0; // Reset sound position to allow rapid firing
                    typingSound.play().catch(e => {}); // Ignore errors if browser blocks autoplay
                }
            });
        });

        // Start the logic on load
        window.addEventListener('load', () => {
            startBoot();
        });
    </script>

</body>
</html>