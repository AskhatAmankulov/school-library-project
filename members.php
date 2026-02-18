<?php
session_start();
include 'auth_check.php';
include 'config.php';
include 'includes/header.php';
include 'includes/functions.php';

// Ensure extra columns for admin features (first_name, last_name, group_name)
if (!$db_error && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
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
if (!$db_error && isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && isset($_POST['import_csv'])) {
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$db_error) {
    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    $membership_date = $_POST['membership_date'] ?? date('Y-m-d');
    $status = 'active'; // Default to active
    $edit_id = isset($_POST['edit_member_id']) ? (int)$_POST['edit_member_id'] : 0;

    if ($isAdmin) {
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $class = trim($_POST['class'] ?? '');
        $group = trim($_POST['group_name'] ?? '');
        $name = trim($first . ' ' . $last);
        if ($first === '' && $last === '') {
            $_SESSION['error_message'] = 'First Name and/or Last Name is required!';
        } else if ($edit_id) {
            $stmt = $conn->prepare("UPDATE members SET name=?, first_name=?, last_name=?, class=?, group_name=?, membership_date=? WHERE id=?");
            $stmt->bind_param("ssssssi", $name, $first, $last, $class, $group, $membership_date, $edit_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Member updated successfully!';
            } else {
                $_SESSION['error_message'] = 'Error updating member: ' . $conn->error;
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO members (name, first_name, last_name, class, group_name, membership_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $name, $first, $last, $class, $group, $membership_date, $status);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Member added successfully!';
            } else {
                $_SESSION['error_message'] = 'Error adding member: ' . $conn->error;
            }
            $stmt->close();
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $class = trim($_POST['class'] ?? '');
        if (empty($name)) {
            $_SESSION['error_message'] = 'Name is required!';
        } else if ($edit_id) {
            $stmt = $conn->prepare("UPDATE members SET name=?, class=?, membership_date=? WHERE id=?");
            $stmt->bind_param("sssi", $name, $class, $membership_date, $edit_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Member updated successfully!';
            } else {
                $_SESSION['error_message'] = 'Error updating member: ' . $conn->error;
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO members (name, class, membership_date, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $class, $membership_date, $status);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Member added successfully!';
            } else {
                $_SESSION['error_message'] = 'Error adding member: ' . $conn->error;
            }
            $stmt->close();
        }
    }
    header("Location: members.php");
    exit();
}

// Handle delete
if (isset($_GET['delete']) && !$db_error) {
    $member_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Member deleted successfully!';
    } else {
        $_SESSION['error_message'] = 'Error deleting member: ' . $conn->error;
    }
    $stmt->close();
    
    header("Location: members.php");
    exit();
}
?>

<?php
// Admin-only: quick add manual classes (free text like "toddlers, 7H, 8E")
if (isset($_POST['add_manual_classes']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
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
                $_SESSION['error_message'] = 'Invalid class name: ' . htmlspecialchars($p);
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
                    $_SESSION['success_message'] = 'Added ' . count($added) . ' class(es): ' . htmlspecialchars(implode(', ', $added));
                }
            } else if (empty($_SESSION['error_message'])) {
                $_SESSION['error_message'] = 'No new classes added (duplicates skipped).';
            }
        }
    }
    header('Location: members.php');
    exit();
}
?>

<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 35px; margin: -20px -20px 30px -20px; border-radius: 0 0 20px 20px; box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);">
    <h1 style="color: white; margin: 0; display: flex; align-items: center; gap: 12px; font-size: 2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><span style="font-size: 2.2rem;">👥</span> Members Management</h1>
    <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 1.05rem;">Manage your library members</p>
</div>

<?php
// Display success/error messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success" style="border-radius: 12px; border-left: 4px solid #4CAF50; background: #e8f5e9; color: #2e7d32; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(76, 175, 80, 0.2);">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger" style="border-radius: 12px; border-left: 4px solid #f44336; background: #ffebee; color: #c62828; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(244, 67, 54, 0.2);">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}

if ($db_error) {
    echo '<div class="alert alert-danger" style="border-radius: 12px; border-left: 4px solid #f44336; background: #ffebee; color: #c62828; padding: 15px 20px; margin-bottom: 20px;">Database connection error. Please check your configuration.</div>';
}
?>

<div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h2 style="margin: 0; color: #333; font-size: 1.5rem; display: flex; align-items: center; gap: 10px;"><span style="color: #667eea; font-size: 1.3rem;">📋</span> Members List</h2>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'): ?>
            <label for="statusFilter" style="font-weight: 600; color: #555; display: flex; align-items: center; gap: 8px;"><span style="font-size: 0.9rem;">🔍</span> Filter:</label>
            <select id="statusFilter" onchange="filterMembers()" style="padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; background: white; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#667eea'" onmouseout="this.style.borderColor='#e0e0e0'">
                <option value="all">All Members</option>
                <option value="active">Active Only</option>
                <option value="inactive">Inactive Only</option>
            </select>
            <?php endif; ?>
            <button onclick="openMemberModal()" class="btn btn-success" style="padding: 10px 20px; font-size: 1rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(76,175,80,0.3); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(76,175,80,0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(76,175,80,0.3)'">➕ Add New Member</button>
            <button onclick="openClassManagerModal()" class="btn btn-info" style="padding: 10px 20px; font-size: 1rem; border-radius: 8px; background: linear-gradient(135deg, #42a5f5 0%, #7e57c2 100%); color: white; font-weight: 600; box-shadow: 0 2px 8px rgba(33,150,243,0.18); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(33,150,243,0.25)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(33,150,243,0.18)'">🏷️ Manage Classes</button>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <button onclick="openImportModal()" class="btn btn-warning" style="padding: 10px 20px; font-size: 1rem; border-radius: 8px; background: linear-gradient(135deg,#ffb74d 0%, #ff7043 100%); color: white; font-weight: 600; box-shadow: 0 2px 8px rgba(255,167,38,0.25);">📥 Import CSV</button>
            <a href="download_members_template.php" class="btn btn-secondary" style="padding: 10px 20px; font-size: 1rem; border-radius: 8px; background: linear-gradient(135deg, #78909c 0%, #546e7a 100%); color: white; font-weight: 600; box-shadow: 0 2px 8px rgba(96,125,139,0.25); text-decoration: none; display: inline-block;">📄 Download CSV Template</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <div style="margin-top:25px; background: linear-gradient(135deg,#f5f7fa 0%, #e8ecf5 100%); padding:18px 20px; border-radius:12px; border:1px solid #d0d7e2;">
        <h3 style="margin:0 0 12px 0; font-size:1.15rem; display:flex; align-items:center; gap:8px; color:#334e68;">✍️ Quick Add Manual Classes</h3>
        <p style="margin:0 0 12px 0; color:#5d6d7e; font-size:0.9rem;">Type custom class names (examples: <code>toddlers</code>, <code>7H</code>, <code>8E</code>) separated by commas or new lines. These will appear in the class dropdown immediately.</p>
        <form method="POST" action="" style="display:flex; flex-direction:column; gap:12px;">
            <textarea name="manual_classes" rows="2" placeholder="toddlers, 7H, 8E" style="width:100%; padding:10px; border:2px solid #ccd6e0; border-radius:8px; font-size:0.9rem; resize:vertical;" onfocus="this.style.borderColor='#667eea'" onblur="this.style.borderColor='#ccd6e0'"></textarea>
            <input type="hidden" name="add_manual_classes" value="1" />
            <div style="display:flex; gap:10px;">
                <button type="submit" class="btn btn-primary" style="flex:1; padding:10px 18px; background:linear-gradient(135deg,#667eea 0%, #764ba2 100%); border:none; border-radius:8px; color:white; font-weight:600; font-size:0.95rem; cursor:pointer; box-shadow:0 2px 8px rgba(102,126,234,0.3);" onmouseover="this.style.transform='translateY(-2px)';" onmouseout="this.style.transform='translateY(0)'">➕ Add Classes</button>
                <button type="button" onclick="this.closest('form').querySelector('textarea').value='';" class="btn btn-secondary" style="padding:10px 18px; background:#e0e0e0; border:none; border-radius:8px; color:#555; font-weight:600; font-size:0.95rem; cursor:pointer;" onmouseover="this.style.background='#d0d0d0'" onmouseout="this.style.background='#e0e0e0'">Clear</button>
            </div>
        </form>
        <?php
        // Show existing manual classes for reference
        $manualExistingPath = __DIR__ . '/includes/manual_classes.php';
        $manualExisting = file_exists($manualExistingPath) ? include $manualExistingPath : [];
        if ($manualExisting && is_array($manualExisting) && count($manualExisting) > 0): ?>
            <div style="margin-top:12px; font-size:0.85rem; color:#444;">
                <strong>Existing manual classes:</strong>
                <span><?php echo htmlspecialchars(implode(', ', $manualExisting)); ?></span>
            </div>
        <?php else: ?>
            <div style="margin-top:12px; font-size:0.85rem; color:#777;">No manual classes added yet.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Class Manager Modal -->
<div id="classManagerModal" class="modal">
    <div class="modal-content" style="border-radius: 16px; max-width: 600px; padding: 0; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #42a5f5 0%, #7e57c2 100%); padding: 25px 30px; position: relative;">
            <h2 style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 1.6rem;">🏷️ Manage Classes</h2>
            <span class="modal-close" onclick="closeClassManagerModal()" style="position: absolute; top: 20px; right: 25px; color: white; font-size: 2rem; opacity: 0.9; cursor: pointer;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">&times;</span>
        </div>
        <div style="padding: 30px;">
            <form id="classManagerForm" onsubmit="return saveClassConfig(event)">
                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Minimum Grade</label>
                    <input type="number" id="minGradeInput" min="1" max="20" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem;">
                </div>
                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Maximum Grade</label>
                    <input type="number" id="maxGradeInput" min="1" max="20" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem;">
                </div>
                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Sections (comma separated, e.g. A,B,C)</label>
                    <input type="text" id="sectionsInput" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem;">
                </div>
                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Academic Year Start Month (1-12)</label>
                    <input type="number" id="startMonthInput" min="1" max="12" required style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem;">
                </div>
                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Per-Year Overrides (JSON, optional)</label>
                    <textarea id="overridesInput" rows="3" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem;"></textarea>
                    <small style="color:#777;">Example: { "2025-2026": { "sections": ["A","B","C"] } }</small>
                </div>
                <div style="display: flex; gap: 12px; margin-top: 25px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px 24px; background: linear-gradient(135deg, #42a5f5 0%, #7e57c2 100%); border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(66, 165, 245, 0.18);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(66, 165, 245, 0.25)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(66, 165, 245, 0.18)'">💾 Save</button>
                    <button type="button" class="btn btn-secondary" onclick="closeClassManagerModal()" style="padding: 12px 24px; background: #e0e0e0; border: none; border-radius: 10px; color: #666; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='#d0d0d0'" onmouseout="this.style.background='#e0e0e0'">Cancel</button>
                </div>
                <div id="classManagerMsg" style="margin-top: 18px; color: #c62828; font-weight: 500;"></div>
            </form>
        </div>
    </div>
</div>

<!-- Member Modal -->
<div id="memberModal" class="modal">
    <div class="modal-content" style="border-radius: 16px; max-width: 550px; padding: 0; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px 30px; position: relative;">
            <h2 style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 1.6rem;">➕ Add New Member</h2>
            <span class="modal-close" onclick="closeMemberModal()" style="position: absolute; top: 20px; right: 25px; color: white; font-size: 2rem; opacity: 0.9; cursor: pointer;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">&times;</span>
        </div>
        <form method="POST" action="" id="memberForm" style="padding: 30px;">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div class="form-group" style="margin-bottom: 20px; display: flex; gap: 16px;">
                <div style="flex:1;">
                    <label for="first_name" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">First Name *</label>
                    <input type="text" id="first_name" name="first_name" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                </div>
                <div style="flex:1;">
                    <label for="last_name" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 20px; display: flex; gap: 16px;">
                <div style="flex:1;">
                    <label for="class" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Class</label>
                    <select id="class" name="class" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; cursor: pointer; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                        <option value="">Loading classes...</option>
                    </select>
                    <small id="ayLabel" style="color:#777; display: block; margin-top: 8px;"></small>
                </div>
                <div style="flex:1;">
                    <label for="group_name" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Group</label>
                    <input type="text" id="group_name" name="group_name" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                </div>
            </div>
            <?php else: ?>
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="name" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Name *</label>
                <input type="text" id="name" name="name" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="class" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Class</label>
                <select id="class" name="class" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; cursor: pointer; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                    <option value="">Loading classes...</option>
                </select>
                <small id="ayLabel" style="color:#777; display: block; margin-top: 8px;"></small>
            </div>
            <?php endif; ?>
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="membership_date" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Membership Date *</label>
                <input type="date" id="membership_date" name="membership_date" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
            </div>
            <div style="display: flex; gap: 12px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.3)'">💾 Save Member</button>
                <button type="button" class="btn btn-secondary" onclick="closeMemberModal()" style="padding: 12px 24px; background: #e0e0e0; border: none; border-radius: 10px; color: #666; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='#d0d0d0'" onmouseout="this.style.background='#e0e0e0'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Borrowing History Modal -->
<div id="historyModal" class="modal">
    <div class="modal-content" style="max-width: 900px; border-radius: 16px; padding: 0; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px 30px; position: relative;">
            <h2 id="historyTitle" style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 1.6rem;">📚 Borrowing History</h2>
            <span class="modal-close" onclick="closeHistoryModal()" style="position: absolute; top: 20px; right: 25px; color: white; font-size: 2rem; opacity: 0.9; cursor: pointer;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">&times;</span>
        </div>
        <div id="historyContent" style="padding: 30px;">
            <p style="text-align: center; color: #999; padding: 20px 0;">Loading...</p>
        </div>
    </div>
</div>

<table class="table" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
    <thead>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <th style="color: white; padding: 15px; text-align: left; font-weight: 600; border-radius: 12px 0 0 0;">First Name</th>
                <th style="color: white; padding: 15px; text-align: left; font-weight: 600;">Last Name</th>
                <th style="color: white; padding: 15px; text-align: left; font-weight: 600;">Class</th>
                <th style="color: white; padding: 15px; text-align: left; font-weight: 600;">Group</th>
                <th style="color: white; padding: 15px; text-align: left; font-weight: 600; border-radius: 0 12px 0 0;">Actions</th>
            </tr>
        <?php else: ?>
            <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <th style="color: white; padding: 15px; text-align: left; font-weight: 600; border-radius: 12px 0 0 0;">Name</th>
                <th style="color: white; padding: 15px; text-align: left; font-weight: 600;">Class</th>
                <th style="color: white; padding: 15px; text-align: left; font-weight: 600;">Status</th>
                <th style="color: white; padding: 15px; text-align: left; font-weight: 600; border-radius: 0 12px 0 0;">Actions</th>
            </tr>
        <?php endif; ?>
    </thead>
    <tbody>
        <?php
        if (!$db_error && $conn) {
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                $result = $conn->query("SELECT * FROM members ORDER BY COALESCE(NULLIF(last_name,''), name) ASC, COALESCE(NULLIF(first_name,''), name) ASC");
            } else {
                $result = $conn->query("SELECT * FROM members ORDER BY name ASC");
            }
            
            if ($result && $result->num_rows > 0) {
                while ($member = $result->fetch_assoc()) {
                    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                        $first = isset($member['first_name']) && $member['first_name'] ? $member['first_name'] : '';
                        $last  = isset($member['last_name']) && $member['last_name'] ? $member['last_name'] : '';
                        if ($first === '' && $member['name']) {
                            // Try to split
                            $parts = preg_split('/\s+/', $member['name']);
                            $first = array_shift($parts);
                            $last = implode(' ', $parts);
                        }
                        $group = isset($member['group_name']) && $member['group_name'] !== '' ? $member['group_name'] : '-';
                        echo "<tr style='cursor: pointer; transition: all 0.2s; border-bottom: 1px solid #f0f0f0;' onclick='window.location.href=\"profile.php?id=" . $member['id'] . "\"' onmouseover='this.style.backgroundColor=\"#f8f9fa\"; this.style.transform=\"scale(1.01)\"' onmouseout='this.style.backgroundColor=\"\"; this.style.transform=\"scale(1)\"'>";
                        echo "<td style='padding: 15px; font-weight: 500; color: #333;'>" . htmlspecialchars($first) . "</td>";
                        echo "<td style='padding: 15px; font-weight: 500; color: #333;'>" . htmlspecialchars($last) . "</td>";
                        echo "<td style='padding: 15px; color: #666;'>" . htmlspecialchars($member['class'] ?? '-') . "</td>";
                        echo "<td style='padding: 15px; color: #666;'>" . htmlspecialchars($group) . "</td>";
                        echo "<td style='padding: 15px;'>
                            <button onclick='event.stopPropagation(); editMember(" . $member['id'] . ")' class='btn btn-small btn-warning' style='padding: 6px 12px; font-size: 0.85rem; border-radius: 8px; font-weight: 500; margin-right: 6px; transition: all 0.2s;'>✏️ Edit</button>
                            <a href='members.php?delete=" . $member['id'] . "' class='btn btn-small btn-danger' onclick='event.stopPropagation(); return confirm(\"Are you sure you want to delete this member?\")' style='padding: 6px 12px; font-size: 0.85rem; border-radius: 8px; font-weight: 500; text-decoration: none; display: inline-block;'>🗑️</a>
                        </td>";
                        echo "</tr>";
                    } else {
                        // Non-admin original view
                        // Check if member has active borrowings
                        $checkActive = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE member_id = " . $member['id'] . " AND status = 'active'");
                        $activeCount = $checkActive->fetch_assoc()['count'];
                        $hasActive = $activeCount > 0;
                        $buttonClass = $hasActive ? 'btn-success' : 'btn-secondary';
                        $buttonText = $hasActive ? '📖 Active' : '📚 Inactive';
                        $buttonFunction = $hasActive ? 'viewActiveBorrowings' : 'viewLastRead';
                        echo "<tr style='cursor: pointer; transition: all 0.2s; border-bottom: 1px solid #f0f0f0;' onclick='window.location.href=\"profile.php?id=" . $member['id'] . "\"' onmouseover='this.style.backgroundColor=\"#f8f9fa\"; this.style.transform=\"scale(1.01)\"' onmouseout='this.style.backgroundColor=\"\"; this.style.transform=\"scale(1)\"'>";
                        echo "<td style='padding: 15px; font-weight: 500; color: #333;'>" . htmlspecialchars($member['name']) . "</td>";
                        echo "<td style='padding: 15px; color: #666;'>" . htmlspecialchars($member['class'] ?? '-') . "</td>";
                        echo "<td style='padding: 15px;'>
                            <button onclick='event.stopPropagation(); " . $buttonFunction . "(" . $member['id'] . ", \"" . htmlspecialchars($member['name'], ENT_QUOTES) . "\")' class='btn btn-small " . $buttonClass . "' style='padding: 6px 12px; font-size: 0.85rem; border-radius: 20px; font-weight: 500; transition: all 0.2s;'>" . $buttonText . "</button>
                        </td>";
                        echo "<td style='padding: 15px;'>
                            <button onclick='event.stopPropagation(); editMember(" . $member['id'] . ")' class='btn btn-small btn-warning' style='padding: 6px 12px; font-size: 0.85rem; border-radius: 8px; font-weight: 500; margin-right: 6px; transition: all 0.2s;'>✏️ Edit</button>
                            <a href='members.php?delete=" . $member['id'] . "' class='btn btn-small btn-danger' onclick='event.stopPropagation(); return confirm(\"Are you sure you want to delete this member?\")' style='padding: 6px 12px; font-size: 0.85rem; border-radius: 8px; font-weight: 500; text-decoration: none; display: inline-block;'>🗑️</a>
                        </td>";
                        echo "</tr>";
                    }
                }
            } else {
                $colspan = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 5 : 4;
                echo "<tr><td colspan='".$colspan."' style='text-align:center; padding: 60px 20px; color: #999;'><div style='font-size: 3rem; margin-bottom: 15px; opacity: 0.3;'>👥</div><p style='font-size: 1.1rem; margin: 0;'>No members found. Click 'Add New Member' to add one!</p></td></tr>";
            }
        } else {
            echo "<tr><td colspan='4' style='text-align:center; padding: 40px 20px; color: #e74c3c; font-weight: 500;'>Database connection error.</td></tr>";
        }
        ?>
    </tbody>
</table>

<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
<!-- Import CSV Modal (admin only) -->
<div id="importModal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 9999;">
    <div style="background: white; width: min(580px, 92vw); border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden;">
        <div style="padding: 16px 20px; background: linear-gradient(135deg,#ffb74d 0%, #ff7043 100%); color: white; display:flex; align-items:center; justify-content:space-between;">
            <h3 style="margin:0; font-weight:600;">📥 Import Students from CSV</h3>
            <button onclick="closeImportModal()" class="btn btn-small btn-secondary" style="background: rgba(255,255,255,0.2); border:none;">✖</button>
        </div>
        <form method="POST" enctype="multipart/form-data" style="padding: 20px;">
            <input type="hidden" name="import_csv" value="1" />
            <div style="margin-bottom: 12px;">
                <label style="font-weight:600; color:#333; display:block; margin-bottom:8px;">CSV File</label>
                <input type="file" name="csv_file" accept=".csv" required style="padding:10px; border:2px solid #e0e0e0; border-radius:8px; width:100%;" />
            </div>
            <div style="background:#fff8e1; border:1px solid #ffe0b2; border-radius:8px; padding:12px; color:#8d6e63; margin-bottom: 16px;">
                <div style="font-weight:600; margin-bottom:6px;">Expected columns (header row required):</div>
                <code>First Name, Last Name, Class, Group</code>
                <div style="margin-top:8px; font-size:0.9rem;">Example:<br />
                <code>Emma, Johnson, 7H, A</code></div>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" onclick="closeImportModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-warning" style="background: linear-gradient(135deg,#ffb74d 0%, #ff7043 100%); color:white;">Import</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function openMemberModal() {
    document.getElementById('memberModal').style.display = 'block';
    document.querySelector('#memberModal h2').textContent = '➕ Add New Member';
    // Remove edit ID if exists
    const editId = document.getElementById('edit_member_id');
    if (editId) editId.remove();
    populateClassOptions('');
}

function closeMemberModal() {
    document.getElementById('memberModal').style.display = 'none';
    document.getElementById('memberForm').reset();
    // Remove edit ID if exists
    const editId = document.getElementById('edit_member_id');
    if (editId) editId.remove();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('memberModal');
    if (event.target == modal) {
        closeMemberModal();
    }
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeMemberModal();
        closeHistoryModal();
    }
});

// Import CSV modal controls
function openImportModal(){
    const m = document.getElementById('importModal');
    if (m) m.style.display='flex';
}
function closeImportModal(){
    const m = document.getElementById('importModal');
    if (m) m.style.display='none';
}

function viewMemberHistory(memberId, memberName) {
    const modal = document.getElementById('historyModal');
    const title = document.getElementById('historyTitle');
    const content = document.getElementById('historyContent');
    
    title.textContent = '📚 Borrowing History - ' + memberName;
    content.innerHTML = '<p style="text-align: center;">Loading...</p>';
    modal.style.display = 'block';
    
    // Fetch borrowing history via AJAX
    fetch('get_member_history.php?member_id=' + memberId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = '<p style="text-align: center; color: #e74c3c;">' + data.error + '</p>';
            } else if (data.length === 0) {
                content.innerHTML = '<p style="text-align: center;">No borrowing history found.</p>';
            } else {
                let html = '<table class="table" style="width: 100%; border-collapse: collapse;">';
                html += '<thead style="background: #f7f7f7; font-weight: 600; color: #333;">';
                html += '<tr>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Book Title</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Author</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Borrow Date</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Due Date</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Return Date</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Status</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                
                data.forEach(record => {
                    html += '<tr style="transition: background-color 0.2s;">';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + escapeHtml(record.title) + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + escapeHtml(record.author) + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + record.borrow_date + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + record.due_date + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + (record.return_date || '-') + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;"><span class="status-' + record.status + '" style="font-weight: 500;">' + record.status.charAt(0).toUpperCase() + record.status.slice(1) + '</span></td>';
                    html += '</tr>';
                });
                
                html += '</tbody>';
                html += '</table>';
                content.innerHTML = html;
            }
        })
        .catch(error => {
            content.innerHTML = '<p style="text-align: center; color: #e74c3c;">Error loading history.</p>';
        });
}

function closeHistoryModal() {
    document.getElementById('historyModal').style.display = 'none';
}

function viewActiveBorrowings(memberId, memberName) {
    const modal = document.getElementById('historyModal');
    const title = document.getElementById('historyTitle');
    const content = document.getElementById('historyContent');
    
    title.textContent = '📖 Currently Reading - ' + memberName;
    content.innerHTML = '<p style="text-align: center;">Loading...</p>';
    modal.style.display = 'block';
    
    // Fetch active borrowings via AJAX
    fetch('get_member_history.php?member_id=' + memberId + '&status=active')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = '<p style="text-align: center; color: #e74c3c;">' + data.error + '</p>';
            } else if (data.length === 0) {
                content.innerHTML = '<p style="text-align: center;">No active borrowings. Student has not borrowed any books currently.</p>';
            } else {
                let html = '<table class="table" style="width: 100%; border-collapse: collapse;">';
                html += '<thead style="background: #f7f7f7; font-weight: 600; color: #333;">';
                html += '<tr>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Book Title</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Author</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">ISBN</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Category</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Borrow Date</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Due Date</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                
                data.forEach(record => {
                    html += '<tr style="transition: background-color 0.2s;">';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + escapeHtml(record.title) + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + escapeHtml(record.author) + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + (record.isbn || '-') + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + (record.category || '-') + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + record.borrow_date + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + record.due_date + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody>';
                html += '</table>';
                content.innerHTML = html;
            }
        })
        .catch(error => {
            content.innerHTML = '<p style="text-align: center; color: #e74c3c;">Error loading active borrowings.</p>';
        });
}

function viewLastRead(memberId, memberName) {
    const modal = document.getElementById('historyModal');
    const title = document.getElementById('historyTitle');
    const content = document.getElementById('historyContent');
    
    title.textContent = '📚 Last Read Book - ' + memberName;
    content.innerHTML = '<p style="text-align: center;">Loading...</p>';
    modal.style.display = 'block';
    
    // Fetch last returned book via AJAX
    fetch('get_member_history.php?member_id=' + memberId + '&last_read=1')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = '<p style="text-align: center; color: #e74c3c;">' + data.error + '</p>';
            } else if (data.length === 0) {
                content.innerHTML = '<p style="text-align: center;">No reading history found.</p>';
            } else {
                let html = '<table class="table" style="width: 100%; border-collapse: collapse;">';
                html += '<thead style="background: #f7f7f7; font-weight: 600; color: #333;">';
                html += '<tr>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Book Title</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Author</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">ISBN</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Category</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Borrow Date</th>';
                html += '<th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Return Date</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                
                data.forEach(record => {
                    html += '<tr style="transition: background-color 0.2s;">';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + escapeHtml(record.title) + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + escapeHtml(record.author) + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + (record.isbn || '-') + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + (record.category || '-') + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + record.borrow_date + '</td>';
                    html += '<td style="padding: 10px; border-bottom: 1px solid #eee;">' + (record.return_date || '-') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody>';
                html += '</table>';
                content.innerHTML = html;
            }
        })
        .catch(error => {
            content.innerHTML = '<p style="text-align: center; color: #e74c3c;">Error loading last read book.</p>';
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function editMember(memberId) {
    // Fetch member data via AJAX and prefill the modal
    fetch('get_member.php?id=' + memberId)
        .then(response => response.json())
        .then(data => {
            if (data && !data.error) {
                document.getElementById('memberModal').style.display = 'block';
                document.querySelector('#memberModal h2').textContent = '✏️ Edit Member';
                if (typeof isAdmin !== 'undefined' ? isAdmin : (window.isAdmin || false) || (typeof window !== 'undefined' && window.sessionStorage && sessionStorage.getItem('role') === 'admin') || (<?php echo isset($_SESSION['role']) && $_SESSION['role'] === 'admin' ? 'true' : 'false'; ?>)) {
                    if (document.getElementById('first_name')) document.getElementById('first_name').value = data.first_name || '';
                    if (document.getElementById('last_name')) document.getElementById('last_name').value = data.last_name || '';
                    if (document.getElementById('group_name')) document.getElementById('group_name').value = data.group_name || '';
                } else {
                    if (document.getElementById('name')) document.getElementById('name').value = data.name;
                }
                document.getElementById('class').value = data.class || '';
                document.getElementById('membership_date').value = data.membership_date;
                // Add a hidden input for member ID
                let idInput = document.getElementById('edit_member_id');
                if (!idInput) {
                    idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.id = 'edit_member_id';
                    idInput.name = 'edit_member_id';
                    document.getElementById('memberForm').appendChild(idInput);
                }
                idInput.value = data.id;
                // Populate class options and select the member's current class
                populateClassOptions(data.class || '');
            } else {
                alert(data.error || 'Failed to load member data.');
            }
        })
        .catch(() => alert('Failed to load member data.'));
}

function filterMembers() {
    const filterValue = document.getElementById('statusFilter').value;
    const rows = document.querySelectorAll('.table tbody tr');
    
    rows.forEach(row => {
        if (row.querySelector('td') && row.querySelector('td').getAttribute('colspan')) {
            // Skip "no members found" message row
            return;
        }
        
        const statusButton = row.querySelector('td:nth-child(4) button');
        if (!statusButton) return;
        
        const isActive = statusButton.classList.contains('btn-success');
        
        if (filterValue === 'all') {
            row.style.display = '';
        } else if (filterValue === 'active' && isActive) {
            row.style.display = '';
        } else if (filterValue === 'inactive' && !isActive) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Fetch classes from server and populate dropdown
function populateClassOptions(selectedValue) {
    fetch('get_classes.php?t=' + Date.now())
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('class');
            if (!sel) return;
            sel.innerHTML = '';
            // Placeholder
            const opt0 = document.createElement('option');
            opt0.value = '';
            opt0.textContent = 'Select class';
            sel.appendChild(opt0);

            (data.classes || []).forEach(cls => {
                const opt = document.createElement('option');
                opt.value = cls.value; // e.g., 10A
                opt.textContent = cls.label; // e.g., Grade 10A
                if (selectedValue && (selectedValue === cls.value || selectedValue === cls.label)) {
                    opt.selected = true;
                }
                sel.appendChild(opt);
            });

            const ay = document.getElementById('ayLabel');
            if (ay && data.academicYear) {
                ay.textContent = 'Academic Year: ' + data.academicYear;
            }
        })
        .catch(() => {
            const sel = document.getElementById('class');
            if (sel) sel.innerHTML = '<option value="">Unable to load classes</option>';
        });
}

// Close history modal when clicking outside
window.addEventListener('click', function(event) {
    const historyModal = document.getElementById('historyModal');
    if (event.target == historyModal) {
        closeHistoryModal();
    }
});

// Class Manager Modal logic
function openClassManagerModal() {
    document.getElementById('classManagerModal').style.display = 'block';
    loadClassConfig();
}

function closeClassManagerModal() {
    document.getElementById('classManagerModal').style.display = 'none';
    document.getElementById('classManagerMsg').textContent = '';
}

function loadClassConfig() {
    fetch('get_classes.php?t=' + Date.now())
        .then(r => r.json())
        .then(data => {
            // Fetch config file for full details
            fetch('includes/class_config.php?t=' + Date.now())
                .then(r2 => r2.text())
                .then(txt => {
                    // Parse PHP array (very basic, not secure for arbitrary PHP)
                    let min = 1, max = 12, sections = ['A','B'], startMonth = 9, overrides = {};
                    try {
                        // Use regex to extract values
                        min = parseInt(txt.match(/'min_grade'\s*=>\s*(\d+)/)[1]);
                        max = parseInt(txt.match(/'max_grade'\s*=>\s*(\d+)/)[1]);
                        let secMatch = txt.match(/'sections'\s*=>\s*\[((?:'[^']+'\s*,?\s*)+)\]/);
                        if (secMatch) {
                            sections = secMatch[1].split(',').map(s => s.replace(/'/g,'').trim());
                        }
                        let sm = txt.match(/'start_month'\s*=>\s*(\d+)/);
                        if (sm) startMonth = parseInt(sm[1]);
                        let yo = txt.match(/'year_overrides'\s*=>\s*\[((.|\n)*?)\]/);
                        if (yo && yo[1].trim().length > 0) {
                            try { overrides = JSON.parse(yo[1].replace(/=>/g, ':').replace(/'/g, '"').replace(/,\s*\}/g, '}').replace(/,\s*\]/g, ']')); } catch(e) { overrides = {}; }
                        }
                    } catch(e) {}
                    document.getElementById('minGradeInput').value = min;
                    document.getElementById('maxGradeInput').value = max;
                    document.getElementById('sectionsInput').value = sections.join(',');
                    document.getElementById('startMonthInput').value = startMonth;
                    document.getElementById('overridesInput').value = Object.keys(overrides).length ? JSON.stringify(overrides) : '';
                });
        });
}

function saveClassConfig(e) {
    e.preventDefault();
    const min = parseInt(document.getElementById('minGradeInput').value);
    const max = parseInt(document.getElementById('maxGradeInput').value);
    const sections = document.getElementById('sectionsInput').value.split(',').map(s => s.trim()).filter(Boolean);
    const startMonth = parseInt(document.getElementById('startMonthInput').value);
    let overrides = {};
    const overridesRaw = document.getElementById('overridesInput').value.trim();
    if (overridesRaw) {
        try { overrides = JSON.parse(overridesRaw); } catch(e) {
            document.getElementById('classManagerMsg').textContent = 'Invalid JSON in overrides.';
            return false;
        }
    }
    fetch('update_classes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ min_grade: min, max_grade: max, sections, start_month: startMonth, year_overrides: overrides })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('classManagerMsg').style.color = '#2e7d32';
            document.getElementById('classManagerMsg').textContent = 'Class configuration updated!';
            populateClassOptions('');
            setTimeout(() => { closeClassManagerModal(); }, 1200);
        } else {
            document.getElementById('classManagerMsg').style.color = '#c62828';
            document.getElementById('classManagerMsg').textContent = data.error || 'Failed to update.';
        }
    })
    .catch(() => {
        document.getElementById('classManagerMsg').style.color = '#c62828';
        document.getElementById('classManagerMsg').textContent = 'Failed to update.';
    });
    return false;
}
</script>

<?php include 'includes/footer.php'; ?>
