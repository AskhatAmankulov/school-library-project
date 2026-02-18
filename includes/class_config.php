<?php
// Configuration for dynamic class generation
// Adjust as needed for your school
return [
    'min_grade' => 1,             // starting grade level
    'max_grade' => 12,            // ending grade level
    'sections'  => ['A', 'B'],    // default letter sections per grade
    'start_month' => 9,           // academic year starts in September (1-12)
    
    // Optional per-academic-year overrides.
    // Example format:
    // 'year_overrides' => [
    //     '2025-2026' => [ 'sections' => ['A','B','C'] ],
    //     '2026-2027' => [ 'sections' => ['A','B'] ],
    // ]
    'year_overrides' => []
];
