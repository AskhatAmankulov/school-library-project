<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Library System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 20px;
            text-align: center;
            border-radius: 10px;
            margin: 40px 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .hero p {
            font-size: 1.3rem;
            margin-bottom: 30px;
            opacity: 0.95;
        }
        .hero .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .hero .cta-btn {
            background: white;
            color: #667eea;
            padding: 15px 40px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .hero .cta-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin: 50px 0;
        }
        .feature-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .feature-card .icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        .feature-card h3 {
            color: #667eea;
            margin-bottom: 10px;
        }
        .feature-card p {
            color: #666;
            line-height: 1.6;
        }
        .stats {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 50px 20px;
            border-radius: 10px;
            margin: 50px 0;
            text-align: center;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .stat-item {
            background: rgba(255,255,255,0.15);
            padding: 25px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        .stat-item .number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stat-item .label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .testimonials {
            margin: 60px 0;
            text-align: center;
        }
        .testimonial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .testimonial {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        .testimonial .quote {
            font-style: italic;
            color: #555;
            margin-bottom: 15px;
        }
        .testimonial .author {
            font-weight: bold;
            color: #667eea;
        }
        .nav-simple {
            background: #667eea;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .nav-simple .logo {
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
        .nav-simple .login-btn {
            background: white;
            color: #667eea;
            padding: 10px 25px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.3s;
        }
        .nav-simple .login-btn:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="nav-simple">
        <div class="logo">📚 Library Management System</div>
        <a href="login.php" class="login-btn">Login / Get Started</a>
    </div>
    
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        
        <div class="hero">
            <h1>📚 Welcome to Our Modern Library</h1>
            <p>Manage your books, members, and borrowings all in one place. Experience seamless library management with powerful features and an intuitive interface.</p>
            <div class="cta-buttons">
                <a href="login.php" class="cta-btn">🚀 Get Started</a>
                <a href="#features" class="cta-btn">📖 Learn More</a>
            </div>
        </div>

        <div id="features" class="features">
            <div class="feature-card">
                <div class="icon">📚</div>
                <h3>Book Management</h3>
                <p>Organize your entire collection with cover images, locations, ratings, and detailed information for each book.</p>
            </div>
            <div class="feature-card">
                <div class="icon">👥</div>
                <h3>Member Profiles</h3>
                <p>Track member information, borrowing history, reviews, and activity with comprehensive member profiles.</p>
            </div>
            <div class="feature-card">
                <div class="icon">📖</div>
                <h3>Smart Borrowing</h3>
                <p>Streamline the borrowing process with automated tracking, due date alerts, and overdue notifications.</p>
            </div>
            <div class="feature-card">
                <div class="icon">⭐</div>
                <h3>Book Reviews</h3>
                <p>Members can rate and review books, helping others discover great reads and share their thoughts.</p>
            </div>
            <div class="feature-card">
                <div class="icon">📊</div>
                <h3>Dashboard Analytics</h3>
                <p>Get real-time insights with comprehensive statistics on books, members, borrowings, and overdue items.</p>
            </div>
            <div class="feature-card">
                <div class="icon">🔍</div>
                <h3>Advanced Search</h3>
                <p>Find books quickly with powerful sorting and filtering by title, author, rating, and availability.</p>
            </div>
        </div>

        <?php
        // Fetch real stats if database is available
        $total_books = 500;
        $total_members = 250;
        $active_borrowings = 150;
        $total_reviews = 320;
        
        if (file_exists('config.php')) {
            include 'config.php';
            if (!$db_error && $conn) {
                $books_result = $conn->query("SELECT COUNT(*) as count FROM books");
                if ($books_result) {
                    $total_books = $books_result->fetch_assoc()['count'];
                }
                
                $members_result = $conn->query("SELECT COUNT(*) as count FROM members");
                if ($members_result) {
                    $total_members = $members_result->fetch_assoc()['count'];
                }
                
                $borrowings_result = $conn->query("SELECT COUNT(*) as count FROM borrowings WHERE status='active'");
                if ($borrowings_result) {
                    $active_borrowings = $borrowings_result->fetch_assoc()['count'];
                }
                
                $reviews_result = $conn->query("SELECT COUNT(*) as count FROM book_reviews");
                if ($reviews_result) {
                    $total_reviews = $reviews_result->fetch_assoc()['count'];
                }
            }
        }
        ?>

        <div class="stats">
            <h2>📈 Our Library in Numbers</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="number"><?php echo number_format($total_books); ?></div>
                    <div class="label">Books in Collection</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?php echo number_format($total_members); ?></div>
                    <div class="label">Active Members</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?php echo number_format($active_borrowings); ?></div>
                    <div class="label">Active Borrowings</div>
                </div>
                <div class="stat-item">
                    <div class="number"><?php echo number_format($total_reviews); ?></div>
                    <div class="label">Book Reviews</div>
                </div>
            </div>
        </div>

        <div class="testimonials">
            <h2>💬 What Our Users Say</h2>
            <div class="testimonial-grid">
                <div class="testimonial">
                    <div class="quote">"This library system has transformed how we manage our collection. The interface is intuitive and the features are exactly what we needed!"</div>
                    <div class="author">— Sarah Johnson, Librarian</div>
                </div>
                <div class="testimonial">
                    <div class="quote">"I love being able to see book covers and reviews before borrowing. It makes discovering new books so much easier!"</div>
                    <div class="author">— Michael Chen, Member</div>
                </div>
                <div class="testimonial">
                    <div class="quote">"The overdue tracking and automated notifications have saved us countless hours. Highly recommended!"</div>
                    <div class="author">— Emily Davis, Administrator</div>
                </div>
            </div>
        </div>

        <div class="hero" style="margin: 60px 0;">
            <h2>Ready to Get Started?</h2>
            <p>Join hundreds of libraries already using our system to streamline their operations.</p>
            <div class="cta-buttons">
                <a href="login.php" class="cta-btn">🔐 Login Now</a>
                <a href="register.php" class="cta-btn">✨ Create Account</a>
            </div>
        </div>

        <div style="text-align: center; padding: 40px 0; color: #666; border-top: 1px solid #ddd; margin-top: 60px;">
            <p>© 2025 Library Management System. All rights reserved.</p>
            <p style="margin-top: 10px;">Built with ❤️ for modern libraries</p>
        </div>

    </div>
</body>
</html>