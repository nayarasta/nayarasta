<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Content-Type: application/json");

try {
    require_once "db.php";
    
    // Check if database connection exists
    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection not available");
    }

    // Get request method and data
    $request_method = $_SERVER['REQUEST_METHOD'];
    
    if ($request_method !== 'POST') {
        throw new Exception("Invalid request method. POST required.");
    }

    // Get JSON input
    $input = json_decode(file_get_contents("php://input"), true);
    
    // Validate required fields
    if (!isset($input['user_id']) || !isset($input['action'])) {
        throw new Exception("User ID and action are required");
    }

    $user_id = intval($input['user_id']);
    $action = trim($input['action']);
    
    if ($user_id <= 0) {
        throw new Exception("Invalid user ID");
    }

    // ACTION 1: Get current password (view only)
    if ($action === 'view') {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        $stmt->bind_param("i", $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Query execution error: " . $stmt->error);
        }

        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("User not found");
        }

        $user = $result->fetch_assoc();
        
        // Return password (consider if this is secure enough for your use case)
        echo json_encode([
            "status" => "success",
            "message" => "Current password retrieved",
            "password" => $user['password']
        ]);

        $stmt->close();
        exit;
    }

    // ACTION 2: Update/Change password
    if ($action === 'change') {
        if (!isset($input['new_password'])) {
            throw new Exception("New password is required");
        }

        $new_password = trim($input['new_password']);
        
        // Validate password strength
        if (strlen($new_password) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }

        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password in database
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Password update failed: " . $stmt->error);
        }

        if ($stmt->affected_rows === 0) {
            throw new Exception("User not found or password unchanged");
        }

        echo json_encode([
            "status" => "success",
            "message" => "Password updated successfully",
            "updated" => true
        ]);

        $stmt->close();
        exit;
    }

    // Invalid action
    throw new Exception("Invalid action. Use 'view' or 'change'");

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>