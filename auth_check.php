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
<?php
// ---------- Role helpers ----------
// admin     = everything
// librarian = books, borrowings, members (but no deleting)
// user      = student: browse books, write reviews, own profile
if (!function_exists('is_admin')) {
    function current_role() {
        return $_SESSION['role'] ?? 'user';
    }

    function is_admin() {
        return current_role() === 'admin';
    }

    function is_staff() {
        return in_array(current_role(), ['admin', 'librarian'], true);
    }

    // Stop the request if the role check fails.
    // $json = true for AJAX endpoints, false for normal pages.
    function deny_access($json) {
        http_response_code(403);
        if ($json) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'You do not have permission to do this.']);
        } else {
            $_SESSION['error_message'] = 'You do not have permission to open that page.';
            header('Location: index.php');
        }
        exit();
    }

    function require_staff($json = false) {
        if (!is_staff()) {
            deny_access($json);
        }
    }

    function require_admin($json = false) {
        if (!is_admin()) {
            deny_access($json);
        }
    }
}
?>
