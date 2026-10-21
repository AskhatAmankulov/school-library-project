-- ============================================================
-- Real user accounts (replaces the hardcoded demo_users.php)
-- Import this ONCE into the `library` database via phpMyAdmin.
-- Safe to run again: it skips rows that already exist.
-- ============================================================

-- Every account is linked to a row in `members`
-- (the profile, borrowings and reviews all hang off the member).
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','librarian','user') NOT NULL DEFAULT 'user',
  `member_id` int(11) NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`),
  UNIQUE KEY `uniq_email` (`email`),
  UNIQUE KEY `uniq_member` (`member_id`),
  CONSTRAINT `fk_users_member` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Member rows for the old demo accounts that had no member yet
INSERT IGNORE INTO `members` (`id`, `name`, `class`, `email`, `membership_date`, `status`) VALUES
(3, 'Librarian',     NULL,       'librarian@library.com', CURDATE(), 'active'),
(5, 'Demo User',     NULL,       'user@library.com',      CURDATE(), 'active'),
(6, 'Askhat',        'Year 10A', 'askhat@library.com',    CURDATE(), 'active');

-- Same usernames and passwords as before:
--   admin / admin123, librarian / librarian123, user / user123,
--   askhat, emma, liam, sophia, noah / 123
INSERT IGNORE INTO `users` (`username`, `email`, `password_hash`, `role`, `member_id`) VALUES
('admin',     'admin@library.com',     '$2y$10$DNC6kGIIs7ftVKcA6HQF1eNEosfzRNtWQMVZxNWccVfrE5nC25shC', 'admin',     2),
('librarian', 'librarian@library.com', '$2y$10$cCe5/Ixcz/g4e.orDZmMS.BMn5NgQkyKjc12PMMoCRforuZO20NMW', 'librarian', 3),
('user',      'user@library.com',      '$2y$10$dbyEn6Yvlu.pbAAzjcdkcOZYttJMbSqgPoDzHj83nUZzI0.HpDX3m', 'user',      5),
('askhat',    'askhat@library.com',    '$2y$10$gZJd2tOx1yD/0Bc8xbybT.FvUF5ENSghClL35AyC4LUf1wi70kkGe', 'user',      6),
('emma',      'emma@library.com',      '$2y$10$gZJd2tOx1yD/0Bc8xbybT.FvUF5ENSghClL35AyC4LUf1wi70kkGe', 'user',      7),
('liam',      'liam@library.com',      '$2y$10$gZJd2tOx1yD/0Bc8xbybT.FvUF5ENSghClL35AyC4LUf1wi70kkGe', 'user',      8),
('sophia',    'sophia@library.com',    '$2y$10$gZJd2tOx1yD/0Bc8xbybT.FvUF5ENSghClL35AyC4LUf1wi70kkGe', 'user',      9),
('noah',      'noah@library.com',      '$2y$10$gZJd2tOx1yD/0Bc8xbybT.FvUF5ENSghClL35AyC4LUf1wi70kkGe', 'user',     10);
