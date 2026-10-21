<?php
session_start();
include 'config.php';

// Real numbers and a peek at the shelf (nothing is made up if the database is empty)
$stats = ['books' => 0, 'students' => 0, 'reviews' => 0];
$shelf = [];
if (!$db_error && $conn) {
    $stats['books'] = (int)$conn->query("SELECT COUNT(*) FROM books")->fetch_row()[0];
    $stats['students'] = (int)$conn->query("SELECT COUNT(*) FROM members WHERE status = 'active'")->fetch_row()[0];
    $stats['reviews'] = (int)$conn->query("SELECT COUNT(*) FROM book_reviews")->fetch_row()[0];
    $res = $conn->query("SELECT b.title, b.author, b.cover_url, b.available_quantity, b.quantity,
                                COALESCE(AVG(r.rating), 0) AS avg_rating
                         FROM books b
                         LEFT JOIN book_reviews r ON r.book_id = b.id
                         GROUP BY b.id
                         ORDER BY COUNT(r.id) DESC, b.title
                         LIMIT 4");
    if ($res) {
        $shelf = $res->fetch_all(MYSQLI_ASSOC);
    }
}

$page_title = 'Welcome';
$hide_nav = true;
include 'includes/header.php';
$logged_in = isset($_SESSION['user_id']);
?>

<header class="landing-top">
    <span class="logo">
        <span class="logo-mark"><?php echo icon('library', 17); ?></span>
        School Library
    </span>
    <?php if ($logged_in): ?>
        <a href="index.php" class="btn btn-outline btn-small">Open the library</a>
    <?php else: ?>
        <a href="login.php" class="btn btn-outline btn-small">Log in</a>
    <?php endif; ?>
</header>

<section class="landing-hero">
    <div class="landing-copy">
        <h1>The school library, open on any screen.</h1>
        <p>Look up a book, see whether a copy is on the shelf and where to find it, and keep track of what you've borrowed and when it's due.</p>
        <div class="landing-actions">
            <?php if ($logged_in): ?>
                <a href="index.php" class="btn btn-primary">Go to your dashboard</a>
            <?php else: ?>
                <a href="register.php" class="btn btn-primary">Create a student account</a>
                <a href="login.php" class="btn btn-outline">Log in</a>
            <?php endif; ?>
        </div>
        <p class="landing-stats">
            <span><strong><?php echo $stats['books']; ?></strong> titles</span>
            <span><strong><?php echo $stats['students']; ?></strong> readers</span>
            <span><strong><?php echo $stats['reviews']; ?></strong> reviews</span>
        </p>
    </div>

    <?php if ($shelf): ?>
        <div class="landing-shelf panel" aria-label="Most reviewed books">
            <div class="panel-header"><h2>Most reviewed right now</h2></div>
            <ul class="reading-list">
                <?php foreach ($shelf as $b): ?>
                    <li class="reading-item">
                        <span class="cover">
                            <?php echo icon('book', 18); ?>
                            <?php if (!empty($b['cover_url'])): ?>
                                <img src="<?php echo htmlspecialchars($b['cover_url']); ?>" alt="" loading="lazy" decoding="async" onerror="this.remove()">
                            <?php endif; ?>
                        </span>
                        <div class="info">
                            <div class="title"><?php echo htmlspecialchars($b['title']); ?></div>
                            <div class="author"><?php echo htmlspecialchars($b['author']); ?></div>
                        </div>
                        <?php if ($b['available_quantity'] > 0): ?>
                            <span class="badge badge-success"><?php echo (int)$b['available_quantity']; ?> on the shelf</span>
                        <?php else: ?>
                            <span class="badge badge-neutral">All out</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</section>

<section class="landing-columns">
    <div>
        <h2>For students</h2>
        <ul class="landing-list">
            <li><?php echo icon('search', 18); ?><span><strong>Find a book</strong> by title, author or category, and see the shelf and row it sits on.</span></li>
            <li><?php echo icon('clock', 18); ?><span><strong>Know your due dates.</strong> Your dashboard shows what you have and when it goes back.</span></li>
            <li><?php echo icon('star', 18); ?><span><strong>Rate what you read</strong> so classmates can pick their next book.</span></li>
        </ul>
    </div>
    <div>
        <h2>For the librarian</h2>
        <ul class="landing-list">
            <li><?php echo icon('swap', 18); ?><span><strong>Issue and return</strong> in two clicks, with copies counted for you.</span></li>
            <li><?php echo icon('alert', 18); ?><span><strong>See overdue books</strong> at a glance, with who has them and how late they are.</span></li>
            <li><?php echo icon('users', 18); ?><span><strong>Import a class list</strong> or a book list from a CSV file instead of typing it in.</span></li>
        </ul>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
