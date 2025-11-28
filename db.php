<?php
// Remove session_start from here - it should only be called once per file
// Don't set session.save_path on InfinityFree - it can cause issues

// InfinityFree Database Configuration
$db_host = "sql100.infinityfree.com";
$db_username = "if0_39610050";
$db_password = "otgdcSrpmfYvX6S";
$db_name = "if0_39610050_doctors";

// Disable error display for production (InfinityFree doesn't like display_errors)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0); // Turn off error reporting for production

// Create connection with proper error handling for InfinityFree
try {
    $conn = new mysqli($db_host, $db_username, $db_password, $db_name);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed");
    }
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Don't log detailed errors on InfinityFree - it can cause issues
    
    // Return JSON error for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            "status" => "error", 
            "message" => "Database connection failed"
        ]);
        exit;
    }
    
    // For direct access, show simple error
    die("Database connection failed. Please try again later.");
}

// Simple function to test database connection
function testDatabaseConnection() {
    global $conn;
    try {
        $result = $conn->query("SELECT 1");
        return $result !== false;
    } catch (Exception $e) {
        return false;
    }
}
?>