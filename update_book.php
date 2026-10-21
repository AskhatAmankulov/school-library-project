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
if ($too_long = length_error(['Title' => [$title, 255], 'Author' => [$author, 255], 'ISBN' => [$isbn, 20], 'Category' => [$category, 100], 'Row' => [$shelf_row, 10], 'Cover link' => [$cover_url, 512]])) {
    echo json_encode(['error' => $too_long]);
    exit();
}

if ($db_error || !$conn) {
    echo json_encode(['error' => 'Database connection error']);
    exit();
}

if ($quantity < 0) {
    echo json_encode(['error' => 'Copies cannot be negative.']);
    exit();
}

// Copies can't go below the number currently on loan
$stmt = $conn->prepare("SELECT COUNT(*) FROM borrowings WHERE book_id = ? AND status = 'active'");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$on_loan = (int)$stmt->get_result()->fetch_row()[0];
$stmt->close();
if ($quantity < $on_loan) {
    echo json_encode(['error' => $on_loan . ' cop' . ($on_loan == 1 ? 'y is' : 'ies are') . ' on loan right now, so set Copies to at least ' . $on_loan . '.']);
    exit();
}

// ISBN must be unique
if ($isbn !== null) {
    $stmt = $conn->prepare("SELECT title FROM books WHERE isbn = ? AND id <> ? LIMIT 1");
    $stmt->bind_param("si", $isbn, $book_id);
    $stmt->execute();
    $other = $stmt->get_result()->fetch_row();
    $stmt->close();
    if ($other) {
        echo json_encode(['error' => 'ISBN ' . $isbn . ' already belongs to "' . $other[0] . '".']);
        exit();
    }
}
$available = $quantity - $on_loan;

// Try full update with new columns
$sql = "UPDATE books SET title=?, author=?, isbn=?, category=?, quantity=?, available_quantity=?, shelf_number=?, shelf_row=?, cover_url=? WHERE id=?";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ssssiiissi", $title, $author, $isbn, $category, $quantity, $available, $shelf_number, $shelf_row, $final_cover_url, $book_id);
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

try {
    $stmt->execute();
    echo json_encode(['success' => true, 'message' => 'Book updated successfully']);
} catch (mysqli_sql_exception $e) {
    echo json_encode(['error' => 'Could not save the book: ' . $e->getMessage()]);
}

$stmt->close();
?>