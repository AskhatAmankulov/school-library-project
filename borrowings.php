<?php
session_start();
include 'auth_check.php';
require_staff();
include 'config.php';
include 'includes/header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$db_error) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'borrow') {
            $member_id = (int)$_POST['member_id'];
            $book_id = (int)$_POST['book_id'];
            $borrow_date = $_POST['borrow_date'];
            $due_date = $_POST['due_date'];
            
            // Check available quantity
            $book_check = $conn->query("SELECT available_quantity FROM books WHERE id=$book_id");
            $book = $book_check->fetch_assoc();
            
            if ($book && $book['available_quantity'] > 0) {
                $stmt = $conn->prepare("INSERT INTO borrowings (member_id, book_id, borrow_date, due_date, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->bind_param("iiss", $member_id, $book_id, $borrow_date, $due_date);
                
                if ($stmt->execute()) {
                    $conn->query("UPDATE books SET available_quantity = available_quantity - 1 WHERE id=$book_id");
                    $_SESSION['success_message'] = "Book borrowed successfully!";
                } else {
                    $_SESSION['error_message'] = "Error: " . $conn->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "This book is not available!";
            }
        } elseif ($_POST['action'] == 'return') {
            $borrowing_id = (int)$_POST['borrowing_id'];
            
            // Get book_id from borrowing
            $borrowing_check = $conn->query("SELECT book_id FROM borrowings WHERE id=$borrowing_id");
            $borrowing = $borrowing_check->fetch_assoc();
            $book_id = $borrowing['book_id'];
            
            $stmt = $conn->prepare("UPDATE borrowings SET status='returned', return_date=CURDATE() WHERE id=?");
            $stmt->bind_param("i", $borrowing_id);
            
            if ($stmt->execute()) {
                $conn->query("UPDATE books SET available_quantity = available_quantity + 1 WHERE id=$book_id");
                $_SESSION['success_message'] = "Book returned successfully!";
            } else {
                $_SESSION['error_message'] = "Error: " . $conn->error;
            }
            $stmt->close();
        }
    }
    
    header("Location: borrowings.php");
    exit();
}
?>

<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 35px; margin: -20px -20px 30px -20px; border-radius: 0 0 20px 20px; box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);">
    <h1 style="color: white; margin: 0; display: flex; align-items: center; gap: 12px; font-size: 2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.2);"><span style="font-size: 2.2rem;">📖</span> Borrowings Management</h1>
    <p style="color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 1.05rem;">Manage all book borrowings and returns</p>
</div>

<?php
// Display success/error messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success" style="border-radius: 12px; border-left: 4px solid #4CAF50; background: #e8f5e9; color: #2e7d32; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(76, 175, 80, 0.2); animation: slideIn 0.3s ease-out;">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger" style="border-radius: 12px; border-left: 4px solid #f44336; background: #ffebee; color: #c62828; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(244, 67, 54, 0.2); animation: slideIn 0.3s ease-out;">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}
if ($db_error) {
    echo '<div class="alert alert-danger" style="border-radius: 12px; border-left: 4px solid #f44336; background: #ffebee; color: #c62828; padding: 15px 20px; margin-bottom: 20px;">Database connection error. Please check your configuration.</div>';
}
?>

<div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h2 style="margin: 0; color: #333; font-size: 1.5rem; display: flex; align-items: center; gap: 10px;"><span style="color: #667eea; font-size: 1.3rem;">📋</span> Borrowings List</h2>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <label for="statusFilter" style="font-weight: 600; color: #555; display: flex; align-items: center; gap: 8px;"><span style="font-size: 0.9rem;">🔍</span> Filter:</label>
            <select id="statusFilter" onchange="filterBorrowings()" style="padding: 10px 15px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; background: white; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#667eea'" onmouseout="this.style.borderColor='#e0e0e0'">
                <option value="all">All Borrowings</option>
                <option value="active" selected>Active Only</option>
                <option value="returned">Returned Only</option>
                <option value="overdue">Overdue Only</option>
            </select>
            <button onclick="openBorrowModal()" class="btn btn-success" style="padding: 10px 20px; font-size: 1rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(76,175,80,0.3); transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(76,175,80,0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(76,175,80,0.3)'">➕ New Borrowing</button>
        </div>
    </div>
</div>

<!-- Borrow Modal -->
<!-- Borrow Modal -->
<div id="borrowModal" class="modal">
    <div class="modal-content" style="border-radius: 16px; max-width: 550px; padding: 0; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px 30px; position: relative;">
            <h2 style="margin: 0; color: white; display: flex; align-items: center; gap: 10px; font-size: 1.6rem;">📖 Borrow a Book</h2>
            <span class="modal-close" onclick="closeBorrowModal()" style="position: absolute; top: 20px; right: 25px; color: white; font-size: 2rem; opacity: 0.9; cursor: pointer;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.9'">&times;</span>
        </div>
        <form method="POST" action="" id="borrowForm" style="padding: 30px;">
            <input type="hidden" name="action" value="borrow">
            
            <!-- Member Input Option 1: Search -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Member *</label>
                <div style="margin-bottom: 10px;">
                    <div style="position: relative;">
                        <input type="text" id="memberSearch" placeholder="🔍 Search member by name..." style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'; searchMembers();" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'" oninput="searchMembers()">
                        <div id="memberSearchResults" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 2px solid #e0e0e0; border-top: none; border-radius: 0 0 8px 8px; max-height: 250px; overflow-y: auto; display: none; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        </div>
                    </div>
                    <input type="hidden" name="member_id" id="member_id">
                </div>
                <div id="selectedMember" style="padding: 10px 12px; background: #f0f4ff; border-radius: 8px; border: 2px solid transparent; display: none; color: #333; font-weight: 500;">
                    ✅ <span id="selectedMemberText"></span>
                </div>
            </div>
            
            <!-- Book Input Option 2: Search -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Book *</label>
                <div style="margin-bottom: 10px;">
                    <div style="position: relative;">
                        <input type="text" id="bookSearch" placeholder="🔍 Search book by title or author..." style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102,126,234,0.1)'; searchBooks();" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'" oninput="searchBooks()">
                        <div id="bookSearchResults" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 2px solid #e0e0e0; border-top: none; border-radius: 0 0 8px 8px; max-height: 250px; overflow-y: auto; display: none; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                        </div>
                    </div>
                    <input type="hidden" name="book_id" id="book_id">
                </div>
                <div id="selectedBook" style="padding: 10px 12px; background: #f0f4ff; border-radius: 8px; border: 2px solid transparent; display: none; color: #333; font-weight: 500;">
                    ✅ <span id="selectedBookText"></span>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="borrow_date" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Borrow Date *</label>
                <input type="date" name="borrow_date" id="borrow_date" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;">
            </div>
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="due_date" style="font-weight: 600; color: #333; margin-bottom: 8px; display: block;">Due Date *</label>
                <input type="date" name="due_date" id="due_date" required value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 0.95rem; transition: all 0.2s;">
            </div>
            <div style="display: flex; gap: 12px; margin-top: 25px;">
                <button type="submit" class="btn btn-primary" onclick="return validateBorrowForm()" style="flex: 1; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 10px; color: white; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 12px rgba(102, 126, 234, 0.3)'">📖 Borrow Book</button>
                <button type="button" class="btn btn-secondary" onclick="closeBorrowModal()" style="padding: 12px 24px; background: #e0e0e0; border: none; border-radius: 10px; color: #666; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='#d0d0d0'" onmouseout="this.style.background='#e0e0e0'">Cancel</button>
            </div>
        </form>
    </div>
</div>

<table class="table" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
    <thead>
        <tr style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <th style="color: white; padding: 15px; text-align: left; font-weight: 600; border-radius: 12px 0 0 0;">Book Title</th>
            <th style="color: white; padding: 15px; text-align: left; font-weight: 600;">Author</th>
            <th style="color: white; padding: 15px; text-align: left; font-weight: 600;">Member Name</th>
            <th style="color: white; padding: 15px; text-align: left; font-weight: 600;">Borrow Date</th>
            <th style="color: white; padding: 15px; text-align: left; font-weight: 600;">Due Date</th>
            <th style="color: white; padding: 15px; text-align: left; font-weight: 600;">Return Date</th>
            <th style="color: white; padding: 15px; text-align: left; font-weight: 600;">Status</th>
            <th style="color: white; padding: 15px; text-align: left; font-weight: 600; border-radius: 0 12px 0 0;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if (!$db_error && $conn) {
            $sql = "SELECT br.id, br.borrow_date, br.due_date, br.return_date, br.status,
                    b.title, b.author, m.name as member_name, m.class,
                    DATEDIFF(CURDATE(), br.due_date) as days_overdue
                    FROM borrowings br
                    JOIN books b ON br.book_id = b.id
                    JOIN members m ON br.member_id = m.id
                    ORDER BY br.borrow_date DESC";
            
            $result = $conn->query($sql);
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $is_overdue = $row['status'] === 'active' && $row['days_overdue'] > 0;
                    $status_class = $row['status'] === 'returned' ? 'status-returned' : ($is_overdue ? 'status-overdue' : 'status-active');
                    $status_text = $row['status'] === 'returned' ? '✅ Returned' : ($is_overdue ? '⚠️ Overdue' : '📖 Active');
                    $row_class = $is_overdue ? 'overdue-row' : '';
                    
                    echo "<tr class='borrowing-row " . $row_class . "' data-status='" . $row['status'] . "' data-overdue='" . ($is_overdue ? '1' : '0') . "' style='transition: all 0.2s; border-bottom: 1px solid #f0f0f0;' onmouseover='this.style.backgroundColor=\"#f8f9fa\"; this.style.transform=\"scale(1.01)\"' onmouseout='this.style.backgroundColor=\"\"; this.style.transform=\"scale(1)\"'>";
                    echo "<td style='padding: 15px; font-weight: 500; color: #333;'>" . htmlspecialchars($row['title']) . "</td>";
                    echo "<td style='padding: 15px; color: #666;'>" . htmlspecialchars($row['author']) . "</td>";
                    echo "<td style='padding: 15px; color: #666;'>" . htmlspecialchars($row['member_name']) . ($row['class'] ? ' (' . htmlspecialchars($row['class']) . ')' : '') . "</td>";
                    echo "<td style='padding: 15px; color: #555;'>" . date('M d, Y', strtotime($row['borrow_date'])) . "</td>";
                    echo "<td style='padding: 15px; color: #555;'>" . date('M d, Y', strtotime($row['due_date'])) . "</td>";
                    echo "<td style='padding: 15px; color: #555;'>" . ($row['return_date'] ? date('M d, Y', strtotime($row['return_date'])) : '-') . "</td>";
                    echo "<td style='padding: 15px;'><span class='status-badge " . $status_class . "'>" . $status_text . "</span></td>";
                    echo "<td style='padding: 15px;'>";
                    if ($row['status'] === 'active') {
                        echo "<form method='POST' style='display:inline;' onsubmit='return confirm(\"Confirm return of this book?\");'><input type='hidden' name='action' value='return'><input type='hidden' name='borrowing_id' value='" . $row['id'] . "'><button type='submit' class='btn btn-small btn-success' style='padding: 6px 12px; font-size: 0.85rem; border-radius: 8px; font-weight: 500; transition: all 0.2s;' onmouseover='this.style.transform=\"scale(1.05)\"' onmouseout='this.style.transform=\"scale(1)\"'>✅ Return</button></form>";
                    } else {
                        echo "<span style='color: #999; font-size: 0.85rem;'>Already returned</span>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8' style='text-align:center; padding: 60px 20px; color: #999;'><div style='font-size: 3rem; margin-bottom: 15px; opacity: 0.3;'>📖</div><p style='font-size: 1.1rem; margin: 0;'>No borrowings found. Click 'New Borrowing' to add one!</p></td></tr>";
            }
        } else {
            echo "<tr><td colspan='8' style='text-align:center; padding: 40px 20px; color: #e74c3c; font-weight: 500;'>Database connection error.</td></tr>";
        }
        ?>
    </tbody>
</table>

<!-- Statistics Panel -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-top: 30px;">
    <?php
    if (!$db_error && $conn) {
        // Total Active Borrowings
        $activeResult = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE status='active'");
        $activeCount = ($activeResult && $activeResult->num_rows > 0) ? $activeResult->fetch_assoc()['count'] : 0;
        
        // Total Overdue
        $overdueResult = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE status='active' AND due_date < CURDATE()");
        $overdueCount = ($overdueResult && $overdueResult->num_rows > 0) ? $overdueResult->fetch_assoc()['count'] : 0;
        
        // Returned Today
        $todayResult = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE status='returned' AND return_date = CURDATE()");
        $todayCount = ($todayResult && $todayResult->num_rows > 0) ? $todayResult->fetch_assoc()['count'] : 0;
        
        // Total Books Out (count active borrowings)
        $totalBooksResult = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE status='active'");
        $totalBooksOut = ($totalBooksResult && $totalBooksResult->num_rows > 0) ? $totalBooksResult->fetch_assoc()['count'] : 0;
        
        echo "<div style='background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);'>
            <div style='display: flex; align-items: center; gap: 12px; margin-bottom: 10px;'>
                <span style='font-size: 2rem;'>📚</span>
                <h3 style='margin: 0; color: #1565c0; font-size: 1rem;'>Active Borrowings</h3>
            </div>
            <p style='font-size: 2rem; font-weight: 700; color: #0d47a1; margin: 0;'>" . $activeCount . "</p>
        </div>";
        
        echo "<div style='background: linear-gradient(135deg, #fff3cd 0%, #ffe0b2 100%); padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);'>
            <div style='display: flex; align-items: center; gap: 12px; margin-bottom: 10px;'>
                <span style='font-size: 2rem;'>⚠️</span>
                <h3 style='margin: 0; color: #e65100; font-size: 1rem;'>Overdue Books</h3>
            </div>
            <p style='font-size: 2rem; font-weight: 700; color: #bf360c; margin: 0;'>" . $overdueCount . "</p>
        </div>";
        
        echo "<div style='background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);'>
            <div style='display: flex; align-items: center; gap: 12px; margin-bottom: 10px;'>
                <span style='font-size: 2rem;'>✅</span>
                <h3 style='margin: 0; color: #2e7d32; font-size: 1rem;'>Returned Today</h3>
            </div>
            <p style='font-size: 2rem; font-weight: 700; color: #1b5e20; margin: 0;'>" . $todayCount . "</p>
        </div>";
        
        echo "<div style='background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%); padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);'>
            <div style='display: flex; align-items: center; gap: 12px; margin-bottom: 10px;'>
                <span style='font-size: 2rem;'>📖</span>
                <h3 style='margin: 0; color: #6a1b9a; font-size: 1rem;'>Total Books Out</h3>
            </div>
            <p style='font-size: 2rem; font-weight: 700; color: #4a148c; margin: 0;'>" . $totalBooksOut . "</p>
        </div>";
    }
    ?>
</div>

<script>
function openBorrowModal() {
    const modal = document.getElementById('borrowModal');
    if (modal) {
        modal.style.display = 'block';
    }
    // Reset search fields
    document.getElementById('memberSearch').value = '';
    document.getElementById('bookSearch').value = '';
    document.getElementById('selectedMember').style.display = 'none';
    document.getElementById('selectedBook').style.display = 'none';
    document.getElementById('member_id').value = '';
    document.getElementById('book_id').value = '';
    // Set default dates
    document.getElementById('borrow_date').value = new Date().toISOString().split('T')[0];
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 14);
    document.getElementById('due_date').value = dueDate.toISOString().split('T')[0];
    
    // Load all members and books initially
    loadAllMembers();
    loadAllBooks();
}

function closeBorrowModal() {
    document.getElementById('borrowModal').style.display = 'none';
    document.getElementById('borrowForm').reset();
    document.getElementById('memberSearchResults').style.display = 'none';
    document.getElementById('bookSearchResults').style.display = 'none';
    document.getElementById('selectedMember').style.display = 'none';
    document.getElementById('selectedBook').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('borrowModal');
    if (event.target == modal) {
        closeBorrowModal();
    }
}

// Store all members and books for client-side filtering
let allMembers = [];
let allBooks = [];

// Load all members
function loadAllMembers() {
    fetch('get_all_members.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load members');
            }
            return response.json();
        })
        .then(members => {
            allMembers = members;
            displayMembers(members);
        })
        .catch(error => {
            displayMembers([]);
        });
}

// Load all books
function loadAllBooks() {
    fetch('get_all_books.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load books');
            }
            return response.json();
        })
        .then(books => {
            allBooks = books;
            displayBooks(books);
        })
        .catch(error => {
            displayBooks([]);
        });
}

// Display members in dropdown
function displayMembers(members) {
    const resultsDiv = document.getElementById('memberSearchResults');
    resultsDiv.innerHTML = '';
    
    if (members.length === 0) {
        resultsDiv.innerHTML = '<div style="padding: 15px; color: #999; text-align: center;">No members found</div>';
        resultsDiv.style.display = 'block';
        return;
    }
    
    members.forEach(member => {
        const classInfo = member.class ? ' (' + member.class + ')' : '';
        const div = document.createElement('div');
        div.style.cssText = 'padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; font-size: 0.95rem;';
        div.innerHTML = '<strong style="color: #333;">' + member.name + '</strong><span style="color: #666;">' + classInfo + '</span>';
        div.onmouseover = () => div.style.background = '#f5f5f5';
        div.onmouseout = () => div.style.background = '';
        div.onclick = () => selectMember(member.id, member.name, member.class);
        resultsDiv.appendChild(div);
    });
    
    resultsDiv.style.display = 'block';
}

// Display books in dropdown
function displayBooks(books) {
    const resultsDiv = document.getElementById('bookSearchResults');
    resultsDiv.innerHTML = '';
    
    if (books.length === 0) {
        resultsDiv.innerHTML = '<div style="padding: 15px; color: #999; text-align: center;">No available books found</div>';
        resultsDiv.style.display = 'block';
        return;
    }
    
    books.forEach(book => {
        const div = document.createElement('div');
        div.style.cssText = 'padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; font-size: 0.95rem;';
        div.innerHTML = '<strong style="color: #333;">' + book.title + '</strong><br><span style="color: #666; font-size: 0.9rem;">by ' + book.author + '</span><span style="color: #667eea; font-weight: 600; margin-left: 8px;">(Available: ' + book.available_quantity + ')</span>';
        div.onmouseover = () => div.style.background = '#f5f5f5';
        div.onmouseout = () => div.style.background = '';
        div.onclick = () => selectBook(book.id, book.title, book.author, book.available_quantity);
        resultsDiv.appendChild(div);
    });
    
    resultsDiv.style.display = 'block';
}

// Member Search Function - filters locally
function searchMembers() {
    const searchTerm = document.getElementById('memberSearch').value.toLowerCase();
    
    if (searchTerm.length === 0) {
        // Show all members if search is empty
        displayMembers(allMembers);
        return;
    }
    
    // Filter members by name or class
    const filtered = allMembers.filter(member => 
        member.name.toLowerCase().includes(searchTerm) || 
        (member.class && member.class.toLowerCase().includes(searchTerm))
    );
    
    displayMembers(filtered);
}

function selectMember(memberId, memberName, memberClass) {
    document.getElementById('member_id').value = memberId;
    const classInfo = memberClass ? ' (' + memberClass + ')' : '';
    document.getElementById('selectedMemberText').textContent = memberName + classInfo;
    document.getElementById('selectedMember').style.display = 'block';
    document.getElementById('memberSearch').value = memberName + classInfo;
    document.getElementById('memberSearchResults').style.display = 'none';
}

// Book Search Function - filters locally
function searchBooks() {
    const searchTerm = document.getElementById('bookSearch').value.toLowerCase();
    
    if (searchTerm.length === 0) {
        // Show all books if search is empty
        displayBooks(allBooks);
        return;
    }
    
    // Filter books by title or author
    const filtered = allBooks.filter(book => 
        book.title.toLowerCase().includes(searchTerm) || 
        book.author.toLowerCase().includes(searchTerm)
    );
    
    displayBooks(filtered);
}

function selectBook(bookId, bookTitle, bookAuthor, availableQty) {
    document.getElementById('book_id').value = bookId;
    document.getElementById('selectedBookText').textContent = bookTitle + ' by ' + bookAuthor + ' (Available: ' + availableQty + ')';
    document.getElementById('selectedBook').style.display = 'block';
    document.getElementById('bookSearch').value = bookTitle;
    document.getElementById('bookSearchResults').style.display = 'none';
}

// Validate form before submission
function validateBorrowForm() {
    const memberId = document.getElementById('member_id').value;
    const bookId = document.getElementById('book_id').value;
    const borrowDate = document.getElementById('borrow_date').value;
    const dueDate = document.getElementById('due_date').value;
    
    if (!memberId || memberId == 0) {
        alert('⚠️ Please select or enter a member ID');
        return false;
    }
    
    if (!bookId || bookId == 0) {
        alert('⚠️ Please select or enter a book ID');
        return false;
    }
    
    if (!borrowDate) {
        alert('⚠️ Please select a borrow date');
        return false;
    }
    
    if (!dueDate) {
        alert('⚠️ Please select a due date');
        return false;
    }
    
    const borrowDateObj = new Date(borrowDate);
    const dueDateObj = new Date(dueDate);
    
    if (dueDateObj <= borrowDateObj) {
        alert('⚠️ Due date must be after borrow date');
        return false;
    }
    
    return true;
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeBorrowModal();
    }
});

// Validate due date is after borrow date
document.getElementById('borrow_date')?.addEventListener('change', function() {
    const borrowDate = new Date(this.value);
    const dueDate = new Date(document.getElementById('due_date').value);
    if (dueDate <= borrowDate) {
        const newDueDate = new Date(borrowDate);
        newDueDate.setDate(newDueDate.getDate() + 14);
        document.getElementById('due_date').value = newDueDate.toISOString().split('T')[0];
    }
});

document.getElementById('due_date')?.addEventListener('change', function() {
    const borrowDate = new Date(document.getElementById('borrow_date').value);
    const dueDate = new Date(this.value);
    if (dueDate <= borrowDate) {
        alert('Due date must be after borrow date!');
        const newDueDate = new Date(borrowDate);
        newDueDate.setDate(newDueDate.getDate() + 14);
        this.value = newDueDate.toISOString().split('T')[0];
    }
});

function filterBorrowings() {
    const filterElement = document.getElementById('statusFilter');
    if (!filterElement) return;
    
    const filter = filterElement.value;
    const rows = document.querySelectorAll('.borrowing-row');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const status = row.getAttribute('data-status');
        const isOverdue = row.getAttribute('data-overdue') === '1';
        
        let show = false;
        if (filter === 'all') {
            show = true;
        } else if (filter === 'active') {
            show = status === 'active';
        } else if (filter === 'returned') {
            show = status === 'returned';
        } else if (filter === 'overdue') {
            show = isOverdue;
        }
        
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });
    
    // Show "no results" message if needed
    if (visibleCount === 0) {
        const tbody = document.querySelector('.table tbody');
        if (!tbody) return;
        const existingMsg = tbody.querySelector('.no-results-msg');
        if (!existingMsg) {
            const noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-msg';
            noResultsRow.innerHTML = '<td colspan="8" style="text-align:center; padding: 40px 20px; color: #999;"><div style="font-size: 2rem; margin-bottom: 10px; opacity: 0.3;">🔍</div><p style="font-size: 1rem; margin: 0;">No borrowings match the selected filter.</p></td>';
            tbody.appendChild(noResultsRow);
        }
    } else {
        const existingMsg = document.querySelector('.no-results-msg');
        if (existingMsg) existingMsg.remove();
    }
}

// Apply default filter on load
document.addEventListener('DOMContentLoaded', function() {
    filterBorrowings();
    
    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#memberSearch') && !e.target.closest('#memberSearchResults')) {
            document.getElementById('memberSearchResults').style.display = 'none';
        }
        if (!e.target.closest('#bookSearch') && !e.target.closest('#bookSearchResults')) {
            document.getElementById('bookSearchResults').style.display = 'none';
        }
    });
});
</script>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.overdue-row {
    background-color: #fff3cd !important;
}
.status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
    box-shadow: 0 1px 4px rgba(0,0,0,0.07);
}
.status-active {
    background: linear-gradient(135deg, #e3f2fd 0%, #b3e5fc 100%);
    color: #0c5460;
}
.status-returned {
    background: linear-gradient(135deg, #d4edda 0%, #b2dfdb 100%);
    color: #155724;
}
.status-overdue {
    background: linear-gradient(135deg, #f8d7da 0%, #ffe0b2 100%);
    color: #721c24;
}
.btn.btn-small {
    padding: 6px 12px;
    font-size: 0.85rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s;
}
.btn.btn-small.btn-success {
    background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
    color: white;
    border: none;
    box-shadow: 0 2px 8px rgba(76,175,80,0.2);
}
.btn.btn-small.btn-success:hover {
    background: linear-gradient(135deg, #388e3c 0%, #43a047 100%);
    transform: scale(1.05);
}
.modal-content {
    border-radius: 16px;
    max-width: 550px;
    padding: 0;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.18);
    margin: 50px auto;
    background: white;
}
.modal-close {
    position: absolute;
    top: 20px;
    right: 25px;
    color: white;
    font-size: 2rem;
    opacity: 0.9;
    cursor: pointer;
    transition: opacity 0.2s;
}
.modal-close:hover {
    opacity: 1;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php include 'includes/footer.php'; ?>
