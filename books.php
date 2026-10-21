<?php
session_start();
include 'auth_check.php';
include 'config.php';
include 'includes/functions.php';

// Handle form submission BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$db_error && !is_staff()) {
    $_SESSION['error_message'] = 'Only the librarian or admin can add or edit books.';
    header("Location: books.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$db_error) {
    // Clear a leftover message from an earlier action, otherwise the insert below is skipped
    unset($_SESSION['error_message']);
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    if ($isbn === '') { $isbn = null; }
    $category = trim($_POST['category'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $shelf_number = isset($_POST['shelf_number']) && $_POST['shelf_number'] !== '' ? (int)trim($_POST['shelf_number']) : null;
    $shelf_row = trim($_POST['shelf_row'] ?? '');
    if ($shelf_row === '') { $shelf_row = null; }
    $cover_url = trim($_POST['cover_url'] ?? '');
    if ($cover_url === '') { $cover_url = null; }
    
    if (empty($title) || empty($author)) {
        $_SESSION['error_message'] = 'Title and Author are required!';
    } elseif ($too_long = length_error(['Title' => [$title, 255], 'Author' => [$author, 255], 'ISBN' => [$isbn, 20], 'Category' => [$category, 100], 'Row' => [$shelf_row, 10], 'Cover link' => [$cover_url, 512]])) {
        $_SESSION['error_message'] = $too_long;
    } else {
        // Handle cover image: uploaded file or URL
        $final_cover_url = null;
        // Ensure upload dir exists
        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'covers';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        // Process file upload if present
        if (isset($_FILES['cover_file']) && is_uploaded_file($_FILES['cover_file']['tmp_name'])) {
            $file = $_FILES['cover_file'];
            if ($file['error'] === UPLOAD_ERR_OK) {
                $maxSize = 5 * 1024 * 1024; // 5MB
                if ($file['size'] <= $maxSize) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
                    if (isset($allowed[$mime])) {
                        $ext = $allowed[$mime];
                        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', strtolower(pathinfo($file['name'], PATHINFO_FILENAME)));
                        $unique = $safeName . '_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
                        $dest = $uploadDir . DIRECTORY_SEPARATOR . $unique;
                        if (move_uploaded_file($file['tmp_name'], $dest)) {
                            // Save relative path for web access
                            $final_cover_url = 'uploads/covers/' . $unique;
                        } else {
                            $_SESSION['error_message'] = 'Failed to save uploaded image.';
                        }
                    } else {
                        $_SESSION['error_message'] = 'Unsupported image format. Please upload JPG, PNG, WEBP, or GIF.';
                    }
                } else {
                    $_SESSION['error_message'] = 'Image too large. Max size is 5MB.';
                }
            } else if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
                $_SESSION['error_message'] = 'Image upload error code: ' . $file['error'];
            }
        }
        // If no file, use URL if provided
        if (!$final_cover_url && !empty($cover_url)) {
            // Basic validation for URL
            if (preg_match('/^https?:\/\//i', $cover_url)) {
                $final_cover_url = $cover_url;
            } else {
                $_SESSION['error_message'] = 'Cover URL must start with http or https.';
            }
        }

        // ISBN must be unique
        if (!isset($_SESSION['error_message']) && $isbn !== null) {
            $chk = $conn->prepare("SELECT title FROM books WHERE isbn = ? LIMIT 1");
            $chk->bind_param("s", $isbn);
            $chk->execute();
            $other = $chk->get_result()->fetch_row();
            $chk->close();
            if ($other) {
                $_SESSION['error_message'] = 'ISBN ' . $isbn . ' already belongs to "' . $other[0] . '". Edit that book instead, or check the number.';
            }
        }
        if (!isset($_SESSION['error_message']) && $quantity < 0) {
            $_SESSION['error_message'] = 'Copies cannot be negative.';
        }

        // Insert into database - only if no errors so far
        if (!isset($_SESSION['error_message'])) {
            try {
                $stmt = $conn->prepare("INSERT INTO books (title, author, isbn, category, quantity, available_quantity, shelf_number, shelf_row, cover_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssiiiss", $title, $author, $isbn, $category, $quantity, $quantity, $shelf_number, $shelf_row, $final_cover_url);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success_message'] = 'Added "' . $title . '".';
            } catch (mysqli_sql_exception $e) {
                $_SESSION['error_message'] = 'Could not add the book: ' . $e->getMessage();
            }
        }
    }

    // The add dialog posts with fetch: answer in JSON so it can stay open on errors
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        if (isset($_SESSION['error_message'])) {
            echo json_encode(['success' => false, 'error' => $_SESSION['error_message']]);
            unset($_SESSION['error_message']);
        } else {
            echo json_encode(['success' => true]);
        }
        exit();
    }
    header("Location: books.php");
    exit();
}

$page_title = 'Books';
include 'includes/header.php';

// ---------- Data ----------
$books = [];
$categories = [];
if (!$db_error && $conn) {
    $res = $conn->query("SELECT b.*, COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(r.id) AS review_count
                         FROM books b
                         LEFT JOIN book_reviews r ON b.id = r.book_id
                         GROUP BY b.id
                         ORDER BY b.title ASC");
    if ($res) {
        $books = $res->fetch_all(MYSQLI_ASSOC);
    }
    foreach ($books as $b) {
        if (!empty($b['category'])) {
            $categories[$b['category']] = true;
        }
    }
    $categories = array_keys($categories);
    natcasesort($categories);
}
$available_titles = count(array_filter($books, fn($b) => $b['available_quantity'] > 0));
?>

<div class="page-header">
    <div>
        <h1>Books</h1>
        <p><?php echo count($books); ?> titles · <?php echo $available_titles; ?> with a copy on the shelf</p>
    </div>
    <?php if (is_staff()): ?>
        <div class="toolbar-group">
            <button class="btn btn-outline" onclick="openCSVImportModal()">Import CSV</button>
            <button class="btn btn-primary" onclick="openBookModal()"><?php echo icon('plus', 16); ?>Add book</button>
        </div>
    <?php endif; ?>
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

<div class="panel catalogue-bar">
    <div class="toolbar" style="border-bottom: none;">
        <div class="toolbar-group">
            <div class="search-input">
                <?php echo icon('search', 16); ?>
                <input type="search" id="searchInput" placeholder="Search title, author or ISBN" oninput="applyBookFilters()">
            </div>
            <select id="categoryFilter" class="select" onchange="applyBookFilters()">
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?php echo htmlspecialchars(mb_strtolower($c, 'UTF-8')); ?>"><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="availabilityFilter" class="select" onchange="applyBookFilters()">
                <option value="">Any availability</option>
                <option value="yes">On the shelf now</option>
                <option value="no">All copies out</option>
            </select>
            <select id="sortOrder" class="select" onchange="sortBooks()">
                <option value="title">Title A–Z</option>
                <option value="author">Author A–Z</option>
                <option value="rating">Highest rated</option>
                <option value="reviews">Most reviewed</option>
            </select>
        </div>
        <div class="segmented" aria-label="View">
            <button type="button" id="viewGridBtn" class="active" onclick="switchView('grid')">Grid</button>
            <button type="button" id="viewListBtn" onclick="switchView('list')">List</button>
        </div>
    </div>
    <?php if (is_admin()): ?>
        <div id="bulkDeleteControls" class="bulk-bar" style="display: none;">
            <label><input type="checkbox" id="selectAllBooks" onchange="toggleSelectAll()"> Select all shown</label>
            <span id="selectedCount" class="muted">0 selected</span>
            <button id="deleteSelectedBtn" class="btn btn-danger btn-small" onclick="deleteSelectedBooks()" disabled>Delete selected</button>
        </div>
    <?php endif; ?>
</div>

<div id="booksGrid" class="books is-grid">
    <?php foreach ($books as $book):
        $avg = round($book['avg_rating'], 1);
        $avail = (int)$book['available_quantity'];
        $qty = (int)$book['quantity'];
        $search = mb_strtolower($book['title'] . ' ' . $book['author'] . ' ' . $book['isbn'] . ' ' . $book['category'], 'UTF-8');
    ?>
        <article class="book-card" tabindex="0"
                 data-book-id="<?php echo (int)$book['id']; ?>"
                 data-title="<?php echo htmlspecialchars(mb_strtolower($book['title'], 'UTF-8')); ?>"
                 data-author="<?php echo htmlspecialchars(mb_strtolower($book['author'], 'UTF-8')); ?>"
                 data-search="<?php echo htmlspecialchars($search); ?>"
                 data-category="<?php echo htmlspecialchars(mb_strtolower($book['category'] ?? '', 'UTF-8')); ?>"
                 data-rating="<?php echo $avg; ?>"
                 data-reviews="<?php echo (int)$book['review_count']; ?>"
                 data-available="<?php echo $avail > 0 ? 'yes' : 'no'; ?>"
                 onclick="viewBookInfo(<?php echo (int)$book['id']; ?>)"
                 onkeydown="if (event.key === 'Enter') viewBookInfo(<?php echo (int)$book['id']; ?>)">
            <div class="book-cover">
                <?php echo icon('book', 28); ?>
                <?php if (!empty($book['cover_url'])): ?>
                    <img src="<?php echo htmlspecialchars($book['cover_url']); ?>" alt="" loading="lazy" onerror="this.remove()">
                <?php endif; ?>
                <?php if (is_admin()): ?>
                    <input type="checkbox" class="book-select-checkbox" data-book-id="<?php echo (int)$book['id']; ?>"
                           aria-label="Select <?php echo htmlspecialchars($book['title']); ?>"
                           onclick="event.stopPropagation(); updateBulkDeleteUI();">
                <?php endif; ?>
            </div>
            <div class="book-body">
                <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                <p class="book-author"><?php echo htmlspecialchars($book['author']); ?></p>
                <div class="book-meta">
                    <?php if ($book['review_count'] > 0): ?>
                        <span class="book-rating"><?php echo icon('star', 13, 'filled'); ?><?php echo number_format($avg, 1); ?> <span class="muted">(<?php echo (int)$book['review_count']; ?>)</span></span>
                    <?php else: ?>
                        <span class="muted">No ratings</span>
                    <?php endif; ?>
                    <?php if ($avail > 0): ?>
                        <span class="availability yes"><?php echo $avail; ?> of <?php echo $qty; ?> free</span>
                    <?php else: ?>
                        <span class="availability no">All out</span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<div id="noResultsMessage" class="panel empty" style="display: none;">
    <strong>No books match</strong>
    Try a different title, author or category.
</div>
<?php if (!$books && !$db_error): ?>
    <div class="panel empty">
        <strong>The catalogue is empty</strong>
        <?php echo is_staff() ? 'Add the first book or import a CSV file.' : 'Check back soon.'; ?>
    </div>
<?php endif; ?>

<!-- Book details -->
<div id="bookInfoModal" class="modal">
    <div class="modal-content has-parts modal-lg">
        <div class="modal-header">
            <h2 id="bookInfoTitle">Book</h2>
            <button type="button" class="icon-button" onclick="closeBookInfoModal()" aria-label="Close"><?php echo icon('x', 18); ?></button>
        </div>
        <div class="modal-body" id="bookInfoContent"></div>

        <div class="modal-body detail-section">
            <h3 class="section-title">Reviews <span class="muted" id="reviewsSummary"></span></h3>
            <form id="reviewForm" class="review-form" onsubmit="submitReview(event)">
                <input type="hidden" id="review_book_id" name="book_id">
                <div class="star-picker" role="radiogroup" aria-label="Your rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>">
                        <label for="star<?php echo $i; ?>" title="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>"><?php echo icon('star', 22); ?></label>
                    <?php endfor; ?>
                </div>
                <textarea id="comment" name="comment" rows="2" class="input" placeholder="What did you think? (optional)"></textarea>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button type="submit" class="btn btn-primary btn-small">Post review</button>
                    <span id="reviewMsg" class="form-hint" style="margin: 0;"></span>
                </div>
            </form>
            <div id="reviewsContent"></div>
        </div>
    </div>
</div>

<?php if (is_staff()): ?>
<!-- Add / edit book -->
<div id="bookModal" class="modal">
    <div class="modal-content has-parts">
        <div class="modal-header">
            <h2 id="bookModalTitle">Add book</h2>
            <button type="button" class="icon-button" onclick="closeBookModal()" aria-label="Close"><?php echo icon('x', 18); ?></button>
        </div>
        <form id="bookForm" onsubmit="saveBook(event)" enctype="multipart/form-data">
            <input type="hidden" id="edit_book_id" name="book_id" value="">
            <input type="hidden" id="cover_source" name="cover_source" value="upload">
            <div class="modal-body">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="author">Author</label>
                        <input type="text" id="author" name="author" required>
                    </div>
                    <div class="form-group">
                        <label for="isbn">ISBN <span class="muted">(optional)</span></label>
                        <input type="text" id="isbn" name="isbn">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" list="categoryList">
                        <datalist id="categoryList">
                            <?php foreach ($categories as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label for="quantity">Copies</label>
                        <input type="number" id="quantity" name="quantity" required min="0" value="1">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="shelf_number">Shelf</label>
                        <input type="number" id="shelf_number" name="shelf_number" min="0" placeholder="e.g. 2">
                    </div>
                    <div class="form-group">
                        <label for="shelf_row">Row</label>
                        <input type="text" id="shelf_row" name="shelf_row" maxlength="5" placeholder="e.g. B">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label>Cover</label>
                    <div class="segmented" style="margin-bottom: 12px;">
                        <button type="button" class="active" data-src="upload" onclick="setCoverSource('upload')">Upload a file</button>
                        <button type="button" data-src="url" onclick="setCoverSource('url')">Use a link</button>
                    </div>
                    <div id="coverUploadContainer">
                        <input type="file" id="cover_file" name="cover_file" accept="image/*" class="input">
                        <div id="pasteZone" class="paste-zone" tabindex="0" style="margin-top: 10px;">Or click here and paste an image (Ctrl+V)</div>
                    </div>
                    <div id="coverUrlContainer" style="display: none;">
                        <input type="url" id="cover_url" name="cover_url" class="input" placeholder="https://example.com/cover.jpg">
                    </div>
                    <div id="coverPreview" class="cover-preview" style="display: none;">
                        <img id="coverPreviewImg" src="" alt="Cover preview">
                    </div>
                </div>
                <p class="form-hint" id="bookFormError" style="color: var(--danger); display: none;"></p>
            </div>
            <div class="modal-actions">
                <?php if (is_admin()): ?>
                    <button type="button" id="deleteBookBtn" class="btn btn-ghost-danger" onclick="deleteBook()" style="display: none; margin-right: auto;">Delete book</button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline" onclick="closeBookModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="bookSubmit">Add book</button>
            </div>
        </form>
    </div>
</div>

<!-- Import CSV -->
<div id="csvImportModal" class="modal">
    <div class="modal-content has-parts modal-sm">
        <div class="modal-header">
            <h2>Import books from CSV</h2>
            <button type="button" class="icon-button" onclick="closeCSVImportModal()" aria-label="Close"><?php echo icon('x', 18); ?></button>
        </div>
        <form id="csvImportForm" onsubmit="importCSV(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label for="csvFile">CSV file</label>
                    <input type="file" id="csvFile" name="csvFile" accept=".csv" required class="input">
                </div>
                <div class="alert alert-info" style="display: block; margin-bottom: 0;">
                    Columns, in this order: <strong>title, author, isbn, category, quantity, shelf_number, shelf_row</strong>.
                    Books whose ISBN is already in the catalogue are skipped.
                    <div style="margin-top: 8px;"><a href="#" onclick="downloadCSVTemplate(); return false;">Download a template</a></div>
                </div>
                <div id="importResults" style="display: none; margin-top: 14px;"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeCSVImportModal()" id="importCancel">Cancel</button>
                <button type="submit" class="btn btn-primary" id="importSubmit">Import</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
const isAdmin = <?php echo is_admin() ? 'true' : 'false'; ?>;
const isStaff = <?php echo is_staff() ? 'true' : 'false'; ?>;
const ICON_BOOK = <?php echo json_encode(icon('book', 32)); ?>;
const ICON_STAR = <?php echo json_encode(icon('star', 14)); ?>;
const ICON_STAR_FILLED = <?php echo json_encode(icon('star', 14, 'filled')); ?>;

function starsHtml(rating) {
    let out = '<span class="stars">';
    for (let i = 1; i <= 5; i++) out += i <= Math.round(rating) ? ICON_STAR_FILLED : ICON_STAR;
    return out + '</span>';
}

function fmtDate(d) {
    if (!d) return '—';
    const dt = new Date(d.replace(' ', 'T'));
    return dt.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

// ---------- View, filters, sort ----------
function switchView(view) {
    const grid = document.getElementById('booksGrid');
    grid.classList.toggle('is-grid', view === 'grid');
    grid.classList.toggle('is-list', view === 'list');
    document.getElementById('viewGridBtn').classList.toggle('active', view === 'grid');
    document.getElementById('viewListBtn').classList.toggle('active', view === 'list');
    try { localStorage.setItem('booksViewMode', view); } catch (e) {}
}

function applyBookFilters() {
    const q = document.getElementById('searchInput').value.trim().toLowerCase();
    const cat = document.getElementById('categoryFilter').value;
    const avail = document.getElementById('availabilityFilter').value;
    let visible = 0;
    document.querySelectorAll('.book-card').forEach(card => {
        const show = (!q || card.dataset.search.includes(q))
            && (!cat || card.dataset.category === cat)
            && (!avail || card.dataset.available === avail);
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const total = document.querySelectorAll('.book-card').length;
    document.getElementById('noResultsMessage').style.display = total && !visible ? 'block' : 'none';
    if (isAdmin) updateBulkDeleteUI();
}

function sortBooks() {
    const by = document.getElementById('sortOrder').value;
    const grid = document.getElementById('booksGrid');
    const cards = Array.from(grid.querySelectorAll('.book-card'));
    cards.sort((a, b) => {
        if (by === 'author') return a.dataset.author.localeCompare(b.dataset.author);
        if (by === 'rating') return (parseFloat(b.dataset.rating) - parseFloat(a.dataset.rating)) || a.dataset.title.localeCompare(b.dataset.title);
        if (by === 'reviews') return (parseInt(b.dataset.reviews) - parseInt(a.dataset.reviews)) || a.dataset.title.localeCompare(b.dataset.title);
        return a.dataset.title.localeCompare(b.dataset.title);
    });
    cards.forEach(c => grid.appendChild(c));
}

// ---------- Book details ----------
function viewBookInfo(bookId) {
    const content = document.getElementById('bookInfoContent');
    document.getElementById('bookInfoTitle').textContent = 'Book';
    content.innerHTML = '<p class="muted">Loading…</p>';
    document.getElementById('reviewsContent').innerHTML = '';
    document.getElementById('reviewsSummary').textContent = '';
    document.getElementById('reviewForm').reset();
    document.getElementById('reviewMsg').textContent = '';
    document.getElementById('review_book_id').value = bookId;
    document.getElementById('bookInfoModal').style.display = 'block';

    fetch('get_book.php?book_id=' + bookId)
        .then(r => r.json())
        .then(book => {
            if (book.error) { content.innerHTML = '<p style="color: var(--danger);">' + esc(book.error) + '</p>'; return; }
            document.getElementById('bookInfoTitle').textContent = book.title;

            const row = book.shelf_row && book.shelf_row !== '0' ? book.shelf_row : '';
            let where = 'Not set';
            if (book.shelf_number || row) {
                where = [book.shelf_number ? 'Shelf ' + book.shelf_number : '', row ? 'row ' + row : ''].filter(Boolean).join(', ');
            }
            const avail = parseInt(book.available_quantity);
            const cover = '<div class="detail-cover">' + ICON_BOOK
                + (book.cover_url ? '<img src="' + esc(book.cover_url) + '" alt="" decoding="async" onerror="this.remove()">' : '') + '</div>';

            let html = '<div class="detail">' + cover + '<div class="detail-info">';
            html += '<p class="detail-author">' + esc(book.author) + '</p>';
            html += '<p style="margin: 6px 0 16px;">' + (avail > 0
                ? '<span class="badge badge-success">' + avail + ' of ' + esc(book.quantity) + ' copies on the shelf</span>'
                : '<span class="badge badge-danger">All ' + esc(book.quantity) + ' copies are out</span>') + '</p>';
            html += '<dl class="facts">';
            html += '<dt>Where</dt><dd>' + esc(where) + '</dd>';
            html += '<dt>Category</dt><dd>' + esc(book.category || '—') + '</dd>';
            html += '<dt>ISBN</dt><dd>' + esc(book.isbn || '—') + '</dd>';
            html += '</dl>';
            if (isStaff) {
                html += '<div style="margin-top: 16px;"><button class="btn btn-outline btn-small" onclick="editBook(' + book.id + ')">Edit book</button></div>';
            }
            html += '</div></div>';
            if (isStaff) {
                html += '<div class="detail-section-inline"><h3 class="section-title">On loan to</h3><div id="borrowersContent" class="muted">Loading…</div></div>';
            }
            content.innerHTML = html;
            if (isStaff) loadBorrowers(book.id);
        })
        .catch(() => { content.innerHTML = '<p style="color: var(--danger);">Could not load this book.</p>'; });

    loadReviews(bookId);
}

function closeBookInfoModal() {
    document.getElementById('bookInfoModal').style.display = 'none';
}

function loadBorrowers(bookId) {
    fetch('get_book_borrowers.php?book_id=' + bookId)
        .then(r => r.json())
        .then(rows => {
            const box = document.getElementById('borrowersContent');
            if (!box) return;
            if (!Array.isArray(rows) || !rows.length) { box.textContent = 'Nobody has this book right now.'; return; }
            const today = new Date(); today.setHours(0, 0, 0, 0);
            box.classList.remove('muted');
            box.innerHTML = '<ul class="plain-list">' + rows.map(r => {
                const late = new Date(r.due_date) < today;
                return '<li><span><a href="profile.php?id=' + parseInt(r.id) + '" class="cell-main" style="text-decoration: none;">' + esc(r.name) + '</a>'
                    + (r.class ? ' <span class="cell-sub">' + esc(r.class) + '</span>' : '') + '</span>'
                    + '<span class="' + (late ? 'badge badge-danger' : 'muted') + '">Due ' + fmtDate(r.due_date) + '</span></li>';
            }).join('') + '</ul>';
        });
}

function loadReviews(bookId) {
    const content = document.getElementById('reviewsContent');
    fetch('get_book_reviews.php?book_id=' + bookId + '&t=' + Date.now())
        .then(r => r.json())
        .then(data => {
            if (data.error) { content.innerHTML = '<p style="color: var(--danger);">' + esc(data.error) + '</p>'; return; }
            if (!data.length) {
                document.getElementById('reviewsSummary').textContent = '';
                content.innerHTML = '<p class="muted" style="margin-top: 14px;">No reviews yet. Be the first to rate it.</p>';
                return;
            }
            const avg = data.reduce((s, r) => s + parseInt(r.rating), 0) / data.length;
            document.getElementById('reviewsSummary').textContent = avg.toFixed(1) + ' average from ' + data.length;
            content.innerHTML = '<ul class="review-list" style="margin-top: 8px;">' + data.map(r =>
                '<li class="review" style="padding-left: 0; padding-right: 0;"><div class="review-head"><div><div class="cell-main">' + esc(r.name) + '</div>'
                + '<div class="cell-sub">' + fmtDate(r.created_at) + '</div></div>' + starsHtml(r.rating) + '</div>'
                + (r.comment ? '<p class="review-text">' + esc(r.comment) + '</p>' : '') + '</li>'
            ).join('') + '</ul>';
        })
        .catch(() => { content.innerHTML = '<p style="color: var(--danger);">Could not load reviews.</p>'; });
}

function submitReview(event) {
    event.preventDefault();
    const form = event.target;
    const msg = document.getElementById('reviewMsg');
    if (!form.querySelector('input[name="rating"]:checked')) {
        msg.style.color = 'var(--danger)';
        msg.textContent = 'Pick 1 to 5 stars first.';
        return false;
    }
    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    fetch('submit_review.php', { method: 'POST', body: new FormData(form) })
        .then(r => r.json())
        .then(data => {
            msg.style.color = data.success ? 'var(--success)' : 'var(--danger)';
            msg.textContent = data.success ? 'Thanks, your review is posted.' : (data.error || 'Could not post the review.');
            if (data.success) {
                form.reset();
                loadReviews(document.getElementById('review_book_id').value);
            }
        })
        .catch(() => { msg.style.color = 'var(--danger)'; msg.textContent = 'Could not post the review.'; })
        .finally(() => { btn.disabled = false; });
    return false;
}

<?php if (is_staff()): ?>
// ---------- Add / edit book ----------
let pastedFile = null;
const coverPrev = document.getElementById('coverPreview');
const coverPrevImg = document.getElementById('coverPreviewImg');
const pasteZone = document.getElementById('pasteZone');

function showCoverPreview(src) {
    coverPrevImg.src = src || '';
    coverPrev.style.display = src ? 'block' : 'none';
}

function setCoverSource(src) {
    document.getElementById('cover_source').value = src;
    document.querySelectorAll('#bookModal .segmented button').forEach(b => b.classList.toggle('active', b.dataset.src === src));
    document.getElementById('coverUploadContainer').style.display = src === 'upload' ? '' : 'none';
    document.getElementById('coverUrlContainer').style.display = src === 'url' ? '' : 'none';
}

function resetPasteZone() {
    pastedFile = null;
    pasteZone.classList.remove('done');
    pasteZone.textContent = 'Or click here and paste an image (Ctrl+V)';
}

function openBookModal() {
    document.getElementById('bookForm').reset();
    document.getElementById('edit_book_id').value = '';
    document.getElementById('bookModalTitle').textContent = 'Add book';
    document.getElementById('bookSubmit').textContent = 'Add book';
    document.getElementById('bookFormError').style.display = 'none';
    const del = document.getElementById('deleteBookBtn');
    if (del) del.style.display = 'none';
    resetPasteZone();
    setCoverSource('upload');
    showCoverPreview(null);
    document.getElementById('bookModal').style.display = 'block';
    setTimeout(() => document.getElementById('title').focus(), 50);
}

function closeBookModal() {
    document.getElementById('bookModal').style.display = 'none';
}

function editBook(bookId) {
    closeBookInfoModal();
    openBookModal();
    document.getElementById('bookModalTitle').textContent = 'Edit book';
    document.getElementById('bookSubmit').textContent = 'Save changes';
    const del = document.getElementById('deleteBookBtn');
    if (del) del.style.display = '';
    fetch('get_book.php?book_id=' + bookId)
        .then(r => r.json())
        .then(data => {
            if (data.error) { alert(data.error); closeBookModal(); return; }
            document.getElementById('edit_book_id').value = data.id;
            document.getElementById('title').value = data.title || '';
            document.getElementById('author').value = data.author || '';
            document.getElementById('isbn').value = data.isbn || '';
            document.getElementById('category').value = data.category || '';
            document.getElementById('quantity').value = data.quantity ?? 1;
            document.getElementById('shelf_number').value = data.shelf_number ?? '';
            document.getElementById('shelf_row').value = data.shelf_row && data.shelf_row !== '0' ? data.shelf_row : '';
            document.getElementById('cover_url').value = data.cover_url || '';
            if (data.cover_url) showCoverPreview(data.cover_url);
        })
        .catch(() => { alert('Could not load this book.'); closeBookModal(); });
}

function saveBook(event) {
    event.preventDefault();
    const bookId = document.getElementById('edit_book_id').value;
    const formData = new FormData(event.target);
    if (pastedFile && document.getElementById('cover_source').value === 'upload') {
        formData.set('cover_file', pastedFile);
    }
    const err = document.getElementById('bookFormError');
    const btn = document.getElementById('bookSubmit');
    btn.disabled = true;

    const request = bookId
        ? fetch('update_book.php', { method: 'POST', body: formData }).then(r => r.json())
        : (formData.append('ajax', '1'), fetch('books.php', { method: 'POST', body: formData }).then(r => r.json()));

    request
        .then(data => {
            if (data.success) { window.location.reload(); return; }
            err.textContent = data.error || 'Could not save the book.';
            err.style.display = 'block';
        })
        .catch(() => { err.textContent = 'Could not save the book.'; err.style.display = 'block'; })
        .finally(() => { btn.disabled = false; });
    return false;
}

document.getElementById('cover_file').addEventListener('change', e => {
    const file = e.target.files?.[0];
    resetPasteZone();
    if (!file) return showCoverPreview(null);
    const reader = new FileReader();
    reader.onload = () => showCoverPreview(reader.result);
    reader.readAsDataURL(file);
});

document.getElementById('cover_url').addEventListener('input', e => showCoverPreview(e.target.value.trim()));

pasteZone.addEventListener('paste', e => {
    e.preventDefault();
    const item = [...(e.clipboardData?.items || [])].find(i => i.type.startsWith('image'));
    if (!item) return;
    const blob = item.getAsFile();
    pastedFile = new File([blob], 'pasted-image-' + Date.now() + '.png', { type: blob.type });
    const reader = new FileReader();
    reader.onload = () => {
        showCoverPreview(reader.result);
        pasteZone.classList.add('done');
        pasteZone.textContent = 'Image pasted. Save to use it as the cover.';
    };
    reader.readAsDataURL(blob);
});

function deleteBook() {
    const bookId = document.getElementById('edit_book_id').value;
    const title = document.getElementById('title').value;
    if (!bookId || !confirm('Delete "' + title + '"? Its loans and reviews are removed too. This cannot be undone.')) return;
    fetch('delete_book.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ book_id: bookId }) })
        .then(r => r.json())
        .then(data => data.success ? window.location.reload() : alert(data.error || 'Could not delete the book.'))
        .catch(() => alert('Could not delete the book.'));
}

// ---------- CSV import ----------
function openCSVImportModal() {
    document.getElementById('csvImportForm').reset();
    document.getElementById('importResults').style.display = 'none';
    document.getElementById('importSubmit').style.display = '';
    document.getElementById('importCancel').textContent = 'Cancel';
    document.getElementById('csvImportModal').style.display = 'block';
}

function closeCSVImportModal() {
    document.getElementById('csvImportModal').style.display = 'none';
}

function importCSV(event) {
    event.preventDefault();
    const file = document.getElementById('csvFile').files[0];
    const out = document.getElementById('importResults');
    const show = (cls, html) => { out.className = 'alert ' + cls; out.style.display = 'block'; out.innerHTML = html; };
    if (!file || !file.name.toLowerCase().endsWith('.csv')) return show('alert-danger', 'Choose a .csv file.');
    if (file.size > 10 * 1024 * 1024) return show('alert-danger', 'The file is larger than 10 MB.');

    const btn = document.getElementById('importSubmit');
    btn.disabled = true;
    btn.textContent = 'Importing…';
    const formData = new FormData();
    formData.append('csvFile', file);
    fetch('import_csv.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return show('alert-danger', esc(data.error || 'Import failed.'));
            let html = '<div><strong>Imported ' + data.imported + ' book' + (data.imported == 1 ? '' : 's') + '.</strong>';
            if (data.skipped > 0) html += ' Skipped ' + data.skipped + '.';
            if (data.errorDetails && data.errorDetails.length) {
                html += '<ul style="margin: 6px 0 0 18px;">' + data.errorDetails.slice(0, 8).map(e => '<li>' + esc(e) + '</li>').join('') + '</ul>';
            }
            show(data.imported > 0 ? 'alert-success' : 'alert-info', html + '</div>');
            btn.style.display = 'none';
            const cancel = document.getElementById('importCancel');
            cancel.textContent = 'Done';
            cancel.onclick = () => location.reload();
        })
        .catch(() => show('alert-danger', 'Import failed. Check the file and try again.'))
        .finally(() => { btn.disabled = false; btn.textContent = 'Import'; });
}

function downloadCSVTemplate() {
    const csv = 'title,author,isbn,category,quantity,shelf_number,shelf_row\n'
        + 'The Great Gatsby,F. Scott Fitzgerald,9780743273565,Fiction,5,2,A\n'
        + 'To Kill a Mockingbird,Harper Lee,9780061120084,Fiction,3,2,B\n';
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    link.download = 'books_import_template.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
}
<?php endif; ?>

<?php if (is_admin()): ?>
// ---------- Bulk delete (admin) ----------
function visibleCheckboxes() {
    return [...document.querySelectorAll('.book-select-checkbox')].filter(cb => cb.closest('.book-card').style.display !== 'none');
}

function updateBulkDeleteUI() {
    const checked = document.querySelectorAll('.book-select-checkbox:checked').length;
    const visible = visibleCheckboxes();
    document.getElementById('bulkDeleteControls').style.display = checked ? 'flex' : 'none';
    document.getElementById('selectedCount').textContent = checked + ' selected';
    document.getElementById('deleteSelectedBtn').disabled = !checked;
    const all = document.getElementById('selectAllBooks');
    all.checked = visible.length > 0 && visible.every(cb => cb.checked);
    all.indeterminate = checked > 0 && !all.checked;
    document.querySelectorAll('.book-card').forEach(c => c.classList.toggle('is-selected', !!c.querySelector('.book-select-checkbox:checked')));
}

function toggleSelectAll() {
    const on = document.getElementById('selectAllBooks').checked;
    visibleCheckboxes().forEach(cb => { cb.checked = on; });
    updateBulkDeleteUI();
}

function deleteSelectedBooks() {
    const ids = [...document.querySelectorAll('.book-select-checkbox:checked')].map(cb => parseInt(cb.dataset.bookId));
    if (!ids.length || !confirm('Delete ' + ids.length + ' book' + (ids.length === 1 ? '' : 's') + '? Their loans and reviews are removed too. This cannot be undone.')) return;
    fetch('delete_books.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ book_ids: ids }) })
        .then(r => r.json())
        .then(data => data.success ? location.reload() : alert(data.error || 'Could not delete the books.'))
        .catch(() => alert('Could not delete the books.'));
}
<?php endif; ?>

// Restore the saved view
try { if (localStorage.getItem('booksViewMode') === 'list') switchView('list'); } catch (e) {}
</script>

<?php include 'includes/footer.php'; ?>
