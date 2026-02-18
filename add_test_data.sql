-- Add more diverse books across different categories
INSERT INTO books
    (title, author, isbn, publication_date, quantity, available_quantity, category)
VALUES
    ('The Lord of the Rings', 'J.R.R. Tolkien', '978-0-618-00222-1', '1954-07-29', 8, 7, 'Fantasy'),
    ('Dune', 'Frank Herbert', '978-0-441-17271-9', '1965-08-01', 5, 4, 'Science Fiction'),
    ('Foundation', 'Isaac Asimov', '978-0-553-29335-0', '1951-05-01', 4, 3, 'Science Fiction'),
    ('The Handmaid''s Tale', 'Margaret Atwood', '978-0-385-49081-8', '1985-06-01', 3, 3, 'Dystopian'),
    ('Crime and Punishment', 'Fyodor Dostoevsky', '978-0-14-310483-8', '1866-01-01', 5, 5, 'Classic'),
    ('War and Peace', 'Leo Tolstoy', '978-0-19-953297-6', '1869-01-01', 4, 4, 'Classic'),
    ('The Odyssey', 'Homer', '978-0-14-026886-7', '-800-01-01', 6, 6, 'Classic'),
    ('One Hundred Years of Solitude', 'Gabriel García Márquez', '978-0-06-088328-7', '1967-05-30', 4, 4, 'Magical Realism'),
    ('The Alchemist', 'Paulo Coelho', '978-0-06-231500-7', '1988-01-01', 10, 9, 'Fiction'),
    ('Sapiens', 'Yuval Noah Harari', '978-0-06-231609-7', '2011-01-01', 6, 5, 'Non-Fiction'),
    ('Educated', 'Tara Westover', '978-0-399-59050-4', '2018-02-20', 5, 5, 'Memoir'),
    ('Becoming', 'Michelle Obama', '978-1-524-76313-8', '2018-11-13', 7, 6, 'Biography'),
    ('The Da Vinci Code', 'Dan Brown', '978-0-385-50420-1', '2003-03-18', 8, 7, 'Mystery'),
    ('Gone Girl', 'Gillian Flynn', '978-0-307-58836-4', '2012-06-05', 4, 3, 'Thriller'),
    ('The Girl with the Dragon Tattoo', 'Stieg Larsson', '978-0-307-45454-8', '2005-08-01', 5, 4, 'Mystery'),
    ('And Then There Were None', 'Agatha Christie', '978-0-06-207348-5', '1939-11-06', 6, 6, 'Mystery'),
    ('The Shining', 'Stephen King', '978-0-385-12167-5', '1977-01-28', 4, 3, 'Horror'),
    ('It', 'Stephen King', '978-0-450-41130-6', '1986-09-15', 5, 4, 'Horror'),
    ('Dracula', 'Bram Stoker', '978-0-14-143984-6', '1897-05-26', 4, 4, 'Horror'),
    ('Frankenstein', 'Mary Shelley', '978-0-486-28211-4', '1818-01-01', 5, 5, 'Horror'),
    ('The Hunger Games', 'Suzanne Collins', '978-0-439-02348-1', '2008-09-14', 9, 7, 'Young Adult'),
    ('Divergent', 'Veronica Roth', '978-0-06-202402-2', '2011-04-25', 6, 5, 'Young Adult'),
    ('The Fault in Our Stars', 'John Green', '978-0-14-242417-9', '2012-01-10', 7, 6, 'Young Adult'),
    ('The Book Thief', 'Markus Zusak', '978-0-375-84220-7', '2005-09-01', 5, 4, 'Historical Fiction'),
    ('All the Light We Cannot See', 'Anthony Doerr', '978-1-476-74621-9', '2014-05-06', 6, 5, 'Historical Fiction'),
    ('The Nightingale', 'Kristin Hannah', '978-0-312-57721-5', '2015-02-03', 5, 4, 'Historical Fiction'),
    ('Where the Crawdads Sing', 'Delia Owens', '978-0-735-21932-3', '2018-08-14', 8, 6, 'Fiction'),
    ('The Silent Patient', 'Alex Michaelides', '978-1-250-30169-7', '2019-02-05', 6, 5, 'Thriller'),
    ('The Seven Husbands of Evelyn Hugo', 'Taylor Jenkins Reid', '978-1-501-13922-0', '2017-06-13', 7, 6, 'Fiction'),
    ('Normal People', 'Sally Rooney', '978-0-451-49990-8', '2018-08-28', 5, 4, 'Romance');

-- Add more members with diverse backgrounds
INSERT INTO members
    (name, address, membership_date, status)
VALUES
    ('Emma Watson', '567 Broadway St, New York', DATE_SUB(CURDATE(), INTERVAL
6 MONTH), 'active'),
('Michael Chen', '890 Cherry Lane, San Francisco', DATE_SUB
(CURDATE
(), INTERVAL 1 YEAR), 'active'),
('Sarah Johnson', '234 River Road, Portland', DATE_SUB
(CURDATE
(), INTERVAL 3 MONTH), 'active'),
('David Martinez', '456 Hill Street, Miami', DATE_SUB
(CURDATE
(), INTERVAL 8 MONTH), 'active'),
('Lisa Anderson', '789 Lake Avenue, Chicago', DATE_SUB
(CURDATE
(), INTERVAL 2 YEAR), 'active'),
('James Wilson', '321 Park Boulevard, Seattle', DATE_SUB
(CURDATE
(), INTERVAL 4 MONTH), 'active'),
('Maria Garcia', '654 Ocean Drive, Los Angeles', DATE_SUB
(CURDATE
(), INTERVAL 10 MONTH), 'active'),
('Robert Taylor', '987 Mountain View, Denver', DATE_SUB
(CURDATE
(), INTERVAL 5 MONTH), 'active'),
('Jennifer Lee', '147 Garden Street, Boston', DATE_SUB
(CURDATE
(), INTERVAL 7 MONTH), 'active'),
('William Brown', '258 Valley Road, Austin', DATE_SUB
(CURDATE
(), INTERVAL 9 MONTH), 'active'),
('Patricia Davis', '369 Forest Lane, Phoenix', DATE_SUB
(CURDATE
(), INTERVAL 11 MONTH), 'active'),
('Christopher Miller', '741 Desert Avenue, Las Vegas', DATE_SUB
(CURDATE
(), INTERVAL 2 MONTH), 'active'),
('Linda Rodriguez', '852 Harbor Way, San Diego', DATE_SUB
(CURDATE
(), INTERVAL 1 MONTH), 'active'),
('Daniel Hernandez', '963 Summit Drive, Salt Lake City', DATE_SUB
(CURDATE
(), INTERVAL 15 MONTH), 'active'),
('Nancy Lopez', '159 Sunset Boulevard, Nashville', CURDATE
(), 'active'),
('Thomas Moore', '357 Maple Street, Memphis', DATE_SUB
(CURDATE
(), INTERVAL 6 MONTH), 'inactive'),
('Betty Jackson', '486 Cedar Avenue, Charlotte', DATE_SUB
(CURDATE
(), INTERVAL 18 MONTH), 'inactive'),
('Mark White', '624 Pine Road, Atlanta', DATE_SUB
(CURDATE
(), INTERVAL 3 MONTH), 'active'),
('Sandra Harris', '735 Birch Lane, Detroit', DATE_SUB
(CURDATE
(), INTERVAL 12 MONTH), 'active'),
('Kevin Clark', '846 Spruce Way, Philadelphia', DATE_SUB
(CURDATE
(), INTERVAL 5 MONTH), 'active');

-- Add diverse borrowing records (some active, some returned, some overdue)
-- Recent active borrowings
INSERT INTO borrowings
    (member_id, book_id, borrow_date, due_date, status)
VALUES
    (6, 17, DATE_SUB(CURDATE(), INTERVAL
2 DAY), DATE_ADD
(CURDATE
(), INTERVAL 12 DAY), 'active'),
(7, 18, DATE_SUB
(CURDATE
(), INTERVAL 1 DAY), DATE_ADD
(CURDATE
(), INTERVAL 13 DAY), 'active'),
(8, 19, CURDATE
(), DATE_ADD
(CURDATE
(), INTERVAL 14 DAY), 'active'),
(9, 20, DATE_SUB
(CURDATE
(), INTERVAL 4 DAY), DATE_ADD
(CURDATE
(), INTERVAL 10 DAY), 'active'),
(10, 21, DATE_SUB
(CURDATE
(), INTERVAL 3 DAY), DATE_ADD
(CURDATE
(), INTERVAL 11 DAY), 'active'),
(11, 22, DATE_SUB
(CURDATE
(), INTERVAL 5 DAY), DATE_ADD
(CURDATE
(), INTERVAL 9 DAY), 'active'),
(12, 23, DATE_SUB
(CURDATE
(), INTERVAL 1 DAY), DATE_ADD
(CURDATE
(), INTERVAL 13 DAY), 'active'),
(13, 24, DATE_SUB
(CURDATE
(), INTERVAL 2 DAY), DATE_ADD
(CURDATE
(), INTERVAL 12 DAY), 'active'),
(14, 25, DATE_SUB
(CURDATE
(), INTERVAL 6 DAY), DATE_ADD
(CURDATE
(), INTERVAL 8 DAY), 'active'),
(15, 26, CURDATE
(), DATE_ADD
(CURDATE
(), INTERVAL 14 DAY), 'active'),
(18, 27, DATE_SUB
(CURDATE
(), INTERVAL 3 DAY), DATE_ADD
(CURDATE
(), INTERVAL 11 DAY), 'active'),
(19, 28, DATE_SUB
(CURDATE
(), INTERVAL 1 DAY), DATE_ADD
(CURDATE
(), INTERVAL 13 DAY), 'active'),
(20, 29, DATE_SUB
(CURDATE
(), INTERVAL 4 DAY), DATE_ADD
(CURDATE
(), INTERVAL 10 DAY), 'active'),
(6, 30, DATE_SUB
(CURDATE
(), INTERVAL 2 DAY), DATE_ADD
(CURDATE
(), INTERVAL 12 DAY), 'active'),
(7, 31, DATE_SUB
(CURDATE
(), INTERVAL 5 DAY), DATE_ADD
(CURDATE
(), INTERVAL 9 DAY), 'active');

-- Returned books with history
INSERT INTO borrowings
    (member_id, book_id, borrow_date, due_date, return_date, status)
VALUES
    (6, 1, DATE_SUB(CURDATE(), INTERVAL
30 DAY), DATE_SUB
(CURDATE
(), INTERVAL 16 DAY), DATE_SUB
(CURDATE
(), INTERVAL 18 DAY), 'returned'),
(7, 2, DATE_SUB
(CURDATE
(), INTERVAL 45 DAY), DATE_SUB
(CURDATE
(), INTERVAL 31 DAY), DATE_SUB
(CURDATE
(), INTERVAL 32 DAY), 'returned'),
(8, 3, DATE_SUB
(CURDATE
(), INTERVAL 60 DAY), DATE_SUB
(CURDATE
(), INTERVAL 46 DAY), DATE_SUB
(CURDATE
(), INTERVAL 47 DAY), 'returned'),
(9, 4, DATE_SUB
(CURDATE
(), INTERVAL 25 DAY), DATE_SUB
(CURDATE
(), INTERVAL 11 DAY), DATE_SUB
(CURDATE
(), INTERVAL 12 DAY), 'returned'),
(10, 5, DATE_SUB
(CURDATE
(), INTERVAL 50 DAY), DATE_SUB
(CURDATE
(), INTERVAL 36 DAY), DATE_SUB
(CURDATE
(), INTERVAL 37 DAY), 'returned'),
(11, 6, DATE_SUB
(CURDATE
(), INTERVAL 40 DAY), DATE_SUB
(CURDATE
(), INTERVAL 26 DAY), DATE_SUB
(CURDATE
(), INTERVAL 27 DAY), 'returned'),
(12, 7, DATE_SUB
(CURDATE
(), INTERVAL 35 DAY), DATE_SUB
(CURDATE
(), INTERVAL 21 DAY), DATE_SUB
(CURDATE
(), INTERVAL 22 DAY), 'returned'),
(13, 8, DATE_SUB
(CURDATE
(), INTERVAL 55 DAY), DATE_SUB
(CURDATE
(), INTERVAL 41 DAY), DATE_SUB
(CURDATE
(), INTERVAL 42 DAY), 'returned'),
(14, 32, DATE_SUB
(CURDATE
(), INTERVAL 20 DAY), DATE_SUB
(CURDATE
(), INTERVAL 6 DAY), DATE_SUB
(CURDATE
(), INTERVAL 7 DAY), 'returned'),
(15, 33, DATE_SUB
(CURDATE
(), INTERVAL 28 DAY), DATE_SUB
(CURDATE
(), INTERVAL 14 DAY), DATE_SUB
(CURDATE
(), INTERVAL 15 DAY), 'returned'),
(18, 34, DATE_SUB
(CURDATE
(), INTERVAL 33 DAY), DATE_SUB
(CURDATE
(), INTERVAL 19 DAY), DATE_SUB
(CURDATE
(), INTERVAL 20 DAY), 'returned'),
(19, 35, DATE_SUB
(CURDATE
(), INTERVAL 48 DAY), DATE_SUB
(CURDATE
(), INTERVAL 34 DAY), DATE_SUB
(CURDATE
(), INTERVAL 35 DAY), 'returned'),
(20, 36, DATE_SUB
(CURDATE
(), INTERVAL 22 DAY), DATE_SUB
(CURDATE
(), INTERVAL 8 DAY), DATE_SUB
(CURDATE
(), INTERVAL 9 DAY), 'returned');

-- Multiple borrowings from same members
INSERT INTO borrowings
    (member_id, book_id, borrow_date, due_date, return_date, status)
VALUES
    (6, 37, DATE_SUB(CURDATE(), INTERVAL
90 DAY), DATE_SUB
(CURDATE
(), INTERVAL 76 DAY), DATE_SUB
(CURDATE
(), INTERVAL 77 DAY), 'returned'),
(6, 38, DATE_SUB
(CURDATE
(), INTERVAL 75 DAY), DATE_SUB
(CURDATE
(), INTERVAL 61 DAY), DATE_SUB
(CURDATE
(), INTERVAL 62 DAY), 'returned'),
(7, 39, DATE_SUB
(CURDATE
(), INTERVAL 80 DAY), DATE_SUB
(CURDATE
(), INTERVAL 66 DAY), DATE_SUB
(CURDATE
(), INTERVAL 67 DAY), 'returned'),
(7, 40, DATE_SUB
(CURDATE
(), INTERVAL 65 DAY), DATE_SUB
(CURDATE
(), INTERVAL 51 DAY), DATE_SUB
(CURDATE
(), INTERVAL 52 DAY), 'returned'),
(8, 41, DATE_SUB
(CURDATE
(), INTERVAL 70 DAY), DATE_SUB
(CURDATE
(), INTERVAL 56 DAY), DATE_SUB
(CURDATE
(), INTERVAL 57 DAY), 'returned'),
(9, 42, DATE_SUB
(CURDATE
(), INTERVAL 85 DAY), DATE_SUB
(CURDATE
(), INTERVAL 71 DAY), DATE_SUB
(CURDATE
(), INTERVAL 72 DAY), 'returned'),
(10, 43, DATE_SUB
(CURDATE
(), INTERVAL 95 DAY), DATE_SUB
(CURDATE
(), INTERVAL 81 DAY), DATE_SUB
(CURDATE
(), INTERVAL 82 DAY), 'returned'),
(1, 44, DATE_SUB
(CURDATE
(), INTERVAL 100 DAY), DATE_SUB
(CURDATE
(), INTERVAL 86 DAY), DATE_SUB
(CURDATE
(), INTERVAL 87 DAY), 'returned'),
(2, 45, DATE_SUB
(CURDATE
(), INTERVAL 110 DAY), DATE_SUB
(CURDATE
(), INTERVAL 96 DAY), DATE_SUB
(CURDATE
(), INTERVAL 97 DAY), 'returned');

-- Overdue borrowings (active but past due date)
INSERT INTO borrowings
    (member_id, book_id, borrow_date, due_date, status)
VALUES
    (4, 46, DATE_SUB(CURDATE(), INTERVAL
20 DAY), DATE_SUB
(CURDATE
(), INTERVAL 6 DAY), 'active'),
(5, 47, DATE_SUB
(CURDATE
(), INTERVAL 25 DAY), DATE_SUB
(CURDATE
(), INTERVAL 11 DAY), 'active'),
(11, 17, DATE_SUB
(CURDATE
(), INTERVAL 18 DAY), DATE_SUB
(CURDATE
(), INTERVAL 4 DAY), 'active'),
(12, 18, DATE_SUB
(CURDATE
(), INTERVAL 22 DAY), DATE_SUB
(CURDATE
(), INTERVAL 8 DAY), 'active');

-- Update available quantities based on active borrowings
UPDATE books SET available_quantity = quantity - (SELECT COUNT(*)
FROM borrowings
WHERE book_id = books.id AND status = 'active');
