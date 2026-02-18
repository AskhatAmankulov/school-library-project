<?php
session_start();
include 'auth_check.php';
include 'config.php';

// Only admins can access this
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($searchTerm) < 1) {
    echo json_encode([]);
    exit();
}

// Search for members - match by name
$searchTerm = '%' . $searchTerm . '%';

if ($db_error || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$stmt = $conn->prepare("SELECT id, name, class FROM members WHERE status='active' AND name LIKE ? ORDER BY name LIMIT 20");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit();
}

$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$members = [];
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

$stmt->close();

header('Content-Type: application/json');
echo json_encode($members);
?>
