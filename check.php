<?php
session_start();
header("Content-Type: application/json");

// Check if user is logged in and session is valid
if (isset($_SESSION['username']) && isset($_SESSION['role'])) {
    echo json_encode([
        "status" => "success",
        "loggedIn" => true,
        "data" => [
            "username" => $_SESSION['username'],
            "role" => $_SESSION['role'],
            "feePaid" => $_SESSION['fee_paid'] ?? false,
            "user_id" => $_SESSION['user_id'] ?? null
        ]
    ]);
} else {
    echo json_encode([
        "status" => "success",
        "loggedIn" => false,
        "message" => "No active session"
    ]);
}
?>