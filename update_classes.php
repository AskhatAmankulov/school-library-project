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

if (!is_array($data['sections']) || array_filter($data['sections'], fn($x) => !is_scalar($x))
    || !is_numeric($data['min_grade']) || !is_numeric($data['max_grade'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Grades must be numbers and sections a list like A,B,C.']);
    exit();
}

// Optionally handle year_overrides
$min_grade = (int)$data['min_grade'];
$max_grade = (int)$data['max_grade'];
$sections = array_map('strval', $data['sections']);
$start_month = isset($data['start_month']) ? (int)$data['start_month'] : 9;
$year_overrides = isset($data['year_overrides']) && is_array($data['year_overrides']) ? $data['year_overrides'] : [];

$sections = array_values(array_filter(array_map('trim', $sections), fn($x) => $x !== ''));
$error = null;
if ($min_grade < 1 || $max_grade > 20 || $min_grade > $max_grade) {
    $error = 'Grades must run from a lower to a higher number between 1 and 20.';
} elseif (!$sections) {
    $error = 'Enter at least one section, e.g. A.';
} elseif (count($sections) > 26 || array_filter($sections, fn($x) => !preg_match('/^[A-Za-z0-9]{1,5}$/', $x))) {
    $error = 'Sections must be short letters or numbers, e.g. A,B,C.';
} elseif ($start_month < 1 || $start_month > 12) {
    $error = 'The start month must be 1 to 12.';
}
if ($error) {
    http_response_code(400);
    echo json_encode(['error' => $error]);
    exit();
}

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
