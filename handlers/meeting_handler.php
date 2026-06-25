<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed");

// AUTO-FIX: Ensure Meeting Tables Exist
$conn->query("CREATE TABLE IF NOT EXISTS sacli_meetings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_code VARCHAR(50) NOT NULL UNIQUE,
    host_id VARCHAR(50) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS sacli_meeting_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meeting_code VARCHAR(50) NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    status ENUM('waiting', 'admitted', 'denied') DEFAULT 'waiting',
    is_cam_on TINYINT(1) DEFAULT 1,
    is_mic_on TINYINT(1) DEFAULT 1,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (meeting_code, student_id)
)");

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

$my_id = $_SESSION['student_id'];
$action = $_POST['action'] ?? '';

if ($action === 'create_meeting') {
    $code = 'SACLI-' . strtoupper(substr(md5(uniqid()), 0, 6));
    $stmt = $conn->prepare("INSERT INTO sacli_meetings (meeting_code, host_id) VALUES (?, ?)");
    $stmt->bind_param("ss", $code, $my_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'code' => $code]);
    } else {
        echo json_encode(['status' => 'error']);
    }
}

elseif ($action === 'request_join') {
    $code = $_POST['code'] ?? '';
    // Validate meeting exists and is active
    $stmt = $conn->prepare("SELECT m.host_id, COALESCE(t.name, s.student_name) as host_name 
                            FROM sacli_meetings m
                            LEFT JOIN teachers t ON m.host_id = CONCAT('T-', t.id)
                            LEFT JOIN students s ON m.host_id = s.student_id
                            WHERE m.meeting_code = ? AND m.is_active = 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $meeting_info = $res->fetch_assoc();
        $host_id = $meeting_info['host_id'];
        $host_name = $meeting_info['host_name'];
        
        // If I am host, join immediately
        if ($host_id === $my_id) {
             echo json_encode(['status' => 'success', 'role' => 'host']);
             exit;
        }

        // Insert/Update participant status to 'waiting' if not already admitted/denied
        // Note: We use ON DUPLICATE KEY UPDATE to handle re-joins or refresh
        // If previously denied, they stay denied unless we reset. For now, let's reset to waiting if they try again.
        $stmt = $conn->prepare("INSERT INTO sacli_meeting_participants (meeting_code, student_id, status) VALUES (?, ?, 'waiting') ON DUPLICATE KEY UPDATE status = IF(status='denied', 'waiting', status)");
        $stmt->bind_param("ss", $code, $my_id);
        if ($stmt->execute()) {
            // Check current status immediately
            $check = $conn->query("SELECT status FROM sacli_meeting_participants WHERE meeting_code = '$code' AND student_id = '$my_id'")->fetch_assoc();
            if($check['status'] == 'admitted') {
                echo json_encode(['status' => 'admitted', 'role' => 'participant', 'host_id' => $host_id, 'host_name' => $host_name]);
            } else {
                echo json_encode(['status' => 'waiting', 'role' => 'participant', 'host_id' => $host_id, 'host_name' => $host_name]);
            }
        } else {
            echo json_encode(['status' => 'error']);
        }
    } else {
        echo json_encode(['status' => 'invalid']);
    }
}

elseif ($action === 'check_status') {
    $code = $_POST['code'] ?? '';
    $stmt = $conn->prepare("SELECT status FROM sacli_meeting_participants WHERE meeting_code = ? AND student_id = ?");
    $stmt->bind_param("ss", $code, $my_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if ($row['status'] === 'admitted') {
            // If admitted, also fetch host info
            $host_stmt = $conn->prepare("SELECT m.host_id, COALESCE(t.name, s.student_name) as host_name 
                                         FROM sacli_meetings m
                                         LEFT JOIN teachers t ON m.host_id = CONCAT('T-', t.id)
                                         LEFT JOIN students s ON m.host_id = s.student_id
                                         WHERE m.meeting_code = ?");
            $host_stmt->bind_param("s", $code);
            $host_stmt->execute();
            $host_info = $host_stmt->get_result()->fetch_assoc();
            echo json_encode(['status' => 'admitted', 'host_id' => $host_info['host_id'], 'host_name' => $host_info['host_name']]);
        } else {
            echo json_encode(['status' => $row['status']]);
        }
    } else {
        echo json_encode(['status' => 'not_found']);
    }
}

elseif ($action === 'get_waiting_list') {
    $code = $_POST['code'] ?? '';
    
    // Security: Only host can see list
    $stmt = $conn->prepare("SELECT host_id FROM sacli_meetings WHERE meeting_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows == 0 || $res->fetch_assoc()['host_id'] !== $my_id) {
        echo json_encode([]);
        exit;
    }

    // Fetch waiting users
    $sql = "SELECT p.student_id, 
            COALESCE(s.student_name, t.name) as name, 
            COALESCE(s.profile_pic, t.profile_pic) as profile_pic
            FROM sacli_meeting_participants p
            LEFT JOIN students s ON p.student_id = s.student_id
            LEFT JOIN teachers t ON p.student_id = CONCAT('T-', t.id)
            WHERE p.meeting_code = ? AND p.status = 'waiting'";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $list[] = $row;
    }
    echo json_encode($list);
}

elseif ($action === 'decide_participant') {
    $code = $_POST['code'] ?? '';
    $target_id = $_POST['target_id'] ?? '';
    $decision = $_POST['decision'] ?? ''; // 'admitted' or 'denied'
    
    // Verify host
    $stmt = $conn->prepare("SELECT host_id FROM sacli_meetings WHERE meeting_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows == 0 || $res->fetch_assoc()['host_id'] !== $my_id) {
        echo json_encode(['status' => 'error']);
        exit;
    }
    
    if (in_array($decision, ['admitted', 'denied'])) {
        $stmt = $conn->prepare("UPDATE sacli_meeting_participants SET status = ? WHERE meeting_code = ? AND student_id = ?");
        $stmt->bind_param("sss", $decision, $code, $target_id);
        $stmt->execute();
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
}

elseif ($action === 'meeting_update_state') {
    $code = $_POST['code'] ?? '';
    $cam = (int)($_POST['is_cam_on'] ?? 1);
    $mic = (int)($_POST['is_mic_on'] ?? 1);
    
    // Upsert participant state (works for host too)
    $stmt = $conn->prepare("INSERT INTO sacli_meeting_participants (meeting_code, student_id, status, is_cam_on, is_mic_on) VALUES (?, ?, 'admitted', ?, ?) ON DUPLICATE KEY UPDATE is_cam_on = VALUES(is_cam_on), is_mic_on = VALUES(is_mic_on)");
    $stmt->bind_param("ssii", $code, $my_id, $cam, $mic);
    $stmt->execute();
    echo json_encode(['status' => 'success']);
}

elseif ($action === 'meeting_get_state') {
    $code = $_POST['code'] ?? '';
    
    // Get host ID to flag the host correctly
    $h_res = $conn->query("SELECT host_id FROM sacli_meetings WHERE meeting_code = '$code'");
    $host_id = ($h_res && $h_res->num_rows > 0) ? $h_res->fetch_assoc()['host_id'] : '';
    
    $stmt = $conn->prepare("SELECT p.student_id, p.is_cam_on, p.is_mic_on, 
                            COALESCE(s.student_name, t.name) as name, 
                            COALESCE(s.profile_pic, t.profile_pic) as profile_pic 
                            FROM sacli_meeting_participants p 
                            LEFT JOIN students s ON p.student_id = s.student_id 
                            LEFT JOIN teachers t ON p.student_id = CONCAT('T-', t.id) 
                            WHERE p.meeting_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $parts = [];
    while($row = $res->fetch_assoc()){
        $row['is_host'] = ($row['student_id'] === $host_id);
        $parts[] = $row;
    }
    echo json_encode($parts);
}

$conn->close();
?>