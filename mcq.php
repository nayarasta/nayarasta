<?php
header("Content-Type: application/json");
require_once "db.php";

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

// Log input for debugging
error_log("Incoming $method: " . json_encode($data));

// Check if chapter column exists
function checkChapterColumnExists($conn) {
    $result = $conn->query("SHOW COLUMNS FROM mcqs LIKE 'chapter'");
    return $result->num_rows > 0;
}

$hasChapterColumn = checkChapterColumnExists($conn);
error_log("Chapter column exists: " . ($hasChapterColumn ? 'YES' : 'NO'));

// Handle different HTTP methods - InfinityFree compatible
switch($method) {
    case 'GET':
        $type = $_GET['type'] ?? 'regular';
        $category = $_GET['category'] ?? '';
        $chapter = $_GET['chapter'] ?? '';
        $action = $_GET['action'] ?? '';
        // Handle Mock Test requests
    if ($type === 'mock') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        
        if (empty($category)) {
            echo json_encode(['status' => 'error', 'message' => 'Category is required for mock test']);
            exit;
        }
        
        // SQL query to get random questions from specific category with limit
        if ($hasChapterColumn) {
            $query = "SELECT id, question, option_a, option_b, option_c, option_d, correct_answer, category, chapter, created_by FROM mcqs WHERE type = 'regular' AND category = ? ORDER BY RAND() LIMIT ?";
        } else {
            $query = "SELECT id, question, option_a, option_b, option_c, option_d, correct_answer, category, created_by FROM mcqs WHERE type = 'regular' AND category = ? ORDER BY RAND() LIMIT ?";
        }
        
        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            echo json_encode(['status' => 'error', 'message' => 'Database prepare error: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param('si', $category, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $mcqs = [];
        while($row = $result->fetch_assoc()) {
            $mcq = [
                'id' => (int)$row['id'],
                'question' => $row['question'],
                'options' => [
                    $row['option_a'],
                    $row['option_b'],
                    $row['option_c'],
                    $row['option_d']
                ],
                'answer' => (int)$row['correct_answer'],
                'category' => $row['category'],
                'createdBy' => $row['created_by']
            ];
            
            // Add chapter if column exists
            if ($hasChapterColumn) {
                $mcq['chapter'] = $row['chapter'] ?? '';
            }
            
            $mcqs[] = $mcq;
        }
        
        echo json_encode([
            'status' => 'success',
            'data' => $mcqs,
            'total' => count($mcqs)
        ]);
        $stmt->close();
        exit;
    }

        // Handle different GET actions
        if ($action === 'get_chapters') {
            // Get chapters for a specific category
            if (empty($category)) {
                echo json_encode(["status" => "error", "message" => "Category is required for getting chapters"]);
                exit;
            }

            if (!$hasChapterColumn) {
                echo json_encode(["status" => "success", "data" => []]);
                exit;
            }

            $query = "SELECT DISTINCT chapter FROM mcqs WHERE category = ? AND chapter IS NOT NULL AND chapter != '' ORDER BY chapter";
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                echo json_encode(["status" => "error", "message" => "Database prepare error: " . $conn->error]);
                exit;
            }
            
            $stmt->bind_param("s", $category);
            $stmt->execute();
            $result = $stmt->get_result();

            $chapters = [];
            while($row = $result->fetch_assoc()) {
                $chapters[] = $row['chapter'];
            }

            echo json_encode(["status" => "success", "data" => $chapters]);
            $stmt->close();
            break;
        }

        // Regular MCQ fetching with backward compatibility
        if ($hasChapterColumn) {
            $query = "SELECT id, question, option_a, option_b, option_c, option_d, correct_answer, category, chapter, created_by FROM mcqs WHERE type = ?";
        } else {
            $query = "SELECT id, question, option_a, option_b, option_c, option_d, correct_answer, category, created_by FROM mcqs WHERE type = ?";
        }
        
        $params = [$type];
        $types = "s";

        if (!empty($category)) {
            $query .= " AND category = ?";
            $params[] = $category;
            $types .= "s";
        }

        if (!empty($chapter) && $hasChapterColumn) {
            $query .= " AND chapter = ?";
            $params[] = $chapter;
            $types .= "s";
        }

        $query .= " ORDER BY id";

        $stmt = $conn->prepare($query);
        if ($stmt === false) {
            echo json_encode(["status" => "error", "message" => "Database prepare error: " . $conn->error]);
            exit;
        }
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $mcqs = [];
        while($row = $result->fetch_assoc()) {
            $mcq = [
                'id' => (int)$row['id'],
                'question' => $row['question'],
                'options' => [
                    $row['option_a'],
                    $row['option_b'],
                    $row['option_c'],
                    $row['option_d']
                ],
                'answer' => (int)$row['correct_answer'],
                'category' => $row['category'],
                'createdBy' => $row['created_by']
            ];
            
            // Add chapter if column exists
            if ($hasChapterColumn) {
                $mcq['chapter'] = $row['chapter'] ?? '';
            }
            
            $mcqs[] = $mcq;
        }

        echo json_encode(["status" => "success", "data" => $mcqs]);
        $stmt->close();
        break;

    case 'POST':
        // Handle different POST actions
        $action = $data['action'] ?? 'add';
        
        if ($action === 'add' || !isset($data['action'])) {
            // Add new MCQ with backward compatibility
            $question = isset($data['question']) ? trim($data['question']) : '';
            $options = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
            $answer = isset($data['answer']) ? (int)$data['answer'] : 0;
            $category = isset($data['category']) ? trim($data['category']) : '';
            $chapter = isset($data['chapter']) ? trim($data['chapter']) : '';
            $type = isset($data['type']) ? trim($data['type']) : 'regular';
            $username = isset($data['username']) ? trim($data['username']) : '';

            // Enhanced logging for debugging
            error_log("POST Data Processing:");
            error_log("- Question: '$question'");
            error_log("- Category received: '$category'");
            error_log("- Chapter received: '$chapter'");
            error_log("- Options count: " . count($options));
            error_log("- Answer: $answer");
            error_log("- Type: '$type'");
            error_log("- Username: '$username'");

            // Validation
            if (empty($question)) {
                echo json_encode(["status" => "error", "message" => "Question is required."]);
                exit;
            }

            if (count($options) !== 4) {
                echo json_encode(["status" => "error", "message" => "Exactly 4 options are required."]);
                exit;
            }

            // Check if all options are provided
            foreach ($options as $i => $option) {
                if (empty(trim($option))) {
                    echo json_encode(["status" => "error", "message" => "Option " . chr(65 + $i) . " is required."]);
                    exit;
                }
            }

            if (empty($username)) {
                echo json_encode(["status" => "error", "message" => "Username is required."]);
                exit;
            }

            if ($answer < 0 || $answer > 3) {
                echo json_encode(["status" => "error", "message" => "Answer must be between 0-3."]);
                exit;
            }

            // Category validation
            $validCategories = ['Physics', 'Chemistry', 'Biology', 'English'];
            if (empty($category) || !in_array($category, $validCategories)) {
                echo json_encode(["status" => "error", "message" => "Invalid category. Must be one of: " . implode(', ', $validCategories)]);
                exit;
            }

            // Prepare the SQL statement with backward compatibility
            if ($hasChapterColumn) {
                $sql = "INSERT INTO mcqs (question, option_a, option_b, option_c, option_d, correct_answer, category, chapter, type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            } else {
                $sql = "INSERT INTO mcqs (question, option_a, option_b, option_c, option_d, correct_answer, category, type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            }
            
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                error_log("SQL Prepare Error: " . $conn->error);
                echo json_encode(["status" => "error", "message" => "Database prepare error: " . $conn->error]);
                exit;
            }

            // Bind parameters based on column availability
            if ($hasChapterColumn) {
                $stmt->bind_param(
                    "sssssissss",
                    $question,
                    $options[0],
                    $options[1],
                    $options[2],
                    $options[3],
                    $answer,
                    $category,
                    $chapter,
                    $type,
                    $username
                );
            } else {
                $stmt->bind_param(
                    "sssssisss",
                    $question,
                    $options[0],
                    $options[1],
                    $options[2],
                    $options[3],
                    $answer,
                    $category,
                    $type,
                    $username
                );
            }

            // Execute and check for errors
            if ($stmt->execute()) {
                $insertId = $conn->insert_id;
                error_log("MCQ inserted successfully with ID: $insertId");
                echo json_encode(["status" => "success", "message" => "MCQ added successfully", "id" => $insertId]);
            } else {
                error_log("SQL Execute Error: " . $stmt->error);
                echo json_encode(["status" => "error", "message" => "Failed to add MCQ: " . $stmt->error]);
            }
            $stmt->close();
            
        } elseif ($action === 'update') {
            // Update MCQ with backward compatibility
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            $question = isset($data['question']) ? trim($data['question']) : '';
            $options = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
            $answer = isset($data['answer']) ? (int)$data['answer'] : 0;
            $category = isset($data['category']) ? trim($data['category']) : '';
            $chapter = isset($data['chapter']) ? trim($data['chapter']) : '';
            $username = isset($data['username']) ? trim($data['username']) : '';

            // Validation
            if ($id <= 0) {
                echo json_encode(["status" => "error", "message" => "Valid MCQ ID is required."]);
                exit;
            }

            if (empty($question)) {
                echo json_encode(["status" => "error", "message" => "Question is required."]);
                exit;
            }

            if (count($options) !== 4) {
                echo json_encode(["status" => "error", "message" => "Exactly 4 options are required."]);
                exit;
            }

            // Check if all options are provided
            foreach ($options as $i => $option) {
                if (empty(trim($option))) {
                    echo json_encode(["status" => "error", "message" => "Option " . chr(65 + $i) . " is required."]);
                    exit;
                }
            }

            if (empty($username)) {
                echo json_encode(["status" => "error", "message" => "Username is required."]);
                exit;
            }

            if ($answer < 0 || $answer > 3) {
                echo json_encode(["status" => "error", "message" => "Answer must be between 0-3."]);
                exit;
            }

            // Category validation
            $validCategories = ['Physics', 'Chemistry', 'Biology', 'English'];
            if (empty($category) || !in_array($category, $validCategories)) {
                echo json_encode(["status" => "error", "message" => "Invalid category. Must be one of: " . implode(', ', $validCategories)]);
                exit;
            }

            // Prepare the SQL statement with backward compatibility
            if ($hasChapterColumn) {
                $sql = "UPDATE mcqs SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, category = ?, chapter = ? WHERE id = ?";
            } else {
                $sql = "UPDATE mcqs SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, category = ? WHERE id = ?";
            }
            
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                error_log("SQL Prepare Error: " . $conn->error);
                echo json_encode(["status" => "error", "message" => "Database prepare error: " . $conn->error]);
                exit;
            }

            // Bind parameters based on column availability
            if ($hasChapterColumn) {
                $stmt->bind_param(
                    "ssssisisi",
                    $question,
                    $options[0],
                    $options[1],
                    $options[2],
                    $options[3],
                    $answer,
                    $category,
                    $chapter,
                    $id
                );
            } else {
                $stmt->bind_param(
                    "ssssisii",
                    $question,
                    $options[0],
                    $options[1],
                    $options[2],
                    $options[3],
                    $answer,
                    $category,
                    $id
                );
            }

            // Execute and check for errors
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    error_log("MCQ updated successfully for ID: $id");
                    echo json_encode(["status" => "success", "message" => "MCQ updated successfully"]);
                } else {
                    error_log("No rows affected for MCQ ID: $id");
                    echo json_encode(["status" => "error", "message" => "MCQ not found or no changes made"]);
                }
            } else {
                error_log("SQL Execute Error: " . $stmt->error);
                echo json_encode(["status" => "error", "message" => "Failed to update MCQ: " . $stmt->error]);
            }
            $stmt->close();
            
        } elseif ($action === 'delete') {
            // Delete MCQ (unchanged)
            $id = isset($data['id']) ? (int)$data['id'] : 0;
            
            if ($id <= 0) {
                echo json_encode(["status" => "error", "message" => "Valid MCQ ID is required."]);
                exit;
            }

            $stmt = $conn->prepare("DELETE FROM mcqs WHERE id = ?");
            if ($stmt === false) {
                echo json_encode(["status" => "error", "message" => "Database prepare error: " . $conn->error]);
                exit;
            }
            
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(["status" => "success", "message" => "MCQ deleted successfully"]);
                } else {
                    echo json_encode(["status" => "error", "message" => "MCQ not found"]);
                }
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to delete MCQ: " . $stmt->error]);
            }
            $stmt->close();
        }
        break;

    case 'PUT':
        // Keep PUT for backward compatibility with chapter support
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $question = isset($data['question']) ? trim($data['question']) : '';
        $options = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
        $answer = isset($data['answer']) ? (int)$data['answer'] : 0;
        $category = isset($data['category']) ? trim($data['category']) : '';
        $chapter = isset($data['chapter']) ? trim($data['chapter']) : '';
        $username = isset($data['username']) ? trim($data['username']) : '';

        // Validation (same as POST update)
        if ($id <= 0) {
            echo json_encode(["status" => "error", "message" => "Valid MCQ ID is required."]);
            exit;
        }

        if (empty($question)) {
            echo json_encode(["status" => "error", "message" => "Question is required."]);
            exit;
        }

        if (count($options) !== 4) {
            echo json_encode(["status" => "error", "message" => "Exactly 4 options are required."]);
            exit;
        }

        // Check if all options are provided
        foreach ($options as $i => $option) {
            if (empty(trim($option))) {
                echo json_encode(["status" => "error", "message" => "Option " . chr(65 + $i) . " is required."]);
                exit;
            }
        }

        if (empty($username)) {
            echo json_encode(["status" => "error", "message" => "Username is required."]);
            exit;
        }

        if ($answer < 0 || $answer > 3) {
            echo json_encode(["status" => "error", "message" => "Answer must be between 0-3."]);
            exit;
        }

        // Category validation
        $validCategories = ['Physics', 'Chemistry', 'Biology', 'English'];
        if (empty($category) || !in_array($category, $validCategories)) {
            echo json_encode(["status" => "error", "message" => "Invalid category. Must be one of: " . implode(', ', $validCategories)]);
            exit;
        }

        // Prepare the SQL statement with backward compatibility
        if ($hasChapterColumn) {
            $sql = "UPDATE mcqs SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, category = ?, chapter = ? WHERE id = ?";
        } else {
            $sql = "UPDATE mcqs SET question = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, category = ? WHERE id = ?";
        }
        
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            error_log("SQL Prepare Error: " . $conn->error);
            echo json_encode(["status" => "error", "message" => "Database prepare error: " . $conn->error]);
            exit;
        }

        // Bind parameters based on column availability
        if ($hasChapterColumn) {
            $stmt->bind_param(
                "ssssisisi",
                $question,
                $options[0],
                $options[1],
                $options[2],
                $options[3],
                $answer,
                $category,
                $chapter,
                $id
            );
        } else {
            $stmt->bind_param(
                "ssssisii",
                $question,
                $options[0],
                $options[1],
                $options[2],
                $options[3],
                $answer,
                $category,
                $id
            );
        }

        // Execute and check for errors
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                error_log("MCQ updated successfully for ID: $id");
                echo json_encode(["status" => "success", "message" => "MCQ updated successfully"]);
            } else {
                error_log("No rows affected for MCQ ID: $id");
                echo json_encode(["status" => "error", "message" => "MCQ not found or no changes made"]);
            }
        } else {
            error_log("SQL Execute Error: " . $stmt->error);
            echo json_encode(["status" => "error", "message" => "Failed to update MCQ: " . $stmt->error]);
        }
        $stmt->close();
        break;

    case 'DELETE':
        // Keep DELETE for backward compatibility (unchanged)
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        
        if ($id <= 0) {
            echo json_encode(["status" => "error", "message" => "Valid MCQ ID is required."]);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM mcqs WHERE id = ?");
        if ($stmt === false) {
            echo json_encode(["status" => "error", "message" => "Database prepare error: " . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(["status" => "success", "message" => "MCQ deleted successfully"]);
            } else {
                echo json_encode(["status" => "error", "message" => "MCQ not found"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to delete MCQ: " . $stmt->error]);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}

$conn->close();
?>