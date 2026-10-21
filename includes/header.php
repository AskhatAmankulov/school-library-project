<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<?php
// Ensure session is available so we can detect user role for theming
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
$bodyClass = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'admin-theme' : '';
?>
<body class="<?php echo htmlspecialchars($bodyClass); ?>">
    <nav class="navbar">
        <div class="container">
            <div class="logo">📚 Library System</div>
            <ul class="nav-links">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="books.php">Books</a></li>
                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'librarian'], true)): ?>
                    <li><a href="members.php">Members</a></li>
                    <li><a href="borrowings.php">Borrowings</a></li>
                <?php endif; ?>
            </ul>
            <div class="user-menu">
                <?php
                if (isset($_SESSION['username'])) {
                    $user_id = $_SESSION['user_id'] ?? null;
                    $user_role = $_SESSION['role'] ?? 'user';
                    echo '<span class="user-info">👤 ' . htmlspecialchars($_SESSION['username']) . ' (' . htmlspecialchars($user_role) . ')</span>';
                    // Every account has a member profile now
                    if ($user_id) {
                        echo ' <a href="profile.php?id=' . $user_id . '" class="btn-profile" style="background: #6c5ce7; color: white; padding: 8px 15px; border-radius: 4px; text-decoration: none; margin-right: 10px;">My Profile</a>';
                    }
                    echo ' <a href="logout.php" class="btn-logout">Logout</a>';
                } else {
                    echo '<a href="login.php" class="btn-login-nav">Login</a>';
                }
                ?>
            </div>
        </div>
    </nav>
    <div class="container">
