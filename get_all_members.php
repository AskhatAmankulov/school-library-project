<?php
session_start();
include 'auth_check.php';
include 'config.php';

// Only admins can access this
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized - Admin role required']);
    exit();
}

if ($db_error || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get all active members
$stmt = $conn->prepare("SELECT id, name, class FROM members WHERE status='active' ORDER BY name");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => $conn->error]);
    exit();
}

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
