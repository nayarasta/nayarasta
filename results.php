<?php
// Set proper headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Disable error display for production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once "db.php";
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $username = $_GET['username'] ?? '';
    
    switch($method) {
        case 'GET':
            if ($action === 'all') {
                // Get all results for admin panel
                getAllResults($conn);
            } elseif ($action === 'user' && $username) {
                // Get results for specific user
                getUserResults($conn, $username);
            } elseif ($action === 'detailed' && isset($_GET['result_id'])) {
                // Get detailed result
                getDetailedResult($conn, (int)$_GET['result_id']);
            } else {
                // Default: get all results
                getAllResults($conn);
            }
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            $postAction = $data['action'] ?? '';
            
            if ($postAction === 'delete' && isset($data['result_id'])) {
                deleteResult($conn, (int)$data['result_id']);
            } else {
                echo json_encode(["status" => "error", "message" => "Invalid action"]);
            }
            break;
            
        default:
            echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    }

} catch (Exception $e) {
    error_log("Result.php Error: " . $e->getMessage());
    echo json_encode([
        "status" => "error", 
        "message" => "Server error occurred. Please try again."
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}

function getAllResults($conn) {
    try {
        // Get all test results with user information
        $query = "
            SELECT 
                tr.id,
                tr.username,
                tr.score,
                tr.total_questions,
                tr.test_type,
                tr.completed_at,
                ROUND((tr.score / tr.total_questions) * 100, 1) as percentage,
                u.role
            FROM test_results tr
            LEFT JOIN users u ON tr.username = u.username
            ORDER BY tr.completed_at DESC
            LIMIT 100
        ";
        
        $result = $conn->query($query);
        
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        
        $results = [];
        while($row = $result->fetch_assoc()) {
            $results[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'score' => (int)$row['score'],
                'total' => (int)$row['total_questions'],
                'percentage' => (float)$row['percentage'],
                'test_type' => $row['test_type'] ?? 'regular',
                'completed_at' => $row['completed_at'],
                'role' => $row['role'] ?? 'user'
            ];
        }
        
        echo json_encode([
            "status" => "success", 
            "data" => $results,
            "count" => count($results)
        ]);
        
    } catch (Exception $e) {
        error_log("getAllResults Error: " . $e->getMessage());
        echo json_encode([
            "status" => "error", 
            "message" => "Failed to load results"
        ]);
    }
}

function getUserResults($conn, $username) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                id,
                score,
                total_questions,
                test_type,
                completed_at,
                ROUND((score / total_questions) * 100, 1) as percentage
            FROM test_results 
            WHERE username = ? 
            ORDER BY completed_at DESC
            LIMIT 50
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $results = [];
        while($row = $result->fetch_assoc()) {
            $results[] = [
                'id' => (int)$row['id'],
                'score' => (int)$row['score'],
                'total' => (int)$row['total_questions'],
                'percentage' => (float)$row['percentage'],
                'test_type' => $row['test_type'] ?? 'regular',
                'completed_at' => $row['completed_at']
            ];
        }
        
        $stmt->close();
        
        echo json_encode([
            "status" => "success", 
            "data" => $results,
            "username" => $username
        ]);
        
    } catch (Exception $e) {
        error_log("getUserResults Error: " . $e->getMessage());
        echo json_encode([
            "status" => "error", 
            "message" => "Failed to load user results"
        ]);
    }
}

function getDetailedResult($conn, $resultId) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                tr.*,
                u.role,
                ROUND((tr.score / tr.total_questions) * 100, 1) as percentage
            FROM test_results tr
            LEFT JOIN users u ON tr.username = u.username
            WHERE tr.id = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $resultId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Result not found"]);
            return;
        }
        
        $testResult = $result->fetch_assoc();
        $stmt->close();
        
        $detailedResult = [
            'id' => (int)$testResult['id'],
            'username' => $testResult['username'],
            'score' => (int)$testResult['score'],
            'total' => (int)$testResult['total_questions'],
            'percentage' => (float)$testResult['percentage'],
            'test_type' => $testResult['test_type'] ?? 'regular',
            'completed_at' => $testResult['completed_at'],
            'answers' => json_decode($testResult['answers'] ?? '[]', true),
            'role' => $testResult['role'] ?? 'user'
        ];
        
        echo json_encode([
            "status" => "success", 
            "data" => $detailedResult
        ]);
        
    } catch (Exception $e) {
        error_log("getDetailedResult Error: " . $e->getMessage());
        echo json_encode([
            "status" => "error", 
            "message" => "Failed to load detailed result"
        ]);
    }
}

function deleteResult($conn, $resultId) {
    try {
        $stmt = $conn->prepare("DELETE FROM test_results WHERE id = ?");
        
        if (!$stmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $resultId);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode([
                    "status" => "success", 
                    "message" => "Result deleted successfully"
                ]);
            } else {
                echo json_encode([
                    "status" => "error", 
                    "message" => "Result not found"
                ]);
            }
        } else {
            throw new Exception("Delete execution failed: " . $stmt->error);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("deleteResult Error: " . $e->getMessage());
        echo json_encode([
            "status" => "error", 
            "message" => "Failed to delete result"
        ]);
    }
}
?>