<?php
class SecurityEngine {
    private $conn;
    private $max_attempts = 5;
    private $lockout_time = 15; // minutes

    public function __construct($db_conn) {
        $this->conn = $db_conn;
    }

    // 1. Secure Password Hashing
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    // 2. Password Strength Validation
    public function validatePasswordStrength($password) {
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number    = preg_match('@[0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);

        if(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {
            return false;
        }
        return true;
    }

    // 3. Failed Login Attempt Detection & Lockout
    public function checkLockout($ip) {
        $stmt = $this->conn->prepare("SELECT attempts, lockout_until FROM login_attempts WHERE ip_address = ?");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        if ($res && $res['lockout_until'] && strtotime($res['lockout_until']) > time()) {
            return [
                'is_locked' => true,
                'remaining' => ceil((strtotime($res['lockout_until']) - time()) / 60)
            ];
        }
        return ['is_locked' => false];
    }

    public function recordLoginAttempt($ip, $success) {
        if ($success) {
            $this->conn->query("DELETE FROM login_attempts WHERE ip_address = '$ip'");
        } else {
            $lockout = date('Y-m-d H:i:s', strtotime("+{$this->lockout_time} minutes"));
            $this->conn->query("INSERT INTO login_attempts (ip_address, attempts, last_attempt) 
                               VALUES ('$ip', 1, NOW()) 
                               ON DUPLICATE KEY UPDATE 
                               attempts = attempts + 1, 
                               last_attempt = NOW(),
                               lockout_until = IF(attempts >= ".($this->max_attempts-1).", '$lockout', NULL)");
        }
    }

    // 4. Multi-Device Session Management
    public function registerSession($user_id) {
        $token = bin2hex(random_bytes(64));
        $ip = $_SERVER['REMOTE_ADDR'];
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $expiry = date('Y-m-d H:i:s', strtotime("+30 days"));

        $stmt = $this->conn->prepare("INSERT INTO user_active_sessions (user_id, session_token, ip_address, device_info, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $user_id, $token, $ip, $ua, $expiry);
        $stmt->execute();

        setcookie("SECURE_SESS", $token, time() + (86400 * 30), "/", "", true, true);
        return $token;
    }

    // 5. Audit Logging
    public function logEvent($user_id, $event, $severity = 'low') {
        $ip = $_SERVER['REMOTE_ADDR'];
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $stmt = $this->conn->prepare("INSERT INTO security_audit_logs (user_id, event_type, ip_address, user_agent, severity) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $user_id, $event, $ip, $ua, $severity);
        $stmt->execute();
    }

    // 6. CSRF Protection
    public static function generateCSRF() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCSRF($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>