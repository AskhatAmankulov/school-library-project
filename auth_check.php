<?php
// Buffer the whole page so it can still redirect after the header is printed.
// (PHP's default 4 KB buffer is not enough: the header alone is bigger.)
ob_start();
include_once __DIR__ . '/includes/input.php';
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Not logged in (or logged out after an hour of inactivity):
// pages go to the login screen, background requests from a page get a JSON 401
// so the page can say what happened instead of breaking.
if (!function_exists('send_to_login')) {
    function send_to_login($expired) {
        $is_fetch = ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'empty'
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
        if ($is_fetch) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'You were logged out. Log in again to continue.', 'login' => true]);
        } else {
            header('Location: login.php' . ($expired ? '?expired=1' : ''));
        }
        exit();
    }
}

if (!isset($_SESSION['user_id'])) {
    send_to_login(false);
}

$timeout = 3600; // log out after 1 hour without activity
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $timeout)) {
    session_unset();
    session_destroy();
    send_to_login(true);
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
