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
    
    if (!isset($input['whatsapp_number'])) {
        throw new Exception("WhatsApp number is required");
    }

    $whatsapp_number = trim($input['whatsapp_number']);
    
    // Validate WhatsApp number format (basic validation)
    if (empty($whatsapp_number) || strlen($whatsapp_number) < 10) {
        throw new Exception("Invalid WhatsApp number format");
    }

    // Prepare query to find user by WhatsApp number
    $stmt = $conn->prepare("SELECT id, username, whatsapp_number FROM users WHERE whatsapp_number = ? LIMIT 1");
    
    if (!$stmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }

    $stmt->bind_param("s", $whatsapp_number);
    
    if (!$stmt->execute()) {
        throw new Exception("Query execution error: " . $stmt->error);
    }

    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // User not found
        echo json_encode([
            "status" => "error",
            "message" => "No account found with this WhatsApp number",
            "found" => false
        ]);
        $stmt->close();
        exit;
    }

    // User found
    $user = $result->fetch_assoc();
    
    echo json_encode([
        "status" => "success",
        "message" => "WhatsApp number verified successfully",
        "found" => true,
        "user_id" => (int)$user['id'],
        "username" => $user['username']
    ]);

    $stmt->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Error: " . $e->getMessage(),
        "found" => false
    ]);
}
?>