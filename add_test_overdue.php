<?php
// Quick script to add test overdue books
include 'config.php';

if ($db_error || !$conn) {
    die("Database connection failed!");
}

// Add 3 overdue borrowings with different severity levels
$queries = [
    // Mild overdue (2 days)
    "INSERT INTO borrowings (member_id, book_id, borrow_date, due_date, status) 
     VALUES (1, 1, DATE_SUB(CURDATE(), INTERVAL 12 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'active')",
    
    // Moderate overdue (6 days)
    "INSERT INTO borrowings (member_id, book_id, borrow_date, due_date, status) 
     VALUES (2, 2, DATE_SUB(CURDATE(), INTERVAL 16 DAY), DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'active')",
    
    // Severe overdue (12 days)
    "INSERT INTO borrowings (member_id, book_id, borrow_date, due_date, status) 
     VALUES (3, 3, DATE_SUB(CURDATE(), INTERVAL 22 DAY), DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'active')"
];

$success_count = 0;
foreach ($queries as $query) {
    if (mysqli_query($conn, $query)) {
        $success_count++;
    } else {
        echo "Error: " . mysqli_error($conn) . "<br>";
    }
}

mysqli_close($conn);

echo "<h2>✅ Test Overdue Books Added!</h2>";
echo "<p>Successfully added $success_count overdue borrowing records:</p>";
echo "<ul>";
echo "<li>🟠 Mild: 2 days overdue</li>";
echo "<li>🟠 Moderate: 6 days overdue</li>";
echo "<li>🔴 Severe: 12 days overdue</li>";
echo "</ul>";
echo "<p><a href='index.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px; display: inline-block; margin-top: 10px;'>Go to Dashboard</a></p>";
echo "<p><small>You can delete this file (add_test_overdue.php) after testing.</small></p>";
?>
