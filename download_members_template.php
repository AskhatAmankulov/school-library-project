<?php
session_start();

include 'auth_check.php';
require_staff();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="members_template.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 (helps Excel recognize UTF-8 encoding)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header row
fputcsv($output, array('First Name', 'Last Name', 'Class', 'Group'));

// Write example rows
fputcsv($output, array('Emma', 'Johnson', '7H', 'A'));
fputcsv($output, array('Liam', 'Smith', '8C', 'B'));
fputcsv($output, array('Olivia', 'Williams', '9A', 'C'));

fclose($output);
exit();
?>
