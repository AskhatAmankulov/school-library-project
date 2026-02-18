-- Add location fields to books table
ALTER TABLE books
  ADD COLUMN shelf_number INT NULL AFTER available_quantity,
ADD COLUMN shelf_row VARCHAR
(10) NULL AFTER shelf_number;

-- Optional: backfill examples (uncomment to use)
-- UPDATE books SET shelf_number = 2, shelf_row = 'B' WHERE title = 'The Great Gatsby';
-- UPDATE books SET shelf_number = 1, shelf_row = 'A' WHERE title = '1984';
