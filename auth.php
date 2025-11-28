<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Include database connection
require_once "db.php";

// Check if database connection exists
if (!isset($conn) || $conn->connect_error) {
    echo json_encode([
        "status" => "error", 
        "message" => "Database connection failed. Please try again later."
    ]);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
    exit;
}

// Get and decode JSON input
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON data received"]);
    exit;
}

$action = $data['action'] ?? 'login'; // Default to login if no action specified

try {
    switch($action) {
        case 'login':
            handleLogin($conn, $data);
            break;
            
        case 'register':
            handleRegister($conn, $data);
            break;
            
        default:
            echo json_encode(["status" => "error", "message" => "Invalid action: " . $action]);
    }
} catch (Exception $e) {
    // Log error but don't expose details in production
    error_log("Auth error: " . $e->getMessage());
    echo json_encode([
        "status" => "error", 
        "message" => "Server error occurred. Please try again."
    ]);
}

function handleLogin($conn, $data) {
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    
    if (!$username || !$password) {
        echo json_encode(["status" => "error", "message" => "Username and password are required"]);
        return;
    }
    
    // Check if user exists - include whatsapp_number in select
    $stmt = $conn->prepare("SELECT username, password, role, fee_paid, id, whatsapp_number FROM users WHERE username = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error occurred"]);
        return;
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Invalid username or password"]);
        $stmt->close();
        return;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        echo json_encode(["status" => "error", "message" => "Invalid username or password"]);
        return;
    }
    
    // Check fee status for non-admin users
    if ($user['role'] !== 'admin' && !$user['fee_paid']) {
        echo json_encode([
            "status" => "error", 
            "message" => "Your account is pending fee payment. Contact 03328335332"
        ]);
        return;
    }
    
    // Create PHP session
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['fee_paid'] = $user['fee_paid'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['whatsapp_number'] = $user['whatsapp_number'];
    
    // Login successful
    echo json_encode([
        "status" => "success",
        "message" => "Login successful",
        "data" => [
            "username" => $user['username'],
            "role" => $user['role'],
            "feePaid" => (bool)$user['fee_paid'],
            "whatsapp_number" => $user['whatsapp_number']
        ]
    ]);
}

function handleRegister($conn, $data) {
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    $whatsapp_number = trim($data['whatsapp_number'] ?? '');
    $code = trim($data['registrationCode'] ?? '');
    
    // Check if any approval codes exist on server
    $approvalCodesExist = false;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM approval_codes");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $row = $result->fetch_assoc();
            $approvalCodesExist = ($row['count'] > 0);
        }
        $stmt->close();
    }
    
    // If approval codes exist, don't require any code validation
    if ($approvalCodesExist) {
        // Require username, password, and WhatsApp number
        if (!$username || !$password || !$whatsapp_number) {
            echo json_encode(["status" => "error", "message" => "Username, password, and WhatsApp number are required"]);
            return;
        }
    } else {
        // No approval codes exist, require registration code
        if (!$username || !$password || !$whatsapp_number || !$code) {
            echo json_encode(["status" => "error", "message" => "All fields including registration code are required"]);
            return;
        }
    }
    
    // Validate WhatsApp number format (Pakistani format)
    if (!preg_match('/^03[0-9]{9}$/', $whatsapp_number)) {
        echo json_encode(["status" => "error", "message" => "Invalid WhatsApp number format. Please use Pakistani format (03XXXXXXXXX)"]);
        return;
    }
    
    // Validate username length
    if (strlen($username) < 3) {
        echo json_encode(["status" => "error", "message" => "Username must be at least 3 characters"]);
        return;
    }
    
    // Validate password length
    if (strlen($password) < 6) {
        echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters"]);
        return;
    }
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error occurred"]);
        return;
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Username already exists"]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // Check if WhatsApp number already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE whatsapp_number = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error occurred"]);
        return;
    }
    
    $stmt->bind_param("s", $whatsapp_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "This WhatsApp number is already registered"]);
        $stmt->close();
        return;
    }
    $stmt->close();
    
    // If no approval codes exist, validate the registration code
    if (!$approvalCodesExist && $code) {
        $stmt = $conn->prepare("SELECT id FROM registration_codes WHERE code = ? AND is_used = FALSE");
        if ($stmt) {
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(["status" => "error", "message" => "Invalid or already used registration code"]);
                $stmt->close();
                return;
            }
            $stmt->close();
        }
    }
    
    // Create user account with fee_paid = TRUE and WhatsApp number
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, whatsapp_number, role, fee_paid, total_notes_purchased, total_amount_spent) VALUES (?, ?, ?, 'user', 1, 0, 0.00)");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error occurred"]);
        return;
    }
    
    $stmt->bind_param("sss", $username, $hashedPassword, $whatsapp_number);
    
    if ($stmt->execute()) {
        // If registration code was used and validated, mark it as used
        if (!$approvalCodesExist && $code) {
            $stmt2 = $conn->prepare("UPDATE registration_codes SET is_used = TRUE, used_at = NOW(), used_by = ? WHERE code = ?");
            if ($stmt2) {
                $stmt2->bind_param("ss", $username, $code);
                $stmt2->execute();
                $stmt2->close();
            }
        }
        
        echo json_encode([
            "status" => "success",
            "message" => "Registration successful! Welcome to NayaRasta!"
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Registration failed. Please try again."]);
    }
    $stmt->close();
}

$conn->close();
?>