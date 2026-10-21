<?php
// Report database errors as exceptions on every PHP version (8.1+ does this by default)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
include_once __DIR__ . '/includes/input.php';

$servername = "localhost";
$username = "root";
$password = "";
$database = "library";
$port = 3306;

// Try to connect using PDO with mysql_native_password
try {
    $pdo_conn = new PDO("mysql:host=$servername;port=$port;dbname=$database;charset=utf8", $username, $password, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Create mysqli connection for backward compatibility
    $conn = @new mysqli($servername, $username, $password, $database, $port);
    
    if ($conn->connect_error) {
        // If mysqli fails, at least PDO is working
        $conn = null;
    } else {
        $conn->set_charset("utf8");
    }
    
    $db_error = false;
} catch (PDOException $e) {
    // Try alternative ports
    try {
        $pdo_conn = new PDO("mysql:host=$servername;port=3307;dbname=$database;charset=utf8", $username, $password, [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $port = 3307;
        $conn = @new mysqli($servername, $username, $password, $database, 3307);
    } catch (PDOException $e2) {
        die("Database connection failed: " . $e->getMessage() . "<br>Please make sure MySQL is running in XAMPP.<br><br>To fix: Open XAMPP Control Panel as Administrator and restart MySQL.");
    }
}
?>
