<?php
session_start();
error_reporting(0); // Pigilan ang mga warnings na sumira sa HTML response
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/storage.php'; for all actions in this handler
if(!isset($_SESSION['student_id'])) {
    die("Not logged in"); // Or return a JSON error for AJAX requests
}
$my_id = $_SESSION['student_id']; // Define $my_id globally

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS direct_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id VARCHAR(50),
    receiver_id VARCHAR(50),
    message TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");
// Add is_read column if not exists
safeAddColumn($conn, 'direct_messages', 'is_read', "TINYINT(1) DEFAULT 0");
safeAddColumn($conn, 'direct_messages', 'is_pinned', "TINYINT(1) DEFAULT 0");
safeAddColumn($conn, 'direct_messages', 'media', "VARCHAR(255) DEFAULT NULL");
safeAddColumn($conn, 'direct_messages', 'media_type', "VARCHAR(20) DEFAULT NULL");
safeAddColumn($conn, 'direct_messages', 'is_unsent', "TINYINT(1) DEFAULT 0");
$conn->query("CREATE TABLE IF NOT EXISTS message_deletions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    chat_type ENUM('direct', 'group') NOT NULL,
    UNIQUE KEY (message_id, user_id, chat_type)
)");

// Create table for clearing history per user
$conn->query("CREATE TABLE IF NOT EXISTS chat_clear_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50),
    other_id VARCHAR(50),
    cleared_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_id, other_id)
)");

// Ensure chat_media table exists
$conn->query("CREATE TABLE IF NOT EXISTS chat_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    chat_type ENUM('direct', 'group') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Create table for typing status tracking if not exists
$conn->query("CREATE TABLE IF NOT EXISTS typing_status (
    user_id VARCHAR(50) PRIMARY KEY,
    typing_to VARCHAR(50),
    type ENUM('direct', 'group') DEFAULT 'direct',
    last_typed DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

if(isset($_POST['action'])){
    // Pin/Unpin Message
    if($_POST['action'] == 'pin_message'){
        $msg_id = (int)$_POST['msg_id'];
        $pin = (int)$_POST['is_pinned'];
        $stmt = $conn->prepare("UPDATE direct_messages SET is_pinned = ? WHERE id = ?");
        $stmt->bind_param("ii", $pin, $msg_id);
        if($stmt->execute()) echo "success";
        exit;
    }

    // Fetch Pinned Messages
    if($_POST['action'] == 'fetch_pinned_messages'){
        $other = $_POST['receiver_id'];
        $stmt = $conn->prepare("
            SELECT dm.*, 
            COALESCE(s.student_name, t.name) as sender_name,
            COALESCE(s.profile_pic, t.profile_pic) as profile_pic 
            FROM direct_messages dm
            LEFT JOIN students s ON dm.sender_id = s.student_id 
            LEFT JOIN teachers t ON dm.sender_id = CONCAT('T-', t.id)
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND dm.is_pinned = 1
            ORDER BY timestamp DESC 
        ");
        $stmt->bind_param("ssss", $my_id, $other, $other, $my_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $pinned = []; 
        while($r = $res->fetch_assoc()) $pinned[] = $r;
        echo json_encode($pinned);
        exit;
    }

    // Save Theme for Direct Chat
    if($_POST['action'] == 'save_theme'){
        $u1 = $my_id; $u2 = $_POST['receiver_id'];
        $low = ($u1 < $u2) ? $u1 : $u2;
        $high = ($u1 < $u2) ? $u2 : $u1;
        $theme = $_POST['theme'];
        $stmt = $conn->prepare("INSERT INTO direct_chat_themes (user1_id, user2_id, theme) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE theme = VALUES(theme)");
        $stmt->bind_param("sss", $low, $high, $theme);
        $stmt->execute();
        exit;
    }
    if($_POST['action'] == 'get_theme'){
        $u1 = $my_id; $u2 = $_POST['receiver_id'];
        $low = ($u1 < $u2) ? $u1 : $u2;
        $high = ($u1 < $u2) ? $u2 : $u1;
        $res = $conn->query("SELECT theme FROM direct_chat_themes WHERE user1_id='$low' AND user2_id='$high'"); 
        if($r = $res->fetch_assoc()) echo $r['theme']; else echo 'default';
        exit;
    }
    
    if($_POST['action'] == 'send_system_message'){
        $receiver = $_POST['receiver_id'];
        $msg = $_POST['message'];
        $stmt = $conn->prepare("INSERT INTO direct_messages (sender_id, receiver_id, message, media_type) VALUES (?, ?, ?, 'system')");
        $stmt->bind_param("sss", $my_id, $receiver, $msg);
        $stmt->execute();
        exit;
    }

    // Update Typing Status
    if($_POST['action'] == 'update_typing'){
        $target = $_POST['target_id'];
        $chat_type = $_POST['chat_type'] ?? 'direct';
        $stmt = $conn->prepare("INSERT INTO typing_status (user_id, typing_to, type, last_typed) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE typing_to = VALUES(typing_to), type = VALUES(type), last_typed = NOW()");
        $stmt->bind_param("sss", $my_id, $target, $chat_type);
        $stmt->execute();
        exit;
    }
    
    // Stop Typing (explicitly)
    if($_POST['action'] == 'stop_typing'){ 
        $conn->query("DELETE FROM typing_status WHERE user_id = '$my_id'");
        exit;
    }

    // Check if other user is typing
    if($_POST['action'] == 'check_typing'){
        $target = $_POST['receiver_id'];
        $chat_type = $_POST['chat_type'] ?? 'direct';
        
        if($chat_type === 'group') {
            // Check if anyone else in the group is typing
            $stmt = $conn->prepare("SELECT COALESCE(s.student_name, t.name) as typing_name 
                                   FROM typing_status ts
                                   LEFT JOIN students s ON ts.user_id = s.student_id
                                   LEFT JOIN teachers t ON ts.user_id = CONCAT('T-', t.id)
                                   WHERE ts.typing_to = ? AND ts.type = 'group' AND ts.user_id != ?
                                   AND ts.last_typed > DATE_SUB(NOW(), INTERVAL 4 SECOND) LIMIT 1");
            $stmt->bind_param("ss", $target, $my_id);
        } else {
            // Check if the specific person is typing to me
            $stmt = $conn->prepare("SELECT user_id FROM typing_status WHERE typing_to = ? AND user_id = ? AND type = 'direct' AND last_typed > DATE_SUB(NOW(), INTERVAL 4 SECOND)"); 
            $stmt->bind_param("ss", $my_id, $target);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if($row = $res->fetch_assoc()) {
            echo ($chat_type === 'group') ? $row['typing_name'] : "true";
        } else {
            echo "false";
        }
        exit;
    }
    
    // Send Message
    if($_POST['action'] == 'send'){
        $receiver = $_POST['receiver_id'];
        $msg = trim($_POST['message']);
        
        // Insert main message first
        $stmt = $conn->prepare("INSERT INTO direct_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)"); 
        $stmt->bind_param("sss", $my_id, $receiver, $msg);
        $stmt->execute();
        $message_id = $stmt->insert_id;

        // Process Multiple Media (media[] array from SacliChat_Full.php)
        if(isset($_FILES['media'])){
            $files = $_FILES['media'];
            $count = is_array($files['name']) ? count($files['name']) : 0;

            for($i=0; $i<$count; $i++){
                $name = $files['name'][$i];
                $tmp  = $files['tmp_name'][$i];
                $error= $files['error'][$i];

                if($error === 0){
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'webm', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'];
                    
                    if(in_array($ext, $allowed)){
                        if(in_array($ext, ['mp4', 'mov', 'webm'])) $type = 'video';
                        elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $type = 'photo';
                        else $type = 'file';

                        $filename = "chat_" . time() . "_" . uniqid() . "." . $ext;
                        $url = uploadToSupabase($tmp, $filename);
                        if ($url) {
                            $m_stmt = $conn->prepare("INSERT INTO chat_media (message_id, chat_type, file_path, file_type) VALUES (?, 'direct', ?, ?)");
                            $m_stmt->bind_param("iss", $message_id, $url, $type);
                            $m_stmt->execute();
                        }
                    }
                }
            }
        }
        exit();
    }

    // Delete Message (Student)
    if($_POST['action'] == 'delete'){
        $id = $_POST['msg_id'];
        // Ensure ownership
        $stmt = $conn->prepare("DELETE FROM direct_messages WHERE id=? AND sender_id=?");
        $stmt->bind_param("is", $id, $my_id);
        $stmt->execute();
    }

    // Unsend (Delete for everyone)
    if($_POST['action'] == 'unsend'){
        $id = (int)$_POST['msg_id'];
        // Ensure ownership
        $stmt = $conn->prepare("UPDATE direct_messages SET is_unsent = 1, message = '' WHERE id=? AND sender_id=?");
        $stmt->bind_param("is", $id, $my_id);
        if($stmt->execute()) {
            // Physically delete associated media files
            $m_res = $conn->query("SELECT file_path FROM chat_media WHERE message_id = $id AND chat_type = 'direct'");
            if ($m_res) {
                while($m = $m_res->fetch_assoc()) {
                    if(!empty($m['file_path']) && file_exists($m['file_path'])) @unlink($m['file_path']);
                }
            }
            $conn->query("DELETE FROM chat_media WHERE message_id = $id AND chat_type = 'direct'");
            echo "success";
        }
        exit;
    }

    // Delete for me
    if($_POST['action'] == 'delete_for_me'){
        $id = (int)$_POST['msg_id'];
        $chat_type = $_POST['chat_type'] ?? 'direct';
        $stmt = $conn->prepare("INSERT IGNORE INTO message_deletions (message_id, user_id, chat_type) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $id, $my_id, $chat_type);
        if($stmt->execute()) echo "success";
        exit;
    }

    // Edit Message (Student)
    if($_POST['action'] == 'edit'){
        $id = $_POST['msg_id']; 
        $new_msg = trim($_POST['message']);
        if(!empty($new_msg)){ 
            $stmt = $conn->prepare("UPDATE direct_messages SET message=? WHERE id=? AND sender_id=?");
            $stmt->bind_param("sis", $new_msg, $id, $my_id);
            $stmt->execute();
        }
    }

    // Clear History (User only)
    if($_POST['action'] == 'clear_history'){
        $other = $_POST['receiver_id'];
        $stmt = $conn->prepare("INSERT INTO chat_clear_history (user_id, other_id, cleared_at)
                                VALUES (?, ?, NOW())
                                ON DUPLICATE KEY UPDATE cleared_at = NOW()");
        $stmt->bind_param("ss", $my_id, $other);
        if($stmt->execute()) echo "success";
        $stmt->close();
        exit();
    }

    // Fetch Messages
    if($_POST['action'] == 'fetch'){
        $other = $_POST['receiver_id'];
        
        // Mark as read
        $upd = $conn->prepare("UPDATE direct_messages SET is_read=1 WHERE sender_id=? AND receiver_id=?"); 
        $upd->bind_param("ss", $other, $my_id);
        $upd->execute();

        // Get conversation between Me and Other
        $stmt = $conn->prepare("
            SELECT dm.*,
            COALESCE(s.student_name, t.name) as student_name,
            COALESCE(s.profile_pic, t.profile_pic) as profile_pic
            FROM direct_messages dm 
            LEFT JOIN students s ON dm.sender_id = s.student_id
            LEFT JOIN teachers t ON dm.sender_id = CONCAT('T-', t.id) 
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
            AND dm.timestamp > (SELECT COALESCE(MAX(cleared_at), '1000-01-01') FROM chat_clear_history WHERE user_id = ? AND other_id = ?)
            AND NOT EXISTS (SELECT 1 FROM message_deletions WHERE message_id = dm.id AND user_id = ? AND chat_type = 'direct')
            ORDER BY timestamp ASC
        ");
        $stmt->bind_param("sssssss", $my_id, $other, $other, $my_id, $my_id, $other, $my_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $all_msgs = [];
        while($r = $res->fetch_assoc()) $all_msgs[] = $r;
        $total_msgs = count($all_msgs);

        // Fetch all media for these messages
        $media_by_message_id = [];
        $message_ids = array_column($all_msgs, 'id');
        if (!empty($message_ids)) { 
            $media_res = $conn->query("SELECT message_id, file_path, file_type, uploaded_at FROM chat_media WHERE message_id IN (" . implode(',', $message_ids) . ") AND chat_type = 'direct'");
            while($m = $media_res->fetch_assoc()) $media_by_message_id[$m['message_id']][] = $m; 
        }

        foreach($all_msgs as $index => $row){
            // Date/Time Separator Logic
            $show_timestamp_separator = false;
            if ($index === 0) {
                $show_timestamp_separator = true; // Always show for the first message
            } else {
                $prev_msg_timestamp = strtotime($all_msgs[$index - 1]['timestamp']);
                $current_msg_timestamp = strtotime($row['timestamp']);
                if (($current_msg_timestamp - $prev_msg_timestamp) >= (5 * 60)) { // 5 minutes in seconds
                    $show_timestamp_separator = true;
                }
            }
            if ($show_timestamp_separator) echo "<div class='msg-timestamp-separator'>".date("M d, Y h:i A", strtotime($row['timestamp']))."</div>";

            // Render System Message (Centered)
            if ($row['media_type'] === 'system') {
                $name = ($row['sender_id'] == $my_id) ? "You" : (htmlspecialchars($row['student_name']) ?: "User"); // Use $my_id here
                echo "<div class='msg-container system-msg' style='width:100%; display:flex; justify-content:center; margin: 15px 0;'>";
                echo "<div style='font-family: var(--terminal-font); font-size: 11px; color: #fff; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; text-align: center;'>— $name " . htmlspecialchars($row['message']) . " —</div>";
                echo "</div>";
                continue;
            }

            $is_me = ($row['sender_id'] == $my_id);
            $class = $is_me ? 'my-msg' : 'other-msg'; 
            $senderName = $row['student_name'] ?? $row['sender_id'];
            $pic = !empty($row['profile_pic']) ? "uploads/".$row['profile_pic'] : "assets/images/3icons8-student-64.png";

            // Handle Unsent (Deleted for all)
            if ($row['is_unsent'] == 1) {
                echo "<div class='msg-container " . ($is_me ? "mine" : "other") . "'>";
                echo "<img src='$pic' class='msg-avatar'>";
                echo "<div style='display: flex; flex-direction: column; align-items: " . ($is_me ? "flex-end" : "flex-start") . "; max-width: 80%;'>";
                if(!$is_me) echo "<div style='font-size:10px; color:#00ffaa; margin-bottom:4px;'>".htmlspecialchars($senderName)."</div>";
                echo "<div class='msg $class' id='msg-anchor-{$row['id']}' style='opacity: 0.5; font-style: italic; border: 1px dashed rgba(255,255,255,0.2) !important; background: transparent !important; color: #aaa !important;'>deleted message</div>";
                echo "</div>";
                echo "<div class='msg-options-wrapper'><button class='msg-dots-btn' onclick='toggleMsgMenu(event, {$row['id']})'>•••</button><div class='msg-menu' id='msg-menu-{$row['id']}'><div onclick='executeDeleteForMe({$row['id']})' style='color:#ff5555;'>Remove for you</div></div></div>";
                echo "</div>";
                continue;
            }

            $status_html = ""; 
            if($is_me){
                // Show Delivered/Seen only for the very last message sent by me
                if($index === $total_msgs - 1) {
                    $is_seen = ($row['is_read'] == 1);
                    $status_text = $is_seen ? "Seen" : "Delivered";
                    $status_color = $is_seen ? "#90ee90" : "#ffffff"; // Light green for Seen, White for Delivered
                    $status_html = "<div class='msg-status' style='font-size: 10px; color: $status_color; font-weight: bold; margin-top: 2px;'>$status_text</div>";
                }
            }
            
            $pin_label = $row['is_pinned'] ? 'Unpin' : 'Pin';
            $pin_val = $row['is_pinned'] ? 0 : 1;
            $js_msg = htmlspecialchars(json_encode($row['message'] ?? ""), ENT_QUOTES, 'UTF-8');
            $dots_html = " 
            <div class='msg-options-wrapper'>
                <button class='msg-dots-btn' onclick='toggleMsgMenu(event, {$row['id']})'>•••</button>
                <div class='msg-menu' id='msg-menu-{$row['id']}'>
                    " . ($is_me ? "<div onclick='editMsg(".$row['id'].", $js_msg)'>Edit</div>" : "") . "
                    <div onclick='pinMessage(".$row['id'].", $pin_val)'>$pin_label</div>
                    " . ($is_me ? "<div onclick='deleteMsg(".$row['id'].")' style='color:#ff5555;'>Delete</div>" : "") . "
                </div>
            </div>";

            $mediaHtml = '';
            if (isset($media_by_message_id[$row['id']])) {
                $files = $media_by_message_id[$row['id']];
                $media_count = count($files); 
                $mediaHtml .= "<div class='message-media-grid grid-{$media_count}' data-message-id='{$row['id']}'>";
                foreach ($files as $idx => $media_item) {
                    $path = htmlspecialchars($media_item['file_path']);
                    $type = $media_item['file_type'];
                    $date = date("M d, Y", strtotime($media_item['uploaded_at']));
                    $onclick = "openMessageLightbox({$row['id']}, {$idx}, 'direct')";

                    $mediaHtml .= "<div class='message-media-item' onclick=\"{$onclick}\">";
                    if ($type === 'video') {
                        $mediaHtml .= "<video src='{$path}' preload='metadata'></video>";
                        $mediaHtml .= "<div class='media-play-icon'>▶</div>"; 
                    } elseif ($type === 'photo') {
                        $mediaHtml .= "<img src='{$path}'>";
                    } else { // file
                        $fname = basename($path);
                        $mediaHtml .= "<div class='file-attachment-preview'>📄 ".htmlspecialchars($fname)."</div>";
                    }
                    $mediaHtml .= "<div class='media-hover-overlay'></div>";
                    $mediaHtml .= "</div>";
                }
                $mediaHtml .= "</div>";
            }
            
            // Only show message content if it's not empty AND there's no media, OR if there's media but also text
            $message_content_html = '';
            if (!empty($row['message'])) {
                $message_content_html = htmlspecialchars($row['message']);
            }

            // If there's only media and no text, don't render an empty message bubble
            if (empty($message_content_html) && !empty($mediaHtml)) {
                // If only media, apply media directly to the container, no message bubble
                echo "<div class='msg-container " . ($is_me ? "mine" : "other") . "'>";
                echo "<img src='$pic' class='msg-avatar'>";
                echo "<div style='display: flex; flex-direction: column; align-items: " . ($is_me ? "flex-end" : "flex-start") . "; max-width: 80%;'>";
                if(!$is_me) echo "<div style='font-size:10px; color:#00ffaa; margin-bottom:4px;'>".htmlspecialchars($senderName)."</div>";
                echo $mediaHtml; // Media only
                if($is_me) echo "<div style='margin-top:2px;'>".$status_html."</div>";
                echo "</div>";
                echo $dots_html;
                echo "</div>";
                continue;
            }

            echo "<div class='msg-container " . ($is_me ? "mine" : "other") . "'>";
            echo "<img src='$pic' class='msg-avatar'>";
            
            echo "<div style='display: flex; flex-direction: column; align-items: " . ($is_me ? "flex-end" : "flex-start") . "; max-width: 80%;'>";
            
            $pinned_indicator = $row['is_pinned'] ? '<div style="font-size:10px; color:#ffd700; margin-bottom:2px;">📌 Pinned</div>' : '';

            echo "<div class='msg $class' id='msg-anchor-{$row['id']}'>";
            echo $pinned_indicator;
            if(!$is_me) echo "<div style='font-size:10px; color:#00ffaa; margin-bottom:2px;'>".htmlspecialchars($senderName)."</div>";
            echo $message_content_html;
            echo $mediaHtml; // Media inside the message bubble
            echo "</div>"; // End msg
            if($is_me) echo $status_html;
            echo "</div>"; // End column
            echo $dots_html;
            echo "</div>"; // End msg-container
        }
    }

    // Fetch Media for a specific message (for Lightbox)
    if($_POST['action'] == 'fetch_message_media'){
        $message_id = (int)$_POST['message_id'];
        $chat_type = $_POST['chat_type'];
        $stmt = $conn->prepare("SELECT file_path, file_type, uploaded_at FROM chat_media WHERE message_id = ? AND chat_type = ? ORDER BY uploaded_at ASC");
        $stmt->bind_param("is", $message_id, $chat_type);
        $stmt->execute();
        $res = $stmt->get_result();
        $media_items = [];
        while($r = $res->fetch_assoc()){
            $media_items[] = [
                'file_path' => $r['file_path'],
                'file_type' => $r['file_type'],
                'date' => date("M d, Y", strtotime($r['uploaded_at']))
            ];
        }
        echo json_encode($media_items);
        exit();
    }

    // Fetch Media & Files for Sidebar
    if($_POST['action'] == 'fetch_sidebar_assets'){
        $other = $_POST['receiver_id'];
        $stmt = $conn->prepare(" 
            SELECT cm.file_path, cm.file_type, cm.uploaded_at as timestamp
            FROM chat_media cm
            JOIN direct_messages dm ON cm.message_id = dm.id
            WHERE cm.chat_type = 'direct' AND ((dm.sender_id = ? AND dm.receiver_id = ?) OR (dm.sender_id = ? AND dm.receiver_id = ?))
            AND dm.timestamp > (SELECT COALESCE(MAX(cleared_at), '1000-01-01') FROM chat_clear_history WHERE user_id = ? AND other_id = ?) 
            ORDER BY cm.uploaded_at DESC
        ");
        $stmt->bind_param("ssssss", $my_id, $other, $other, $my_id, $my_id, $other);
        $stmt->execute();
        $res = $stmt->get_result();
        $assets = [];
        while($r = $res->fetch_assoc()) {
            $assets[] = [
                'path' => $r['file_path'],
                'type' => $r['file_type'],
                'name' => basename($r['file_path']),
                'date' => date("M d, Y", strtotime($r['timestamp']))
            ];
        }
        echo json_encode($assets);
        exit();
    }

    // Check Notifications (New Messages)
    if($_POST['action'] == 'check_new'){
        $stmt = $conn->prepare("SELECT id, sender_id, message FROM direct_messages WHERE receiver_id = ? AND is_read = 0"); 
        $stmt->bind_param("s", $my_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $unread = [];
        while($row = $res->fetch_assoc()) $unread[] = $row;
        echo json_encode($unread);
    }
}

// Get Chat Participants (for mention suggestions in direct chat)
if($_POST['action'] == 'get_chat_participants'){
    $receiver_id = $_POST['receiver_id']; 
    $participants = [];
    
    // Fetch details of the receiver
    $stmt = $conn->prepare("
        SELECT student_id as id, student_name as name, profile_pic FROM students WHERE student_id = ?
        UNION ALL
        SELECT CONCAT('T-', id) as id, name, profile_pic FROM teachers WHERE CONCAT('T-', id) = ?
    ");
    $stmt->bind_param("ss", $receiver_id, $receiver_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        $participants[] = $row;
    }

    // Also include the current user in the list of participants (for self-mention if desired, though usually filtered out by JS)
    // The JavaScript in SacliChat_Full.php already filters out the current user.
    $participants[] = ['id' => $my_id, 'name' => $_SESSION['student_name'], 'profile_pic' => $_SESSION['profile_pic'] ?? ''];

    echo json_encode($participants);
    exit;
}
?>