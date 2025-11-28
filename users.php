<?php
header("Content-Type: application/json");
require_once "db.php";

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

switch($method) {
    case 'GET':
        // Fetch all users
        $result = $conn->query("SELECT username, role, fee_paid FROM users ORDER BY username");
        $users = [];
        while($row = $result->fetch_assoc()) {
            $users[] = [
                'username' => $row['username'],
                'role' => $row['role'],
                'feePaid' => (bool)$row['fee_paid']
            ];
        }
        echo json_encode(["status" => "success", "data" => $users]);
        break;

    case 'POST':
        // Handle different POST actions for InfinityFree compatibility
        $action = $data['action'] ?? 'add';
        
        if ($action === 'add') {
            // Add new user
            $username = trim($data['username']);
            $password = trim($data['password']);
            $role = $data['role'] ?? 'user';
            $feePaid = $data['feePaid'] ?? false;
            $whatsapp_number = trim($data['whatsapp_number'] ?? '');

            if(!$username || !$password) {
                echo json_encode(["status" => "error", "message" => "Username and password required"]);
                exit;
            }

            // Validate WhatsApp number if provided
            if ($whatsapp_number && !preg_match('/^03[0-9]{9}$/', $whatsapp_number)) {
                echo json_encode(["status" => "error", "message" => "Invalid WhatsApp number format. Please use Pakistani format (03XXXXXXXXX)"]);
                exit;
            }

            // Check if user exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if($stmt->get_result()->num_rows > 0) {
                echo json_encode(["status" => "error", "message" => "Username already exists"]);
                exit;
            }
            $stmt->close();

            // Check if WhatsApp number already exists (if provided)
            if ($whatsapp_number) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE whatsapp_number = ?");
                if (!$stmt) {
                    echo json_encode(["status" => "error", "message" => "Database error occurred"]);
                    exit;
                }
                
                $stmt->bind_param("s", $whatsapp_number);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    echo json_encode(["status" => "error", "message" => "This WhatsApp number is already registered"]);
                    $stmt->close();
                    exit;
                }
                $stmt->close();
            }

            // Insert user
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, whatsapp_number, role, fee_paid, total_notes_purchased, total_amount_spent) VALUES (?, ?, ?, ?, ?, 0, 0.00)");
            $stmt->bind_param("ssssi", $username, $hashedPassword, $whatsapp_number, $role, $feePaid);
            
            if($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "User added successfully"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to add user"]);
            }
            $stmt->close();
            
        } elseif ($action === 'view') {
            // NEW: View user details
            if (!isset($data['username'])) {
                echo json_encode(["status" => "error", "message" => "Username is required"]);
                exit;
            }
            
            $username = $data['username'];
            
            // Get user details with all available fields
            $stmt = $conn->prepare("
                SELECT id, username, password, role, fee_paid, created_at, 
                       whatsapp_number, total_notes_purchased, total_amount_spent, 
                       last_purchase_date 
                FROM users 
                WHERE username = ?
            ");
            
            if (!$stmt) {
                echo json_encode(["status" => "error", "message" => "Database error occurred"]);
                exit;
            }
            
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(["status" => "error", "message" => "User not found"]);
                $stmt->close();
                exit;
            }
            
            $user = $result->fetch_assoc();
            $stmt->close();
            
            // Convert fee_paid to boolean for consistency
            $user['fee_paid'] = (bool)$user['fee_paid'];
            
            // Handle null values gracefully
            $user['whatsapp_number'] = $user['whatsapp_number'] ?? '';
            $user['total_notes_purchased'] = $user['total_notes_purchased'] ?? 0;
            $user['total_amount_spent'] = $user['total_amount_spent'] ?? '0.00';
            $user['last_purchase_date'] = $user['last_purchase_date'] ?? null;
            
            echo json_encode([
                "status" => "success",
                "data" => $user
            ]);
            
        } elseif ($action === 'edit') {
            // Edit user via POST (instead of PUT)
            $username = trim($data['username']);
            $password = trim($data['password']);
            $role = $data['role'] ?? 'user';
            $feePaid = $data['feePaid'] ?? false;

            if(!$username) {
                echo json_encode(["status" => "error", "message" => "Username required"]);
                exit;
            }

            if($password) {
                // Update with password
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password=?, role=?, fee_paid=? WHERE username=?");
                $stmt->bind_param("ssis", $hashedPassword, $role, $feePaid, $username);
            } else {
                // Update without password
                $stmt = $conn->prepare("UPDATE users SET role=?, fee_paid=? WHERE username=?");
                $stmt->bind_param("sis", $role, $feePaid, $username);
            }
            
            if($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "User updated successfully"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to update user"]);
            }
            $stmt->close();
            
        } elseif ($action === 'delete') {
            // Delete user via POST (instead of DELETE)
            $username = trim($data['username']);
            if(!$username || $username === 'admin') {
                echo json_encode(["status" => "error", "message" => "Cannot delete this user"]);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM users WHERE username=?");
            $stmt->bind_param("s", $username);
            
            if($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "User deleted successfully"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to delete user"]);
            }
            $stmt->close();
            
        } elseif ($action === 'toggle_fee') {
            // Toggle fee status via POST (silent update)
            $username = trim($data['username']);
            $feePaid = $data['feePaid'] ?? false;
            
            if(!$username) {
                echo json_encode(["status" => "error", "message" => "Username required"]);
                exit;
            }

            $stmt = $conn->prepare("UPDATE users SET fee_paid=? WHERE username=?");
            $stmt->bind_param("is", $feePaid, $username);
            
            if($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Fee status updated successfully"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to update fee status"]);
            }
            $stmt->close();
        }
        break;

    case 'PUT':
        // Keep PUT for backwards compatibility (if needed)
        $username = trim($data['username']);
        $password = trim($data['password']);
        $role = $data['role'] ?? 'user';
        $feePaid = $data['feePaid'] ?? false;

        if(!$username) {
            echo json_encode(["status" => "error", "message" => "Username required"]);
            exit;
        }

        if($password) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password=?, role=?, fee_paid=? WHERE username=?");
            $stmt->bind_param("ssis", $hashedPassword, $role, $feePaid, $username);
        } else {
            $stmt = $conn->prepare("UPDATE users SET role=?, fee_paid=? WHERE username=?");
            $stmt->bind_param("sis", $role, $feePaid, $username);
        }
        
        if($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User updated successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to update user"]);
        }
        $stmt->close();
        break;

    case 'DELETE':
        // Keep DELETE for backwards compatibility (if needed)
        $username = trim($data['username']);
        if(!$username || $username === 'admin') {
            echo json_encode(["status" => "error", "message" => "Cannot delete this user"]);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE username=?");
        $stmt->bind_param("s", $username);
        
        if($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User deleted successfully"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to delete user"]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}

$conn->close();
?>