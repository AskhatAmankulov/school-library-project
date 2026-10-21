<?php
session_start();
include 'auth_check.php';
include 'config.php';

// Only admin/librarian can access this
require_staff(true);

$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($searchTerm) < 1) {
    echo json_encode([]);
    exit();
}

// Search for books - match by title or author
$searchTerm = '%' . $searchTerm . '%';

if ($db_error || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$stmt = $conn->prepare("SELECT id, title, author, available_quantity FROM books WHERE available_quantity > 0 AND (title LIKE ? OR author LIKE ?) ORDER BY title LIMIT 20");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit();
}

$stmt->bind_param("ss", $searchTerm, $searchTerm);
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
