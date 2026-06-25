<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if(empty($_SESSION['admin_username'])) die("Not authorized");

if($_SERVER['REQUEST_METHOD']=='POST'){
    $id = intval($_POST['id']);
    $res = $conn->query("SELECT media FROM posts WHERE id=$id");
    if($res->num_rows > 0){
        $row = $res->fetch_assoc();
        if($row['media']) unlink($row['media']); // delete file
        $conn->query("DELETE FROM posts WHERE id=$id");
    }
    header("Location: SACLICONNECT2.php");
}
?>
