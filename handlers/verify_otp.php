<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['verify_email'])) {
    header("Location: register.php");
    exit();
}

$msg = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_SESSION['verify_email'];
    $otp_input = mysqli_real_escape_string($conn, $_POST['otp']);

    // Check OTP in database
    $sql = "SELECT * FROM users WHERE email='$email' AND otp_code='$otp_input' AND otp_expiry > NOW()";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        // Update user as verified
        mysqli_query($conn, "UPDATE users SET is_verified = 1, otp_code = NULL WHERE email='$email'");
        unset($_SESSION['verify_email']);
        $msg = "<p style='color:#00ffaa;'>Verification Successful! You can now <a href='SacliConnect_LOG_IN.php' style='color:#fff;'>Login</a></p>";
    } else {
        $msg = "<p style='color:red;'>Invalid or expired OTP code.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP - SacliConnect</title>
    <style>
        body { display: flex; justify-content: center; align-items: center; height: 100vh; background: #0a1f16; color: white; font-family: sans-serif; }
        .box { background: #1a3d2f; padding: 30px; border-radius: 15px; border: 1px solid #00ffaa; text-align: center; }
        input { padding: 15px; font-size: 24px; text-align: center; letter-spacing: 5px; width: 200px; border-radius: 10px; border: 1px solid #00ffaa; background: #000; color: #00ffaa; margin-bottom: 20px; }
        button { display: block; width: 100%; padding: 10px; background: #00ffaa; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Enter 6-Digit Code</h2>
        <p>Sent to: <?php echo $_SESSION['verify_email']; ?></p>
        <form method="POST">
            <input type="text" name="otp" maxlength="6" placeholder="000000" required autofocus>
            <button type="submit">Verify Account</button>
        </form>
        <?php echo $msg; ?>
    </div>
</body>
</html>