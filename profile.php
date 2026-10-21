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

<div style="max-width: 1200px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 35px; margin: -20px -20px 30px -20px; border-radius: 0 0 20px 20px; box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);">
        <h1 style="color: white; margin: 0; display: flex; align-items: center; gap: 12px; font-size: 2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><span style="font-size: 2.2rem;">👤</span> Member Profile</h1>
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
    ?>
    
    <!-- Profile Card -->
    <div style="background: white; border-radius: 16px; padding: 40px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <div style="display: flex; align-items: center; gap: 30px; flex-wrap: wrap;">
            <div style="position: relative;">
            <?php
            $avatar = $member['avatar_url'] ?? '';
            if ($avatar) {
                echo '<img src="' . htmlspecialchars($avatar) . '" alt="Avatar" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #667eea; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">';
            } else {
                echo '<div style="width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem; font-weight: bold; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">' . strtoupper(substr($member['name'], 0, 1)) . '</div>';
            }
            // Show edit button for admin or user viewing their own profile
            if ($_SESSION['role'] === 'admin' || $member_id == $_SESSION['user_id']) {
                echo '<button onclick="openAvatarModal()" style="position: absolute; bottom: 0; right: 0; background: #667eea; color: white; border: none; border-radius: 50%; width: 36px; height: 36px; cursor: pointer; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4); display: flex; align-items: center; justify-content: center; font-size: 1rem; transition: all 0.3s;" onmouseover="this.style.background=\'#764ba2\'; this.style.transform=\'scale(1.1)\'" onmouseout="this.style.background=\'#667eea\'; this.style.transform=\'scale(1)\'" title="Edit Picture">📷</button>';
            }
            ?>
            </div>
            <div style="flex: 1; min-width: 250px;">
                <h2 style="margin: 0 0 15px 0; color: #333; font-size: 2rem;"><?php echo htmlspecialchars($member['name']); ?></h2>
                <div style="margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 1.5rem;">🎓</span>
                    <div>
                        <strong style="color: #555;">Class:</strong>
                        <span style="color: #667eea; font-size: 1.15rem; font-weight: 600; margin-left: 8px;"><?php echo !empty($member['class']) ? htmlspecialchars($member['class']) : 'Not assigned'; ?></span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <span style="background: <?php echo $member['status'] === 'active' ? 'linear-gradient(135deg, #4CAF50 0%, #45a049 100%)' : 'linear-gradient(135deg, #f44336 0%, #e53935 100%)'; ?>; color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
                        <?php echo $member['status'] === 'active' ? '✅ Active' : '❌ Inactive'; ?>
                    </span>
                    <span style="color: #666; display: flex; align-items: center; gap: 6px;">
                        <span style="font-size: 1.2rem;">📅</span>
                        <span><strong>Member since:</strong> <?php echo date('M d, Y', strtotime($member['membership_date'])); ?></span>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Password Change Button (only for own profile) -->
        <?php if ($member_id == $_SESSION['user_id']): ?>
        <div style="margin-top: 25px; padding-top: 25px; border-top: 2px solid #f0f0f0;">
            <button onclick="openPasswordModal()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3); transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(102, 126, 234, 0.3)'">
                🔒 Change Password
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Borrowing History -->
    <div style="background: white; border-radius: 12px; padding: 30px; margin-bottom: 30px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
        <h2 style="margin-top: 0; color: #333; display: flex; align-items: center; gap: 10px;"><span style="font-size: 1.3rem;">📚</span> Borrowing History</h2>
        <?php if ($borrowing_result->num_rows > 0): ?>
        <div style="overflow-x: auto;">
        <table class="table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <th style="padding: 12px 15px; text-align: left; font-weight: 600; border-radius: 8px 0 0 0;">Book Title</th>
                    <th style="padding: 12px 15px; text-align: left; font-weight: 600;">Author</th>
                    <th style="padding: 12px 15px; text-align: left; font-weight: 600;">Borrowed</th>
                    <th style="padding: 12px 15px; text-align: left; font-weight: 600;">Due Date</th>
                    <th style="padding: 12px 15px; text-align: left; font-weight: 600;">Returned</th>
                    <th style="padding: 12px 15px; text-align: center; font-weight: 600; border-radius: 0 8px 0 0;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($borrow = $borrowing_result->fetch_assoc()): ?>
                <tr style="border-bottom: 1px solid #f0f0f0; transition: background 0.2s;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background=''">
                    <td style="padding: 15px; font-weight: 500; color: #333;"><?php echo htmlspecialchars($borrow['title']); ?></td>
                    <td style="padding: 15px; color: #666;"><?php echo htmlspecialchars($borrow['author']); ?></td>
                    <td style="padding: 15px; color: #666;"><?php echo date('M d, Y', strtotime($borrow['borrow_date'])); ?></td>
                    <td style="padding: 15px; color: #666;"><?php echo date('M d, Y', strtotime($borrow['due_date'])); ?></td>
                    <td style="padding: 15px; color: #666;"><?php echo $borrow['return_date'] ? date('M d, Y', strtotime($borrow['return_date'])) : '<span style="color: #999;">-</span>'; ?></td>
                    <td style="padding: 15px; text-align: center;">
                        <?php 
                        if ($borrow['status'] === 'returned') {
                            echo '<span style="background: #4CAF50; color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px;"><span>✅</span> Returned</span>';
                        } else {
                            $overdue = strtotime($borrow['due_date']) < time();
                            if ($overdue) {
                                echo '<span style="background: #f44336; color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px;"><span>⚠️</span> Overdue</span>';
                            } else {
                                echo '<span style="background: #2196F3; color: white; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; display: inline-flex; align-items: center; gap: 4px;"><span>📖</span> Active</span>';
                            }
                        }
                        ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 40px 20px; color: #999; background: #f8f9fa; border-radius: 8px;">
            <div style="font-size: 3rem; margin-bottom: 10px;">📚</div>
            <p style="margin: 0; font-size: 1.1rem;">No borrowing history found.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Reviews -->
    <div style="background: white; border-radius: 16px; padding: 35px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
        <h2 style="margin-top: 0; color: #333; display: flex; align-items: center; gap: 10px; font-size: 1.75rem;"><span style="font-size: 1.5rem;">⭐</span> Book Reviews</h2>
        <?php if ($reviews_result->num_rows > 0): ?>
        <div style="display: grid; gap: 20px;">
            <?php while ($review = $reviews_result->fetch_assoc()): ?>
            <div style="border: 1px solid #e0e0e0; border-radius: 12px; padding: 20px; background: linear-gradient(to bottom, #ffffff 0%, #f8f9fa 100%); transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.05);" onmouseover="this.style.boxShadow='0 4px 16px rgba(102, 126, 234, 0.15)'; this.style.borderColor='#667eea'" onmouseout="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)'; this.style.borderColor='#e0e0e0'">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; gap: 15px;">
                    <div style="flex: 1;">
                        <strong style="color: #333; font-size: 1.15rem; display: block; margin-bottom: 5px;"><?php echo htmlspecialchars($review['title']); ?></strong>
                        <div style="color: #666; font-size: 0.95rem; display: flex; align-items: center; gap: 5px;">
                            <span style="font-size: 1rem;">✍️</span>
                            <span>by <?php echo htmlspecialchars($review['author']); ?></span>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; padding: 8px 14px; border-radius: 20px; font-size: 1.1rem; box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3); white-space: nowrap;">
                        <?php echo str_repeat('⭐', $review['rating']); ?>
                    </div>
                </div>
                <?php if (!empty($review['comment'])): ?>
                <p style="margin: 12px 0; color: #555; line-height: 1.6; padding: 12px; background: white; border-radius: 8px; border-left: 3px solid #667eea;"><?php echo htmlspecialchars($review['comment']); ?></p>
                <?php endif; ?>
                <div style="display: flex; align-items: center; gap: 6px; margin-top: 10px;">
                    <span style="font-size: 1rem;">📅</span>
                    <small style="color: #999; font-size: 0.9rem;"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 50px 20px; color: #999; background: #f8f9fa; border-radius: 12px;">
            <div style="font-size: 4rem; margin-bottom: 15px; opacity: 0.5;">⭐</div>
            <p style="margin: 0; font-size: 1.15rem; font-weight: 500;">No reviews yet.</p>
            <p style="margin: 8px 0 0 0; font-size: 0.95rem; color: #bbb;">Reviews will appear here once submitted.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Avatar Upload Modal (Admin and Users viewing own profile) -->
<?php if ($_SESSION['role'] === 'admin' || $member_id == $_SESSION['user_id']): ?>
<div id="avatarModal" class="modal">
    <div class="modal-content" style="border-radius: 16px; max-width: 600px; padding: 0; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px 30px; position: relative;">
            <h2 style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 1.6rem;">📷 Edit Profile Photo</h2>
            <span class="modal-close" onclick="closeAvatarModal()" style="position: absolute; top: 20px; right: 25px; color: white; font-size: 2rem; opacity: 0.9; cursor: pointer;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">&times;</span>
        </div>
        <form id="avatarForm" method="POST" enctype="multipart/form-data" style="padding: 30px;">
            <input type="hidden" name="update_avatar" value="1">
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label style="font-weight: 600; color: #333; margin-bottom: 12px; display: block; font-size: 1.05rem;">Photo Source</label>
                <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:center; margin-bottom:15px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor: pointer; padding: 10px 16px; border: 2px solid #667eea; border-radius: 10px; background: white; transition: all 0.3s;" onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background='white'">
                        <input type="radio" name="avatar_source" id="avatar_source_upload" value="upload" checked style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="font-weight: 500;">📁 Upload from computer</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor: pointer; padding: 10px 16px; border: 2px solid #667eea; border-radius: 10px; background: white; transition: all 0.3s;" onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background='white'">
                        <input type="radio" name="avatar_source" id="avatar_source_url" value="url" style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="font-weight: 500;">🔗 Use a URL/link</span>
                    </label>
                </div>
                
                <div id="avatarUploadContainer">
                    <input type="file" id="avatar_file" name="avatar_file" accept="image/*" style="padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; width: 100%; font-size: 0.95rem;">
                    <small style="color:#666; display: block; margin-top: 8px; line-height: 1.5;">Accepted formats: JPG, PNG, WEBP, GIF. Max 5MB. <strong style="color: #667eea;">Or paste (Ctrl+V) below</strong></small>
                    <div id="avatarPasteZone" style="margin-top:12px; padding:30px 20px; border:2px dashed #667eea; border-radius:12px; text-align:center; color:#667eea; background:#f0f4ff; cursor: pointer; transition: all 0.3s; font-weight: 500;" onmouseover="this.style.borderColor='#764ba2'; this.style.background='#e8e4ff'" onmouseout="this.style.borderColor='#667eea'; this.style.background='#f0f4ff'">
                        📋 Click here and press Ctrl+V to paste an image or screenshot
                    </div>
                </div>
                
                <div id="avatarUrlContainer" style="display:none;">
                    <input type="url" id="avatar_url" name="avatar_url" placeholder="https://example.com/photo.jpg" style="padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; width: 100%; font-size: 0.95rem;">
                    <small style="color:#666; display: block; margin-top: 8px;">Provide a direct image link (JPG/PNG/WebP/GIF).</small>
                </div>
                
                <div id="avatarPreview" style="margin-top:20px; display:none; text-align:center; padding: 20px; background: #f8f9fa; border-radius: 12px;">
                    <img id="avatarPreviewImg" src="" alt="Avatar preview" style="max-height:200px; border-radius:50%; border:4px solid #667eea; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.3)'">💾 Save Photo</button>
                <button type="button" class="btn btn-secondary" onclick="closeAvatarModal()" style="padding: 12px 24px; background: #e0e0e0; border: none; border-radius: 10px; color: #666; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='#d0d0d0'" onmouseout="this.style.background='#e0e0e0'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Avatar source toggle
document.getElementById('avatar_source_upload')?.addEventListener('change', () => {
    document.getElementById('avatarUploadContainer').style.display = '';
    document.getElementById('avatarUrlContainer').style.display = 'none';
    document.getElementById('avatar_url').value = '';
});
document.getElementById('avatar_source_url')?.addEventListener('change', () => {
    document.getElementById('avatarUploadContainer').style.display = 'none';
    document.getElementById('avatarUrlContainer').style.display = '';
    document.getElementById('avatar_file').value = '';
    const avatarPrev = document.getElementById('avatarPreview');
    if (avatarPrev) avatarPrev.style.display = 'none';
});

const avatarFileInput = document.getElementById('avatar_file');
const avatarUrlInput = document.getElementById('avatar_url');
const avatarPrev = document.getElementById('avatarPreview');
const avatarPrevImg = document.getElementById('avatarPreviewImg');

avatarFileInput?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = () => {
            avatarPrevImg.src = reader.result;
            avatarPrev.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        avatarPrev.style.display = 'none';
        avatarPrevImg.src = '';
    }
});

avatarUrlInput?.addEventListener('input', (e) => {
    const url = e.target.value.trim();
    if (url) {
        avatarPrevImg.src = url;
        avatarPrev.style.display = 'block';
    } else {
        avatarPrev.style.display = 'none';
        avatarPrevImg.src = '';
    }
});

// Clipboard paste support
const avatarPasteZone = document.getElementById('avatarPasteZone');
let avatarPastedFile = null;

avatarPasteZone?.addEventListener('paste', (e) => {
    e.preventDefault();
    const items = e.clipboardData?.items;
    if (!items) return;
    
    for (let i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
            const blob = items[i].getAsFile();
            if (blob) {
                avatarPastedFile = new File([blob], `avatar-pasted-${Date.now()}.png`, { type: blob.type });
                
                const reader = new FileReader();
                reader.onload = () => {
                    avatarPrevImg.src = reader.result;
                    avatarPrev.style.display = 'block';
                    avatarPasteZone.style.borderColor = '#4CAF50';
                    avatarPasteZone.style.background = '#e8f5e9';
                    avatarPasteZone.innerHTML = '✅ Image pasted! Ready to save.';
                };
                reader.readAsDataURL(blob);
            }
            break;
        }
    }
});

avatarPasteZone?.setAttribute('tabindex', '0');
avatarPasteZone?.addEventListener('click', () => avatarPasteZone.focus());

// Form submission with pasted file support
document.getElementById('avatarForm')?.addEventListener('submit', (e) => {
    const sourceEl = document.querySelector('input[name="avatar_source"]:checked');
    if (avatarPastedFile && sourceEl?.value === 'upload') {
        e.preventDefault();
        const formData = new FormData(e.target);
        formData.set('avatar_file', avatarPastedFile);
        
        fetch('profile.php', { method: 'POST', body: formData })
        .then(() => window.location.reload())
        .catch(() => alert('Error uploading avatar.'));
    }
});

function openAvatarModal() {
    document.getElementById('avatarModal').style.display = 'block';
    avatarPastedFile = null;
    
    const avatarPrev = document.getElementById('avatarPreview');
    if (avatarPrev) avatarPrev.style.display = 'none';
    
    const pasteZone = document.getElementById('avatarPasteZone');
    if (pasteZone) {
        pasteZone.style.borderColor = '#667eea';
        pasteZone.style.background = '#f0f4ff';
        pasteZone.innerHTML = '📋 Click here and press Ctrl+V to paste an image or screenshot';
    }
    
    const uploadRadio = document.getElementById('avatar_source_upload');
    if (uploadRadio) {
        uploadRadio.checked = true;
        document.getElementById('avatarUploadContainer').style.display = '';
        document.getElementById('avatarUrlContainer').style.display = 'none';
    }
}

function closeAvatarModal() {
    document.getElementById('avatarModal').style.display = 'none';
    document.getElementById('avatarForm').reset();
}

window.onclick = function(event) {
    const modal = document.getElementById('avatarModal');
    if (event.target == modal) {
        closeAvatarModal();
    }
}
</script>
<?php endif; ?>

<!-- Password Change Modal -->
<div id="passwordModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.5);">
    <div class="modal-content" style="background-color: white; margin: 10% auto; padding: 0; border-radius: 16px; width: 90%; max-width: 500px; box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3); overflow: hidden;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px 30px; position: relative;">
            <h2 style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 1.6rem;">🔒 Change Password</h2>
            <span class="modal-close" onclick="closePasswordModal()" style="position: absolute; top: 20px; right: 25px; color: white; font-size: 2rem; opacity: 0.9; cursor: pointer; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">&times;</span>
        </div>
        <form method="POST" action="" id="passwordForm" style="padding: 30px;">
            <input type="hidden" name="change_password" value="1">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="current_password" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Current Password *</label>
                <input type="password" id="current_password" name="current_password" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="new_password" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">New Password *</label>
                <input type="password" id="new_password" name="new_password" required minlength="6" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                <small style="color: #777; display: block; margin-top: 5px;">At least 6 characters</small>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="confirm_password" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Confirm New Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
            </div>
            
            <div style="display: flex; gap: 12px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.3)'">🔒 Change Password</button>
                <button type="button" class="btn btn-secondary" onclick="closePasswordModal()" style="padding: 12px 24px; background: #e0e0e0; border: none; border-radius: 10px; color: #666; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='#d0d0d0'" onmouseout="this.style.background='#e0e0e0'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPasswordModal() {
    document.getElementById('passwordModal').style.display = 'block';
}

function closePasswordModal() {
    document.getElementById('passwordModal').style.display = 'none';
    document.getElementById('passwordForm').reset();
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const passwordModal = document.getElementById('passwordModal');
    if (event.target == passwordModal) {
        closePasswordModal();
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePasswordModal();
    }
});

// Validate password confirmation
document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass !== confirmPass) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
    }
});
</script>

<?php include 'includes/footer.php'; ?>