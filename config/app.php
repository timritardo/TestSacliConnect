<?php
/**
 * config/app.php
 * Global application constants and settings.
 * Include alongside database.php wherever app-wide config is needed.
 */

// ─── App Identity ─────────────────────────────────────────────────────────────
define('APP_NAME',    'SacliConnect');
define('APP_SCHOOL',  'St. Anne College Lucena, Inc.');
define('APP_VERSION', '1.0.0');

// ─── Base Paths ───────────────────────────────────────────────────────────────
// Use these instead of hardcoding path strings across files.
define('ROOT_PATH',    dirname(__DIR__));           // /Capstone_Project_2026_Test
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH',  ROOT_PATH . '/assets');

// ─── Upload Directories (relative to web root, for <img src="..."> tags) ─────
define('UPLOAD_URL',   'uploads/');
define('ASSETS_URL',   'assets/');

// ─── Session / Security ───────────────────────────────────────────────────────
define('SESSION_TIMEOUT_SECONDS', 60);   // Seconds before a user is marked offline
define('OTP_EXPIRY_MINUTES',       10);  // OTP validity window

// ─── PHPMailer (uses Composer vendor/ copy) ───────────────────────────────────
// Loaded via vendor/autoload.php — do NOT require PHPMailer/ manual copies.
// Usage:  require_once ROOT_PATH . '/vendor/autoload.php';
//         use PHPMailer\PHPMailer\PHPMailer;
