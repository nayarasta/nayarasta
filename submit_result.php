<?php
header("Content-Type: application/json");
require_once "db.php";

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Only POST method allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

// Log input for debugging
error_log("Test Result Submission: " . json_encode($data));

// Extract and validate data
$username = trim($data['username'] ?? '');
$score = (int)($data['score'] ?? 0);
$total = (int)($data['total'] ?? 0);
$answers = $data['answers'] ?? [];
$testType = $data['test_type'] ?? 'regular';
$isPartial = $data['is_partial'] ?? false;

// Enhanced logging for debugging
error_log("Parsed Data:");
error_log("- Username: '$username'");
error_log("- Score: $score");
error_log("- Total: $total");
error_log("- Test Type: '$testType'");
error_log("- Is Partial: " . ($isPartial ? 'true' : 'false'));
error_log("- Answers count: " . count($answers));

// Validation
if (empty($username)) {
    echo json_encode(["status" => "error", "message" => "Username is required"]);
    exit;
}

if ($score < 0) {
    echo json_encode(["status" => "error", "message" => "Score cannot be negative"]);
    exit;
}

if ($total <= 0) {
    echo json_encode(["status" => "error", "message" => "Total questions must be greater than 0"]);
    exit;
}

if ($score > $total) {
    echo json_encode(["status" => "error", "message" => "Score cannot be greater than total questions"]);
    exit;
}

// Verify user exists
$stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
if ($stmt === false) {
    error_log("SQL Prepare Error for user verification: " . $conn->error);
    echo json_encode(["status" => "error", "message" => "Database error during user verification"]);
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
$stmt->close();

// Convert answers array to JSON string
$answersJson = json_encode($answers);

// Handle partial vs final results
if ($isPartial) {
    handlePartialResult($conn, $username, $score, $total, $answersJson, $testType);
} else {
    insertNewResult($conn, $username, $score, $total, $answersJson, $testType);
}

$conn->close();

// Function to handle partial results (UPDATE existing or INSERT new)
function handlePartialResult($conn, $username, $score, $total, $answersJson, $testType) {
    try {
        // ========== START: DUPLICATE CHECK - ADDED TO FIX BUG ==========
        // Check for duplicate submission within last 5 seconds
        $duplicateCheck = "SELECT id FROM test_results 
                           WHERE username = ? 
                           AND score = ? 
                           AND total_questions = ? 
                           AND completed_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)";
        $dupStmt = $conn->prepare($duplicateCheck);
        if ($dupStmt === false) {
            throw new Exception("Prepare failed for duplicate check: " . $conn->error);
        }
        
        $dupStmt->bind_param("sii", $username, $score, $total);
        $dupStmt->execute();
        $dupResult = $dupStmt->get_result();

        if ($dupResult->num_rows > 0) {
            $dupStmt->close();
            error_log("Duplicate submission detected and blocked for user: $username");
            echo json_encode([
                "status" => "success", 
                "message" => "Result already saved (duplicate prevented)",
                "action" => "duplicate_blocked"
            ]);
            return;
        }
        $dupStmt->close();
        // ========== END: DUPLICATE CHECK ==========
        
        // Remove suffixes to get base test type for matching
        $baseTestType = str_replace([' (Auto-saved)', ' (Incomplete)'], '', $testType);
        $searchPattern = $baseTestType . '%';
        
        // ========== START: MODIFIED TIME WINDOW - CHANGED FROM 2 TO 4 HOURS ==========
        // Check if there's already a partial result for this user and test type within last 4 hours
        $checkQuery = "SELECT id FROM test_results 
                       WHERE username = ? 
                       AND test_type LIKE ? 
                       AND completed_at >= DATE_SUB(NOW(), INTERVAL 4 HOUR) 
                       ORDER BY completed_at DESC 
                       LIMIT 1";
        // ========== END: MODIFIED TIME WINDOW ==========
        
        $stmt = $conn->prepare($checkQuery);
        if ($stmt === false) {
            throw new Exception("Prepare failed for checking existing result: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $username, $searchPattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing partial result
            $row = $result->fetch_assoc();
            $existingId = $row['id'];
            $stmt->close();
            
            $updateQuery = "UPDATE test_results SET score = ?, total_questions = ?, answers = ?, test_type = ?, completed_at = NOW() WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            
            if ($updateStmt === false) {
                throw new Exception("Prepare failed for update: " . $conn->error);
            }
            
            $updateStmt->bind_param("iissi", $score, $total, $answersJson, $testType, $existingId);
            
            if ($updateStmt->execute()) {
                error_log("Partial result updated successfully for ID: $existingId");
                echo json_encode([
                    "status" => "success", 
                    "message" => "Partial result updated successfully",
                    "action" => "updated",
                    "result_id" => $existingId,
                    "data" => [
                        "username" => $username,
                        "score" => $score,
                        "total" => $total,
                        "percentage" => round(($score / $total) * 100, 1)
                    ]
                ]);
            } else {
                throw new Exception("Failed to update partial result: " . $updateStmt->error);
            }
            $updateStmt->close();
        } else {
            // Insert new partial result
            $stmt->close();
            insertNewResult($conn, $username, $score, $total, $answersJson, $testType);
        }
        
    } catch (Exception $e) {
        error_log("handlePartialResult Error: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Failed to save partial result: " . $e->getMessage()]);
    }
}

// Function to insert new result
function insertNewResult($conn, $username, $score, $total, $answersJson, $testType) {
    try {
        $insertQuery = "INSERT INTO test_results (username, score, total_questions, answers, test_type, completed_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insertQuery);
        
        if ($stmt === false) {
            throw new Exception("Prepare failed for insert: " . $conn->error);
        }
        
        $stmt->bind_param("siiss", $username, $score, $total, $answersJson, $testType);
        
        if ($stmt->execute()) {
            $insertId = $conn->insert_id;
            error_log("Test result inserted successfully with ID: $insertId");
            
            echo json_encode([
                "status" => "success", 
                "message" => "Test result saved successfully",
                "action" => "inserted",
                "result_id" => $insertId,
                "data" => [
                    "username" => $username,
                    "score" => $score,
                    "total" => $total,
                    "percentage" => round(($score / $total) * 100, 1)
                ]
            ]);
        } else {
            throw new Exception("Failed to insert result: " . $stmt->error);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("insertNewResult Error: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Failed to save result: " . $e->getMessage()]);
    }
}
?>