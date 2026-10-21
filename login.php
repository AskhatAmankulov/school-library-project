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

if (isset($_GET['registered'])) {
    $success = 'Account created! You can log in now.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username and password are required!';
    } else {
        try {
            // Accept either username or email
            $stmt = $pdo_conn->prepare(
                "SELECT id, username, email, password_hash, role, member_id
                 FROM users WHERE username = ? OR email = ? LIMIT 1"
            );
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);

                // user_id = member id (the rest of the app uses it to load profile/borrowings)
                $_SESSION['user_id'] = (int)$user['member_id'];
                $_SESSION['account_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();

                $pdo_conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                         ->execute([$user['id']]);

                header("Location: index.php");
                exit();
            }
            $error = 'Invalid username or password!';
        } catch (PDOException $e) {
            $error = 'The users table is missing. Import add_users_table.sql in phpMyAdmin first.';
        }
    }
}

// Use site header/footer for consistent theme
include 'includes/header.php';
?>

<div class="login-page">
    <div class="login-container">
        <div class="login-header">
            <h1>📚 Library</h1>
            <p>Management System</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username or email</label>
                <input type="text" id="username" name="username" required autocomplete="username" placeholder="Enter username or email">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter password">
            </div>

            <button type="submit" class="btn btn-login">Login</button>
        </form>

        <p style="text-align: center; margin-top: 20px;">
            No account yet? <a href="register.php">Create one</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
