<?php
session_start();
header("Content-Type: application/json");
require_once "db.php";

// Check if user is admin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        "status" => "error",
        "message" => "Admin access required"
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || !isset($input['note_id']) || !isset($input['user_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Note ID and User ID are required"
    ]);
    exit;
}

$note_id = (int)$input['note_id'];
$user_id = (int)$input['user_id'];
$admin_id = $_SESSION['user_id'];

// Validate note exists
$note_check = $conn->prepare("SELECT id FROM notes WHERE id = ?");
$note_check->bind_param("i", $note_id);
$note_check->execute();
if ($note_check->get_result()->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Note not found"
    ]);
    exit;
}
$note_check->close();

// Validate user exists
$user_check = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
$user_check->bind_param("i", $user_id);
$user_check->execute();
$user_result = $user_check->get_result();
if ($user_result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);
    exit;
}
$user_data = $user_result->fetch_assoc();
$user_check->close();

try {
    // Check if access already exists
    $access_check = $conn->prepare("SELECT id FROM user_note_access WHERE user_id = ? AND note_id = ?");
    $access_check->bind_param("ii", $user_id, $note_id);
    $access_check->execute();
    
    if ($access_check->get_result()->num_rows > 0) {
        echo json_encode([
            "status" => "error",
            "message" => "User already has access to this note"
        ]);
        exit;
    }
    $access_check->close();
    
    // Grant access
    $grant_stmt = $conn->prepare("INSERT INTO user_note_access (user_id, note_id, granted_by) VALUES (?, ?, ?)");
    $grant_stmt->bind_param("iii", $user_id, $note_id, $admin_id);
    
    if ($grant_stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Access granted to user: " . $user_data['username']
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to grant access"
        ]);
    }
    
    $grant_stmt->close();

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>