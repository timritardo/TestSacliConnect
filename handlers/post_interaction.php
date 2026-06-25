<?php
error_reporting(0); // Pigilan ang PHP warnings na lumabas sa screen
session_start();

// MANUALLY INCLUDE PHPMailer files - Siguraduhin na nandito ang mga files na ito
require_once __DIR__ . '/../vendor/autoload.php'; elseif (file_exists('vendor/autoload.php')) {
    // Backup: Gamitin ang Composer autoloader kung wala ang manual folder
    require_once 'vendor/autoload.php';
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'PHPMailer library not found. Mangyaring i-check ang PHPMailer folder o vendor folder.']);
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// --- HELPER FUNCTION PARA SA EMAIL NOTIFICATION ---
function sendNotificationEmail($to_email, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sacliconnect20@gmail.com';
        $mail->Password   = 'umrrmsyujepjopbo'; 
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->Timeout    = 10; // 10 seconds timeout para hindi mag-hang ang site

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->setFrom('sacliconnect20@gmail.com', 'SacliConnect');
        $mail->addAddress($to_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = "
            <div style='font-family: Arial; padding: 20px; border: 2px solid #00ffaa; border-radius: 10px; background-color: #0a1f16; color: #fff;'>
                <h2 style='color: #00ffaa;'>SacliConnect Notification</h2>
                <p style='font-size: 16px;'>$body</p>
                <hr style='border: 0.5px solid #00ffaa;'>
                <p style='font-size: 12px;'>This is an automated message. Please do not reply.</p>
            </div>";

        $mail->send();
    } catch (Exception $e) {
        // Optional: I-log ang error sa file kung kailangan
    }
}

// --- HELPER FUNCTION PARA SA MASKING NG EMAIL ---
function maskEmail($email) {
    if (empty($email)) return "N/A";
    $parts = explode("@", $email);
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len > 3) return substr($name, 0, 3) . str_repeat('*', $len - 3) . "@" . $domain;
    return $name . "@" . $domain;
}

// --- HELPER FUNCTION PARA SA SMS NOTIFICATION (SEMAPHORE) ---
function sendSMSNotification($number, $message) {
    $apikey = "84ade4c76ffef08037b29c672a017d33"; 
    $ch = curl_init();
    $parameters = array(
        'apikey' => $apikey,
        'number' => $number,
        'message' => $message,
        'sendername' => 'SEMAPHORE' 
    );
    curl_setopt($ch, CURLOPT_URL, 'https://semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    return $output;
}

require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) { 
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']); 
    exit(); 
}
if (!isset($_SESSION['student_id'])) { 
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Not logged in.']); 
    exit(); 
}

$my_id = $_SESSION['student_id'];
if (isset($_POST['action'])) {

    // Like/Unlike a post
    if ($_POST['action'] == 'react') {
        $post_id = intval($_POST['post_id']);
        $check = $conn->query("SELECT id FROM post_reactions WHERE post_id='$post_id' AND student_id='$my_id'");
        if($check->num_rows > 0){
            $conn->query("DELETE FROM post_reactions WHERE post_id='$post_id' AND student_id='$my_id'");
            
            // --- DELETE NOTIFICATION ON UNREACT ---
            $p_owner_res = $conn->query("SELECT student_name FROM posts WHERE id='$post_id'");
            if($p_owner_row = $p_owner_res->fetch_assoc()){
                $p_owner_name = $p_owner_row['student_name'];
                $owner_res = $conn->query("SELECT student_id FROM students WHERE student_name='$p_owner_name'");
                if($owner_row = $owner_res->fetch_assoc()){
                    $owner_id = $owner_row['student_id'];
                    // Tanggalin ang notification para hindi dumami
                    $conn->query("DELETE FROM notifications WHERE user_id='$owner_id' AND actor_id='$my_id' AND type='reaction' AND post_id='$post_id'");
                }
            }
            echo "unliked";
        } else {
            $conn->query("INSERT INTO post_reactions (post_id, student_id) VALUES ('$post_id', '$my_id')");
            // Notify post owner
            $p_owner_res = $conn->query("SELECT student_name FROM posts WHERE id='$post_id'");
            if($p_owner_row = $p_owner_res->fetch_assoc()){
                $p_owner_name = $p_owner_row['student_name'];
                $owner_res = $conn->query("SELECT student_id FROM students WHERE student_name='$p_owner_name'");
                if($owner_row = $owner_res->fetch_assoc()){
                    $owner_id = $owner_row['student_id'];
                    if($owner_id != $my_id){ // Don't notify self
                        $conn->query("INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES ('$owner_id', '$my_id', 'reaction', '$post_id')");
                        
                        // --- EMAIL ALERT LOGIC ---
                        $email_res = $conn->query("SELECT email, student_name FROM students WHERE student_id='$owner_id'");
                        if($email_row = $email_res->fetch_assoc()){
                            $target_email = $email_row['email'];
                            $actor_name = $_SESSION['student_name'];
                            $subject = "Someone reacted to your post!";
                            $msg = "Hi <b>{$email_row['student_name']}</b>, <br><br> <b>$actor_name</b> just reacted to your post. Open SacliConnect.";
                            sendNotificationEmail($target_email, $subject, $msg);
                        }
                    }
                }
            }
            echo "liked";
        }
        exit();
    }

    // Add a comment
    if ($_POST['action'] == 'comment') {
        $post_id = intval($_POST['post_id']);
        $comment = $conn->real_escape_string(trim($_POST['comment']));
        if(!empty($comment)){
            $conn->query("INSERT INTO post_comments (post_id, student_id, comment) VALUES ('$post_id', '$my_id', '$comment')");
            $comment_id = $conn->insert_id;

            // Notify post owner
            $p_owner_res = $conn->query("SELECT student_name FROM posts WHERE id='$post_id'");
            if($p_owner_row = $p_owner_res->fetch_assoc()){
                $p_owner_name = $p_owner_row['student_name'];
                $owner_res = $conn->query("SELECT student_id FROM students WHERE student_name='$p_owner_name'");
                if($owner_row = $owner_res->fetch_assoc()){
                    $owner_id = $owner_row['student_id'];
                    if($owner_id != $my_id){ // Don't notify self
                        $conn->query("INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES ('$owner_id', '$my_id', 'comment', '$post_id')");
                        
                        // --- EMAIL ALERT LOGIC ---
                        $email_res = $conn->query("SELECT email, student_name FROM students WHERE student_id='$owner_id'");
                        if($email_row = $email_res->fetch_assoc()){
                            $target_email = $email_row['email'];
                            $actor_name = $_SESSION['student_name'];
                            $subject = "New comment on your post!";
                            $msg = "Hi <b>{$email_row['student_name']}</b>, <br><br> <b>$actor_name</b> commented: \"<i>$comment</i>\". <br><br>Check it out now on SacliConnect!";
                            sendNotificationEmail($target_email, $subject, $msg);
                        }
                    }
                }
            }

            // Fetch the comment we just inserted to return it as HTML
            $c_res = $conn->query("SELECT c.*, COALESCE(s.student_name, t.name) as student_name, COALESCE(s.profile_pic, t.profile_pic) as profile_pic FROM post_comments c LEFT JOIN students s ON c.student_id = s.student_id LEFT JOIN teachers t ON c.student_id = CONCAT('T-', t.id) WHERE c.id='$comment_id'");
            $c = $c_res->fetch_assoc();
            $c_pic = !empty($c['profile_pic']) ? "uploads/".$c['profile_pic'] : "assets/images/3icons8-student-64.png";
            
            echo '<div class="comment-item" id="comment-'.$c['id'].'">
                    <img src="'.$c_pic.'" class="comment-avatar">
                    <div class="comment-bubble">
                        <div class="comment-header">
                            <strong>'.htmlspecialchars($c['student_name']).'</strong>
                            <span class="comment-time">Just now</span>
                        </div>
                        <p>'.htmlspecialchars($c['comment']).'</p>
                    </div>
                    <div class="comment-actions">
                        <button class="dots-btn">•••</button>
                        <div class="comment-menu">
                            <div class="comment-menu-item delete" onclick="deleteComment('.$c['id'].')">Delete</div>
                        </div>
                    </div>
                  </div>';
        }
        exit();
    }

    // Fetch all comments for a post (for the "View All" modal)
    if ($_POST['action'] == 'fetch_comments') {
        $post_id = intval($_POST['post_id']);
        $response = ['status' => 'success', 'post_html' => '', 'comments_html' => ''];

        // --- Fetch Post Details ---
        $post_query = "SELECT p.*, 
                       COALESCE(s.profile_pic, t.profile_pic) as poster_profile_pic, 
                       COALESCE(s.student_id, CONCAT('T-', t.id)) as poster_id,
                       COALESCE(s.student_name, t.name) as poster_name
                       FROM posts p 
                       LEFT JOIN students s ON p.student_name = s.student_name
                       LEFT JOIN teachers t ON p.student_name = t.name
                       WHERE p.id = '$post_id'";
        $post_res = $conn->query($post_query);
        $post = $post_res->fetch_assoc();

        if ($post) {
            $poster_pic = !empty($post['poster_profile_pic']) ? "uploads/".$post['poster_profile_pic'] : "assets/images/3icons8-student-64.png";
            $profile_link = !empty($post['poster_id']) ? "Student_Profile.php?id=".$post['poster_id'] : "#";
            
            // Fetch Tags with IDs
            $tags_res = $conn->query("SELECT t.student_id as user_id, COALESCE(s.student_name, te.name) as student_name 
                                      FROM post_tags t 
                                      LEFT JOIN students s ON t.student_id = s.student_id 
                                      LEFT JOIN teachers te ON t.student_id = CONCAT('T-', te.id)
                                      WHERE t.post_id = '$post_id'");
            $tag_display = "";
            if($tags_res && $tags_res->num_rows > 0){
                $tagged_users = [];
                while($t = $tags_res->fetch_assoc()) $tagged_users[] = $t;
                $first_tag = $tagged_users[0];
                $first_tag_name = htmlspecialchars($first_tag['student_name']);
                $first_tag_id = $first_tag['user_id'];
                $other_count = count($tagged_users) - 1;
                $tag_display = " <span style='color:#b0b3b8; font-weight:normal;'>is with</span> <a href='Student_Profile.php?id=$first_tag_id' style='color:#e4e6eb; text-decoration:none; font-weight:bold;'>$first_tag_name</a>";
                if($other_count > 0) $tag_display .= " <span style='color:#b0b3b8;'>and </span><a href='javascript:void(0)' onclick='openTaggedUsersModal($post_id)' style='color:#b0b3b8; text-decoration:none; font-weight:bold;'>$other_count others</a>";
            }

            $post_content_html = htmlspecialchars($post['content']);
            // Find and highlight @mentions
            $post_content_html = preg_replace('/@([\w\d\s.-]+)/', '<span class="tagged-user">@$1</span>', $post_content_html);

            ob_start(); // Start output buffering to capture HTML
            ?>
            <div class="modal-post-content" id="modal-post-<?php echo $post_id; ?>">
                <div style="display: flex; align-items: center; margin-bottom: 10px;">
                    <a href="<?php echo $profile_link; ?>">
                        <img src="<?php echo $poster_pic; ?>" class="post-profile-img">
                    </a>
                    <div>
                        <h4 style="margin: 0; color: #e4e6eb; font-size: 15px;">
                            <a href="<?php echo $profile_link; ?>" style="color: inherit; text-decoration: none; font-weight: 600;">
                                <?php echo htmlspecialchars($post['poster_name']); ?>
                            </a>
                            <?php echo $tag_display; ?>
                        </h4>
                        <span class="time" style="color: #b0b3b8; font-size: 12px;"><?php echo date("M d, Y H:i", strtotime($post['timestamp'])); ?></span>
                    </div>
                </div>
                <p style="white-space: pre-wrap; margin-bottom: 15px;"><?php echo $post_content_html; ?></p>
                <?php
                // --- MULTI-MEDIA DISPLAY LOGIC ---
                $media_res = $conn->query("SELECT * FROM post_media WHERE post_id='$post_id'");
                $media_files = [];
                if($media_res && $media_res->num_rows > 0){
                    while($m = $media_res->fetch_assoc()) $media_files[] = $m;
                }

                $count = count($media_files);
                if($count > 0){
                    $display_limit = 5; // Limit to 5 for modal display
                    $layout_count = min($count, 5);
                    $grid_class = 'grid-' . $layout_count;

                    echo '<div class="media-grid '.$grid_class.'">';
                    for($i=0; $i < $layout_count; $i++){
                        $file = $media_files[$i];
                        $path = htmlspecialchars($file['file_path']);
                        $is_video = ($file['file_type'] == 'video');
                        echo '<div class="media-item">';
                        if ($is_video) { echo '<video src="'.$path.'" controls playsinline style="max-width:100%; max-height:300px; object-fit:cover;"></video>'; } 
                        else { echo '<img src="'.$path.'" style="max-width:100%; max-height:300px; object-fit:cover;">'; }
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
            <?php
            $response['post_html'] = ob_get_clean(); // Capture and store post HTML
        }

        // --- Fetch Comments ---
        ob_start(); // Start output buffering for comments
        $comments = $conn->query("SELECT c.*, COALESCE(s.student_name, t.name) as student_name, COALESCE(s.profile_pic, t.profile_pic) as profile_pic FROM post_comments c LEFT JOIN students s ON c.student_id = s.student_id LEFT JOIN teachers t ON c.student_id = CONCAT('T-', t.id) WHERE c.post_id='$post_id' ORDER BY c.is_pinned DESC, c.timestamp ASC");
        
        if ($comments->num_rows === 0) {
            echo '<div style="text-align:center; padding:20px; color:#aaa;">No comments yet.</div>';
        } else {
            while($c = $comments->fetch_assoc()){
                $c_pic = !empty($c['profile_pic']) ? "uploads/".$c['profile_pic'] : "assets/images/3icons8-student-64.png";
                $is_my_comment = ($c['student_id'] == $my_id);
                $is_pinned = $c['is_pinned'] == 1;
                $post_owner_name = isset($post['poster_name']) ? $post['poster_name'] : ''; // Get post owner name for delete check
                $is_post_owner = ($post_owner_name == $_SESSION['student_name']);

                echo '<div class="comment-item '.($is_pinned ? 'pinned' : '').'" id="comment-'.$c['id'].'">
                        <img src="'.$c_pic.'" class="comment-avatar">
                        <div class="comment-bubble">';
                if($is_pinned) echo '<div class="pinned-label">📌 Pinned</div>';
                echo '      <div class="comment-header">
                                <strong>'.htmlspecialchars($c['student_name']).'</strong>
                                <span class="comment-time">'.date("M d H:i", strtotime($c['timestamp'])).'</span>
                            </div>
                            <p>'.htmlspecialchars($c['comment']).'</p>
                        </div>';
                
                if ($is_my_comment || $is_post_owner) { // Allow post owner to delete any comment
                    echo '<div class="comment-actions">
                            <button class="dots-btn">•••</button>
                            <div class="comment-menu">
                                ';
                    if($is_post_owner && !$is_pinned) echo '<div class="comment-menu-item" onclick="pinComment('.$c['id'].', '.$post_id.')">Pin</div>';
                    if($is_post_owner && $is_pinned) echo '<div class="comment-menu-item" onclick="unpinComment('.$c['id'].', '.$post_id.')">Unpin</div>';
                    if($is_my_comment || $is_post_owner) echo '<div class="comment-menu-item delete" onclick="deleteComment('.$c['id'].')">Delete</div>';
                    echo '
                            </div>
                          </div>';
                }
                echo '</div>';
            }
        }
        $response['comments_html'] = ob_get_clean(); // Capture and store comments HTML

        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    // Delete a comment
    if ($_POST['action'] == 'delete_comment') {
        $comment_id = intval($_POST['comment_id']);
        $c_res = $conn->query("SELECT c.student_id, p.student_name FROM post_comments c JOIN posts p ON c.post_id = p.id WHERE c.id = '$comment_id'");
        if($c_res && $c_row = $c_res->fetch_assoc()){
            if($c_row['student_id'] == $my_id || $c_row['student_name'] == $_SESSION['student_name']){
                $conn->query("DELETE FROM post_comments WHERE id = '$comment_id'");
                echo "success";
            }
        }
        exit();
    }

    // Delete a post
    if ($_POST['action'] == 'delete_post') {
        $post_id = intval($_POST['post_id']);
        $p_res = $conn->query("SELECT student_name FROM posts WHERE id = '$post_id'");
        if($p_res && $p_row = $p_res->fetch_assoc()){
            if($p_row['student_name'] == $_SESSION['student_name']){
                $conn->query("DELETE FROM posts WHERE id=$post_id");
                $conn->query("DELETE FROM post_media WHERE post_id=$post_id");
                $conn->query("DELETE FROM post_comments WHERE post_id=$post_id");
                $conn->query("DELETE FROM post_reactions WHERE post_id=$post_id");
                $conn->query("DELETE FROM notifications WHERE post_id=$post_id");
                echo "success";
            }
        }
        exit();
    }

    // Delete conversation for me (Hide it until new message arrives)
    if ($_POST['action'] == 'delete_convo_for_me') {
        $other_id = $conn->real_escape_string($_POST['other_id']);
        $type = $_POST['chat_type'];
        $conn->query("CREATE TABLE IF NOT EXISTS chat_clear_history (id INT AUTO_INCREMENT PRIMARY KEY, user_id VARCHAR(50), other_id VARCHAR(50), cleared_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (user_id, other_id))");

        if ($type === 'group') {
            $stmt = $conn->prepare("UPDATE group_chat_members SET cleared_at = NOW() WHERE group_id = ? AND user_id = ?");
            $stmt->bind_param("is", $other_id, $my_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO chat_clear_history (user_id, other_id, cleared_at) 
                                    VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE cleared_at = NOW()");
            $stmt->bind_param("ss", $my_id, $other_id);
        }
        if ($stmt->execute()) echo "success";
        else echo "error";
        $stmt->close();
        exit();
    }

    // Fetch message list for dropdown
    if ($_POST['action'] == 'fetch_msgs') {
        $my_id = $_SESSION['student_id'];
        $conn->query("CREATE TABLE IF NOT EXISTS chat_clear_history (id INT AUTO_INCREMENT PRIMARY KEY, user_id VARCHAR(50), other_id VARCHAR(50), cleared_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY (user_id, other_id))");

        $sql = "SELECT m.*, 
                IF(m.sender_id = ?, m.receiver_id, m.sender_id) as other_id,
                (SELECT COUNT(*) FROM direct_messages WHERE sender_id = other_id AND receiver_id = ? AND is_read = 0) as unread_count
                FROM direct_messages m
                INNER JOIN (
                    SELECT LEAST(sender_id, receiver_id) as p1, GREATEST(sender_id, receiver_id) as p2, MAX(id) as max_id
                    FROM direct_messages
                    WHERE sender_id = ? OR receiver_id = ?
                    GROUP BY p1, p2
                ) AS latest ON m.id = latest.max_id
                LEFT JOIN chat_clear_history cch ON cch.user_id = ? AND cch.other_id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
                WHERE m.timestamp > COALESCE(cch.cleared_at, '1000-01-01')
                ORDER BY m.timestamp DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $my_id, $my_id, $my_id, $my_id, $my_id, $my_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $messages = [];
        while ($row = $res->fetch_assoc()) {
            $other_id = $row['other_id'];
            
            $user_info = null;
            if (strpos($other_id, 'T-') === 0) {
                $real_id = substr($other_id, 2);
                $user_stmt = $conn->prepare("SELECT name as student_name, profile_pic, is_online FROM teachers WHERE id = ?");
                $user_stmt->bind_param("s", $real_id);
            } else {
                $user_stmt = $conn->prepare("SELECT student_name, profile_pic, is_online FROM students WHERE student_id = ?");
                $user_stmt->bind_param("s", $other_id);
            }
            $user_stmt->execute();
            $user_res = $user_stmt->get_result();
            $user_info = $user_res->fetch_assoc(); // This can be null
            $user_stmt->close();

            if ($user_info) {
                $row['student_name'] = $user_info['student_name'];
                $row['profile_pic'] = $user_info['profile_pic'];
                $row['is_online'] = $user_info['is_online'];

                $row['is_unread'] = $row['unread_count'] > 0 ? 1 : 0;
                $messages[] = $row;
            }
            // Kapag ang account ay deleted na (null ang user_info), hindi na natin 
            // isasama ang conversation na ito sa listahan para hindi na ito lumitaw.
        }
        header('Content-Type: application/json');
        echo json_encode($messages);
        exit();
    }

    // Deep Search: Hanapin ang mga conversations na may keyword sa history
    if ($_POST['action'] == 'search_conversations_deep') {
        $q = "%" . $conn->real_escape_string($_POST['query']) . "%";
        $results = [];

        // 1. Mag-search sa Direct Messages
        $dm_sql = "SELECT DISTINCT IF(sender_id = ?, receiver_id, sender_id) as other_id 
                   FROM direct_messages 
                   WHERE (sender_id = ? OR receiver_id = ?) 
                   AND message LIKE ?";
        $stmt = $conn->prepare($dm_sql);
        $stmt->bind_param("ssss", $my_id, $my_id, $my_id, $q);
        $stmt->execute();
        $dm_res = $stmt->get_result();
        while($r = $dm_res->fetch_assoc()) {
            $results[] = ['id' => $r['other_id'], 'type' => 'direct'];
        }

        // 2. Mag-search sa Group Chats
        $gc_sql = "SELECT DISTINCT g.id 
                   FROM group_chats g 
                   JOIN group_chat_members m ON g.id = m.group_id 
                   JOIN group_chat_messages msg ON g.id = msg.group_id 
                   WHERE m.user_id = ? 
                   AND msg.message LIKE ?";
        $stmt = $conn->prepare($gc_sql);
        $stmt->bind_param("ss", $my_id, $q);
        $stmt->execute();
        $gc_res = $stmt->get_result();
        while($r = $gc_res->fetch_assoc()) {
            $results[] = ['id' => $r['id'], 'type' => 'group'];
        }

        header('Content-Type: application/json');
        echo json_encode($results);
        exit();
    }

    // Fetch tagged users for the "others" modal
    if ($_POST['action'] == 'fetch_tagged_users') {
        $post_id = intval($_POST['post_id']);
        $sql = "SELECT t.student_id as user_id, COALESCE(s.student_name, te.name) as student_name, COALESCE(s.profile_pic, te.profile_pic) as profile_pic 
                FROM post_tags t 
                LEFT JOIN students s ON t.student_id = s.student_id 
                LEFT JOIN teachers te ON t.student_id = CONCAT('T-', te.id)
                WHERE t.post_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $post_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $tagged = [];
        while($row = $res->fetch_assoc()){
            $row['profile_pic'] = !empty($row['profile_pic']) ? "uploads/".$row['profile_pic'] : (strpos($row['user_id'], 'T-') === 0 ? "4icons8-teacher-50.png" : "assets/images/3icons8-student-64.png");
            $tagged[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode($tagged);
        exit();
    }

    // Fetch notifications
    if ($_POST['action'] == 'fetch_notifs') {
        $sql = "SELECT n.*, 
                COALESCE(s.student_name, t.name) as student_name, 
                COALESCE(s.profile_pic, t.profile_pic) as profile_pic
                FROM notifications n
                LEFT JOIN students s ON n.actor_id = s.student_id
                LEFT JOIN teachers t ON n.actor_id = CONCAT('T-', t.id)
                WHERE n.user_id = ?
                ORDER BY n.timestamp DESC
                LIMIT 20";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $my_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $notifs = [];
        while($row = $res->fetch_assoc()){
            if ($row['actor_id'] === 'Admin') {
                $row['student_name'] = 'Admin';
                $row['profile_pic'] = 'Adobe Express - file.png';
            }
            $notifs[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode($notifs);
        exit();
    }

    // Broadcast event to all users via email (Para sa "Now Event" section)
    if ($_POST['action'] == 'broadcast_event') {
        $event_id = intval($_POST['event_id']);
        $ev_res = $conn->query("SELECT * FROM calendar_events WHERE id = $event_id");
        
        if ($ev = $ev_res->fetch_assoc()) {
            $title = $ev['title'];
            $date = date("F d, Y", strtotime($ev['event_date']));
            $desc = $ev['description'];
            
            $subject = "SacliConnect Alert: Upcoming Event - $title";
            $body = "Hi, we have an exciting upcoming event at Sacli!<br><br>
                     <b>Event Title:</b> $title<br>
                     <b>Scheduled Date:</b> $date<br>
                     <b>Details:</b> $desc<br><br>
                     Please check the SacliConnect Dashboard for more details. See you there!";
            
            // Kunin lahat ng active students
            $students = $conn->query("SELECT email FROM students WHERE is_restricted = 0 AND email != ''");
            while ($s = $students->fetch_assoc()) {
                sendNotificationEmail($s['email'], $subject, $body);
            }
            
            // Kunin lahat ng teachers
            $teachers = $conn->query("SELECT email FROM teachers WHERE email != ''");
            while ($t = $teachers->fetch_assoc()) {
                sendNotificationEmail($t['email'], $subject, $body);
            }
            
            echo json_encode(['status' => 'success', 'message' => 'Broadcast sent! All students and teachers have been notified.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Event record not found in database.']);
        }
        exit();
    }

    // Mark notification as read
    if ($_POST['action'] == 'mark_notif_read') {
        $notif_id = intval($_POST['notif_id']);
        $conn->query("UPDATE notifications SET is_read = 1 WHERE id = '$notif_id' AND user_id = '$my_id'");
        exit();
    }

    // Mark all notifications as read (Facebook style: clear badge on open)
    if ($_POST['action'] == 'mark_all_notifs_read') {
        $conn->query("UPDATE notifications SET is_read = 1 WHERE user_id = '$my_id'");
        echo "success";
        exit();
    }

    // Check for unread notifications
    if ($_POST['action'] == 'check_unread') {
        $res = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE user_id = '$my_id' AND is_read = 0");
        echo $res->fetch_assoc()['count'];
        exit();
    }

    // Increment post view count
    if ($_POST['action'] == 'increment_view') {
        $post_id = intval($_POST['post_id']);
        
        // --- UNIQUE VIEW LOGIC ---
        // I-check kung na-view na ng user na ito ang post na ito dati
        $v_check = $conn->query("SELECT id FROM post_views_tracking WHERE post_id='$post_id' AND student_id='$my_id'");
        if($v_check->num_rows == 0) {
            $conn->query("INSERT INTO post_views_tracking (post_id, student_id) VALUES ('$post_id', '$my_id')");
            $conn->query("UPDATE posts SET views = views + 1 WHERE id = '$post_id'");
        }
        
        $res = $conn->query("SELECT views FROM posts WHERE id = '$post_id'");
        echo $res->fetch_assoc()['views'];
        exit();
    }

    // Handle password change approval/denial
    if ($_POST['action'] == 'approve_pass_change') {
        $request_id = intval($_POST['request_id']);
        // Get request details
        $req_res = $conn->query("SELECT * FROM password_change_requests WHERE id = '$request_id' AND user_id = '$my_id' AND status = 'pending'");
        if($req = $req_res->fetch_assoc()){
            $new_pass = password_hash($req['new_password'], PASSWORD_DEFAULT);
            $conn->query("UPDATE students SET password = '$new_pass' WHERE student_id = '$my_id'");
            $conn->query("UPDATE password_change_requests SET status = 'approved', approved_at = NOW() WHERE id = '$request_id'");
            echo "success";
        }
        exit();
    }

    if ($_POST['action'] == 'deny_pass_change') {
        $request_id = intval($_POST['request_id']);
        $conn->query("UPDATE password_change_requests SET status = 'denied' WHERE id = '$request_id' AND user_id = '$my_id'");
        echo "success";
        exit();
    }

    // Pin/Unpin Comment
    if ($_POST['action'] == 'pin_comment' || $_POST['action'] == 'unpin_comment') {
        $comment_id = intval($_POST['comment_id']);
        $post_id = intval($_POST['post_id']);
        $is_pinning = $_POST['action'] == 'pin_comment';

        // Check if user is the post owner
        $p_res = $conn->query("SELECT student_name FROM posts WHERE id = '$post_id'");
        if($p_row = $p_res->fetch_assoc()){
            if($p_row['student_name'] == $_SESSION['student_name']){
                // Unpin any existing pinned comment for this post first
                $conn->query("UPDATE post_comments SET is_pinned = 0 WHERE post_id = '$post_id'");
                if($is_pinning){
                    // Pin the new one
                    $conn->query("UPDATE post_comments SET is_pinned = 1 WHERE id = '$comment_id'");
                }
                echo "success";
            }
        }
        exit();
    }

    // --- CHANGE PASSWORD AUTHENTICATION HANDLERS ---

    // Step 1: Verify current password and send OTP
    if ($_POST['action'] == 'cp_verify_password') {
        header('Content-Type: application/json');
        $current_pass = $_POST['password'];
        $valid = false;
        $email = "";
        $user_type = $_SESSION['user_type'] ?? 'student';
        
        if ($user_type === 'teacher') {
            $real_id = str_replace("T-", "", $my_id);
            $res = $conn->query("SELECT password, email FROM teachers WHERE id='$real_id'");
            $row = $res->fetch_assoc();
            if ($row && $current_pass === $row['password']) {
                $valid = true;
                $email = $row['email'];
            }
        } else {
            $res = $conn->query("SELECT student_id, password, email FROM students WHERE student_id='$my_id'");
            $row = $res->fetch_assoc();
            if ($row && !empty($row['password']) && password_verify($current_pass, $row['password'])) {
                $valid = true;
                $email = $row['email'];
            }
        }

        if ($valid) {
            if (empty($email)) {
                echo json_encode(['status' => 'error', 'message' => 'No email registered to this account. Please update your profile with a valid email.']);
                exit();
            }
            
            $otp = rand(100000, 999999);
            $_SESSION['cp_otp'] = $otp;
            $_SESSION['cp_otp_expiry'] = time() + 60; // 1 min

            // Send Email using PHPMailer instead of mail()
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                /** 
                 * DEBUGGING TIP: I-uncomment ang line sa ibaba (burahin ang //) 
                 * para makita ang eksaktong dahilan ng error sa browser Network tab.
                 */
                // $mail->SMTPDebug = 2; 
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;

                // FIX: Ginamit ang App Password na nakita sa send_otp.php para mag-match.
                $mail->Username   = 'sacliconnect20@gmail.com';
                $mail->Password   = 'umrrmsyujepjopbo'; 
                
                $mail->SMTPSecure = 'tls';
                $mail->Port       = 587;
                $mail->Timeout    = 15;

                // Dagdag na SMTPOptions para sa localhost/XAMPP compatibility
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                $mail->setFrom('sacliconnect20@gmail.com', 'Sacli Connect');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Verification Code for Password Change';
                $mail->Body    = "
                    <div style='font-family: Arial; padding: 20px; border: 1px solid #00ffaa; background-color: #0a1f16; color: #fff; border-radius: 10px;'>
                        <h2 style='color: #00ffaa;'>SacliConnect Security</h2>
                        <p>Hello! Your 6-digit verification code is:</p>
                        <h1 style='letter-spacing: 5px; color: #46d811;'>$otp</h1>
                        <p>This code will expire in 10 minutes. If you did not request this, please secure your account.</p>
                    </div>";

                $mail->send();
                echo json_encode(['status' => 'success', 'email' => $email]);
            } catch (PHPMailerException $e) {
                echo json_encode(['status' => 'error', 'message' => 'Mailer Error: ' . $mail->ErrorInfo]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Incorrect current password.']);
        }
        exit();
    }

    // Step 2: Verify OTP
    if ($_POST['action'] == 'cp_verify_otp') {
        header('Content-Type: application/json');
        $otp = $_POST['otp'];
        if (isset($_SESSION['cp_otp']) && $_SESSION['cp_otp'] == $otp && time() < $_SESSION['cp_otp_expiry']) {
            $_SESSION['cp_verified'] = true;
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code.']);
        }
        exit();
    }

    // --- PROFILE UPDATE VERIFICATION HANDLERS ---
    if ($_POST['action'] == 'profile_verify_send') {
        header('Content-Type: application/json');
        $user_type = $_SESSION['user_type'] ?? 'student';
        if ($user_type === 'teacher') {
            $real_id = str_replace("T-", "", $my_id);
            $res = $conn->query("SELECT email FROM teachers WHERE id='$real_id'");
        } else {
            $res = $conn->query("SELECT email FROM students WHERE student_id='$my_id'");
        }
        $row = $res->fetch_assoc();
        $current_email = $row['email'] ?? '';

        if (empty($current_email)) {
             echo json_encode(['status' => 'error', 'message' => 'No current email found to send verification code. Please update your profile without changing email/phone first.']);
             exit();
        }

        $otp = rand(100000, 999999);
        $_SESSION['profile_update_otp'] = $otp;
        $_SESSION['profile_update_otp_expiry'] = time() + 300; // 5 mins

        $mail = new PHPMailer(true);
        $profile_url = "http://" . $_SERVER['HTTP_HOST'] . "/Capstone_Project_2026/Student_Profile.php";

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'sacliconnect20@gmail.com';
            $mail->Password   = 'umrrmsyujepjopbo'; 
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;
            $mail->SMTPOptions = array('ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true));

            $mail->setFrom('sacliconnect20@gmail.com', 'Sacli Connect');
            $mail->addAddress($current_email);
            $mail->isHTML(true);
            $mail->Subject = 'Verification Code for Profile Update';
            $mail->Body    = "
                <div style='font-family: Arial; padding: 20px; border: 1px solid #00ffaa; background-color: #0a1f16; color: #fff; border-radius: 10px;'>
                    <h2 style='color: #00ffaa;'>SacliConnect Security</h2>
                    <p>You requested to update your email or phone number. Use the code below to authorize this change:</p>
                    <h1 style='letter-spacing: 10px; color: #46d811; text-align:center; background:rgba(0,0,0,0.3); padding:10px; border-radius:5px;'>$otp</h1>
                    <p style='text-align:center; margin-top:20px;'>
                        <a href='$profile_url' style='display:inline-block; padding:10px 20px; background:#00ffaa; color:#0a1f16; text-decoration:none; border-radius:5px; font-weight:bold;'>CONFIRM ON PROFILE PAGE</a>
                    </p>
                    <p style='margin-top:20px; font-size:12px; color:#888;'>This code will expire in 5 minutes. If you did not request this, please ignore this transmission.</p>
                </div>";
            $mail->send();
            echo json_encode(['status' => 'success', 'email' => maskEmail($current_email)]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Mailer Error: ' . $mail->ErrorInfo]);
        }
        exit();
    }

    if ($_POST['action'] == 'profile_verify_otp') {
        header('Content-Type: application/json');
        $otp = $_POST['otp'];
        if (isset($_SESSION['profile_update_otp']) && $_SESSION['profile_update_otp'] == $otp && time() < $_SESSION['profile_update_otp_expiry']) {
            $_SESSION['profile_update_verified'] = true;
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired code.']);
        }
        exit();
    }

       // --- PROFILE UPDATE VERIFICATION HANDLERS (New Flow) ---
    if ($_POST['action'] == 'request_new_contact_verification') {
        header('Content-Type: application/json');
        
        // Siguraduhin na may table para sa pending changes
        $conn->query("CREATE TABLE IF NOT EXISTS pending_profile_changes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(50) NOT NULL,
            change_type ENUM('email', 'phone') NOT NULL,
            new_value VARCHAR(255) NOT NULL,
            verification_token VARCHAR(255) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $change_type = $_POST['change_type']; // 'email' or 'phone'
        $new_value = trim($_POST['new_value']);

        if (empty($new_value)) {
            echo json_encode(['status' => 'error', 'message' => 'New value cannot be empty.']);
            exit();
        }

        // Basic validation
        if ($change_type === 'email' && !filter_var($new_value, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
            exit();
        }
        // Add phone number validation if needed

        // Kunin ang current email ng user para sa phone verification
        $u_type = $_SESSION['user_type'] ?? 'student';
        if ($u_type === 'teacher') {
            $real_id = str_replace("T-", "", $my_id);
            $curr_u = $conn->query("SELECT email FROM teachers WHERE id='$real_id'")->fetch_assoc();
        } else {
            $curr_u = $conn->query("SELECT email FROM students WHERE student_id='$my_id'")->fetch_assoc();
        }
        $current_email = $curr_u['email'] ?? '';

        // Generate a unique token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + 3600); // Link valid for 1 hour

        // Store the request in a temporary table
        $stmt = $conn->prepare("INSERT INTO pending_profile_changes (user_id, change_type, new_value, verification_token, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $my_id, $change_type, $new_value, $token, $expires_at);

        if ($stmt->execute()) {
            $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/Capstone_Project_2026/verify_profile_change.php?token=" . $token;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'sacliconnect20@gmail.com';
                $mail->Password   = 'umrrmsyujepjopbo'; 
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->Timeout    = 20;
                $mail->SMTPOptions = array('ssl'=>array('verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true));

                $mail->setFrom('sacliconnect20@gmail.com', 'Sacli Connect');
                
                // Kung email ang pinapalitan, i-verify ang BAGONG email.
                // Kung phone ang pinapalitan, i-verify gamit ang KASALUKUYANG email.
                $target_email = ($change_type === 'email') ? $new_value : $current_email;
                if (empty($target_email)) {
                    echo json_encode(['status' => 'error', 'message' => 'No valid email found to send verification link.']);
                    exit();
                }
                $mail->addAddress($target_email);
                
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Profile Update';
                $mail->Body    = "
                    <div style='font-family: Arial; padding: 20px; border: 1px solid #00ffaa; background-color: #0a1f16; color: #fff; border-radius: 10px;'>
                        <h2 style='color: #00ffaa;'>SacliConnect Profile Update</h2>
                        <p>You recently requested to change your " . $change_type . " to: <strong>" . htmlspecialchars($new_value) . "</strong>.</p>
                        <p>To confirm this change, please click the link below:</p>
                        <p style='text-align:center; margin-top:20px;'>
                            <a href='$verification_link' style='display:inline-block; padding:10px 20px; background:#00ffaa; color:#0a1f16; text-decoration:none; border-radius:5px; font-weight:bold;'>VERIFY AND UPDATE</a>
                        </p>
                        <p style='margin-top:20px; font-size:12px; color:#888;'>This link will expire in 1 hour. If you did not request this change, please ignore this email.</p>
                    </div>";
                $mail->send();
                echo json_encode(['status' => 'success', 'message' => 'Verification link sent to ' . maskEmail($target_email) . '. Please check your inbox.']);
            } catch (PHPMailerException $e) {
                echo json_encode(['status' => 'error', 'message' => 'Mailer Error: Could not send verification link. ' . $mail->ErrorInfo]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to record verification request.']);
        }
        exit();
    }

    // Step 3: Update Password
    if ($_POST['action'] == 'cp_finalize') {
        header('Content-Type: application/json');
        if (!isset($_SESSION['cp_verified']) || $_SESSION['cp_verified'] !== true) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized session. Please start over.']);
            exit();
        }
        
        $new_pass = $_POST['new_password'];
        $user_type = $_SESSION['user_type'] ?? 'student';

        if ($user_type === 'teacher') {
            $real_id = str_replace("T-", "", $my_id);
            $stmt = $conn->prepare("UPDATE teachers SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $new_pass, $real_id);
        } else {
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            // UPDATE: Only change the password field. The student_id remains permanent.
            $stmt = $conn->prepare("UPDATE students SET password = ? WHERE student_id = ?");
            $stmt->bind_param("ss", $hashed, $my_id);
        }
        
        if ($stmt->execute()) {
            // --- I-LOG ANG PASSWORD CHANGE SA ACTIVITY STREAM ---
            $ip = $_SERVER['REMOTE_ADDR'];
            $ua = $_SERVER['HTTP_USER_AGENT'];
            $event_type = "PASSWORD_CHANGE";
            $severity = "medium";

            // Siguraduhin na exist ang table para hindi mag-error
            $conn->query("CREATE TABLE IF NOT EXISTS security_audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(50),
                event_type VARCHAR(50),
                ip_address VARCHAR(45),
                user_agent TEXT,
                severity ENUM('low', 'medium', 'high') DEFAULT 'low',
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )");

            $log_stmt = $conn->prepare("INSERT INTO security_audit_logs (user_id, event_type, ip_address, user_agent, severity) VALUES (?, ?, ?, ?, ?)");
            $log_stmt->bind_param("sssss", $my_id, $event_type, $ip, $ua, $severity);
            $log_stmt->execute();
            $log_stmt->close();

            // We no longer need the cascading updates or session ID update 
            // because the student_id (the primary key for links) never changes.

            unset($_SESSION['cp_otp'], $_SESSION['cp_otp_expiry'], $_SESSION['cp_verified']);
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update database.']);
        }
        exit();
    }

    // Terminate specific session
    if ($_POST['action'] == 'terminate_session') {
        $sid = intval($_POST['session_id']);
        $conn->query("DELETE FROM user_active_sessions WHERE id = '$sid' AND user_id = '$my_id'");
        echo "success";
        exit();
    }

    // Logout all other devices
    if ($_POST['action'] == 'logout_all_devices') {
        $current_token = $conn->real_escape_string($_COOKIE['SECURE_SESS'] ?? '');
        $conn->query("DELETE FROM user_active_sessions WHERE user_id = '$my_id' AND session_token != '$current_token'");
        echo "success";
        exit();
    }
}
?> 