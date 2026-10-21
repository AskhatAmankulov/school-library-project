<?php
header('Content-Type: application/json');

$configPath = __DIR__ . '/includes/class_config.php';
$config = file_exists($configPath) ? include $configPath : [
    'min_grade' => 1,
    'max_grade' => 12,
    'sections' => ['A','B'],
    'start_month' => 9
];

$now = new DateTime('now');
$year = (int)$now->format('Y');
$month = (int)$now->format('n');
$startMonth = max(1, min(12, (int)$config['start_month']));

// Determine academic year label (e.g., 2025-2026)
if ($month < $startMonth) {
    $startYear = $year - 1;
} else {
    $startYear = $year;
}
$academicYear = $startYear . '-' . ($startYear + 1);

$min = (int)$config['min_grade'];
$max = (int)$config['max_grade'];
$sections = $config['sections'];

// Apply per-academic-year overrides if present
if (!empty($config['year_overrides']) && is_array($config['year_overrides'])) {
    if (isset($config['year_overrides'][$academicYear]) && is_array($config['year_overrides'][$academicYear])) {
        $override = $config['year_overrides'][$academicYear];
        if (isset($override['min_grade'])) $min = (int)$override['min_grade'];
        if (isset($override['max_grade'])) $max = (int)$override['max_grade'];
        if (isset($override['sections']) && is_array($override['sections']) && count($override['sections']) > 0) {
            $sections = $override['sections'];
        }
    }
}

$classes = [];
for ($g = $min; $g <= $max; $g++) {
    foreach ($sections as $sec) {
        $classes[] = [
            'value' => $g . $sec,            // stored value like 10A
            'label' => 'Grade ' . $g . $sec  // display label like Grade 10A
        ];
    }
}

// Append manually added classes (admin-defined free text)
$manualPath = __DIR__ . '/includes/manual_classes.php';
$manualClasses = file_exists($manualPath) ? include $manualPath : [];
if (is_array($manualClasses)) {
    foreach ($manualClasses as $mc) {
        $mc = trim($mc);
        if ($mc === '') { continue; }
        // Avoid duplicate of existing generated class values
        $already = false;
        foreach ($classes as $c) {
            if (strcasecmp($c['value'], $mc) === 0) { $already = true; break; }
        }
        if (!$already) {
            $classes[] = [
                'value' => $mc,
                'label' => $mc // show as typed
            ];
        }
    }
}

echo json_encode([
    'academicYear' => $academicYear,
    'classes'      => $classes,
    'manual'       => $manualClasses,
    // Raw settings, used by the class manager form on members.php
    'config'       => [
        'min_grade'      => (int)($config['min_grade'] ?? 1),
        'max_grade'      => (int)($config['max_grade'] ?? 12),
        'sections'       => array_values($config['sections'] ?? ['A', 'B']),
        'start_month'    => (int)($config['start_month'] ?? 9),
        'year_overrides' => $config['year_overrides'] ?? []
    ]
]);
