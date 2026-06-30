<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Function to record login history
 */
function recordLoginHistory(mysqli $conn, string $student_id, ?string $provided_location = null) {
    $ua = $_SERVER['HTTP_USER_AGENT'];
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $location = !empty($provided_location) ? $provided_location : "Unknown";
    if($location == "Unknown") {
    if($ip == '::1' || $ip == '127.0.0.1') {
        $location = "Localhost";
    } else {
        $geo = @json_decode(file_get_contents("http://ip-api.com/json/".$ip));
        if($geo && $geo->status == 'success') {
            $location = $geo->city . ", " . $geo->country;
        }
    }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS login_history ( id INT AUTO_INCREMENT PRIMARY KEY, student_id VARCHAR(50), device_info TEXT, ip_address VARCHAR(45), login_time DATETIME DEFAULT CURRENT_TIMESTAMP, location VARCHAR(255) DEFAULT 'Unknown' )");

    $stmt = $conn->prepare("INSERT INTO login_history (student_id, device_info, ip_address, location) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $student_id, $ua, $ip, $location);
    $stmt->execute();
    $stmt->close();

    $limit = 9;
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM login_history WHERE student_id = ?");
    $count_stmt->bind_param("s", $student_id);
    $count_stmt->execute();
    $count = $count_stmt->get_result()->fetch_assoc()['count'];
    $count_stmt->close();

    if ($count > $limit) {
        $num_to_delete = $count - $limit;
        $delete_stmt = $conn->prepare("DELETE FROM login_history WHERE student_id = ? ORDER BY login_time ASC LIMIT ?");
        $delete_stmt->bind_param("si", $student_id, $num_to_delete);
        $delete_stmt->execute();
        $delete_stmt->close();
    }
}
/**
 * Simple email masking for privacy
 */
function maskEmail(string $email) {
    if(empty($email)) return "no-email@set.com";
    $parts = explode("@", $email);
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if($len > 2) {
        $name = substr($name, 0, 2) . str_repeat("*", $len - 2);
    } else {
        $name = str_repeat("*", $len);
    }
    return $name . "@" . $domain; 
}

/**
 * Function to send login alert email with Yes/No buttons
 */
function sendLoginAlert(mysqli $conn, string $user_id, string $name, string $email, string $type) {
    if (empty($email)) return;

    // Check if system is currently in blackout mode via saclisacli.php toggle
    $blackout_res = $conn->query("SELECT setting_value FROM site_settings WHERE setting_key='blackout_mode'");
    $is_blackout = ($blackout_res && $blackout_res->num_rows > 0 && $blackout_res->fetch_assoc()['setting_value'] == '1');

    $token = bin2hex(random_bytes(16));
    $table = ($type === 'teacher') ? 'teachers' : 'students';
    $id_col = ($type === 'teacher') ? 'id' : 'student_id';
    $real_id = ($type === 'teacher') ? str_replace('T-', '', $user_id) : $user_id;

    $conn->query("UPDATE $table SET logout_token = '$token' WHERE $id_col = '$real_id'");

    $no_link  = "https://" . $_SERVER['HTTP_HOST'] . "/remote_logout.php?id=" . urlencode($user_id) . "&token=" . $token;
    $yes_link = "https://" . $_SERVER['HTTP_HOST'] . "/SacliConnect.php";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sacliconnect20@gmail.com';
        $mail->Password   = 'umrrmsyujepjopbo';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 10; // fail fast — don't block login redirect
        $mail->SMTPOptions = array('ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true));
        $mail->setFrom('sacliconnect20@gmail.com', 'SacliConnect Security');
        $mail->addAddress($email);
        $mail->isHTML(true);

        if ($is_blackout) {
            $mail->Subject = 'System Status: Offline Protocol Active';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 30px; border: 2px solid #ff4757; border-radius: 15px; background-color: #0a1f16; color: #fff; text-align: center; max-width: 600px; margin: auto;'>
                    <h2 style='color: #ff4757; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 20px;'>SYSTEM_OFFLINE_DETECTED</h2>
                    <p style='font-size: 16px; color: #b0fce0;'>Hello <b>$name</b>,</p>
                    <p style='font-size: 15px; line-height: 1.6; margin-bottom: 25px;'>
                        Your authentication attempt was recorded, however, the system is currently in <b>TOTAL_BLACKOUT</b> mode. 
                        Public access nodes have been temporarily de-energized for system maintenance or security updates.
                    </p>
                    <div style='margin: 25px 0; padding: 15px; background: rgba(255, 71, 87, 0.1); border: 1px dashed #ff4757; border-radius: 10px;'>
                        <div style='font-size: 11px; color: #ff4757; margin-bottom: 5px; font-family: monospace;'>STATUS_REPORT:</div>
                        <div style='font-size: 24px; font-weight: 900; color: #ff4757; letter-spacing: 5px;'>OFFLINE</div>
                    </div>
                    <p style='font-size: 13px; color: #888;'>Please wait for the administrator to restore connection. Access will remain restricted until the blackout protocol is lifted.</p>
                </div>";
        } else {
            $mail->Subject = 'Security Alert: Your Account is Online';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 25px; border: 2px solid #00ffaa; border-radius: 15px; background-color: #0a1f16; color: #fff; text-align: center;'>
                    <h2 style='color: #00ffaa; text-transform: uppercase; letter-spacing: 2px;'>Login Notification</h2>
                    <p style='font-size: 16px;'>Hello <b>$name</b>, your account has just been accessed and is now online.</p>
                    <p style='margin: 20px 0; font-weight: bold; color: #b0fce0;'>Is this you?</p>
                    <div style='margin-top: 25px;'>
                        <a href='$yes_link' style='display: inline-block; padding: 12px 30px; background: #00ffaa; color: #0a1f16; text-decoration: none; border-radius: 5px; font-weight: 900; margin: 10px;'>YES, IT'S ME</a>
                        <a href='$no_link' style='display: inline-block; padding: 12px 30px; background: #ff4757; color: #fff; text-decoration: none; border-radius: 5px; font-weight: 900; margin: 10px;'>NO, LOGOUT</a>
                    </div>
                    <p style='margin-top: 25px; color: #888; font-size: 12px;'>If you click 'NO', the active session will be terminated immediately.</p>
                </div>";
        }
        $mail->send();
    } catch (Exception $e) {}
}

/**
 * Function to send a password reset link when account is locked.
 */
function sendPasswordResetLink(mysqli $conn, string $user_id, string $name, string $email, string $type) {
    if (empty($email)) return;
    $token = bin2hex(random_bytes(16));
    $table = ($type === 'teacher') ? 'teachers' : 'students';
    $id_col = ($type === 'teacher') ? 'id' : 'student_id';
    $real_id = ($type === 'teacher') ? str_replace('T-', '', $user_id) : $user_id;

    // Check if a token already exists to prevent invalidating the current security link
    $check = $conn->query("SELECT logout_token FROM $table WHERE $id_col = '$real_id'");
    $row = $check->fetch_assoc();
    
    if (!empty($row['logout_token'])) {
        $token = $row['logout_token']; // Use the existing token
    } else {
        $conn->query("UPDATE $table SET logout_token = '$token' WHERE $id_col = '$real_id'");
    }

    $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/remote_logout.php?id=" . urlencode($user_id) . "&token=" . $token;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sacliconnect20@gmail.com';
        $mail->Password   = 'umrrmsyujepjopbo';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 10; // fail fast
        $mail->SMTPOptions = array('ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true));
        $mail->setFrom('sacliconnect20@gmail.com', 'SacliConnect Security');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Security Alert: Action Required - Password Reset';
        $mail->Body    = " 
            <div style='font-family: Arial, sans-serif; padding: 25px; border: 2px solid #ff4757; border-radius: 15px; background-color: #0a1f16; color: #fff; text-align: center; max-width: 600px; margin: auto;'> 
                <h2 style='color: #ff4757; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 15px;'>SYSTEM OFFLINE: SECURITY PROTOCOL ACTIVE</h2> 
                <p style='font-size: 16px; color: #b0fce0;'>Hello <b>$name</b>,</p> 
                <p style='font-size: 16px; line-height: 1.5;'> 
                    Your account is currently inaccessible as the system is undergoing maintenance or is in a security lockdown state. 
                    This is a temporary measure to ensure data integrity and user safety. 
                </p> 
                <p style='margin: 25px 0; font-weight: bold; color: #00ffaa; font-size: 18px;'> 
                    STATUS: <span style='color: #ff4757;'>OFFLINE</span> // ACCESS: <span style='color: #ff4757;'>DENIED</span> 
                </p> 
                <p style='font-size: 14px; color: #ccc; margin-bottom: 30px;'> 
                    Please try again later. If you believe this is an error, contact system administration. 
                </p> 
                <div style='margin-top: 20px;'> 
                    <a href='#' style='display: inline-block; padding: 12px 30px; background: #00ffaa; color: #0a1f16; text-decoration: none; border-radius: 5px; font-weight: 900; margin: 10px;'>VISIT SACLICONNECT</a> 
                </div>
                <p style='margin-top: 25px; color: #888; font-size: 12px;'>This link is valid for a limited time. If you did not attempt to log in, please ignore this email.</p>
            </div>";
        $mail->send();
    } catch (Exception $e) {}
}

// Redirect if already logged in
if(isset($_SESSION['student_name'])){
    header("Location: intronext.php");
    exit();
}
$show_admin = isset($_GET['show']) && $_GET['show'] === 'admin';

// Connect to database
require_once __DIR__ . '/config/database.php';

// AUTO-FIX for Security Logout
try {
    $sec_cols = ['force_logout' => 'TINYINT(1) DEFAULT 0', 'logout_token' => 'VARCHAR(255) NULL'];
    foreach ($sec_cols as $col => $def) {
        $check_students = $conn->query("SHOW COLUMNS FROM students LIKE '$col'");
        if ($check_students && $check_students->num_rows == 0) $conn->query("ALTER TABLE students ADD COLUMN $col $def");
        
        $check_teachers = $conn->query("SHOW COLUMNS FROM teachers LIKE '$col'");
        if ($check_teachers && $check_teachers->num_rows == 0) $conn->query("ALTER TABLE teachers ADD COLUMN $col $def");
    }
} catch (mysqli_sql_exception $e) {
    // Catch corruption or temporary table errors to prevent page crash
}
// AUTO-FIX: Ensure user_active_sessions table exists for multi-device logout
$conn->query("CREATE TABLE IF NOT EXISTS user_active_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    session_token VARCHAR(255),
    ip_address VARCHAR(45),
    device_info TEXT,
    expires_at DATETIME
)");

// Fetch Site Theme and Video
$settings = [];
// Ensure site_settings table exists before querying it
$conn->query("CREATE TABLE IF NOT EXISTS site_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT
)");
$settings_res = $conn->query("SELECT setting_key, setting_value FROM site_settings");
if($settings_res) while($row = $settings_res->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value'];

$current_theme = $settings['site_theme'] ?? 'default';

// --- VIDEO SOURCE ---
// Priority: seasonal override > DB default > local fallback
$video_src = $settings['login_video'] ?? '';

// Check for seasonal video override (e.g., halloween_video, christmas_video)
$seasonal_video_key = $current_theme . '_video';
if (!empty($settings[$seasonal_video_key])) {
    $video_src = $settings[$seasonal_video_key];
}

// If still empty or a bare filename (no URL, no path), resolve it
if (empty($video_src)) {
    // No video in DB yet — use local file as last resort
    $video_src = 'assets/video/ST. ANNE COLLEGE LUCENA, INC. VIDEO TEASER.mp4';
} elseif (strpos($video_src, 'http') === false && strpos($video_src, 'assets/') === false) {
    // Bare filename stored in DB before Supabase was set up
    $video_src = 'assets/video/' . $video_src;
}
// If $video_src is already a full https:// URL (Supabase), use it as-is.

// --- BACKGROUND LOGOS ---
// Read from site_settings so admin can update them; fall back to local defaults
$bg_logo1_src = !empty($settings['login_bg_logo1']) ? $settings['login_bg_logo1'] : 'assets/images/ST40.png';
$bg_logo2_src = !empty($settings['login_bg_logo'])  ? $settings['login_bg_logo']  : 'assets/images/St.Anne_logo.png';

$error = "";
$show_otp_form = false;
$show_wait_ui = false;
$show_reset_form = false;
$show_admin_otp = isset($_GET['admin_otp']) && $_GET['admin_otp'] == '1';
$admin_mfa_email = "";
$mfa_email = "";

// --- DETECT PASSWORD RESET TOKEN ---
if (isset($_GET['reset_token'])) {
    $token = $_GET['reset_token'];
    // Subukang hanapin sa students table
    $stmt = $conn->prepare("SELECT student_id, 'student' as type FROM students WHERE otp_code = ? AND otp_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $show_reset_form = true;
        $_SESSION['resetting_sid'] = $row['student_id'];
        $_SESSION['resetting_type'] = 'student';
    } else {
        // Kung wala sa students, subukan sa teachers table
        $stmt_t = $conn->prepare("SELECT id, 'teacher' as type FROM teachers WHERE otp_code = ? AND otp_expiry > NOW()");
        $stmt_t->bind_param("s", $token);
        $stmt_t->execute();
        $res_t = $stmt_t->get_result();
        if ($row_t = $res_t->fetch_assoc()) {
            $show_reset_form = true;
            $_SESSION['resetting_sid'] = "T-" . $row_t['id'];
            $_SESSION['resetting_type'] = 'teacher';
        } else {
            $error = "Invalid or expired reset link.";
        }
        $stmt_t->close();
    }
    $stmt->close();
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    // --- 0. HANDLE OTP VERIFICATION (STEP 2 OF 2FA) ---
    if (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        $otp = trim($_POST['otp']);
        $pending_id = $_SESSION['mfa_pending_id'] ?? '';

        if (empty($pending_id)) {
            $error = "Session expired. Please log in again.";
        } else {
            $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ? AND otp_code = ? AND otp_expiry > NOW()");
            $stmt->bind_param("ss", $pending_id, $otp);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user_data = $result->fetch_assoc();
                
                // STEP 2 SUCCESS: Setup full session and redirect
                $_SESSION['student_name'] = ucwords(strtolower($user_data['student_name']));
                $_SESSION['student_id']   = $user_data['student_id'];
                $_SESSION['user_type']    = 'student';

                // Set online status
                $conn->query("UPDATE students SET is_online = 1, last_activity = NOW(), otp_code = NULL, otp_expiry = NULL WHERE student_id = '" . $user_data['student_id'] . "'");

                recordLoginHistory($conn, $user_data['student_id'], $_POST['location'] ?? null);
                // Send alert asynchronously — don't block the redirect
                $alert_id   = $user_data['student_id'];
                $alert_name = $user_data['student_name'];
                $alert_email = $user_data['email'];
                register_shutdown_function(function() use ($conn, $alert_id, $alert_name, $alert_email) {
                    sendLoginAlert($conn, $alert_id, $alert_name, $alert_email, 'student');
                });
                unset($_SESSION['mfa_pending_id']);
                header("Location: intronext.php");
                exit();
            } else {
                $error = "Invalid or expired verification code.";
                $show_otp_form = true;
                $e_res = $conn->query("SELECT email FROM students WHERE student_id = '$pending_id'");
                if($e_row = $e_res->fetch_assoc()) $mfa_email = maskEmail($e_row['email']);
            }
            $stmt->close();
        }
    }
    // --- HANDLE ADMIN OTP VERIFICATION ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'verify_admin_otp') {
        $otp = trim($_POST['otp']);
        $pending_id = $_SESSION['admin_mfa_pending_id'] ?? '';
        $pending_user = $_SESSION['admin_mfa_username'] ?? '';

        if (empty($pending_id)) {
            $error = "Admin session expired. Please log in again.";
            header("Location: SacliConnect_LOG_IN.php?show=admin&admin_error=" . urlencode($error));
            exit();
        } else {
            // I-pass ang current PHP time para sigurado tayong sync ang validation
            $now = date('Y-m-d H:i:s');
            $stmt = $conn->prepare("SELECT * FROM admins2 WHERE id = ? AND otp_code = ? AND otp_expiry >= ?");
            $stmt->bind_param("iss", $pending_id, $otp, $now);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // ADMIN LOGIN SUCCESS
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $pending_id;
                $_SESSION['admin_username'] = $pending_user;

                // POV Dashboard Sessions: Pinapayagan ang admin na pumasok sa user view
                $_SESSION['student_name'] = 'Admin';
                $_SESSION['student_id'] = 'Admin';
                $_SESSION['user_type'] = 'admin';
                
                // Cleanup OTP
                $conn->query("UPDATE admins2 SET otp_code = NULL, otp_expiry = NULL WHERE id = '$pending_id'");
                
                unset($_SESSION['admin_mfa_pending_id'], $_SESSION['admin_mfa_username']);
                header("Location: admin_intro.php");
                exit();
            } else {
                $error = "Invalid or expired authorization code.";
                $show_admin_otp = true;
                $e_res = $conn->query("SELECT email FROM admins2 WHERE id = '$pending_id'");
                if($e_row = $e_res->fetch_assoc()) $admin_mfa_email = maskEmail($e_row['email']);
            }
            $stmt->close();
        }
    }
    // --- HANDLE FORGOT PASSWORD REQUEST ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'request_reset') {
        $email_input = trim($_POST['email'] ?? '');
        $valid_email = filter_var($email_input, FILTER_VALIDATE_EMAIL);
        $is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest');
        
        if (!$valid_email) {
            if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => 'Invalid email format. Access denied.']); exit(); }
            $error = "Invalid email format.";
        } else {
            // Check both students and teachers table
            $found = false;
            $user_type = 'student';
            
            // Try students first
            $stmt = $conn->prepare("SELECT student_id, email FROM students WHERE email = ?");
            $stmt->bind_param("s", $email_input);
            $stmt->execute();
            $res = $stmt->get_result();
            
            if ($res->num_rows > 0) {
                $row = $res->fetch_assoc();
                $found = true;
                $sid = $row['student_id'];
            } else {
                // Try teachers
                $stmt_t = $conn->prepare("SELECT id, email FROM teachers WHERE email = ?");
                $stmt_t->bind_param("s", $email_input);
                $stmt_t->execute();
                $res_t = $stmt_t->get_result();
                if ($res_t->num_rows > 0) {
                    $row_t = $res_t->fetch_assoc();
                    $found = true;
                    $user_type = 'teacher';
                    $sid = "T-" . $row_t['id'];
                }
            }
            
            if ($found) {
                try {
                    // Auto-fix: Ensure teachers table has recovery columns
                    safeAddColumn($conn, 'teachers', 'otp_code', 'VARCHAR(255) NULL');
                    safeAddColumn($conn, 'teachers', 'otp_expiry', 'DATETIME NULL');

                    $token = bin2hex(random_bytes(16));
                    $expiry = date('Y-m-d H:i:s', strtotime("+30 minutes"));
                    
                    if ($user_type === 'student') {
                        $conn->query("UPDATE students SET otp_code = '$token', otp_expiry = '$expiry' WHERE student_id = '$sid'");
                    } else {
                        $real_tid = str_replace("T-", "", $sid);
                        $conn->query("UPDATE teachers SET otp_code = '$token', otp_expiry = '$expiry' WHERE id = '$real_tid'");
                    }

                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?reset_token=" . $token;
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'SacliConnect20@gmail.com';
                    $mail->Password   = 'umrrmsyujepjopbo'; 
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    $mail->Timeout    = 20; // 20 seconds connection timeout
                    $mail->SMTPOptions = array('ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true));
                    $mail->setFrom('SacliConnect20@gmail.com', 'SacliConnect Security');
                    $mail->addAddress($email_input);
                    $mail->isHTML(true);
                    $mail->Subject = 'Account Recovery Link';
                    $mail->Body    = "
                        <div style='font-family: Arial; padding: 20px; border: 1px solid #00ffaa; text-align: center; background: #05100c; color: #fff;'>
                            <h2 style='color: #00ffaa;'>Neural Link Recovery</h2>
                            <p>Identity match pending. Click the button below to establish a new access code:</p><br>
                            <a href='$reset_link' style='display: inline-block; padding: 15px 30px; background: #00ffaa; color: #0a1f16; text-decoration: none; border-radius: 5px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px;'>VERIFY</a>
                            <p style='margin-top: 30px; color: #555; font-size: 12px;'>This uplink expires in 30 minutes. If you did not request this, ignore this transmission.</p>
                        </div>";
                    $mail->send();
                    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'success', 'email' => maskEmail($email_input)]); exit(); }
                    $show_wait_ui = true;
                } catch (PHPMailerException $e) { 
                    if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => 'Uplink Error: ' . $mail->ErrorInfo]); exit(); }
                    $error = "Transmission failed: " . $mail->ErrorInfo; 
                }
            } else { 
                if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['status' => 'error', 'message' => 'UNRECOGNIZED: Identity not found in database.']); exit(); }
                $error = "This email is unrecognized.";
            }
        }
    }
    // --- HANDLE PASSWORD FINALIZATION ---
    elseif (isset($_POST['action']) && $_POST['action'] === 'finalize_reset') {
        $new_pass = $_POST['new_password'];
        $conf_pass = $_POST['confirm_password'];
        $sid = $_SESSION['resetting_sid'] ?? '';
        $type = $_SESSION['resetting_type'] ?? 'student';

        if ($new_pass !== $conf_pass) { $error = "Access codes do not match."; $show_reset_form = true; }
        elseif (strlen($new_pass) < 6) { $error = "Access code must be 6+ chars."; $show_reset_form = true; }
        else {
            if ($type === 'student') {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $conn->query("UPDATE students SET password = '$hashed', otp_code = NULL, otp_expiry = NULL WHERE student_id = '$sid'");
            } else {
                $real_id = str_replace("T-", "", $sid);
                $conn->query("UPDATE teachers SET password = '$new_pass', otp_code = NULL, otp_expiry = NULL WHERE id = '$real_id'");
            }
            unset($_SESSION['resetting_sid'], $_SESSION['resetting_type']);
            $_SESSION['signup_success_msg'] = "Password established! Authentication ready.";
            header("Location: SacliConnect_LOG_IN.php"); exit();
        }
    }
    // --- 1. HANDLE INITIAL LOGIN (STEP 1) ---
    elseif (!isset($_POST['action'])) {

    // Get credentials from Step 1 form
    $login_name = trim($_POST['student_name']);
    $password   = trim($_POST['student_id']); // This field is used for password

    if(!empty($login_name) && !empty($password)){
        $user_data = null;

        // 1. Try to log in as a student or alumni
        $stmt = $conn->prepare("SELECT *, 'student' as type FROM students WHERE LOWER(student_name) = LOWER(?)");
        $stmt->bind_param("s", $login_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if($result->num_rows > 0){
            $temp_user = $result->fetch_assoc();
            
            $password_valid = false;
            if (!empty($temp_user['password'])) {
                // Suportahan ang hashed (password_verify) at plain text (legacy) comparisons
                if (password_verify($password, $temp_user['password']) || $password === $temp_user['password']) {
                    $password_valid = true;
                }
            } elseif ($password === $temp_user['student_id']) {
                $password_valid = true;
            }

            if ($password_valid) {
                $user_data = $temp_user;
            }
        }
        $stmt->close();

        // 2. If not a student, try to log in as a teacher
        if(!$user_data) {
            $stmt = $conn->prepare("SELECT *, 'teacher' as type, name as student_name, CONCAT('T-', id) as student_id FROM teachers WHERE LOWER(name) = LOWER(?)");
            $stmt->bind_param("s", $login_name);
            $stmt->execute();
            $result = $stmt->get_result();
            if($result->num_rows > 0){
                $temp_teacher = $result->fetch_assoc();
                if (password_verify($password, $temp_teacher['password']) || $password === $temp_teacher['password']) {
                    $user_data = $temp_teacher;
                }
            }
            $stmt->close();
        }

        if($user_data){
            // Check for restriction
            if (isset($user_data['is_restricted']) && $user_data['is_restricted'] == 1) {
                $end_date_str = $user_data['restriction_end_date'];
                if ($end_date_str) {
                    $end_date = new DateTime($end_date_str);
                    $now = new DateTime();
                    if ($now < $end_date) {
                        $error = "Your account is restricted until " . $end_date->format('M d, Y, h:i A') . ".";
                    } else {
                        // Restriction expired, lift it
                        if ($user_data['type'] === 'teacher') {
                            $conn->query("UPDATE teachers SET is_restricted = 0, restriction_end_date = NULL WHERE id = " . $user_data['id']);
                        } else {
                            $conn->query("UPDATE students SET is_restricted = 0, restriction_end_date = NULL WHERE student_id = '" . $user_data['student_id'] . "'");
                        }
                    }
                } else {
                    $error = "Your account is restricted indefinitely.";
                }
            }
            
            // Check for security lock (force_logout triggered via Security Alert)
            if (empty($error) && isset($user_data['force_logout']) && $user_data['force_logout'] == 1) {
                // Account is locked, send a new password reset link and inform the user
                sendPasswordResetLink($conn, $user_data['student_id'], $user_data['student_name'], $user_data['email'], $user_data['type']);
                $error = "Access Denied: Your account is currently locked for security reasons. We've sent a password reset link to your registered email address. Please check your inbox to continue.";
                // Do not proceed with login even if password is correct
            }

            if(empty($error)) {
                // CHECK FOR 2FA (Students only)
                if ($user_data['type'] === 'student' && isset($user_data['mfa_enabled']) && $user_data['mfa_enabled'] == 1) {
                    // Credentials correct but 2FA is ON -> Proceed to Verification Step
                    $mfa_email_raw = $user_data['email'];
                    if (empty($mfa_email_raw)) {
                        $error = "2FA is enabled but no email is found. Please contact admin.";
                    } else {
                        $otp = rand(100000, 999999);
                        $expiry = date('Y-m-d H:i:s', strtotime("+10 minutes"));
                        // Save OTP to DB
                        $conn->query("UPDATE students SET otp_code = '$otp', otp_expiry = '$expiry' WHERE student_id = '".$user_data['student_id']."'");
                        
                        $_SESSION['mfa_pending_id'] = $user_data['student_id'];
                        $mfa_email = maskEmail($mfa_email_raw);

                        $mail = new PHPMailer(true);
                        try {
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com';
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'sacliconnect20@gmail.com';
                            $mail->Password   = 'umrrmsyujepjopbo'; 
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = 587;
                            $mail->SMTPOptions = array('ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true));
                            $mail->setFrom('sacliconnect20@gmail.com', 'SacliConnect Security');
                            $mail->addAddress($mfa_email_raw);
                            $mail->isHTML(true);
                            $mail->Subject = 'Login Verification Code';
                            $mail->Body    = "Your verification code is: <b>$otp</b><br>This code will expire in 10 minutes.";
                            $mail->send();
                            
                            $show_otp_form = true;
                        } catch (Exception $e) {
                            $error = "Failed to send verification code. Please try again.";
                        }
                    }
                } else {
                    // 2FA is OFF or user is Teacher -> Direct Login
                    $_SESSION['student_name'] = ucwords(strtolower($user_data['student_name']));
                    $_SESSION['student_id']   = $user_data['student_id'];
                    $_SESSION['user_type']    = $user_data['type'];

                    if ($user_data['type'] === 'teacher') {
                        $conn->query("UPDATE teachers SET is_online = 1, last_activity = NOW() WHERE id = " . $user_data['id']);
                    } else {
                        $conn->query("UPDATE students SET is_online = 1, last_activity = NOW() WHERE student_id = '" . $user_data['student_id'] . "'");
                    }
                    recordLoginHistory($conn, $user_data['student_id'], $_POST['location'] ?? null);
                    // Send alert asynchronously — don't block the redirect
                    $alert_id    = $user_data['student_id'];
                    $alert_name  = $user_data['student_name'];
                    $alert_email = $user_data['email'];
                    $alert_type  = $user_data['type'];
                    register_shutdown_function(function() use ($conn, $alert_id, $alert_name, $alert_email, $alert_type) {
                        sendLoginAlert($conn, $alert_id, $alert_name, $alert_email, $alert_type);
                    });
                    header("Location: intronext.php");
                    exit();
                }
            }
        } else {
            $error = "Incorrect, please try again.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
    }
}

// Mask email for Admin MFA display
if($show_admin_otp && isset($_SESSION['admin_mfa_pending_id'])) {
    $pending_id = $_SESSION['admin_mfa_pending_id'];
    $e_res = $conn->query("SELECT email FROM admins2 WHERE id = '$pending_id'");
    if($e_row = $e_res->fetch_assoc()) $admin_mfa_email = maskEmail($e_row['email']);
}

$conn->close();
?>


<!DOCTYPE html>
<html>


<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Sacli Connect</title>
    <link href="https://fonts.googleapis.com/css2?family=Cutive+Mono&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/2_Login.css">
    <style>
        .welcome-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(5,16,12,0.95);z-index:99999;display:none;justify-content:center;align-items:center;opacity:0;transition:opacity .5s ease;backdrop-filter:blur(10px)}.welcome-overlay.show{opacity:1}.w
        elcome-content{text-align:center;color:#fff;transform:scale(.8);transition:transform .5s cubic-bezier(.34,1.56,.64,1)}.welcome-overlay.show .welcome-content{transform:scale(1)}.welcome-content h1{font-family:'Segoe UI',sans-serif;font-size:1.5rem;margin-bottom:10px;color:#b0fce0;font-weight:300;letter-spacing:2px}.welcome-content h2{font-family:'Segoe UI',sans-serif;font-size:3rem;font-weight:800;text-transform:uppercase;color:#00ffaa;text-shadow:0 0 30px rgba(0,255,170,.6);margin:0}.fade-out{opacity:0 !important; transition: opacity 0.8s ease;}

        /* --- Night / Tech Style for Default Theme --- */
        body:not([class*="theme-"]), body.theme-default {
            background: radial-gradient(circle at center, #0a1f16 0%, #020806 100%) !important;
            color: #e4e6eb;
            position: relative;
            overflow: hidden;
        }

        /* --- Video Intro Animation Styles --- */
        .video-intro-overlay {
            position: absolute;
            top: 8px; left: 8px; right: 8px; bottom: 8px;
            position: fixed; /* Ginawang fixed para maging full screen */
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at center, #103d2e 0%, #020806 100%);
            background-image: 
                linear-gradient(rgba(0, 255, 170, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 170, 0.05) 1px, transparent 1px);
            background-size: 20px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 25;
            border-radius: 20px;
            z-index: 100001; /* Siguraduhing nasa ibabaw ng header at card */
            border-radius: 0;
            transition: opacity 1.2s cubic-bezier(0.4, 0, 0.2, 1), visibility 1.2s, transform 1.2s ease-in;
            border: 1px solid rgba(0, 255, 170, 0.2);
            border: none;
            pointer-events: none;
            overflow: hidden;
            box-shadow: inset 0 0 100px rgba(0,0,0,0.8);
        }
        
        .video-intro-overlay::after {
            content: "";
            position: absolute; top: -100%; left: 0; width: 100%; height: 50%;
            background: linear-gradient(to bottom, transparent, rgba(0, 255, 170, 0.05), transparent);
            animation: introScan 3s linear infinite;
        }
        @keyframes introScan { 0% { top: -100%; } 100% { top: 100%; } }

        .intro-text-glow {
            font-family: 'Orbitron', sans-serif;
            font-size: 6rem;
            font-weight: 900;
            background: linear-gradient(to bottom, #ffffff 0%, #00ffaa 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: #00ffaa;
            text-align: center;
            line-height: 0.9;
            text-transform: uppercase;
            letter-spacing: 5px;
            animation: neonPulse 1.5s infinite alternate 9.5s;
            filter: drop-shadow(0 0 30px rgba(0, 255, 170, 0.5));
            transform: translateZ(0);
            will-change: filter, transform;
            display: flex;
            justify-content: center;
            perspective: 1000px;
        }

        /* Animation bawat letter */
        .intro-text-glow span {
            display: inline-block;
            opacity: 0;
            transform: translateZ(100px) scale(0.3) rotateY(90deg);
            animation: letterReveal 0.8s cubic-bezier(0.23, 1, 0.32, 1) forwards;
            background: linear-gradient(to bottom, #ffffff 0%, #00ffaa 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 0px rgba(0, 255, 170, 0);
        }

        @keyframes letterReveal {
            0% { opacity: 0; transform: translateZ(100px) scale(0.3) rotateY(90deg); filter: blur(20px) brightness(5); }
            50% { opacity: 1; filter: blur(5px) brightness(2); }
            70% { transform: translateZ(-20px) scale(1.1) rotateY(-10deg); }
            100% { opacity: 1; transform: translateZ(0) scale(1) rotateY(0deg); filter: blur(0) brightness(1); }
        }

        /* Staggered Delay para sa SACLI CONNECT (13 characters) */
        .intro-text-glow span:nth-child(1) { animation-delay: 0.2s; }
        .intro-text-glow span:nth-child(2) { animation-delay: 0.9s; }
        .intro-text-glow span:nth-child(3) { animation-delay: 1.6s; }
        .intro-text-glow span:nth-child(4) { animation-delay: 2.3s; }
        .intro-text-glow span:nth-child(5) { animation-delay: 3.0s; }
        .intro-text-glow span:nth-child(6) { animation-delay: 3.7s; } /* Space */
        .intro-text-glow span:nth-child(7) { animation-delay: 4.4s; }
        .intro-text-glow span:nth-child(8) { animation-delay: 5.1s; }
        .intro-text-glow span:nth-child(9) { animation-delay: 5.8s; }
        .intro-text-glow span:nth-child(10) { animation-delay: 6.5s; }
        .intro-text-glow span:nth-child(11) { animation-delay: 7.2s; }
        .intro-text-glow span:nth-child(12) { animation-delay: 7.9s; }
        .intro-text-glow span:nth-child(13) { animation-delay: 8.6s; }

        .intro-subtext {
            font-family: 'Cutive Mono', monospace;
            color: #00ffaa;
            font-size: 12px;
            letter-spacing: 4px;
            margin-top: 15px;
            opacity: 0;
            overflow: hidden;
            white-space: nowrap;
            border-right: 2px solid #00ffaa;
            width: 0;
            animation: 
                typingEffect 2s steps(20, end) forwards 1.5s,
                blinkCursor 0.8s infinite;
            text-shadow: 0 0 10px rgba(0, 255, 170, 0.5);
        }
        
        @keyframes typingEffect {
            from { width: 0; opacity: 1; }
            to { width: 230px; opacity: 1; }
        }
        
        @keyframes blinkCursor {
            from, to { border-color: transparent; }
            50% { border-color: #00ffaa; }
        }

        @keyframes neonPulse { 
            0%, 100% { filter: drop-shadow(0 0 15px rgba(0, 255, 170, 0.6)); opacity: 1; } 
            50% { filter: drop-shadow(0 0 35px rgba(0, 255, 170, 1)); opacity: 0.8; transform: scale(1.01); } 
            20%, 80% { filter: drop-shadow(0 0 20px rgba(0, 255, 170, 0.8)); }
            21% { opacity: 0.5; } /* Subte flicker */
        }

        @keyframes glitchReveal {
            0% { opacity: 0; transform: scale(0.9); filter: blur(10px) hue-rotate(90deg); }
            20% { opacity: 1; filter: blur(0); transform: scale(1.1); }
            25% { transform: translate(2px, -2px); }
            30% { transform: translate(-2px, 2px); }
            35% { transform: translate(0); }
            100% { opacity: 1; transform: scale(1); }
        }

        /* --- Cinematic Video Reveal --- */
        #myVideo {
            clip-path: circle(0% at center);
            transition: clip-path 1.5s cubic-bezier(0.77, 0, 0.175, 1), filter 1.5s;
            filter: brightness(0.2) blur(10px);
        }
        #myVideo.reveal-active { clip-path: circle(150% at center); filter: brightness(1) blur(0); }
        
        .video-intro-overlay.fade-out-intro { opacity: 0; visibility: hidden; transform: scale(1.1); }
        /* ------------------------------------ */

        /* --- Mute Button Intro Appearance --- */
        #muteBtn {
            opacity: 0;
            visibility: hidden;
            transition: opacity 1s ease 0.5s; /* Lalabas ng dahan-dahan pagkatapos ma-reveal ang video */
            z-index: 30;
        }
        #muteBtn.visible { opacity: 1; visibility: visible; }

        /* --- Passing Light Animation (Enhanced Glow) --- */
        body:not([class*="theme-"])::before, body.theme-default::before {
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

        /* Initial state para sa fade-in effect */
        #mainCard, #videoContainer, header, footer {
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
        }
        
        .card {
            background: 
                linear-gradient(rgba(0, 255, 170, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 170, 0.03) 1px, transparent 1px),
                radial-gradient(ellipse at center, rgba(16, 46, 34, 0.9) 0%, rgba(5, 15, 10, 0.95) 100%) !important;
            background-size: 25px 25px, 25px 25px, 100% 100% !important;
            backdrop-filter: blur(15px) !important;
            border: 1px solid rgba(0, 255, 170, 0.25);
            box-shadow: 0 0 50px rgba(0, 0, 0, 0.8), inset 0 0 20px rgba(0, 255, 170, 0.05);
            border-radius: 12px !important;
            position: relative;
            overflow: hidden;
            height: 76%;
            top:20px;
            animation: borderPulse 3s infinite ease-in-out;
            position: relative;
            top: 1.5%;
        }

        /* Class para i-trigger ang paglitaw ng UI */
        .reveal-ui {
            opacity: 1 !important;
        }

        /* FIX HEADER SIZE: Pinipigilan ang paggalaw o pagliit ng header at wrap tuwing nag-zo-zoom o resize */
        header {
            min-width: 1200px !important;
            height: 80px !important;
            padding: 10px 40px !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
        }

        /* Mobile Override para sa Header at Wrap */
        @media (max-width: 900px) {
            header {
                min-width: 100% !important;
                padding: 0 15px !important;
                height: 70px !important;
            }
            .nameschool {
                display: none; /* Itago ang pangalan ng school sa mobile para magkasya ang tabs */
            }
            .wrap {
                right: 0 !important; /* I-reset ang inline right:79% para pumasok sa loob ng screen */
            }
        }

        /* Responsive adjustments for mobile phones */
        @media (max-width: 850px) {
            body {
                padding: 90px 15px 80px 15px !important;
                flex-direction: column; /* Stack elements vertically */
                align-items: center;    /* Center items horizontally */
                justify-content: center; /* Center items vertically in available space */
                gap: 20px; /* Spacing between card and other elements if any */
                overflow-y: auto; /* Allow scrolling if content is long */
            }
            .video3 {
                display: none !important; /* Hide the video on mobile */
            }
            .card {
                width: 100% !important; /* Make card take full width */
                max-width: 400px !important; /* Limit max width for larger phones/tablets */
                padding: 35px 25px !important; /* Adjust card padding for mobile */
            }
            footer { height: 50px; font-size: 9px; padding: 0 20px; text-align: center; } /* Adjust footer height and font size */
        }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 60px;
            background: rgba(4, 14, 10, 0.95);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-top: 1px solid rgba(0, 255, 170, 0.2);
            box-shadow: 0 -4px 30px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(0, 255, 170, 0.7);
            font-family: 'Cutive Mono', monospace;
            font-size: 11px;
            letter-spacing: 1.5px;
            z-index: 1000;
            text-transform: uppercase;
            text-shadow: 0 0 10px rgba(0, 255, 170, 0.3);
        }

        @keyframes passingLight {
            0% { transform: translateX(-150%) skewX(-20deg); opacity: 0; }
            25% { opacity: 0; }
            50% { opacity: 1; }
            75% { opacity: 0; }
            100% { transform: translateX(150%) skewX(-20deg); opacity: 0; }
        }

        @keyframes borderPulse {
            0%, 25%, 75%, 100% { 
                border-color: rgba(0, 255, 170, 0.25); 
                box-shadow: 0 0 50px rgba(0, 0, 0, 0.8), inset 0 0 20px rgba(0, 255, 170, 0.05);
            }
            50% { 
                border-color: rgba(0, 255, 170, 1); 
                box-shadow: 0 0 50px rgba(0, 0, 0, 0.8), 0 0 30px rgba(0, 255, 170, 0.4), inset 0 0 25px rgba(0, 255, 170, 0.15);
            }
        }

        #stars {
          position: fixed;
          top: 0;
          left: 0;
          z-index: -1;
          width: 1px;
          height: 1px;
          background: transparent;
          box-shadow:
            501px 811px #fff, 1450px 1324px #fff, 1093px 1780px #fff, 1469px 678px #fff, 904px 741px #fff, 1160px 781px #fff, 1841px 1962px #fff, 1630px 1667px #fff, 1788px 676px #fff, 367px 1734px #fff, 1343px 156px #fff, 1283px 1142px #fff, 1062px 378px #fff, 1395px 467px #fff, 1017px 1891px #fff, 137px 1114px #fff, 1767px 1403px #fff, 1543px 11px #fff, 1078px 181px #fff, 1189px 1574px #fff, 1697px 1551px #fff, 439px 472px #fff, 1491px 677px #fff, 1364px 599px #fff, 34px 382px #fff, 1221px 1584px #fff, 1266px 1499px #fff, 169px 1907px #fff, 1219px 1125px #fff, 659px 18px #fff, 1731px 1959px #fff, 332px 1216px #fff, 1913px 788px #fff, 80px 712px #fff, 326px 1605px #fff, 574px 1502px #fff, 473px 1653px #fff, 404px 975px #fff, 322px 1797px #fff, 425px 1321px #fff, 1121px 1797px #fff, 731px 647px #fff, 891px 1584px #fff, 1523px 109px #fff, 1379px 244px #fff, 865px 1064px #fff, 493px 956px #fff, 624px 1380px #fff, 440px 619px #fff, 1630px 767px #fff, 955px 1196px #fff, 62px 729px #fff, 126px 946px #fff, 1256px 896px #fff, 1444px 256px #fff, 661px 1628px #fff, 1078px 1716px #fff, 300px 737px #fff, 1734px 413px #fff, 1296px 129px #fff, 1771px 1678px #fff, 977px 1764px #fff, 1879px 549px #fff, 665px 1531px #fff, 89px 701px #fff, 1084px 1183px #fff, 1597px 1576px #fff, 1354px 1774px #fff, 554px 1471px #fff, 1469px 287px #fff, 887px 106px #fff, 1962px 766px #fff, 638px 805px #fff, 1651px 741px #fff, 1517px 1826px #fff, 24px 1152px #fff, 507px 558px #fff, 1262px 652px #fff, 246px 1048px #fff, 1077px 421px #fff, 1866px 1847px #fff, 1986px 1561px #fff, 704px 632px #fff, 1991px 1875px #fff, 1227px 395px #fff, 45px 1116px #fff, 247px 786px #fff, 890px 607px #fff, 787px 1235px #fff, 557px 524px #fff, 1582px 1285px #fff, 1725px 1366px #fff, 952px 747px #fff, 251px 458px #fff, 1500px 1250px #fff, 1999px 1734px #fff, 1336px 1955px #fff, 1705px 1464px #fff, 728px 697px #fff, 594px 510px #fff, 1345px 1990px #fff, 1919px 1803px #fff, 1117px 966px #fff, 1629px 97px #fff, 1046px 1196px #fff, 810px 1092px #fff, 722px 976px #fff, 406px 18px #fff, 1665px 1860px #fff, 1758px 1628px #fff, 1183px 463px #fff, 564px 239px #fff, 13px 1767px #fff, 1482px 1472px #fff, 1700px 347px #fff, 1362px 244px #fff, 1141px 1708px #fff, 22px 885px #fff, 374px 1309px #fff, 1034px 1037px #fff, 1725px 1086px #fff, 1343px 1921px #fff, 596px 903px #fff, 1061px 478px #fff, 18px 1409px #fff, 729px 1364px #fff, 264px 911px #fff, 677px 1442px #fff, 123px 33px #fff, 1303px 646px #fff, 1945px 792px #fff, 1305px 938px #fff, 918px 1536px #fff, 620px 948px #fff, 183px 646px #fff, 695px 687px #fff, 881px 272px #fff, 1521px 1212px #fff, 1423px 1022px #fff, 1545px 1271px #fff, 1393px 348px #fff, 685px 1910px #fff, 1446px 856px #fff, 73px 1201px #fff, 736px 999px #fff, 673px 796px #fff, 469px 850px #fff, 1912px 142px #fff, 1278px 664px #fff, 184px 1990px #fff, 1173px 1312px #fff, 782px 1879px #fff, 323px 1035px #fff, 611px 908px #fff, 565px 1449px #fff, 748px 1713px #fff, 1047px 490px #fff, 1040px 1872px #fff, 1818px 1659px #fff, 1806px 1327px #fff, 386px 575px #fff, 1550px 463px #fff, 148px 687px #fff, 651px 1683px #fff, 1588px 1194px #fff, 1831px 2px #fff, 581px 876px #fff, 1396px 1743px #fff, 1212px 1810px #fff, 421px 1920px #fff, 658px 1461px #fff, 1859px 1809px #fff, 1456px 388px #fff, 186px 1627px #fff, 1528px 1145px #fff, 171px 97px #fff, 674px 1072px #fff, 676px 1052px #fff, 1165px 1131px #fff, 1088px 781px #fff, 1231px 948px #fff, 330px 257px #fff, 426px 1046px #fff, 549px 652px #fff, 1338px 74px #fff, 1749px 364px #fff, 931px 369px #fff, 383px 1428px #fff, 1558px 389px #fff, 927px 133px #fff, 234px 1888px #fff, 1785px 1617px #fff, 556px 643px #fff, 401px 275px #fff, 406px 1644px #fff, 1253px 1852px #fff, 1599px 883px #fff, 744px 1721px #fff, 524px 1297px #fff, 1226px 1177px #fff, 1679px 55px #fff, 874px 1811px #fff, 838px 790px #fff, 1241px 430px #fff, 1676px 652px #fff, 1191px 568px #fff, 53px 1990px #fff, 1163px 237px #fff, 61px 223px #fff, 592px 456px #fff, 1844px 271px #fff, 1324px 1488px #fff, 1373px 717px #fff, 1822px 709px #fff, 1464px 941px #fff, 1445px 1118px #fff, 991px 1414px #fff, 1964px 1076px #fff, 108px 172px #fff, 641px 1722px #fff, 1539px 427px #fff, 1697px 45px #fff, 1301px 1353px #fff, 1060px 329px #fff, 967px 1396px #fff, 493px 301px #fff, 1228px 1406px #fff, 1211px 1653px #fff, 444px 1822px #fff, 1746px 353px #fff, 1449px 381px #fff, 671px 887px #fff, 650px 138px #fff, 30px 1839px #fff, 1094px 1405px #fff, 273px 796px #fff, 1618px 1964px #fff, 1045px 1849px #fff, 1472px 1155px #fff, 1529px 1312px #fff, 728px 448px #fff, 44px 1908px #fff, 691px 818px #fff, 254px 293px #fff, 1981px 1133px #fff, 1307px 375px #fff, 196px 316px #fff, 1241px 1975px #fff, 1138px 1706px #fff, 1769px 463px #fff, 1768px 1428px #fff, 1730px 590px #fff, 1780px 523px #fff, 1862px 1526px #fff, 1613px 909px #fff, 1266px 1781px #fff, 470px 352px #fff, 699px 1682px #fff, 1002px 614px #fff, 1209px 133px #fff, 1842px 518px #fff, 1422px 1836px #fff, 1720px 1901px #fff, 470px 1788px #fff, 1355px 1387px #fff, 146px 1162px #fff, 933px 80px #fff, 681px 1063px #fff, 313px 1341px #fff, 740px 1498px #fff, 168px 1014px #fff, 345px 1355px #fff, 1498px 1562px #fff, 1626px 1358px #fff, 890px 403px #fff, 663px 562px #fff, 1481px 168px #fff, 22px 719px #fff, 774px 1041px #fff, 1899px 829px #fff, 430px 158px #fff, 430px 361px #fff, 1592px 1334px #fff, 224px 323px #fff, 1639px 1131px #fff, 7px 271px #fff, 1646px 1514px #fff, 1605px 1444px #fff, 1820px 1665px #fff, 1549px 1641px #fff, 1609px 1377px #fff, 486px 1098px #fff, 229px 613px #fff, 542px 1694px #fff, 318px 256px #fff, 1861px 918px #fff, 889px 892px #fff, 442px 1524px #fff, 19px 422px #fff, 1935px 1908px #fff, 828px 109px #fff, 862px 1248px #fff, 1275px 560px #fff, 906px 63px #fff, 337px 1605px #fff, 1691px 918px #fff, 1414px 679px #fff, 1726px 749px #fff, 1540px 1149px #fff, 1337px 1466px #fff, 446px 430px #fff, 676px 1616px #fff, 840px 326px #fff, 976px 977px #fff, 1840px 642px #fff, 1273px 804px #fff, 1071px 928px #fff, 1292px 1675px #fff, 29px 1148px #fff, 1585px 135px #fff, 1007px 563px #fff, 1035px 78px #fff, 1174px 574px #fff, 120px 1304px #fff, 845px 1292px #fff, 861px 540px #fff, 234px 232px #fff, 1940px 1367px #fff, 759px 639px #fff, 1775px 1381px #fff, 906px 372px #fff, 1104px 1165px #fff, 1524px 911px #fff, 1882px 330px #fff, 1389px 700px #fff, 300px 1629px #fff, 220px 1614px #fff, 563px 140px #fff, 1611px 1586px #fff, 793px 1316px #fff, 325px 1070px #fff, 1722px 1462px #fff, 1406px 1120px #fff, 1169px 1768px #fff, 1956px 1053px #fff, 959px 1587px #fff, 585px 1566px #fff, 370px 204px #fff, 1606px 1416px #fff, 443px 1606px #fff, 1499px 1102px #fff, 1943px 105px #fff, 1121px 1594px #fff, 1512px 32px #fff, 871px 1425px #fff, 433px 100px #fff, 294px 1471px #fff, 1688px 1755px #fff, 1666px 591px #fff, 1034px 300px #fff, 734px 1178px #fff, 1342px 313px #fff, 1616px 1590px #fff, 1763px 1472px #fff, 632px 1935px #fff, 1708px 872px #fff, 1871px 915px #fff, 1829px 1020px #fff, 1599px 578px #fff, 42px 585px #fff, 1163px 1382px #fff, 1744px 1272px #fff, 984px 1426px #fff, 1786px 1584px #fff, 1813px 379px #fff, 1867px 1127px #fff, 97px 567px #fff, 626px 988px #fff, 1178px 79px #fff, 1703px 211px #fff, 961px 1785px #fff, 110px 975px #fff, 953px 1941px #fff, 1027px 1790px #fff, 1665px 107px #fff, 11px 964px #fff, 1718px 1147px #fff, 21px 1728px #fff, 1358px 1922px #fff, 872px 65px #fff, 1191px 1635px #fff, 762px 681px #fff, 1519px 1033px #fff, 906px 566px #fff, 1074px 657px #fff, 1093px 415px #fff, 51px 198px #fff, 1075px 1418px #fff, 1547px 1070px #fff, 225px 920px #fff, 850px 1974px #fff, 981px 595px #fff, 1425px 131px #fff, 460px 917px #fff, 56px 495px #fff, 714px 428px #fff, 920px 493px #fff, 470px 1521px #fff, 532px 821px #fff, 1905px 71px #fff, 883px 1501px #fff, 294px 196px #fff, 381px 1999px #fff, 332px 793px #fff, 1246px 408px #fff, 233px 149px #fff, 315px 231px #fff, 1594px 1302px #fff, 696px 1585px #fff, 791px 136px #fff, 479px 199px #fff, 1627px 1413px #fff, 1824px 924px #fff, 1631px 342px #fff, 1251px 1151px #fff, 284px 1781px #fff, 497px 1052px #fff, 204px 1161px #fff, 646px 1499px #fff, 1762px 558px #fff, 854px 1833px #fff, 883px 945px #fff, 44px 982px #fff, 1101px 834px #fff, 515px 1748px #fff, 1578px 1435px #fff, 819px 1258px #fff, 776px 670px #fff, 115px 385px #fff, 1478px 434px #fff, 885px 20px #fff, 192px 1513px #fff, 78px 1129px #fff, 1774px 1105px #fff, 955px 1149px #fff, 1817px 1929px #fff, 1106px 1832px #fff, 1107px 1997px #fff, 94px 23px #fff, 243px 982px #fff, 43px 1972px #fff, 1798px 673px #fff, 1131px 1589px #fff, 841px 14px #fff, 826px 345px #fff, 687px 56px #fff, 1084px 32px #fff, 1887px 1878px #fff, 153px 526px #fff, 1828px 253px #fff, 1947px 1105px #fff, 886px 700px #fff, 1307px 1723px #fff, 1274px 651px #fff, 1530px 837px #fff, 1699px 1637px #fff, 1703px 1331px #fff, 1929px 1557px #fff, 1763px 737px #fff, 1118px 1680px #fff, 1545px 692px #fff, 1462px 1092px #fff, 208px 1667px #fff, 1393px 859px #fff, 186px 1794px #fff, 351px 1199px #fff, 642px 1995px #fff, 1061px 1726px #fff, 1708px 115px #fff, 1233px 1305px #fff, 637px 1786px #fff, 1730px 603px #fff, 75px 1240px #fff, 1704px 1326px #fff, 584px 346px #fff, 438px 1554px #fff, 561px 513px #fff, 1382px 225px #fff, 467px 1674px #fff, 1403px 815px #fff, 1546px 1835px #fff, 127px 1119px #fff, 276px 591px #fff, 688px 1458px #fff, 765px 646px #fff, 474px 984px #fff, 171px 361px #fff, 94px 1480px #fff, 1962px 1666px #fff, 909px 1037px #fff, 1725px 222px #fff, 253px 1355px #fff, 1892px 1901px #fff, 275px 1847px #fff, 28px 1184px #fff, 1725px 1382px #fff, 882px 647px #fff, 1935px 1046px #fff, 10px 344px #fff, 292px 1328px #fff, 127px 1352px #fff, 752px 929px #fff, 1589px 384px #fff, 284px 1829px #fff, 381px 820px #fff, 1229px 1125px #fff, 777px 429px #fff, 1811px 1499px #fff, 1573px 287px #fff, 295px 756px #fff, 389px 616px #fff, 781px 41px #fff, 1092px 333px #fff, 794px 1588px #fff, 386px 1847px #fff, 1802px 710px #fff, 662px 60px #fff, 640px 264px #fff, 463px 746px #fff, 1859px 799px #fff, 763px 37px #fff, 639px 396px #fff, 357px 1071px #fff, 1190px 1430px #fff, 1814px 257px #fff, 1382px 235px #fff, 606px 1304px #fff, 1939px 1470px #fff, 1124px 349px #fff, 307px 1567px #fff, 310px 1323px #fff, 1145px 922px #fff, 1196px 1922px #fff, 1647px 544px #fff, 788px 1337px #fff, 257px 632px #fff, 1413px 414px #fff, 590px 620px #fff, 582px 794px #fff, 1702px 1481px #fff, 1055px 53px #fff, 157px 346px #fff, 50px 1901px #fff, 1038px 1369px #fff, 796px 1941px #fff, 215px 194px #fff, 1567px 1538px #fff, 367px 800px #fff, 1044px 489px #fff, 1109px 1712px #fff, 524px 327px #fff, 525px 1252px #fff, 1475px 1240px #fff, 529px 436px #fff, 795px 834px #fff, 122px 1371px #fff, 79px 482px #fff, 520px 1249px #fff, 336px 1878px #fff, 188px 944px #fff, 325px 1259px #fff, 1491px 1942px #fff, 620px 1054px #fff, 1606px 1153px #fff, 1448px 502px #fff, 53px 1381px #fff, 107px 1670px #fff, 1380px 618px #fff, 967px 1557px #fff, 1116px 1722px #fff, 1174px 1044px #fff, 1805px 717px #fff, 663px 394px #fff, 1848px 1007px #fff, 389px 802px #fff, 49px 392px #fff, 1650px 852px #fff, 1678px 1012px #fff, 335px 1009px #fff, 1818px 1631px #fff, 1568px 742px #fff, 1162px 1991px #fff, 52px 1190px #fff, 1401px 928px #fff, 119px 1549px #fff, 537px 1529px #fff, 2px 1709px #fff, 122px 387px #fff, 543px 2px #fff, 27px 1971px #fff, 507px 1377px #fff, 1362px 1080px #fff, 1031px 1544px #fff, 1631px 1174px #fff, 1603px 312px #fff, 1626px 1422px #fff, 1430px 615px #fff, 1958px 1431px #fff, 1946px 1412px #fff, 1848px 247px #fff, 984px 1808px #fff, 1396px 225px #fff, 319px 717px #fff, 1252px 875px #fff, 1619px 156px #fff, 951px 1971px #fff, 386px 355px #fff, 1406px 1151px #fff, 273px 1538px #fff, 844px 1570px #fff, 947px 151px #fff, 1363px 525px #fff, 209px 307px #fff, 1923px 1718px #fff, 993px 1741px #fff, 1513px 353px #fff, 1353px 61px #fff, 664px 352px #fff, 1382px 359px #fff, 1487px 1707px #fff, 657px 1045px #fff, 1107px 490px #fff, 1834px 1176px #fff, 837px 1438px #fff, 1947px 448px #fff, 1196px 333px #fff, 151px 555px #fff, 18px 992px #fff, 458px 748px #fff, 1801px 890px #fff, 1093px 1012px #fff, 315px 1101px #fff, 194px 323px #fff, 754px 292px #fff, 1737px 7px #fff, 40px 840px #fff, 1170px 805px #fff, 176px 1753px #fff, 805px 1148px #fff, 1578px 1271px #fff, 367px 1494px #fff, 363px 1111px #fff, 1955px 243px #fff, 1451px 1093px #fff, 375px 617px #fff, 1223px 720px #fff, 1178px 13px #fff, 1456px 865px #fff, 1440px 49px #fff, 186px 1569px #fff, 320px 1853px #fff, 300px 539px #fff, 1559px 509px #fff, 1985px 1108px #fff, 1588px 828px #fff, 525px 1432px #fff, 831px 363px #fff, 141px 281px #fff, 1319px 402px #fff, 40px 456px #fff, 1955px 478px #fff, 1758px 818px #fff, 1924px 688px #fff, 1030px 953px #fff, 1982px 210px #fff, 917px 1401px #fff, 1051px 1837px #fff, 1045px 463px #fff, 1744px 573px #fff, 529px 1530px #fff, 542px 469px #fff, 1982px 324px #fff, 1902px 1422px #fff, 1968px 782px #fff, 1666px 1561px #fff, 955px 304px #fff, 323px 778px #fff, 272px 443px #fff, 485px 581px #fff, 1353px 1058px #fff, 1257px 131px #fff, 434px 98px #fff, 1587px 1953px #fff, 1749px 68px #fff, 1984px 839px #fff, 1518px 183px #fff, 1071px 855px #fff, 1662px 1994px #fff, 1111px 106px #fff, 1954px 838px #fff;
          animation: animStar 50s linear infinite;
        }
        #stars:after {
          content: " ";
          position: absolute;
          top: 2000px;
          width: 1px;
          height: 1px;
          background: transparent;
          box-shadow: inherit;
        }

        #stars2 {
          position: fixed;
          top: 0;
          left: 0;
          z-index: -1;
          width: 2px;
          height: 2px;
          background: transparent;
          box-shadow:
            1925px 1320px #fff, 693px 1778px #fff, 1016px 711px #fff, 1171px 563px #fff, 661px 1919px #fff, 1610px 44px #fff, 1275px 140px #fff, 1208px 1802px #fff, 1473px 1587px #fff, 11px 1117px #fff, 853px 1757px #fff, 1149px 937px #fff, 1353px 428px #fff, 270px 279px #fff, 258px 1404px #fff, 417px 1188px #fff, 286px 561px #fff, 393px 1765px #fff, 147px 881px #fff, 666px 1097px #fff, 1425px 1278px #fff, 806px 156px #fff, 1252px 561px #fff, 218px 52px #fff, 1371px 1980px #fff, 171px 745px #fff, 1424px 89px #fff, 137px 244px #fff, 939px 1922px #fff, 137px 1080px #fff, 1757px 50px #fff, 904px 536px #fff, 1938px 1001px #fff, 1172px 440px #fff, 72px 1475px #fff, 102px 121px #fff, 804px 1671px #fff, 1314px 270px #fff, 440px 1341px #fff, 1216px 511px #fff, 1061px 1523px #fff, 97px 274px #fff, 704px 1318px #fff, 52px 1872px #fff, 1962px 296px #fff, 111px 289px #fff, 1157px 1236px #fff, 1347px 1451px #fff, 820px 286px #fff, 1389px 1169px #fff, 644px 841px #fff, 1286px 522px #fff, 955px 659px #fff, 428px 1805px #fff, 237px 557px #fff, 1689px 1058px #fff, 636px 1882px #fff, 1349px 1664px #fff, 1548px 432px #fff, 1841px 504px #fff, 302px 252px #fff, 827px 1765px #fff, 620px 123px #fff, 207px 748px #fff, 1454px 1234px #fff, 1967px 1790px #fff, 542px 33px #fff, 742px 1214px #fff, 255px 1402px #fff, 74px 1772px #fff, 699px 475px #fff, 980px 1253px #fff, 534px 1676px #fff, 909px 202px #fff, 1498px 1251px #fff, 1796px 120px #fff, 1409px 1263px #fff, 1627px 995px #fff, 969px 710px #fff, 1674px 676px #fff, 1832px 759px #fff, 1623px 563px #fff, 251px 1790px #fff, 96px 1688px #fff, 886px 239px #fff, 778px 150px #fff, 1767px 430px #fff, 765px 1259px #fff, 1189px 877px #fff, 444px 1629px #fff, 1560px 324px #fff, 1952px 1097px #fff, 712px 1173px #fff, 541px 911px #fff, 827px 1420px #fff, 1233px 285px #fff, 784px 546px #fff, 645px 285px #fff, 1273px 1255px #fff, 1821px 174px #fff, 221px 1795px #fff, 1004px 456px #fff, 1298px 941px #fff, 274px 387px #fff, 174px 376px #fff, 1491px 258px #fff, 1489px 1946px #fff, 1134px 1382px #fff, 1289px 1145px #fff, 464px 358px #fff, 1249px 1842px #fff, 1665px 831px #fff, 1982px 84px #fff, 541px 774px #fff, 1994px 523px #fff, 762px 1644px #fff, 1730px 867px #fff, 1951px 1287px #fff, 911px 1691px #fff, 1454px 725px #fff, 1287px 1940px #fff, 70px 564px #fff, 1980px 638px #fff, 1674px 1774px #fff, 1720px 116px #fff, 1747px 182px #fff, 1040px 450px #fff, 1795px 375px #fff, 857px 1471px #fff, 1326px 1730px #fff, 915px 274px #fff, 1224px 358px #fff, 1808px 60px #fff, 43px 1870px #fff, 1810px 1536px #fff, 1564px 1719px #fff, 731px 1388px #fff, 1953px 1967px #fff, 1744px 1119px #fff, 794px 1384px #fff, 959px 714px #fff, 18px 1932px #fff, 1358px 1437px #fff, 355px 939px #fff, 1355px 1648px #fff, 608px 719px #fff, 383px 758px #fff, 1164px 1681px #fff, 1045px 253px #fff, 424px 1279px #fff, 1899px 359px #fff, 379px 488px #fff, 214px 465px #fff, 179px 905px #fff, 830px 1993px #fff, 448px 1077px #fff, 1880px 1354px #fff, 1973px 347px #fff, 745px 1025px #fff, 788px 1007px #fff, 1377px 883px #fff, 6px 290px #fff, 1312px 407px #fff, 1398px 622px #fff, 1405px 339px #fff, 1198px 1709px #fff, 988px 1226px #fff, 87px 1459px #fff, 1113px 1698px #fff, 997px 732px #fff, 708px 331px #fff, 1876px 1112px #fff, 1729px 1797px #fff, 719px 703px #fff, 1295px 522px #fff, 758px 1061px #fff, 1309px 1014px #fff, 1327px 1365px #fff, 854px 1317px #fff, 531px 1001px #fff, 1751px 1040px #fff, 1354px 190px #fff, 800px 1538px #fff, 88px 1455px #fff, 668px 39px #fff, 1379px 41px #fff, 892px 524px #fff, 54px 649px #fff, 1289px 730px #fff, 727px 488px #fff, 181px 842px #fff, 1230px 64px #fff, 3px 857px #fff, 292px 1201px #fff, 1343px 673px #fff, 1096px 1412px #fff, 1520px 292px #fff, 104px 1683px #fff, 934px 1387px #fff, 314px 739px #fff;
          animation: animStar 100s linear infinite;
        }
        #stars2:after {
          content: " ";
          position: absolute;
          top: 2000px;
          width: 2px;
          height: 2px;
          background: transparent;
          box-shadow: inherit;
        }

        #stars3 {
          position: fixed;
          top: 0;
          left: 0;
          z-index: -1;
          width: 3px;
          height: 3px;
          background: transparent;
          box-shadow:
            200px 981px #fff, 1731px 521px #fff, 132px 1039px #fff, 1888px 1547px #fff, 899px 1226px #fff, 1887px 580px #fff, 1548px 1092px #fff, 1626px 689px #fff, 254px 1072px #fff, 1684px 1211px #fff, 672px 1267px #fff, 939px 668px #fff, 1969px 645px #fff, 1126px 983px #fff, 457px 568px #fff, 476px 876px #fff, 829px 1896px #fff, 1364px 1846px #fff, 1507px 1120px #fff, 936px 1948px #fff, 1833px 832px #fff, 1424px 285px #fff, 1377px 1596px #fff, 432px 153px #fff, 1348px 1410px #fff, 1529px 954px #fff, 1102px 387px #fff, 264px 297px #fff, 811px 977px #fff, 1931px 673px #fff, 1734px 978px #fff, 1772px 1567px #fff, 1197px 1400px #fff, 764px 282px #fff, 1103px 822px #fff, 872px 1803px #fff, 1057px 1763px #fff, 52px 1299px #fff, 1312px 1236px #fff, 235px 1082px #fff, 299px 1086px #fff, 1017px 1602px #fff, 1950px 626px #fff, 1306px 132px #fff, 1358px 1618px #fff, 1873px 1718px #fff, 1447px 940px #fff, 1888px 1195px #fff, 1704px 1765px #fff, 872px 1357px #fff, 1555px 1120px #fff, 250px 1415px #fff, 450px 415px #fff, 492px 901px #fff, 170px 1641px #fff, 56px 1129px #fff, 627px 1514px #fff, 1221px 500px #fff, 324px 1895px #fff, 1397px 1775px #fff, 1966px 598px #fff, 1550px 763px #fff, 326px 1605px #fff, 261px 969px #fff, 890px 281px #fff, 736px 544px #fff, 589px 1262px #fff, 1581px 368px #fff, 1900px 1132px #fff, 1914px 585px #fff, 1864px 1517px #fff, 241px 217px #fff, 859px 787px #fff, 996px 1729px #fff, 741px 121px #fff, 418px 414px #fff, 142px 967px #fff, 387px 896px #fff, 703px 562px #fff, 968px 1136px #fff, 1682px 332px #fff, 1287px 846px #fff, 256px 1427px #fff, 1885px 432px #fff, 1739px 1458px #fff, 345px 1769px #fff, 1140px 1612px #fff, 192px 1921px #fff, 920px 471px #fff, 834px 881px #fff, 917px 1803px #fff, 466px 1266px #fff, 483px 1108px #fff, 689px 986px #fff, 1279px 786px #fff, 458px 910px #fff, 1250px 870px #fff, 785px 1654px #fff, 1543px 1757px #fff, 287px 1272px #fff;
          animation: animStar 150s linear infinite;
        }
        #stars3:after {
          content: " ";
          position: absolute;
          top: 2000px;
          width: 3px;
          height: 3px;
          background: transparent;
          box-shadow: inherit;
        }

        @keyframes animStar {
          from { transform: translateY(0px); }
          to { transform: translateY(-2000px); }
        }

        .input {
            border-radius: 8px !important;
            border: 1px solid rgba(0, 255, 170, 0.2) !important;
            background: rgba(255, 255, 255, 0.03) !important;
            backdrop-filter: blur(5px);
            color: #fff !important;
            padding: 15px 45px 15px 20px !important;
            transition: 0.3s !important;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.2) !important;
        }

        .input:focus {
            border-color: #00ffaa !important;
            background: rgba(0, 255, 170, 0.05) !important;
            box-shadow: 0 0 15px rgba(0, 255, 170, 0.3), inset 0 0 10px rgba(0, 255, 170, 0.05) !important;
        }

        .user-label {
            color: #aaa !important;
            left: 20px !important;
        }
        
        .input:focus ~ .user-label, .input:valid ~ .user-label {
            background-color: #102e22 !important;
            color: #00ffaa !important;
            padding: 0 5px !important;
        }

        button {
            background: linear-gradient(90deg, #00ffaa, #00cc88) !important;
            color: #0a1f16 !important;
            border: none !important;
            box-shadow: 0 0 20px rgba(0, 255, 170, 0.4) !important;
            border-radius: 8px !important;
            font-weight: 800 !important;
            text-transform: uppercase;
            letter-spacing: 2px;
            transition: 0.3s !important;
            padding: 15px !important;
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 0 40px rgba(0, 255, 170, 0.8) !important;
            background: #fff !important;
            color: #00ffaa !important;
        }

        /* Specific override for header school name to ensure Cutive Mono is applied */
        header .nameschool {
            font-family: 'Segoe UI', Arial, sans-serif !important;
            font-size: 1.9rem !important; /* Mas malaki at mas malapad */
            text-transform: uppercase;
            letter-spacing: 1.5px !important;
            font-weight: 900 !important; /* Pinaka-makapal na weight */
            background: linear-gradient(to bottom, #ffffff 40%, #00ffaa 100%) !important; /* Metallic transition */
            background-size: 100% auto !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            filter: drop-shadow(0 0 12px rgba(0, 255, 170, 0.4)) !important;
            animation: textGlow 2s ease-in-out infinite alternate !important;
            transition: 0.3s ease;
        }
        @keyframes shine { to { background-position: 200% center; } }
        @keyframes textGlow { from { text-shadow: 0 0 5px rgba(0, 255, 170, 0.2); } to { text-shadow: 0 0 15px rgba(0, 255, 170, 0.6); } }
        @keyframes logoFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); text-shadow: 0 0 30px rgba(0, 255, 170, 0.6); } }
        @keyframes hologram-reveal {
            from { opacity: 0; transform: translateX(-20px); clip-path: polygon(0 0, 0 0, 0 100%, 0 100%); }
            to { opacity: 1; transform: translateX(0); clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%); }
        }
        
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        /* Staggered entrance for form elements */
        .card > *, #studentForm > *, #adminForm > * {
            animation: hologram-reveal 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) backwards;
        }
        .input-group { animation-delay: 0.2s; }
        button { animation-delay: 0.4s; }

        /* --- THEME STYLES --- */
        /* Halloween Theme */
        body.theme-halloween { background: #050202 !important; }
        body.theme-halloween::before { content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to bottom, rgba(0,0,0,0.6), rgba(20,0,0,0.9)), repeating-linear-gradient(45deg, rgba(20,0,0,0.1) 0px, rgba(20,0,0,0.1) 2px, transparent 2px, transparent 10px), radial-gradient(circle at 50% 50%, rgba(40,0,0,0.2), transparent 80%); background-size: cover; z-index: -2; pointer-events: none; }
        body.theme-halloween::after { content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at 50% 0%, rgba(255, 0, 0, 0.1), transparent 70%); opacity: 0.6; animation: lightningFlash 10s infinite; pointer-events: none; z-index: -2; mix-blend-mode: overlay; }
        @keyframes lightningFlash { 0%, 85% { opacity: 0.3; background-color: transparent; } 86% { opacity: 0.8; background-color: rgba(255, 0, 0, 0.15); } 87% { opacity: 0.3; background-color: transparent; } 92% { opacity: 0.3; background-color: transparent; } 93% { opacity: 1; background-color: rgba(255, 50, 50, 0.2); } 94% { opacity: 0.3; background-color: transparent; } 100% { opacity: 0.3; } }
        body.theme-halloween .card { border-color: #800000; background: rgba(10, 5, 5, 0.65); }
        body.theme-halloween .input { border-color: rgba(255, 85, 85, 0.3); }
        body.theme-halloween .input:focus { border-color: #ff5555; box-shadow: 0 0 15px rgba(255, 85, 85, 0.4); }
        body.theme-halloween button { background: #ff5555; color: #fff; }
        body.theme-halloween button:hover { box-shadow: 0 0 25px #ff5555; }
        body.theme-halloween .logo strong { color: #ff5555; }
        body.theme-halloween .slidebar { background: #ff5555; }
        body.theme-halloween .label:hover ~ .slidebar, body.theme-halloween input[class*="rd-"]:checked + label { color: #fff; }
        body.theme-halloween .bar::before, body.theme-halloween .bar::after { background-color: #ff5555; }

        /* Christmas Theme */
        body.theme-christmas { background: linear-gradient(to bottom, #0f2027, #203a43, #2c5364) !important; }
        body.theme-christmas::before { content: ''; position: fixed; top: -50px; left: 0; width: 10px; height: 10px; border-radius: 50%; background: transparent; box-shadow: 5vw 10vh 2px 2px #fff, 15vw 25vh 1px 3px #fff, 25vw 5vh 3px 2px #fff, 35vw 15vh 1px 1px #fff, 45vw 10vh 2px 2px #fff, 55vw 25vh 1px 3px #fff, 65vw 5vh 3px 2px #fff, 75vw 15vh 1px 1px #fff, 85vw 10vh 2px 2px #fff, 95vw 25vh 1px 3px #fff, 10vw 40vh 2px 2px #fff, 30vw 60vh 1px 3px #fff, 50vw 50vh 3px 2px #fff, 70vw 70vh 1px 1px #fff, 90vw 80vh 2px 2px #fff; opacity: 0.8; pointer-events: none; animation: snow 10s linear infinite; z-index: -2; }
        @keyframes snow { 0% { transform: translateY(-10vh); } 100% { transform: translateY(110vh); } }
        body.theme-christmas .card { border-color: #fff; background: rgba(255, 255, 255, 0.1); }
        body.theme-christmas .input { border-color: rgba(255, 255, 255, 0.3); }
        body.theme-christmas .input:focus { border-color: #fff; box-shadow: 0 0 15px rgba(255, 255, 255, 0.4); }
        body.theme-christmas button { background: #e41e3f; color: #fff; }
        body.theme-christmas button:hover { box-shadow: 0 0 25px #e41e3f; }
        body.theme-christmas .logo strong { color: #fff; }
        body.theme-christmas .slidebar { background: #e41e3f; }
        body.theme-christmas .bar::before, body.theme-christmas .bar::after { background-color: #e41e3f; }

        /* Summer Theme */
        body.theme-summer { background: #2980b9 !important; }
        body.theme-summer::before { content:''; position:fixed; top: -100px; right: -100px; width:600px; height:600px; background:radial-gradient(circle, #ffcc00 0%, transparent 60%); opacity:0.6; animation:pulseGlow 4s infinite; z-index: -2; }
        body.theme-summer .card { border-color: #ffcc00; background: rgba(255, 255, 255, 0.1); }
        body.theme-summer .input { border-color: rgba(255, 204, 0, 0.5); }
        body.theme-summer .input:focus { border-color: #ffcc00; box-shadow: 0 0 15px rgba(255, 204, 0, 0.4); }
        body.theme-summer button { background: #ffcc00; color: #000; }
        body.theme-summer button:hover { box-shadow: 0 0 25px #ffcc00; }
        body.theme-summer .logo strong { color: #ffcc00; }
        body.theme-summer .slidebar { background: #ffcc00; }
        body.theme-summer .bar::before, body.theme-summer .bar::after { background-color: #ffcc00; }

        /* New Year Theme */
        body.theme-new_year { background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%) !important; }
        body.theme-new_year .card { border-color: #ffd700; background: rgba(255, 215, 0, 0.05); }
        body.theme-new_year .input { border-color: rgba(255, 215, 0, 0.3); }
        body.theme-new_year .input:focus { border-color: #ffd700; box-shadow: 0 0 15px rgba(255, 215, 0, 0.4); }
        body.theme-new_year button { background: #ffd700; color: #000; }
        body.theme-new_year button:hover { box-shadow: 0 0 25px #ffd700; }
        body.theme-new_year .logo strong { color: #ffd700; }
        body.theme-new_year .slidebar { background: #ffd700; }
        body.theme-new_year .bar::before, body.theme-new_year .bar::after { background-color: #ffd700; }
    </style>
</head>
    <style>
        /* New styles for the About Page */
        #aboutPageContainer {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: none; /* Hidden by default */
            width: 100%;
            max-width: 1100px;
            /* margin: 20px auto; <-- No longer needed for absolute positioning */
            padding: 40px;
            /* Enhanced UI: Tech Background */
            background: linear-gradient(135deg, rgba(5, 20, 15, 0.98) 0%, rgba(0, 10, 5, 0.95) 100%);
            background-image: 
                linear-gradient(rgba(0, 255, 170, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 170, 0.04) 1px, transparent 1px);
            background-size: 30px 30px;
            border: 1px solid rgba(0, 255, 170, 0.2);
            border-radius: 10px; /* Sharper corners like alumni form */
            backdrop-filter: blur(15px);
            box-shadow: 0 0 60px rgba(0, 255, 170, 0.15), inset 0 0 40px rgba(0, 255, 170, 0.05);
            opacity: 0; /* for fade-in/out */
            z-index: 10; /* Ensure it's on top of other elements */
            /* overflow: hidden; Removed to allow scrolling */
            max-height: 90vh;
            overflow-y: auto;

            /* New transition for hologram effect */
            transition: opacity 0.6s cubic-bezier(0.22, 1, 0.36, 1), filter 0.6s ease-out, transform 0.6s cubic-bezier(0.22, 1, 0.36, 1);
            filter: blur(10px);
            transform: translate(-50%, -50%) scale(0.95);
        }

        #aboutPageContainer.visible {
            opacity: 1;
            filter: blur(0);
            transform: translate(-50%, -50%) scale(1);
        }
        
        /* Custom Scrollbar for About Page */
        #aboutPageContainer::-webkit-scrollbar { width: 6px; }
        #aboutPageContainer::-webkit-scrollbar-track { background: rgba(0,0,0,0.3); border-radius: 10px; }
        #aboutPageContainer::-webkit-scrollbar-thumb { background: #00ffaa; border-radius: 10px; }

        /* Tech Corners - adjusted to be more subtle like alumni accents */
        .tech-corner {
            position: absolute;
            width: 20px;
            height: 20px;
            border-color: rgba(0, 255, 170, 0.6);
            border-style: solid;
            transition: all 0.4s ease;
            box-shadow: 0 0 5px rgba(0, 255, 170, 0.3);
            opacity: 0.8;
        }
        .tl { top: 0; left: 0; border-width: 3px 0 0 3px; border-radius: 10px 0 0 0; }
        .tr { top: 0; right: 0; border-width: 3px 3px 0 0; border-radius: 0 10px 0 0; }
        .bl { bottom: 0; left: 0; border-width: 0 0 3px 3px; border-radius: 0 0 0 10px; }
        .br { bottom: 0; right: 0; border-width: 0 3px 3px 0; border-radius: 0 0 10px 0; }
        
        #aboutPageContainer:hover .tech-corner {
            width: 40px; height: 40px;
            box-shadow: 0 0 20px #00ffaa;
            opacity: 1;
        }

        .about-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .about-header h1 {
            font-size: 42px;
            font-weight: 900;
            background: linear-gradient(to bottom, #ffffff 0%, #00ffaa 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 4px;
            text-shadow: 0 0 20px rgba(0, 255, 170, 0.4);
            margin: 0;
        }

        /* Staggered animation for content */
        #aboutPageContainer.visible .about-header,
        #aboutPageContainer.visible .about-text-section,
        #aboutPageContainer.visible .creator-card {
            opacity: 0;
            animation: content-fade-in 0.6s ease-out forwards;
        }

        #aboutPageContainer.visible .about-header { animation-delay: 0.3s; }
        #aboutPageContainer.visible .about-text-section { animation-delay: 0.5s; }
        #aboutPageContainer.visible .creator-card { animation-delay: 0.7s; }

        @keyframes content-fade-in {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .about-content-flex {
            display: flex;
            gap: 40px;
            align-items: flex-start;
        }

        .about-text-section {
            flex: 2;
        }

        .about-text-section h2 {
            color: #00ffaa;
            border-bottom: 1px solid rgba(0, 255, 170, 0.3);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .about-text-section p {
            color: #e4e6eb;
            font-size: 15px;
            line-height: 1.8;
            text-align: justify;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .creator-card {
            flex: 1;
            background: rgba(0, 255, 170, 0.03);
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(0, 255, 170, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }
        /* Grid overlay for creator card similar to alumni profile pic wrapper */
        .creator-card::before {
            content: '';
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background-image: linear-gradient(rgba(0, 255, 170, 0.05) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 255, 170, 0.05) 1px, transparent 1px);
            background-size: 20px 20px;
            pointer-events: none;
            z-index: 0;
        }
        .creator-card > * { position: relative; z-index: 1; }

        .creator-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 255, 170, 0.2);
            border-color: rgba(0, 255, 170, 0.5);
        }

        .creator-img {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #00ffaa;
            margin-bottom: 20px;
            box-shadow: 0 0 25px rgba(0, 255, 170, 0.3);
            transition: transform 0.5s ease;
        }
        .creator-card:hover .creator-img {
            transform: scale(1.05);
            box-shadow: 0 0 35px rgba(0, 255, 170, 0.6);
        }

        .creator-name {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(90deg, #fff, #00ffaa);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .creator-role {
            font-size: 14px;
            color: #00ffaa;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.8;
        }
        
        @keyframes pulseImg {
            0% { box-shadow: 0 0 15px rgba(0, 255, 170, 0.4); }
            50% { box-shadow: 0 0 30px rgba(0, 255, 170, 0.7); border-color: #fff; }
            100% { box-shadow: 0 0 15px rgba(0, 255, 170, 0.4); }
        }

        @media (max-width: 900px) {
            .about-content-flex {
                flex-direction: column;
                align-items: stretch;
                gap: 30px; 
            }
            #aboutPageContainer {
                padding: 30px 20px;
                width: 90%;
                max-height: 80vh;
                /* On mobile, revert to normal flow */
                position: relative;
                top: auto; left: auto;
                transform: scale(0.95); /* Start scale for animation, remove translate */
                margin: 10px auto;
            }

            /* Fix: Override desktop transform on mobile when visible */
            #aboutPageContainer.visible {
                transform: scale(1);
            }

            .about-header h1 {
                font-size: 28px;
                letter-spacing: 2px;
                margin-bottom: 20px;
            }

            .about-text-section p {
                text-align: left; /* Better readability on mobile */
                font-size: 14px;
                line-height: 1.6;
            }

            .creator-card {
                width: 100%;
            }
        }

        /* Flash Message Shared Styles */
        #alumniFlashMessage, #studentFlashMessage, #adminFlashMessage {
            display: none;
            color: #ff5555;
            background: rgba(255, 85, 85, 0.15);
            border: 1px solid rgba(255, 85, 85, 0.5);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 0 20px rgba(255, 85, 85, 0.2);
            text-shadow: 0 0 5px rgba(255, 85, 85, 0.5);
        }
        
        .flash-shake {
            animation: errorShake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }

        @keyframes errorShake {
            0% { transform: translateX(0); }
            15% { transform: translateX(-5px) rotate(-1deg); }
            30% { transform: translateX(4px) rotate(1deg); }
            45% { transform: translateX(-3px) rotate(-1deg); }
            60% { transform: translateX(2px) rotate(1deg); }
            75% { transform: translateX(-1px) rotate(0deg); }
            100% { transform: translateX(0); }
        }
    </style>

<body class="theme-<?php echo htmlspecialchars($current_theme); ?>">

    

    <div id="welcomeOverlay" class="welcome-overlay">
        <div class="welcome-content">
            <h1>Welcome,</h1>
            <h2 id="welcomeName"></h2>
        </div>
    </div>

    <img class="background-logo1" src="<?php echo htmlspecialchars($bg_logo1_src); ?>" alt="">
    <img class="background-logo" src="<?php echo htmlspecialchars($bg_logo2_src); ?>" alt="">

    <header>
        <div class="logo">
            <img class="logostanne" src="assets/images/St.Anne_logo.png" alt="Logo" />
            <Strong class="nameschool">Sacli Connect</Strong>
        </div>

        <!-- From Uiverse.io by GustavoAlmeidaPuff -->
        <input type="checkbox" id="theme-mode" class="mode" hidden="" />
        <div class="container">
            <div class="wrap" style="position: relative; right: 32.3%;">
                <!-- RADIO BUTTONS -->
                <input <?php echo $show_admin ? '' : 'checked=""'; ?> type="radio" id="rd-1" name="radio" class="rd-1" hidden="" />
                <label for="rd-1" class="label" id="backToStudent" style="--index: 0;"><span>Log In</span></label>
                <input <?php echo $show_admin ? 'checked=""' : ''; ?> type="radio" id="rd-2" name="radio" class="rd-2" hidden />
                <label for="rd-2" class="label" id="goToAdmin" style="--index: 1;"><span>Admin</span></label>
                <input type="radio" id="rd-3" name="radio" class="rd-3" hidden />
                <label for="rd-3" class="label" id="goToAlumni" style="--index: 2;"><span>Alumni</span></label>
                <input type="radio" id="rd-4" name="radio" class="rd-4" hidden />
                <label for="rd-4" class="label" id="goToAbout" style="--index: 3;"><span>About</span></label>
                <div class="bar"></div>
                <div class="slidebar"></div>
            </div>
        </div>
    </header>

    <!-- New Intro Animation Layer moved outside to be visible independently of the hidden UI -->
    <div id="videoIntro" class="video-intro-overlay">
        <h1 class="intro-text-glow">
            <span>S</span><span>A</span><span>C</span><span>L</span><span>I</span><span>&nbsp;</span><span>C</span><span>O</span><span>N</span><span>N</span><span>E</span><span>C</span><span>T</span>
        </h1>
        <div class="intro-subtext">// INITIALIZING_UPLINK</div>
    </div>

    <audio id="sacliConnectRevealSound" src="assets/audio/sound intro.mp3" preload="auto"></audio>
    <!-- ST. ANNE COLLEGE LUCENA, INC. VIDEO TEASER.mp4 -->
    <div class="video3" id="videoContainer">
        <video class="preview-video" loop muted playsinline id="myVideo" >
            <source src="<?php echo htmlspecialchars($video_src); ?>" type="video/mp4">
        </video>
        <button id="muteBtn" onclick="toggleMute()">Unmute</button>
    </div>
    <script>
        function toggleMute() {
            const btn = document.getElementById('muteBtn');
            const vid = document.getElementById('myVideo');
            vid.muted = !vid.muted;
            if (vid.muted) {
                btn.textContent = "Unmute";
            } else {
                btn.textContent = "Mute";
            }
        }
    </script>



    <div class="card" id="mainCard">
        <div id="stars"></div>
        <div id="stars2"></div>
        <div id="stars3"></div>
        <!-- Student Login (PHP intact) -->
    <div id="studentForm" style="<?php echo ($show_admin || $show_otp_form) ? 'display:none;' : ''; ?>">
        <img class="icon2" src="assets/images/St.Anne_logo.png" alt="School_logo">
        <div class="logo"><strong>SACLI CONNECT</strong></div>
        <div id="studentFlashMessage"></div>
        <form method="POST">
            <input type="hidden" name="location" id="loginLocation">
            <div class="input-group">
                <input required type="text" name="student_name" class="input" autocomplete="off">
                <label class="user-label">Enter Student Name</label>
            </div>
            <div class="input-group" style="position: relative;">
                <input required type="password" name="student_id" id="student_pass" class="input" autocomplete="off">
                <label class="user-label">Enter Password</label>
            </div>
             
            <p style="text-align: right; margin-top: -10px; margin-bottom: 20px;">
                <a href="Sacli_signup.php" style="color: #00ffaa; text-decoration: none; font-size: 13px; font-weight: bold; opacity: 0.8;">Sign up</a>
            </p>
            <button type="submit">LOG IN</button>
        </form>
        
    </div>

    <!-- Forgot Password Form -->
    <div id="forgotPasswordForm" style="display:none; text-align: center;">
        <img class="icon2" style=" position:relative; right:50%; transform: translateX(-78%);" src="internet.png" alt="Security">
        <div class="logo"><strong>FORGOT PASSWORD</strong></div>
        <p class="paragraph">Enter your registered email to receive a reset link.</p>
        <div id="forgotFlashMessage"></div>
        <form onsubmit="submitForgotRequest(event)">
            <input type="hidden" name="action" value="request_reset">
            <div class="input-group">
                <input required type="email" name="email" class="input" autocomplete="off">
                <label class="user-label">Enter Email Address</label>
            </div>
            <button type="submit">SEND RESET LINK</button>
            <p style="margin-top:20px;"><a href="#" onclick="switchForm(studentForm); return false;" style="color:#00ffaa; text-decoration:none; font-weight:bold;">Back to Login</a></p>
        </form>
    </div>

    <!-- Wait Verification UI -->
    <div id="waitVerificationUI" style="<?php echo $show_wait_ui ? '' : 'display:none;'; ?> text-align: center;">
        <img class="icon2" src="network-access.png" alt="Wait">
        <div class="logo"><strong>WAIT FOR VERIFICATION</strong></div>
        <p class="paragraph" style="color: #00ffaa; font-weight: bold; font-size: 1.1em;">A reset link has been sent to your email.</p>
        <p class="paragraph">Please check your inbox (<span id="maskedEmailDisplay" style="color:#00ffaa; font-weight:bold;"></span>) to proceed with recovery.</p>
        <div style="margin-top: 30px; padding: 20px; border: 1px dashed #00ffaa; border-radius: 10px; background: rgba(0,255,170,0.05);">
            <div style="width: 40px; height: 40px; border: 3px solid rgba(0,255,170,0.3); border-top-color: #00ffaa; border-radius: 50%; margin: 0 auto 15px; animation: spin 1s linear infinite;"></div>
            <p style="font-family: monospace; font-size: 12px; color: #aaa;">Status: WAITING_FOR_UPLINK...</p>
        </div>
        <button onclick="window.location.href='SacliConnect_LOG_IN.php'" style="background:transparent !important; border: 1px solid #00ffaa !important; color: #00ffaa !important; margin-top: 30px;">CANCEL REQUEST</button>
    </div>

    <!-- Reset Password Form -->
    <div id="resetPasswordForm" style="<?php echo $show_reset_form ? '' : 'display:none;'; ?>">
        <img class="icon2" src="Adobe Express - file.png" alt="Reset">
        <div class="logo"><strong>NEW PASSWORD</strong></div>
        <p class="paragraph">Establish a new access code for your account.</p>
        <div id="resetFlashMessage"></div>
        <form method="POST">
            <input type="hidden" name="action" value="finalize_reset">
            <div class="input-group">
                <input required type="password" name="new_password" id="reset_pass" class="input" autocomplete="off">
                <label class="user-label">Enter New Password</label>
            </div>
            <div class="input-group">
                <input required type="password" name="confirm_password" id="reset_conf" class="input" autocomplete="off">
                <label class="user-label">Confirm New Password</label>
            </div>
            <button type="submit">OVERWRITE PASSWORD</button>
        </form>
    </div>

    <!-- Admin Login (separate) -->


    <div id="adminForm" style="<?php echo ($show_admin && !$show_otp_form) ? '' : 'display:none;'; ?>">
        <img class="icon2" src="Adobe Express - file.png" alt="School_logo">
        <div class="logo"><strong style="color:turquoise">ADMIN LOGIN</strong></div>
        <div id="adminFlashMessage"></div>
        <form method="POST" action="admin/admin_login.php">
            <input type="hidden" name="location" id="adminLoginLocation">
            <div class="input-group">
                <input required type="text" name="admin_username" class="input" autocomplete="off" value="">
                <label class="user-label">Username</label>
            </div>
            <div class="input-group" style="position: relative;">
                <input required type="password" name="admin_password" id="admin_pass" class="input" autocomplete="off">
                <label class="user-label">Password <label class="cl-checkbox" style="position:relative; left:210%;" >
                    <input type="checkbox" onchange="togglePass('student_pass', this)">
                </label></label>
            </div>
            <button type="submit">LOGIN</button>
        </form>
    </div>

    <!-- 2FA OTP Form -->
    <div id="otpForm" style="<?php echo $show_otp_form ? '' : 'display:none;'; ?> text-align: center;">
        <img class="icon2" src="network-access.png" alt="Security_logo">
        <div class="logo"><strong style="color: #00ffaa;">SECURITY VERIFICATION</strong></div>
        <p class="paragraph">A 6-digit verification code has been sent to:<br><strong style="color: #00ffaa;"><?php echo htmlspecialchars($mfa_email); ?></strong></p>
        
        <div id="otpFlashMessage" style="<?php echo !empty($error) && $show_otp_form ? 'display:block;' : 'display:none;'; ?>">
            <?php if($show_otp_form) echo "<div class='error'>$error</div>"; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="verify_otp">
            <div class="input-group">
                <input required type="text" name="otp" maxlength="6" class="input" autocomplete="off" style="text-align:center; font-size: 24px; letter-spacing: 10px; font-family: monospace;" placeholder="000000">
                <label class="user-label" style="left: 0; right: 0; text-align: center;">Verification Code</label>
            </div>
            <button type="submit">VERIFY & LOGIN</button>
            <p class="paragraph" style="margin-top: 20px;">
                Didn't receive the code? 
                <a href="SacliConnect_LOG_IN.php" style="color:#00ffaa; text-decoration:none; font-weight:bold;">Try Again</a>
            </p>
        </form>
    </div>

    <!-- Admin Authentication (MFA OTP) Form -->
    <div id="adminOtpForm" style="<?php echo $show_admin_otp ? '' : 'display:none;'; ?> text-align: center;">
        <img class="icon2" src="network-access.png" alt="Admin_Security">
        <div class="logo"><strong style="color: turquoise;">ADMIN AUTHENTICATION</strong></div>
        <p class="paragraph">Authorized access required. Code sent to:<br><strong style="color: #00ffaa;"><?php echo htmlspecialchars($admin_mfa_email); ?></strong></p>
        
        <div id="adminOtpFlashMessage" style="<?php echo !empty($error) && $show_admin_otp ? 'display:block;' : 'display:none;'; ?>">
            <?php if($show_admin_otp && !empty($error)) echo "<div class='error'>$error</div>"; ?>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="verify_admin_otp">
            <div class="input-group">
                <input required type="text" name="otp" maxlength="6" class="input" autocomplete="off" style="text-align:center; font-size: 24px; letter-spacing: 10px; font-family: monospace;" placeholder="000000">
               
            </div>
            <button type="submit">AUTHORIZE UPLINK</button>
            <p class="paragraph" style="margin-top: 20px;">
                Transmission issue? 
                <a href="SacliConnect_LOG_IN.php?show=admin" style="color:turquoise; text-decoration:none; font-weight:bold;">Restart Login</a>
            </p>
        </form>
    </div>

    <!-- Alumni Sign-up Section -->
    <div id="alumniSignupSection" style="display:none;">
        <img class="icon2" src="student.png" alt="Alumni_logo">
        <div class="logo"><strong>ALUMNI SIGN-UP</strong></div>
        <p class="paragraph">Enter your Alumni ID to sign up and connect.</p>
        <form onsubmit="submitAlumniId(); return false;">
            <div id="alumniFlashMessage"></div>
            <div class="input-group">
                <input required type="text" name="alumni_id" class="input" id="alumniIdInput" autocomplete="off">
                <label class="user-label">Enter Alumni ID</label>
            </div>
            <button type="submit">SIGN UP</button>
        </form>
    </div>

    <!-- Social Media Icons and Text (Inside the card) -->
    <div style="margin-top: 30px; border-top: 1px solid rgba(0, 255, 170, 0.2); padding: 10px;">
        <!-- From Uiverse.io by javierBarroso -->
        <div class="parent">
            <div class="child child-1">
                <a href="https://www.facebook.com/St.AnneCollegeLucenaIncOfficial" rel="noopener noreferrer" title="Twitter/X">
                    <button class="button btn-1"><svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 512 512" fill="#1e90ff"><path d="M459.37 151.716c.325 4.548.325 9.097.325 13.645 0 138.72-105.583 298.558-298.558 298.558-59.452 0-114.68-17.219-161.137-47.106 8.447.974 16.568 1.299 25.34 1.299 49.055 0 94.213-16.568 130.274-44.832-46.132-.975-84.792-31.188-98.112-72.772 6.498.974 12.995 1.624 19.818 1.624 9.421 0 18.843-1.3 27.614-3.573-48.081-9.747-84.143-51.98-84.143-102.985v-1.299c13.969 7.797 30.214 12.67 47.431 13.319-28.264-18.843-46.781-51.005-46.781-87.391 0-19.492 5.197-37.36 14.294-52.954 51.655 63.675 129.3 105.258 216.365 109.807-1.624-7.797-2.599-15.918-2.599-24.04 0-57.828 46.782-104.934 104.934-104.934 30.213 0 57.502 12.67 76.67 33.137 23.715-4.548 46.456-13.32 66.599-25.34-7.798 24.366-24.366 44.833-46.132 57.827 21.117-2.273 41.584-8.122 60.426-16.243-14.292 20.791-32.161 39.308-52.628 54.253z"></path></svg></button>
                </a>
            </div>
            <div class="child child-2">
                <a href="https://www.facebook.com/St.AnneCollegeLucenaIncOfficial" rel="noopener noreferrer" title="Instagram">
                    <button class="button btn-2"><svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 448 512" fill="#ff00ff"><path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"></path></svg></button>
                </a>
            </div>
            <div class="child child-3">
                <a href="https://www.facebook.com/St.AnneCollegeLucenaIncOfficial" rel="noopener noreferrer" title="GitHub">
                    <button class="button btn-3"><svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 496 512"><path d="M165.9 397.4c0 2-2.3 3.6-5.2 3.6-3.3.3-5.6-1.3-5.6-3.6 0-2 2.3-3.6 5.2-3.6 3-.3 5.6 1.3 5.6 3.6zm-31.1-4.5c-.7 2 1.3 4.3 4.3 4.9 2.6 1 5.6 0 6.2-2s-1.3-4.3-4.3-5.2c-2.6-.7-5.5.3-6.2 2.3zm44.2-1.7c-2.9.7-4.9 2.6-4.6 4.9.3 2 2.9 3.3 5.9 2.6 2.9-.7 4.9-2.6 4.6-4.6-.3-1.9-3-3.2-5.9-2.9zM244.8 8C106.1 8 0 113.3 0 252c0 110.9 69.8 205.8 169.5 239.2 12.8 2.3 17.3-5.6 17.3-12.1 0-6.2-.3-40.4-.3-61.4 0 0-70 15-84.7-29.8 0 0-11.4-29.1-27.8-36.6 0 0-22.9-15.7 1.6-15.4 0 0 24.9 2 38.6 25.8 21.9 38.6 58.6 27.5 72.9 20.9 2.3-16 8.8-27.1 16-33.7-55.9-6.2-112.3-14.3-112.3-110.5 0-27.5 7.6-41.3 23.6-58.9-2.6-6.5-11.1-33.3 2.6-67.9 20.9-6.5 69 27 69 27 20-5.6 41.5-8.5 62.8-8.5s42.8 2.9 62.8 8.5c0 0 48.1-33.6 69-27 13.7 34.7 5.2 61.4 2.6 67.9 16 17.7 25.8 31.5 25.8 58.9 0 96.5-58.9 104.2-114.8 110.5 9.2 7.9 17 22.9 17 46.4 0 33.7-.3 75.4-.3 83.6 0 6.5 4.6 14.4 17.3 12.1C428.2 457.8 496 362.9 496 252 496 113.3 383.5 8 244.8 8zM97.2 352.9c-1.3 1-1 3.3.7 5.2 1.6 1.6 3.9 2.3 5.2 1 1.3-1 1-3.3-.7-5.2-1.6-1.6-3.9-2.3-5.2-1zm-10.8-8.1c-.7 1.3.3 2.9 2.3 3.9 1.6 1 3.6.7 4.3-.7.7-1.3-.3-2.9-2.3-3.9-2-.6-3.6-.3-4.3.7zm32.4 35.6c-1.6 1.3-1 4.3 1.3 6.2 2.3 2.3 5.2 2.6 6.5 1 1.3-1.3.7-4.3-1.3-6.2-2.2-2.3-5.2-2.6-6.5-1zm-11.4-14.7c-1.6 1-1.6 3.6 0 5.9 1.6 2.3 4.3 3.3 5.6 2.3 1.6-1.3 1.6-3.9 0-6.2-1.4-2.3-4-3.3-5.6-2z"></path></svg></button>
                </a>
            </div>
            <div class="child child-4">
                <a href="https://www.facebook.com/St.AnneCollegeLucenaIncOfficial" rel="noopener noreferrer" title="Facebook">
                    <button class="button btn-4"><svg xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 320 512" fill="#4267B2"><path d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06h40.42V6.26S260.43 0 225.36 0c-73.22 0-121.08 44.38-121.08 124.72v70.62H22.89V288h81.39v224h100.17V288z"></path></svg></button>
                </a>
            </div>
        </div>
        <div style="text-align: center; padding: 5%;">
            <p class="paragraph">For more information, please follow the</p>
            <p class="paragraph">St. Anne College Inc page.</p>
            <p class="paragraph">Just click the icon.</p>
        </div>
    </div>

    </div>

    <!-- New About Page Container -->
    <div id="aboutPageContainer">
        <!-- Tech Corners -->
        <div class="tech-corner tl"></div>
        <div class="tech-corner tr"></div>
        <div class="tech-corner bl"></div>
        <div class="tech-corner br"></div>

        <div class="about-header">
            <h1>About SacliConnect</h1>
        </div>
        <div class="about-content-flex">
            <div class="about-text-section">
                <h2>System Overview</h2>
                <p>
                    SacliConnect is the official centralized web-based platform for St. Anne College Lucena, Inc. (SACLI). 
                    It bridges the gap between traditional campus life and the digital world, unifying students, faculty, alumni, 
                    and administrators into one cohesive ecosystem. By streamlining communication and academic management, 
                    it ensures that every member of the community stays connected and informed.
                </p>
                <h2>Core Features</h2>
                <ul style="color: #b0fce0; padding-left: 20px; line-height: 1.6; margin-bottom: 25px; list-style-type: none;">
                    <li style="margin-bottom: 10px;"><strong style="color:#00ffaa">Dynamic Social Feed:</strong> A central hub for official announcements, events, and community updates.</li>
                    <li style="margin-bottom: 10px;"><strong style="color:#00ffaa">SacliRoom:</strong> An integrated learning management system for virtual classrooms, assignments, and submissions.</li>
                    <li style="margin-bottom: 10px;"><strong style="color:#00ffaa">Real-Time Communication:</strong> Direct messaging and group chats to facilitate seamless interaction.</li>
                    <li style="margin-bottom: 10px;"><strong style="color:#00ffaa">Alumni Tracking:</strong> A dedicated portal for graduates to connect, network, and maintain ties with the alma mater.</li>
                </ul>
                <h2>Project Vision</h2>
                <p>
                    This project addresses the challenges of a fragmented digital landscape by eliminating information silos and 
                    inefficiencies. SacliConnect aims to serve as the authoritative digital hub for the institution, fostering 
                    a more connected, efficient, and engaged SACLI community.
                </p>
            </div>
            <div class="creator-card">
                <img src="Screenshot 2024-09-05 221942.png" alt="Creator" class="creator-img">
                <h3 class="creator-name">Justin Ritardo</h3>
                <p class="creator-role">System Developer & Creator</p>
            </div>
        </div>
    </div>

    <footer>
        &copy; 2026 SACLICONNECT // ᜐ ᜇᜒᜌᜓᜐ᜔ ᜀᜅ᜔ ᜃᜎᜓᜏᜎ᜔ᜑᜆᜒᜀᜈ᜔ // CREATE BY: Justin Ritardo
    </footer>


<script>
const synth = window.speechSynthesis;
let voices = [];

function loadVoices() {
    voices = synth.getVoices();
}

window.addEventListener('load', requestLocation);

// --- VIDEO BEAT GLOW EFFECT ---
document.addEventListener('DOMContentLoaded', function() {
    const video = document.getElementById('myVideo');
    const videoContainer = document.getElementById('videoContainer'); // This is the .video3 element

    function toggleBeatGlow() {
        // Apply glow if video is playing AND not muted AND has some volume
        if (!video.paused && !video.muted && video.volume > 0) {
            videoContainer.classList.add('beat-active');
        } else {
            videoContainer.classList.remove('beat-active');
        }
    }

    // Listen for changes in video state
    video.addEventListener('play', toggleBeatGlow);
    video.addEventListener('pause', toggleBeatGlow);
    video.addEventListener('volumechange', toggleBeatGlow); // Important for mute/unmute and volume changes
    video.addEventListener('ended', toggleBeatGlow);
});

// Handle Video Intro Overlay Timeout
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const skipIntro = urlParams.get('no_intro') === '1';
    if (skipIntro) return; // Wag patunugin ang reveal sound kung galing sa logout

    const revealSound = document.getElementById('sacliConnectRevealSound');
    if (revealSound) revealSound.play().catch(e => console.warn("Reveal sound blocked by browser policy. Usually needs a click."));
});

const handleIntroReveal = () => {
    const intro = document.getElementById('videoIntro');
    const vid = document.getElementById('myVideo');
    const muteBtn = document.getElementById('muteBtn');
    const header = document.querySelector('header');
    const card = document.getElementById('mainCard');
    const videoContainer = document.getElementById('videoContainer');
    const footer = document.querySelector('footer');

    const urlParams = new URLSearchParams(window.location.search);
    const skipIntro = urlParams.get('no_intro') === '1';

    if(intro) {
        if (skipIntro) {
            intro.style.display = 'none'; // Alisin agad ang black overlay
        } else {
            intro.classList.add('fade-out-intro');
        }
        if(vid) {
            vid.classList.add('reveal-active');
            vid.play(); // Dito lang magsisimula ang video paglabas ng teaser
        }
        if(muteBtn) muteBtn.classList.add('visible');
        if(header) header.classList.add('reveal-ui');
        if(card) card.classList.add('reveal-ui');
        if(footer) footer.classList.add('reveal-ui');
        if(videoContainer) videoContainer.classList.add('reveal-ui');
    }
};

const urlParamsCheck = new URLSearchParams(window.location.search);
if (urlParamsCheck.get('no_intro') === '1') {
    // Reveal UI agad-agad pag nag-log out
    handleIntroReveal();
} else {
    setTimeout(handleIntroReveal, 10000); // Standard 10 seconds delay para sa fresh login
}

if (speechSynthesis.onvoiceschanged !== undefined) {
    speechSynthesis.onvoiceschanged = loadVoices;
}
function speakOnce(text) {
    if (synth.getVoices().length === 0) {
        synth.addEventListener('voiceschanged', () => speakOnce(text), { once: true });
        return;
    }
    if (synth.speaking) return;
    const utterThis = new SpeechSynthesisUtterance(text);
    const jarvisVoice = synth.getVoices().find(v => v.name.includes('Google UK English Male') || v.name.includes('Daniel') || (v.lang === 'en-GB' && v.name.includes('Male'))) || voices.find(v => v.lang === 'en-GB') || voices.find(v => v.lang === 'en-US');
    if (jarvisVoice) utterThis.voice = jarvisVoice;
    utterThis.rate = 0.9;
    utterThis.pitch = 0.85;
    synth.speak(utterThis);
}

function requestLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(position => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
            .then(res => res.json())
            .then(data => {
                let parts = data.address;
                let simple = (parts.suburb || parts.neighbourhood || "") + ", " + (parts.city || parts.town || "") + ", " + (parts.province || "");
                simple = simple.replace(/^, /, "").replace(/, ,/g, ",");
                const resolved = simple || data.display_name;
                document.getElementById('loginLocation').value = resolved;
                document.getElementById('adminLoginLocation').value = resolved;
            });
        }, null, { enableHighAccuracy: true });
    }
}

function togglePass(inputId, checkbox) {
    const input = document.getElementById(inputId);
    if (checkbox.checked) {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

const videoContainer = document.getElementById('videoContainer');
const mainCard = document.getElementById('mainCard');
const aboutPageContainer = document.getElementById('aboutPageContainer');

const studentBtn = document.getElementById('backToStudent');
const adminBtn   = document.getElementById('goToAdmin');
const aboutBtn   = document.getElementById('goToAbout');
const alumniBtn  = document.getElementById('goToAlumni');

const studentForm = document.getElementById('studentForm');
const adminForm   = document.getElementById('adminForm');
const alumniSignupSection = document.getElementById('alumniSignupSection');
const otpForm = document.getElementById('otpForm');
const adminOtpForm = document.getElementById('adminOtpForm');
const forgotPasswordForm = document.getElementById('forgotPasswordForm');
const waitVerificationUI = document.getElementById('waitVerificationUI');
const resetPasswordForm = document.getElementById('resetPasswordForm');

const allForms = [studentForm, adminForm, alumniSignupSection, otpForm, adminOtpForm, forgotPasswordForm, waitVerificationUI, resetPasswordForm];

let isSwitchingForms = false;

function switchForm(showEl) {
    // Sync the radio buttons visually based on the form being shown
    if (showEl === studentForm) document.getElementById('rd-1').checked = true;
    else if (showEl === adminForm) document.getElementById('rd-2').checked = true;
    else if (showEl === alumniSignupSection) document.getElementById('rd-3').checked = true;
    else if (showEl === aboutPageContainer) document.getElementById('rd-4').checked = true;

    // Clear errors when switching
    allForms.forEach(form => {
        form.querySelectorAll('input:not([type="button"]):not([type="submit"]):not([readonly])').forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else {
                input.value = '';
                // Reset password fields to masked type if they were toggled visible
                if (input.id.includes('pass')) input.type = 'password';
            }
        });
        const flash = form.querySelector('[id*="FlashMessage"]');
        if (flash) flash.style.display = 'none';
    });

    // Handle showing/hiding the main containers (card vs about page)
    if (showEl === aboutPageContainer) {
        if (isSwitchingForms) return;
        isSwitchingForms = true;

        // Switching TO About page
        mainCard.style.opacity = 0;
        videoContainer.style.opacity = 0;

        setTimeout(() => {
            mainCard.style.display = 'none';
            videoContainer.style.display = 'none';
            
            showEl.style.display = 'block';
            // Use a minimal timeout to ensure display:block is rendered before adding the class
            setTimeout(() => {
                showEl.classList.add('visible'); // Add class to trigger animation
                isSwitchingForms = false;
            }, 50);
        }, 400); // match transition duration
    } else {
        // Switching FROM About page (or between forms in the main card)
        const wasAboutPage = aboutPageContainer.style.display === 'block';
        
        aboutPageContainer.classList.remove('visible'); // Remove class to trigger fade-out
        
        setTimeout(() => {
            aboutPageContainer.style.display = 'none';

            mainCard.style.display = 'block';
            videoContainer.style.display = ''; // Reset to CSS default (hidden on mobile)

            setTimeout(() => {
                mainCard.style.opacity = 1;
                videoContainer.style.opacity = 1;
            }, 10);

            // Now handle switching forms inside the card
            allForms.forEach(form => {
                if (form !== showEl) {
                    form.style.display = 'none';
                }
            });
            showEl.style.display = 'block';
            
        }, wasAboutPage ? 500 : 0); // No delay if just switching cards
    }
}

function submitForgotRequest(event) {
    event.preventDefault();
    const form = event.target;
    const btn = form.querySelector('button');
    const emailInput = form.querySelector('input[name="email"]');
    const email = emailInput.value.trim();
    if (!email) return;

    // Ipakita muna ang loading state sa button sa halip na lumipat agad ng screen
    const originalText = btn.innerText;
    btn.innerText = "VERIFYING IDENTITY...";
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'request_reset');
    formData.append('email', email);

    fetch('SacliConnect_LOG_IN.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            // TAMA ANG EMAIL: Dito pa lang natin ipapakita ang Wait UI at animation
            switchForm(waitVerificationUI);
            document.getElementById('maskedEmailDisplay').innerText = data.email;
        } else if (data.status === 'error') {
            // MALI ANG EMAIL: Mananatili sa form at magpapakita ng error
            showFormError('forgotFlashMessage', data.message);
            btn.innerText = originalText;
            btn.disabled = false;
        }
    })
    .catch(err => {
        // Ipakita ang totoong error message para sa debugging
        showFormError('forgotFlashMessage', 'CONNECTION_LOST: ' + err.message);
        btn.innerText = originalText;
        btn.disabled = false;
    });
}

function submitAlumniId() {
        var alumniId = document.getElementById('alumniIdInput').value;
        var flashMsg = document.getElementById('alumniFlashMessage');
        flashMsg.style.display = 'none'; // Hide previous error

        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'check_alumni_id.php?student_id=' + alumniId, true);
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.status === 'exists') {
                        // Trigger Animation
                        var overlay = document.getElementById('welcomeOverlay');
                        document.getElementById('welcomeName').innerText = response.name;
                        overlay.style.display = 'flex';
                        void overlay.offsetWidth; // Force reflow
                        overlay.classList.add('show');

                        // Speak the welcome message
                        speakOnce("Regonized you as " + response.name + ". Welcome to Sacli Connect, ");

                        setTimeout(function() {
                            document.body.classList.add('fade-out');
                            setTimeout(function() {
                                window.location.href = 'alumni_signup.php?student_id=' + encodeURIComponent(alumniId);
                            }, 800);
                        }, 2000);
                    } else if (response.status === 'already_registered') {
                        showFormError('alumniFlashMessage', 'this id number is already created a account');
                    } else {
                        showFormError('alumniFlashMessage', 'Alumni ID not found. Please contact the admin.');
                    }
                } catch (e) {
                    showFormError('alumniFlashMessage', 'Error parsing server response.');
                }
            } else {
                showFormError('alumniFlashMessage', 'An error occurred while checking the Alumni ID.');
            }
        };
        xhr.onerror = function () { showFormError('alumniFlashMessage', 'An error occurred while checking the Alumni ID.'); };
        xhr.send();
        return false;
    }

    let flashTimeout;

    function showFormError(elementId, message) {
        var flashMsg = document.getElementById(elementId);
        flashMsg.innerText = message;
        flashMsg.style.display = 'block';
        flashMsg.classList.remove('flash-shake'); // Reset animation to trigger it again
        void flashMsg.offsetWidth; // Force reflow
        flashMsg.classList.add('flash-shake');

        // Clear existing timer if user triggers error again quickly
        if (flashTimeout) clearTimeout(flashTimeout);

        // Set new timer to hide after 5 seconds
        flashTimeout = setTimeout(() => {
            flashMsg.style.display = 'none';
        }, 5000);
    }

// Initial setup on load
document.addEventListener('DOMContentLoaded', () => {
    <?php if($show_otp_form): ?>
        // Ipakita ang OTP form nang hindi tinatawag ang switchForm(otpForm)
        // para hindi ma-clear ang error message kung mali ang nilagay na code.
        allForms.forEach(f => f.style.display = 'none');
        otpForm.style.display = 'block';
        
        // Show OTP error if exists
        <?php if(!empty($error)): ?>showFormError('otpFlashMessage', "<?php echo addslashes($error); ?>");<?php endif; ?>
    <?php elseif($show_admin_otp): ?>
        allForms.forEach(f => f.style.display = 'none');
        adminOtpForm.style.display = 'block';
        <?php if(!empty($error)): ?>showFormError('adminOtpFlashMessage', "<?php echo addslashes($error); ?>");<?php endif; ?>
    <?php elseif($show_wait_ui): ?>
        allForms.forEach(f => f.style.display = 'none');
        waitVerificationUI.style.display = 'block';
    <?php elseif($show_reset_form): ?>
        allForms.forEach(f => f.style.display = 'none');
        resetPasswordForm.style.display = 'block';
        <?php if(!empty($error)): ?>showFormError('resetFlashMessage', "<?php echo addslashes($error); ?>");<?php endif; ?>
    <?php else: ?>
        const params = new URLSearchParams(window.location.search);
        if (params.get('show') === 'admin') {
            switchForm(adminForm);
        } else {
            // Show Admin/Student Errors from PHP
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('admin_error')) {
                switchForm(adminForm);
                showFormError('adminFlashMessage', urlParams.get('admin_error'));
            } else {
                allForms.forEach(f => f.style.display = 'none');
                studentForm.style.display = 'block';
                <?php if(!empty($error)): ?>showFormError('studentFlashMessage', "<?php echo addslashes($error); ?>");<?php endif; ?>
            }
        }
    <?php endif; ?>
});

// Buttons
studentBtn.addEventListener('click', e => {
    switchForm(studentForm);
});

adminBtn.addEventListener('click', e => {
    switchForm(adminForm);
});

aboutBtn.addEventListener('click', e => {
    switchForm(aboutPageContainer);
});

alumniBtn.addEventListener('click', e => {
    switchForm(alumniSignupSection);
});

</script>

</body>


</html>