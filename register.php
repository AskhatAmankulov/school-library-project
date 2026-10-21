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
    } elseif ($too_long = length_error(['Full name' => [$full_name, 100], 'Class' => [$class, 50], 'Username' => [$username, 50], 'Email' => [$email, 255]])) {
        $error = $too_long;
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

$page_title = 'Create account';
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

        <h1>Create your account</h1>
        <p class="lead">For students. Your librarian can see your loans and reviews.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo icon('alert', 16) . htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="auth-panel">
            <div class="form-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" required autocomplete="name"
                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="class">Class <span class="muted">(optional)</span></label>
                <input type="text" id="class" name="class" list="class_list" placeholder="e.g. Year 10A"
                       value="<?php echo htmlspecialchars($_POST['class'] ?? ''); ?>">
                <datalist id="class_list">
                    <?php foreach ($class_options as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="username"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                <p class="form-hint">At least 3 characters: letters, numbers, _ . -</p>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password">
                <p class="form-hint">At least 6 characters.</p>
            </div>

            <div class="form-group">
                <label for="confirm_password">Repeat password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-login">Create account</button>
        </form>

        <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
