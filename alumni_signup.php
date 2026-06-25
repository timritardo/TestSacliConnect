<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Security: Kung walang student_id sa GET, ibalik sa login page.
if (!isset($_GET['student_id'])) {
    header('Location: SacliConnect_LOG_IN.php');
    exit();
}

$error = "";
$alumni_id = $_GET['student_id'];

// Connect to database
require_once __DIR__ . '/config/database.php';

// Fetch Site Theme
$theme_q = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='site_theme'");
$current_theme = ($theme_q && $theme_q->num_rows > 0) ? $theme_q->fetch_assoc()['setting_value'] : 'default';

// --- AUTO-FIX ---
// Ensure the necessary tables and columns exist to prevent fatal errors.
$student_cols = ['year_level' => 'VARCHAR(50) DEFAULT \'\'', 'is_alumni' => 'TINYINT(1) DEFAULT 0'];
foreach ($student_cols as $col => $def) {
    if ($conn->query("SHOW COLUMNS FROM students LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE students ADD COLUMN $col $def");
    }
}

// Create a minimal alumni table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS alumni (id INT AUTO_INCREMENT PRIMARY KEY)");

// Check and add ALL required columns for 'alumni' table to ensure full compatibility
$alumni_cols = [
    'name' => 'VARCHAR(100) NULL', 'course' => 'VARCHAR(100) NULL', 'batch_year' => 'VARCHAR(20) NULL',
    'student_id' => 'VARCHAR(50) NULL', 'profile_pic' => 'VARCHAR(255) NULL', 'birthdate' => 'DATE NULL',
    'status' => 'TEXT NULL', 'location' => 'VARCHAR(255) NULL'
    , 'phone' => 'VARCHAR(20) DEFAULT NULL' // Added phone column
];
foreach ($alumni_cols as $col => $def) {
    if ($conn->query("SHOW COLUMNS FROM alumni LIKE '$col'")->num_rows == 0) {
        $conn->query("ALTER TABLE alumni ADD COLUMN $col $def");
    }
}
// --- END AUTO-FIX ---

// Security Check: Huwag hayaang pumasok sa page kung may account na sa students table
$stmt_check_reg = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
$stmt_check_reg->bind_param("s", $alumni_id);
$stmt_check_reg->execute();
if ($stmt_check_reg->get_result()->num_rows > 0) {
    $_SESSION['alumni_error'] = "this id number is already created a account";
    header('Location: SacliConnect_LOG_IN.php?show=alumni');
    exit();
}

// Check if Alumni ID exists in alumni table
$stmt = $conn->prepare("SELECT name, course, profile_pic, phone FROM alumni WHERE student_id = ?");
$stmt->bind_param("s", $alumni_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $conn->close();
    // Redirect back with an error message for a better user experience
    $_SESSION['alumni_error'] = "Alumni ID not found. Please contact the admin.";
    header('Location: SacliConnect_LOG_IN.php?show=alumni');
    exit();
} else {
    $row = $result->fetch_assoc();
    $alumni_name   = ucwords(strtolower($row['name'] ?? 'Alumni Name Not Found'));
    $alumni_course = $row['course'] ?? 'Course Not Found';
    $alumni_phone = $row['phone'] ?? null; // Get phone number from alumni table

    // Store id, name, course in session, para magamit sa susunod na page
    $_SESSION['alumni_id_to_register']   = $alumni_id;
    $_SESSION['alumni_name_to_register'] = $alumni_name;
    $_SESSION['alumni_course_to_register'] = $alumni_course;
    $alumni_pic = !empty($row['profile_pic']) ? 'uploads/' . $row['profile_pic'] : 'student.png';
}
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!isset($_POST['terms'])) {
        $error = "You must agree to the terms and conditions.";
    } else {
        // Check if the student_id is already registered in the students table
        $stmt_id_check = $conn->prepare("SELECT id FROM students WHERE student_id = ?");
        $stmt_id_check->bind_param("s", $alumni_id);
        $stmt_id_check->execute();
        $res_id = $stmt_id_check->get_result();

        if ($res_id->num_rows > 0) {
            $error = "this id number is already created a account";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM students WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = "An account with this email already exists.";
            } else {
                // All checks passed, create the account
                $insert_stmt = $conn->prepare("INSERT INTO students (student_id, student_name, course, email, password, year_level, is_alumni, phone) VALUES (?, ?, ?, ?, ?, 'Alumni', 1, ?)");
                $insert_stmt->bind_param("ssssss", $alumni_id, $alumni_name, $alumni_course, $email, $password, $alumni_phone);

                if ($insert_stmt->execute()) {
                    // Success! Clear session and redirect to login with success message.
                    unset($_SESSION['alumni_id_to_register']);
                    unset($_SESSION['alumni_name_to_register']);
                    unset($_SESSION['alumni_course_to_register']);

                    $_SESSION['signup_success_msg'] = "Account created successfully! You can now log in with your name and new password.";
                    header('Location: SacliConnect_LOG_IN.php');
                    exit();
                } else {
                    $error = "An error occurred. Please try again.";
                }
                $insert_stmt->close();
            }
            $stmt->close();
        }
        $stmt_id_check->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Sign Up - Sacli Connect</title>
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/2_Login.css">
    <style>
        body {
            /* This rule applies to all body elements */
            opacity: 0;
            animation: fadeInBody 1s ease forwards;
            justify-content: center;
            align-items: center;
            display: flex;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            margin: 0;
            padding: 0;
            /* The default theme background is applied here */
            background: radial-gradient(circle at center, #133a2b 0%, #000502 100%); 
        }
        body:not([class*="theme-"]) { /* This rule applies when no specific theme class is present */
            color: #e4e6eb;
            perspective: 1000px; /* For 3D particle effect */
        }
        .signup-container {
            display: flex;
            width: 100%;
            max-width: none;
            height: 100vh;
            gap: 0;
            align-items: center;
            justify-content: center;
            padding: 0;
            position: relative;
            z-index: 10;
        }
        .form-container {
            width: 50%;
            height: 100%;
            animation: slideInLeft 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
            background: 
                linear-gradient(to right, rgba(8, 28, 20, 0.8) 0%, rgba(8, 28, 20, 0.6) 80%, transparent 100%),
                linear-gradient(rgba(0, 255, 170, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 170, 0.03) 1px, transparent 1px);
            background-size: 100% 100%, 25px 25px, 25px 25px;
            backdrop-filter: blur(8px);
            border: none;
            box-shadow: none;
            padding: 40px 80px;
            border-radius: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative; /* Needed for scan line */
        }
        .form-container::before {
            content: '';
            position: absolute; top: 0; left: 0; width: 100%; height: 3px;
            background: linear-gradient(90deg, transparent, #00ffaa, transparent);
            box-shadow: 0 0 20px #00ffaa;
            animation: scan-down 2.5s cubic-bezier(0.4, 0, 0.2, 1) 0.2s forwards;
        }
        .form-container::after {
            content: '';
            position: absolute; top: 0; left: 0; width: 100%; height: 1px;
            background: #fff;
            box-shadow: 0 0 10px #fff;
            animation: scan-down 1.8s cubic-bezier(0.4, 0, 0.2, 1) 0.8s forwards;
            opacity: 0.7;
        }
        .profile-preview-container {
            width: 50%;
            height: 100%;
            text-align: center;
            color: #fff;
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            position: relative;
            animation: slideInRight 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
            border-radius: 0;
            background: transparent;
            border: none;
            box-shadow: none;
            overflow: hidden;
            padding-bottom: 60px; /* Add space from the bottom */
        }
        .profile-pic-wrapper {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 0;
            margin: 0;
            animation: none;
            background: transparent;
            box-shadow: none;
            z-index: 0;
        }
        /* Tech Grid Overlay on Profile Picture */
        .profile-pic-wrapper::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(rgba(0, 255, 170, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 255, 170, 0.05) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: 2;
            pointer-events: none;
            animation: gridScroll 10s linear infinite, gridPulse 4s ease-in-out infinite alternate;
        }
        .profile-pic-wrapper::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to bottom, rgba(10, 30, 20, 0.2), rgba(0, 5, 2, 0.95));
            z-index: 1;
        }
        .profile-pic-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0;
            border: none;
            opacity: 0.9;
            animation: kenBurns 25s ease-in-out infinite alternate;
        }
        .alumni-welcome-name {
            font-size: 42px;
            font-weight: 900;
            margin: 0 0 10px 0;
            text-transform: uppercase;
            background: linear-gradient(to bottom, #ffffff 0%, #00ffaa 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: #00ffaa;
            letter-spacing: 3px;
            position: relative;
            z-index: 2;
            opacity: 0;
            animation: nameEntrance 1s cubic-bezier(0.2, 0.8, 0.2, 1) 0.5s forwards, namePulse 2s infinite alternate 1.5s;
        }
        .alumni-welcome-course {
            font-size: 1.2rem;
            color: #00ffaa;
            margin: 0;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: rgba(0, 255, 170, 0.1);
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            position: relative;
            z-index: 2;
        }
        .alumni-welcome-message {
            /* Hide the welcome message to de-clutter the bottom area */
            display: none;
        }
        .input[readonly] {
            background: rgba(0, 255, 170, 0.05);
            border-color: rgba(0, 255, 170, 0.3);
            color: #b0fce0;
            font-weight: 600;
            cursor: not-allowed;
        }
        .input[readonly] ~ .user-label {
            transform: translateY(-50%) scale(.8);
            background: #102e22;
            padding: 0 .4em;
            color: #fff;
        }
        @keyframes fadeInBody { to { opacity: 1; } }
        @keyframes slideInLeft { from { opacity: 0; transform: translateX(-50px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes slideInRight { from { opacity: 0; transform: translateX(50px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes pulseGlow { from { opacity: 0.5; transform: translate(-50%, -50%) scale(0.8); } to { opacity: 1; transform: translate(-50%, -50%) scale(1.2); } }
        @keyframes floatProfile { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }
        @keyframes spinGlow { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes spinRing { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes nameEntrance { from { opacity: 0; transform: translateY(30px) scale(0.9); filter: blur(10px); } to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); } }
        @keyframes namePulse { from { filter: drop-shadow(0 0 5px rgba(0, 255, 170, 0.3)); } to { filter: drop-shadow(0 0 20px rgba(0, 255, 170, 0.8)); transform: scale(1.02); } }        
        @keyframes kenBurns { 0% { transform: scale(1.1) translateX(-2%) translateY(1%); } 100% { transform: scale(1.15) translateX(2%) translateY(-1%); } }
        @keyframes gridScroll { 0% { background-position: 0 0; } 100% { background-position: 50px 50px; } }
        @keyframes gridPulse { 0%, 100% { opacity: 0.05; } 50% { opacity: 0.15; } }
        @keyframes particleFlow { /* More realistic 3D flow */
            from { transform: translateZ(-50px) translateY(var(--y-start)) translateX(var(--x-start)); opacity: 0; }
            50% { opacity: 1; }
            to { transform: translateZ(400px) translateY(var(--y-end)) translateX(var(--x-end)); opacity: 0; }
        }
        @keyframes hologram-reveal {
            from {
                opacity: 0;
                transform: translateX(-30px) scale(0.95);
                filter: blur(5px) brightness(3);
                clip-path: polygon(0 0, 0 0, 0 100%, 0 100%);
            }
            60% { filter: blur(1px) brightness(1.5); }
            to {
                opacity: 1;
                transform: translateX(0) scale(1);
                filter: blur(0) brightness(1);
                clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);
            }
        }
        @keyframes glowPulse { 0% { box-shadow: 0 0 15px rgba(0, 255, 170, 0.3); } 50% { box-shadow: 0 0 25px rgba(0, 255, 170, 0.6); } 100% { box-shadow: 0 0 15px rgba(0, 255, 170, 0.3); } }

        /* Background Particles */
        .particles { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; overflow: hidden; transform-style: preserve-3d; }
        .particle { position: absolute; background: #fff; border-radius: 50%; box-shadow: 0 0 10px #00ffaa, 0 0 20px #00ffaa; animation-name: particleFlow; animation-timing-function: linear; animation-iteration-count: infinite; }

        /* Enhanced Form Styles */
        @keyframes scan-down { 0% { top: 0; opacity: 0.8; } 100% { top: 100%; opacity: 0; } }
        .form-container > * {
            animation: hologram-reveal 0.7s cubic-bezier(0.25, 0.46, 0.45, 0.94) 0.5s backwards;
        }
        .form-container .logo { animation-delay: 0.6s; }
        .form-container p { animation-delay: 0.7s; }
        .form-container .error { animation-delay: 0.8s; }
        .form-container form > *:nth-child(1) { animation-delay: 0.9s; }
        .form-container form > *:nth-child(2) { animation-delay: 1.0s; }
        .form-container form > *:nth-child(3) { animation-delay: 1.1s; }
        .form-container form > *:nth-child(4) { animation-delay: 1.2s; }
        .form-container form > *:nth-child(5) { animation-delay: 1.3s; }
        .form-container form > *:nth-child(6) { animation-delay: 1.4s; }
        .form-container form > *:nth-child(7) { animation-delay: 1.5s; }

        .logo strong { font-size: 32px; letter-spacing: 2px; text-transform: uppercase; background: linear-gradient(90deg, #fff, #00ffaa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0 0 20px rgba(0, 255, 170, 0.3); }
        .logo { margin-bottom: 30px; animation: logoFloat 3s ease-in-out infinite; }
        @keyframes logoFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); text-shadow: 0 0 30px rgba(0, 255, 170, 0.6); } }

        .input-group { margin-bottom: 25px; position: relative; }
        .input {
            border-radius: 8px;
            border: 1px solid rgba(0, 255, 170, 0.2);
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(5px);
            color: #fff;
            padding: 15px 20px;
            transition: 0.3s;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.2);
        }
        .input-group::after {
            content: ''; position: absolute; bottom: 0; left: 50%; width: 0; height: 2px;
            background: #00ffaa; transition: 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            transform: translateX(-50%); box-shadow: 0 0 10px #00ffaa;
        }
        .input-group:focus-within::after { width: 100%; }
        
        @keyframes inputGlow { from { box-shadow: 0 0 15px rgba(0, 255, 170, 0.2); } to { box-shadow: 0 0 25px rgba(0, 255, 170, 0.5); } }
        .input:focus {
            border-color: #00ffaa;
            background: rgba(0, 255, 170, 0.05);
            animation: inputGlow 0.8s infinite alternate;
        }
        .user-label { left: 20px; color: #aaa; }
        .input:focus ~ .user-label, .input:valid ~ .user-label, .input[readonly] ~ .user-label {
            background-color: #102e22;
            padding: 0 5px;
        }
        
        .file-upload-group { margin-bottom: 20px; }
        .file-label { display: block; color: #b0fce0; font-size: 0.85em; margin-bottom: 8px; margin-left: 10px; }
        input[type="file"] {
            width: 100%;
            padding: 10px;
            background: rgba(0, 255, 170, 0.05);
            border: 1px dashed rgba(0, 255, 170, 0.4);
            border-radius: 15px;
            color: #ccc;
            font-size: 0.9em;
            cursor: pointer;
        }
        input[type="file"]::file-selector-button {
            background: #00ffaa; border: none; color: #0a1f16;
            padding: 5px 12px; border-radius: 10px; margin-right: 10px;
            font-weight: bold; cursor: pointer; transition: 0.2s;
        }
        input[type="file"]::file-selector-button:hover { background: #fff; }

        .btn-group { display: flex; gap: 15px; margin-top: 20px; }
        .btn-primary, .btn-secondary { flex: 1; padding: 15px; border-radius: 8px; font-weight: 800; cursor: pointer; transition: 0.3s; text-transform: uppercase; letter-spacing: 2px; text-align: center; position: relative; overflow: hidden; }
        .btn-primary { background: linear-gradient(90deg, #00ffaa, #00cc88); color: #0a1f16; border: none; box-shadow: 0 0 20px rgba(0, 255, 170, 0.4); }
        .btn-primary:hover { transform: translateY(-3px) scale(1.02); box-shadow: 0 0 40px rgba(0, 255, 170, 0.8); background: #fff; color: #00ffaa; }
        .btn-secondary { background: transparent; border: 2px solid #00ffaa; color: #00ffaa; text-decoration: none; display: flex; align-items: center; justify-content: center;}
        .btn-secondary:hover { background: rgba(0, 255, 170, 0.1); color: #fff; transform: translateY(-3px); }
        
        /* Voice AI Button */
        .voice-toggle-btn {
            position: absolute; top: 30px; right: 30px;
            background: rgba(0, 255, 170, 0.1); border: 1px solid #00ffaa; color: #00ffaa;
            width: 45px; height: 45px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.3s; z-index: 20; backdrop-filter: blur(5px);
        }
        .voice-toggle-btn:hover { background: rgba(0, 255, 170, 0.2); box-shadow: 0 0 15px #00ffaa; transform: scale(1.1); }
        .voice-toggle-btn.active { background: #00ffaa; color: #0a1f16; box-shadow: 0 0 20px #00ffaa, inset 0 0 10px rgba(255,255,255,0.5); animation: pulseBtn 2s infinite; }
        
        .voice-waves {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 100%; height: 100%; border-radius: 50%; border: 2px solid #00ffaa;
            opacity: 0; pointer-events: none;
        }
        .voice-toggle-btn.active .voice-waves { animation: voiceRipple 1.5s infinite; }
        
        @keyframes voiceRipple {
            0% { width: 100%; height: 100%; opacity: 0.8; border-width: 2px; }
            100% { width: 250%; height: 250%; opacity: 0; border-width: 0px; }
        }
        @keyframes pulseBtn {
            0% { box-shadow: 0 0 0 0 rgba(0, 255, 170, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(0, 255, 170, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 255, 170, 0); }
        }

        @media (max-width: 900px) {
            .signup-container { flex-direction: column-reverse; gap: 0; padding: 0; height: auto; }
            body { height: auto; padding: 0; overflow-y: auto; }
            .form-container { width: 100%; padding: 40px 20px; border-right: none; }
            .profile-preview-container { width: 100%; height: 400px; }
            .alumni-welcome-name { font-size: 2rem; }
            .profile-pic-wrapper { position: absolute; width: 100%; height: 100%; }
        }

        /* --- THEME STYLES --- */
        body.theme-halloween { background: #050202 !important; }
        body.theme-halloween::before { content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, rgba(0,0,0,0.6), rgba(20,0,0,0.9)), repeating-linear-gradient(45deg, rgba(20,0,0,0.1) 0px, rgba(20,0,0,0.1) 2px, transparent 2px, transparent 10px), radial-gradient(circle at 50% 50%, rgba(40,0,0,0.2), transparent 80%); background-size: cover; z-index: -2; pointer-events: none; }
        body.theme-halloween::after { content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at 50% 0%, rgba(255, 0, 0, 0.1), transparent 70%); opacity: 0.6; animation: lightningFlash 10s infinite; pointer-events: none; z-index: -2; mix-blend-mode: overlay; }
        
        body.theme-christmas { background: linear-gradient(to bottom, #0f2027, #203a43, #2c5364) !important; }
        body.theme-christmas::before { content: ''; position: fixed; top: -50px; left: 0; width: 10px; height: 10px; border-radius: 50%; background: transparent; box-shadow: 5vw 10vh 2px 2px #fff, 15vw 25vh 1px 3px #fff, 25vw 5vh 3px 2px #fff, 35vw 15vh 1px 1px #fff, 45vw 10vh 2px 2px #fff, 55vw 25vh 1px 3px #fff, 65vw 5vh 3px 2px #fff, 75vw 15vh 1px 1px #fff, 85vw 10vh 2px 2px #fff, 95vw 25vh 1px 3px #fff, 10vw 40vh 2px 2px #fff, 30vw 60vh 1px 3px #fff, 50vw 50vh 3px 2px #fff, 70vw 70vh 1px 1px #fff, 90vw 80vh 2px 2px #fff; opacity: 0.8; pointer-events: none; animation: snow 10s linear infinite; z-index: -2; }
        
        body.theme-summer { background: #2980b9 !important; }
        body.theme-summer::before { content:''; position:fixed; top: -100px; right: -100px; width:600px; height:600px; background:radial-gradient(circle, #ffcc00 0%, transparent 60%); opacity:0.6; animation:pulseGlow 4s infinite; z-index: -2; }
        
        body.theme-new_year { background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%) !important; }
    </style>
</head>
<body>
<body class="theme-<?php echo htmlspecialchars($current_theme); ?>">

    <div class="particles" id="particles"></div>

    <div class="signup-container">
        <!-- Left Side: Form -->
        <div class="form-container">
            <button type="button" class="voice-toggle-btn" id="voiceToggle" onclick="toggleVoiceAI()" title="Toggle Voice Assistant">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>
                <div class="voice-waves"></div>
            </button>

            <div class="logo"><strong>ALUMNI SIGN UP</strong></div>
            <p style="text-align: center; color: #509b83; margin-top:-15px; margin-bottom:15px;">Complete the form to create your account.</p>
            
            <?php if(!empty($error)) echo "<div class='error'>$error</div>"; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="input-group">
                    <input type="text" name="alumni_name" class="input" value="<?php echo htmlspecialchars($alumni_name); ?>" readonly>
                    <label class="user-label">Alumni Name</label>
                </div>

                <div class="file-upload-group">
                    <label class="file-label">Upload ACTB Document (Optional)</label>
                    <input type="file" name="actb_document" accept=".pdf,.jpg,.jpeg,.png">
                </div>

                <div class="input-group">
                    <input required type="email" name="email" class="input" autocomplete="off">
                    <label class="user-label">Email Address</label>
                </div>
                <div class="input-group">
                    <input required type="password" name="password" class="input" autocomplete="off">
                    <label class="user-label">New Password</label>
                </div>
                <div class="input-group">
                    <input required type="password" name="confirm_password" class="input" autocomplete="off">
                    <label class="user-label">Confirm Password</label>
                </div>
                <div style="margin-bottom: 20px; color: #fdfdfd; font-size: 0.9em; display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="terms" required id="terms" style="width: auto; margin: 0;">
                    <label for="terms">I agree to the terms and conditions</label>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn-secondary" onclick="window.location.href='SacliConnect_LOG_IN.php'">Cancel</button>
                    <button type="submit" class="btn-primary">Create Account</button>
                </div>
            </form>
        </div>

        <!-- Right Side: Profile Preview -->
        <div class="profile-preview-container">
            <div class="profile-pic-wrapper">
                <img src="<?php echo htmlspecialchars($alumni_pic); ?>" alt="Alumni Picture">
            </div>
            <h2 class="alumni-welcome-name"><?php echo htmlspecialchars($alumni_name); ?></h2>
            <p class="alumni-welcome-course"><?php echo htmlspecialchars($alumni_course); ?></p>
        </div>
    </div>

    <script>
        // Generate floating particles
        const particlesContainer = document.getElementById('particles');
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

        // Voice AI Logic
        let voiceEnabled = false;
        const synth = window.speechSynthesis;
        let voices = [];
        const alumniNameJS = <?php echo json_encode($alumni_name); ?>;
        const alumniCourseJS = <?php echo json_encode($alumni_course); ?>;

        // Speech Recognition Setup (Listening Capability)
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        let recognition = null;

        if (SpeechRecognition) {
            recognition = new SpeechRecognition();
            recognition.continuous = false; // Stop after one sentence to process
            recognition.lang = 'en-US';
            recognition.interimResults = false;

            recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript.toLowerCase();
                handleUserQuery(transcript);
            };

            recognition.onend = () => {
                // Automatically restart listening if AI is still enabled and not speaking
                if (voiceEnabled && !synth.speaking) {
                    try { recognition.start(); } catch(e) {}
                }
            };
        }

        function loadVoices() { voices = synth.getVoices(); }
        if (speechSynthesis.onvoiceschanged !== undefined) { speechSynthesis.onvoiceschanged = loadVoices; }

        function speak(text) {
            if (!voiceEnabled) return;
            if (synth.speaking) synth.cancel();
            
            // Stop listening while the AI is speaking to prevent it from hearing itself
            if (recognition) try { recognition.stop(); } catch(e) {}

            const utterThis = new SpeechSynthesisUtterance(text);
            // J.A.R.V.I.S. Style: Prefer British Male Voice
            const jarvisVoice = voices.find(v => v.name.includes('Google UK English Male') || v.name.includes('Daniel') || (v.lang === 'en-GB' && v.name.includes('Male'))) || voices.find(v => v.lang === 'en-GB') || voices.find(v => v.lang === 'en-US');
            if (jarvisVoice) utterThis.voice = jarvisVoice;
            utterThis.rate = 0.9; utterThis.pitch = 0.85; // Lower pitch, composed rate
            
            // Resume listening after speaking
            utterThis.onend = () => {
                if (voiceEnabled && recognition) {
                    try { recognition.start(); } catch(e) {}
                }
            };

            synth.speak(utterThis);
        }

        function handleUserQuery(text) {
            // Helper for random responses
            const sayRandom = (arr) => speak(arr[Math.floor(Math.random() * arr.length)]);
            
            let originalText = text;
            text = text.toLowerCase().trim();
            
    
            // This section uses keyword matching to provide more flexible answers.

            // Topic: Definition of SacliConnect
            if ((text.includes('what is') || text.includes("what's") || text.includes('about') || text.includes('explain')) && (text.includes('sacliconnect') || text.includes('system'))) {
                speak("SacliConnect is the official communication and management hub of Saint Anne College Lucena, designed to unite students, faculty, and alumni. It features a social feed, direct messaging, and virtual classrooms called SacliRoom.");
                return;
            }
            // Topic: Purpose
            if (text.includes('purpose') || text.includes('goal') || text.includes('objective') || (text.includes('what') && text.includes('for'))) {
                speak("The main goal of SacliConnect is to create a single, integrated web-based platform for the SACLI community, to enhance communication, and to streamline academic management.");
                return;
            }
            // Topic: Features
            if (text.includes('feature') || (text.includes('what can') && text.includes('do'))) {
                speak("Core features include a central social feed for announcements, real-time one-on-one and group chat, and SacliRoom, which is an integrated Learning Management System for virtual classes and assignments.");
                return;
            }
            // Topic: Technology used
            if (text.includes('technology') || text.includes('made of') || text.includes('programming language') || text.includes('built with')) {
                speak("The system is built using a standard web stack. The front-end uses HTML, CSS, and JavaScript. The back-end is powered by PHP, and all data is stored in a MySQL database. The entire project is developed on an XAMPP server environment.");
                return;
            }
            // Topic: Rules / Prohibited actions
            if (text.includes('rule') || text.includes('prohibited') || text.includes('bawal') || (text.includes('what') && text.includes('not allowed'))) {
                speak("Prohibited actions include cyberbullying, harassment, sharing illegal content, and account sharing. Attempting to hack the system is also strictly forbidden and will result in disciplinary action.");
                return;
            }
            // Topic: Consequences
            if (text.includes('consequence') || text.includes('penalty') || text.includes('offense') || (text.includes('what happens if') && text.includes('break the rules'))) {
                speak("Violations have consequences. A first offense results in a warning. A second offense leads to temporary account suspension. Serious offenses can lead to a permanent ban and will be reported to the Office of Student Affairs.");
                return;
            }
            // Topic: Authorized Users
            if (text.includes('who can use') || text.includes('authorized user')) {
                speak("The system is for authorized users only, which includes bonafide Students, Faculty Members, Admin Staff, and of course, verified Alumni like yourself.");
                return;
            }
            // Topic: System Architecture
            if (text.includes('architecture')) {
                speak("SacliConnect uses a Client-Server Architecture and is currently deployed in a local environment, making it an intranet-based system for the school.");
                return;
            }
            // Topic: Development Methodology
            if (text.includes('methodology') || text.includes('model')) {
                speak("The system was developed using the Iterative Waterfall Model. This combines a structured, phase-by-phase approach with the flexibility to refine features based on testing and feedback.");
                return;
            }


            // --- GREETINGS & STATUS ---
            if (/^(hello|hi|hey|greetings|good morning|good afternoon|good evening)/.test(text)) {
                sayRandom([
                    "At your service. Ready to assist.",
                    "Greetings. Systems are online.",
                    "Hello. How may I help you with the protocol?"
                ]);
                return;
            } 
            if (text.includes('how are you')) {
                speak("All systems functional. Thank you for asking.");
                return;
            }

            // --- TIME & DATE QUERIES ---
            if (text.includes('what time') || text.includes('current time')) {
                speak("It is currently " + new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}));
                return;
            }
            if (text.includes('what date') || text.includes('what day') || text.includes('today')) {
                speak("Today is " + new Date().toLocaleDateString(undefined, {weekday:'long', month:'long', day:'numeric', year:'numeric'}));
                return;
            }

            // --- MATH / CALCULATOR ---
            // Example: "What is 5 plus 5" or "Calculate 10 times 2"
            if (text.match(/(\d+)\s*(\+|\-|\*|\/|plus|minus|times|divided by|x)\s*(\d+)/)) {
                try {
                    let sanitized = text.replace(/plus/g, '+').replace(/minus/g, '-').replace(/times|x/g, '*').replace(/divided by/g, '/');
                    let match = sanitized.match(/(\d+)\s*([\+\-\*\/])\s*(\d+)/);
                    if(match) {
                        let n1 = parseFloat(match[1]);
                        let op = match[2];
                        let n2 = parseFloat(match[3]);
                        let res = 0;
                        if(op === '+') res = n1 + n2;
                        else if(op === '-') res = n1 - n2;
                        else if(op === '*') res = n1 * n2;
                        else if(op === '/') res = (n2 !== 0 ? n1 / n2 : 'undefined');
                        
                        speak("The answer is " + res);
                        return;
                    }
                } catch(e){}
            }

            // --- GENERAL KNOWLEDGE / CHIT-CHAT ---
            if (text.includes('who created you') || text.includes('who made you') || text.includes('developer')) {
                speak("I was developed by Justin Ritardo as part of the Sacli Connect system.");
                return;
            }
            if (text.includes('your name') || text.includes('who are you')) {
                speak("I am tim, your personal alumni registration assistant.");
                return;
            }
            if (text.includes('joke')) {
                sayRandom([
                    "Why do programmers prefer dark mode? Because light attracts bugs.",
                    "I told my computer I needed a break, and now it won't stop sending me Kit-Kat ads.",
                    "Why was the computer cold? It left its Windows open."
                ]);
                return;
            }
            if (text.includes('meaning of life')) {
                speak("42. According to standard galactic data.");
                return;
            }
            if (text.includes('school') || text.includes('sacli')) {
                speak("St. Anne College Lucena, Inc. is an institution of excellence. I am here to help you reconnect.");
                return;
            }

            // --- FORM COMMANDS (Existing) ---
            if (text.includes('my email is') || text.includes('set email to')) {
                // Convert "at" to "@" and "dot" to "." and remove spaces
                let email = text.replace('my email is', '').replace('set email to', '').trim();
                email = email.replace(/ at /g, '@').replace(/ dot /g, '.').replace(/\s/g, '');
                
                let emailInput = document.querySelector('input[name="email"]');
                if(emailInput) {
                    emailInput.value = email;
                    speak("I have updated your email address to " + email);
                    return;
                }
            }
            if (text.includes('name') || text.includes('who am i')) {
                speak("You are currently identified as " + alumniNameJS + ". If this isn't you, please contact the admin.");
                return;
            } 
            if (text.includes('course') || text.includes('program')) {
                speak("According to our records, you completed " + alumniCourseJS + ".");
                return;
            } 
            if (text.includes('password')) {
                speak("For your password, try to use a mix of letters and numbers. I am focusing the password field now.");
                document.querySelector('input[name="password"]').focus();
                return;
            } 
            if (text.includes('email')) {
                speak("We need your active email address. I am focusing the email field.");
                document.querySelector('input[name="email"]').focus();
                return;
            } 
            if (text.includes('document') || text.includes('upload') || text.includes('actb')) {
                speak("Uploading the ACTB document is optional, but it really helps speed up the verification of your alumni status.");
                return;
            } 
            if (text.includes('thank')) {
                sayRandom(["My pleasure.", "As you wish.", "Always happy to help."]);
                return;
            } 
            if (text.includes('next') || text.includes('what to do')) {
                speak("Please proceed with the data entry. I suggest starting with your email address.");
                return;
            }
            if (text.includes('bye') || text.includes('cancel') || text.includes('stop')) {
                speak("Powering down interface. Goodbye.");
                setTimeout(toggleVoiceAI, 2000);
                return;
            }
            
            // --- FALLBACK (Simulate Thinking) ---
            if(text.length > 0) {
                if (text.includes('what') || text.includes('where') || text.includes('why') || text.includes('how')) {
                    speak("Searching database for '" + originalText + "'... No local records found. My knowledge is currently limited to the Sacli Connect protocol and basic functions.");
                } else {
                    speak("I heard: " + originalText + ". I am not sure how to respond to that command.");
                }
            }
        }

        function toggleVoiceAI() {
            voiceEnabled = !voiceEnabled;
            const btn = document.getElementById('voiceToggle');
            if (voiceEnabled) {
                btn.classList.add('active');
                speak("I'm your personal assistant. At your service. Ready to assist.");
            } else {
                btn.classList.remove('active');
                synth.cancel();
                if (recognition) try { recognition.stop(); } catch(e) {}
            }
        }

        // Add focus listeners for guidance
        document.querySelector('input[name="alumni_name"]').addEventListener('focus', () => speak("This is your Alumni Name, retrieved from our records. It cannot be changed."));
        document.querySelector('input[name="actb_document"]').addEventListener('focus', () => speak("If you have an ACTB document, you can upload it here. This is optional."));
        document.querySelector('input[name="email"]').addEventListener('focus', () => speak("Please enter your active email address. We will use this for account recovery."));
        document.querySelector('input[name="password"]').addEventListener('focus', () => speak("Create a strong password for your account."));
        document.querySelector('input[name="confirm_password"]').addEventListener('focus', () => speak("Please re-type your password to confirm it matches."));
        document.querySelector('input[name="terms"]').addEventListener('focus', () => speak("Please read and accept the terms and conditions to proceed."));

        // Automatically start the voice assistant when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            // A short delay helps ensure speech synthesis voices are loaded
            setTimeout(toggleVoiceAI, 1000);
        });
    </script>
</body>
</html>