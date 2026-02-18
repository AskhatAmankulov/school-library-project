<?php
// Check database and add test overdue books
include 'config.php';

if ($db_error || !$conn) {
    die("Database connection failed!");
}

echo "<h2>📊 Database Check</h2>";

// Check members
$members_result = mysqli_query($conn, "SELECT id, name FROM members LIMIT 5");
echo "<h3>Available Members:</h3>";
echo "<ul>";
while ($member = mysqli_fetch_assoc($members_result)) {
    echo "<li>ID: {$member['id']} - {$member['name']}</li>";
}
echo "</ul>";

// Check books
$books_result = mysqli_query($conn, "SELECT id, title FROM books LIMIT 5");
echo "<h3>Available Books:</h3>";
echo "<ul>";
while ($book = mysqli_fetch_assoc($books_result)) {
    echo "<li>ID: {$book['id']} - {$book['title']}</li>";
}
echo "</ul>";

// Check current borrowings
$borrowings_result = mysqli_query($conn, "SELECT * FROM borrowings WHERE status='active' AND due_date < CURDATE()");
echo "<h3>Current Overdue Borrowings:</h3>";
if (mysqli_num_rows($borrowings_result) > 0) {
    echo "<ul>";
    while ($b = mysqli_fetch_assoc($borrowings_result)) {
        echo "<li>Member ID: {$b['member_id']}, Book ID: {$b['book_id']}, Due: {$b['due_date']}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No overdue books found.</p>";
}

// Now add test overdue books using actual member and book IDs
mysqli_query($conn, "SELECT id FROM members ORDER BY id ASC LIMIT 1");
$first_member = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM members ORDER BY id ASC LIMIT 1"));
$second_member = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM members ORDER BY id ASC LIMIT 1,1"));
$third_member = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM members ORDER BY id ASC LIMIT 2,1"));

$first_book = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM books ORDER BY id ASC LIMIT 1"));
$second_book = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM books ORDER BY id ASC LIMIT 1,1"));
$third_book = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM books ORDER BY id ASC LIMIT 2,1"));

echo "<hr><h2>➕ Adding Test Overdue Books...</h2>";

$added = 0;

if ($first_member && $first_book) {
    $query1 = "INSERT INTO borrowings (member_id, book_id, borrow_date, due_date, status) 
               VALUES ({$first_member['id']}, {$first_book['id']}, DATE_SUB(CURDATE(), INTERVAL 12 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'active')";
    if (mysqli_query($conn, $query1)) {
        echo "<p>✅ Added: Mild overdue (2 days) - Member {$first_member['id']}, Book {$first_book['id']}</p>";
        $added++;
    } else {
        echo "<p>❌ Error: " . mysqli_error($conn) . "</p>";
    }
}

if ($second_member && $second_book) {
    $query2 = "INSERT INTO borrowings (member_id, book_id, borrow_date, due_date, status) 
               VALUES ({$second_member['id']}, {$second_book['id']}, DATE_SUB(CURDATE(), INTERVAL 16 DAY), DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'active')";
    if (mysqli_query($conn, $query2)) {
        echo "<p>✅ Added: Moderate overdue (6 days) - Member {$second_member['id']}, Book {$second_book['id']}</p>";
        $added++;
    } else {
        echo "<p>❌ Error: " . mysqli_error($conn) . "</p>";
    }
}

if ($third_member && $third_book) {
    $query3 = "INSERT INTO borrowings (member_id, book_id, borrow_date, due_date, status) 
               VALUES ({$third_member['id']}, {$third_book['id']}, DATE_SUB(CURDATE(), INTERVAL 22 DAY), DATE_SUB(CURDATE(), INTERVAL 12 DAY), 'active')";
    if (mysqli_query($conn, $query3)) {
        echo "<p>✅ Added: Severe overdue (12 days) - Member {$third_member['id']}, Book {$third_book['id']}</p>";
        $added++;
    } else {
        echo "<p>❌ Error: " . mysqli_error($conn) . "</p>";
    }
}

mysqli_close($conn);

echo "<hr>";
echo "<h3>✅ Successfully added $added overdue borrowing records!</h3>";
echo "<p><a href='index.php' style='background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block; margin-top: 10px; font-weight: 600;'>Go to Dashboard</a></p>";
echo "<p><small style='color: #666;'>You can delete this file (check_and_add_overdue.php) after testing.</small></p>";
?>
