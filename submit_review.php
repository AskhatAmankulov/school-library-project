<?php
session_start();
include 'auth_check.php';
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

if (!isset($_POST['book_id']) || !isset($_POST['rating'])) {
    echo json_encode(['error' => 'Book ID and rating are required']);
    exit();
}

$book_id = (int)$_POST['book_id'];
$rating = (int)$_POST['rating'];
// Only staff can post a review on behalf of a chosen member;
// a student's review is always saved under their own account.
$member_id = (is_staff() && !empty($_POST['member_id'])) ? (int)$_POST['member_id'] : null;
$comment = trim($_POST['comment'] ?? '');

// Verify book exists
$verify_book = $conn->query("SELECT id FROM books WHERE id = $book_id");
if (!$verify_book || $verify_book->num_rows == 0) {
    echo json_encode(['error' => 'Book not found (ID: ' . $book_id . ')']);
    exit();
}

// Determine reviewer identity automatically based on session role
$reviewer_role = $_SESSION['role'] ?? 'user';
$reviewer_name = $_SESSION['username'] ?? null;

if ($member_id) {
    // If a member was explicitly selected, verify
    $verify_member = $conn->query("SELECT id FROM members WHERE id = $member_id");
    if (!$verify_member || $verify_member->num_rows == 0) {
        echo json_encode(['error' => 'Member not found (ID: ' . $member_id . ')']);
        exit();
    }
} else {
    // No member selected; auto-assign based on role
    if ($reviewer_role === 'user') {
        // Map session user_id to a member_id (demo users use members ids)
        $member_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        if (!$member_id) {
            echo json_encode(['error' => 'Unable to resolve member for user session']);
            exit();
        }
    } else {
        // Admin or librarian: store review under role/name without a member link
        $member_id = null;
        if (!$reviewer_name) { $reviewer_name = ucfirst($reviewer_role); }
    }
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['error' => 'Rating must be between 1 and 5']);
    exit();
}

if ($db_error || !$conn) {
    echo json_encode(['error' => 'Database connection error']);
    exit();
}

// Save review. If member_id is present, upsert by (book_id, member_id). Otherwise, insert with reviewer_name/role.
if ($member_id) {
    // Upsert by book/member
    $check_sql = "SELECT id FROM book_reviews WHERE book_id = ? AND member_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $book_id, $member_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $sql = "UPDATE book_reviews SET rating = ?, comment = ? WHERE book_id = ? AND member_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isii", $rating, $comment, $book_id, $member_id);
    } else {
        $sql = "INSERT INTO book_reviews (book_id, member_id, rating, comment) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: Could not prepare statement']);
            exit();
        }
        $stmt->bind_param("iiis", $book_id, $member_id, $rating, $comment);
    }
} else {
    // Admin/librarian review
    $sql = "INSERT INTO book_reviews (book_id, member_id, rating, comment, reviewer_name, reviewer_role) VALUES (?, NULL, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("iisss", $book_id, $rating, $comment, $reviewer_name, $reviewer_role);
    } else {
        // Fallback when reviewer columns don't exist yet
        $sql = "INSERT INTO book_reviews (book_id, member_id, rating, comment) VALUES (?, NULL, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: Could not prepare statement']);
            exit();
        }
        $stmt->bind_param("iis", $book_id, $rating, $comment);
    }
    // Create a dummy check_stmt for resource cleanup symmetry
    $check_stmt = (object) ['close' => function(){}];
}

if ($stmt->execute()) {
    $stmt->close();
    if (is_object($check_stmt)) { $check_stmt->close(); }
    
    // Return success with review count
    $count_result = $conn->query("SELECT COUNT(*) as count FROM book_reviews WHERE book_id = $book_id");
    $count = $count_result->fetch_assoc()['count'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully!',
        'review_count' => $count
    ]);
} else {
    $stmt->close();
    if (is_object($check_stmt)) { $check_stmt->close(); }
    echo json_encode(['error' => 'Failed to save review: ' . $conn->error]);
}
?>
