<?php
session_start();
header("Content-Type: application/json");
require_once "db.php";

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Please login first"
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

try {
    // Base query to get all notes with creator info
    $sql = "SELECT n.*, u.username as created_by_username FROM notes n 
            LEFT JOIN users u ON n.created_by = u.id 
            ORDER BY n.created_at DESC";
    
    $result = $conn->query($sql);
    $notes = [];
    
    while ($row = $result->fetch_assoc()) {
        // Check if current user has access to this note
        $hasAccess = false;
        
        if ($user_role === 'admin') {
            $hasAccess = true; // Admins have access to all notes
        } else {
            // Check user_note_access table
            $access_stmt = $conn->prepare("SELECT id FROM user_note_access WHERE user_id = ? AND note_id = ?");
            $access_stmt->bind_param("ii", $user_id, $row['id']);
            $access_stmt->execute();
            $access_result = $access_stmt->get_result();
            $hasAccess = $access_result->num_rows > 0;
            $access_stmt->close();
        }
        
        $notes[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'subject' => $row['subject'],
            'description' => $row['description'],
            'price' => (float)$row['price'],
            'created_by' => $row['created_by_username'],
            'created_at' => $row['created_at'],
            'hasAccess' => $hasAccess
        ];
    }
    
    echo json_encode([
        "status" => "success",
        "data" => $notes,
        "user_role" => $user_role
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Error fetching notes: " . $e->getMessage()
    ]);
}
?>