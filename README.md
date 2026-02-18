# Library Management System - Setup Instructions

## Prerequisites

- XAMPP installed and running
- Apache and MySQL services active
- PHPMyAdmin accessible at `http://localhost/phpmyadmin`

## Step 1: Create Database

1. Open PHPMyAdmin: http://localhost/phpmyadmin
2. Click "New" or "Create database"
3. Database name: `library_db`
4. Collation: `utf8_general_ci`
5. Click "Create"

## Step 2: Create Tables

Copy and paste the following SQL in the SQL tab of PHPMyAdmin:

```sql
-- Create Books Table
CREATE TABLE books (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  author VARCHAR(255) NOT NULL,
  isbn VARCHAR(20) UNIQUE,
  publication_date DATE,
  quantity INT DEFAULT 0,
  available_quantity INT DEFAULT 0,
  category VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Members Table
CREATE TABLE members (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) UNIQUE,
  phone VARCHAR(20),
  address TEXT,
  membership_date DATE,
  status ENUM('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Borrowings Table
CREATE TABLE borrowings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  member_id INT NOT NULL,
  book_id INT NOT NULL,
  borrow_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE,
  status ENUM('active', 'returned') DEFAULT 'active',
  FOREIGN KEY (member_id) REFERENCES members(id),
  FOREIGN KEY (book_id) REFERENCES books(id)
);
```

## Step 3: Access the Application

1. Start XAMPP (Apache and MySQL)
2. Open browser: `http://localhost/lib`
3. You should see the Library Dashboard

## File Structure

```
c:\xampp\htdocs\lib\
├── index.php                 (Dashboard)
├── books.php                 (Books Management)
├── members.php               (Members Management)
├── borrowings.php            (Borrowings Management)
├── config.php                (Database Connection)
├── includes/
│   ├── header.php           (HTML Header)
│   ├── footer.php           (HTML Footer)
│   └── functions.php        (Utility Functions)
├── css/
│   └── style.css            (Styling)
└── js/
    └── script.js            (JavaScript)
```

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

## Sample Data (Optional)

Insert sample data into PHPMyAdmin:

```sql
-- Sample Books
INSERT INTO books (title, author, isbn, publication_date, quantity, available_quantity, category)
VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', '978-0-7432-7356-5', '1925-04-10', 5, 5, 'Fiction'),
('To Kill a Mockingbird', 'Harper Lee', '978-0-06-112008-4', '1960-07-11', 3, 2, 'Fiction'),
('1984', 'George Orwell', '978-0-452-26059-5', '1949-06-08', 4, 4, 'Dystopian');

-- Sample Members
INSERT INTO members (name, email, phone, address, membership_date, status)
VALUES
('John Doe', 'john@example.com', '555-0001', '123 Main St', CURDATE(), 'active'),
('Jane Smith', 'jane@example.com', '555-0002', '456 Oak Ave', CURDATE(), 'active'),
('Bob Johnson', 'bob@example.com', '555-0003', '789 Pine Rd', CURDATE(), 'active');
```

## Troubleshooting

### Database Connection Error

- Check MySQL is running in XAMPP
- Verify credentials in `config.php`
- Ensure database `library_db` exists

### Page Not Found

- Check file location: `c:\xampp\htdocs\lib\`
- Verify Apache is running
- Try `http://localhost/lib/index.php`

### Styling Issues

- Clear browser cache (Ctrl+Shift+Delete)
- Ensure CSS file is loading: check browser console

## Future Enhancements

- User authentication/login system
- Email notifications for overdue books
- Book search and filters
- Member reports
- PDF export functionality
- Fine calculation for overdue books
