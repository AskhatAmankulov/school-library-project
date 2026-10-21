<?php
session_start();
include 'auth_check.php';
require_staff(true);
include 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['member_id']) || empty($_GET['member_id'])) {
    echo json_encode(['error' => 'Member ID is required']);
    exit();
}

$member_id = (int)$_GET['member_id'];
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$last_read = isset($_GET['last_read']) && $_GET['last_read'] == '1';

if ($db_error || !$conn) {
    echo json_encode(['error' => 'Database connection error']);
    exit();
}

// Fetch borrowing history for the member
$sql = "SELECT 
            br.id,
            br.borrow_date,
            br.due_date,
            br.return_date,
            br.status,
            b.title,
            b.author,
            b.isbn,
            b.category
        FROM borrowings br
        JOIN books b ON br.book_id = b.id
        WHERE br.member_id = ?";

// Add status filter if provided
if ($status_filter === 'active') {
    $sql .= " AND br.status = 'active' ORDER BY br.borrow_date DESC";
} elseif ($last_read) {
    // Get only last returned book if requested
    $sql .= " AND br.status = 'returned' ORDER BY br.return_date DESC LIMIT 1";
} else {
    $sql .= " ORDER BY br.borrow_date DESC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'author' => $row['author'],
        'isbn' => $row['isbn'],
        'category' => $row['category'],
        'borrow_date' => $row['borrow_date'],
        'due_date' => $row['due_date'],
        'return_date' => $row['return_date'],
        'status' => $row['status']
    ];
}

$stmt->close();

echo json_encode($history);
?>
