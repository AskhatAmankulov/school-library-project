<?php
session_start();
include 'auth_check.php';
include 'config.php';

$role = $_SESSION['role'] ?? 'user';
$is_staff = in_array($role, ['admin', 'librarian'], true);

// ---------- Change own password ----------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $stmt = $pdo_conn->prepare("SELECT id, password_hash FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['account_id'] ?? 0]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        $_SESSION['error_message'] = 'Account not found. Please log out and log in again.';
    } elseif (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error_message'] = 'All password fields are required.';
    } elseif (!password_verify($current_password, $account['password_hash'])) {
        $_SESSION['error_message'] = 'Current password is incorrect.';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error_message'] = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $_SESSION['error_message'] = 'New password must be at least 6 characters long.';
    } else {
        $pdo_conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                 ->execute([password_hash($new_password, PASSWORD_DEFAULT), $account['id']]);
        $_SESSION['success_message'] = 'Password changed successfully!';
    }

    header("Location: profile.php?id=" . (int)$_SESSION['user_id']);
    exit();
}

// ---------- Update avatar (admin: anyone, others: only themselves) ----------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_avatar'])) {
    $target_id = ($role === 'admin' && isset($_POST['member_id'])) ? (int)$_POST['member_id'] : (int)$_SESSION['user_id'];
    $new_avatar = null;

    if (($_POST['avatar_source'] ?? 'upload') === 'url') {
        $url = trim($_POST['avatar_url'] ?? '');
        if (filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url)) {
            $new_avatar = $url;
        } else {
            $_SESSION['error_message'] = 'Please enter a valid image link (http/https).';
        }
    } elseif (!empty($_FILES['avatar_file']['tmp_name']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $info = @getimagesize($_FILES['avatar_file']['tmp_name']);

        if ($_FILES['avatar_file']['size'] > 5 * 1024 * 1024) {
            $_SESSION['error_message'] = 'Image is too large (max 5MB).';
        } elseif (!$info || !isset($allowed[$info['mime']])) {
            $_SESSION['error_message'] = 'Only JPG, PNG, WEBP or GIF images are allowed.';
        } else {
            $dir = __DIR__ . '/uploads/avatars';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = 'avatar_' . $target_id . '_' . time() . '_' . rand(1000, 9999) . '.' . $allowed[$info['mime']];
            if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $dir . '/' . $filename)) {
                $new_avatar = 'uploads/avatars/' . $filename;
            } else {
                $_SESSION['error_message'] = 'Could not save the image.';
            }
        }
    } else {
        $_SESSION['error_message'] = 'Please choose an image.';
    }

    if ($new_avatar) {
        $pdo_conn->prepare("UPDATE members SET avatar_url = ? WHERE id = ?")->execute([$new_avatar, $target_id]);
        $_SESSION['success_message'] = 'Profile photo updated!';
    }

    header("Location: profile.php?id=" . $target_id);
    exit();
}

$page_title = 'Profile';
include 'includes/header.php';

// ---------- Which profile to show ----------
// Students can only see their own profile; admin/librarian can open anyone's.
$member_id = (int)$_SESSION['user_id'];
if ($is_staff && isset($_GET['id'])) {
    $member_id = (int)$_GET['id'];
}

$stmt = $pdo_conn->prepare(
    "SELECT id, name, class, status, membership_date, avatar_url FROM members WHERE id = ?"
);
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    echo '<div class="alert alert-danger">Member not found.</div>';
    include 'includes/footer.php';
    exit();
}

// ---------- Borrowing history and reviews ----------
$stmt = $conn->prepare(
    "SELECT b.title, b.author, br.borrow_date, br.due_date, br.return_date, br.status
     FROM borrowings br
     JOIN books b ON b.id = br.book_id
     WHERE br.member_id = ?
     ORDER BY br.borrow_date DESC, br.id DESC"
);
$stmt->bind_param('i', $member_id);
$stmt->execute();
$borrowing_result = $stmt->get_result();

$stmt = $conn->prepare(
    "SELECT b.title, b.author, r.rating, r.comment, r.created_at
     FROM book_reviews r
     JOIN books b ON b.id = r.book_id
     WHERE r.member_id = ?
     ORDER BY r.created_at DESC"
);
$stmt->bind_param('i', $member_id);
$stmt->execute();
$reviews_result = $stmt->get_result();
?>
<?php
$history = $borrowing_result->fetch_all(MYSQLI_ASSOC);
$reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
$on_loan = count(array_filter($history, fn($h) => $h['status'] === 'active'));
$is_own = $member_id == $_SESSION['user_id'];
$can_edit_photo = $role === 'admin' || $is_own;
?>

<?php if (!$is_own && $is_staff): ?>
    <p style="margin-bottom: 14px;"><a href="members.php" class="muted" style="text-decoration: none;">&larr; Members</a></p>
<?php endif; ?>

<?php
foreach (['success_message' => ['alert-success', 'check-circle'], 'error_message' => ['alert-danger', 'alert']] as $key => [$cls, $ic]) {
    if (isset($_SESSION[$key])) {
        echo '<div class="alert ' . $cls . '">' . icon($ic, 16) . htmlspecialchars($_SESSION[$key]) . '</div>';
        unset($_SESSION[$key]);
    }
}
?>

<div class="page-header">
    <div class="page-header-person">
        <div class="profile-avatar">
            <span class="avatar avatar-xl">
                <?php if (!empty($member['avatar_url'])): ?>
                    <img src="<?php echo htmlspecialchars($member['avatar_url']); ?>" alt="">
                <?php else: ?>
                    <?php echo htmlspecialchars(strtoupper(substr($member['name'], 0, 1))); ?>
                <?php endif; ?>
            </span>
            <?php if ($can_edit_photo): ?>
                <button type="button" class="avatar-edit" onclick="openAvatarModal()" title="Change photo" aria-label="Change photo"><?php echo icon('plus', 14); ?></button>
            <?php endif; ?>
        </div>
        <div>
            <h1><?php echo htmlspecialchars($member['name']); ?></h1>
            <p class="meta-line">
                <span><?php echo !empty($member['class']) ? htmlspecialchars($member['class']) : 'No class set'; ?></span>
                <span>Member since <?php echo date('F Y', strtotime($member['membership_date'] ?? 'now')); ?></span>
                <?php if ($member['status'] === 'active'): ?>
                    <span class="badge badge-success">Active</span>
                <?php else: ?>
                    <span class="badge badge-neutral">Inactive</span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php if ($is_own): ?>
        <button class="btn btn-outline" onclick="openPasswordModal()">Change password</button>
    <?php endif; ?>
</div>

<div class="stats-strip stats-3">
    <div class="stat">
        <div class="stat-label"><?php echo icon('book', 15); ?>Borrowed</div>
        <div class="stat-value"><?php echo count($history); ?></div>
        <div class="stat-note">books in total</div>
    </div>
    <div class="stat">
        <div class="stat-label"><?php echo icon('book-open', 15); ?>On loan</div>
        <div class="stat-value"><?php echo $on_loan; ?></div>
        <div class="stat-note">right now</div>
    </div>
    <div class="stat">
        <div class="stat-label"><?php echo icon('star', 15); ?>Reviews</div>
        <div class="stat-value"><?php echo count($reviews); ?></div>
        <div class="stat-note">books rated</div>
    </div>
</div>

<section class="panel" style="margin-bottom: 24px;">
    <div class="panel-header">
        <h2><?php echo icon('clock', 17); ?>Borrowing history <span class="count"><?php echo count($history); ?></span></h2>
    </div>
    <?php if ($history): ?>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Book</th>
                        <th class="hide-sm">Borrowed</th>
                        <th>Due</th>
                        <th class="hide-sm">Returned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h):
                        $late = $h['status'] === 'active' && strtotime($h['due_date']) < strtotime('today');
                    ?>
                        <tr class="<?php echo $late ? 'is-overdue' : ''; ?>">
                            <td>
                                <div class="cell-main"><?php echo htmlspecialchars($h['title']); ?></div>
                                <div class="cell-sub"><?php echo htmlspecialchars($h['author']); ?></div>
                            </td>
                            <td class="muted hide-sm nowrap"><?php echo date('M j, Y', strtotime($h['borrow_date'])); ?></td>
                            <td class="muted nowrap"><?php echo date('M j, Y', strtotime($h['due_date'])); ?></td>
                            <td class="muted hide-sm nowrap"><?php echo $h['return_date'] ? date('M j, Y', strtotime($h['return_date'])) : '—'; ?></td>
                            <td>
                                <?php if ($h['status'] === 'returned'): ?>
                                    <span class="badge badge-neutral">Returned</span>
                                <?php elseif ($late): ?>
                                    <span class="badge badge-danger">Overdue</span>
                                <?php else: ?>
                                    <span class="badge badge-success">On loan</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty">
            <strong>No books borrowed yet</strong>
            <?php echo $is_own ? 'Find a book in the catalogue and ask the librarian to check it out.' : 'This student has not borrowed anything yet.'; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="panel-header">
        <h2><?php echo icon('star', 17); ?>Reviews <span class="count"><?php echo count($reviews); ?></span></h2>
    </div>
    <?php if ($reviews): ?>
        <ul class="review-list">
            <?php foreach ($reviews as $rv): ?>
                <li class="review">
                    <div class="review-head">
                        <div>
                            <div class="cell-main"><?php echo htmlspecialchars($rv['title']); ?></div>
                            <div class="cell-sub"><?php echo htmlspecialchars($rv['author']); ?> · <?php echo date('M j, Y', strtotime($rv['created_at'])); ?></div>
                        </div>
                        <span class="stars" aria-label="<?php echo (int)$rv['rating']; ?> out of 5">
                            <?php for ($i = 1; $i <= 5; $i++) { echo icon('star', 15, $i <= $rv['rating'] ? 'filled' : ''); } ?>
                        </span>
                    </div>
                    <?php if (!empty($rv['comment'])): ?>
                        <p class="review-text"><?php echo nl2br(htmlspecialchars($rv['comment'])); ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="empty">
            <strong>No reviews yet</strong>
            <?php echo $is_own ? 'Rate a book from its page in the catalogue.' : 'This student has not reviewed any books.'; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($can_edit_photo): ?>
<!-- Change photo -->
<div id="avatarModal" class="modal">
    <div class="modal-content has-parts modal-sm">
        <div class="modal-header">
            <h2>Change photo</h2>
            <button type="button" class="icon-button" onclick="closeAvatarModal()" aria-label="Close"><?php echo icon('x', 18); ?></button>
        </div>
        <form id="avatarForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="update_avatar" value="1">
            <?php if ($role === 'admin'): ?>
                <input type="hidden" name="member_id" value="<?php echo (int)$member_id; ?>">
            <?php endif; ?>
            <input type="hidden" name="avatar_source" id="avatar_source" value="upload">
            <div class="modal-body">
                <div class="segmented" style="margin-bottom: 16px;">
                    <button type="button" class="active" data-src="upload" onclick="setAvatarSource('upload')">Upload a file</button>
                    <button type="button" data-src="url" onclick="setAvatarSource('url')">Use a link</button>
                </div>

                <div id="avatarUploadContainer">
                    <div class="form-group">
                        <input type="file" id="avatar_file" name="avatar_file" accept="image/*" class="input">
                        <p class="form-hint">JPG, PNG, WEBP or GIF, up to 5 MB.</p>
                    </div>
                    <div id="avatarPasteZone" class="paste-zone" tabindex="0">Or click here and paste an image (Ctrl+V)</div>
                </div>

                <div id="avatarUrlContainer" class="form-group" style="display: none;">
                    <input type="url" id="avatar_url" name="avatar_url" class="input" placeholder="https://example.com/photo.jpg">
                    <p class="form-hint">A direct link to an image.</p>
                </div>

                <div id="avatarPreview" class="photo-preview" style="display: none;">
                    <span class="avatar avatar-xl"><img id="avatarPreviewImg" src="" alt="Preview"></span>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeAvatarModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save photo</button>
            </div>
        </form>
    </div>
</div>

<script>
const avatarPrev = document.getElementById('avatarPreview');
const avatarPrevImg = document.getElementById('avatarPreviewImg');
const avatarPasteZone = document.getElementById('avatarPasteZone');
let avatarPastedFile = null;

function showPreview(src) {
    avatarPrevImg.src = src || '';
    avatarPrev.style.display = src ? 'flex' : 'none';
}

function setAvatarSource(src) {
    document.getElementById('avatar_source').value = src;
    document.querySelectorAll('#avatarModal .segmented button').forEach(b => b.classList.toggle('active', b.dataset.src === src));
    document.getElementById('avatarUploadContainer').style.display = src === 'upload' ? '' : 'none';
    document.getElementById('avatarUrlContainer').style.display = src === 'url' ? '' : 'none';
    showPreview(null);
}

document.getElementById('avatar_file').addEventListener('change', e => {
    const file = e.target.files?.[0];
    avatarPastedFile = null;
    if (!file) return showPreview(null);
    const reader = new FileReader();
    reader.onload = () => showPreview(reader.result);
    reader.readAsDataURL(file);
});

document.getElementById('avatar_url').addEventListener('input', e => showPreview(e.target.value.trim()));

avatarPasteZone.addEventListener('paste', e => {
    e.preventDefault();
    const item = [...(e.clipboardData?.items || [])].find(i => i.type.startsWith('image'));
    if (!item) return;
    const blob = item.getAsFile();
    avatarPastedFile = new File([blob], 'avatar-pasted-' + Date.now() + '.png', { type: blob.type });
    const reader = new FileReader();
    reader.onload = () => {
        showPreview(reader.result);
        avatarPasteZone.classList.add('done');
        avatarPasteZone.textContent = 'Image pasted. Click Save photo.';
    };
    reader.readAsDataURL(blob);
});

document.getElementById('avatarForm').addEventListener('submit', e => {
    if (avatarPastedFile && document.getElementById('avatar_source').value === 'upload') {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.set('avatar_file', avatarPastedFile);
        fetch('profile.php', { method: 'POST', body: formData })
            .then(() => window.location.reload())
            .catch(() => alert('Could not upload the photo.'));
    }
});

function openAvatarModal() {
    document.getElementById('avatarForm').reset();
    avatarPastedFile = null;
    avatarPasteZone.classList.remove('done');
    avatarPasteZone.textContent = 'Or click here and paste an image (Ctrl+V)';
    setAvatarSource('upload');
    document.getElementById('avatarModal').style.display = 'block';
}

function closeAvatarModal() {
    document.getElementById('avatarModal').style.display = 'none';
}
</script>
<?php endif; ?>

<?php if ($is_own): ?>
<!-- Change password -->
<div id="passwordModal" class="modal">
    <div class="modal-content has-parts modal-sm">
        <div class="modal-header">
            <h2>Change password</h2>
            <button type="button" class="icon-button" onclick="closePasswordModal()" aria-label="Close"><?php echo icon('x', 18); ?></button>
        </div>
        <form method="POST" action="" id="passwordForm">
            <input type="hidden" name="change_password" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label for="current_password">Current password</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
                    <p class="form-hint">At least 6 characters.</p>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="confirm_password">Repeat new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
                    <p class="form-hint" id="passwordError" style="color: var(--danger); display: none;">The new passwords don't match.</p>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closePasswordModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Change password</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPasswordModal() {
    document.getElementById('passwordForm').reset();
    document.getElementById('passwordError').style.display = 'none';
    document.getElementById('passwordModal').style.display = 'block';
    setTimeout(() => document.getElementById('current_password').focus(), 50);
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
}

document.getElementById('passwordForm').addEventListener('submit', e => {
    const mismatch = document.getElementById('new_password').value !== document.getElementById('confirm_password').value;
    document.getElementById('passwordError').style.display = mismatch ? 'block' : 'none';
    if (mismatch) e.preventDefault();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
