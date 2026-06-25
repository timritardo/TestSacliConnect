<?php
session_start();
require_once __DIR__ . '/../config/database.php';
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if (!isset($_SESSION['student_name']) || $_SESSION['student_name'] !== 'Admin') {
    header("Location: ../SacliConnect.php");
    exit();
}

$done = false;

// Save Sidebar Menu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_sidebar') {
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
    $done = true;
}

// Save Subject Chats
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_subjects') {
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
    $done = true;
}

// Delete one sidebar item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_sidebar' && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    $conn->query("DELETE FROM sidebar_menu WHERE id = $id");
    $done = true;
}

// Delete one subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_subject' && isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    $conn->query("DELETE FROM subject_chats WHERE id = $id");
    $done = true;
}

if ($done) {
    header("Location: Admin_panel.php?updated=1");
    exit();
}

$conn->close();
header("Location: Admin_panel.php");
exit();
