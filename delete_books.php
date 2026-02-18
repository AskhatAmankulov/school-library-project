<?php
session_start();
include 'auth_check.php';
include 'config.php';

// Only admin can delete books
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access. Admin privileges required.']);
    exit();
}

// Verify database connection
if ($db_error || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$book_ids = isset($data['book_ids']) ? $data['book_ids'] : [];

// Validate input
if (empty($book_ids) || !is_array($book_ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid book IDs. Please provide an array of book IDs.']);
    exit();
}

// Sanitize and validate all IDs are integers
$book_ids = array_filter(array_map('intval', $book_ids), function($id) {
    return $id > 0;
});

if (empty($book_ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'No valid book IDs provided.']);
    exit();
}

// Prepare placeholders for IN clause
$placeholders = implode(',', array_fill(0, count($book_ids), '?'));
$types = str_repeat('i', count($book_ids));

// Delete the books
$stmt = $conn->prepare("DELETE FROM books WHERE id IN ($placeholders)");
if ($stmt) {
    // Bind parameters dynamically
    $stmt->bind_param($types, ...$book_ids);
    
    if ($stmt->execute()) {
        $deleted_count = $stmt->affected_rows;
        $stmt->close();
        
        if ($deleted_count > 0) {
            echo json_encode([
                'success' => true, 
                'message' => "Successfully deleted $deleted_count book" . ($deleted_count === 1 ? '' : 's') . '!',
                'deleted_count' => $deleted_count
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No books found with the provided IDs.']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete books: ' . $conn->error]);
        $stmt->close();
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
?>
