<?php
session_start();
include 'auth_check.php';
include 'config.php';

// Only admin can delete books
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
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
$book_id = isset($data['book_id']) ? (int)$data['book_id'] : 0;

if (!$book_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid book ID']);
    exit();
}

// Delete the book
$stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $book_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Book deleted successfully!']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete book: ' . $conn->error]);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $conn->error]);
}
?>
