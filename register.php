<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required!';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email!';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters!';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match!';
    } else {
        // In demo mode, we'll simulate registration
        // In production, save to database
        $_SESSION['new_user'] = [
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => 'user',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $success = 'Registration successful! You can now login.';
        // Clear form
        $_POST = [];
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
