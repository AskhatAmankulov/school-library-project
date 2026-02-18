-- Add cover_url field to books table (MySQL)
ALTER TABLE books
  ADD COLUMN cover_url VARCHAR
(512) NULL AFTER shelf_row;