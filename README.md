# School Library Management System

A PHP + MySQL web app for running a school library: books, students, borrowings, reviews and user accounts with roles (admin, librarian, student).

## Requirements

- [XAMPP](https://www.apachefriends.org/) (Apache + PHP + MySQL/MariaDB)

## Setup

1. Put the project in `C:\xampp\htdocs\lib`
   ```
   cd C:\xampp\htdocs
   git clone https://github.com/AskhatAmankulov/school-library-project.git lib
   ```
2. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3. Open http://localhost/phpmyadmin and create a database named **`library`**.
4. Select `library`, open the **Import** tab and import these two files, in this order:
   1. `library.sql`: all tables and sample data
   2. `add_users_table.sql`: login accounts
5. Open http://localhost/lib/login.php

The other `add_*.sql` files and `setup.sql` are old migration steps. They are already included in `library.sql`, so don't run them.

## Accounts

| Username | Password | Role |
|---|---|---|
| admin | admin123 | Admin |
| librarian | librarian123 | Librarian |
| user | user123 | Student |
| askhat, emma, liam, sophia, noah | 123 | Student |

Students can create their own account at `register.php`. Every account is stored in the `users` table and linked to a row in `members`.

## Features

### Dashboard (index.php)

- View total books, members, active borrowings
- See overdue books count
- View recent borrowing history

### Books Management (books.php)

- Add new books
- Edit book details
- Delete books
- View all books with availability

### Members Management (members.php)

- Register new members
- Edit member information
- Mark members as active/inactive
- Delete members

### Borrowings Management (borrowings.php)

- Borrow books for members
- Return books
- Track active borrowings
- View overdue books
- See borrowing history

### Accounts & Profile (login.php, register.php, profile.php)

- Log in with username or email
- Self-registration for students (creates the member and the account)
- Change password, upload a profile photo
- Borrowing history and reviews on the profile page

## Troubleshooting

- **"Database connection failed"**: MySQL isn't running. Restart it in the XAMPP Control Panel (run it as Administrator).
- **"The users table is missing"**: import `add_users_table.sql` (step 4).
- **Page not found**: check the files are in `C:\xampp\htdocs\lib` and Apache is running.
