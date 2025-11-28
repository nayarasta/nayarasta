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
    
    if (!isset($input['user_id'])) {
        throw new Exception("User ID is required");
    }

    $user_id = intval($input['user_id']);
    
    if ($user_id <= 0) {
        throw new Exception("Invalid user ID");
    }

    // Prepare query to fetch username and other safe info
    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id = ? LIMIT 1");
    
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
    
    echo json_encode([
        "status" => "success",
        "message" => "Username retrieved successfully",
        "username" => $user['username'],
        "email" => $user['email'] ?? "Not available"
    ]);

    $stmt->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>