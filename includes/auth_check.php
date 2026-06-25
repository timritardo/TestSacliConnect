<?php
/**
 * includes/auth_check.php
 * Drop this at the top of any page that requires a logged-in user.
 *
 * Usage:
 *   require_once 'includes/auth_check.php';           // from root-level files
 *   require_once __DIR__ . '/../includes/auth_check.php'; // from subdirectory files
 *
 * After this runs, the following session variables are guaranteed to exist:
 *   $_SESSION['student_id']   — user's ID (or 'Admin' for admins)
 *   $_SESSION['student_name'] — display name
 *   $_SESSION['user_type']    — 'student' | 'teacher' | 'admin'
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin bridge: normalise session when admin is viewing as a user
if (isset($_SESSION['admin_username']) && (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin')) {
    $_SESSION['student_name'] = 'Admin';
    $_SESSION['student_id']   = 'Admin';
    $_SESSION['user_type']    = 'admin';
}

// Redirect to login if no authenticated session exists
if (!isset($_SESSION['student_name'])) {
    header("Location: " . (defined('ROOT_URL') ? ROOT_URL : '') . "SacliConnect_LOG_IN.php");
    exit();
}
