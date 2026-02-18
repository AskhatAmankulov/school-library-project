<?php
// Demo data - simulating database records
// Use session to persist changes during demo mode
if (!isset($_SESSION['demo_books'])) {
    $_SESSION['demo_books'] = [
        ['id' => 1, 'title' => 'The Great Gatsby', 'author' => 'F. Scott Fitzgerald', 'isbn' => '978-0-7432-7356-5', 'category' => 'Fiction', 'quantity' => 5, 'available_quantity' => 3],
        ['id' => 2, 'title' => 'To Kill a Mockingbird', 'author' => 'Harper Lee', 'isbn' => '978-0-06-112008-4', 'category' => 'Fiction', 'quantity' => 3, 'available_quantity' => 1],
        ['id' => 3, 'title' => '1984', 'author' => 'George Orwell', 'isbn' => '978-0-452-26059-5', 'category' => 'Dystopian', 'quantity' => 4, 'available_quantity' => 2],
    ];
}
$demo_books = &$_SESSION['demo_books'];

$demo_members = [
    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'phone' => '555-0001', 'status' => 'active'],
    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'phone' => '555-0002', 'status' => 'active'],
    ['id' => 3, 'name' => 'Bob Johnson', 'email' => 'bob@example.com', 'phone' => '555-0003', 'status' => 'active'],
];

$demo_borrowings = [
    ['id' => 1, 'book_title' => 'The Great Gatsby', 'member_name' => 'John Doe', 'borrow_date' => '2025-11-10', 'due_date' => '2025-11-24', 'status' => 'active', 'days_left' => 3],
    ['id' => 2, 'book_title' => 'To Kill a Mockingbird', 'member_name' => 'Jane Smith', 'borrow_date' => '2025-11-15', 'due_date' => '2025-11-29', 'status' => 'active', 'days_left' => 8],
];
?>
