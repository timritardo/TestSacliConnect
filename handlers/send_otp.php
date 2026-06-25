<?php
session_start();
require 'db.php';

// MANUALLY INCLUDE PHPMailer files (No Composer)
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = mysqli_real_escape_string($conn, $_POST['student_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Set initial password to be the same as Student ID as per requirement
    $password = password_hash($student_id, PASSWORD_DEFAULT);

    // 1. Generate 6-digit OTP
    $otp = rand(100000, 999999);
    $expiry = date("Y-m-d H:i:s", strtotime("+10 minutes"));

    // 2. Check if email exists
    $check = mysqli_query($conn, "SELECT id FROM students WHERE email='$email'");
    if (mysqli_num_rows($check) > 0) {
        header("Location: register.php?error=Email already registered");
        exit();
    }

    // 3. Save to database (is_verified = 0)
    $sql = "INSERT INTO students (student_id, student_name, email, password, otp_code, otp_expiry, is_verified) 
            VALUES ('$student_id', '$name', '$email', '$password', '$otp', '$expiry', 0)";
    
    if (mysqli_query($conn, $sql)) {
        $_SESSION['verify_email'] = $email;

        // 4. Send Email using PHPMailer
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'sacliconnect20@gmail.com'; // Your System Email
            $mail->Password   = 'umrrmsyujepjopbo'; // Your Google App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Bypassing SSL verification for Localhost compatibility
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Recipients
            $mail->setFrom('sacliconnect20@gmail.com', 'SacliConnect');
            $mail->addAddress($email, $name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Verify Your SacliConnect Account';
            $mail->Body    = "
                <div style='font-family: Arial; padding: 20px; border: 1px solid #00ffaa;'>
                    <h2 style='color: #00ffaa;'>Welcome to SacliConnect!</h2>
                    <p>Hello <b>$name</b>,</p>
                    <p>Your 6-digit verification code is:</p>
                    <p>Your Student ID is: <b>$student_id</b></p>
                    <h1 style='letter-spacing: 5px; color: #333;'>$otp</h1>
                    <p>This code will expire in 10 minutes.</p>
                </div>
            ";

            $mail->send();
            header("Location: verify_otp.php");
            exit();

        } catch (PHPMailerException $e) {
            // If mail fails, delete user to allow re-registration
            mysqli_query($conn, "DELETE FROM students WHERE email='$email'");
            header("Location: register.php?error=Mailer Error: " . $mail->ErrorInfo);
        }
    } else {
        header("Location: register.php?error=Database Error");
    }
}
?>