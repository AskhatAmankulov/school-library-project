<?php
// Demo users (in real app, store in database with hashed passwords)
$demo_users = [
    [
        'id' => 2, // Jane Smith from members table
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_BCRYPT), // hashed password
        'email' => 'admin@library.com',
        'role' => 'admin'
    ],
    [
        'id' => 3, // Bob Johnson from members table
        'username' => 'librarian',
        'password' => password_hash('librarian123', PASSWORD_BCRYPT),
        'email' => 'librarian@library.com',
        'role' => 'librarian'
    ],
    [
        'id' => 5, // Charlie Brown from members table
        'username' => 'user',
        'password' => password_hash('user123', PASSWORD_BCRYPT),
        'email' => 'user@library.com',
        'role' => 'user'
    ],
    [
        'id' => 6,
        'username' => 'askhat',
        'password' => password_hash('123', PASSWORD_BCRYPT),
        'email' => 'askhat@library.com',
        'role' => 'user',
        'class' => 'Year 10A'
    ],
    [
        'id' => 7,
        'username' => 'emma',
        'password' => password_hash('123', PASSWORD_BCRYPT),
        'email' => 'emma@library.com',
        'role' => 'user',
        'class' => 'Year 9B'
    ],
    [
        'id' => 8,
        'username' => 'liam',
        'password' => password_hash('123', PASSWORD_BCRYPT),
        'email' => 'liam@library.com',
        'role' => 'user',
        'class' => 'Year 10A'
    ],
    [
        'id' => 9,
        'username' => 'sophia',
        'password' => password_hash('123', PASSWORD_BCRYPT),
        'email' => 'sophia@library.com',
        'role' => 'user',
        'class' => 'Year 11C'
    ],
    [
        'id' => 10,
        'username' => 'noah',
        'password' => password_hash('123', PASSWORD_BCRYPT),
        'email' => 'noah@library.com',
        'role' => 'user',
        'class' => 'Year 10B'
    ]
];

// Demo login credentials info (for display only)
$login_info = [
    ['username' => 'admin', 'password' => 'admin123', 'role' => 'Admin'],
    ['username' => 'librarian', 'password' => 'librarian123', 'role' => 'Librarian'],
    ['username' => 'user', 'password' => 'user123', 'role' => 'User']
];
?>
