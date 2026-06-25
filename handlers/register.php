<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - SacliConnect</title>
    <link rel="stylesheet" href="assets/css/1_SacliConnect.css"> <!-- Reusing your UI -->
    <style>
        body { display: flex; justify-content: center; align-items: center; height: 100vh; background: #0a1f16; }
        .reg-box { background: #1a3d2f; padding: 30px; border-radius: 15px; border: 1px solid #00ffaa; width: 350px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border-radius: 5px; border: 1px solid #00ffaa; background: #000; color: #fff; }
        button { width: 100%; padding: 10px; background: #00ffaa; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="reg-box">
        <h2 style="color: #00ffaa; text-align: center;">Registration</h2>
        <form action="send_otp.php" method="POST">
            <label>Student ID</label>
            <input type="text" name="student_id" required placeholder="e.g. 2024-0001">

            <label>Full Name</label>
            <input type="text" name="name" required placeholder="Juan Dela Cruz">
            
            <label>Email</label>
            <input type="email" name="email" required placeholder="example@gmail.com">
            
            <p style="color: #888; font-size: 11px; margin-bottom: 15px;">Note: Your Student ID will serve as your initial password.</p>
            <button type="submit">Register & Send OTP</button>
        </form>
        <?php if(isset($_GET['error'])) echo "<p style='color:red; font-size:12px;'>".$_GET['error']."</p>"; ?>
    </div>
</body>
</html>