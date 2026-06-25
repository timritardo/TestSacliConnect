<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) {
    http_response_code(500);
    die("Connection failed");
}
if (!isset($_SESSION['student_id'])) {
    http_response_code(403);
    die("Not logged in");
}

$my_id = $_SESSION['student_id'];
$filter = $_GET['filter'] ?? 'active';
$search = $_GET['search'] ?? '';

$params = [];
$types = '';

// Base queries for students and teachers
$student_query = "SELECT student_id as id, student_name as name, profile_pic, is_alumni, is_online, 'student' as base_type FROM students WHERE student_id != ?";
$params[] = $my_id;
$types .= 's';

$teacher_query = "SELECT CONCAT('T-', id) as id, name, profile_pic, 0 as is_alumni, is_online, 'teacher' as base_type FROM teachers WHERE CONCAT('T-', id) != ?";
$params[] = $my_id;
$types .= 's';

// Add search filter
if (!empty($search)) {
    $student_query .= " AND student_name LIKE ?";
    $teacher_query .= " AND name LIKE ?";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

// Combine queries
$sql = "($student_query) UNION ALL ($teacher_query)";

// Add filter conditions in a wrapper query
$final_sql = "SELECT * FROM ($sql) as users";
$where_clauses = [];

switch ($filter) {
    case 'active':
        // By not adding a WHERE clause here, we show all users.
        // The ORDER BY clause below will handle putting online users at the top.
        break;
    case 'student':
        $where_clauses[] = "(base_type = 'student')"; // is_alumni is handled in the label
        break;
    case 'teacher':
        $where_clauses[] = "base_type = 'teacher'";
        break;
}

if (!empty($where_clauses)) {
    $final_sql .= " WHERE " . implode(' AND ', $where_clauses);
}

$final_sql .= " ORDER BY is_online DESC, name ASC";

$stmt = $conn->prepare($final_sql);
if ($stmt) {
    if(!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $message = 'No users found.';
        if ($filter === 'active' && empty($search)) {
            $message = 'No one is Online right now.';
        }
        echo '<div style="text-align:center; color:#888; padding: 20px;">'.$message.'</div>';
    } else {
        while ($user = $result->fetch_assoc()) {
            $pic = !empty($user['profile_pic']) ? "uploads/".$user['profile_pic'] : ($user['base_type'] === 'teacher' ? "4icons8-teacher-50.png" : "assets/images/3icons8-student-64.png");
            $label = ($user['base_type'] === 'teacher') ? ' <small style="color:#00ffaa; font-size:11px;">(Teacher)</small>' : ($user['is_alumni'] ? ' <small style="color:#ffd700; font-size:11px;">(Alumni)</small>' : '');
            $online_indicator = $user['is_online'] ? '<div class="status online"></div>' : '<div class="status"></div>';
            $type_class = 'type-' . ($user['base_type'] === 'teacher' ? 'teacher' : ($user['is_alumni'] ? 'alumni' : 'student'));
            $name_escaped = htmlspecialchars($user['name'], ENT_QUOTES);
            echo "<div class=\"gc student-item {$type_class}\" id=\"student-row-{$user['id']}\" onclick=\"openChat('{$user['id']}', '{$name_escaped}', '{$pic}')\" style=\"cursor:pointer;\"><img src=\"{$pic}\"><span class=\"student-name\">".htmlspecialchars($user['name'])."{$label}</span>{$online_indicator}</div>";
        }
    }
    $stmt->close();
}

$conn->close();
?>