<?php
session_start();
include 'auth_check.php';
include 'config.php';

// Only admin/librarian can access this
require_staff(true);

// Verify database connection
if ($db_error || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['csvFile'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit();
}

if ($_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'Upload error: ';
    switch ($_FILES['csvFile']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errorMsg .= 'File is too large';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMsg .= 'No file was uploaded';
            break;
        default:
            $errorMsg .= 'Unknown error (code: ' . $_FILES['csvFile']['error'] . ')';
    }
    http_response_code(400);
    echo json_encode(['error' => $errorMsg]);
    exit();
}

$file = $_FILES['csvFile'];
$filename = $file['tmp_name'];

// Validate file extension (more reliable than MIME type)
$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($fileExt !== 'csv') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Please upload a CSV file (detected: .' . $fileExt . ')']);
    exit();
}

// Validate file size (10MB max)
if ($file['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Maximum size is 10MB']);
    exit();
}

$imported = 0;
$skipped = 0;
$errors = [];

// Read CSV file
if (($handle = fopen($filename, 'r')) !== FALSE) {
    $rowNumber = 0;
    $isHeader = true;

    // Excel saves "CSV UTF-8" with a BOM and, in many locales, ';' instead of ','
    $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', (string)fgets($handle));
    $delim = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    rewind($handle);

    while (($data = fgetcsv($handle, 0, $delim)) !== FALSE) {
        $rowNumber++;
        
        // Skip header row
        if ($isHeader) {
            $isHeader = false;
            continue;
        }
        
        // Skip rows that are completely empty
        if (count(array_filter($data, fn($cell) => trim((string)$cell) !== '')) === 0) {
            continue;
        }
        
        // Validate required columns
        if (count($data) < 5) {
            $skipped++;
            $errors[] = "Row $rowNumber: Missing required columns (need at least 5)";
            continue;
        }
        
        // Parse CSV columns: title, author, isbn, category, quantity, shelf_number, shelf_row
        $title = trim($data[0] ?? '');
        $author = trim($data[1] ?? '');
        $isbn = trim($data[2] ?? '');
        if ($isbn === '') { $isbn = null; }
        $category = trim($data[3] ?? '');
        $quantity = isset($data[4]) ? (int)trim($data[4]) : 0;
        $shelf_number = isset($data[5]) && trim($data[5]) !== '' ? trim($data[5]) : null;
        $shelf_row = isset($data[6]) && trim($data[6]) !== '' ? trim($data[6]) : null;
        
        // Validate required fields
        if (empty($title) || empty($author)) {
            $skipped++;
            $errors[] = "Row $rowNumber: Title and Author are required";
            continue;
        }
        
        $shelf_number = $shelf_number !== null ? (int)$shelf_number : null;
        if ($too_long = length_error(['Title' => [$title, 255], 'Author' => [$author, 255], 'ISBN' => [$isbn, 20], 'Category' => [$category, 100], 'Row' => [$shelf_row, 10]])) {
            $skipped++;
            $errors[] = "Row $rowNumber: $too_long";
            continue;
        }

        // Validate quantity
        if (!preg_match('/^\d+$/', trim((string)($data[4] ?? '')))) {
            $skipped++;
            $errors[] = "Row $rowNumber: Quantity must be a whole number, 0 or more";
            continue;
        }
        if ($quantity < 0) {
            $skipped++;
            $errors[] = "Row $rowNumber: Quantity must be non-negative";
            continue;
        }
        
        // Check for duplicate ISBN (if ISBN is provided)
        if (!empty($isbn)) {
            $checkStmt = $conn->prepare("SELECT id FROM books WHERE isbn = ?");
            if ($checkStmt) {
                $checkStmt->bind_param("s", $isbn);
                $checkStmt->execute();
                $checkStmt->store_result();
                if ($checkStmt->num_rows > 0) {
                    $skipped++;
                    $errors[] = "Row $rowNumber: Book with ISBN '$isbn' already exists";
                    $checkStmt->close();
                    continue;
                }
                $checkStmt->close();
            }
        }
        
        // Insert book into database
        $stmt = $conn->prepare("INSERT INTO books (title, author, isbn, category, quantity, available_quantity, shelf_number, shelf_row) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssssiiis", $title, $author, $isbn, $category, $quantity, $quantity, $shelf_number, $shelf_row);
            try {
                $stmt->execute();
                $imported++;
            } catch (mysqli_sql_exception $e) {
                $skipped++;
                $errors[] = "Row $rowNumber: " . $e->getMessage();
            }
            $stmt->close();
        } else {
            $skipped++;
            $errors[] = "Row $rowNumber: Failed to prepare statement";
        }
    }
    
    fclose($handle);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to read CSV file']);
    exit();
}

// Return response
if ($imported === 0 && $skipped === 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No valid data found in CSV file. Please check the format.'
    ]);
    exit();
}

$response = [
    'success' => $imported > 0,
    'imported' => $imported,
    'skipped' => $skipped,
    'message' => $imported . ' books imported successfully' . ($skipped > 0 ? ', ' . $skipped . ' skipped' : '')
];

if (!empty($errors) && count($errors) <= 10) {
    $response['errorDetails'] = $errors;
}

echo json_encode($response);
?>
