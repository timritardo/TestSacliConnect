<?php
error_reporting(0); // Pigilan ang PHP warnings na sumira sa JSON response
session_start();
require_once __DIR__ . '/../config/database.php';

// Include PHPMailer Files
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: application/json');

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- STEP 1: I-verify ang Email at mag-send ng OTP ---
    if ($_POST['action'] === 'forgot_send_otp') {
        $email = $conn->real_escape_string(trim($_POST['email']));

        // Tignan kung existing ang email sa Students o Teachers
        $stmt = $conn->prepare("SELECT email, 'student' as type FROM students WHERE email = ? UNION SELECT email, 'teacher' as type FROM teachers WHERE email = ?");
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'this email is not register']);
            exit();
        }

        $user = $result->fetch_assoc();
        $otp = rand(100000, 999999);
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // I-save ang OTP sa database para sa verification mamaya
        $table = ($user['type'] === 'student') ? 'students' : 'teachers';
        $update_stmt = $conn->prepare("UPDATE $table SET otp_code = ?, otp_expiry = ? WHERE email = ?");
        $update_stmt->bind_param("sss", $otp, $expiry, $email);
        $update_stmt->execute();

        // dito mag seset ngani pakak
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'sacliconnect20@gmail.com';
            $mail->Password   = 'umrrmsyujepjopbo'; 
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->Timeout    = 15;
            $mail->SMTPOptions = array('ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true));
            
            $mail->setFrom('sacliconnect20@gmail.com', 'SacliConnect Support'); 
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Your Verification Code';
            $mail->Body    = "Hi! Your password reset code is: <h2 style='color:#00ffaa;'>$otp</h2> This code will expire in 10 minutes.";
            
            $mail->send();
            echo json_encode(['status' => 'success', 'message' => 'Verification code sent to your email!']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Mailer Error: Could not send code.']);
        }
        exit();
    }

    // --- STEP 2: I-verify ang OTP at i-update ang Password ---
    if ($_POST['action'] === 'forgot_reset_pass') {
        $email = $conn->real_escape_string($_POST['email']);
        $otp = $conn->real_escape_string($_POST['otp']);
        $new_pass = $_POST['new_password'];
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

        // Check validity sa Students table
        $s_check = $conn->query("SELECT student_id FROM students WHERE email='$email' AND otp_code='$otp' AND otp_expiry > NOW()");
        // Check validity sa Teachers table
        $t_check = $conn->query("SELECT id FROM teachers WHERE email='$email' AND otp_code='$otp' AND otp_expiry > NOW()");

        if ($s_check->num_rows > 0) {
            $conn->query("UPDATE students SET password='$hashed_pass', otp_code=NULL, otp_expiry=NULL WHERE email='$email'");
            echo json_encode(['status' => 'success', 'message' => 'Success! Password updated.']);
        } elseif ($t_check->num_rows > 0) {
            $conn->query("UPDATE teachers SET password='$new_pass', otp_code=NULL, otp_expiry=NULL WHERE email='$email'");
            echo json_encode(['status' => 'success', 'message' => 'Success! Password updated.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired verification code.']);
        }
        exit();
    }
}
?>