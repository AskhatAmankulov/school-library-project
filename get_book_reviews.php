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

// Fetch reviews for the book
$sql = "SELECT 
            r.id,
            r.rating,
            r.comment,
            r.created_at,
            COALESCE(m.name, r.reviewer_name) AS name,
            r.reviewer_role
        FROM book_reviews r
        LEFT JOIN members m ON r.member_id = m.id
        WHERE r.book_id = ?
        ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Fallback for environments without reviewer_name/reviewer_role columns
    $fallbackSql = "SELECT r.id, r.rating, r.comment, r.created_at, m.name FROM book_reviews r JOIN members m ON r.member_id = m.id WHERE r.book_id = ? ORDER BY r.created_at DESC";
    $stmt = $conn->prepare($fallbackSql);
    if (!$stmt) {
        echo json_encode(['error' => 'Failed to prepare reviews query']);
        exit();
    }
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
}

$reviews = [];
while ($row = $result->fetch_assoc()) {
    $display_name = $row['name'];
    if (!$display_name && $row['reviewer_role']) {
        $display_name = ucfirst($row['reviewer_role']);
    }
    $reviews[] = [
        'id' => $row['id'],
        'name' => $display_name,
        'rating' => $row['rating'],
        'comment' => $row['comment'],
        'created_at' => $row['created_at']
    ];
}

$stmt->close();

echo json_encode($reviews);
?>
