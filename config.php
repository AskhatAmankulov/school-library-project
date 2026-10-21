<?php
// Report database errors as exceptions on every PHP version (8.1+ does this by default)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once __DIR__ . '/includes/input.php';

// Database settings. The defaults match XAMPP; Docker and the automatic
// tests set them through environment variables (see docker-compose.yml).
$servername = getenv('DB_HOST') ?: 'localhost';
$username   = getenv('DB_USER') ?: 'root';
$password   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$database   = getenv('DB_NAME') ?: 'library';
$ports      = getenv('DB_PORT') ? [(int)getenv('DB_PORT')] : [3306, 3307]; // XAMPP sometimes moves MySQL to 3307

$db_error = true;
$pdo_conn = null;
$conn = null;
$last_error = '';

foreach ($ports as $port) {
    try {
        // utf8mb4 = full Unicode, including Kazakh letters and emoji in reviews
        $pdo_conn = new PDO("mysql:host=$servername;port=$port;dbname=$database;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $conn = new mysqli($servername, $username, $password, $database, $port);
        $conn->set_charset('utf8mb4');
        $db_error = false;
        break;
    } catch (PDOException | mysqli_sql_exception $e) {
        $last_error = $e->getMessage();
    }
}

if ($db_error) {
    http_response_code(503);
    die("Database connection failed: " . htmlspecialchars($last_error)
        . "<br>Make sure MySQL is running (in XAMPP: open the Control Panel and start MySQL)"
        . " and that the <code>$database</code> database exists.");
}

// The database exists but nothing was imported yet: say what to do instead of failing later
if ($conn->query("SHOW TABLES LIKE 'books'")->num_rows === 0) {
    http_response_code(503);
    die("The <code>" . htmlspecialchars($database) . "</code> database is empty. Import <code>library.sql</code>,"
        . " then <code>add_users_table.sql</code> and <code>fix_data.sql</code> (in phpMyAdmin: select the database, Import tab)."
        . " With Docker this happens automatically on the first start.");
}

