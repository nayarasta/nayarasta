<?php
// debug_codes.php - Debug the code management issue
session_start();

header("Content-Type: application/json");
require_once "db.php";

// Check session
$session_status = [
    "session_id" => session_id(),
    "username" => $_SESSION['username'] ?? 'NOT_SET',
    "role" => $_SESSION['role'] ?? 'NOT_SET',
    "is_admin" => isset($_SESSION['role']) && $_SESSION['role'] === 'admin'
];

// Check database connection
$db_status = [
    "connection" => $conn ? "SUCCESS" : "FAILED",
    "error" => $conn ? null : mysqli_connect_error()
];

// Check tables exist
$tables_status = [];
$tables = ['registration_codes', 'approval_codes'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $tables_status[$table] = $result && $result->num_rows > 0 ? "EXISTS" : "MISSING";
}

// Test registration codes query
$reg_codes_status = [];
try {
    $stmt = $conn->prepare("SELECT code FROM registration_codes WHERE is_used = FALSE ORDER BY created_at DESC LIMIT 5");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $codes = [];
        while($row = $result->fetch_assoc()) {
            $codes[] = $row['code'];
        }
        $reg_codes_status = [
            "status" => "SUCCESS",
            "count" => count($codes),
            "codes" => $codes
        ];
        $stmt->close();
    } else {
        $reg_codes_status = [
            "status" => "FAILED",
            "error" => "Failed to prepare statement: " . $conn->error
        ];
    }
} catch (Exception $e) {
    $reg_codes_status = [
        "status" => "EXCEPTION",
        "error" => $e->getMessage()
    ];
}

// Test approval codes query
$app_codes_status = [];
try {
    $stmt = $conn->prepare("SELECT code FROM approval_codes ORDER BY created_at DESC LIMIT 5");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $codes = [];
        while($row = $result->fetch_assoc()) {
            $codes[] = $row['code'];
        }
        $app_codes_status = [
            "status" => "SUCCESS",
            "count" => count($codes),
            "codes" => $codes
        ];
        $stmt->close();
    } else {
        $app_codes_status = [
            "status" => "FAILED",
            "error" => "Failed to prepare statement: " . $conn->error
        ];
    }
} catch (Exception $e) {
    $app_codes_status = [
        "status" => "EXCEPTION",
        "error" => $e->getMessage()
    ];
}

// Check table structure
$table_structure = [];
foreach ($tables as $table) {
    $result = $conn->query("DESCRIBE $table");
    if ($result) {
        $columns = [];
        while($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }
        $table_structure[$table] = $columns;
    } else {
        $table_structure[$table] = "ERROR: " . $conn->error;
    }
}

echo json_encode([
    "debug_timestamp" => date('Y-m-d H:i:s'),
    "session_status" => $session_status,
    "database_status" => $db_status,
    "tables_status" => $tables_status,
    "table_structure" => $table_structure,
    "registration_codes_test" => $reg_codes_status,
    "approval_codes_test" => $app_codes_status,
    "request_info" => [
        "method" => $_SERVER['REQUEST_METHOD'],
        "query_string" => $_SERVER['QUERY_STRING'] ?? '',
        "content_type" => $_SERVER['CONTENT_TYPE'] ?? '',
    ]
], JSON_PRETTY_PRINT);

$conn->close();
?>