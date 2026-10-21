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

        // Insert into database - only if no errors so far
        if (!isset($_SESSION['error_message'])) {
            $stmt = $conn->prepare("INSERT INTO books (title, author, isbn, category, quantity, available_quantity, shelf_number, shelf_row, cover_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssssiisss", $title, $author, $isbn, $category, $quantity, $quantity, $shelf_number, $shelf_row, $final_cover_url);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = 'Book added successfully!';
                } else {
                    $_SESSION['error_message'] = 'Error adding book: ' . $conn->error;
                }
                $stmt->close();
            } else {
                // Fallback for environments where new columns aren't present yet
                $stmt = $conn->prepare("INSERT INTO books (title, author, isbn, category, quantity, available_quantity) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("ssssii", $title, $author, $isbn, $category, $quantity, $quantity);
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = 'Book added successfully!';
                    } else {
                        $_SESSION['error_message'] = 'Error adding book: ' . $conn->error;
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error_message'] = 'Error preparing book insert. Please check DB connection.';
                }
            }
        }
    }
    
    header("Location: books.php");
    exit();
}

include 'includes/header.php';
?>

<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px 0; margin: -20px -20px 30px -20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <h1 style="color: white; margin: 0; text-align: center; font-size: 2.5rem; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><span style="font-size: 2rem;">📚</span> Books Management</h1>
    <p style="color: rgba(255,255,255,0.9); text-align: center; margin: 10px 0 0 0; font-size: 1.1rem;">Manage your library collection</p>
</div>

<?php
// Display success/error messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success" style="animation: slideIn 0.3s ease-out;">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger" style="animation: slideIn 0.3s ease-out;">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}

if ($db_error) {
    echo '<div class="alert alert-danger">Database connection error. Please check your configuration.</div>';
}
?>

<style>
@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

<div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h2 style="margin: 0; color: #333; font-size: 1.5rem; display: flex; align-items: center; gap: 10px;"><span style="color: #667eea; font-size: 1.3rem;">📖</span> Books Collection</h2>
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <label for="sortOrder" style="font-weight: 600; color: #555; display: flex; align-items: center; gap: 8px;"><span style="font-size: 0.85rem;">🔽</span> Sort by:</label>
            <select id="sortOrder" onchange="sortBooks()" style="padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; background: white; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#667eea'" onmouseout="this.style.borderColor='#e0e0e0'">
                <option value="title">📝 Title (A-Z)</option>
                <option value="author">✍️ Author (A-Z)</option>
                <option value="rating">⭐ Rating (High to Low)</option>
                <option value="reviews">💬 Most Reviewed</option>
            </select>
            <div style="display: flex; gap: 5px; border: 2px solid #e0e0e0; border-radius: 8px; padding: 5px; background: white;">
                <button id="viewGridBtn" onclick="switchView('grid')" style="padding: 8px 12px; border: none; background: #667eea; color: white; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.2s; font-size: 0.9rem;" title="Grid View">⊞ Grid</button>
                <button id="viewListBtn" onclick="switchView('list')" style="padding: 8px 12px; border: none; background: transparent; color: #667eea; cursor: pointer; font-weight: 600; transition: all 0.2s; font-size: 0.9rem;" title="List View">≡ List</button>
            </div>
            <?php if (is_staff()): ?>
            <button class="btn btn-success" onclick="openBookModal()" style="padding: 10px 20px; font-size: 1rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(76,175,80,0.3); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(76,175,80,0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(76,175,80,0.3)'">➕ Add New Book</button>
            <button class="btn btn-info" onclick="openCSVImportModal()" style="padding: 10px 20px; font-size: 1rem; border-radius: 8px; background: #2196F3; color: white; box-shadow: 0 2px 8px rgba(33,150,243,0.3); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(33,150,243,0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(33,150,243,0.3)'">📥 Import CSV</button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <!-- Bulk Delete Controls (Admin Only) -->
    <div id="bulkDeleteControls" style="margin-top: 15px; padding: 15px; background: linear-gradient(135deg, #fff5f5 0%, #ffe5e5 100%); border-radius: 10px; border: 2px solid #ffcccc; display: none;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; color: #d32f2f; cursor: pointer;">
                    <input type="checkbox" id="selectAllBooks" onchange="toggleSelectAll()" style="width: 18px; height: 18px; cursor: pointer;">
                    <span>Select All</span>
                </label>
                <span id="selectedCount" style="color: #666; font-size: 0.95rem;">0 books selected</span>
            </div>
            <button id="deleteSelectedBtn" onclick="deleteSelectedBooks()" disabled style="padding: 10px 20px; font-size: 1rem; border-radius: 8px; background: #f44336; color: white; border: none; cursor: pointer; transition: all 0.2s; opacity: 0.5;" onmouseover="if(!this.disabled) { this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(244,67,54,0.4)'; }" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">🗑️ Delete Selected</button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Search Bar -->
<div style="margin-bottom: 30px;">
    <div style="display: flex; gap: 10px; max-width: 600px; background: white; padding: 8px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
        <input type="text" id="searchInput" placeholder="🔍 Search by book title..." 
               style="flex: 1; padding: 12px 18px; border: 2px solid #f0f0f0; border-radius: 8px; font-size: 1rem; transition: all 0.2s;"
               onkeyup="if(event.key==='Enter') searchBooks()"
               onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'"
               onblur="this.style.borderColor='#f0f0f0'; this.style.boxShadow='none'">
        <button class="btn btn-primary" onclick="searchBooks()" style="padding: 12px 24px; border-radius: 8px; font-weight: 600; box-shadow: 0 2px 8px rgba(33,150,243,0.3); transition: all 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">Search</button>
        <button class="btn btn-secondary" onclick="clearSearch()" style="padding: 12px 24px; border-radius: 8px; transition: all 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">Clear</button>
    </div>
</div>

<!-- Book Modal -->
<div id="bookModal" class="modal">
    <div class="modal-content" style="border-radius: 16px; max-width: 650px; padding: 0; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px 30px; position: relative;">
            <h2 id="bookModalTitle" style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 1.6rem;">➕ Add New Book</h2>
            <span class="modal-close" onclick="closeBookModal()" style="position: absolute; top: 20px; right: 25px; color: white; font-size: 2rem; opacity: 0.9; cursor: pointer;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">&times;</span>
        </div>
        <form id="bookForm" onsubmit="saveBook(event)" enctype="multipart/form-data" style="padding: 30px;">
            <input type="hidden" id="edit_book_id" name="book_id" value="">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="title" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Title *</label>
                <input type="text" id="title" name="title" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="author" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Author *</label>
                    <input type="text" id="author" name="author" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="isbn" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">ISBN</label>
                    <input type="text" id="isbn" name="isbn" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="category" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Category</label>
                    <input type="text" id="category" name="category" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="quantity" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Quantity *</label>
                    <input type="number" id="quantity" name="quantity" required min="0" value="1" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="shelf_number" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Shelf Number</label>
                    <input type="text" id="shelf_number" name="shelf_number" placeholder="e.g., 2 or A-5" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="shelf_row" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Row</label>
                    <input type="text" id="shelf_row" name="shelf_row" maxlength="5" placeholder="e.g., B" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: 600; color: #333; margin-bottom: 12px; display: block; font-size: 1.05rem;">Cover Image</label>
                <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:center; margin-bottom:15px;">
                    <label style="display:flex; align-items:center; gap:8px; cursor: pointer; padding: 10px 16px; border: 2px solid #667eea; border-radius: 10px; background: white; transition: all 0.3s;" onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background='white'">
                        <input type="radio" name="cover_source" id="cover_source_upload" value="upload" checked style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="font-weight: 500;">📁 Upload from computer</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px; cursor: pointer; padding: 10px 16px; border: 2px solid #667eea; border-radius: 10px; background: white; transition: all 0.3s;" onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background='white'">
                        <input type="radio" name="cover_source" id="cover_source_url" value="url" style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="font-weight: 500;">🔗 Use a URL/link</span>
                    </label>
                </div>
                <div id="coverUploadContainer">
                    <input type="file" id="cover_file" name="cover_file" accept="image/*" style="padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; width: 100%; font-size: 0.95rem;">
                    <small style="color:#666; display: block; margin-top: 8px; line-height: 1.5;">Accepted formats: JPG, PNG, WEBP, GIF. Max 5MB. <strong style="color: #667eea;">Or paste (Ctrl+V)</strong></small>
                    <div id="pasteZone" style="margin-top:12px; padding:30px 20px; border:2px dashed #667eea; border-radius:12px; text-align:center; color:#667eea; background:#f0f4ff; cursor: pointer; transition: all 0.3s; font-weight: 500;" onmouseover="this.style.borderColor='#764ba2'; this.style.background='#e8e4ff'" onmouseout="this.style.borderColor='#667eea'; this.style.background='#f0f4ff'">
                        📋 Click here and press Ctrl+V to paste an image
                    </div>
                </div>
                <div id="coverUrlContainer" style="display:none;">
                    <input type="url" id="cover_url" name="cover_url" placeholder="https://example.com/cover.jpg" style="padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; width: 100%; font-size: 0.95rem;">
                    <small style="color:#666; display: block; margin-top: 8px;">Provide a direct image link (JPG/PNG/WebP). Optional.</small>
                </div>
                <div id="coverPreview" style="margin-top:20px; display:none; text-align:center; padding: 20px; background: #f8f9fa; border-radius: 12px;">
                    <img id="coverPreviewImg" src="" alt="Cover preview" style="max-height:200px; border-radius:8px; border:3px solid #667eea; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.3)'">💾 Save Book</button>
                <button type="button" id="deleteBookBtn" class="btn btn-danger" onclick="deleteBook()" style="display: none; padding: 12px 24px; background: #ff6b6b; border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(255, 107, 107, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(255, 107, 107, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(255, 107, 107, 0.3)'">🗑️ Delete Book</button>
                <button type="button" class="btn btn-secondary" onclick="closeBookModal()" style="padding: 12px 24px; background: #e0e0e0; border: none; border-radius: 10px; color: #666; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='#d0d0d0'" onmouseout="this.style.background='#e0e0e0'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Borrowers Modal -->
<div id="borrowersModal" class="modal">
    <div class="modal-content" style="max-width: 800px; border-radius: 16px; padding: 0; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px 30px; position: relative;">
            <h2 id="borrowersTitle" style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 1.6rem;">👥 Current Borrowers</h2>
            <span class="modal-close" onclick="closeBorrowersModal()" style="position: absolute; top: 20px; right: 25px; color: white; font-size: 2rem; opacity: 0.9; cursor: pointer;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">&times;</span>
        </div>
        <div style="padding: 30px;">
            <div id="availabilityInfo" style="background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #667eea; font-weight: 500; color: #333;"></div>
            <div id="borrowersContent">
                <p style="text-align: center; color: #999; padding: 20px 0;">Loading...</p>
            </div>
        </div>
    </div>
</div>

<!-- Book Info Modal -->
<div id="bookInfoModal" class="modal">
    <div class="modal-content" style="max-width: 600px; border-radius: 16px; padding: 0; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px 30px; position: relative;">
            <h2 id="bookInfoTitle" style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 1.6rem;">📋 Book Information</h2>
            <span class="modal-close" onclick="closeBookInfoModal()" style="position: absolute; top: 20px; right: 25px; color: white; font-size: 2rem; opacity: 0.9; cursor: pointer;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">&times;</span>
        </div>
        <div id="bookInfoContent" style="margin-top: 0; padding: 30px;">
            <p style="text-align: center; color: #999; padding: 20px 0;">Loading...</p>
        </div>
    </div>
</div>

<!-- Reviews Modal -->
<div id="reviewsModal" class="modal">
    <div class="modal-content" style="max-width: 900px; border-radius: 16px; padding: 0; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px 30px; position: relative;">
            <h2 id="reviewsTitle" style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 1.6rem;">⭐ Book Reviews</h2>
            <span class="modal-close" onclick="closeReviewsModal()" style="position: absolute; top: 20px; right: 25px; color: white; font-size: 2rem; opacity: 0.9; cursor: pointer;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">&times;</span>
        </div>
        <div style="padding: 30px;">
        <!-- Add Review Form -->
        <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 2px solid #e0e0e0;">
            <h3 style="margin-top: 0; color: #333; font-size: 1.2rem; display: flex; align-items: center; gap: 8px;">✍️ Leave a Review</h3>
            <form id="reviewForm" onsubmit="submitReview(event)">
                <input type="hidden" id="review_book_id" name="book_id">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="rating" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Rating *</label>
                    <select id="rating" name="rating" required style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; cursor: pointer;">
                        <option value="">Select rating</option>
                        <option value="5">⭐⭐⭐⭐⭐ (5 stars)</option>
                        <option value="4">⭐⭐⭐⭐ (4 stars)</option>
                        <option value="3">⭐⭐⭐ (3 stars)</option>
                        <option value="2">⭐⭐ (2 stars)</option>
                        <option value="1">⭐ (1 star)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="comment" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Comment</label>
                    <textarea id="comment" name="comment" rows="3" placeholder="Share your thoughts about this book..." style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; resize: vertical; font-family: inherit;"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 10px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(102, 126, 234, 0.3)'">💬 Submit Review</button>
            </form>
        </div>
        
        <!-- Reviews List -->
        <div id="reviewsContent">
            <p style="text-align: center; color: #999; padding: 20px 0;">Loading...</p>
        </div>
        </div>
    </div>
</div>

<!-- CSV Import Modal -->
<div id="csvImportModal" class="modal">
    <div class="modal-content" style="border-radius: 16px; max-width: 700px; padding: 0; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); padding: 25px 30px; position: relative;">
            <h2 style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 1.6rem;">📥 Import Books from CSV</h2>
            <span class="modal-close" onclick="closeCSVImportModal()" style="position: absolute; top: 20px; right: 25px; color: white; font-size: 2rem; opacity: 0.9; cursor: pointer;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">&times;</span>
        </div>
        <div style="padding: 30px;">
            <div style="background: #e3f2fd; padding: 15px; border-radius: 10px; border-left: 4px solid #2196F3; margin-bottom: 20px;">
                <p style="margin: 0; color: #1565c0; font-weight: 500;">📋 CSV Format Guide:</p>
                <small style="color: #0d47a1; display: block; margin-top: 8px; line-height: 1.6;">
                    Your CSV file must have these columns in order:<br>
                    <strong>title, author, isbn, category, quantity, shelf_number, shelf_row</strong><br>
                    Example: "The Great Gatsby,F. Scott Fitzgerald,9780743273565,Fiction,5,2,A"<br>
                    All columns except shelf_number and shelf_row are required.
                </small>
                <button type="button" onclick="downloadCSVTemplate()" style="margin-top: 12px; padding: 8px 16px; background: #4caf50; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 6px rgba(76, 175, 80, 0.3);" onmouseover="this.style.background='#45a049'; this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 10px rgba(76, 175, 80, 0.4)'" onmouseout="this.style.background='#4caf50'; this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 6px rgba(76, 175, 80, 0.3)'">📥 Download CSV Template</button>
            </div>
            
            <form id="csvImportForm" onsubmit="importCSV(event)" style="margin-bottom: 20px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="csvFile" style="font-weight: 600; color: #333; margin-bottom: 12px; display: block;">Select CSV File *</label>
                    <input type="file" id="csvFile" name="csvFile" accept=".csv" required style="padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; width: 100%; font-size: 0.95rem; cursor: pointer;">
                    <small style="color: #666; display: block; margin-top: 8px;">Supported: CSV files only. Max 10MB.</small>
                </div>
                
                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px 24px; background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(33, 150, 243, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(33, 150, 243, 0.3)'">⬆️ Upload & Import</button>
                    <button type="button" class="btn btn-secondary" onclick="closeCSVImportModal()" style="padding: 12px 24px; background: #e0e0e0; border: none; border-radius: 10px; color: #666; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='#d0d0d0'" onmouseout="this.style.background='#e0e0e0'">Cancel</button>
                </div>
            </form>
            
            <div id="importProgress" style="display: none; margin-top: 20px;">
                <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; text-align: center;">
                    <p style="margin: 0 0 10px 0; color: #333; font-weight: 500;">Processing...</p>
                    <div style="width: 100%; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                        <div id="importProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #2196F3, #1976D2); transition: width 0.3s;"></div>
                    </div>
                </div>
            </div>
            
            <div id="importResults" style="display: none; margin-top: 20px; padding: 15px; border-radius: 8px; border: 2px solid #4caf50; background: #f1f8e9;">
                <p style="margin: 0 0 10px 0; color: #33691e; font-weight: 600;">✅ Import Successful!</p>
                <div id="importResultsText" style="color: #558b2f; font-size: 0.95rem;"></div>
                <button type="button" onclick="location.reload()" style="margin-top: 15px; padding: 10px 20px; background: #4caf50; border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='#45a049'" onmouseout="this.style.background='#4caf50'">🔄 Reload Page</button>
            </div>
        </div>
    </div>
</div>

<!-- Books Grid -->
<div id="booksGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; margin-top: 20px; padding: 10px;">
    <?php
    if (!$db_error && $conn) {
        $sql = "SELECT b.*, 
                COALESCE(AVG(r.rating), 0) as avg_rating,
                COUNT(r.id) as review_count
                FROM books b
                LEFT JOIN book_reviews r ON b.id = r.book_id
                GROUP BY b.id
                ORDER BY b.title ASC";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($book = $result->fetch_assoc()) {
                $avg_rating = round($book['avg_rating'], 1);
                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    $stars .= $i <= round($avg_rating) ? '⭐' : '☆';
                }
                $rating_text = $avg_rating > 0 ? $avg_rating . ' ' : '';
                
                $cover = isset($book['cover_url']) && $book['cover_url'] ? htmlspecialchars($book['cover_url']) : '';
                $title = htmlspecialchars($book['title']);
                $author = htmlspecialchars($book['author']);
                
                echo "<div class='book-card' data-book-id='" . $book['id'] . "' data-title='" . strtolower($title) . "' data-author='" . strtolower($author) . "' data-rating='" . $avg_rating . "' data-reviews='" . $book['review_count'] . "' data-available-qty='" . $book['available_quantity'] . "' style='display: flex; flex-direction: column; background: white; border: none; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.08); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; height: 100%;' onmouseover='this.style.transform=\"translateY(-8px)\"; this.style.boxShadow=\"0 12px 32px rgba(102,126,234,0.25)\"' onmouseout='this.style.transform=\"translateY(0)\"; this.style.boxShadow=\"0 4px 16px rgba(0,0,0,0.08)\"'>";
                
                // Cover Image
                echo "<div style='position: relative; width: 100%; height: 320px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0;'>";
                
                // Add checkbox for admin users (top-left corner)
                if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                    echo "<div style='position: absolute; top: 12px; left: 12px; z-index: 10;'>";
                    echo "<input type='checkbox' class='book-select-checkbox' data-book-id='" . $book['id'] . "' onclick='event.stopPropagation(); updateBulkDeleteUI();' style='width: 24px; height: 24px; cursor: pointer; accent-color: #f44336; border-radius: 4px;'>";
                    echo "</div>";
                }
                
                if ($cover) {
                    echo "<img src='" . $cover . "' alt='" . $title . "' style='width: 100%; height: 100%; object-fit: cover;' onerror=\"this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';\">";
                    echo "<div style='display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: white; font-size: 3rem; opacity: 0.3;'>📖</div>";
                } else {
                    echo "<div style='color: white; font-size: 3rem; opacity: 0.3;'>📖</div>";
                }
                echo "</div>";
                
                // Book Info
                echo "<div style='padding: 20px; display: flex; flex-direction: column; flex-grow: 1;'>";
                echo "<h3 style='margin: 0 0 8px 0; font-size: 1.05rem; font-weight: 600; color: #333; line-height: 1.4; min-height: 2.8em; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;'>" . $title . "</h3>";
                echo "<p style='margin: 0 0 12px 0; color: #666; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>by " . $author . "</p>";
                
                // Rating
                echo "<div style='margin-bottom: 14px; font-size: 0.85rem; color: #f39c12; font-weight: 500;'>" . $rating_text . $stars . "</div>";
                
                // Action Buttons - push to bottom
                echo "<div style='margin-top: auto;'>";
                echo "<div style='display: flex; gap: 6px; margin-bottom: 6px;'>";
                echo "<button onclick='event.stopPropagation(); viewBookInfo(" . $book['id'] . ")' class='btn btn-small btn-success' style='flex: 1; font-size: 0.8rem; padding: 8px 10px; border-radius: 8px; font-weight: 500; transition: all 0.2s; white-space: nowrap;' onmouseover='this.style.transform=\"scale(1.05)\"' onmouseout='this.style.transform=\"scale(1)\"'>ℹ️ Info</button>";
                if (is_staff()) {
                    echo "<button onclick='event.stopPropagation(); editBook(" . $book['id'] . ")' class='btn btn-small btn-primary' style='flex: 1; font-size: 0.8rem; padding: 8px 10px; border-radius: 8px; font-weight: 500; transition: all 0.2s;' onmouseover='this.style.transform=\"scale(1.05)\"' onmouseout='this.style.transform=\"scale(1)\"'>✏️ Edit</button>";
                }
                echo "</div>";
                echo "<div style='display: flex; gap: 6px;'>";
                echo "<button onclick='event.stopPropagation(); viewReviews(" . $book['id'] . ", \"" . htmlspecialchars($book['title'], ENT_QUOTES) . "\")' class='btn btn-small btn-warning' style='flex: 1; font-size: 0.8rem; padding: 8px 10px; border-radius: 8px; font-weight: 500; transition: all 0.2s; white-space: nowrap;' onmouseover='this.style.transform=\"scale(1.05)\"' onmouseout='this.style.transform=\"scale(1)\"'>⭐ Reviews</button>";
                if (is_staff()) {
                    echo "<button onclick='event.stopPropagation(); viewBorrowers(" . $book['id'] . ", \"" . htmlspecialchars($book['title'], ENT_QUOTES) . "\", " . $book['available_quantity'] . ")' class='btn btn-small btn-info' style='flex: 1; font-size: 0.8rem; padding: 8px 10px; border-radius: 8px; font-weight: 500; transition: all 0.2s; white-space: nowrap;' onmouseover='this.style.transform=\"scale(1.05)\"' onmouseout='this.style.transform=\"scale(1)\"'>👥 Borrowers</button>";
                }
                echo "</div>";
                echo "</div>";
                echo "</div>";
                
                echo "</div>";
            }
        } else {
            echo "<div style='grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: #999;'><p style='font-size: 1.2rem;'>📚 No books found. Click 'Add New' to add your first book!</p></div>";
        }
    } else {
        echo "<div style='grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: #e74c3c;'><p>Database connection error.</p></div>";
    }
    ?>
</div>

<div id="noResultsMessage" style="display: none; text-align: center; padding: 80px 20px; color: #999;">
    <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">📚</div>
    <p style="font-size: 1.3rem; margin: 0; font-weight: 500;">No books found matching your search.</p>
    <p style="font-size: 1rem; margin: 10px 0 0 0; color: #bbb;">Try adjusting your search terms.</p>
</div>

<script>
// Check if current user is admin
const isAdmin = <?php echo is_admin() ? 'true' : 'false'; ?>;
const isStaff = <?php echo is_staff() ? 'true' : 'false'; ?>;
let booksOriginalGridHTML = '';
let booksCurrentView = 'grid';

function switchView(view) {
    // Store checkbox states before switching views
    const currentCheckboxes = document.querySelectorAll('.book-select-checkbox:checked');
    const checkedBooks = new Set(Array.from(currentCheckboxes).map(cb => cb.getAttribute('data-book-id')));
    
    const grid = document.getElementById('booksGrid');
    const cards = Array.from(document.querySelectorAll('.book-card'));
    const gridBtn = document.getElementById('viewGridBtn');
    const listBtn = document.getElementById('viewListBtn');
    
    if (view === 'grid') {
        // Switch to grid view
        if (booksOriginalGridHTML) {
            grid.innerHTML = booksOriginalGridHTML;
            booksOriginalGridHTML = '';
        }

        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(240px, 1fr))';
        grid.style.gap = '30px';
        grid.style.flexDirection = '';
        
        const refreshedCards = grid.querySelectorAll('.book-card');
        refreshedCards.forEach(card => {
            card.style.display = 'block';
            // Restore checkbox state for grid view
            if (isAdmin) {
                const checkbox = card.querySelector('.book-select-checkbox');
                if (checkbox) {
                    const bookId = checkbox.getAttribute('data-book-id');
                    checkbox.checked = checkedBooks.has(bookId);
                }
            }
        });
        
        gridBtn.style.background = '#667eea';
        gridBtn.style.color = 'white';
        listBtn.style.background = 'transparent';
        listBtn.style.color = '#667eea';
        
        booksCurrentView = 'grid';
        localStorage.setItem('booksViewMode', 'grid');
        
        // Update bulk delete UI after switching to grid view
        updateBulkDeleteUI();
    } else if (view === 'list') {
        // Switch to list view
        if (!booksOriginalGridHTML) {
            booksOriginalGridHTML = grid.innerHTML;
        }

        grid.style.display = 'flex';
        grid.style.gridTemplateColumns = '';
        grid.style.gap = '12px';
        grid.style.flexDirection = 'column';
        
        const listContainer = document.createElement('div');
        listContainer.style.cssText = 'display: flex; flex-direction: column; gap: 12px; width: 100%;';
        
        cards.forEach(card => {
            // respect current visibility (e.g., search filter)
            if (card.style.display === 'none') return;

            const bookId = card.getAttribute('data-book-id');
            const title = card.querySelector('h3')?.textContent || '';
            const author = card.querySelector('p')?.textContent || '';
            const coverEl = card.querySelector('img');
            const cover = coverEl ? coverEl.src : null;
            const rating = card.getAttribute('data-rating') || '0';
            const reviews = card.getAttribute('data-reviews') || '0';
            const isTopRated = parseFloat(rating) >= 4.5;

            const listItem = document.createElement('div');
            listItem.style.cssText = 'display: flex; align-items: stretch; gap: 0; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.3s; border: 2px solid transparent;';
            
            listItem.onmouseover = function() { 
                this.style.transform = 'translateX(4px)';
                this.style.boxShadow = '0 4px 16px rgba(102,126,234,0.15)';
                this.style.borderColor = '#667eea';
            };
            listItem.onmouseout = function() { 
                this.style.transform = 'translateX(0)';
                this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.08)';
                this.style.borderColor = 'transparent';
            };

            let html = '<div style="width: 5px; background: linear-gradient(180deg, #667eea 0%, #764ba2 100%); flex-shrink: 0;"></div>';
            html += '<div style="flex-shrink: 0; padding: 15px; display: flex; align-items: center; gap: 15px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">';
            
            // Add checkbox for admin users in list view
            if (isAdmin) {
                html += '<input type="checkbox" class="book-select-checkbox" data-book-id="' + bookId + '" onclick="event.stopPropagation(); updateBulkDeleteUI();" style="width: 20px; height: 20px; cursor: pointer; accent-color: #f44336; flex-shrink: 0;">';
            }
            
            html += '<div style="width: 90px; height: 130px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.15); flex-shrink: 0;">';
            if (cover) {
                html += '<img src="' + cover + '" alt="' + title + '" style="width: 100%; height: 100%; object-fit: cover; display: block;" onerror="this.onerror=null; this.style.display=\'none\'; var fallback = document.createElement(\'div\'); fallback.style.cssText=\'color: white; font-size: 2.5rem; opacity: 0.4; display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;\'; fallback.textContent=\'📖\'; this.parentElement.innerHTML=\'\'; this.parentElement.appendChild(fallback);">';
            } else {
                html += '<div style="color: white; font-size: 2.5rem; opacity: 0.4;">📖</div>';
            }
            html += '</div></div>';

            html += '<div style="flex: 1; min-width: 0; padding: 20px 24px; display: flex; flex-direction: column; justify-content: center;">';
            html += '<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">';
            html += '<h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #2c3e50; line-height: 1.3;">' + title + '</h3>';
            if (isTopRated) {
                html += '<span style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; box-shadow: 0 2px 6px rgba(243,156,18,0.3);">⭐ TOP RATED</span>';
            }
            html += '</div>';
            html += '<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">';
            html += '<span style="color: #95a5a6; font-size: 0.85rem;">✍️</span>';
            html += '<p style="margin: 0; color: #7f8c8d; font-size: 0.95rem; font-weight: 500;">by ' + author + '</p>';
            html += '</div>';
            html += '<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 8px;">';
            html += '<div style="display: flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #fff5e6 0%, #ffe5cc 100%); padding: 6px 12px; border-radius: 8px; border: 1px solid #f39c12;">';
            const fullStars = Math.floor(parseFloat(rating));
            const hasHalfStar = parseFloat(rating) % 1 >= 0.5;
            for (let i = 0; i < 5; i++) {
                if (i < fullStars) {
                    html += '<span style="color: #f39c12; font-size: 1rem;">⭐</span>';
                } else if (i === fullStars && hasHalfStar) {
                    html += '<span style="color: #f39c12; font-size: 1rem;">⭐</span>';
                } else {
                    html += '<span style="color: #ddd; font-size: 1rem;">☆</span>';
                }
            }
            html += '<span style="color: #e67e22; font-weight: 700; font-size: 0.9rem; margin-left: 4px;">' + rating + '</span>';
            html += '</div>';
            html += '<div style="display: flex; align-items: center; gap: 5px; color: #7f8c8d; font-size: 0.85rem;">';
            html += '<span style="font-size: 1rem;">💬</span>';
            html += '<span style="font-weight: 600;">' + reviews + '</span>';
            html += '<span>reviews</span>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            // Get available quantity for borrowers button
            const availableQty = card.querySelector('[data-available-qty]')?.getAttribute('data-available-qty') || '0';
            
            html += '<div style="flex-shrink: 0; padding: 20px; display: flex; flex-direction: column; gap: 10px; justify-content: center; background: linear-gradient(135deg, #f8f9fc 0%, #f0f3f7 100%); border-left: 1px solid #e9ecef;">';
            html += '<button onclick="viewBookInfo(' + bookId + ')" class="btn btn-small" style="padding: 10px 20px; font-size: 0.9rem; border-radius: 8px; font-weight: 600; background: linear-gradient(135deg, #27ae60 0%, #229954 100%); color: white; border: none; box-shadow: 0 2px 6px rgba(39,174,96,0.3); transition: all 0.2s; white-space: nowrap;" onmouseover="this.style.transform=\'scale(1.05)\'; this.style.boxShadow=\'0 4px 12px rgba(39,174,96,0.4)\'" onmouseout="this.style.transform=\'scale(1)\'; this.style.boxShadow=\'0 2px 6px rgba(39,174,96,0.3)\'">ℹ️ Details</button>';
            if (isStaff) {
                html += '<button onclick="editBook(' + bookId + ')" class="btn btn-small" style="padding: 10px 20px; font-size: 0.9rem; border-radius: 8px; font-weight: 600; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; box-shadow: 0 2px 6px rgba(102,126,234,0.3); transition: all 0.2s; white-space: nowrap;" onmouseover="this.style.transform=\'scale(1.05)\'; this.style.boxShadow=\'0 4px 12px rgba(102,126,234,0.4)\'" onmouseout="this.style.transform=\'scale(1)\'; this.style.boxShadow=\'0 2px 6px rgba(102,126,234,0.3)\'">✏️ Edit</button>';
            }
            html += '<button onclick="viewReviews(' + bookId + ', \'' + title.replace(/'/g, "\\'") + '\')" class="btn btn-small" style="padding: 10px 20px; font-size: 0.9rem; border-radius: 8px; font-weight: 600; background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; border: none; box-shadow: 0 2px 6px rgba(243,156,18,0.3); transition: all 0.2s; white-space: nowrap;" onmouseover="this.style.transform=\'scale(1.05)\'; this.style.boxShadow=\'0 4px 12px rgba(243,156,18,0.4)\'" onmouseout="this.style.transform=\'scale(1)\'; this.style.boxShadow=\'0 2px 6px rgba(243,156,18,0.3)\'">⭐ Reviews</button>';
            if (isStaff) {
                html += '<button onclick="viewBorrowers(' + bookId + ', \'' + title.replace(/'/g, "\\'") + '\', ' + availableQty + ')" class="btn btn-small" style="padding: 10px 20px; font-size: 0.9rem; border-radius: 8px; font-weight: 600; background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; border: none; box-shadow: 0 2px 6px rgba(52,152,219,0.3); transition: all 0.2s; white-space: nowrap;" onmouseover="this.style.transform=\'scale(1.05)\'; this.style.boxShadow=\'0 4px 12px rgba(52,152,219,0.4)\'" onmouseout="this.style.transform=\'scale(1)\'; this.style.boxShadow=\'0 2px 6px rgba(52,152,219,0.3)\'">👥 Borrowers</button>';
            }
            html += '</div>';

            listItem.innerHTML = html;
            listContainer.appendChild(listItem);
        });
        
        grid.innerHTML = '';
        grid.appendChild(listContainer);
        
        // Restore checkbox states for list view
        if (isAdmin) {
            const listCheckboxes = grid.querySelectorAll('.book-select-checkbox');
            listCheckboxes.forEach(checkbox => {
                const bookId = checkbox.getAttribute('data-book-id');
                checkbox.checked = checkedBooks.has(bookId);
            });
        }
        
        gridBtn.style.background = 'transparent';
        gridBtn.style.color = '#667eea';
        listBtn.style.background = '#667eea';
        listBtn.style.color = 'white';
        
        booksCurrentView = 'list';
        localStorage.setItem('booksViewMode', 'list');
        
        // Update bulk delete UI after switching to list view
        updateBulkDeleteUI();
    }
}

// Restore saved view preference on page load
window.addEventListener('load', () => {
    const savedView = localStorage.getItem('booksViewMode') || 'grid';
    if (savedView === 'list') {
        switchView('list');
    }
});

function sortBooks() {
    const wasList = booksCurrentView === 'list';
    if (wasList) {
        switchView('grid');
    }

    const sortValue = document.getElementById('sortOrder').value;
    const grid = document.getElementById('booksGrid');
    const cards = Array.from(grid.querySelectorAll('.book-card'));
    
    cards.sort((a, b) => {
        if (sortValue === 'title') {
            const titleA = a.getAttribute('data-title');
            const titleB = b.getAttribute('data-title');
            return titleA.localeCompare(titleB);
        } else if (sortValue === 'author') {
            const authorA = a.getAttribute('data-author');
            const authorB = b.getAttribute('data-author');
            return authorA.localeCompare(authorB);
        } else if (sortValue === 'rating') {
            const ratingA = parseFloat(a.getAttribute('data-rating')) || 0;
            const ratingB = parseFloat(b.getAttribute('data-rating')) || 0;
            return ratingB - ratingA;
        } else if (sortValue === 'reviews') {
            const reviewsA = parseInt(a.getAttribute('data-reviews')) || 0;
            const reviewsB = parseInt(b.getAttribute('data-reviews')) || 0;
            return reviewsB - reviewsA;
        }
        return 0;
    });
    
    cards.forEach(card => grid.appendChild(card));

    if (wasList) {
        switchView('list');
    }
}

function openBookModal() {
    document.getElementById('bookModalTitle').textContent = '➕ Add New Book';
    document.getElementById('edit_book_id').value = '';
    document.getElementById('bookForm').reset();
    document.getElementById('bookModal').style.display = 'block';
    document.getElementById('deleteBookBtn').style.display = 'none';
    
    // Reset pasted file
    if (typeof pastedFile !== 'undefined') {
        pastedFile = null;
    }
    
    const coverPrev = document.getElementById('coverPreview');
    if (coverPrev) coverPrev.style.display = 'none';
    const pasteZone = document.getElementById('pasteZone');
    if (pasteZone) {
        pasteZone.style.borderColor = '#ccc';
        pasteZone.style.background = '#f9f9f9';
        pasteZone.innerHTML = '📋 Click here and press Ctrl+V to paste an image';
    }
    
    // Ensure upload mode is selected by default
    const uploadRadio = document.getElementById('cover_source_upload');
    if (uploadRadio) {
        uploadRadio.checked = true;
        document.getElementById('coverUploadContainer').style.display = '';
        document.getElementById('coverUrlContainer').style.display = 'none';
    }
}

function editBook(bookId) {
    document.getElementById('bookModalTitle').textContent = '✏️ Edit Book';
    document.getElementById('bookModal').style.display = 'block';
    document.getElementById('deleteBookBtn').style.display = isAdmin ? 'block' : 'none';
    
    fetch('get_book.php?book_id=' + bookId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert('Error: ' + data.error);
                closeBookModal();
            } else {
                document.getElementById('edit_book_id').value = data.id;
                document.getElementById('title').value = data.title || '';
                document.getElementById('author').value = data.author || '';
                document.getElementById('isbn').value = data.isbn || '';
                document.getElementById('category').value = data.category || '';
                document.getElementById('quantity').value = data.quantity || 1;
                document.getElementById('shelf_number').value = data.shelf_number || '';
                document.getElementById('shelf_row').value = data.shelf_row || '';
                document.getElementById('cover_url').value = data.cover_url || '';
            }
        })
        .catch(error => {
            alert('Error loading book details');
            closeBookModal();
        });
}

function saveBook(event) {
    event.preventDefault();
    event.stopPropagation();
    
    const bookId = document.getElementById('edit_book_id').value;
    const formData = new FormData(event.target);
    const sourceEl = document.querySelector('input[name="cover_source"]:checked');
    if (sourceEl) { formData.append('cover_source', sourceEl.value); }
    
    // If user pasted an image, use that instead of file input
    if (typeof pastedFile !== 'undefined' && pastedFile && sourceEl?.value === 'upload') {
        formData.set('cover_file', pastedFile);
    }
    
    if (bookId) {
        // Update existing book
        fetch('update_book.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Book updated successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + (data.error || 'Failed to update book'));
            }
        })
        .catch(error => {
            alert('Error updating book. Please try again.');
        });
    } else {
        // Add new book - use fetch to submit multipart data
        fetch('books.php', { method: 'POST', body: formData })
        .then(response => {
            if (response.redirected || response.ok) {
                // Book added successfully, reload to show it
                window.location.reload();
            } else {
                return response.text().then(text => {
                    console.error('Server response:', text);
                    alert('Error adding book. Please check console for details.');
                });
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            alert('Error adding book. Please try again.');
        });
    }
    
    return false;
}

function deleteBook() {
    const bookId = document.getElementById('edit_book_id').value;
    if (!bookId) {
        alert('No book selected for deletion');
        return;
    }
    
    const bookTitle = document.getElementById('title').value;
    if (!confirm('Are you sure you want to delete "' + bookTitle + '"? This action cannot be undone.')) {
        return;
    }
    
    fetch('delete_book.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ book_id: bookId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Book deleted successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete book'));
        }
    })
    .catch(error => {
        alert('Error deleting book. Please try again.');
        console.error('Error:', error);
    });
}

// CSV Import Functions
function openCSVImportModal() {
    document.getElementById('csvImportModal').style.display = 'block';
    document.getElementById('csvImportForm').reset();
    document.getElementById('importProgress').style.display = 'none';
    document.getElementById('importResults').style.display = 'none';
}

function closeCSVImportModal() {
    document.getElementById('csvImportModal').style.display = 'none';
}

function importCSV(event) {
    event.preventDefault();
    
    const fileInput = document.getElementById('csvFile');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Please select a CSV file');
        return;
    }
    
    if (!file.name.endsWith('.csv')) {
        alert('Please select a valid CSV file');
        return;
    }
    
    if (file.size > 10 * 1024 * 1024) { // 10MB limit
        alert('File size exceeds 10MB limit');
        return;
    }
    
    const formData = new FormData();
    formData.append('csvFile', file);
    
    // Show progress
    document.getElementById('importProgress').style.display = 'block';
    document.getElementById('importResults').style.display = 'none';
    document.getElementById('csvImportForm').style.display = 'none';
    
    fetch('import_csv.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.error || 'Server error: ' + response.status);
            });
        }
        return response.json();
    })
    .then(data => {
        document.getElementById('importProgress').style.display = 'none';
        
        if (data.success) {
            let resultHTML = '<strong>✅ ' + data.imported + ' books imported successfully!</strong><br>';
            if (data.skipped > 0) {
                resultHTML += '<span style="color: #ff9800;">⚠️ ' + data.skipped + ' rows skipped (invalid data)</span><br>';
            }
            if (data.errorDetails && data.errorDetails.length > 0) {
                resultHTML += '<div style="margin-top: 10px; font-size: 0.9rem; color: #666;"><strong>Details:</strong><br>';
                data.errorDetails.forEach(err => {
                    resultHTML += '• ' + err + '<br>';
                });
                resultHTML += '</div>';
            }
            document.getElementById('importResultsText').innerHTML = resultHTML;
            document.getElementById('importResults').style.display = 'block';
        } else {
            document.getElementById('csvImportForm').style.display = 'block';
            let errorMsg = 'Error: ' + (data.error || 'Failed to import CSV');
            if (data.errorDetails) {
                errorMsg += '\n\nDetails:\n' + data.errorDetails.join('\n');
            }
            alert(errorMsg);
        }
    })
    .catch(error => {
        document.getElementById('importProgress').style.display = 'none';
        document.getElementById('csvImportForm').style.display = 'block';
        alert('Error importing CSV: ' + error.message);
        console.error('Error:', error);
    });
}

// Cover source toggle and preview
document.getElementById('cover_source_upload')?.addEventListener('change', () => {
    document.getElementById('coverUploadContainer').style.display = '';
    document.getElementById('coverUrlContainer').style.display = 'none';
    // Clear URL input when switching to upload
    document.getElementById('cover_url').value = '';
});
document.getElementById('cover_source_url')?.addEventListener('change', () => {
    document.getElementById('coverUploadContainer').style.display = 'none';
    document.getElementById('coverUrlContainer').style.display = '';
    // Clear file input when switching to URL
    document.getElementById('cover_file').value = '';
    const coverPrev = document.getElementById('coverPreview');
    if (coverPrev) coverPrev.style.display = 'none';
});

const coverFileInput = document.getElementById('cover_file');
const coverUrlInput = document.getElementById('cover_url');
const coverPrev = document.getElementById('coverPreview');
const coverPrevImg = document.getElementById('coverPreviewImg');

coverFileInput?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = () => {
            coverPrevImg.src = reader.result;
            coverPrev.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        coverPrev.style.display = 'none';
        coverPrevImg.src = '';
    }
});

coverUrlInput?.addEventListener('input', (e) => {
    const url = e.target.value.trim();
    if (url) {
        coverPrevImg.src = url;
        coverPrev.style.display = 'block';
    } else {
        coverPrev.style.display = 'none';
        coverPrevImg.src = '';
    }
});

// Clipboard paste support
const pasteZone = document.getElementById('pasteZone');
let pastedFile = null;

pasteZone?.addEventListener('paste', (e) => {
    e.preventDefault();
    const items = e.clipboardData?.items;
    if (!items) return;
    
    for (let i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
            const blob = items[i].getAsFile();
            if (blob) {
                // Store the pasted file
                pastedFile = new File([blob], `pasted-image-${Date.now()}.png`, { type: blob.type });
                
                // Show preview
                const reader = new FileReader();
                reader.onload = () => {
                    coverPrevImg.src = reader.result;
                    coverPrev.style.display = 'block';
                    pasteZone.style.borderColor = '#4CAF50';
                    pasteZone.style.background = '#e8f5e9';
                    pasteZone.innerHTML = '✅ Image pasted! Ready to save.';
                };
                reader.readAsDataURL(blob);
            }
            break;
        }
    }
});

// Make paste zone focusable
pasteZone?.setAttribute('tabindex', '0');
pasteZone?.addEventListener('click', () => pasteZone.focus());

// Reset paste zone when modal opens
function resetPasteZone() {
    pastedFile = null;
    if (pasteZone) {
        pasteZone.style.borderColor = '#ccc';
        pasteZone.style.background = '#f9f9f9';
        pasteZone.innerHTML = '📋 Click here and press Ctrl+V to paste an image';
    }
}

function closeBookModal() {
    document.getElementById('bookModal').style.display = 'none';
    document.getElementById('bookForm').reset();
    document.getElementById('edit_book_id').value = '';
    document.getElementById('bookModalTitle').textContent = '➕ Add New Book';
    resetPasteZone();
    if (coverPrev) coverPrev.style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('bookModal');
    if (event.target == modal) {
        closeBookModal();
    }
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeBookModal();
        closeBorrowersModal();
        closeReviewsModal();
    }
});

function viewBorrowers(bookId, bookTitle, availableQty) {
    const modal = document.getElementById('borrowersModal');
    const title = document.getElementById('borrowersTitle');
    const availInfo = document.getElementById('availabilityInfo');
    const content = document.getElementById('borrowersContent');
    
    title.textContent = '👥 Current Borrowers - ' + bookTitle;
    availInfo.innerHTML = '<strong>📦 Available Copies:</strong> ' + availableQty;
    content.innerHTML = '<p style="text-align: center;">Loading...</p>';
    modal.style.display = 'block';
    
    // Fetch borrowers via AJAX
    fetch('get_book_borrowers.php?book_id=' + bookId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = '<p style="text-align: center; color: #e74c3c;">' + data.error + '</p>';
            } else if (data.length === 0) {
                content.innerHTML = '<p style="text-align: center;">No one is currently borrowing this book.</p>';
            } else {
                let html = '<table class="table"><thead><tr>';
                html += '<th>Student Name</th><th>Class</th><th>Borrow Date</th><th>Due Date</th>';
                html += '</tr></thead><tbody>';
                
                data.forEach(record => {
                    html += '<tr>';
                    html += '<td>' + escapeHtml(record.name) + '</td>';
                    html += '<td>' + (record.class || '-') + '</td>';
                    html += '<td>' + record.borrow_date + '</td>';
                    html += '<td>' + record.due_date + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                content.innerHTML = html;
            }
        })
        .catch(error => {
            content.innerHTML = '<p style="text-align: center; color: #e74c3c;">Error loading borrowers.</p>';
        });
}

function closeBorrowersModal() {
    document.getElementById('borrowersModal').style.display = 'none';
}

function searchBooks() {
    const wasList = booksCurrentView === 'list';
    if (wasList) {
        switchView('grid');
    }

    const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.book-card');
    const noResultsMsg = document.getElementById('noResultsMessage');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const title = card.getAttribute('data-title');
        if (title.includes(searchTerm)) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    if (visibleCount === 0 && searchTerm !== '') {
        noResultsMsg.style.display = 'block';
    } else {
        noResultsMsg.style.display = 'none';
    }

    if (wasList) {
        switchView('list');
    }
}

function clearSearch() {
    const wasList = booksCurrentView === 'list';
    if (wasList) {
        switchView('grid');
    }

    document.getElementById('searchInput').value = '';
    const cards = document.querySelectorAll('.book-card');
    cards.forEach(card => {
        card.style.display = '';
    });
    document.getElementById('noResultsMessage').style.display = 'none';

    if (wasList) {
        switchView('list');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const borrowersModal = document.getElementById('borrowersModal');
    const reviewsModal = document.getElementById('reviewsModal');
    const bookInfoModal = document.getElementById('bookInfoModal');
    if (event.target == borrowersModal) {
        closeBorrowersModal();
    }
    if (event.target == reviewsModal) {
        closeReviewsModal();
    }
    if (event.target == bookInfoModal) {
        closeBookInfoModal();
    }
});

function viewReviews(bookId, bookTitle) {
    const modal = document.getElementById('reviewsModal');
    const title = document.getElementById('reviewsTitle');
    const content = document.getElementById('reviewsContent');
    
    title.textContent = '⭐ Reviews - ' + bookTitle;
    document.getElementById('review_book_id').value = bookId;
    content.innerHTML = '<p style="text-align: center;">Loading...</p>';
    modal.style.display = 'block';
    
    loadReviews(bookId);
}

function loadReviews(bookId) {
    const content = document.getElementById('reviewsContent');
    content.innerHTML = '<p style="text-align: center;">Loading reviews...</p>';
    
    // Add timestamp to prevent caching
    fetch('get_book_reviews.php?book_id=' + bookId + '&t=' + Date.now())
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.error) {
                content.innerHTML = '<p style="text-align: center; color: #e74c3c;">' + data.error + '</p>';
            } else if (data.length === 0) {
                content.innerHTML = '<p style="text-align: center;">No reviews yet. Be the first to review this book!</p>';
            } else {
                let html = '<div style="margin-top: 20px;">';
                
                data.forEach(review => {
                    const stars = '⭐'.repeat(review.rating);
                    html += '<div style="background: #fff; border: 1px solid #ddd; padding: 15px; border-radius: 5px; margin-bottom: 15px;">';
                    html += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">';
                    html += '<strong>' + escapeHtml(review.name) + '</strong>';
                    html += '<span style="color: #f39c12;">' + stars + '</span>';
                    html += '</div>';
                    if (review.comment) {
                        html += '<p style="margin: 10px 0; color: #555;">' + escapeHtml(review.comment) + '</p>';
                    }
                    html += '<small style="color: #999;">' + review.created_at + '</small>';
                    html += '</div>';
                });
                
                html += '</div>';
                content.innerHTML = html;
            }
        })
        .catch(error => {
            content.innerHTML = '<p style="text-align: center; color: #e74c3c;">Error loading reviews.</p>';
        });
}

function submitReview(event) {
    event.preventDefault();
    
    const rating = document.getElementById('rating').value;
    const comment = document.getElementById('comment').value.trim();
    const bookId = document.getElementById('review_book_id').value;
    
    // Validate rating
    if (!rating) {
        alert('Please select a rating');
        return false;
    }
    
    // Disable submit button to prevent double submission
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    
    const formData = new FormData(event.target);
    
    fetch('submit_review.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Network error');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert(data.message || 'Review submitted successfully!');
            event.target.reset();
            // Reload reviews immediately
            setTimeout(() => loadReviews(bookId), 100);
        } else {
            alert('Error: ' + (data.error || 'Failed to submit review'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error submitting review. Please try again.');
    })
    .finally(() => {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
    
    return false;
}

function viewBookInfo(bookId) {
    const modal = document.getElementById('bookInfoModal');
    const content = document.getElementById('bookInfoContent');
    
    content.innerHTML = '<p style="text-align: center;">Loading...</p>';
    modal.style.display = 'block';
    
    fetch('get_book.php?book_id=' + bookId)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                content.innerHTML = '<p style="text-align: center; color: #e74c3c;">' + data.error + '</p>';
            } else {
                let shelfNum = data.shelf_number || '-';
                let shelfRow = data.shelf_row || '-';
                let location = shelfNum !== '-' || shelfRow !== '-' ? 
                    (shelfNum !== '-' ? shelfNum + (shelfNum == 1 ? 'st' : shelfNum == 2 ? 'nd' : shelfNum == 3 ? 'rd' : 'th') + ' shelf' : '') + 
                    (shelfRow !== '-' ? (shelfNum !== '-' ? ', ' : '') + 'row ' + shelfRow : '') : '-';
                
                let html = '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">';
                html += '<div style="display: grid; gap: 15px;">';
                html += '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd;"><strong>📚 Title:</strong><span>' + escapeHtml(data.title) + '</span></div>';
                html += '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd;"><strong>✍️ Author:</strong><span>' + escapeHtml(data.author) + '</span></div>';
                html += '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd;"><strong>🔢 ISBN:</strong><span>' + (data.isbn || '-') + '</span></div>';
                html += '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd;"><strong>📁 Category:</strong><span>' + escapeHtml(data.category || '-') + '</span></div>';
                html += '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd;"><strong>📦 Total Quantity:</strong><span>' + data.quantity + '</span></div>';
                html += '<div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #ddd;"><strong>✅ Available:</strong><span style="color: ' + (data.available_quantity > 0 ? '#28a745' : '#dc3545') + '; font-weight: 600;">' + data.available_quantity + '</span></div>';
                html += '<div style="display: flex; justify-content: space-between; padding: 10px 0;"><strong>📍 Location:</strong><span>' + location + '</span></div>';
                html += '</div>';
                html += '</div>';
                content.innerHTML = html;
            }
        })
        .catch(error => {
            content.innerHTML = '<p style="text-align: center; color: #e74c3c;">Error loading book information.</p>';
        });
}

function closeBookInfoModal() {
    document.getElementById('bookInfoModal').style.display = 'none';
}

function closeReviewsModal() {
    document.getElementById('reviewsModal').style.display = 'none';
    document.getElementById('reviewForm').reset();
}

function downloadCSVTemplate() {
    // Create CSV content with headers and sample data
    const csvContent = `title,author,isbn,category,quantity,shelf_number,shelf_row
The Great Gatsby,F. Scott Fitzgerald,9780743273565,Fiction,5,2,A
To Kill a Mockingbird,Harper Lee,9780061120084,Fiction,3,2,B
1984,George Orwell,9780451524935,Fiction,4,3,A
Pride and Prejudice,Jane Austen,9780141439518,Romance,2,1,C
The Catcher in the Rye,J.D. Salinger,9780316769174,Fiction,3,2,C`;
    
    // Create a Blob from the CSV content
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    
    // Create a download link
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'books_import_template.csv');
    link.style.visibility = 'hidden';
    
    // Append to body, click, and remove
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show success message
    alert('✅ Template downloaded! Fill it with your book data and upload it.');
}

// Bulk Delete Functions (Admin Only)
function updateBulkDeleteUI() {
    const checkboxes = document.querySelectorAll('.book-select-checkbox');
    const checkedBoxes = document.querySelectorAll('.book-select-checkbox:checked');
    const bulkDeleteControls = document.getElementById('bulkDeleteControls');
    const selectedCount = document.getElementById('selectedCount');
    const deleteBtn = document.getElementById('deleteSelectedBtn');
    const selectAllCheckbox = document.getElementById('selectAllBooks');
    
    // Show/hide bulk controls based on whether any checkboxes are checked
    if (checkedBoxes.length > 0) {
        bulkDeleteControls.style.display = 'block';
        selectedCount.textContent = checkedBoxes.length + ' book' + (checkedBoxes.length === 1 ? '' : 's') + ' selected';
        deleteBtn.disabled = false;
        deleteBtn.style.opacity = '1';
        deleteBtn.style.cursor = 'pointer';
    } else {
        bulkDeleteControls.style.display = 'none';
        deleteBtn.disabled = true;
        deleteBtn.style.opacity = '0.5';
        deleteBtn.style.cursor = 'not-allowed';
    }
    
    // Update "Select All" checkbox state
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = checkboxes.length > 0 && checkedBoxes.length === checkboxes.length;
        selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < checkboxes.length;
    }
}

function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAllBooks');
    const checkboxes = document.querySelectorAll('.book-select-checkbox');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    
    updateBulkDeleteUI();
}

function deleteSelectedBooks() {
    const checkedBoxes = document.querySelectorAll('.book-select-checkbox:checked');
    
    if (checkedBoxes.length === 0) {
        alert('Please select at least one book to delete.');
        return;
    }
    
    const count = checkedBoxes.length;
    const confirmation = confirm(`Are you sure you want to delete ${count} book${count === 1 ? '' : 's'}? This action cannot be undone.`);
    
    if (!confirmation) return;
    
    // Collect book IDs
    const bookIds = Array.from(checkedBoxes).map(cb => parseInt(cb.getAttribute('data-book-id')));
    
    // Send delete request
    fetch('delete_books.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ book_ids: bookIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Books deleted successfully!');
            // Reload the page to refresh the book list
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete books'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting books. Please try again.');
    });
}

// Initialize bulk delete UI on page load
document.addEventListener('DOMContentLoaded', function() {
    updateBulkDeleteUI();
});
</script>

<?php include 'includes/footer.php'; ?>
