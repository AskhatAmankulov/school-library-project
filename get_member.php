<?php
session_start();
include 'auth_check.php';
require_staff(true);
include 'config.php';
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'No member ID provided']);
    exit();
}
$id = (int)$_GET['id'];
if ($db_error || !$conn) {
    echo json_encode(['error' => 'Database connection error']);
    exit();
}
$res = $conn->query("SELECT id, name, class, membership_date, first_name, last_name, group_name, status FROM members WHERE id = $id");
if ($res && $res->num_rows > 0) {
    echo json_encode($res->fetch_assoc());
} else {
    echo json_encode(['error' => 'Member not found']);
}
