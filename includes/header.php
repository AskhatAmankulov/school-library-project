<?php
// Shared page header + top navigation.
// Optional before including:  $page_title = 'Books';  $hide_nav = true; (login/register)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
include_once __DIR__ . '/icons.php';

$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? null;
$is_staff_nav = in_array($role, ['admin', 'librarian'], true);
$role_labels = ['admin' => 'Admin', 'librarian' => 'Librarian', 'user' => 'Student'];

// Avatar for the user chip (only if a DB connection is already open)
$nav_avatar = null;
if (!empty($_SESSION['user_id']) && isset($pdo_conn)) {
    try {
        $st = $pdo_conn->prepare("SELECT avatar_url FROM members WHERE id = ?");
        $st->execute([(int)$_SESSION['user_id']]);
        $nav_avatar = $st->fetchColumn() ?: null;
    } catch (Exception $e) {
        $nav_avatar = null;
    }
}

$nav_items = [
    ['index.php',      'Dashboard',  'dashboard', true],
    ['books.php',      'Books',      'book-open', true],
    ['members.php',    'Members',    'users',     $is_staff_nav],
    ['borrowings.php', 'Borrowings', 'swap',      $is_staff_nav],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' · ' : ''; ?>School Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php if (empty($hide_nav) && isset($_SESSION['username'])): ?>
    <nav class="navbar">
        <div class="container">
            <a class="logo" href="index.php">
                <span class="logo-mark"><?php echo icon('library', 17); ?></span>
                School Library
            </a>
            <ul class="nav-links">
                <?php foreach ($nav_items as [$href, $label, $ic, $visible]): ?>
                    <?php if ($visible): ?>
                        <li>
                            <a href="<?php echo $href; ?>" class="<?php echo $current_page === $href ? 'active' : ''; ?>">
                                <?php echo icon($ic, 16) . htmlspecialchars($label); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <div class="user-menu">
                <a class="user-chip" href="profile.php" title="My profile">
                    <span class="avatar">
                        <?php if ($nav_avatar): ?>
                            <img src="<?php echo htmlspecialchars($nav_avatar); ?>" alt="" loading="lazy" decoding="async" onerror="this.remove()">
                        <?php else: ?>
                            <?php echo htmlspecialchars(strtoupper(substr($_SESSION['username'], 0, 1))); ?>
                        <?php endif; ?>
                    </span>
                    <span class="user-chip-text">
                        <span class="user-chip-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <span class="user-chip-role"><?php echo $role_labels[$role] ?? 'Student'; ?></span>
                    </span>
                </a>
                <a class="icon-button" href="logout.php" title="Log out" aria-label="Log out"><?php echo icon('log-out', 18); ?></a>
            </div>
        </div>
    </nav>
<?php endif; ?>
    <main class="container">
