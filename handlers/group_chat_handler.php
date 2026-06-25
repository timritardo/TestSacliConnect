<?php
session_start();
error_reporting(0);
require_once __DIR__ . '/../config/database.php';

if(!isset($_SESSION['student_id'])) die("Not logged in");
$my_id = $_SESSION['student_id'];

// Ensure tables exist and have necessary columns for unread tracking
$conn->query("CREATE TABLE IF NOT EXISTS group_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    creator_id VARCHAR(50),
    group_icon VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("CREATE TABLE IF NOT EXISTS group_chat_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT,
    user_id VARCHAR(50),
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("ALTER TABLE group_chat_members ADD COLUMN IF NOT EXISTS last_read DATETIME DEFAULT CURRENT_TIMESTAMP");
$conn->query("ALTER TABLE group_chat_members ADD COLUMN IF NOT EXISTS cleared_at DATETIME DEFAULT '1000-01-01'");
$conn->query("CREATE TABLE IF NOT EXISTS group_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT,
    sender_id VARCHAR(50),
    message TEXT,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("ALTER TABLE group_chat_messages ADD COLUMN IF NOT EXISTS media VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE group_chat_messages ADD COLUMN IF NOT EXISTS is_pinned TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE group_chat_messages ADD COLUMN IF NOT EXISTS media_type VARCHAR(20) DEFAULT NULL");
$conn->query("CREATE TABLE IF NOT EXISTS group_chat_mentions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id VARCHAR(50) NOT NULL,
    FOREIGN KEY (message_id) REFERENCES group_chat_messages(id) ON DELETE CASCADE
)");
$conn->query("CREATE TABLE IF NOT EXISTS chat_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    chat_type ENUM('direct', 'group') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("ALTER TABLE group_chats ADD COLUMN IF NOT EXISTS theme VARCHAR(50) DEFAULT 'default'");

if(isset($_POST['action'])){
    
    // Pin/Unpin Message
    if($_POST['action'] == 'pin_message'){
        $msg_id = (int)$_POST['msg_id'];
        $pin = (int)$_POST['is_pinned'];
        $stmt = $conn->prepare("UPDATE group_chat_messages SET is_pinned = ? WHERE id = ?");
        $stmt->bind_param("ii", $pin, $msg_id);
        if($stmt->execute()) echo "success";
        exit;
    }

    // Fetch Pinned Messages
    if($_POST['action'] == 'fetch_pinned_messages'){
        $group_id = $_POST['group_id'];
        $stmt = $conn->prepare("
            SELECT m.*, 
            COALESCE(s.student_name, t.name) as sender_name,
            COALESCE(s.profile_pic, t.profile_pic) as profile_pic 
            FROM group_chat_messages m
            LEFT JOIN students s ON m.sender_id = s.student_id 
            LEFT JOIN teachers t ON m.sender_id = CONCAT('T-', t.id)
            WHERE m.group_id = ? AND m.is_pinned = 1
            ORDER BY timestamp DESC
        ");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $pinned = [];
        while($r = $res->fetch_assoc()) $pinned[] = $r;
        echo json_encode($pinned);
        exit;
    }

    // Save/Get Theme for Group Chat
    if($_POST['action'] == 'save_theme'){
        $gid = $_POST['group_id'];
        $theme = $_POST['theme'];
        $conn->query("UPDATE group_chats SET theme='$theme' WHERE id='$gid'");
        exit;
    }
    if($_POST['action'] == 'get_theme'){
        $gid = $_POST['group_id'];
        $res = $conn->query("SELECT theme FROM group_chats WHERE id='$gid'");
        if($r = $res->fetch_assoc()) echo $r['theme'] ?? 'default'; else echo 'default';
        exit;
    }
    
    if($_POST['action'] == 'send_system_message'){
        $group_id = $_POST['group_id'];
        $msg = $_POST['message'];
        $stmt = $conn->prepare("INSERT INTO group_chat_messages (group_id, sender_id, message, media_type) VALUES (?, ?, ?, 'system')");
        $stmt->bind_param("iss", $group_id, $my_id, $msg);
        $stmt->execute();
        exit;
    }

    // Create Group
    if($_POST['action'] == 'create'){
        $name = $conn->real_escape_string($_POST['group_name']);
        $members = isset($_POST['members']) ? $_POST['members'] : []; // Array of student_ids
        
        $icon_file = "";
        if(isset($_FILES['group_icon']) && $_FILES['group_icon']['error'] == 0){
            $ext = pathinfo($_FILES['group_icon']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            if(in_array(strtolower($ext), $allowed)){
                $new_name = "group_" . time() . "_" . uniqid() . "." . $ext;
                if(move_uploaded_file($_FILES['group_icon']['tmp_name'], "uploads/" . $new_name)){
                    $icon_file = $new_name;
                }
            }
        }

        // Create Group
        $stmt = $conn->prepare("INSERT INTO group_chats (name, creator_id, group_icon) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $my_id, $icon_file);
        $stmt->execute();
        $group_id = $stmt->insert_id;

        // Add Creator
        $conn->query("INSERT INTO group_chat_members (group_id, user_id) VALUES ('$group_id', '$my_id')");
        
        // Add Members
        foreach($members as $uid){
            $uid = $conn->real_escape_string($uid);
            $conn->query("INSERT INTO group_chat_members (group_id, user_id) VALUES ('$group_id', '$uid')");
        }
        echo "success";
    }

    // Add Member to existing group
    if($_POST['action'] == 'add_member'){
        $group_id = $_POST['group_id'];
        $members = isset($_POST['members']) ? $_POST['members'] : [];
        
        foreach($members as $uid){
            $uid = $conn->real_escape_string($uid);
            // Check if exists
            $check = $conn->query("SELECT id FROM group_chat_members WHERE group_id='$group_id' AND user_id='$uid'");
            if($check->num_rows == 0){
                $conn->query("INSERT INTO group_chat_members (group_id, user_id) VALUES ('$group_id', '$uid')");
            }
        }
        echo "success";
    }

    // Send Message
    if($_POST['action'] == 'send'){
        $group_id = $_POST['group_id'];
        $msg = $conn->real_escape_string($_POST['message']);

        // Insert message first
        $stmt = $conn->prepare("INSERT INTO group_chat_messages (group_id, sender_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $group_id, $my_id, $msg);
        $stmt->execute();
        $message_id = $stmt->insert_id;

        // Process Multiple Media
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

                        $filename = "gc_" . time() . "_" . uniqid() . "." . $ext;
                        if(!is_dir('uploads')) mkdir('uploads');
                        
                        if(move_uploaded_file($tmp, "uploads/" . $filename)){
                            $path = "uploads/" . $filename;
                            $m_stmt = $conn->prepare("INSERT INTO chat_media (message_id, chat_type, file_path, file_type) VALUES (?, 'group', ?, ?)");
                            $m_stmt->bind_param("iss", $message_id, $path, $type);
                            $m_stmt->execute();
                        }
                    }
                }
            }
        }

        if($message_id){
            $message_id = $stmt->insert_id;

            // Handle Mentions
            if (isset($_POST['mentioned_users']) && is_array($_POST['mentioned_users'])) {
                $mention_stmt = $conn->prepare("INSERT INTO group_chat_mentions (message_id, user_id) VALUES (?, ?)");
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES (?, ?, 'mention', ?)");
                foreach ($_POST['mentioned_users'] as $mentioned_user_id) {
                    $mentioned_user_id = $conn->real_escape_string($mentioned_user_id);
                    $mention_stmt->bind_param("is", $message_id, $mentioned_user_id);
                    $mention_stmt->execute();

                    // Send notification to mentioned user
                    if ($mentioned_user_id !== $my_id) {
                        // For group chat mentions, post_id is not directly applicable, use group_id for context
                        $notif_stmt->bind_param("ssi", $mentioned_user_id, $my_id, $group_id); 
                        $notif_stmt->execute();

                        // Optional: Send email notification
                        // $u_data = $conn->query("SELECT email, student_name FROM students WHERE student_id='$mentioned_user_id' UNION SELECT email, name as student_name FROM teachers WHERE CONCAT('T-', id)='$mentioned_user_id'")->fetch_assoc();
                        // if ($u_data && !empty($u_data['email'])) {
                        //     sendNotificationEmail($u_data['email'], "You were mentioned in a group chat!", "Hi {$u_data['student_name']}, you were mentioned by {$_SESSION['student_name']} in a group chat.");
                        // }
                    }
                }
                $mention_stmt->close();
                $notif_stmt->close();
            }


        }
    }

    // Delete Message
    if($_POST['action'] == 'delete'){
        $id = $_POST['msg_id'];
        $stmt = $conn->prepare("DELETE FROM group_chat_messages WHERE id=? AND sender_id=?");
        $stmt->bind_param("is", $id, $my_id);
        $stmt->execute();
    }

    // Edit Message
    if($_POST['action'] == 'edit'){
        $id = $_POST['msg_id'];
        $new_msg = trim($_POST['message']);
        if(!empty($new_msg)){
            $stmt = $conn->prepare("UPDATE group_chat_messages SET message=? WHERE id=? AND sender_id=?");
            $stmt->bind_param("sis", $new_msg, $id, $my_id);
            $stmt->execute();
        }
    }

    // Clear Group History (User only)
    if($_POST['action'] == 'clear_history'){
        $group_id = $_POST['group_id'];
        $stmt = $conn->prepare("UPDATE group_chat_members SET cleared_at = NOW() WHERE group_id=? AND user_id=?");
        $stmt->bind_param("is", $group_id, $my_id);
        if($stmt->execute()) echo "success";
        exit();
    }

    // Fetch Messages
    if($_POST['action'] == 'fetch'){
        $group_id = $_POST['group_id'];
        
        // Mark as read (Update last_read timestamp)
        $conn->query("UPDATE group_chat_members SET last_read = NOW() WHERE group_id='$group_id' AND user_id='$my_id'");
        
        // Fetch other members' last_read status to determine who has seen which message
        $members_data = [];
        $mem_res = $conn->query("
            SELECT m.user_id, m.last_read, COALESCE(s.profile_pic, t.profile_pic) as profile_pic
            FROM group_chat_members m
            LEFT JOIN students s ON m.user_id = s.student_id
            LEFT JOIN teachers t ON m.user_id = CONCAT('T-', t.id)
            WHERE m.group_id = '$group_id' AND m.user_id != '$my_id'");
        while($m = $mem_res->fetch_assoc()) $members_data[] = $m;

        $msg_query = $conn->query("
            SELECT m.*, 
            COALESCE(s.student_name, t.name) as sender_name,
            COALESCE(s.profile_pic, t.profile_pic) as profile_pic
            FROM group_chat_messages m 
            LEFT JOIN students s ON m.sender_id = s.student_id 
            LEFT JOIN teachers t ON m.sender_id = CONCAT('T-', t.id)
            WHERE m.group_id='$group_id'
            AND m.timestamp > (SELECT cleared_at FROM group_chat_members WHERE group_id = '$group_id' AND user_id = '$my_id')
            ORDER BY m.timestamp ASC
        ");
        
        $all_msgs = [];
        if ($msg_query) {
            while($r = $msg_query->fetch_assoc()) {
                $all_msgs[] = $r;
            }
        }

        $seen_by_message_id = []; // Map: message_id => [array of member profile_pics]
        $max_last_read_ts = 0; // Track the latest message read by any other member

        foreach ($members_data as $member) {
            $member_last_read_ts = strtotime($member['last_read']);
            if ($member_last_read_ts > $max_last_read_ts) $max_last_read_ts = $member_last_read_ts;

            $last_seen_message_id = null;
            $closest_timestamp = 0;

            foreach ($all_msgs as $msg) {
                $msg_ts = strtotime($msg['timestamp']);
                // Find the latest message whose timestamp is less than or equal to the member's last_read
                if ($msg_ts <= $member_last_read_ts && $msg_ts > $closest_timestamp) {
                    $closest_timestamp = $msg_ts;
                    $last_seen_message_id = $msg['id'];
                }
            }

            if ($last_seen_message_id !== null) {
                $m_pic = !empty($member['profile_pic']) ? "uploads/".$member['profile_pic'] : "assets/images/3icons8-student-64.png";
                if (!isset($seen_by_message_id[$last_seen_message_id])) {
                    $seen_by_message_id[$last_seen_message_id] = [];
                }
                $seen_by_message_id[$last_seen_message_id][] = $m_pic;
            }
        }

        // Fetch all media for these messages
        $media_by_message_id = [];
        $mentions_by_message_id = [];
        $message_ids = array_column($all_msgs, 'id');
        if (!empty($message_ids)) {
            $media_res = $conn->query("SELECT message_id, file_path, file_type, uploaded_at FROM chat_media WHERE message_id IN (" . implode(',', $message_ids) . ") AND chat_type = 'group'");
            while($m = $media_res->fetch_assoc()) $media_by_message_id[$m['message_id']][] = $m;

            // Fetch all mentions for these messages
            $mention_res = $conn->query("
                SELECT gcm.message_id, COALESCE(s.student_name, t.name) as mentioned_name 
                FROM group_chat_mentions gcm 
                LEFT JOIN students s ON gcm.user_id = s.student_id 
                LEFT JOIN teachers t ON gcm.user_id = CONCAT('T-', t.id) 
                WHERE gcm.message_id IN (" . implode(',', $message_ids) . ")");
            while($m = $mention_res->fetch_assoc()) $mentions_by_message_id[$m['message_id']][] = $m;
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
                $name = ($row['sender_id'] == $my_id) ? "You" : (htmlspecialchars($row['sender_name']) ?: "User");
                echo "<div class='msg-container system-msg' style='width:100%; display:flex; justify-content:center; margin: 15px 0;'>";
                echo "<div style='font-family: var(--terminal-font); font-size: 11px; color: #fff; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; text-align: center;'>— $name " . htmlspecialchars($row['message']) . " —</div>";
                echo "</div>";
                continue;
            }

            $is_me = ($row['sender_id'] == $my_id);
            $class = $is_me ? 'my-msg' : 'other-msg';
            $pic = !empty($row['profile_pic']) ? "uploads/".$row['profile_pic'] : "assets/images/3icons8-student-64.png";
            $senderName = $row['sender_name'] ? $row['sender_name'] : $row['sender_id'];
            
            $status_html = "";
            if($is_me){

                $status_html = "";
                if (isset($seen_by_message_id[$row['id']])) {
                    $seen_avatars_html = "";
                    foreach ($seen_by_message_id[$row['id']] as $avatar_pic) {
                        $seen_avatars_html .= "<img src='$avatar_pic' style='width:14px; height:14px; border-radius:50%; border:1px solid #00ffaa; margin-left:-4px; background:#0a1f16; object-fit:cover;' title='Seen'>";
                    }
                    $status_html = "<div class='msg-status' style='display:flex; justify-content:flex-end; margin-top: 2px;'>$seen_avatars_html</div>";
                } elseif (strtotime($row['timestamp']) > $max_last_read_ts) {
                    // Only show "Delivered" if this message (or any after it) hasn't been seen by ANYONE yet.
                    $status_html = "<div class='msg-status' style='font-size: 10px; color: #ffffff; font-weight: bold; margin-top: 2px;'>Delivered</div>";
                }
            }
            
            $mediaHtml = '';
            if (isset($media_by_message_id[$row['id']])) {
                $files = $media_by_message_id[$row['id']];
                $media_count = count($files);
                $mediaHtml .= "<div class='message-media-grid grid-{$media_count}' data-message-id='{$row['id']}'>";
                foreach ($files as $idx => $media_item) {
                    $path = htmlspecialchars($media_item['file_path']);
                    $type = $media_item['file_type'];
                    $date = date("M d, Y", strtotime($media_item['uploaded_at']));
                    $onclick = "openMessageLightbox({$row['id']}, {$idx}, 'group')";

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

            // Generate dots_html once, before the main message container
            $dots_html = "";
            if($is_me) {
                $js_msg = htmlspecialchars(json_encode($row['message'] ?? ""), ENT_QUOTES, 'UTF-8');
                $pin_label = $row['is_pinned'] ? 'Unpin' : 'Pin';
                $pin_val = $row['is_pinned'] ? 0 : 1;
                $dots_html = "
                <div class='msg-options-wrapper'>
                    <button class='msg-dots-btn' onclick='toggleMsgMenu(event, " . $row['id'] . ")'>•••</button>
                    <div class='msg-menu' id='msg-menu-" . $row['id'] . "'>
                        <div onclick='editMsg(" . $row['id'] . ", $js_msg)'>Edit</div>
                        <div onclick='pinMessage(" . $row['id'] . ", $pin_val)'>$pin_label</div>
                        <div onclick='deleteMsg(" . $row['id'] . ")' style='color:#ff5555;'>Delete</div>
                    </div>
                </div>";
            }

            // Main message container rendering for all non-system messages
            echo "<div class='msg-container " . ($is_me ? "mine" : "other") . "'>";
            echo "<img src='$pic' class='msg-avatar' title='".htmlspecialchars($senderName)."'>";
            
            $pinned_indicator = $row['is_pinned'] ? '<div style="font-size:10px; color:#ffd700; margin-bottom:2px;">📌 Pinned</div>' : '';

            // Process message content for mentions
            $processed_message = htmlspecialchars($row['message']);
            if (isset($mentions_by_message_id[$row['id']])) {
                foreach ($mentions_by_message_id[$row['id']] as $mention) {
                    $mentioned_name = htmlspecialchars($mention['mentioned_name']);
                    $processed_message = str_replace(
                        '@' . $mentioned_name,
                        '<span class="mention-highlight">@' . $mentioned_name . '</span>',
                        $processed_message
                    );
                }
            }

            // Column wrapper for message bubble and status (Seen/Delivered)
            echo "<div style='display: flex; flex-direction: column; align-items: " . ($is_me ? "flex-end" : "flex-start") . "; max-width: 80%;'>";

            if (empty($processed_message) && !empty($mediaHtml)) {
                if(!$is_me) echo "<div style='font-size:10px; color:#00ffaa; margin-bottom:4px;'>" . htmlspecialchars($senderName) . "</div>";
                echo $mediaHtml;
            } elseif (!empty($processed_message)) {
                echo "<div class='msg $class' id='msg-anchor-{$row['id']}' style='position:relative;'>";
                echo $pinned_indicator;
                if(!$is_me) echo "<div style='font-size:10px; color:#00ffaa; margin-bottom:2px;'>" . htmlspecialchars($senderName) . "</div>";
                echo $processed_message;
                echo $mediaHtml;
                echo "</div>";
            }

            if($is_me) echo $status_html;
            echo "</div>"; // End column wrapper
            echo $dots_html;
            echo "</div>"; // End msg-container
        }
    }

    // Get Chat Participants (for mention suggestions)
    if($_POST['action'] == 'get_chat_participants'){
        $group_id = $_POST['group_id'];
        $participants = [];
        
        $stmt = $conn->prepare("
            SELECT m.user_id as id, 
            COALESCE(s.student_name, t.name) as name, 
            COALESCE(s.profile_pic, t.profile_pic) as profile_pic 
            FROM group_chat_members m
            LEFT JOIN students s ON m.user_id = s.student_id 
            LEFT JOIN teachers t ON m.user_id = CONCAT('T-', t.id)
            WHERE m.group_id = ?
        ");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        while ($row = $res->fetch_assoc()) {
            $participants[] = $row;
        }
        echo json_encode($participants);
        exit;
    }

    // Fetch Media & Files for Sidebar (Group)
    if($_POST['action'] == 'fetch_sidebar_assets'){
        $group_id = $_POST['group_id'];
        $stmt = $conn->prepare("
            SELECT cm.file_path, cm.file_type, cm.uploaded_at as timestamp
            FROM chat_media cm
            JOIN group_chat_messages gcm ON cm.message_id = gcm.id
            WHERE cm.chat_type = 'group' AND gcm.group_id = ?
            AND gcm.timestamp > (SELECT cleared_at FROM group_chat_members WHERE group_id = ? AND user_id = ?)
            ORDER BY cm.uploaded_at DESC
        ");
        $stmt->bind_param("iis", $group_id, $group_id, $my_id);
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

    // Fetch Candidates for Add Member (Users not in group)
    if($_POST['action'] == 'get_candidates'){
        $group_id = $_POST['group_id'];
        // Get all students NOT in this group
        $sql = "SELECT * FROM students WHERE student_id NOT IN (SELECT user_id FROM group_chat_members WHERE group_id='$group_id') ORDER BY student_name";
        $res = $conn->query($sql);
        while($row = $res->fetch_assoc()){
             $pic = !empty($row['profile_pic']) ? "uploads/".$row['profile_pic'] : "assets/images/3icons8-student-64.png";
             echo '<div class="candidate-item">
                    <input type="checkbox" name="new_members[]" value="'.$row['student_id'].'">
                    <img src="'.$pic.'">
                    <span class="c-name">'.htmlspecialchars($row['student_name']).'</span>
                   </div>';
        }
    }

    // Fetch My Groups for Dropdown
    if($_POST['action'] == 'get_my_groups'){
        // Modified query to include unread count and sort by activity
        $sql = "SELECT g.id, g.name, g.group_icon, 
                (SELECT COUNT(*) FROM group_chat_messages msg 
                 WHERE msg.group_id = g.id 
                 AND msg.timestamp > m.last_read 
                 AND msg.sender_id != '$my_id') as unread_count,
                msg_latest.message as last_msg,
                msg_latest.timestamp as last_ts,
                msg_latest.sender_id as last_sender
                FROM group_chats g 
                JOIN group_chat_members m ON g.id = m.group_id 
                LEFT JOIN (
                    SELECT group_id, message, timestamp, sender_id
                    FROM group_chat_messages
                    WHERE id IN (SELECT MAX(id) FROM group_chat_messages GROUP BY group_id)
                ) AS msg_latest ON g.id = msg_latest.group_id
                WHERE m.user_id='$my_id' 
                ORDER BY COALESCE(msg_latest.timestamp, g.created_at) DESC";
        
        $res = $conn->query($sql);
        $data = [];
        while($row = $res->fetch_assoc()){
            $data[] = $row;
        }
        echo json_encode($data);
        exit();
    }

    // Leave Group
    if($_POST['action'] == 'leave_group'){
        $group_id = $_POST['group_id'];
        $conn->query("DELETE FROM group_chat_members WHERE group_id='$group_id' AND user_id='$my_id'");
        echo "success";
        exit();
    }

    // Check Unread Group Messages (Detailed for previews)
    if($_POST['action'] == 'check_unread'){
        $sql = "SELECT m.id, m.group_id, m.message, COALESCE(s.student_name, t.name) as sender_name 
                FROM group_chat_messages m
                JOIN group_chat_members mem ON m.group_id = mem.group_id
                LEFT JOIN students s ON m.sender_id = s.student_id
                LEFT JOIN teachers t ON m.sender_id = CONCAT('T-', t.id)
                WHERE mem.user_id = '$my_id' 
                AND m.sender_id != '$my_id'
                AND m.timestamp > mem.last_read";
        $res = $conn->query($sql);
        $unread = [];
        while($row = $res->fetch_assoc()) $unread[] = $row;
        echo json_encode($unread);
    }
}
?>