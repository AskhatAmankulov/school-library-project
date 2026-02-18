<?php
// Utility functions for the library system

// Sanitize user input
function sanitize($data) {
    return htmlspecialchars(trim($data));
}

// Display alert messages
function showAlert($message, $type = 'success') {
    echo '<div class="alert alert-' . $type . '">' . $message . '</div>';
}

// Redirect to a page
function redirect($page) {
    header("Location: " . $page);
    exit();
}

// Get total books count
function getTotalBooks($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM books");
    return $result->fetch_assoc()['count'];
}

// Get total members count
function getTotalMembers($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM members");
    return $result->fetch_assoc()['count'];
}

// Get active borrowings count
function getActiveBorrowings($conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE status='active'");
    return $result->fetch_assoc()['count'];
}

// Get overdue books
function getOverdueBooks($conn) {
    $result = $conn->query("
        SELECT COUNT(*) as count FROM borrowings 
        WHERE status='active' AND due_date < CURDATE()
    ");
    return $result->fetch_assoc()['count'];
}
?>
