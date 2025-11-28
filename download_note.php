<?php
session_start();
require_once "db.php";

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    die("Please login first to download notes.");
}

// Get note ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid note ID.");
}

$note_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

try {
    // Get note information
    $stmt = $conn->prepare("SELECT title, file_path FROM notes WHERE id = ?");
    $stmt->bind_param("i", $note_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        die("Note not found.");
    }
    
    $note = $result->fetch_assoc();
    $stmt->close();
    
    // Check if user has access (admin has access to all)
    if ($user_role !== 'admin') {
        $access_stmt = $conn->prepare("SELECT id FROM user_note_access WHERE user_id = ? AND note_id = ?");
        $access_stmt->bind_param("ii", $user_id, $note_id);
        $access_stmt->execute();
        $access_result = $access_stmt->get_result();
        
        if ($access_result->num_rows === 0) {
            die("You don't have access to this note. Please contact admin.");
        }
        $access_stmt->close();
    }
    
    // Check if file exists
    $file_path = $note['file_path'];
    if (!file_exists($file_path)) {
        die("File not found on server. Please contact admin.");
    }
    
    // Get file info
    $file_name = basename($file_path);
    $file_size = filesize($file_path);
    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
    
    // Set appropriate headers
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $note['title'] . '.' . $file_extension . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . $file_size);
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read and output file
    readfile($file_path);
    exit;
    
} catch (Exception $e) {
    die("Error downloading file: " . $e->getMessage());
}
?>