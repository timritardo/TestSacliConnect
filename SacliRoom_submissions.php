<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['student_id']) || ($_SESSION['user_type'] ?? 'student') !== 'teacher') {
    die("Access Denied. Teachers only.");
}

$my_id = $_SESSION['student_id'];
$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

if ($post_id === 0) {
    die("Invalid Post ID.");
}

// Fetch post and room info, and perform security check
$post_info_stmt = $conn->prepare("
    SELECT p.title, p.room_id, r.teacher_id, r.name as room_name 
    FROM sacli_room_posts p 
    JOIN sacli_rooms r ON p.room_id = r.id 
    WHERE p.id = ?
");
$post_info_stmt->bind_param("i", $post_id);
$post_info_stmt->execute();
$post_info = $post_info_stmt->get_result()->fetch_assoc();
$post_info_stmt->close();

if (!$post_info || $post_info['teacher_id'] !== $my_id) {
    die("Access Denied or Post Not Found.");
}

// Fetch all students and their submissions for this post
$submissions_sql = "
    SELECT 
        s.student_id, s.student_name, s.profile_pic,
        sub.id as submission_id, sub.file_path, sub.submitted_at, sub.grade
    FROM sacli_room_members m
    JOIN students s ON m.student_id = s.student_id
    LEFT JOIN sacli_room_submissions sub ON s.student_id = sub.student_id AND sub.post_id = ?
    WHERE m.room_id = ? AND m.role = 'student'
    ORDER BY sub.submitted_at DESC, s.student_name ASC
";
$submissions_stmt = $conn->prepare($submissions_sql);
$submissions_stmt->bind_param("ii", $post_id, $post_info['room_id']);
$submissions_stmt->execute();
$submissions_res = $submissions_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submissions for <?php echo htmlspecialchars($post_info['title']); ?></title>
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Re-use styles from SacliRoom_view.php for consistency */
        body { background: #0a1f16; color: #e4e6eb; font-family: 'Google Sans', 'Segoe UI', sans-serif; }
        .submissions-container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
        .submissions-header { padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .submissions-header h1 { margin: 0; font-size: 28px; color: #fff; }
        .submissions-header p { margin: 5px 0 0; color: #aaa; }
        .back-link { color: #00ffaa; text-decoration: none; font-weight: bold; display: inline-block; margin-bottom: 20px; }
        
        .submission-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: background 0.2s;
        }
        .submission-item:hover { background: #1a3d2f; }
        .sub-student-info { flex: 1; display: flex; align-items: center; gap: 15px; }
        .sub-student-info img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
        .sub-student-info span { font-size: 16px; font-weight: 500; }
        
        .sub-status { flex: 0 0 120px; text-align: center; }
        .sub-status .status-text { font-size: 13px; font-weight: 500; display: block; }
        .status-turned-in { color: #00ffaa; }
        .status-graded { color: #8c9eff; }
        .status-missing { color: #ff8a80; }
        .sub-status .time { font-size: 11px; color: #aaa; }
        
        .sub-file { flex: 1; text-align: center; }
        .sub-file a { color: #8ab4f8; text-decoration: none; font-weight: 500; }
        .sub-file a:hover { text-decoration: underline; }
        
        .sub-grade { flex: 0 0 200px; display: flex; gap: 10px; align-items: center; }
        .sub-grade input {
            width: 80px;
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #00ffaa;
            background: #0a1f16;
            color: white;
            text-align: center;
        }
        .sr-btn {
            background: #00ffaa; color: #0a1f16; border: none; padding: 8px 15px; border-radius: 5px;
            font-weight: bold; cursor: pointer; transition: 0.2s;
        }
        .sr-btn:disabled { background: #555; cursor: not-allowed; }
        .sr-btn.return { background: transparent; border: 1px solid #00ffaa; color: #00ffaa; }
        .sr-btn.return:hover { background: rgba(0, 255, 170, 0.1); }

        /* Flash Message (from SacliConnect.php) */
        .flash-message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            background: rgba(0, 255, 170, 0.95);
            color: #0a1f16;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: bold;
            z-index: 20000;
            box-shadow: 0 5px 20px rgba(0,0,0,0.4);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            pointer-events: none;
            border: 1px solid #fff;
        }
        .flash-message.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .flash-message.error { background: rgba(255, 85, 85, 0.95); color: white; }
    </style>
</head>
<body>
    <div class="submissions-container">
        <a href="SacliRoom_view.php?id=<?php echo $post_info['room_id']; ?>&page=classwork" class="back-link">&larr; Back to Classwork</a>
        <header class="submissions-header">
            <h1><?php echo htmlspecialchars($post_info['title']); ?></h1>
            <p><?php echo htmlspecialchars($post_info['room_name']); ?></p>
        </header>

        <div class="submissions-list">
            <?php
            if ($submissions_res->num_rows > 0):
                while($row = $submissions_res->fetch_assoc()):
                    $pic = !empty($row['profile_pic']) ? "uploads/".$row['profile_pic'] : "assets/images/3icons8-student-64.png";
            ?>
            <div class="submission-item" id="sub-row-<?php echo $row['submission_id']; ?>">
                <div class="sub-student-info">
                    <img src="<?php echo $pic; ?>" alt="Student">
                    <span><?php echo htmlspecialchars($row['student_name']); ?></span>
                </div>
                
                <div class="sub-status">
                    <?php if ($row['submission_id']): ?>
                        <?php if ($row['grade']): ?>
                            <span class="status-text status-graded">Graded</span>
                        <?php else: ?>
                            <span class="status-text status-turned-in">Turned In</span>
                            <span class="time"><?php echo date("M d, h:i A", strtotime($row['submitted_at'])); ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="status-text status-missing">Missing</span>
                    <?php endif; ?>
                </div>

                <div class="sub-file">
                    <?php if ($row['file_path']): ?>
                        <a href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank">View File</a>
                    <?php else: ?>
                        <span>--</span>
                    <?php endif; ?>
                </div>

                <div class="sub-grade">
                    <?php if ($row['submission_id']): ?>
                        <input type="text" id="grade-input-<?php echo $row['submission_id']; ?>" placeholder="-- / 100" value="<?php echo htmlspecialchars($row['grade'] ?? ''); ?>">
                        <button class="sr-btn return" onclick="returnGrade(<?php echo $row['submission_id']; ?>)">Return</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div style="text-align:center; padding: 40px; color: #aaa;">No students in this class.</div>
            <?php endif; $submissions_stmt->close(); ?>
        </div>
    </div>

    <script>
        // --- FLASH MESSAGE FUNCTION ---
        function showFlash(msg, type = 'success') {
            let flash = document.createElement('div');
            flash.className = 'flash-message ' + type;
            flash.innerText = msg;
            document.body.appendChild(flash);
            
            // Trigger reflow
            void flash.offsetWidth;
            
            flash.classList.add('show');
            
            setTimeout(() => {
                flash.classList.remove('show');
                setTimeout(() => flash.remove(), 500);
            }, 3000);
        }

        function returnGrade(submissionId) {
            const gradeInput = document.getElementById('grade-input-' + submissionId);
            const grade = gradeInput.value;
            const button = gradeInput.nextElementSibling;

            button.disabled = true;
            button.innerText = 'Returning...';

            let formData = new FormData();
            formData.append('action', 'grade_submission');
            formData.append('submission_id', submissionId);
            formData.append('grade', grade);

            fetch('handlers/sacli_room_handler.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                showFlash(data.message, data.status);
                if (data.status === 'success') {
                    // Update UI without reloading
                    const statusDiv = document.querySelector('#sub-row-' + submissionId + ' .sub-status .status-text');
                    if (statusDiv) {
                        statusDiv.className = 'status-text status-graded';
                        statusDiv.innerText = 'Graded';
                    }
                }
                button.disabled = false;
                button.innerText = 'Return';
            })
            .catch(err => {
                showFlash('An error occurred.', 'error');
                console.error(err);
                button.disabled = false;
                button.innerText = 'Return';
            });
        }
    </script>
</body>
</html>