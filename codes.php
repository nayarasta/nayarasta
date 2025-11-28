<?php
// Simplified codes.php - Minimal version for debugging
session_start();

// Basic headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Simple error logging function
function logError($message) {
    error_log("CODES.PHP ERROR: " . $message);
}

// Include database
if (!file_exists("db.php")) {
    echo json_encode(["status" => "error", "message" => "Database config file not found"]);
    exit;
}

require_once "db.php";

// Check database connection
if (!$conn) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . mysqli_connect_error()]);
    exit;
}

// Simple session check - be more lenient for debugging
if (!isset($_SESSION['username'])) {
    echo json_encode([
        "status" => "error", 
        "message" => "No active session found",
        "debug" => [
            "session_id" => session_id(),
            "session_data" => $_SESSION
        ]
    ]);
    exit;
}

// Check admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        "status" => "error", 
        "message" => "Admin access required",
        "debug" => [
            "current_role" => $_SESSION['role'] ?? 'not_set',
            "username" => $_SESSION['username'] ?? 'not_set'
        ]
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
logError("Method: $method, User: " . $_SESSION['username']);

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        logError("GET Action: $action");
        
        if ($action === 'registration') {
            // First check if table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'registration_codes'");
            if (!$tableCheck || $tableCheck->num_rows === 0) {
                // Create table if it doesn't exist
                $createTable = "CREATE TABLE IF NOT EXISTS registration_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) NOT NULL UNIQUE,
                    is_used BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                $conn->query($createTable);
                
                // Insert some default codes
                $conn->query("INSERT IGNORE INTO registration_codes (code) VALUES ('REG001'), ('REG002'), ('REG003')");
            }
            
            // Get registration codes
            $result = $conn->query("SELECT code FROM registration_codes WHERE is_used = FALSE ORDER BY created_at DESC");
            $codes = [];
            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $codes[] = $row['code'];
                }
            }
            
            echo json_encode([
                "status" => "success", 
                "data" => $codes,
                "debug" => ["count" => count($codes), "table_exists" => true]
            ]);
            
        } elseif ($action === 'approval') {
            // First check if table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'approval_codes'");
            if (!$tableCheck || $tableCheck->num_rows === 0) {
                // Create table if it doesn't exist
                $createTable = "CREATE TABLE IF NOT EXISTS approval_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) NOT NULL UNIQUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                $conn->query($createTable);
                
                // Insert some default codes
                $conn->query("INSERT IGNORE INTO approval_codes (code) VALUES ('APP001'), ('APP002'), ('APP003')");
            }
            
            // Get approval codes
            $result = $conn->query("SELECT code FROM approval_codes ORDER BY created_at DESC");
            $codes = [];
            if ($result) {
                while($row = $result->fetch_assoc()) {
                    $codes[] = $row['code'];
                }
            }
            
            echo json_encode([
                "status" => "success", 
                "data" => $codes,
                "debug" => ["count" => count($codes), "table_exists" => true]
            ]);
            
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid action: $action"]);
        }
        
    } elseif ($method === 'POST') {
        $input = file_get_contents("php://input");
        $data = json_decode($input, true);
        
        if (!$data) {
            echo json_encode(["status" => "error", "message" => "Invalid JSON data"]);
            exit;
        }
        
        $action = $data['action'] ?? '';
        $code = trim($data['code'] ?? '');
        
        logError("POST Action: $action, Code: $code");
        
        if ($action === 'registration' && $code) {
            // Add registration code
            $stmt = $conn->prepare("INSERT IGNORE INTO registration_codes (code) VALUES (?)");
            $stmt->bind_param("s", $code);
            
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Registration code added"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to add code: " . $conn->error]);
            }
            $stmt->close();
            
        } elseif ($action === 'approval' && $code) {
            // Add approval code
            $stmt = $conn->prepare("INSERT IGNORE INTO approval_codes (code) VALUES (?)");
            $stmt->bind_param("s", $code);
            
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Approval code added"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to add code: " . $conn->error]);
            }
            $stmt->close();
            
        } elseif ($action === 'delete') {
            $type = $data['type'] ?? '';
            
            if ($type === 'registration') {
                $stmt = $conn->prepare("DELETE FROM registration_codes WHERE code = ?");
            } elseif ($type === 'approval') {
                $stmt = $conn->prepare("DELETE FROM approval_codes WHERE code = ?");
            } else {
                echo json_encode(["status" => "error", "message" => "Invalid type: $type"]);
                exit;
            }
            
            $stmt->bind_param("s", $code);
            
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Code deleted"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to delete: " . $conn->error]);
            }
            $stmt->close();
            
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid action or missing code"]);
        }
        
    } else {
        echo json_encode(["status" => "error", "message" => "Method not allowed: $method"]);
    }
    
} catch (Exception $e) {
    logError("Exception: " . $e->getMessage());
    echo json_encode([
        "status" => "error", 
        "message" => "Server error: " . $e->getMessage()
    ]);
}

$conn->close();
?>