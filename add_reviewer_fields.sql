-- Add reviewer_name and reviewer_role to book_reviews (MySQL)
ALTER TABLE book_reviews
  ADD COLUMN reviewer_name VARCHAR
(255) NULL AFTER comment,
ADD COLUMN reviewer_role ENUM
('admin','librarian','user') NULL AFTER reviewer_name;