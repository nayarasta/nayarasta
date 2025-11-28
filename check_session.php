<?php
session_start();
header("Content-Type: application/json");

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Not logged in"
    ]);
    exit;
}

// Return user session data
echo json_encode([
    "status" => "success",
    "message" => "User session valid",
    "data" => [
        "username" => $_SESSION['username'],
        "role" => $_SESSION['role'],
        "user_id" => $_SESSION['user_id'] ?? null,
        "fee_paid" => $_SESSION['fee_paid'] ?? false
    ]
]);
?>