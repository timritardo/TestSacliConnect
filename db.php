<?php
/**
 * db.php — Backward compatibility shim.
 * This file is kept so any legacy code using require 'db.php' still works.
 * New code should use: require_once 'config/database.php';
 */
require_once __DIR__ . '/config/database.php';