<?php
header("Content-Type: application/json");
require_once "db.php";

// Get input from POST
$data = json_decode(file_get_contents("php://input"), true);
$username = trim($data['username']);
$password = trim($data['password']);
$code = trim($data['code']);

// Validate
if (!$username || !$password || !$code) {
    echo json_encode(["status" => "error", "message" => "Username, password, and registration code are required"]);
    exit;
}

// Check if registration code is valid and unused
$stmt = $conn->prepare("SELECT id FROM registration_codes WHERE code = ? AND is_used = FALSE");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid or already used registration code"]);
    exit;
}
$stmt->close();

// Check if user already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "Username already exists"]);
    exit;
}
$stmt->close();

// âœ… FIX #1: Insert user with fee_paid = TRUE (default role: user, fee automatically paid)
$hashed = password_hash($password, PASSWORD_BCRYPT);
$stmt = $conn->prepare("INSERT INTO users (username, password, role, fee_paid) VALUES (?, ?, 'user', 1)");
$stmt->bind_param("ss", $username, $hashed);

if ($stmt->execute()) {
    // Mark registration code as used
    $stmt2 = $conn->prepare("UPDATE registration_codes SET is_used = TRUE, used_at = NOW(), used_by = ? WHERE code = ?");
    $stmt2->bind_param("ss", $username, $code);
    $stmt2->execute();
    $stmt2->close();
    
    echo json_encode(["status" => "success", "message" => "Registration successful - Fee status automatically set to paid"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to register"]);
}
$stmt->close();
$conn->close();
?