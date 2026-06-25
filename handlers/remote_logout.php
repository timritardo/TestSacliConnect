<?php
/**
 * remote_logout.php
 * Handles the "NO, LOGOUT" security feature.
 * Immediately blocks logins and forces a password change.
 */
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed");

$user_id = $_GET['id'] ?? $_POST['id'] ?? '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = "";
$success = false;

if (empty($user_id) || empty($token)) {
    die("Invalid access parameters.");
}

// Identify user type
$type = (strpos($user_id, 'T-') === 0) ? 'teacher' : 'student';
$table = ($type === 'teacher') ? 'teachers' : 'students';
$id_col = ($type === 'teacher') ? 'id' : 'student_id';
$real_id = ($type === 'teacher') ? str_replace('T-', '', $user_id) : $user_id;

// Verify token
$stmt = $conn->prepare("SELECT id FROM $table WHERE $id_col = ? AND logout_token = ?");
$stmt->bind_param("ss", $real_id, $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("<div style='font-family: Arial; text-align: center; margin-top: 100px;'><h1>LINK EXPIRED</h1><p>This security link is no longer valid or has already been used.</p></div>");
}

// Handle Password Change Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $new_pass = $_POST['new_password'];
    $conf_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($new_pass !== $conf_pass) {
        $error = "Passwords do not match.";
    } else {
        // Format: Hash for students, plain for teachers (matching your system logic)
        $final_pass = ($type === 'student') ? password_hash($new_pass, PASSWORD_DEFAULT) : $new_pass;

        // UPDATE DB: Set new password, lift block (force_logout = 0), and clear token
        $update = $conn->prepare("UPDATE $table SET password = ?, force_logout = 0, logout_token = NULL, is_online = 0 WHERE $id_col = ?");
        $update->bind_param("ss", $final_pass, $real_id);
        
        if ($update->execute()) {
            // Revoke all other active device sessions for this user
            $conn->query("DELETE FROM user_active_sessions WHERE user_id = '$user_id'");
            $success = true;
        } else {
            $error = "System error during update. Please try again.";
        }
    }
} else {
    // Ito ang unang beses na na-access ang remote_logout.php (GET request mula sa email).
    // Agad na i-set ang 'force_logout = 1' para i-block ang lahat ng login attempts.
    $conn->query("UPDATE $table SET force_logout = 1 WHERE $id_col = '$real_id'");
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Security Protocol: Reset Access</title>
    <link rel="stylesheet" href="assets/css/2_Login.css">
    <style>
        body { background: radial-gradient(circle at center, #133a2b 0%, #000502 100%) !important; height: 100vh; display: flex; justify-content: center; align-items: center; margin: 0; }
        .card { border-color: #ff4757 !important; box-shadow: 0 0 30px rgba(255, 71, 87, 0.2) !important; padding: 40px !important; text-align: center; background: rgba(16, 46, 34, 0.85); border: 1px solid rgba(0, 255, 170, 0.2); border-radius: 8px; width: 400px; }
        .logo { color: #ff4757 !important; font-weight: 900; letter-spacing: 2px; margin-bottom: 20px; font-size: 26px; }
        button { background: linear-gradient(90deg, #ff4757, #ff6b81) !important; color: #fff !important; box-shadow: 0 0 15px rgba(255, 71, 87, 0.4) !important; margin-top: 20px; border-radius: 10px !important; width: 100%; padding: 12px; border: none; font-weight: bold; cursor: pointer; }
        .input:focus { border-color: #ff4757 !important; box-shadow: 0 0 15px rgba(255, 71, 87, 0.4) !important; }
        .user-label { color: #ff4757 !important; }
        p { color: #e4e6eb; font-size: 14px; line-height: 1.6; margin-bottom: 25px; }
        .error { color: #ff4757; margin-bottom: 15px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <?php if ($success): ?>
            <div class="logo">ACCESS RESTORED</div>
            <p>Your security credentials have been successfully updated. Unauthorized sessions have been terminated. You may now log in securely.</p>
            <button onclick="window.location.href='SacliConnect_LOG_IN.php'">RETURN TO LOGIN</button>
        <?php else: ?>
            <div class="logo">SESSION REVOKED</div>
            <p>Unauthorized access detected. All active sessions have been terminated. For security reasons, you must set a new password to re-enable login access.</p>
            
            <?php if($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>

            <form method="POST">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($user_id); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="input-group">
                    <input required type="password" name="new_password" class="input" autocomplete="off">
                    <label class="user-label">New Password</label>
                </div>
                <div class="input-group">
                    <input required type="password" name="confirm_password" class="input" autocomplete="off">
                    <label class="user-label">Confirm New Password</label>
                </div>
                <button type="submit">UPDATE SECURITY CODE</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
<?php $conn->close(); ?>