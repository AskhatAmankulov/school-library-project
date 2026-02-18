<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check session timeout (optional - 1 hour)
$timeout = 3600; // 1 hour in seconds
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $timeout)) {
    session_destroy();
    header("Location: login.php?expired=1");
    exit();
}

// Update login time
$_SESSION['login_time'] = time();
?>
