<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'config.php';

$error = '';
$success = '';

// Existing class names, offered as suggestions in the form
$class_options = [];
try {
    $class_options = $pdo_conn->query(
        "SELECT DISTINCT class FROM members WHERE class IS NOT NULL AND class <> '' ORDER BY class"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Suggestions are optional
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields!';
    } elseif (strlen($username) < 3 || !preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
        $error = 'Username must be at least 3 characters: letters, numbers, _ . - only.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email!';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters!';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match!';
    } else {
        try {
            // Username or email already taken?
            $check = $pdo_conn->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM users WHERE username = ?) AS u,
                    (SELECT COUNT(*) FROM users WHERE email = ?) + (SELECT COUNT(*) FROM members WHERE email = ?) AS e"
            );
            $check->execute([$username, $email, $email]);
            $taken = $check->fetch(PDO::FETCH_ASSOC);

            if ($taken['u'] > 0) {
                $error = 'This username is already taken.';
            } elseif ($taken['e'] > 0) {
                $error = 'An account with this email already exists.';
            } else {
                // Create the member (library card) and the login account together
                $pdo_conn->beginTransaction();

                $name_parts = preg_split('/\s+/', $full_name, 2);
                $stmt = $pdo_conn->prepare(
                    "INSERT INTO members (name, first_name, last_name, class, email, membership_date, status)
                     VALUES (?, ?, ?, ?, ?, CURDATE(), 'active')"
                );
                $stmt->execute([$full_name, $name_parts[0], $name_parts[1] ?? '', $class !== '' ? $class : null, $email]);
                $member_id = (int)$pdo_conn->lastInsertId();

                $stmt = $pdo_conn->prepare(
                    "INSERT INTO users (username, email, password_hash, role, member_id)
                     VALUES (?, ?, ?, 'user', ?)"
                );
                $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $member_id]);

                $pdo_conn->commit();

                header("Location: login.php?registered=1");
                exit();
            }
        } catch (PDOException $e) {
            if ($pdo_conn->inTransaction()) {
                $pdo_conn->rollBack();
            }
            $error = 'Could not create the account. Make sure add_users_table.sql was imported.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Library Management System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .register-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h1 {
            color: #2c3e50;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .register-header p {
            color: #7f8c8d;
            font-size: 1rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-register {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
        }
        
        .btn-register:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-links {
            text-align: center;
            margin-top: 20px;
            color: #7f8c8d;
        }
        
        .form-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .form-links a:hover {
            color: #764ba2;
        }
        
        .password-requirements {
            background-color: #f0f0f0;
            border-left: 4px solid #667eea;
            padding: 12px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #555;
        }
        
        .password-requirements h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .password-requirements ul {
            margin-left: 20px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>📚 Library</h1>
            <p>Create New Account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <div style="margin-top: 10px;">
                    <a href="login.php" style="color: #155724; text-decoration: none; font-weight: 600;">→ Go to Login</a>
                </div>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required placeholder="First and last name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="class">Class <span style="font-weight: 400; color: #7f8c8d;">(optional)</span></label>
                <input type="text" id="class" name="class" list="class_list" placeholder="e.g. Year 10A" value="<?php echo htmlspecialchars($_POST['class'] ?? ''); ?>">
                <datalist id="class_list">
                    <?php foreach ($class_options as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required placeholder="Choose a username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter password (min 6 characters)">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm password">
            </div>
            
            <button type="submit" class="btn-register">Create Account</button>
            
            <div class="form-links">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </form>
        
        <div class="password-requirements">
            <h4>✓ Password Requirements:</h4>
            <ul>
                <li>At least 6 characters long</li>
                <li>Must match confirmation</li>
                <li>Use strong passwords</li>
            </ul>
        </div>
    </div>
</body>
</html>
