<?php
session_start();

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

include 'demo_users.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required!';
    } else {
        // Check credentials against demo users
        $user_found = false;

        foreach ($demo_users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password'])) {
                // Password is correct
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();

                $user_found = true;
                header("Location: index.php");
                exit();
            }
        }

        if (!$user_found) {
            $error = 'Invalid username or password!';
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

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="username" placeholder="Enter username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter password">
            </div>

            <button type="submit" class="btn btn-login">Login</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
