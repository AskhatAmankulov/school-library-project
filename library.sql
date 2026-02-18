-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 03, 2025 at 05:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `library`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `available_quantity` int(11) DEFAULT 0,
  `shelf_number` int(11) DEFAULT NULL,
  `shelf_row` varchar(10) DEFAULT NULL,
  `cover_url` varchar(512) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `publication_date`, `quantity`, `available_quantity`, `shelf_number`, `shelf_row`, `cover_url`, `category`, `created_at`) VALUES
(1, 'The Great Gatsby', 'F. Scott Fitzgerald', '978-0-7432-7356-5', '1925-04-10', 5, 5, 1, 'A', 'https://covers.openlibrary.org/b/id/7222246-M.jpg', 'Fiction', '2025-11-21 07:36:50'),
(2, 'To Kill a Mockingbird', 'Harper Lee', '978-0-06-112008-4', '1960-07-11', 3, 3, 1, '0', 'https://covers.openlibrary.org/b/id/8228691-M.jpg', 'Fiction', '2025-11-21 07:36:50'),
(3, '1984', 'George Orwell', '978-0-452-26059-5', '1949-06-08', 4, 2, 2, '0', 'https://covers.openlibrary.org/b/id/7222246-M.jpg', 'Dystopian', '2025-11-21 07:36:50'),
(4, 'Pride and Prejudice', 'Jane Austen', '978-0-14-143951-8', '1813-01-28', 6, 6, 2, 'B', 'https://covers.openlibrary.org/b/id/8235051-M.jpg', 'Romance', '2025-11-21 07:36:50'),
(5, 'The Catcher in the Rye', 'J.D. Salinger', '978-0-316-76948-0', '1951-07-16', 2, 1, 3, 'A', NULL, 'Fiction', '2025-11-21 07:36:50'),
(6, 'Brave New World', 'Aldous Huxley', '978-0-06-085052-4', '1932-06-30', 3, 3, NULL, NULL, NULL, 'Dystopian', '2025-11-21 07:36:50'),
(7, 'The Hobbit', 'J.R.R. Tolkien', '978-0-547-92822-2', '1937-09-21', 4, 4, NULL, NULL, NULL, 'Fantasy', '2025-11-21 07:36:50'),
(8, 'Harry Potter', 'J.K. Rowling', '978-0-439-13959-0', '1997-06-26', 7, 7, NULL, NULL, NULL, 'Fantasy', '2025-11-21 07:36:50'),
(17, 'as', 'as', 'asas', NULL, 1, -1, NULL, NULL, NULL, 'as', '2025-11-21 07:46:27'),
(18, 'The Lord of the Rings', 'J.R.R. Tolkien', '978-0-618-00222-1', '1954-07-29', 8, 6, NULL, NULL, NULL, 'Fantasy', '2025-11-24 01:27:57'),
(19, 'Dune', 'Frank Herbert', '978-0-441-17271-9', '1965-08-01', 5, 5, 6, '0', 'uploads/covers/pasted-image-1764143979013_1764143980_9138.png', 'Science Fiction', '2025-11-24 01:27:57'),
(20, 'Foundation', 'Isaac Asimov', '978-0-553-29335-0', '1951-05-01', 4, 2, NULL, NULL, NULL, 'Science Fiction', '2025-11-24 01:27:57'),
(21, 'The Handmaid\'s Tale', 'Margaret Atwood', '978-0-385-49081-8', '1985-06-01', 3, 1, NULL, NULL, NULL, 'Dystopian', '2025-11-24 01:27:57'),
(22, 'Crime and Punishment', 'Fyodor Dostoevsky', '978-0-14-310483-8', '1866-01-01', 5, 3, NULL, NULL, NULL, 'Classic', '2025-11-24 01:27:57'),
(23, 'War and Peace', 'Leo Tolstoy', '978-0-19-953297-6', '1869-01-01', 4, 4, NULL, NULL, NULL, 'Classic', '2025-11-24 01:27:57'),
(24, 'The Odyssey', 'Homer', '978-0-14-026886-7', '0000-00-00', 6, 4, NULL, NULL, NULL, 'Classic', '2025-11-24 01:27:57'),
(25, 'One Hundred Years of Solitude', 'Gabriel Garc??a M??rquez', '978-0-06-088328-7', '1967-05-30', 4, 3, NULL, NULL, NULL, 'Magical Realism', '2025-11-24 01:27:57'),
(26, 'The Alchemist', 'Paulo Coelho', '978-0-06-231500-7', '1988-01-01', 10, 10, NULL, NULL, NULL, 'Fiction', '2025-11-24 01:27:57'),
(27, 'Sapiens', 'Yuval Noah Harari', '978-0-06-231609-7', '2011-01-01', 6, 4, NULL, NULL, NULL, 'Non-Fiction', '2025-11-24 01:27:57'),
(28, 'Educated', 'Tara Westover', '978-0-399-59050-4', '2018-02-20', 5, 4, NULL, NULL, NULL, 'Memoir', '2025-11-24 01:27:57'),
(29, 'Becoming', 'Michelle Obama', '978-1-524-76313-8', '2018-11-13', 7, 5, NULL, NULL, 'uploads/covers/pasted-image-1764143875195_1764143877_4740.png', 'Biography', '2025-11-24 01:27:57'),
(30, 'The Da Vinci Code', 'Dan Brown', '978-0-385-50420-1', '2003-03-18', 8, 6, NULL, NULL, NULL, 'Mystery', '2025-11-24 01:27:57'),
(31, 'Gone Girl', 'Gillian Flynn', '978-0-307-58836-4', '2012-06-05', 4, 2, NULL, NULL, 'uploads/covers/pasted-image-1764146271189_1764146272_3409.png', 'Thriller', '2025-11-24 01:27:57'),
(32, 'The Girl with the Dragon Tattoo', 'Stieg Larsson', '978-0-307-45454-8', '2005-08-01', 5, 5, NULL, NULL, NULL, 'Mystery', '2025-11-24 01:27:57'),
(33, 'And Then There Were None', 'Agatha Christie', '978-0-06-207348-5', '1939-11-06', 6, 6, NULL, NULL, 'uploads/covers/pasted-image-1764149715313_1764149717_2225.png', 'Mystery', '2025-11-24 01:27:57'),
(34, 'The Shining', 'Stephen King', '978-0-385-12167-5', '1977-01-28', 4, 4, NULL, NULL, NULL, 'Horror', '2025-11-24 01:27:57'),
(35, 'It', 'Stephen King', '978-0-450-41130-6', '1986-09-15', 5, 5, NULL, NULL, NULL, 'Horror', '2025-11-24 01:27:57'),
(36, 'Dracula', 'Bram Stoker', '978-0-14-143984-6', '1897-05-26', 4, 4, NULL, NULL, NULL, 'Horror', '2025-11-24 01:27:57'),
(37, 'Frankenstein', 'Mary Shelley', '978-0-486-28211-4', '1818-01-01', 5, 5, NULL, NULL, NULL, 'Horror', '2025-11-24 01:27:57'),
(38, 'The Hunger Games', 'Suzanne Collins', '978-0-439-02348-1', '2008-09-14', 9, 9, NULL, NULL, NULL, 'Young Adult', '2025-11-24 01:27:57'),
(39, 'Divergent', 'Veronica Roth', '978-0-06-202402-2', '2011-04-25', 6, 6, NULL, NULL, NULL, 'Young Adult', '2025-11-24 01:27:57'),
(40, 'The Fault in Our Stars', 'John Green', '978-0-14-242417-9', '2012-01-10', 7, 7, NULL, NULL, NULL, 'Young Adult', '2025-11-24 01:27:57'),
(41, 'The Book Thief', 'Markus Zusak', '978-0-375-84220-7', '2005-09-01', 5, 5, NULL, NULL, NULL, 'Historical Fiction', '2025-11-24 01:27:57'),
(42, 'All the Light We Cannot See', 'Anthony Doerr', '978-1-476-74621-9', '2014-05-06', 6, 6, NULL, NULL, 'https://covers.openlibrary.org/b/id/8228691-M.jpg', 'Historical Fiction', '2025-11-24 01:27:57'),
(43, 'The Nightingale', 'Kristin Hannah', '978-0-312-57721-5', '2015-02-03', 5, 5, NULL, NULL, NULL, 'Historical Fiction', '2025-11-24 01:27:57'),
(44, 'Where the Crawdads Sing', 'Delia Owens', '978-0-735-21932-3', '2018-08-14', 8, 8, NULL, NULL, NULL, 'Fiction', '2025-11-24 01:27:57'),
(45, 'The Silent Patient', 'Alex Michaelides', '978-1-250-30169-7', '2019-02-05', 6, 6, NULL, NULL, NULL, 'Thriller', '2025-11-24 01:27:57'),
(46, 'The Seven Husbands of Evelyn Hugo', 'Taylor Jenkins Reid', '978-1-501-13922-0', '2017-06-13', 7, 7, NULL, NULL, NULL, 'Fiction', '2025-11-24 01:27:57'),
(47, 'Normal People', 'Sally Rooney', '978-0-451-49990-8', '2018-08-28', 5, 4, NULL, NULL, NULL, 'Romance', '2025-11-24 01:27:57'),
(49, 'AAAA', 'AAAA', 'AAAA', NULL, 12, 12, NULL, NULL, 'uploads/covers/pasted-image-1764145742597_1764145744_6635.png', 'AAAA', '2025-11-26 04:45:10'),
(53, 'a', 'd', 'd', NULL, 1, 1, 3, 'A', 'uploads/covers/pasted-image-1764144545319_1764144546_6807.png', 'd', '2025-11-26 08:09:06'),
(58, 'LaLalend', 'Асхат Аманкулов', '1234567', NULL, 13, 13, 3, '0', 'uploads/covers/pasted-image-1764146352858_1764146354_2398.png', 'Science Fiction', '2025-11-26 08:39:14');

-- --------------------------------------------------------

--
-- Table structure for table `book_reviews`
--

CREATE TABLE `book_reviews` (
  `id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `reviewer_name` varchar(255) DEFAULT NULL,
  `reviewer_role` enum('admin','librarian','user') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `book_reviews`
--

INSERT INTO `book_reviews` (`id`, `book_id`, `member_id`, `rating`, `comment`, `reviewer_name`, `reviewer_role`, `created_at`) VALUES
(6, 3, 2, 5, 'wqertyuiop', NULL, NULL, '2025-11-24 02:43:10'),
(7, 33, 2, 5, 'dfdfd', NULL, NULL, '2025-11-24 02:43:39'),
(8, 3, 17, 5, 'ffasdfasdf', NULL, NULL, '2025-11-24 02:51:14');

-- --------------------------------------------------------

--
-- Table structure for table `borrowings`
--

CREATE TABLE `borrowings` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `borrow_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('active','returned') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowings`
--

INSERT INTO `borrowings` (`id`, `member_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `status`) VALUES
(2, 2, 3, '2025-11-18', '2025-12-02', NULL, 'active'),
(5, 2, 2, '2025-11-19', '2025-12-03', '2025-11-26', 'returned'),
(22, 7, 18, '2025-11-23', '2025-12-07', NULL, 'active'),
(23, 8, 19, '2025-11-24', '2025-12-08', '2025-11-26', 'returned'),
(24, 9, 20, '2025-11-20', '2025-12-04', NULL, 'active'),
(25, 10, 21, '2025-11-21', '2025-12-05', NULL, 'active'),
(26, 11, 22, '2025-11-19', '2025-12-03', NULL, 'active'),
(27, 12, 23, '2025-11-23', '2025-12-07', '2025-11-26', 'returned'),
(28, 13, 24, '2025-11-22', '2025-12-06', NULL, 'active'),
(29, 14, 25, '2025-11-18', '2025-12-02', '2025-11-26', 'returned'),
(31, 18, 27, '2025-11-21', '2025-12-05', NULL, 'active'),
(32, 19, 28, '2025-11-23', '2025-12-07', NULL, 'active'),
(33, 20, 29, '2025-11-20', '2025-12-04', NULL, 'active'),
(35, 7, 31, '2025-11-19', '2025-12-03', NULL, 'active'),
(37, 7, 2, '2025-10-10', '2025-10-24', '2025-10-23', 'returned'),
(38, 8, 3, '2025-09-25', '2025-10-09', '2025-10-08', 'returned'),
(39, 9, 4, '2025-10-30', '2025-11-13', '2025-11-12', 'returned'),
(40, 10, 5, '2025-10-05', '2025-10-19', '2025-10-18', 'returned'),
(41, 11, 6, '2025-10-15', '2025-10-29', '2025-10-28', 'returned'),
(42, 12, 7, '2025-10-20', '2025-11-03', '2025-11-02', 'returned'),
(43, 13, 8, '2025-09-30', '2025-10-14', '2025-10-13', 'returned'),
(44, 14, 32, '2025-11-04', '2025-11-18', '2025-11-17', 'returned'),
(46, 18, 34, '2025-10-22', '2025-11-05', '2025-11-04', 'returned'),
(47, 19, 35, '2025-10-07', '2025-10-21', '2025-10-20', 'returned'),
(48, 20, 36, '2025-11-02', '2025-11-16', '2025-11-15', 'returned'),
(59, 7, 18, '2025-11-23', '2025-12-07', NULL, 'active'),
(60, 8, 19, '2025-11-24', '2025-12-08', '2025-11-26', 'returned'),
(61, 9, 20, '2025-11-20', '2025-12-04', NULL, 'active'),
(62, 10, 21, '2025-11-21', '2025-12-05', NULL, 'active'),
(63, 11, 22, '2025-11-19', '2025-12-03', NULL, 'active'),
(64, 12, 23, '2025-11-23', '2025-12-07', '2025-11-26', 'returned'),
(65, 13, 24, '2025-11-22', '2025-12-06', NULL, 'active'),
(66, 14, 25, '2025-11-18', '2025-12-02', NULL, 'active'),
(68, 18, 27, '2025-11-21', '2025-12-05', NULL, 'active'),
(69, 19, 28, '2025-11-23', '2025-12-07', '2025-11-26', 'returned'),
(70, 20, 29, '2025-11-20', '2025-12-04', NULL, 'active'),
(72, 7, 31, '2025-11-19', '2025-12-03', NULL, 'active'),
(74, 7, 2, '2025-10-10', '2025-10-24', '2025-10-23', 'returned'),
(75, 8, 3, '2025-09-25', '2025-10-09', '2025-10-08', 'returned'),
(76, 9, 4, '2025-10-30', '2025-11-13', '2025-11-12', 'returned'),
(77, 10, 5, '2025-10-05', '2025-10-19', '2025-10-18', 'returned'),
(78, 11, 6, '2025-10-15', '2025-10-29', '2025-10-28', 'returned'),
(79, 12, 7, '2025-10-20', '2025-11-03', '2025-11-02', 'returned'),
(80, 13, 8, '2025-09-30', '2025-10-14', '2025-10-13', 'returned'),
(81, 14, 32, '2025-11-04', '2025-11-18', '2025-11-17', 'returned'),
(83, 18, 34, '2025-10-22', '2025-11-05', '2025-11-04', 'returned'),
(84, 19, 35, '2025-10-07', '2025-10-21', '2025-10-20', 'returned'),
(85, 20, 36, '2025-11-02', '2025-11-16', '2025-11-15', 'returned'),
(88, 7, 39, '2025-09-05', '2025-09-19', '2025-09-18', 'returned'),
(89, 7, 40, '2025-09-20', '2025-10-04', '2025-10-03', 'returned'),
(90, 8, 41, '2025-09-15', '2025-09-29', '2025-09-28', 'returned'),
(91, 9, 42, '2025-08-31', '2025-09-14', '2025-09-13', 'returned'),
(92, 10, 43, '2025-08-21', '2025-09-04', '2025-09-03', 'returned'),
(93, 2, 44, '2025-08-16', '2025-08-30', '2025-08-29', 'returned'),
(97, 16, 17, '2025-11-06', '2025-11-20', '2025-11-26', 'returned'),
(98, 17, 18, '2025-11-02', '2025-11-16', '2025-11-26', 'returned'),
(100, 2, 1, '2025-11-14', '2025-11-24', NULL, 'active'),
(102, 7, 3, '2025-11-04', '2025-11-14', NULL, 'active'),
(103, 29, 3, '2025-12-01', '2025-12-15', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `class` varchar(50) DEFAULT NULL,
  `group_name` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `avatar_url` varchar(512) DEFAULT NULL,
  `membership_date` date DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `name`, `first_name`, `last_name`, `class`, `group_name`, `email`, `phone`, `address`, `avatar_url`, `membership_date`, `status`, `created_at`) VALUES
(2, 'Jane Smith', NULL, NULL, NULL, NULL, 'jane@example.com', '555-0002', '456 Oak Avenue', NULL, '2025-11-21', 'active', '2025-11-21 07:39:44'),
(7, 'Michael Chen', NULL, NULL, NULL, NULL, NULL, NULL, '890 Cherry Lane, San Francisco', NULL, '2024-11-24', 'active', '2025-11-24 01:27:57'),
(8, 'Sarah Johnson', NULL, NULL, NULL, NULL, NULL, NULL, '234 River Road, Portland', NULL, '2025-08-24', 'active', '2025-11-24 01:27:57'),
(9, 'David Martinez', NULL, NULL, NULL, NULL, NULL, NULL, '456 Hill Street, Miami', NULL, '2025-03-24', 'active', '2025-11-24 01:27:57'),
(10, 'Lisa Anderson', NULL, NULL, NULL, NULL, NULL, NULL, '789 Lake Avenue, Chicago', NULL, '2023-11-24', 'active', '2025-11-24 01:27:57'),
(11, 'James Wilson', NULL, NULL, NULL, NULL, NULL, NULL, '321 Park Boulevard, Seattle', NULL, '2025-07-24', 'active', '2025-11-24 01:27:57'),
(12, 'Maria Garcia', NULL, NULL, NULL, NULL, NULL, NULL, '654 Ocean Drive, Los Angeles', NULL, '2025-01-24', 'active', '2025-11-24 01:27:57'),
(13, 'Robert Taylor', NULL, NULL, NULL, NULL, NULL, NULL, '987 Mountain View, Denver', NULL, '2025-06-24', 'active', '2025-11-24 01:27:57'),
(14, 'Jennifer Lee', NULL, NULL, NULL, NULL, NULL, NULL, '147 Garden Street, Boston', NULL, '2025-04-24', 'active', '2025-11-24 01:27:57'),
(16, 'Patricia Davis', NULL, NULL, NULL, NULL, NULL, NULL, '369 Forest Lane, Phoenix', NULL, '2024-12-24', 'active', '2025-11-24 01:27:57'),
(17, 'Christopher Miller', NULL, NULL, NULL, NULL, NULL, NULL, '741 Desert Avenue, Las Vegas', 'uploads/avatars/avatar_17_1764146689_4566.png', '2025-09-24', 'active', '2025-11-24 01:27:57'),
(18, 'Linda Rodriguez', NULL, NULL, NULL, NULL, NULL, NULL, '852 Harbor Way, San Diego', NULL, '2025-10-24', 'active', '2025-11-24 01:27:57'),
(19, 'Daniel Hernandez', NULL, NULL, NULL, NULL, NULL, NULL, '963 Summit Drive, Salt Lake City', NULL, '2024-08-24', 'active', '2025-11-24 01:27:57'),
(20, 'Nancy Lopez', NULL, NULL, NULL, NULL, NULL, NULL, '159 Sunset Boulevard, Nashville', NULL, '2025-11-24', 'active', '2025-11-24 01:27:57'),
(21, 'Thomas Moore', NULL, NULL, NULL, NULL, NULL, NULL, '357 Maple Street, Memphis', NULL, '2025-05-24', 'inactive', '2025-11-24 01:27:57'),
(23, 'Mark White', NULL, NULL, NULL, NULL, NULL, NULL, '624 Pine Road, Atlanta', NULL, '2025-08-24', 'active', '2025-11-24 01:27:57'),
(24, 'Sandra Harris', NULL, NULL, NULL, NULL, NULL, NULL, '735 Birch Lane, Detroit', NULL, '2024-11-24', 'active', '2025-11-24 01:27:57'),
(25, 'Kevin Clark', NULL, NULL, NULL, NULL, NULL, NULL, '846 Spruce Way, Philadelphia', NULL, '2025-06-24', 'active', '2025-11-24 01:27:57'),
(26, 'Mirshad abi', NULL, NULL, '10B', NULL, NULL, NULL, NULL, NULL, '2025-11-24', 'active', '2025-11-24 02:17:01'),
(27, 'Phan Bui An Khang', 'Phan Bui An Khang', '', 'Year 13A - Oxford', 'INT', NULL, NULL, NULL, NULL, '2025-12-01', 'active', '2025-12-01 06:32:39'),
(28, 'Wahab Abdul Aamir', 'Wahab Abdul', 'Aamir', 'Year 10A - Oxford', 'INT', NULL, NULL, NULL, NULL, '2025-12-01', 'active', '2025-12-01 06:32:39'),
(29, 'Reyhan Abdulla', 'Reyhan', 'Abdulla', 'Year 9C - Venus', 'INT', NULL, NULL, NULL, NULL, '2025-12-01', 'active', '2025-12-01 06:32:39'),
(30, 'Surayya Abdulla', 'Surayya', 'Abdulla', 'Year 10A - Oxford', 'INT', NULL, NULL, NULL, NULL, '2025-12-01', 'active', '2025-12-01 06:32:39'),
(31, 'Tahta Cahaya Aishani AISHANI', 'Tahta Cahaya Aishani', 'AISHANI', 'Year 7A - Jupiter', 'INT', NULL, NULL, NULL, NULL, '2025-12-01', 'active', '2025-12-01 06:32:40'),
(32, 'Adil Akylbekov', 'Adil', 'Akylbekov', 'Year 7B - Mercury', 'INT', NULL, NULL, NULL, NULL, '2025-12-01', 'active', '2025-12-01 06:32:40'),
(33, 'Nayaka (Naya) Alifi Nareswari', 'Nayaka (Naya)', 'Alifi Nareswari', 'Year 12A - Oxford', 'INT', NULL, NULL, NULL, NULL, '2025-12-01', 'active', '2025-12-01 06:32:40'),
(34, 'Oudomxay (Winner) Atoulakoun', 'Oudomxay (Winner)', 'Atoulakoun', 'Year 10B - Stanford', 'HUM', NULL, NULL, NULL, NULL, '2025-12-01', 'active', '2025-12-01 06:32:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `isbn` (`isbn`);

--
-- Indexes for table `book_reviews`
--
ALTER TABLE `book_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `book_reviews`
--
ALTER TABLE `book_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `borrowings`
--
ALTER TABLE `borrowings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `book_reviews`
--
ALTER TABLE `book_reviews`
  ADD CONSTRAINT `book_reviews_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `book_reviews_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD CONSTRAINT `borrowings_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `borrowings_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
