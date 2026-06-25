<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if (empty($_SESSION['admin_username'])) {
    header("Location: ../SacliConnect_LOG_IN.php?show=admin");
    exit();
}

// Handle Individual ID Registration by Admin
$status_msg = "";
$status_type = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_id') {
    $new_id = trim($_POST['student_id']);

    if (!empty($new_id)) {
        $chk = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
        $chk->bind_param("s", $new_id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $status_msg = "Error: Student ID already exists in the system.";
            $status_type = "error";
        } else {
            $stmt_add = $conn->prepare("INSERT INTO students (student_id, student_name, password) VALUES (?, 'Unclaimed Account', NULL)");
            $stmt_add->bind_param("s", $new_id);
            if ($stmt_add->execute()) {
                $status_msg = "ID Registered Successfully! Student can now sign up.";
                $status_type = "success";
            }
            $stmt_add->close();
        }
        $chk->close();
    }
}

$search_query = $_GET['search'] ?? '';
$search_term = "%" . $conn->real_escape_string($search_query) . "%";

/** 
 * I-fetch lamang ang mga IDs na na-import/pre-register ni admin na wala pang password (hindi pa claimed).
 */
$sql = "SELECT student_id, student_name, profile_pic, password FROM students WHERE (student_name LIKE ? OR student_id LIKE ?) ORDER BY student_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $search_term, $search_term);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Student ID List</title>
    <link rel="icon" href="assets/images/St.Anne_logo.png" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/1_SacliConnect.css"> <!-- Re-use main styling -->
    <style>
        body {
            background: radial-gradient(circle at center, #0f3526 0%, #05100c 100%);
            font-family: 'Segoe UI', sans-serif;
            color: #e4e6eb;
            padding: 20px;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 20px auto;
            background: rgba(20, 50, 40, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 255, 170, 0.2);
            border-radius: 20px;
            overflow: hidden; /* Ensure content stays within rounded corners */
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        h2 {
            color: #00ffaa;
            text-align: center;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 1px;
            text-shadow: 0 0 10px rgba(0, 255, 170, 0.5);
            border-bottom: 1px solid rgba(0, 255, 170, 0.3);
            padding-bottom: 10px;
        }
        .search-bar {
            margin-bottom: 20px;
        }
        .search-bar input {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(0, 255, 170, 0.3);
            color: #fff;
            border-radius: 10px;
            font-size: 16px;
            outline: none;
            box-sizing: border-box; /* Include padding in width */
            transition: 0.3s;
        }
        .search-bar input:focus {
            border-color: #00ffaa;
            box-shadow: 0 0 15px rgba(0, 255, 170, 0.2);
        }
        .student-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            max-height: 600px;
            overflow-y: auto;
            padding-right: 5px; /* For scrollbar space */
            scrollbar-width: thin; /* Firefox */
        }
        .student-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 255, 170, 0.1);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }
        .student-card:hover {
            background: rgba(0, 255, 170, 0.1);
            border-color: #00ffaa;
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 255, 170, 0.2);
        }
        .student-card .delete-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(255, 85, 85, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px; height: 24px;
            font-size: 14px; font-weight: bold; cursor: pointer;
        }
        .student-card img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #00ffaa;
            margin-bottom: 15px;
            box-shadow: 0 0 10px rgba(0, 255, 170, 0.3);
        }
        .student-card h4 {
            color: #fff;
            font-size: 16px;
            margin: 0 0 5px 0;
            font-weight: 600;
        }
        .student-card p {
            color: #b0fce0;
            font-size: 13px;
            margin: 0;
            line-height: 1.4;
            word-break: break-all; /* Ensure long IDs wrap */
        }
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: rgba(0, 255, 170, 0.1);
            color: #00ffaa;
            text-decoration: none;
            border: 1px solid #00ffaa;
            border-radius: 10px;
            transition: 0.3s;
            font-weight: bold;
            text-decoration: none;
        }
        .back-button:hover {
            background: #00ffaa;
            color: #0a1f16;
        }
        .register-box {
            background: rgba(0, 255, 170, 0.05);
            border: 1px dashed #00ffaa;
            padding: 20px;
            margin-top: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .register-box h3 {
            color: #00ffaa;
            margin-top: 0;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .status-alert {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
            font-size: 14px;
        }
        .status-alert.success { background: rgba(0, 255, 170, 0.2); color: #00ffaa; border: 1px solid #00ffaa; }
        .status-alert.error { background: rgba(255, 85, 85, 0.2); color: #ff5555; border: 1px solid #ff5555; }
        
        .reg-form { display: flex; gap: 10px; flex-wrap: wrap; }
        .reg-form input {
            flex: 1;
            min-width: 200px;
            padding: 10px;
            background: rgba(0,0,0,0.3);
            box-sizing: border-box;
            border: 1px solid rgba(0, 255, 170, 0.3);
            color: white;
            border-radius: 5px;
            outline: none;
        }
        .reg-form button {
            background: linear-gradient(45deg, #00ffaa, #00cc88);
            color: #0a1f16;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
            cursor: pointer;
        }
        .reg-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 255, 170, 0.4);
            filter: brightness(1.1);
        }

        /* Scrollbar Styling */
        .student-grid::-webkit-scrollbar {
            width: 8px;
        }
        .student-grid::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
        }
        .student-grid::-webkit-scrollbar-thumb {
            background: #00ffaa;
            border-radius: 10px;
            border: 2px solid rgba(0,0,0,0.2);
        }
        .student-grid::-webkit-scrollbar-thumb:hover {
            background: #00cc88;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
                margin: 10px auto;
            }
            h2 {
                font-size: 20px;
            }
            .student-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 10px;
            }
            .reg-form { flex-direction: column; }
            .reg-form input { min-width: unset; width: 100%; }
        }
        @media (max-width: 480px) {
            .student-grid {
                grid-template-columns: 1fr; /* Stack cards vertically */
            }
            .student-card img { width: 60px; height: 60px; }
            .student-card h4 { font-size: 14px; }
            .student-card p { font-size: 11px; }
        }
        .delete-id-btn {
            background: rgba(255, 85, 85, 0.15);
            color: #ff5555;
            border: 1px solid rgba(255, 85, 85, 0.3);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="SACLICONNECT2.php?page=students" class="back-button">&larr; Back to Student Management</a>
        <h2>Student ID List</h2>
        
        <?php if (!empty($status_msg)): ?>
            <div class="status-alert <?php echo $status_type; ?>"><?php echo $status_msg; ?></div>
        <?php endif; ?>

        <!-- Quick Register ID Form -->
        <div class="register-box">
            <h3>Register New Authorized ID</h3>
            <form method="POST" class="reg-form">
                <input type="hidden" name="action" value="register_id">
                <input type="text" name="student_id" placeholder="Enter Student ID (e.g. 2024-0001)" required>
                <button type="submit">Register ID</button>
            </form>
            <p style="font-size: 11px; color: #aaa; margin-top: 10px;">Note: IDs registered here will be allowed to sign up. The student will provide their own name and details during the signup process.</p>
        </div>

        <div class="search-bar" style="margin-bottom: 30px;">
            <input type="text" id="studentIdSearch" onkeyup="filterStudentCards()" placeholder="Search available/unclaimed IDs...">
        </div>

        <div class="student-grid" id="studentListGrid">
            <?php if (empty($students)): ?>
                <p style="grid-column: 1 / -1; text-align: center; color: #aaa;">No available IDs found. Admin must pre-register student IDs first to allow signup.</p>
            <?php else: ?>
                <?php foreach ($students as $student): 
                    $pic = !empty($student['profile_pic']) ? "uploads/" . $student['profile_pic'] : "assets/images/3icons8-student-64.png";
                    // Add a hidden input for student_id to easily pass to JS
                ?>
                    <div class="student-card">
                        <button class="delete-btn" onclick="deleteStudentId('<?php echo htmlspecialchars($student['student_id']); ?>', '<?php echo htmlspecialchars($student['student_name']); ?>')">×</button>
                        <img src="<?php echo $pic; ?>" alt="Profile Picture">
                        <h4><?php echo htmlspecialchars($student['student_name']); ?></h4>
                        <p><?php echo htmlspecialchars($student['student_id']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterStudentCards() {
            const input = document.getElementById('studentIdSearch').value.toLowerCase();
            const cards = document.querySelectorAll('.student-card');
            cards.forEach(card => {
                const name = card.querySelector('h4').innerText.toLowerCase();
                const id = card.querySelector('p').innerText.toLowerCase();
                if (name.includes(input) || id.includes(input)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function deleteStudentId(studentId, studentName, isClaimed) {
            let confirmationMessage = `Are you sure you want to delete the ID "${studentId}" for ${studentName}?`;
            if (isClaimed) {
                confirmationMessage += `\n\nWARNING: This student has an active account. Deleting this ID will PERMANENTLY DELETE ALL their data, posts, and messages. This action cannot be undone.`;
            } else {
                confirmationMessage += ` This will prevent them from signing up with this ID.`;
            }

            if (confirm(confirmationMessage)) {
                let formData = new FormData();
                formData.append('action', 'delete_student_id');
                formData.append('student_id', studentId);

                fetch('admin_delete_student_id.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert(data.message);
                        location.reload(); // Reload to update the list
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing your request.');
                });
            }
        }
    </script>
</body>
</html>