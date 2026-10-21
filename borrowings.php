<?php
session_start();
include 'auth_check.php';
require_staff();
include 'config.php';

// ---------- Issue / return ----------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$db_error) {
    $action = $_POST['action'] ?? '';

    if ($action === 'borrow') {
        $member_id = (int)($_POST['member_id'] ?? 0);
        $book_id = (int)($_POST['book_id'] ?? 0);
        $borrow_date = $_POST['borrow_date'] ?? date('Y-m-d');
        $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+14 days'));

        $stmt = $conn->prepare("SELECT title, available_quantity FROM books WHERE id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$member_id || !$book) {
            $_SESSION['error_message'] = 'Choose a student and a book.';
        } elseif ($book['available_quantity'] <= 0) {
            $_SESSION['error_message'] = '"' . $book['title'] . '" has no copies left to lend.';
        } elseif (strtotime($due_date) <= strtotime($borrow_date)) {
            $_SESSION['error_message'] = 'The due date must be after the borrow date.';
        } else {
            $stmt = $conn->prepare("INSERT INTO borrowings (member_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->bind_param("iiss", $member_id, $book_id, $borrow_date, $due_date);
            if ($stmt->execute()) {
                $conn->query("UPDATE books SET available_quantity = available_quantity - 1 WHERE id = $book_id");
                $_SESSION['success_message'] = 'Issued "' . $book['title'] . '". Due ' . date('M j', strtotime($due_date)) . '.';
            } else {
                $_SESSION['error_message'] = 'Could not issue the book: ' . $conn->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'return') {
        $borrowing_id = (int)($_POST['borrowing_id'] ?? 0);

        // Only an active loan can be returned (a double click must not add a copy twice)
        $stmt = $conn->prepare("UPDATE borrowings SET status = 'returned', return_date = CURDATE() WHERE id = ? AND status = 'active'");
        $stmt->bind_param("i", $borrowing_id);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        if ($changed === 1) {
            $conn->query("UPDATE books b JOIN borrowings br ON br.book_id = b.id
                          SET b.available_quantity = b.available_quantity + 1 WHERE br.id = $borrowing_id");
            $_SESSION['success_message'] = 'Marked as returned.';
        } else {
            $_SESSION['error_message'] = 'This loan was already returned.';
        }
    }

    header("Location: borrowings.php" . (isset($_POST['filter']) ? '?filter=' . urlencode($_POST['filter']) : ''));
    exit();
}

$page_title = 'Borrowings';
include 'includes/header.php';

// ---------- Data ----------
$rows = [];
$counts = ['active' => 0, 'overdue' => 0, 'returned' => 0, 'all' => 0];
$due_this_week = 0;
$returned_today = 0;

if (!$db_error && $conn) {
    $result = $conn->query("SELECT br.id, br.borrow_date, br.due_date, br.return_date, br.status,
                                   b.title, b.author, m.id AS member_id, m.name AS member_name, m.class,
                                   DATEDIFF(CURDATE(), br.due_date) AS days_overdue
                            FROM borrowings br
                            JOIN books b ON br.book_id = b.id
                            JOIN members m ON br.member_id = m.id
                            ORDER BY (br.status = 'active') DESC, br.due_date ASC, br.return_date DESC");
    if ($result) {
        $rows = $result->fetch_all(MYSQLI_ASSOC);
    }
    foreach ($rows as $r) {
        $counts['all']++;
        if ($r['status'] === 'active') {
            $counts['active']++;
            if ($r['days_overdue'] > 0) {
                $counts['overdue']++;
            } elseif ($r['days_overdue'] >= -7) {
                $due_this_week++;
            }
        } else {
            $counts['returned']++;
            if ($r['return_date'] === date('Y-m-d')) {
                $returned_today++;
            }
        }
    }
}

$filter = $_GET['filter'] ?? 'active';
if (!isset($counts[$filter])) {
    $filter = 'active';
}
?>

<div class="page-header">
    <div>
        <h1>Borrowings</h1>
        <p>Issue books to students and record returns.</p>
    </div>
    <button class="btn btn-primary" onclick="openBorrowModal()"><?php echo icon('plus', 16); ?>Issue a book</button>
</div>

<?php
foreach (['success_message' => ['alert-success', 'check-circle'], 'error_message' => ['alert-danger', 'alert']] as $key => [$cls, $ic]) {
    if (isset($_SESSION[$key])) {
        echo '<div class="alert ' . $cls . '">' . icon($ic, 16) . htmlspecialchars($_SESSION[$key]) . '</div>';
        unset($_SESSION[$key]);
    }
}
if ($db_error) {
    echo '<div class="alert alert-danger">' . icon('alert', 16) . 'Cannot connect to the database. Start MySQL in XAMPP.</div>';
}
?>

<div class="stats-strip">
    <div class="stat">
        <div class="stat-label"><?php echo icon('book-open', 15); ?>On loan</div>
        <div class="stat-value"><?php echo $counts['active']; ?></div>
        <div class="stat-note">books out right now</div>
    </div>
    <div class="stat <?php echo $counts['overdue'] > 0 ? 'is-alert' : ''; ?>">
        <div class="stat-label"><?php echo icon('alert', 15); ?>Overdue</div>
        <div class="stat-value"><?php echo $counts['overdue']; ?></div>
        <div class="stat-note">past the due date</div>
    </div>
    <div class="stat">
        <div class="stat-label"><?php echo icon('clock', 15); ?>Due this week</div>
        <div class="stat-value"><?php echo $due_this_week; ?></div>
        <div class="stat-note">in the next 7 days</div>
    </div>
    <div class="stat">
        <div class="stat-label"><?php echo icon('check-circle', 15); ?>Returned today</div>
        <div class="stat-value"><?php echo $returned_today; ?></div>
        <div class="stat-note"><?php echo date('M j'); ?></div>
    </div>
</div>

<section class="panel">
    <div class="toolbar">
        <div class="segmented" role="tablist" aria-label="Filter loans">
            <?php foreach (['active' => 'On loan', 'overdue' => 'Overdue', 'returned' => 'Returned', 'all' => 'All'] as $key => $label): ?>
                <button type="button" data-filter="<?php echo $key; ?>" class="<?php echo $filter === $key ? 'active' : ''; ?>" onclick="setFilter('<?php echo $key; ?>')">
                    <?php echo $label; ?> <span class="seg-count"><?php echo $counts[$key]; ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="search-input">
            <?php echo icon('search', 16); ?>
            <input type="search" id="loanSearch" placeholder="Search book or student" oninput="applyFilters()">
        </div>
    </div>

    <div class="table-scroll">
        <table class="data-table" id="loansTable">
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Student</th>
                    <th class="hide-sm">Borrowed</th>
                    <th>Due</th>
                    <th class="hide-sm">Status</th>
                    <th class="actions"><span class="hide-sm">Action</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    $is_overdue = $r['status'] === 'active' && $r['days_overdue'] > 0;
                    $days_left = -(int)$r['days_overdue'];
                    $search_text = strtolower($r['title'] . ' ' . $r['author'] . ' ' . $r['member_name'] . ' ' . $r['class']);
                ?>
                    <tr class="loan-row <?php echo $is_overdue ? 'is-overdue' : ''; ?>"
                        data-status="<?php echo $r['status']; ?>"
                        data-overdue="<?php echo $is_overdue ? '1' : '0'; ?>"
                        data-search="<?php echo htmlspecialchars($search_text); ?>">
                        <td>
                            <div class="cell-main"><?php echo htmlspecialchars($r['title']); ?></div>
                            <div class="cell-sub"><?php echo htmlspecialchars($r['author']); ?></div>
                        </td>
                        <td>
                            <a class="cell-main" style="text-decoration: none;" href="profile.php?id=<?php echo (int)$r['member_id']; ?>"><?php echo htmlspecialchars($r['member_name']); ?></a>
                            <?php if (!empty($r['class'])): ?>
                                <div class="cell-sub"><?php echo htmlspecialchars($r['class']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="muted hide-sm"><?php echo date('M j, Y', strtotime($r['borrow_date'])); ?></td>
                        <td>
                            <div class="nowrap"><?php echo date('M j, Y', strtotime($r['due_date'])); ?></div>
                            <?php if ($r['status'] === 'active'): ?>
                                <div class="cell-sub" style="color: <?php echo $is_overdue ? 'var(--danger)' : ($days_left <= 3 ? 'var(--warning)' : 'var(--muted)'); ?>">
                                    <?php
                                    if ($is_overdue) {
                                        echo (int)$r['days_overdue'] . ' day' . ($r['days_overdue'] > 1 ? 's' : '') . ' late';
                                    } elseif ($days_left === 0) {
                                        echo 'Due today';
                                    } else {
                                        echo 'in ' . $days_left . ' day' . ($days_left > 1 ? 's' : '');
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="hide-sm">
                            <?php if ($r['status'] === 'returned'): ?>
                                <span class="badge badge-neutral">Returned <?php echo date('M j', strtotime($r['return_date'])); ?></span>
                            <?php elseif ($is_overdue): ?>
                                <span class="badge badge-danger">Overdue</span>
                            <?php else: ?>
                                <span class="badge badge-success">On loan</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <?php if ($r['status'] === 'active'): ?>
                                <form method="POST" onsubmit="return confirm('Mark &quot;<?php echo htmlspecialchars(addslashes($r['title'])); ?>&quot; as returned?');">
                                    <input type="hidden" name="action" value="return">
                                    <input type="hidden" name="borrowing_id" value="<?php echo (int)$r['id']; ?>">
                                    <input type="hidden" name="filter" class="filter-field" value="<?php echo htmlspecialchars($filter); ?>">
                                    <button type="submit" class="btn btn-outline btn-small" title="Mark as returned"><?php echo icon('check', 14); ?><span class="hide-sm">Returned</span></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="empty" id="loansEmpty" style="display: none;">
        <strong>No loans here</strong>
        <span id="loansEmptyText">Try another filter or search.</span>
    </div>
</section>

<!-- Issue a book -->
<div id="borrowModal" class="modal">
    <div class="modal-content has-parts modal-sm">
        <div class="modal-header">
            <h2><?php echo icon('book-open', 18); ?>Issue a book</h2>
            <button type="button" class="icon-button" onclick="closeBorrowModal()" aria-label="Close"><?php echo icon('x', 18); ?></button>
        </div>
        <form method="POST" action="" id="borrowForm" onsubmit="return validateBorrowForm()">
            <input type="hidden" name="action" value="borrow">
            <input type="hidden" name="filter" class="filter-field" value="<?php echo htmlspecialchars($filter); ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label for="memberSearch">Student</label>
                    <div class="combobox">
                        <input type="text" id="memberSearch" class="input" placeholder="Type a name or class" autocomplete="off"
                               onfocus="searchMembers()" oninput="clearSelection('member'); searchMembers()">
                        <div id="memberSearchResults" class="combo-results"></div>
                    </div>
                    <input type="hidden" name="member_id" id="member_id">
                    <div id="selectedMember" class="selection"><?php echo icon('check', 14); ?><span id="selectedMemberText"></span></div>
                </div>

                <div class="form-group">
                    <label for="bookSearch">Book</label>
                    <div class="combobox">
                        <input type="text" id="bookSearch" class="input" placeholder="Type a title or author" autocomplete="off"
                               onfocus="searchBooks()" oninput="clearSelection('book'); searchBooks()">
                        <div id="bookSearchResults" class="combo-results"></div>
                    </div>
                    <input type="hidden" name="book_id" id="book_id">
                    <div id="selectedBook" class="selection"><?php echo icon('check', 14); ?><span id="selectedBookText"></span></div>
                    <p class="form-hint">Only books with a free copy are listed.</p>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="borrow_date">Borrowed on</label>
                        <input type="date" name="borrow_date" id="borrow_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="due_date">Due on</label>
                        <input type="date" name="due_date" id="due_date" required value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>">
                    </div>
                </div>
                <p class="form-hint" id="formError" style="color: var(--danger); display: none;"></p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeBorrowModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Issue book</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentFilter = <?php echo json_encode($filter); ?>;
let allMembers = [];
let allBooks = [];

// ---------- Filters ----------
function setFilter(name) {
    currentFilter = name;
    document.querySelectorAll('.segmented button').forEach(b => b.classList.toggle('active', b.dataset.filter === name));
    document.querySelectorAll('.filter-field').forEach(f => f.value = name);
    history.replaceState(null, '', '?filter=' + name);
    applyFilters();
}

function applyFilters() {
    const q = document.getElementById('loanSearch').value.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('.loan-row').forEach(row => {
        const status = row.dataset.status;
        const overdue = row.dataset.overdue === '1';
        let show = currentFilter === 'all'
            || (currentFilter === 'active' && status === 'active')
            || (currentFilter === 'returned' && status === 'returned')
            || (currentFilter === 'overdue' && overdue);
        if (show && q) {
            show = row.dataset.search.includes(q);
        }
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('loansEmpty').style.display = visible ? 'none' : 'block';
    document.getElementById('loansTable').style.display = visible ? '' : 'none';
    document.getElementById('loansEmptyText').textContent = q
        ? 'Nothing matches "' + q + '".'
        : (currentFilter === 'overdue' ? 'Every book on loan is within its due date.' : 'Try another filter.');
}

// ---------- Issue modal ----------
function openBorrowModal() {
    document.getElementById('borrowForm').reset();
    clearSelection('member');
    clearSelection('book');
    document.getElementById('formError').style.display = 'none';
    document.getElementById('borrowModal').style.display = 'block';
    if (!allMembers.length) {
        fetch('get_all_members.php').then(r => r.json()).then(d => { allMembers = Array.isArray(d) ? d : []; }).catch(() => {});
    }
    fetch('get_all_books.php').then(r => r.json()).then(d => { allBooks = Array.isArray(d) ? d : []; }).catch(() => {});
    setTimeout(() => document.getElementById('memberSearch').focus(), 50);
}

function closeBorrowModal() {
    document.getElementById('borrowModal').style.display = 'none';
    hideResults();
}

function hideResults() {
    document.getElementById('memberSearchResults').style.display = 'none';
    document.getElementById('bookSearchResults').style.display = 'none';
}

function clearSelection(kind) {
    document.getElementById(kind + '_id').value = '';
    const box = document.getElementById(kind === 'member' ? 'selectedMember' : 'selectedBook');
    box.classList.remove('show');
}

function renderOptions(listId, items, render, onPick, emptyText) {
    const list = document.getElementById(listId);
    list.innerHTML = '';
    if (!items.length) {
        list.innerHTML = '<div class="combo-empty">' + esc(emptyText) + '</div>';
    } else {
        items.slice(0, 50).forEach(item => {
            const div = document.createElement('div');
            div.className = 'combo-option';
            div.innerHTML = render(item);
            div.onmousedown = e => { e.preventDefault(); onPick(item); };
            list.appendChild(div);
        });
    }
    list.style.display = 'block';
}

function searchMembers() {
    const q = document.getElementById('memberSearch').value.trim().toLowerCase();
    const items = allMembers.filter(m => !q || m.name.toLowerCase().includes(q) || (m.class || '').toLowerCase().includes(q));
    renderOptions('memberSearchResults', items,
        m => '<span class="cell-main">' + esc(m.name) + '</span><span class="cell-sub">' + esc(m.class || '') + '</span>',
        selectMember, 'No students match.');
}

function searchBooks() {
    const q = document.getElementById('bookSearch').value.trim().toLowerCase();
    const items = allBooks.filter(b => !q || b.title.toLowerCase().includes(q) || b.author.toLowerCase().includes(q));
    renderOptions('bookSearchResults', items,
        b => '<span><span class="cell-main">' + esc(b.title) + '</span><br><span class="cell-sub">' + esc(b.author) + '</span></span>'
           + '<span class="badge badge-neutral">' + esc(b.available_quantity) + ' free</span>',
        selectBook, 'No available books match.');
}

function selectMember(m) {
    document.getElementById('member_id').value = m.id;
    document.getElementById('memberSearch').value = m.name;
    document.getElementById('selectedMemberText').textContent = m.name + (m.class ? ' · ' + m.class : '');
    document.getElementById('selectedMember').classList.add('show');
    hideResults();
}

function selectBook(b) {
    document.getElementById('book_id').value = b.id;
    document.getElementById('bookSearch').value = b.title;
    document.getElementById('selectedBookText').textContent = b.title + ' by ' + b.author;
    document.getElementById('selectedBook').classList.add('show');
    hideResults();
}

function validateBorrowForm() {
    const err = document.getElementById('formError');
    let msg = '';
    if (!document.getElementById('member_id').value) msg = 'Choose a student from the list.';
    else if (!document.getElementById('book_id').value) msg = 'Choose a book from the list.';
    else if (new Date(document.getElementById('due_date').value) <= new Date(document.getElementById('borrow_date').value)) msg = 'The due date must be after the borrow date.';
    err.textContent = msg;
    err.style.display = msg ? 'block' : 'none';
    return !msg;
}

// Keep the due date 14 days after the borrow date when the borrow date changes
document.getElementById('borrow_date').addEventListener('change', function () {
    const due = new Date(this.value);
    due.setDate(due.getDate() + 14);
    document.getElementById('due_date').value = due.toISOString().split('T')[0];
});

document.addEventListener('click', e => {
    if (!e.target.closest('.combobox')) hideResults();
});

applyFilters();
</script>

<?php include 'includes/footer.php'; ?>
