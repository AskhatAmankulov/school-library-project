<?php
include 'config.php';

if ($db_error || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

// Books to add
$books = [
    ['Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling', '978-0-439-70818-8', 'Fantasy', 10, 10],
    ['The Great Gatsby', 'F. Scott Fitzgerald', '978-0-7432-7356-5', 'Classic', 8, 8],
    ['To Kill a Mockingbird', 'Harper Lee', '978-0-06-112008-4', 'Classic', 7, 7],
    ['The Catcher in the Rye', 'J.D. Salinger', '978-0-316-76948-0', 'Fiction', 6, 6]
];

$stmt = $conn->prepare("INSERT INTO books (title, author, isbn, category, quantity, available_quantity) VALUES (?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}

$success_count = 0;
foreach ($books as $book) {
    $stmt->bind_param("ssssii", $book[0], $book[1], $book[2], $book[3], $book[4], $book[5]);
    if ($stmt->execute()) {
        echo "✓ Added: {$book[0]} by {$book[1]}<br>";
        $success_count++;
    } else {
        echo "✗ Failed to add: {$book[0]} - Error: " . $stmt->error . "<br>";
    }
}

$stmt->close();
$conn->close();

echo "<br><strong>Successfully added $success_count out of " . count($books) . " books.</strong><br>";
echo "<br><a href='books.php'>Go to Books Page</a>";
?>
