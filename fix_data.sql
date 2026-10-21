-- ============================================================
-- One-time data repair. Import into the `library` database in
-- phpMyAdmin (Import tab). Safe to run more than once.
-- ============================================================

-- 1. Free copies must equal: copies - books currently on loan.
--    Older versions could count a return twice or miss a loan,
--    which left some books showing -1 or too many free copies.
UPDATE books b
SET b.available_quantity = GREATEST(0, b.quantity - (
    SELECT COUNT(*) FROM borrowings br
    WHERE br.book_id = b.id AND br.status = 'active'
));

-- If a book has more loans out than copies, raise the copy count to match.
UPDATE books b
JOIN (SELECT book_id, COUNT(*) AS n FROM borrowings WHERE status = 'active' GROUP BY book_id) loans
  ON loans.book_id = b.id
SET b.quantity = loans.n
WHERE b.quantity < loans.n;

-- 2. An old bug saved the shelf row letter as "0". The real letter is
--    unknown, so clear it; set it again with Edit book.
UPDATE books SET shelf_row = NULL WHERE shelf_row = '0';

-- 3. Accented letters lost in an earlier import.
UPDATE books SET author = 'Gabriel García Márquez' WHERE author LIKE 'Gabriel Garc%a M%rquez';

-- 4. Empty ISBNs must be NULL (the column is unique).
UPDATE books SET isbn = NULL WHERE isbn = '';
