<?php
session_start();
include 'auth_check.php';
include 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['book_id']) || empty($_GET['book_id'])) {
    echo json_encode(['error' => 'Book ID is required']);
    exit();
}

$book_id = (int)$_GET['book_id'];

if ($db_error || !$conn) {
    echo json_encode(['error' => 'Database connection error']);
    exit();
}

// Fetch book details
$sql = "SELECT id, title, author, isbn, category, quantity, available_quantity, shelf_number, shelf_row, cover_url FROM books WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    // Fallback for environments without new columns
    $sql = "SELECT id, title, author, isbn, category, quantity, available_quantity FROM books WHERE id = ?";
    $stmt = $conn->prepare($sql);
}

if (!$stmt) {
    echo json_encode(['error' => 'Failed to prepare query']);
    exit();
}

$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Book not found']);
    exit();
}

$book = $result->fetch_assoc();
$stmt->close();

echo json_encode($book);
?>