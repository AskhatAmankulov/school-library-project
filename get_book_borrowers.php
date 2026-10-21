<?php
session_start();
include 'auth_check.php';
require_staff(true);
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

// Fetch current borrowers for the book
$sql = "SELECT 
            m.id,
            m.name,
            m.class,
            br.borrow_date,
            br.due_date
        FROM borrowings br
        JOIN members m ON br.member_id = m.id
        WHERE br.book_id = ? AND br.status = 'active'
        ORDER BY br.borrow_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

$borrowers = [];
while ($row = $result->fetch_assoc()) {
    $borrowers[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'class' => $row['class'],
        'borrow_date' => $row['borrow_date'],
        'due_date' => $row['due_date']
    ];
}

$stmt->close();

echo json_encode($borrowers);
?>
