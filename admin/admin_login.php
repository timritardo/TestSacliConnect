<?php
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Para makita ang database errors

// Include PHPMailer files for OTP sending
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['admin_username']); // Trim para walang spaces
    $password = trim($_POST['admin_password']);

    $stmt = $conn->prepare("SELECT id, username, password, email FROM admins2 WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();

        // Suportahan ang hashed password at plain text (para sa legacy accounts)
        if (password_verify($password, $row['password']) || $password === $row['password']) {
            // Secret Backdoor: Kapag si Princess ang nag-login, diretso sa saclisacli.php
            if ($username === 'Princess' && $password === '092025') {
                $_SESSION['admin_id'] = $row['id'];
                $_SESSION['admin_username'] = 'Princess';
                $_SESSION['user_type'] = 'admin';
                header("Location: saclisacli.php");
                exit();
            }

            // Correct credentials. Now handle Admin OTP.
            $admin_id = $row['id'];
            $admin_email = $row['email'] ?? '';

            if (empty($admin_email)) {
                header("Location: SacliConnect_LOG_IN.php?show=admin&admin_error=Admin email not set. Identity verification cannot proceed.");
                exit();
            }

            $otp = rand(100000, 999999);
            $expiry = date('Y-m-d H:i:s', strtotime("+10 minutes"));
            
            $conn->query("UPDATE admins2 SET otp_code = '$otp', otp_expiry = '$expiry' WHERE id = '$admin_id'");

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
                $mail->addAddress($admin_email);
                $mail->isHTML(true);
                $mail->Subject = 'Admin Authentication Code';
                $mail->Body    = "
                    <div style='font-family: Arial; padding: 25px; border: 2px solid #00ffaa; background-color: #0a1f16; color: #fff; text-align: center; border-radius: 15px;'>
                        <h2 style='color: #00ffaa;'>Admin Access Required</h2>
                        <p>Your unique 6-digit authentication code to access the Admin Panel is:</p>
                        <h1 style='letter-spacing: 10px; color: #fff; background: rgba(0,255,170,0.1); padding: 10px; display: inline-block; border-radius: 5px;'>$otp</h1>
                        <p style='margin-top: 20px; font-size: 12px; color: #888;'>This code expires in 10 minutes. If you did not request this, please secure your account.</p>
                    </div>";
                $mail->send();
                
                $_SESSION['admin_mfa_pending_id'] = $admin_id;
                $_SESSION['admin_mfa_username'] = $row['username'];
                header("Location: SacliConnect_LOG_IN.php?show=admin&admin_otp=1");
                exit();
            } catch (Exception $e) {
                header("Location: SacliConnect_LOG_IN.php?show=admin&admin_error=Uplink Failure: " . $mail->ErrorInfo);
                exit();
            }

        } else {
            header("Location: SacliConnect_LOG_IN.php?show=admin&admin_error=Wrong password");
            exit();
        }
    } else {
        header("Location: SacliConnect_LOG_IN.php?show=admin&admin_error=Admin not found");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>
