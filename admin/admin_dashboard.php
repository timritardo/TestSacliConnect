<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../SacliConnect_LOG_IN.php?show=admin");
    exit();
}
?>

<h1>Welcome Admin: <?php echo $_SESSION['admin_username']; ?></h1>
<a href="admin_logout.php">Logout</a>
