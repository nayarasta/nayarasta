<?php
session_start(); // Start session at the very beginning
header("Content-Type: application/json");
require_once "db.php";

// Decode JSON input
$data = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!$data || empty($data['username']) || empty($data['password'])) {
    echo json_encode(["status" => "error", "message" => "Username and password are required"]);
    exit;
}

$username = trim($data['username']);
$password = trim($data['password']);

// Fetch user from database
$stmt = $conn->prepare("SELECT id, username, password, role, fee_paid FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

// If no user found
if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid username or password"]);
    exit;
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password'])) {
    echo json_encode(["status" => "error", "message" => "Invalid username or password"]);
    exit;
}

// Check fee status for non-admin users
if ($user['role'] !== 'admin' && !$user['fee_paid']) {
    echo json_encode([
        "status" => "error", 
        "message" => "Your account is pending fee payment. Contact 03328335332"
    ]);
    exit;
}

// ✅ CREATE PHP SESSION - This is the key fix!
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'];
$_SESSION['fee_paid'] = $user['fee_paid'];
$_SESSION['user_id'] = $user['id'];

// ✅ Success with session created
echo json_encode([
    "status" => "success",
    "message" => "Login successful",
    "data" => [
        "username" => $user['username'],
        "role" => $user['role'],
        "feePaid" => (bool)$user['fee_paid']
    ]
]);
?>