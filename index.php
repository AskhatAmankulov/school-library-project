<?php
session_start();
include 'auth_check.php';
include 'config.php';
$page_title = 'Dashboard';
include 'includes/header.php';

// Get statistics from database
$total_books = 0;
$total_members = 0;
$active_borrowings = 0;
$overdue_count = 0;

// Student personal statistics
$student_total_borrowed = 0;
$student_currently_reading = 0;
$student_books_returned = 0;
$student_reading_streak = 0;
$student_favorite_genre = 'Not available';
$student_recent_books = [];
$student_avatar = null;
$student_name = $_SESSION['username'] ?? '';

if (!$db_error && $conn) {
    $result = $conn->query("SELECT COUNT(*) as count FROM books");
    if ($result) $total_books = $result->fetch_assoc()['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM members WHERE status = 'active'");
    if ($result) $total_members = $result->fetch_assoc()['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'active'");
    if ($result) $active_borrowings = $result->fetch_assoc()['count'];
    
    $result = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE status = 'active' AND due_date < CURDATE()");
    if ($result) $overdue_count = $result->fetch_assoc()['count'];
    
    // Get student personal statistics if user is not admin
    if (isset($_SESSION['user_id']) && !is_staff()) {
        $user_id = (int)$_SESSION['user_id'];
        
        // Get student avatar
        $result = $conn->query("SELECT name, avatar_url FROM members WHERE id = $user_id");
        if ($result && $result->num_rows > 0) {
            $me = $result->fetch_assoc();
            $student_avatar = $me['avatar_url'];
            $student_name = $me['name'];
        }
        
        // Total books borrowed
        $result = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE member_id = $user_id");
        if ($result) $student_total_borrowed = $result->fetch_assoc()['count'];
        
        // Currently reading
        $result = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE member_id = $user_id AND status = 'active'");
        if ($result) $student_currently_reading = $result->fetch_assoc()['count'];
        
        // Books returned
        $result = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE member_id = $user_id AND status = 'returned'");
        if ($result) $student_books_returned = $result->fetch_assoc()['count'];
        
        // Reading streak (consecutive months with at least one book)
        $result = $conn->query("SELECT COUNT(DISTINCT DATE_FORMAT(borrow_date, '%Y-%m')) as streak 
                               FROM borrowings 
                               WHERE member_id = $user_id 
                               AND borrow_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)");
        if ($result) $student_reading_streak = $result->fetch_assoc()['streak'];
        
        // Favorite genre (most borrowed category)
        $result = $conn->query("SELECT b.category, COUNT(*) as count 
                               FROM borrowings br 
                               JOIN books b ON br.book_id = b.id 
                               WHERE br.member_id = $user_id AND b.category IS NOT NULL AND b.category != ''
                               GROUP BY b.category 
                               ORDER BY count DESC 
                               LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $student_favorite_genre = $result->fetch_assoc()['category'];
        }
        
        // Books on loan first (soonest due), then the latest returned ones
        $result = $conn->query("SELECT b.title, b.author, b.cover_url, br.borrow_date, br.due_date, br.return_date, br.status,
                                       DATEDIFF(br.due_date, CURDATE()) AS days_left
                               FROM borrowings br
                               JOIN books b ON br.book_id = b.id
                               WHERE br.member_id = $user_id
                               ORDER BY (br.status = 'active') DESC, br.due_date ASC, br.return_date DESC
                               LIMIT 6");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $student_recent_books[] = $row;
            }
        }
    }
}
?>

<?php
// Flash messages (e.g. "You do not have permission to open that page.")
foreach (['success_message' => ['alert-success', 'check-circle'], 'error_message' => ['alert-danger', 'alert']] as $key => [$cls, $ic]):
    if (isset($_SESSION[$key])): ?>
        <div class="alert <?php echo $cls; ?>"><?php echo icon($ic, 16) . htmlspecialchars($_SESSION[$key]); ?></div>
        <?php unset($_SESSION[$key]);
    endif;
endforeach;
?>

<?php if (!is_staff()): ?>
    <?php $first_name = explode(' ', trim($student_name))[0]; ?>
    <div class="page-header">
        <div class="page-header-person">
            <span class="avatar avatar-lg">
                <?php if (!empty($student_avatar)): ?>
                    <img src="<?php echo htmlspecialchars($student_avatar); ?>" alt="">
                <?php else: ?>
                    <?php echo htmlspecialchars(strtoupper(substr($first_name, 0, 1))); ?>
                <?php endif; ?>
            </span>
            <div>
                <h1>Hi, <?php echo htmlspecialchars($first_name); ?></h1>
                <p>
                    <?php if ($student_currently_reading > 0): ?>
                        You have <?php echo $student_currently_reading; ?> book<?php echo $student_currently_reading > 1 ? 's' : ''; ?> on loan.
                    <?php else: ?>
                        You have no books on loan right now.
                    <?php endif; ?>
                    <?php if ($student_favorite_genre !== 'Not available'): ?>
                        Your favorite genre is <?php echo htmlspecialchars($student_favorite_genre); ?>.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <a href="books.php" class="btn btn-primary"><?php echo icon('search', 16); ?>Find a book</a>
    </div>

    <div class="stats-strip">
        <div class="stat">
            <div class="stat-label"><?php echo icon('book', 15); ?>Borrowed</div>
            <div class="stat-value"><?php echo $student_total_borrowed; ?></div>
            <div class="stat-note">books in total</div>
        </div>
        <div class="stat">
            <div class="stat-label"><?php echo icon('book-open', 15); ?>Reading now</div>
            <div class="stat-value"><?php echo $student_currently_reading; ?></div>
            <div class="stat-note">on loan</div>
        </div>
        <div class="stat">
            <div class="stat-label"><?php echo icon('check-circle', 15); ?>Returned</div>
            <div class="stat-value"><?php echo $student_books_returned; ?></div>
            <div class="stat-note">books finished</div>
        </div>
        <div class="stat">
            <div class="stat-label"><?php echo icon('calendar', 15); ?>Active months</div>
            <div class="stat-value"><?php echo $student_reading_streak; ?></div>
            <div class="stat-note">of the last 6</div>
        </div>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2><?php echo icon('book-open', 17); ?>Your books</h2>
            <a href="profile.php" class="btn btn-outline btn-small">Full history</a>
        </div>
        <?php if (count($student_recent_books) > 0): ?>
            <ul class="reading-list">
                <?php foreach ($student_recent_books as $book): ?>
                    <li class="reading-item">
                        <span class="cover">
                            <?php echo icon('book', 18); ?>
                            <?php if (!empty($book['cover_url'])): ?>
                                <img src="<?php echo htmlspecialchars($book['cover_url']); ?>" alt="" onerror="this.remove()">
                            <?php endif; ?>
                        </span>
                        <div class="info">
                            <div class="title"><?php echo htmlspecialchars($book['title']); ?></div>
                            <div class="author"><?php echo htmlspecialchars($book['author']); ?></div>
                        </div>
                        <?php
                        if ($book['status'] === 'returned') {
                            echo '<span class="badge badge-neutral">Returned ' . date('M j', strtotime($book['return_date'] ?? $book['due_date'])) . '</span>';
                        } elseif ((int)$book['days_left'] < 0) {
                            echo '<span class="badge badge-danger">' . icon('alert', 13) . abs((int)$book['days_left']) . ' days overdue</span>';
                        } elseif ((int)$book['days_left'] <= 3) {
                            $d = (int)$book['days_left'];
                            echo '<span class="badge badge-warning">' . icon('clock', 13) . ($d === 0 ? 'Due today' : 'Due in ' . $d . ' day' . ($d > 1 ? 's' : '')) . '</span>';
                        } else {
                            echo '<span class="badge badge-success">Due ' . date('M j', strtotime($book['due_date'])) . '</span>';
                        }
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty">
                <strong>No books yet</strong>
                Pick something from the catalogue and ask the librarian to check it out for you.
            </div>
        <?php endif; ?>
    </section>

<?php else: ?>

    <div class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p><?php echo date('l, j F Y'); ?></p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="books.php" class="btn btn-outline"><?php echo icon('plus', 16); ?>Add a book</a>
            <a href="borrowings.php" class="btn btn-primary"><?php echo icon('swap', 16); ?>Issue or return</a>
        </div>
    </div>

    <div class="stats-strip">
        <div class="stat">
            <div class="stat-label"><?php echo icon('book', 15); ?>Books</div>
            <div class="stat-value"><?php echo $total_books; ?></div>
            <div class="stat-note">titles in the catalogue</div>
        </div>
        <div class="stat">
            <div class="stat-label"><?php echo icon('users', 15); ?>Members</div>
            <div class="stat-value"><?php echo $total_members; ?></div>
            <div class="stat-note">active</div>
        </div>
        <div class="stat">
            <div class="stat-label"><?php echo icon('book-open', 15); ?>On loan</div>
            <div class="stat-value"><?php echo $active_borrowings; ?></div>
            <div class="stat-note">books out right now</div>
        </div>
        <div class="stat <?php echo $overdue_count > 0 ? 'is-alert' : ''; ?>">
            <div class="stat-label"><?php echo icon('alert', 15); ?>Overdue</div>
            <div class="stat-value"><?php echo $overdue_count; ?></div>
            <div class="stat-note">past the due date</div>
        </div>
    </div>

    <?php
    $overdue_query = "SELECT b.title, b.isbn, m.id AS member_id, m.name AS member_name, m.class,
                             br.borrow_date, br.due_date, DATEDIFF(CURDATE(), br.due_date) AS days_overdue
                      FROM borrowings br
                      JOIN books b ON br.book_id = b.id
                      JOIN members m ON br.member_id = m.id
                      WHERE br.status = 'active' AND br.due_date < CURDATE()
                      ORDER BY days_overdue DESC";
    $overdue_result = mysqli_query($conn, $overdue_query);
    $overdue_rows = $overdue_result ? mysqli_fetch_all($overdue_result, MYSQLI_ASSOC) : [];
    ?>

    <section class="panel">
        <div class="panel-header">
            <h2><?php echo icon('clock', 17); ?>Overdue books <span class="count"><?php echo count($overdue_rows); ?></span></h2>
            <a href="borrowings.php" class="btn btn-outline btn-small">All loans</a>
        </div>
        <?php if (count($overdue_rows) > 0): ?>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Student</th>
                            <th class="hide-sm">Borrowed</th>
                            <th class="hide-sm">Due</th>
                            <th class="num">Late by</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overdue_rows as $row):
                            $days = (int)$row['days_overdue'];
                            $level = $days <= 3 ? 'is-mild' : ($days <= 14 ? 'is-moderate' : 'is-severe');
                            $fill = min(100, round($days / 30 * 100));
                        ?>
                            <tr>
                                <td>
                                    <div class="cell-main"><?php echo htmlspecialchars($row['title']); ?></div>
                                    <?php if (!empty($row['isbn'])): ?>
                                        <div class="cell-sub hide-sm">ISBN <?php echo htmlspecialchars($row['isbn']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="cell-main" style="text-decoration: none;" href="profile.php?id=<?php echo (int)$row['member_id']; ?>"><?php echo htmlspecialchars($row['member_name']); ?></a>
                                    <?php if (!empty($row['class'])): ?>
                                        <div class="cell-sub"><?php echo htmlspecialchars($row['class']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="muted hide-sm"><?php echo date('M j, Y', strtotime($row['borrow_date'])); ?></td>
                                <td class="muted hide-sm"><?php echo date('M j, Y', strtotime($row['due_date'])); ?></td>
                                <td>
                                    <div class="overdue <?php echo $level; ?>">
                                        <span class="overdue-bar"><span style="width: <?php echo $fill; ?>%"></span></span>
                                        <span class="overdue-days"><?php echo $days; ?> day<?php echo $days > 1 ? 's' : ''; ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="panel-footer">The bar fills up over 30 days. Remind these students to bring their books back.</div>
        <?php else: ?>
            <div class="empty">
                <?php echo icon('check-circle', 28); ?>
                <strong>Nothing overdue</strong>
                Every book on loan is still within its due date.
            </div>
        <?php endif; ?>
    </section>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
