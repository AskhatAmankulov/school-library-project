<?php
session_start();
include 'auth_check.php';
require_staff(true);
include 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

if (!isset($_POST['book_id']) || empty($_POST['book_id'])) {
    echo json_encode(['error' => 'Book ID is required']);
    exit();
}

$book_id = (int)$_POST['book_id'];
$title = trim($_POST['title'] ?? '');
$author = trim($_POST['author'] ?? '');
$isbn = trim($_POST['isbn'] ?? '');
if ($isbn === '') { $isbn = null; }
$category = trim($_POST['category'] ?? '');
$quantity = (int)($_POST['quantity'] ?? 0);
$shelf_number = isset($_POST['shelf_number']) && $_POST['shelf_number'] !== '' ? (int)$_POST['shelf_number'] : null;
$shelf_row = trim($_POST['shelf_row'] ?? '');
if ($shelf_row === '') { $shelf_row = null; }
$cover_url = trim($_POST['cover_url'] ?? '');
if ($cover_url === '') { $cover_url = null; }
// Handle optional file upload for cover
$final_cover_url = $cover_url;
$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'covers';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
if (isset($_FILES['cover_file']) && is_uploaded_file($_FILES['cover_file']['tmp_name'])) {
    $file = $_FILES['cover_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] <= $maxSize) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (isset($allowed[$mime])) {
                $ext = $allowed[$mime];
                $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower(pathinfo($file['name'], PATHINFO_FILENAME)));
                $unique = $safeName . '_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                $dest = $uploadDir . DIRECTORY_SEPARATOR . $unique;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $final_cover_url = 'uploads/covers/' . $unique;
                }
            }
        }
    }
}
// If no file and URL provided, ensure http/https
if (!$final_cover_url && $cover_url && !preg_match('/^https?:\/\//i', $cover_url)) {
    $final_cover_url = null;
}

if (empty($title) || empty($author)) {
    echo json_encode(['error' => 'Title and Author are required']);
    exit();
}

if ($db_error || !$conn) {
    echo json_encode(['error' => 'Database connection error']);
    exit();
}

// Try full update with new columns
$sql = "UPDATE books SET title=?, author=?, isbn=?, category=?, quantity=?, shelf_number=?, shelf_row=?, cover_url=? WHERE id=?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ssssiissi", $title, $author, $isbn, $category, $quantity, $shelf_number, $shelf_row, $final_cover_url, $book_id);
} else {
    // Fallback for environments without new columns
    $sql = "UPDATE books SET title=?, author=?, isbn=?, category=?, quantity=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ssssii", $title, $author, $isbn, $category, $quantity, $book_id);
    } else {
        echo json_encode(['error' => 'Failed to prepare update statement']);
        exit();
    }
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Book updated successfully']);
} else {
    echo json_encode(['error' => 'Failed to update book: ' . $conn->error]);
}

$stmt->close();
?>