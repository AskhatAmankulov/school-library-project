-- Create Books Table
CREATE TABLE
IF NOT EXISTS books
(
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR
(255) NOT NULL,
  author VARCHAR
(255) NOT NULL,
  isbn VARCHAR
(20) UNIQUE,
  publication_date DATE,
  quantity INT DEFAULT 0,
  available_quantity INT DEFAULT 0,
  category VARCHAR
(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Members Table
CREATE TABLE
IF NOT EXISTS members
(
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR
(255) NOT NULL,
  email VARCHAR
(255) UNIQUE,
  phone VARCHAR
(20),
  address TEXT,
  membership_date DATE,
  status ENUM
('active', 'inactive') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create Borrowings Table
CREATE TABLE
IF NOT EXISTS borrowings
(
  id INT PRIMARY KEY AUTO_INCREMENT,
  member_id INT NOT NULL,
  book_id INT NOT NULL,
  borrow_date DATE NOT NULL,
  due_date DATE NOT NULL,
  return_date DATE,
  status ENUM
('active', 'returned') DEFAULT 'active',
  FOREIGN KEY
(member_id) REFERENCES members
(id) ON
DELETE CASCADE,
  FOREIGN KEY (book_id)
REFERENCES books
(id) ON
DELETE CASCADE
);

-- Insert Sample Books
INSERT IGNORE
INTO books
(title, author, isbn, publication_date, quantity, available_quantity, category) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', '978-0-7432-7356-5', '1925-04-10', 5, 5, 'Fiction'),
('To Kill a Mockingbird', 'Harper Lee', '978-0-06-112008-4', '1960-07-11', 3, 3, 'Fiction'),
('1984', 'George Orwell', '978-0-452-26059-5', '1949-06-08', 4, 4, 'Dystopian'),
('Pride and Prejudice', 'Jane Austen', '978-0-14-143951-8', '1813-01-28', 6, 6, 'Romance'),
('The Catcher in the Rye', 'J.D. Salinger', '978-0-316-76948-0', '1951-07-16', 2, 2, 'Fiction'),
('Brave New World', 'Aldous Huxley', '978-0-06-085052-4', '1932-06-30', 3, 3, 'Dystopian'),
('The Hobbit', 'J.R.R. Tolkien', '978-0-547-92822-2', '1937-09-21', 4, 4, 'Fantasy'),
('Harry Potter', 'J.K. Rowling', '978-0-439-13959-0', '1997-06-26', 7, 7, 'Fantasy');

-- Insert Sample Members
INSERT IGNORE
INTO members
(name, email, phone, address, membership_date, status) VALUES
('John Doe', 'john@example.com', '555-0001', '123 Main Street', CURDATE
(), 'active'),
('Jane Smith', 'jane@example.com', '555-0002', '456 Oak Avenue', CURDATE
(), 'active'),
('Bob Johnson', 'bob@example.com', '555-0003', '789 Pine Road', CURDATE
(), 'active'),
('Alice Williams', 'alice@example.com', '555-0004', '321 Elm Street', CURDATE
(), 'active'),
('Charlie Brown', 'charlie@example.com', '555-0005', '654 Maple Drive', CURDATE
(), 'active');

-- Insert Sample Borrowings
INSERT IGNORE
INTO borrowings
(member_id, book_id, borrow_date, due_date, status) VALUES
(1, 1, DATE_SUB
(CURDATE
(), INTERVAL 5 DAY), DATE_ADD
(CURDATE
(), INTERVAL 9 DAY), 'active'),
(2, 3, DATE_SUB
(CURDATE
(), INTERVAL 3 DAY), DATE_ADD
(CURDATE
(), INTERVAL 11 DAY), 'active'),
(3, 5, DATE_SUB
(CURDATE
(), INTERVAL 7 DAY), DATE_ADD
(CURDATE
(), INTERVAL 7 DAY), 'active'),
(1, 7, DATE_SUB
(CURDATE
(), INTERVAL 20 DAY), DATE_SUB
(CURDATE
(), INTERVAL 6 DAY), 'returned'),
(2, 2, DATE_SUB
(CURDATE
(), INTERVAL 2 DAY), DATE_ADD
(CURDATE
(), INTERVAL 12 DAY), 'active');

-- Update available quantities
UPDATE books SET available_quantity = 4 WHERE id = 1;
UPDATE books SET available_quantity = 2 WHERE id = 3;
UPDATE books SET available_quantity = 1 WHERE id = 5;
UPDATE books SET available_quantity = 3 WHERE id = 7;
UPDATE books SET available_quantity = 2 WHERE id = 2;
