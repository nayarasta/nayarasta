<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header("Content-Type: application/json");

try {
    require_once "db.php";
    
    // Check if database connection exists
    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection not available");
    }

    // Check if user is admin
    if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
        echo json_encode([
            "status" => "error",
            "message" => "Admin access required",
            "debug" => [
                "session_username" => isset($_SESSION['username']) ? "exists" : "missing",
                "session_role" => $_SESSION['role'] ?? "not set"
            ]
        ]);
        exit;
    }

    // Get all users (excluding current admin)
    $admin_id = $_SESSION['user_id'] ?? 0;
    
    // First check if users table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'users'");
    if ($table_check->num_rows === 0) {
        throw new Exception("Users table does not exist");
    }
    
    // Check table structure
    $columns_check = $conn->query("DESCRIBE users");
    $columns = [];
    while ($col = $columns_check->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
    
    $required_columns = ['id', 'username', 'role', 'fee_paid'];
    $missing_columns = array_diff($required_columns, $columns);
    if (!empty($missing_columns)) {
        throw new Exception("Missing columns in users table: " . implode(', ', $missing_columns));
    }
    
    // Prepare and execute query
    $stmt = $conn->prepare("SELECT id, username, role, fee_paid FROM users WHERE id != ? ORDER BY username");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $admin_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => (int)$row['id'],
            'username' => $row['username'],
            'role' => $row['role'],
            'fee_paid' => (bool)$row['fee_paid']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        "status" => "success",
        "data" => $users,
        "count" => count($users),
        "admin_id" => $admin_id
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Error: " . $e->getMessage(),
        "file" => __FILE__,
        "line" => $e->getLine(),
        "session" => $_SESSION ?? "No session data"
    ]);
}
?>