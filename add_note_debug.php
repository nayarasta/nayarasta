<?php
session_start();
header("Content-Type: application/json");
require_once "db.php";

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug: Log all received data
$debug_info = [
    'session' => $_SESSION,
    'post_data' => $_POST,
    'files' => $_FILES,
    'method' => $_SERVER['REQUEST_METHOD']
];

// Check if user is admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        "status" => "error",
        "message" => "Admin access required",
        "debug" => $debug_info
    ]);
    exit;
}

// Validate input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method",
        "debug" => $debug_info
    ]);
    exit;
}

$title = trim($_POST['title'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$description = trim($_POST['description'] ?? '');
$price = floatval($_POST['price'] ?? 0);
$user_id = $_SESSION['user_id'] ?? null;

// Debug: Check user_id
if (!$user_id) {
    echo json_encode([
        "status" => "error",
        "message" => "User ID not found in session",
        "debug" => $debug_info
    ]);
    exit;
}

// Validate required fields
if (empty($title) || empty($subject)) {
    echo json_encode([
        "status" => "error",
        "message" => "Title and subject are required",
        "debug" => [
            'title' => $title,
            'subject' => $subject,
            'post_data' => $_POST
        ]
    ]);
    exit;
}

// Validate subject
$valid_subjects = ['physics', 'biology', 'chemistry', 'english'];
if (!in_array($subject, $valid_subjects)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid subject selected: " . $subject,
        "debug" => ['subject' => $subject, 'valid' => $valid_subjects]
    ]);
    exit;
}

// Debug file upload
if (!isset($_FILES['note_file'])) {
    echo json_encode([
        "status" => "error",
        "message" => "No file uploaded - FILES array missing",
        "debug" => ['files' => $_FILES]
    ]);
    exit;
}

$file = $_FILES['note_file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'File too large (exceeds upload_max_filesize)',
        UPLOAD_ERR_FORM_SIZE => 'File too large (exceeds MAX_FILE_SIZE)',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary directory',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    echo json_encode([
        "status" => "error",
        "message" => "Upload error: " . ($upload_errors[$file['error']] ?? 'Unknown error'),
        "debug" => [
            'error_code' => $file['error'],
            'file_info' => $file,
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads')
        ]
    ]);
    exit;
}

$file_name = $file['name'];
$file_tmp = $file['tmp_name'];
$file_size = $file['size'];

// Check file size (max 10MB)
if ($file_size > 10 * 1024 * 1024) {
    echo json_encode([
        "status" => "error",
        "message" => "File size too large. Maximum 10MB allowed",
        "debug" => [
            'file_size' => $file_size,
            'max_allowed' => 10 * 1024 * 1024,
            'file_size_mb' => round($file_size / 1024 / 1024, 2)
        ]
    ]);
    exit;
}

// Check file type
$allowed_extensions = ['pdf', 'doc', 'docx', 'txt'];
$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid file type. Only PDF, DOC, DOCX, TXT files are allowed",
        "debug" => [
            'file_extension' => $file_extension,
            'allowed' => $allowed_extensions,
            'filename' => $file_name
        ]
    ]);
    exit;
}

// Create notes_files directory if it doesn't exist
$upload_dir = 'notes_files/';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to create upload directory",
            "debug" => [
                'upload_dir' => $upload_dir,
                'current_dir' => getcwd(),
                'is_writable' => is_writable('.')
            ]
        ]);
        exit;
    }
}

// Check if directory is writable
if (!is_writable($upload_dir)) {
    echo json_encode([
        "status" => "error",
        "message" => "Upload directory is not writable",
        "debug" => [
            'upload_dir' => $upload_dir,
            'is_writable' => is_writable($upload_dir),
            'permissions' => substr(sprintf('%o', fileperms($upload_dir)), -4)
        ]
    ]);
    exit;
}

// Generate unique filename
$unique_filename = time() . '_' . uniqid() . '.' . $file_extension;
$file_path = $upload_dir . $unique_filename;

// Move uploaded file
if (!move_uploaded_file($file_tmp, $file_path)) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to upload file",
        "debug" => [
            'tmp_name' => $file_tmp,
            'destination' => $file_path,
            'tmp_exists' => file_exists($file_tmp),
            'dest_dir_writable' => is_writable($upload_dir)
        ]
    ]);
    exit;
}

try {
    // Insert note into database
    $stmt = $conn->prepare("INSERT INTO notes (title, subject, description, file_path, price, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ssssdi", $title, $subject, $description, $file_path, $price, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Note added successfully",
            "note_id" => $conn->insert_id,
            "debug" => [
                'title' => $title,
                'subject' => $subject,
                'file_path' => $file_path,
                'price' => $price,
                'user_id' => $user_id
            ]
        ]);
    } else {
        // Delete uploaded file if database insert fails
        unlink($file_path);
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    // Delete uploaded file if there's an error
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage(),
        "debug" => [
            'sql_error' => $conn->error,
            'exception' => $e->getMessage()
        ]
    ]);
}
?>