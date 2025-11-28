<?php
// session.php - Updated to work with your existing login system
session_start();

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['username']) && !empty($_SESSION['username']);
}

// Function to get current username
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Function to check if user can delete (owner or admin)
function canDelete($post_owner) {
    if (!isLoggedIn()) return false;
    return getCurrentUsername() === $post_owner || isAdmin();
}

// Function to check if user has paid fees (for additional restrictions if needed)
function hasFeePaid() {
    return isset($_SESSION['fee_paid']) && $_SESSION['fee_paid'] == 1;
}

// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Please login to continue', 'redirect' => 'login.html']);
        exit;
    }
}

// Optional: Require fee payment for non-admin users
function requireFeePaid() {
    if (!isLoggedIn()) {
        requireLogin();
        return;
    }
    
    if (!isAdmin() && !hasFeePaid()) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['error' => 'Fee payment required. Contact 03328335332', 'redirect' => 'dashboard.html']);
        exit;
    }
}
?>