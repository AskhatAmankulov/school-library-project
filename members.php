<?php
session_start();
include 'auth_check.php';
require_staff();
include 'config.php';

// Ensure extra columns for admin features (first_name, last_name, group_name)
if (!$db_error && is_staff()) {
    if ($conn) {
        $needFirst = true; $needLast = true; $needGroup = true;
        if ($res = $conn->query("SHOW COLUMNS FROM members LIKE 'first_name'")) { if ($res->num_rows) $needFirst = false; }
        if ($res = $conn->query("SHOW COLUMNS FROM members LIKE 'last_name'")) { if ($res->num_rows) $needLast = false; }
        if ($res = $conn->query("SHOW COLUMNS FROM members LIKE 'group_name'")) { if ($res->num_rows) $needGroup = false; }
        if ($needFirst) { @$conn->query("ALTER TABLE members ADD COLUMN first_name VARCHAR(100) NULL AFTER name"); }
        if ($needLast)  { @$conn->query("ALTER TABLE members ADD COLUMN last_name VARCHAR(100) NULL AFTER first_name"); }
        if ($needGroup) { @$conn->query("ALTER TABLE members ADD COLUMN group_name VARCHAR(50) NULL AFTER class"); }
    }
}

// Admin-only: handle CSV import for students
if (!$db_error && is_staff() && isset($_POST['import_csv'])) {
    $summary = [
        'inserted' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => []
    ];
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = 'CSV upload failed. Please try again.';
        header('Location: members.php');
        exit;
    }
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
    $tmpName = $_FILES['csv_file']['tmp_name'];
    $dest = $uploadDir . DIRECTORY_SEPARATOR . ('import_' . time() . '_' . basename($_FILES['csv_file']['name']));
    if (!@move_uploaded_file($tmpName, $dest)) { $dest = $_FILES['csv_file']['tmp_name']; }

    if (($handle = fopen($dest, 'r')) !== false) {
        // Check optional columns
        $hasMembershipDate = false;
        $hasStatus = false;
        if ($conn) {
            if ($res = $conn->query("SHOW COLUMNS FROM members LIKE 'membership_date'")) { $hasMembershipDate = $res->num_rows > 0; }
            if ($res = $conn->query("SHOW COLUMNS FROM members LIKE 'status'")) { $hasStatus = $res->num_rows > 0; }
        }
        $header = fgetcsv($handle);
        if ($header === false) {
            $_SESSION['error_message'] = 'CSV is empty or unreadable.';
            fclose($handle);
            header('Location: members.php');
            exit;
        }
        // Map headers (case-insensitive, tolerant to spaces/underscores)
        $norm = function($s){ return strtolower(preg_replace('/[\s_]+/', '', trim((string)$s))); };
        $headerMap = [];
        foreach ($header as $idx => $col) { $headerMap[$norm($col)] = $idx; }
        $want = [
            'first' => ['firstname','first','givenname','first_name'],
            'last'  => ['lastname','last','surname','last_name','familyname'],
            'class' => ['class','grade','room','classroom'],
            'group' => ['group','groupname','group_name']
        ];
        $map = [];
        foreach ($want as $field => $aliases) {
            $foundIdx = null;
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $headerMap)) { $foundIdx = $headerMap[$alias]; break; }
            }
            if ($foundIdx === null) {
                $_SESSION['error_message'] = 'Missing required column for ' . ucfirst($field) . ' (tried: ' . implode(', ', $aliases) . ')';
                fclose($handle);
                header('Location: members.php');
                exit;
            }
            $map[$field] = $foundIdx;
        }
        // Process rows
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === 1 && trim($row[0]) === '') { continue; }
            $first = trim($row[$map['first']] ?? '');
            $last  = trim($row[$map['last']] ?? '');
            $class = trim($row[$map['class']] ?? '');
            $group = trim($row[$map['group']] ?? '');
            if ($first === '' && $last === '' && $class === '' && $group === '') { continue; }

            // Build name for backward compatibility
            $fullName = trim($first . ' ' . $last);
            if ($fullName === '') { $fullName = $first ?: $last; }

            // Try to find existing member: by first+last+class or by name+class
            $stmt = $conn->prepare("SELECT id FROM members WHERE ( (first_name = ? AND last_name = ?) OR name = ? ) AND class = ? LIMIT 1");
            $stmt->bind_param('ssss', $first, $last, $fullName, $class);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                // Update
                $stmt->bind_result($existingId);
                $stmt->fetch();
                $stmt->close();
                $u = $conn->prepare("UPDATE members SET name = ?, first_name = ?, last_name = ?, class = ?, group_name = ? WHERE id = ?");
                $u->bind_param('sssssi', $fullName, $first, $last, $class, $group, $existingId);
                if ($u->execute()) { $summary['updated']++; } else { $summary['errors'][] = 'Update failed for ' . $fullName; }
                $u->close();
            } else {
                $stmt->close();
                // Insert
                $status = 'active';
                if ($hasMembershipDate && $hasStatus) {
                    $m = $conn->prepare("INSERT INTO members (name, first_name, last_name, class, group_name, status, membership_date) VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
                    $bind = function($stmt) use ($fullName,$first,$last,$class,$group,$status){ $stmt->bind_param('ssssss', $fullName, $first, $last, $class, $group, $status); };
                } elseif ($hasMembershipDate && !$hasStatus) {
                    $m = $conn->prepare("INSERT INTO members (name, first_name, last_name, class, group_name, membership_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
                    $bind = function($stmt) use ($fullName,$first,$last,$class,$group){ $stmt->bind_param('sssss', $fullName, $first, $last, $class, $group); };
                } elseif (!$hasMembershipDate && $hasStatus) {
                    $m = $conn->prepare("INSERT INTO members (name, first_name, last_name, class, group_name, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $bind = function($stmt) use ($fullName,$first,$last,$class,$group,$status){ $stmt->bind_param('ssssss', $fullName, $first, $last, $class, $group, $status); };
                } else {
                    $m = $conn->prepare("INSERT INTO members (name, first_name, last_name, class, group_name) VALUES (?, ?, ?, ?, ?)");
                    $bind = function($stmt) use ($fullName,$first,$last,$class,$group){ $stmt->bind_param('sssss', $fullName, $first, $last, $class, $group); };
                }
                if ($m) {
                    $bind($m);
                    if ($m->execute()) { $summary['inserted']++; } else { $summary['errors'][] = 'Insert failed for ' . $fullName; }
                    $m->close();
                } else {
                    $summary['errors'][] = 'Insert prepare failed for ' . $fullName;
                }
            }
        }
        fclose($handle);
        // Cleanup uploaded file if we moved it
        if (is_file($dest)) { @unlink($dest); }
        $_SESSION['success_message'] = 'Import finished. Inserted: ' . $summary['inserted'] . ', Updated: ' . $summary['updated'] . ', Errors: ' . count($summary['errors']);
        if (!empty($summary['errors'])) {
            $_SESSION['success_message'] .= '. Details: ' . implode('; ', array_slice($summary['errors'], 0, 5));
        }
    } else {
        $_SESSION['error_message'] = 'Could not open uploaded CSV.';
    }
    header('Location: members.php');
    exit;
}

// Add extra class names (free text like "toddlers, 7H, 8E")
if (isset($_POST['add_manual_classes']) && is_staff()) {
    $raw = trim($_POST['manual_classes'] ?? '');
    if ($raw === '') {
        $_SESSION['error_message'] = 'Please type at least one class name.';
    } else {
        $parts = preg_split('/[,\n]+/', $raw);
        $clean = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            // Basic validation: allow letters, numbers, spaces, hyphen
            if (!preg_match('/^[A-Za-z0-9 \-]{1,40}$/', $p)) {
                $_SESSION['error_message'] = 'Invalid class name: ' . $p;
                $clean = []; // Abort
                break;
            }
            $clean[] = $p;
        }
        if (!empty($clean)) {
            // Load existing manual classes file
            $manualPath = __DIR__ . '/includes/manual_classes.php';
            $existing = file_exists($manualPath) ? include $manualPath : [];
            if (!is_array($existing)) { $existing = []; }
            $added = [];
            foreach ($clean as $c) {
                $exists = false;
                foreach ($existing as $ex) {
                    if (strcasecmp($ex, $c) === 0) { $exists = true; break; }
                }
                if (!$exists) { $existing[] = $c; $added[] = $c; }
            }
            if (!empty($added)) {
                // Sort for neatness
                natcasesort($existing);
                $export = "<?php\nreturn " . var_export(array_values($existing), true) . ";\n";
                if (@file_put_contents($manualPath, $export) === false) {
                    $_SESSION['error_message'] = 'Failed to write manual classes file.';
                } else {
                    $_SESSION['success_message'] = 'Added ' . count($added) . ' class(es): ' . implode(', ', $added);
                }
            } else if (empty($_SESSION['error_message'])) {
                $_SESSION['error_message'] = 'No new classes added (duplicates skipped).';
            }
        }
    }
    header('Location: members.php');
    exit();
}

// ---------- Add / edit a member ----------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_member']) && !$db_error) {
    $edit_id = (int)($_POST['edit_member_id'] ?? 0);
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $group = trim($_POST['group_name'] ?? '');
    $membership_date = $_POST['membership_date'] ?: date('Y-m-d');
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
    $name = trim($first . ' ' . $last);

    if ($name === '') {
        $_SESSION['error_message'] = 'Enter a first or last name.';
    } elseif ($edit_id) {
        $stmt = $conn->prepare("UPDATE members SET name=?, first_name=?, last_name=?, class=?, group_name=?, membership_date=?, status=? WHERE id=?");
        $stmt->bind_param("sssssssi", $name, $first, $last, $class, $group, $membership_date, $status, $edit_id);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Saved changes to ' . $name . '.';
        } else {
            $_SESSION['error_message'] = 'Could not save: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $stmt = $conn->prepare("INSERT INTO members (name, first_name, last_name, class, group_name, membership_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $name, $first, $last, $class, $group, $membership_date, $status);
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Added ' . $name . '.';
        } else {
            $_SESSION['error_message'] = 'Could not add: ' . $conn->error;
        }
        $stmt->close();
    }
    header("Location: members.php");
    exit();
}

// ---------- Delete (admin only) ----------
if (isset($_GET['delete']) && !$db_error) {
    if (!is_admin()) {
        $_SESSION['error_message'] = 'Only the admin can delete members.';
        header("Location: members.php");
        exit();
    }
    $member_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Member deleted.';
        } else {
            $_SESSION['error_message'] = 'Could not delete: ' . $conn->error;
        }
    $stmt->close();
    header("Location: members.php");
    exit();
}

$page_title = 'Members';
include 'includes/header.php';

// ---------- Data ----------
$members = [];
$class_list = [];
if (!$db_error && $conn) {
    $res = $conn->query("SELECT m.*, u.username,
                                (SELECT COUNT(*) FROM borrowings br WHERE br.member_id = m.id AND br.status = 'active') AS on_loan,
                                (SELECT COUNT(*) FROM borrowings br WHERE br.member_id = m.id AND br.status = 'active' AND br.due_date < CURDATE()) AS overdue
                         FROM members m
                         LEFT JOIN users u ON u.member_id = m.id
                         ORDER BY COALESCE(NULLIF(m.last_name, ''), m.name), COALESCE(NULLIF(m.first_name, ''), m.name)");
    if ($res) {
        $members = $res->fetch_all(MYSQLI_ASSOC);
    }
    foreach ($members as $m) {
        if (!empty($m['class'])) {
            $class_list[$m['class']] = true;
        }
    }
    $class_list = array_keys($class_list);
    natcasesort($class_list);
}
$active_count = count(array_filter($members, fn($m) => $m['status'] === 'active'));
$manual_classes = file_exists(__DIR__ . '/includes/manual_classes.php') ? include __DIR__ . '/includes/manual_classes.php' : [];
if (!is_array($manual_classes)) {
    $manual_classes = [];
}
?>

<div class="page-header">
    <div>
        <h1>Members</h1>
        <p><?php echo $active_count; ?> active of <?php echo count($members); ?> students and staff</p>
    </div>
    <div class="toolbar-group">
        <button class="btn btn-outline" onclick="openClassManagerModal()">Classes</button>
        <button class="btn btn-outline" onclick="openImportModal()">Import CSV</button>
        <button class="btn btn-primary" onclick="openMemberModal()"><?php echo icon('plus', 16); ?>Add member</button>
    </div>
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

<section class="panel">
    <div class="toolbar">
        <div class="toolbar-group">
            <div class="search-input">
                <?php echo icon('search', 16); ?>
                <input type="search" id="memberSearch" placeholder="Search by name or group" oninput="applyMemberFilters()">
            </div>
            <select id="classFilter" class="select" onchange="applyMemberFilters()">
                <option value="">All classes</option>
                <?php foreach ($class_list as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="statusFilter" class="select" onchange="applyMemberFilters()">
                <option value="">Any status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="overdue">Has overdue books</option>
            </select>
        </div>
        <span class="muted" id="memberCount" style="font-size: 0.88rem;"></span>
    </div>

    <div class="table-scroll">
        <table class="data-table" id="membersTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Class</th>
                    <th class="hide-sm">Group</th>
                    <th class="hide-sm">On loan</th>
                    <th class="hide-sm">Status</th>
                    <th class="actions"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m):
                    $search = strtolower($m['name'] . ' ' . $m['group_name'] . ' ' . $m['username']);
                ?>
                    <tr class="member-row" style="cursor: pointer;"
                        onclick="location.href='profile.php?id=<?php echo (int)$m['id']; ?>'"
                        data-search="<?php echo htmlspecialchars($search); ?>"
                        data-class="<?php echo htmlspecialchars($m['class'] ?? ''); ?>"
                        data-status="<?php echo $m['status']; ?>"
                        data-overdue="<?php echo $m['overdue'] > 0 ? '1' : '0'; ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span class="avatar" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    <?php if (!empty($m['avatar_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($m['avatar_url']); ?>" alt="" onerror="this.remove()">
                                    <?php endif; ?>
                                    <?php echo empty($m['avatar_url']) ? htmlspecialchars(strtoupper(substr($m['name'], 0, 1))) : ''; ?>
                                </span>
                                <div>
                                    <div class="cell-main"><?php echo htmlspecialchars($m['name']); ?></div>
                                    <?php if (!empty($m['username'])): ?>
                                        <div class="cell-sub">@<?php echo htmlspecialchars($m['username']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="muted"><?php echo htmlspecialchars($m['class'] ?: '—'); ?></td>
                        <td class="muted hide-sm"><?php echo htmlspecialchars($m['group_name'] ?: '—'); ?></td>
                        <td class="hide-sm">
                            <?php if ($m['overdue'] > 0): ?>
                                <span class="badge badge-danger"><?php echo $m['overdue'] == $m['on_loan'] ? (int)$m['overdue'] . ' overdue' : (int)$m['on_loan'] . ' on loan · ' . (int)$m['overdue'] . ' overdue'; ?></span>
                            <?php elseif ($m['on_loan'] > 0): ?>
                                <span class="badge badge-success"><?php echo (int)$m['on_loan']; ?> book<?php echo $m['on_loan'] > 1 ? 's' : ''; ?></span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-sm">
                            <span class="badge <?php echo $m['status'] === 'active' ? 'badge-success' : 'badge-neutral'; ?>"><?php echo ucfirst($m['status']); ?></span>
                        </td>
                        <td class="actions">
                            <button type="button" class="btn btn-outline btn-small" onclick="event.stopPropagation(); editMember(<?php echo (int)$m['id']; ?>)">Edit</button>
                            <?php if (is_admin()): ?>
                                <a href="members.php?delete=<?php echo (int)$m['id']; ?>" class="btn btn-small btn-ghost-danger" title="Delete"
                                   onclick="event.stopPropagation(); return confirm('Delete <?php echo htmlspecialchars(addslashes($m['name']), ENT_QUOTES); ?>? Their loans, reviews and login will be removed too.')">Delete</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="empty" id="membersEmpty" style="display: none;">
        <strong>No members match</strong>
        Try a different name, class or status.
    </div>
</section>

<!-- Add / edit member -->
<div id="memberModal" class="modal">
    <div class="modal-content has-parts modal-sm">
        <div class="modal-header">
            <h2 id="memberModalTitle">Add member</h2>
            <button type="button" class="icon-button" onclick="closeMemberModal()" aria-label="Close"><?php echo icon('x', 18); ?></button>
        </div>
        <form method="POST" action="" id="memberForm">
            <input type="hidden" name="save_member" value="1">
            <input type="hidden" name="edit_member_id" id="edit_member_id" value="">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First name</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last name</label>
                        <input type="text" id="last_name" name="last_name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="class">Class</label>
                        <select id="class" name="class"><option value="">Loading…</option></select>
                        <p class="form-hint" id="ayLabel"></p>
                    </div>
                    <div class="form-group">
                        <label for="group_name">Group</label>
                        <input type="text" id="group_name" name="group_name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="membership_date">Member since</label>
                        <input type="date" id="membership_date" name="membership_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeMemberModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="memberSubmit">Add member</button>
            </div>
        </form>
    </div>
</div>

<!-- Classes -->
<div id="classManagerModal" class="modal">
    <div class="modal-content has-parts">
        <div class="modal-header">
            <h2>Classes</h2>
            <button type="button" class="icon-button" onclick="closeClassManagerModal()" aria-label="Close"><?php echo icon('x', 18); ?></button>
        </div>
        <div class="modal-body">
            <h3 style="margin-bottom: 4px;">Grades and sections</h3>
            <p class="form-hint" style="margin: 0 0 14px;">The class list is built from these, e.g. grades 1–12 with sections A, B gives 1A … 12B.</p>
            <form id="classManagerForm" onsubmit="return saveClassConfig(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label for="minGradeInput">From grade</label>
                        <input type="number" id="minGradeInput" min="1" max="20" required>
                    </div>
                    <div class="form-group">
                        <label for="maxGradeInput">To grade</label>
                        <input type="number" id="maxGradeInput" min="1" max="20" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="sectionsInput">Sections</label>
                        <input type="text" id="sectionsInput" required placeholder="A,B,C">
                    </div>
                    <div class="form-group">
                        <label for="startMonthInput">School year starts in month</label>
                        <input type="number" id="startMonthInput" min="1" max="12" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="overridesInput">Changes for a specific year <span class="muted">(optional, JSON)</span></label>
                    <textarea id="overridesInput" rows="2" placeholder='{ "2026-2027": { "sections": ["A","B","C"] } }' style="min-height: 0;"></textarea>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <button type="submit" class="btn btn-primary">Save grades</button>
                    <span id="classManagerMsg" class="form-hint" style="margin: 0;"></span>
                </div>
            </form>

            <hr style="border: none; border-top: 1px solid var(--line); margin: 22px 0;">

            <h3 style="margin-bottom: 4px;">Extra classes</h3>
            <p class="form-hint" style="margin: 0 0 14px;">Names that don't fit the grade pattern, like "toddlers" or "7H". Separate with commas.</p>
            <form method="POST" action="">
                <input type="hidden" name="add_manual_classes" value="1">
                <div class="form-group">
                    <textarea name="manual_classes" rows="2" placeholder="toddlers, 7H, 8E" style="min-height: 0;"></textarea>
                </div>
                <button type="submit" class="btn btn-outline">Add classes</button>
            </form>
            <p class="form-hint" style="margin-top: 12px;">
                <?php echo $manual_classes ? 'Already added: ' . htmlspecialchars(implode(', ', $manual_classes)) : 'No extra classes yet.'; ?>
            </p>
        </div>
    </div>
</div>

<!-- Import CSV -->
<div id="importModal" class="modal">
    <div class="modal-content has-parts modal-sm">
        <div class="modal-header">
            <h2>Import students from CSV</h2>
            <button type="button" class="icon-button" onclick="closeImportModal()" aria-label="Close"><?php echo icon('x', 18); ?></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="import_csv" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label for="csv_file">CSV file</label>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required class="input">
                </div>
                <div class="alert alert-info" style="display: block; margin-bottom: 0;">
                    The first row must name the columns <strong>First Name, Last Name, Class, Group</strong>.
                    Students who already exist (same name and class) are updated, not duplicated.
                    <div style="margin-top: 8px;"><a href="download_members_template.php">Download a template</a></div>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeImportModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Import</button>
            </div>
        </form>
    </div>
</div>

<script>
// ---------- Filters ----------
function applyMemberFilters() {
    const q = document.getElementById('memberSearch').value.trim().toLowerCase();
    const cls = document.getElementById('classFilter').value;
    const status = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('.member-row');
    let visible = 0;
    rows.forEach(row => {
        let show = (!q || row.dataset.search.includes(q) || row.dataset.class.toLowerCase().includes(q))
            && (!cls || row.dataset.class === cls)
            && (!status || (status === 'overdue' ? row.dataset.overdue === '1' : row.dataset.status === status));
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('membersEmpty').style.display = visible ? 'none' : 'block';
    document.getElementById('membersTable').style.display = visible ? '' : 'none';
    document.getElementById('memberCount').textContent = visible === rows.length ? rows.length + ' members' : visible + ' of ' + rows.length;
}

// ---------- Add / edit ----------
function openMemberModal() {
    document.getElementById('memberForm').reset();
    document.getElementById('edit_member_id').value = '';
    document.getElementById('memberModalTitle').textContent = 'Add member';
    document.getElementById('memberSubmit').textContent = 'Add member';
    populateClassOptions('');
    document.getElementById('memberModal').style.display = 'block';
    setTimeout(() => document.getElementById('first_name').focus(), 50);
}

function closeMemberModal() {
    document.getElementById('memberModal').style.display = 'none';
}

function editMember(memberId) {
    fetch('get_member.php?id=' + memberId)
        .then(r => r.json())
        .then(data => {
            if (!data || data.error) {
                alert(data.error || 'Could not load this member.');
                return;
            }
            // Older rows may only have "name": split it into first/last
            let first = data.first_name || '', last = data.last_name || '';
            if (!first && !last && data.name) {
                const parts = data.name.trim().split(/\s+/);
                first = parts.shift();
                last = parts.join(' ');
            }
            document.getElementById('memberForm').reset();
            document.getElementById('edit_member_id').value = data.id;
            document.getElementById('first_name').value = first;
            document.getElementById('last_name').value = last;
            document.getElementById('group_name').value = data.group_name || '';
            document.getElementById('membership_date').value = data.membership_date || '';
            document.getElementById('status').value = data.status || 'active';
            document.getElementById('memberModalTitle').textContent = 'Edit ' + data.name;
            document.getElementById('memberSubmit').textContent = 'Save changes';
            populateClassOptions(data.class || '');
            document.getElementById('memberModal').style.display = 'block';
        })
        .catch(() => alert('Could not load this member.'));
}

// Class dropdown: generated classes + extra classes + the member's current class
function populateClassOptions(selectedValue) {
    const sel = document.getElementById('class');
    fetch('get_classes.php?t=' + Date.now())
        .then(r => r.json())
        .then(data => {
            sel.innerHTML = '<option value="">No class</option>';
            const values = new Set();
            (data.classes || []).forEach(c => {
                values.add(c.value);
                sel.add(new Option(c.label, c.value));
            });
            // Keep classes that came from a CSV import (e.g. "Year 10A - Oxford")
            if (selectedValue && !values.has(selectedValue)) {
                sel.add(new Option(selectedValue, selectedValue), 1);
            }
            sel.value = selectedValue || '';
            document.getElementById('ayLabel').textContent = data.academicYear ? 'School year ' + data.academicYear : '';
        })
        .catch(() => {
            sel.innerHTML = '';
            sel.add(new Option(selectedValue || 'No class', selectedValue || ''));
        });
}

// ---------- Import ----------
function openImportModal() { document.getElementById('importModal').style.display = 'block'; }
function closeImportModal() { document.getElementById('importModal').style.display = 'none'; }

// ---------- Classes ----------
function openClassManagerModal() {
    document.getElementById('classManagerMsg').textContent = '';
    document.getElementById('classManagerModal').style.display = 'block';
    loadClassConfig();
}

function closeClassManagerModal() {
    document.getElementById('classManagerModal').style.display = 'none';
}

function loadClassConfig() {
    fetch('get_classes.php?t=' + Date.now())
        .then(r => r.json())
        .then(data => {
            const cfg = data.config || {};
            const overrides = cfg.year_overrides || {};
            document.getElementById('minGradeInput').value = cfg.min_grade ?? 1;
            document.getElementById('maxGradeInput').value = cfg.max_grade ?? 12;
            document.getElementById('sectionsInput').value = (cfg.sections || ['A', 'B']).join(',');
            document.getElementById('startMonthInput').value = cfg.start_month ?? 9;
            document.getElementById('overridesInput').value = Object.keys(overrides).length ? JSON.stringify(overrides) : '';
        });
}

function saveClassConfig(e) {
    e.preventDefault();
    const msg = document.getElementById('classManagerMsg');
    const say = (text, ok) => { msg.textContent = text; msg.style.color = ok ? 'var(--success)' : 'var(--danger)'; };
    let overrides = {};
    const raw = document.getElementById('overridesInput').value.trim();
    if (raw) {
        try { overrides = JSON.parse(raw); } catch (err) { say('The year changes are not valid JSON.', false); return false; }
    }
    fetch('update_classes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            min_grade: parseInt(document.getElementById('minGradeInput').value),
            max_grade: parseInt(document.getElementById('maxGradeInput').value),
            sections: document.getElementById('sectionsInput').value.split(',').map(s => s.trim()).filter(Boolean),
            start_month: parseInt(document.getElementById('startMonthInput').value),
            year_overrides: overrides
        })
    })
        .then(r => r.json())
        .then(data => data.success ? say('Saved.', true) : say(data.error || 'Could not save.', false))
        .catch(() => say('Could not save.', false));
    return false;
}

applyMemberFilters();
</script>

<?php include 'includes/footer.php'; ?>
