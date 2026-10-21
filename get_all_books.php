<?php
session_start();
include 'auth_check.php';
include 'config.php';

// Only admin/librarian can access this
require_staff(true);

if ($db_error || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get all available books
$stmt = $conn->prepare("SELECT id, title, author, available_quantity FROM books WHERE available_quantity > 0 ORDER BY title");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit();
}

$stmt->execute();
$result = $stmt->get_result();

$books = [];
while ($row = $result->fetch_assoc()) {
    $books[] = $row;
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode($books);
?>
