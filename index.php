<?php
session_start();
include 'auth_check.php';
include 'config.php';
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
        $result = $conn->query("SELECT avatar_url FROM members WHERE id = $user_id");
        if ($result && $result->num_rows > 0) {
            $student_avatar = $result->fetch_assoc()['avatar_url'];
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
        
        // Recent books (last 3)
        $result = $conn->query("SELECT b.title, b.author, b.cover_url, br.borrow_date, br.status 
                               FROM borrowings br 
                               JOIN books b ON br.book_id = b.id 
                               WHERE br.member_id = $user_id 
                               ORDER BY br.borrow_date DESC 
                               LIMIT 3");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $student_recent_books[] = $row;
            }
        }
    }
}
?>

<h1>📊 Library Dashboard</h1>

<?php if (isset($_SESSION['success_message'])): ?>
    <div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3); display: flex; align-items: center; gap: 10px; animation: slideDown 0.3s ease;">
        <span style="font-size: 1.5rem;">✓</span>
        <span style="font-weight: 600;"><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3); display: flex; align-items: center; gap: 10px; animation: slideDown 0.3s ease;">
        <span style="font-size: 1.5rem;">⚠</span>
        <span style="font-weight: 600;"><?php echo htmlspecialchars($_SESSION['error_message']); ?></span>
    </div>
    <?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<?php if (!is_staff()): ?>
<!-- Student Personal Report -->
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 16px; padding: 30px; margin-bottom: 30px; color: white; box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);">
    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
        <div style="position: relative;">
            <?php if (!empty($student_avatar)): ?>
                <img src="<?php echo htmlspecialchars($student_avatar); ?>" alt="Profile" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,0.3); box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
            <?php else: ?>
                <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; border: 4px solid rgba(255,255,255,0.3); box-shadow: 0 4px 15px rgba(0,0,0,0.2);">👤</div>
            <?php endif; ?>
        </div>
        <div>
            <h2 style="margin: 0; font-size: 1.8rem;">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h2>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 1rem;">Here's your reading journey</p>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 25px;">
        <div style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.25)'; this.style.transform='translateY(-5px)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.15)'; this.style.transform='translateY(0)'">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">📚</div>
            <div style="font-size: 2rem; font-weight: bold; margin-bottom: 5px;"><?php echo $student_total_borrowed; ?></div>
            <div style="opacity: 0.9; font-size: 0.9rem;">Books Borrowed</div>
        </div>
        
        <div style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.25)'; this.style.transform='translateY(-5px)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.15)'; this.style.transform='translateY(0)'">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">📖</div>
            <div style="font-size: 2rem; font-weight: bold; margin-bottom: 5px;"><?php echo $student_currently_reading; ?></div>
            <div style="opacity: 0.9; font-size: 0.9rem;">Currently Reading</div>
        </div>
        
        <div style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.25)'; this.style.transform='translateY(-5px)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.15)'; this.style.transform='translateY(0)'">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">✅</div>
            <div style="font-size: 2rem; font-weight: bold; margin-bottom: 5px;"><?php echo $student_books_returned; ?></div>
            <div style="opacity: 0.9; font-size: 0.9rem;">Books Completed</div>
        </div>
        
        <div style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s;" onmouseover="this.style.background='rgba(255, 255, 255, 0.25)'; this.style.transform='translateY(-5px)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.15)'; this.style.transform='translateY(0)'">
            <div style="font-size: 2.5rem; margin-bottom: 10px;">🔥</div>
            <div style="font-size: 2rem; font-weight: bold; margin-bottom: 5px;"><?php echo $student_reading_streak; ?></div>
            <div style="opacity: 0.9; font-size: 0.9rem;">Month Streak</div>
        </div>
    </div>
    
    <div style="background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(10px); padding: 20px; border-radius: 12px; margin-top: 20px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
            <span style="font-size: 1.5rem;">⭐</span>
            <span style="font-weight: 600; font-size: 1.1rem;">Favorite Genre</span>
        </div>
        <div style="font-size: 1.3rem; font-weight: bold;"><?php echo htmlspecialchars($student_favorite_genre); ?></div>
    </div>
</div>

<?php if (count($student_recent_books) > 0): ?>
<!-- Recent Reading Activity -->
<div style="background: white; border-radius: 16px; padding: 30px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">
    <h2 style="margin-top: 0; color: #333; display: flex; align-items: center; gap: 10px;"><span style="font-size: 1.5rem;">📚</span> Recent Reading Activity</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
        <?php foreach ($student_recent_books as $book): ?>
        <div style="background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%); border-radius: 12px; padding: 15px; display: flex; gap: 15px; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.05);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.05)'">
            <?php if (!empty($book['cover_url'])): ?>
                <img src="<?php echo htmlspecialchars($book['cover_url']); ?>" alt="Cover" style="width: 60px; height: 90px; object-fit: cover; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
            <?php else: ?>
                <div style="width: 60px; height: 90px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">📖</div>
            <?php endif; ?>
            <div style="flex: 1;">
                <div style="font-weight: 600; color: #333; margin-bottom: 5px; font-size: 0.95rem;"><?php echo htmlspecialchars($book['title']); ?></div>
                <div style="color: #666; font-size: 0.85rem; margin-bottom: 8px;"><?php echo htmlspecialchars($book['author']); ?></div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="background: <?php echo $book['status'] === 'active' ? '#4CAF50' : '#2196F3'; ?>; color: white; padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">
                        <?php echo $book['status'] === 'active' ? '📖 Reading' : '✅ Completed'; ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Motivational Message -->
<div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); border-radius: 16px; padding: 25px; margin-bottom: 30px; color: white; box-shadow: 0 4px 20px rgba(76, 175, 80, 0.3); text-align: center;">
    <div style="font-size: 2rem; margin-bottom: 10px;">🎯</div>
    <?php if ($student_currently_reading > 0): ?>
        <h3 style="margin: 0 0 10px 0; font-size: 1.4rem;">Keep Going! 🚀</h3>
        <p style="margin: 0; opacity: 0.95; font-size: 1.1rem;">You're currently reading <?php echo $student_currently_reading; ?> book<?php echo $student_currently_reading > 1 ? 's' : ''; ?>. Finish strong!</p>
    <?php elseif ($student_books_returned > 0): ?>
        <h3 style="margin: 0 0 10px 0; font-size: 1.4rem;">Ready for Your Next Adventure? 📖</h3>
        <p style="margin: 0; opacity: 0.95; font-size: 1.1rem;">You've completed <?php echo $student_books_returned; ?> book<?php echo $student_books_returned > 1 ? 's' : ''; ?>. Check out something new!</p>
    <?php else: ?>
        <h3 style="margin: 0 0 10px 0; font-size: 1.4rem;">Start Your Reading Journey! 📚</h3>
        <p style="margin: 0; opacity: 0.95; font-size: 1.1rem;">Browse our collection and borrow your first book today!</p>
    <?php endif; ?>
    <a href="books.php" style="display: inline-block; margin-top: 15px; background: white; color: #4CAF50; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.1);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.15)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)'">Browse Books</a>
</div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="color: #3498db;">📚</div>
        <h3><?php echo $total_books; ?></h3>
        <p>Total Books</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color: #9b59b6;">👥</div>
        <h3><?php echo $total_members; ?></h3>
        <p>Active Members</p>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="color: #27ae60;">📖</div>
        <h3><?php echo $active_borrowings; ?></h3>
        <p>Active Borrowings</p>
    </div>
    <div class="stat-card alert-card">
        <div class="stat-icon" style="color: #e74c3c;">⚠️</div>
        <h3><?php echo $overdue_count; ?></h3>
        <p>Overdue Books</p>
    </div>
</div>

<?php if (is_staff()): ?>
    <?php
    // Fetch detailed overdue books information
    $overdue_query = "SELECT b.title, b.isbn, m.name as member_name, m.class, 
                      br.borrow_date, br.due_date, DATEDIFF(CURDATE(), br.due_date) as days_overdue,
                      br.id as borrowing_id
                      FROM borrowings br
                      JOIN books b ON br.book_id = b.id
                      JOIN members m ON br.member_id = m.id
                      WHERE br.status = 'active' AND br.due_date < CURDATE()
                      ORDER BY days_overdue DESC";
    $overdue_result = mysqli_query($conn, $overdue_query);
    ?>
    
    <div style="margin-top: 30px;">
        <h2 style="color: #2c3e50; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 28px;">⚠️</span> Overdue Books Details
        </h2>
        
        <div style="background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%); 
                    border-radius: 15px; 
                    padding: 25px; 
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                    overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white;">
                        <th style="padding: 15px; text-align: left; border-radius: 10px 0 0 0;">Book Title</th>
                        <th style="padding: 15px; text-align: left;">ISBN</th>
                        <th style="padding: 15px; text-align: left;">Member</th>
                        <th style="padding: 15px; text-align: left;">Class</th>
                        <th style="padding: 15px; text-align: center;">Borrow Date</th>
                        <th style="padding: 15px; text-align: center;">Due Date</th>
                        <th style="padding: 15px; text-align: center; border-radius: 0 10px 0 0;">Days Overdue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($overdue_result && mysqli_num_rows($overdue_result) > 0): ?>
                        <?php while($overdue = mysqli_fetch_assoc($overdue_result)): ?>
                            <?php 
                            $severity_color = '#e74c3c'; // Default: severe (red)
                            $severity_text = 'Severe';
                            if ($overdue['days_overdue'] <= 3) {
                                $severity_color = '#f39c12'; // Mild (orange)
                                $severity_text = 'Mild';
                            } elseif ($overdue['days_overdue'] <= 7) {
                                $severity_color = '#e67e22'; // Moderate (dark orange)
                                $severity_text = 'Moderate';
                            }
                            ?>
                            <tr style="border-bottom: 1px solid #ecf0f1; transition: all 0.3s ease;" 
                                onmouseover="this.style.background='#f8f9fa'" 
                                onmouseout="this.style.background='transparent'">
                                <td style="padding: 15px;">
                                    <strong style="color: #2c3e50;"><?php echo htmlspecialchars($overdue['title']); ?></strong>
                                </td>
                                <td style="padding: 15px; color: #7f8c8d; font-size: 0.9em;">
                                    <?php echo htmlspecialchars($overdue['isbn']); ?>
                                </td>
                                <td style="padding: 15px;">
                                    <strong style="color: #34495e;"><?php echo htmlspecialchars($overdue['member_name']); ?></strong>
                                </td>
                                <td style="padding: 15px; color: #7f8c8d;">
                                    <?php echo htmlspecialchars($overdue['class']); ?>
                                </td>
                                <td style="padding: 15px; text-align: center; color: #7f8c8d;">
                                    <?php echo date('M d, Y', strtotime($overdue['borrow_date'])); ?>
                                </td>
                                <td style="padding: 15px; text-align: center; color: #7f8c8d;">
                                    <?php echo date('M d, Y', strtotime($overdue['due_date'])); ?>
                                </td>
                                <td style="padding: 15px; text-align: center;">
                                    <span style="background: <?php echo $severity_color; ?>; 
                                                color: white; 
                                                padding: 8px 15px; 
                                                border-radius: 20px; 
                                                font-weight: bold;
                                                font-size: 0.9em;
                                                box-shadow: 0 3px 10px rgba(0,0,0,0.2);">
                                        <?php echo $overdue['days_overdue']; ?> days
                                    </span>
                                    <div style="font-size: 0.8em; color: <?php echo $severity_color; ?>; margin-top: 5px;">
                                        <?php echo $severity_text; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="padding: 30px; text-align: center;">
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 15px;">
                                    <span style="font-size: 4rem;">✅</span>
                                    <div style="color: #27ae60; font-size: 1.3rem; font-weight: 600;">No Overdue Books</div>
                                    <div style="color: #7f8c8d; font-size: 1rem;">All books are returned on time or within the due date.</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($overdue_result && mysqli_num_rows($overdue_result) > 0): ?>
            <div style="margin-top: 20px; padding: 15px; background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); 
                        border-left: 4px solid #f39c12; border-radius: 5px;">
                <strong style="color: #856404;">📌 Note:</strong>
                <span style="color: #856404;"> Please contact members with overdue books to arrange returns.</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Avatar Upload Modal (Students Only) -->
<?php if (!is_staff()): ?>
<div id="avatarModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px);">
    <div style="background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%); margin: 5% auto; padding: 0; border-radius: 20px; width: 90%; max-width: 500px; box-shadow: 0 15px 50px rgba(0,0,0,0.3); animation: slideDown 0.3s ease;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 25px; border-radius: 20px 20px 0 0; position: relative;">
            <h2 style="margin: 0; color: white; font-size: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.8rem;">📷</span> Update Profile Picture
            </h2>
            <button onclick="closeAvatarModal()" style="position: absolute; right: 20px; top: 50%; transform: translateY(-50%); background: rgba(255,255,255,0.2); border: none; color: white; font-size: 1.8rem; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all 0.3s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'; this.style.transform='translateY(-50%) rotate(90deg)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'; this.style.transform='translateY(-50%) rotate(0deg)'">×</button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" style="padding: 30px;">
            <input type="hidden" name="update_student_avatar" value="1">
            
            <div style="text-align: center; margin-bottom: 25px;">
                <div id="previewContainer" style="display: inline-block; position: relative;">
                    <?php if (!empty($student_avatar)): ?>
                        <img id="avatarPreview" src="<?php echo htmlspecialchars($student_avatar); ?>" alt="Preview" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 5px solid #667eea; box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);">
                    <?php else: ?>
                        <div id="avatarPreview" style="width: 150px; height: 150px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; font-size: 4rem; color: white; border: 5px solid #667eea; box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);">👤</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 8px; color: #2c3e50; font-weight: 600; font-size: 0.95rem;">
                    <span style="font-size: 1.2rem;">📁</span> Upload from Computer
                </label>
                <input type="file" name="avatar_file" accept="image/*" onchange="previewImage(this)" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1rem; transition: all 0.3s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102, 126, 234, 0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
                <small style="color: #7f8c8d; font-size: 0.85rem; margin-top: 5px; display: block;">Max size: 5MB (JPG, PNG, WEBP, or GIF)</small>
            </div>
            
            <div style="margin-bottom: 25px;">
                <label style="display: block; margin-bottom: 8px; color: #2c3e50; font-weight: 600; font-size: 0.95rem;">
                    <span style="font-size: 1.2rem;">🔗</span> Or Enter Image URL
                </label>
                <input type="url" name="avatar_url" placeholder="https://example.com/avatar.jpg" style="width: 100%; padding: 12px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 1rem; transition: all 0.3s;" onfocus="this.style.borderColor='#667eea'; this.style.boxShadow='0 0 0 3px rgba(102, 126, 234, 0.1)'" onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none'">
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" style="flex: 1; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.4)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(102, 126, 234, 0.3)'">
                    <span style="font-size: 1.2rem;">✓</span> Update Picture
                </button>
                <button type="button" onclick="closeAvatarModal()" style="flex: 0.3; background: #95a5a6; color: white; padding: 14px; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s;" onmouseover="this.style.background='#7f8c8d'" onmouseout="this.style.background='#95a5a6'">
                    Cancel
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
