<?php
session_start();
include 'auth_check.php';
include 'config.php';

header('Content-Type: application/json');

function reply($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(['error' => 'Use POST to send a review.'], 405);
}
if ($db_error || !$conn) {
    reply(['error' => 'Cannot connect to the database.'], 500);
}

$book_id = (int)($_POST['book_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if ($rating < 1 || $rating > 5) {
    reply(['error' => 'Pick a rating from 1 to 5 stars.'], 400);
}
if (mb_strlen($comment) > 2000) {
    reply(['error' => 'The review is too long (2000 characters at most).'], 400);
}

$stmt = $conn->prepare("SELECT id FROM books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_row()) {
    reply(['error' => 'This book no longer exists.'], 404);
}
$stmt->close();

// Everyone reviews as themselves (every account is linked to a member).
// Staff may also record a review on behalf of a student by passing member_id.
$member_id = (int)($_SESSION['user_id'] ?? 0);
if (is_staff() && !empty($_POST['member_id'])) {
    $member_id = (int)$_POST['member_id'];
}
$stmt = $conn->prepare("SELECT id FROM members WHERE id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_row()) {
    reply(['error' => 'Your account is not linked to a member. Log out and in again.'], 400);
}
$stmt->close();

$reviewer_name = $_SESSION['username'] ?? null;
$reviewer_role = current_role();

try {
    // One review per person per book: posting again updates it
    $stmt = $conn->prepare("SELECT id FROM book_reviews WHERE book_id = ? AND member_id = ? LIMIT 1");
    $stmt->bind_param("ii", $book_id, $member_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_row();
    $stmt->close();

    if ($existing) {
        $stmt = $conn->prepare("UPDATE book_reviews SET rating = ?, comment = ?, reviewer_name = ?, reviewer_role = ?, created_at = NOW() WHERE id = ?");
        $stmt->bind_param("isssi", $rating, $comment, $reviewer_name, $reviewer_role, $existing[0]);
    } else {
        $stmt = $conn->prepare("INSERT INTO book_reviews (book_id, member_id, rating, comment, reviewer_name, reviewer_role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisss", $book_id, $member_id, $rating, $comment, $reviewer_name, $reviewer_role);
    }
    $stmt->execute();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    reply(['error' => 'Could not save the review.'], 500);
}

$count = $conn->query("SELECT COUNT(*) FROM book_reviews WHERE book_id = " . $book_id)->fetch_row()[0];
reply([
    'success' => true,
    'message' => $existing ? 'Your review was updated.' : 'Review posted.',
    'review_count' => (int)$count
]);
