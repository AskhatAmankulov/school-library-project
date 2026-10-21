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
if (isset($_GET['expired'])) {
    $error = 'You were logged out after an hour without activity. Log in again.';
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

$page_title = 'Log in';
$hide_nav = true;
include 'includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <span class="logo-mark"><?php echo icon('library', 22); ?></span>
            <div>
                <strong>School Library</strong>
                <span>Books, loans and reading history</span>
            </div>
        </div>

        <h1>Log in</h1>
        <p class="lead">Use your library account to continue.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo icon('alert', 16) . htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo icon('check-circle', 16) . htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="auth-panel">
            <div class="form-group">
                <label for="username">Username or email</label>
                <input type="text" id="username" name="username" required autocomplete="username"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-login">Log in</button>
        </form>

        <p class="auth-switch">New student? <a href="register.php">Create an account</a></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
