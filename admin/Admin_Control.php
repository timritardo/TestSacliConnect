<?php
/**
 * Admin Control — SacliConnect style, admin interface.
 * Lahat ng binago dito ay makikita sa SacliConnect.php (naglo-load from same DB).
 */
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Admin lang — kung student, redirect sa login
if (empty($_SESSION['admin_username'])) {
    header("Location: ../SacliConnect_LOG_IN.php?show=admin");
    exit();
}


// ----- Ensure tables exist -----
$conn->query("CREATE TABLE IF NOT EXISTS sidebar_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    icon VARCHAR(255) DEFAULT '',
    sort_order INT DEFAULT 0
)");
$conn->query("CREATE TABLE IF NOT EXISTS subject_chats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    is_online TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0
)");

// ----- Process saves (same file = walang admin_actions.php) -----
$updated = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Save Sidebar Menu → makikita sa SacliConnect.php left sidebar
    if (isset($_POST['action']) && $_POST['action'] === 'save_sidebar') {
        $conn->query("DELETE FROM sidebar_menu");
        $stmt = $conn->prepare("INSERT INTO sidebar_menu (label, icon, sort_order) VALUES (?, ?, ?)");
        $order = 0;
        if (!empty($_POST['sidebar_label'])) {
            foreach ($_POST['sidebar_label'] as $i => $label) {
                $label = trim($label);
                if ($label === '') continue;
                $icon = isset($_POST['sidebar_icon'][$i]) ? trim($_POST['sidebar_icon'][$i]) : '';
                $order++;
                $stmt->bind_param("ssi", $label, $icon, $order);
                $stmt->execute();
            }
        }
        $stmt->close();
        $updated = true;
    }

    // Save Subject Chats → makikita sa SacliConnect.php right sidebar
    if (isset($_POST['action']) && $_POST['action'] === 'save_subjects') {
        $conn->query("DELETE FROM subject_chats");
        $stmt = $conn->prepare("INSERT INTO subject_chats (name, is_online, sort_order) VALUES (?, ?, ?)");
        $order = 0;
        if (!empty($_POST['subject_name'])) {
            foreach ($_POST['subject_name'] as $i => $name) {
                $name = trim($name);
                if ($name === '') continue;
                $online = isset($_POST['subject_online'][$i]) ? 1 : 0;
                $order++;
                $stmt->bind_param("sii", $name, $online, $order);
                $stmt->execute();
            }
        }
        $stmt->close();
        $updated = true;
    }

    if ($updated) {
        header("Location: Admin_Control.php?updated=1");
        exit();
    }
}

// ----- Load data (same tables na ginagamit ng SacliConnect.php) -----
$sidebarItems = [];
$res = $conn->query("SELECT * FROM sidebar_menu ORDER BY sort_order, id");
if ($res) while ($row = $res->fetch_assoc()) $sidebarItems[] = $row;
if (empty($sidebarItems)) {
    $conn->query("INSERT INTO sidebar_menu (label, icon, sort_order) VALUES
        ('Dashboard', '1icons8-dashboard-50.png', 1),
        ('Announcements', '2icons8-announcement-50.png', 2),
        ('Students', 'assets/images/3icons8-student-64.png', 3),
        ('Teachers', '4icons8-teacher-50.png', 4),
        ('Alumni', '5icons8-student-64.png', 5),
        ('Assignments', '5icons8-assignment-50.png', 6),
        ('Calendar', '6icons8-calendar-50.png', 7),
        ('Organizations', '7icons8-organization-64.png', 8),
        ('Settings', '8icons8-setting-50.png', 9)");
    $res = $conn->query("SELECT * FROM sidebar_menu ORDER BY sort_order, id");
    while ($row = $res->fetch_assoc()) $sidebarItems[] = $row;
}

$subjectChats = [];
$res = $conn->query("SELECT * FROM subject_chats ORDER BY sort_order, id");
if ($res) while ($row = $res->fetch_assoc()) $subjectChats[] = $row;
if (empty($subjectChats)) {
    $conn->query("INSERT INTO subject_chats (name, is_online, sort_order) VALUES
        ('Programming', 1, 1), ('Mathematics', 0, 2), ('Science', 1, 3), ('English', 0, 4)");
    $res = $conn->query("SELECT * FROM subject_chats ORDER BY sort_order, id");
    while ($row = $res->fetch_assoc()) $subjectChats[] = $row;
}

$showUpdated = isset($_GET['updated']) && $_GET['updated'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/1_SacliConnect.css">
    <title>Admin Control — Sacli Connect</title>
</head>
<body>

<!-- ================= Sidebar (SacliConnect style) ================= -->
<div class="sidebar">
    <img class="icon" src="76946050_2554845197961929_5561337140505214976_n-removebg-preview.png" alt="">
    <h2>SACLICONNECT</h2>
    <ul>
        <li><a href="SacliConnect.php" class="sidebar-link">Dashboard</a></li>
        <li><a href="SacliChat_Full.php" class="sidebar-link">Messenger open</a></li>
        <li><img class="icon2" src="2icons8-announcement-50.png" alt="">Announcements</li>
        <li><img class="icon2" src="assets/images/3icons8-student-64.png" alt="">Students</li>
        <li><img class="icon2" src="assets/images/4icons8-teacher-50.png" alt="">Teachers</li>
        <li><img class="icon2" src="5icons8-assignment-50.png" alt="">Assignments</li>
        <li><img class="icon2" src="6icons8-calendar-50.png" alt="">Calendar</li>
        <li><img class="icon2" src="7icons8-organization-64.png" alt="">Organizations</li>
        <li><img class="icon2" src="8icons8-setting-50.png" alt="">Settings</li>
    </ul>
</div>

<!-- ================= Main (admin interface — iba sa SacliConnect) ================= -->
<div class="main">
    <h2 style="color:#00ffaa; margin-bottom:20px;">Admin Control — Ang mga binago dito ay lalabas sa SacliConnect</h2>

    <?php if ($showUpdated): ?>
    <div class="admin-success">✓ Na-save. Makikita na ang changes sa SacliConnect.php</div>
    <?php endif; ?>

    <!-- Sidebar Menu (left menu sa SacliConnect) -->
    <div class="admin-section">
        <h3>Sidebar Menu (kaliwa sa SacliConnect)</h3>
        <form method="POST" action="Admin_Control.php">
            <input type="hidden" name="action" value="save_sidebar">
            <div id="sidebar-rows">
                <?php foreach ($sidebarItems as $item): ?>
                <div class="admin-form-row">
                    <input type="text" name="sidebar_label[]" value="<?php echo htmlspecialchars($item['label']); ?>" placeholder="Menu label">
                    <input type="text" name="sidebar_icon[]" value="<?php echo htmlspecialchars($item['icon']); ?>" placeholder="Icon filename">
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:15px; display:flex; gap:10px;">
                <button type="button" class="admin-btn admin-btn-add" onclick="addSidebarRow()">+ Add item</button>
                <button type="submit" class="admin-btn admin-btn-save">Save Sidebar</button>
            </div>
        </form>
    </div>

    <!-- Subject Group Chats (kanan sa SacliConnect) -->
    <div class="admin-section">
        <h3>Subject Group Chats (kanan sa SacliConnect)</h3>
        <form method="POST" action="Admin_Control.php">
            <input type="hidden" name="action" value="save_subjects">
            <div id="subject-rows">
                <?php foreach ($subjectChats as $s): ?>
                <div class="admin-form-row">
                    <input type="text" name="subject_name[]" value="<?php echo htmlspecialchars($s['name']); ?>" placeholder="Subject name">
                    <label style="display:flex; align-items:center; gap:8px; color:#66ffd9;">
                        <input type="checkbox" name="subject_online[]" value="1" <?php echo $s['is_online'] ? 'checked' : ''; ?>>
                        Online
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:15px; display:flex; gap:10px;">
                <button type="button" class="admin-btn admin-btn-add" onclick="addSubjectRow()">+ Add subject</button>
                <button type="submit" class="admin-btn admin-btn-save">Save Subjects</button>
            </div>
        </form>
    </div>

    <!-- Recent Announcements (delete — same posts sa SacliConnect) -->
    <div class="admin-section">
        <h3>Recent Announcements (delete)</h3>
        <?php
        $posts = $conn->query("SELECT * FROM posts ORDER BY timestamp DESC LIMIT 20");
        if ($posts && $posts->num_rows > 0):
            while ($post = $posts->fetch_assoc()):
        ?>
        <div class="admin-list-item">
            <div style="flex:1;">
                <strong><?php echo htmlspecialchars($post['student_name']); ?></strong>
                <span class="time"><?php echo date("M d, Y H:i", strtotime($post['timestamp'])); ?></span>
                <p style="margin:8px 0 0; color:#ccffeb;"><?php echo htmlspecialchars(mb_substr($post['content'], 0, 80)); ?><?php echo mb_strlen($post['content']) > 80 ? '…' : ''; ?></p>
            </div>
            <form method="POST" action="../handlers/delete_post.php" style="margin:0;">
                <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
                <button type="submit" class="admin-btn admin-btn-delete">Delete</button>
            </form>
        </div>
        <?php
            endwhile;
        else:
            echo '<p style="color:#66ffd9;">Walang posts pa.</p>';
        endif;
        ?>
    </div>
</div>

<!-- ================= Right Sidebar (SacliConnect style) ================= -->
<div class="right-sidebar">
    <div style="display:flex; justify-content:flex-end; margin-bottom:20px;">
        <button class="Btn1" onclick="showLogoutModal(); return false;">
            <div class="sign1">
                <svg viewBox="0 0 512 512">
                    <path d="M377.9 105.9L500.7 228.7c7.2 7.2 11.3 17.1 11.3 27.3s-4.1 20.1-11.3 27.3L377.9 406.1c-6.4 6.4-15 9.9-24 9.9c-18.7 0-33.9-15.2-33.9-33.9l0-62.1-128 0c-17.7 0-32-14.3-32-32l0-64c0-17.7 14.3-32 32-32l128 0 0-62.1c0-18.7 15.2-33.9 33.9-33.9c9 0 17.6 3.6 24 9.9zM160 96L96 96c-17.7 0-32 14.3-32 32l0 256c0 17.7 14.3 32 32 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32l-64 0c-53 0-96-43-96-96L0 128C0 75 43 32 96 32l64 0c17.7 0 32 14.3 32 32s-14.3 32-32 32z"></path>
                </svg>
            </div>
            <div class="text">Logout</div>
        </button>
    </div>
    <h3>Quick Links</h3>
    <div class="gc" onclick="location.href='SacliConnect.php'" style="cursor:pointer;">
        <span>Back to SacliConnect</span>
        <div class="status online"></div>
    </div>
</div>

<!-- Logout Modal -->
<div class="modal" id="logoutModal">
    <div class="modal-content logout-modal-content">
        <h3>Confirm Logout</h3>
        <p>Are you sure you want to logout?</p>
        <div class="logout-buttons">
            <button class="logout-confirm-btn" onclick="confirmLogout()">Yes, Logout</button>
            <button class="logout-cancel-btn" onclick="closeLogoutModal()">Cancel</button>
        </div>
    </div>
</div>

<script>
function showLogoutModal() { document.getElementById("logoutModal").style.display = "flex"; }
function closeLogoutModal() { document.getElementById("logoutModal").style.display = "none"; }
function confirmLogout() { window.location.href = "Logout.php"; }
window.onclick = function(e) {
    if (e.target === document.getElementById("logoutModal")) closeLogoutModal();
};
function addSidebarRow() {
    var d = document.getElementById("sidebar-rows");
    d.appendChild(document.createElement("div")).className = "admin-form-row";
    d.lastElementChild.innerHTML = '<input type="text" name="sidebar_label[]" placeholder="Menu label"> <input type="text" name="sidebar_icon[]" placeholder="Icon filename">';
}
function addSubjectRow() {
    var d = document.getElementById("subject-rows");
    d.appendChild(document.createElement("div")).className = "admin-form-row";
    d.lastElementChild.innerHTML = '<input type="text" name="subject_name[]" placeholder="Subject name"> <label style="display:flex;align-items:center;gap:8px;color:#66ffd9;"><input type="checkbox" name="subject_online[]" value="1"> Online</label>';
}
</script>
</body>
</html>
