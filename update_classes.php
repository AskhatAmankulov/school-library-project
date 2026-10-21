<?php
// API to update class configuration (add/edit/remove classes)
// Only allow POST requests and require authentication (reuse session)
session_start();
include 'auth_check.php';
require_staff(true);

$configPath = __DIR__ . '/includes/class_config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Class config not found']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['min_grade']) || !isset($data['max_grade']) || !isset($data['sections'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

// Optionally handle year_overrides
$min_grade = (int)$data['min_grade'];
$max_grade = (int)$data['max_grade'];
$sections = array_map('strval', $data['sections']);
$start_month = isset($data['start_month']) ? (int)$data['start_month'] : 9;
$year_overrides = isset($data['year_overrides']) && is_array($data['year_overrides']) ? $data['year_overrides'] : [];

// Build PHP config array as string
$configArr = [
    'min_grade' => $min_grade,
    'max_grade' => $max_grade,
    'sections' => $sections,
    'start_month' => $start_month,
    'year_overrides' => $year_overrides
];

// Export as PHP file
$configStr = "<?php\nreturn " . var_export($configArr, true) . ";\n";
if (file_put_contents($configPath, $configStr) === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to write config']);
    exit();
}

echo json_encode(['success' => true]);
