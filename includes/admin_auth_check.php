<?php
/**
 * includes/admin_auth_check.php
 * Drop this at the top of any admin-only page.
 *
 * Usage:
 *   require_once 'includes/admin_auth_check.php';                // from root
 *   require_once __DIR__ . '/../includes/admin_auth_check.php';  // from subdirectory
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Must be logged in AND be an admin
if (!isset($_SESSION['admin_username']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: " . (defined('ROOT_URL') ? ROOT_URL : '') . "SacliConnect_LOG_IN.php?show=admin");
    exit();
}
