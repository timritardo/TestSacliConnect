<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['student_id'])) die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));

$my_id = $_SESSION['student_id'];
$my_name = $_SESSION['student_name'];
$user_type = $_SESSION['user_type'] ?? 'student';

// Haversine Formula for GPS distance calculation
function getDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // in meters
    $latFrom = deg2rad($lat1); $lonFrom = deg2rad($lon1);
    $latTo = deg2rad($lat2); $lonTo = deg2rad($lon2);
    $latDelta = $latTo - $latFrom; $lonDelta = $lonTo - $lonFrom;
    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius;
}

if (isset($_GET['action'])) {
    if ($_GET['action'] == 'get_events') {
        $res = $conn->query("SELECT * FROM sacli_events ORDER BY event_date DESC");
        while ($ev = $res->fetch_assoc()) {
            $is_admin = ($user_type !== 'student');
            echo '<div class="sr-class-card" style="background:rgba(10,31,22,0.8);">';
            echo '<div class="sr-card-header" style="background:#1a3d2f; height: auto; padding: 20px;">';
            echo '<h3 class="sr-card-title">'.htmlspecialchars($ev['title']).'</h3>';
            echo '<p class="sr-card-teacher">📍 '.htmlspecialchars($ev['venue']).'</p></div>';
            echo '<div class="sr-card-content" style="padding:15px; font-size:13px; color:#aaa;">';
            echo '📅 '.date("M d, Y", strtotime($ev['event_date'])).'<br>';
            echo '⏰ '.date("h:i A", strtotime($ev['start_time'])).' - '.date("h:i A", strtotime($ev['end_time']));
            echo '</div><div class="sr-card-footer">';
            if ($is_admin) {
                echo '<button class="sr-btn" onclick="openQRModal('.$ev['id'].', \''.addslashes($ev['title']).'\')">GENERATE QR</button>';
                echo '<button class="sr-btn join" style="border-color:#ff5555; color:#ff5555;" onclick="deleteEvent('.$ev['id'].')">DELETE</button>';
            } else {
                echo '<button class="sr-btn join" onclick="showEventSection(\'scanQR\', document.querySelector(\'[onclick*=\\\'scanQR\\\']\'))">SCAN TO JOIN</button>';
            }
            echo '</div></div>';
        }
    }

    if ($_GET['action'] == 'get_history') {
        $res = $conn->query("SELECT a.*, e.title, e.venue FROM sacli_attendance a JOIN sacli_events e ON a.event_id = e.id WHERE a.student_id = '$my_id' ORDER BY a.timestamp DESC");
        echo '<table style="width:100%; color:#fff; border-collapse:collapse;">';
        echo '<tr style="color:#00ffaa; font-size:12px; border-bottom:1px solid #333;"><th>EVENT</th><th>STATUS</th><th>TIME</th></tr>';
        while($r = $res->fetch_assoc()) {
            echo "<tr style='border-bottom:1px solid #222;'><td style='padding:10px;'><b>{$r['title']}</b><br><small>{$r['venue']}</small></td><td>{$r['status']}</td><td>".date("M d, H:i", strtotime($r['timestamp']))."</td></tr>";
        }
        echo '</table>';
    }
}

if (isset($_POST['action'])) {
    if ($_POST['action'] == 'create_event') {
        $title = $_POST['title']; $date = $_POST['event_date'];
        $start = $_POST['start_time']; $end = $_POST['end_time'];
        $venue = $_POST['venue']; $lat = $_POST['latitude'];
        $lng = $_POST['longitude']; $rad = $_POST['radius'];

        $stmt = $conn->prepare("INSERT INTO sacli_events (title, event_date, start_time, end_time, venue, latitude, longitude, radius) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssi", $title, $date, $start, $end, $venue, $lat, $lng, $rad);
        if ($stmt->execute()) echo json_encode(['status' => 'success']);
    }

    if ($_POST['action'] == 'get_qr_token') {
        $eid = (int)$_POST['event_id'];
        $now = date('Y-m-d H:i:s');
        
        // Find valid token
        $res = $conn->query("SELECT token, expires_at FROM sacli_event_qr_tokens WHERE event_id = $eid AND expires_at > '$now' LIMIT 1");
        if ($row = $res->fetch_assoc()) {
            echo json_encode(['status' => 'success', 'token' => $row['token'], 'expiry' => strtotime($row['expires_at']) - time()]);
        } else {
            // Create new
            $token = bin2hex(random_bytes(20));
            $expires = date('Y-m-d H:i:s', strtotime('+65 seconds'));
            $conn->query("DELETE FROM sacli_event_qr_tokens WHERE event_id = $eid");
            $conn->query("INSERT INTO sacli_event_qr_tokens (event_id, token, expires_at) VALUES ($eid, '$token', '$expires')");
            echo json_encode(['status' => 'success', 'token' => $token, 'expiry' => 60]);
        }
    }

    if ($_POST['action'] == 'submit_attendance') {
        $token = $_POST['qr_data'];
        $lat = (float)$_POST['lat'];
        $lng = (float)$_POST['lng'];

        // 1. Validate Token
        $stmt = $conn->prepare("SELECT event_id FROM sacli_event_qr_tokens WHERE token = ? AND expires_at > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $eid = $row['event_id'];
            
            // 2. Check Duplicates
            $chk = $conn->query("SELECT id FROM sacli_attendance WHERE event_id = $eid AND student_id = '$my_id'");
            if ($chk->num_rows > 0) die(json_encode(['status' => 'error', 'message' => 'Attendance already recorded.']));

            // 3. Check GPS
            $ev = $conn->query("SELECT * FROM sacli_events WHERE id = $eid")->fetch_assoc();
            $dist = getDistance($lat, $lng, $ev['latitude'], $ev['longitude']);
            
            if ($dist > $ev['radius']) {
                die(json_encode(['status' => 'error', 'message' => "Access denied: Outside event radius ($dist meters away)."]));
            }

            // 4. Record Attendance
            $status = (date('H:i:s') > $ev['start_time']) ? 'Late' : 'Present';
            $stmt = $conn->prepare("INSERT INTO sacli_attendance (event_id, student_id, student_name, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $eid, $my_id, $my_name, $status);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => "Logged in as $status!"]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired QR token.']);
        }
    }

    if ($_POST['action'] == 'delete_event') {
        $eid = (int)$_POST['id'];
        $conn->query("DELETE FROM sacli_events WHERE id = $eid");
        echo "success";
    }

    if ($_POST['action'] == 'load_logs') {
        $res = $conn->query("SELECT a.*, e.title FROM sacli_attendance a JOIN sacli_events e ON a.event_id = e.id ORDER BY a.timestamp DESC");
        echo '<table style="width:100%; color:#fff; border-collapse:collapse; font-size:13px;">';
        echo '<tr style="color:#00ffaa; border-bottom:1px solid #333;"><th>STUDENT</th><th>EVENT</th><th>STATUS</th><th>TIME</th></tr>';
        while($r = $res->fetch_assoc()) {
            echo "<tr style='border-bottom:1px solid #222;'><td style='padding:8px;'>{$r['student_name']}<br><small>{$r['student_id']}</small></td><td>{$r['title']}</td><td>{$r['status']}</td><td>".date("H:i", strtotime($r['timestamp']))."</td></tr>";
        }
        echo '</table>';
    }
}
?>


