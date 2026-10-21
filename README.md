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
4. Select `library`, open the **Import** tab and import these files, in this order:
   1. `library.sql`: all tables and sample data
   2. `add_users_table.sql`: login accounts
   3. `fix_data.sql`: repairs copy counts and a few damaged values in the sample data

Already have the database from an earlier version? Import only the files you haven't imported yet. `add_users_table.sql` and `fix_data.sql` are safe to import again.
5. Open http://localhost/lib/login.php


## Accounts

| Username | Password | Role |
|---|---|---|
| admin | admin123 | Admin |
| librarian | librarian123 | Librarian |
| user | user123 | Student |
| askhat, emma, liam, sophia, noah | 123 | Student |

### What each role can do

| Action | Student | Librarian | Admin |
|---|:-:|:-:|:-:|
| Browse books, write reviews | ✅ | ✅ | ✅ |
| Own profile and history | ✅ | ✅ | ✅ |
| Add and edit books | ❌ | ✅ | ✅ |
| Issue and return books | ❌ | ✅ | ✅ |
| See students and their profiles | ❌ | ✅ | ✅ |
| Add and edit students, CSV import | ❌ | ✅ | ✅ |
| Delete books and students | ❌ | ❌ | ✅ |

The checks live in `auth_check.php` (`is_staff()`, `is_admin()`, `require_staff()`, `require_admin()`).

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

## Project structure

```
lib/
├── login.php, register.php, logout.php   Accounts
├── index.php                             Dashboard (staff view / student view)
├── books.php                             Book catalogue, add/edit, CSV import
├── members.php                           Students (staff only)
├── borrowings.php                        Issue and return books (staff only)
├── profile.php                           Profile, history, reviews, password, photo
├── get_*.php, update_*.php, delete_*.php,
│   search_*.php, submit_review.php,
│   import_csv.php                        AJAX endpoints used by the pages
├── auth_check.php                        Login check + role helpers
├── config.php                            Database connection
├── includes/                             Header, footer, shared functions, class settings
├── css/, js/                             Styles and scripts
├── uploads/                              Book covers and profile photos
├── library.sql, add_users_table.sql      Database to import
└── .htaccess                             Blocks web access to .sql/.md and includes/
```

## Troubleshooting

- **"Database connection failed"**: MySQL isn't running. Restart it in the XAMPP Control Panel (run it as Administrator).
- **"The users table is missing"**: import `add_users_table.sql` (step 4).
- **Page not found**: check the files are in `C:\xampp\htdocs\lib` and Apache is running.
