<?php
// studyfeed.php - Backend processing for StudyFeed
// Include your existing database connection file instead of config.php
// require_once 'config.php';
require_once 'db.php'; // Use your existing database connection
require_once 'session.php';

// Set JSON header for AJAX responses
header('Content-Type: application/json');

// File upload configuration
define('UPLOAD_DIR', 'uploads/studyfeed/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

try {
    // Use the existing MySQLi connection from db.php
    global $conn;
    
    // Handle different actions
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch($action) {
        case 'post_question':
            requireLogin();
            // Optional: Uncomment if you want to require fee payment for posting
            // requireFeePaid();
            handlePostQuestion($conn);
            break;
            
        case 'post_answer':
            requireLogin();
            // Optional: Uncomment if you want to require fee payment for answering
            // requireFeePaid();
            handlePostAnswer($conn);
            break;
            
        case 'delete_question':
            requireLogin();
            handleDeleteQuestion($conn);
            break;
            
        case 'delete_answer':
            requireLogin();
            handleDeleteAnswer($conn);
            break;
            
        case 'get_feed':
            handleGetFeed($conn);
            break;
            
        case 'get_answers':
            handleGetAnswers($conn);
            break;
            
        case 'get_user_info':
            handleGetUserInfo();
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    
} catch(Exception $e) {
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

// Function to handle posting a new question
function handlePostQuestion($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid request method']);
        return;
    }
    
    $student_name = getCurrentUsername(); // Use logged-in username
    $question_text = sanitizeInput($_POST['question_text'] ?? '');
    
    if (empty($question_text)) {
        echo json_encode(['error' => 'Question is required']);
        return;
    }
    
    if (strlen($question_text) > 1000) {
        echo json_encode(['error' => 'Question too long (max 1000 characters)']);
        return;
    }
    
    $image_path = null;
    
    // Handle image upload if present
    if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = handleImageUpload($_FILES['question_image']);
        if ($upload_result['success']) {
            $image_path = $upload_result['path'];
        } else {
            echo json_encode(['error' => $upload_result['error']]);
            return;
        }
    }
    
    // Insert question into database using MySQLi
    $sql = "INSERT INTO studyfeed_questions (student_name, question_text, image_path) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->bind_param("sss", $student_name, $question_text, $image_path);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Question posted successfully']);
        } else {
            echo json_encode(['error' => 'Failed to post question']);
        }
        $stmt->close();
    } else {
        echo json_encode(['error' => 'Database error']);
    }
}

// Function to handle posting an answer
function handlePostAnswer($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid request method']);
        return;
    }
    
    $question_id = intval($_POST['question_id'] ?? 0);
    $parent_id = intval($_POST['parent_id'] ?? 0) ?: null; // For nested replies
    $student_name = getCurrentUsername(); // Use logged-in username
    $answer_text = sanitizeInput($_POST['answer_text'] ?? '');
    
    if ($question_id <= 0 || empty($answer_text)) {
        echo json_encode(['error' => 'All fields are required']);
        return;
    }
    
    if (strlen($answer_text) > 500) {
        echo json_encode(['error' => 'Answer too long (max 500 characters)']);
        return;
    }
    
    // Check if question exists
    $check_sql = "SELECT id FROM studyfeed_questions WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $question_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Question not found']);
        $check_stmt->close();
        return;
    }
    $check_stmt->close();
    
    // If parent_id is provided, check if parent answer exists
    if ($parent_id) {
        $parent_check_sql = "SELECT id FROM studyfeed_answers WHERE id = ? AND question_id = ?";
        $parent_check_stmt = $conn->prepare($parent_check_sql);
        $parent_check_stmt->bind_param("ii", $parent_id, $question_id);
        $parent_check_stmt->execute();
        $parent_result = $parent_check_stmt->get_result();
        
        if ($parent_result->num_rows === 0) {
            echo json_encode(['error' => 'Parent answer not found']);
            $parent_check_stmt->close();
            return;
        }
        $parent_check_stmt->close();
    }
    
    // Insert answer
    $sql = "INSERT INTO studyfeed_answers (question_id, parent_id, student_name, answer_text) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        if ($parent_id) {
            $stmt->bind_param("iiss", $question_id, $parent_id, $student_name, $answer_text);
        } else {
            $null_parent = null;
            $stmt->bind_param("iiss", $question_id, $null_parent, $student_name, $answer_text);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Answer posted successfully']);
        } else {
            echo json_encode(['error' => 'Failed to post answer']);
        }
        $stmt->close();
    } else {
        echo json_encode(['error' => 'Database error']);
    }
}

// Function to get the feed of questions
function handleGetFeed($conn) {
    $page = intval($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT q.*, 
                   COUNT(a.id) as answer_count
            FROM studyfeed_questions q 
            LEFT JOIN studyfeed_answers a ON q.id = a.question_id 
            GROUP BY q.id 
            ORDER BY q.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }
    $stmt->close();
    
    // Format the data
    $formatted_questions = [];
    foreach ($questions as $question) {
        $formatted_questions[] = [
            'id' => $question['id'],
            'student_name' => sanitizeOutput($question['student_name']),
            'question_text' => sanitizeOutput($question['question_text']),
            'image_path' => $question['image_path'],
            'created_at' => formatTimeAgo($question['created_at']),
            'answer_count' => $question['answer_count'],
            'can_delete' => canDelete($question['student_name'])
        ];
    }
    
    echo json_encode(['success' => true, 'questions' => $formatted_questions]);
}

// Function to handle deleting a question
function handleDeleteQuestion($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid request method']);
        return;
    }
    
    $question_id = intval($_POST['question_id'] ?? 0);
    
    if ($question_id <= 0) {
        echo json_encode(['error' => 'Invalid question ID']);
        return;
    }
    
    // Check if question exists and get owner
    $check_sql = "SELECT student_name, image_path FROM studyfeed_questions WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $question_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $question = $result->fetch_assoc();
    $check_stmt->close();
    
    if (!$question) {
        echo json_encode(['error' => 'Question not found']);
        return;
    }
    
    // Check if user can delete (question owner or admin)
    if (!canDelete($question['student_name'])) {
        echo json_encode(['error' => 'You can only delete your own questions']);
        return;
    }
    
    // Delete associated image if exists
    if ($question['image_path'] && file_exists($question['image_path'])) {
        unlink($question['image_path']);
    }
    
    // Delete question (answers will be deleted by CASCADE)
    $delete_sql = "DELETE FROM studyfeed_questions WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $question_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Question deleted successfully']);
    } else {
        echo json_encode(['error' => 'Failed to delete question']);
    }
    $delete_stmt->close();
}

// Function to handle deleting an answer
function handleDeleteAnswer($conn) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'Invalid request method']);
        return;
    }
    
    $answer_id = intval($_POST['answer_id'] ?? 0);
    
    if ($answer_id <= 0) {
        echo json_encode(['error' => 'Invalid answer ID']);
        return;
    }
    
    // Get answer details and question owner
    $check_sql = "SELECT a.student_name as answer_author, q.student_name as question_author, a.question_id
                  FROM studyfeed_answers a 
                  JOIN studyfeed_questions q ON a.question_id = q.id 
                  WHERE a.id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $answer_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    $check_stmt->close();
    
    if (!$row) {
        echo json_encode(['error' => 'Answer not found']);
        return;
    }
    
    $current_user = getCurrentUsername();
    $is_admin = isAdmin();
    $is_answer_author = $current_user === $row['answer_author'];
    $is_question_author = $current_user === $row['question_author'];
    
    // Check if user can delete (answer author, question author, or admin)
    if (!$is_answer_author && !$is_question_author && !$is_admin) {
        echo json_encode(['error' => 'You can only delete your own answers or answers on your questions']);
        return;
    }
    
    // Delete answer (child answers will be deleted by CASCADE)
    $delete_sql = "DELETE FROM studyfeed_answers WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $answer_id);
    
    if ($delete_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Answer deleted successfully']);
    } else {
        echo json_encode(['error' => 'Failed to delete answer']);
    }
    $delete_stmt->close();
}

// Function to get current user info
function handleGetUserInfo() {
    echo json_encode([
        'success' => true,
        'logged_in' => isLoggedIn(),
        'username' => getCurrentUsername(),
        'role' => $_SESSION['role'] ?? null,
        'is_admin' => isAdmin(),
        'fee_paid' => hasFeePaid(),
        'user_id' => $_SESSION['user_id'] ?? null
    ]);
}

// Function to get answers for a specific question
function handleGetAnswers($conn) {
    $question_id = intval($_GET['question_id'] ?? 0);
    
    if ($question_id <= 0) {
        echo json_encode(['error' => 'Invalid question ID']);
        return;
    }
    
    // Get question owner for permission checking
    $question_sql = "SELECT student_name FROM studyfeed_questions WHERE id = ?";
    $question_stmt = $conn->prepare($question_sql);
    $question_stmt->bind_param("i", $question_id);
    $question_stmt->execute();
    $question_result = $question_stmt->get_result();
    $question = $question_result->fetch_assoc();
    $question_stmt->close();
    
    if (!$question) {
        echo json_encode(['error' => 'Question not found']);
        return;
    }
    
    // Get all answers for this question
    $sql = "SELECT * FROM studyfeed_answers WHERE question_id = ? ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $answers = [];
    while ($row = $result->fetch_assoc()) {
        $answers[] = $row;
    }
    $stmt->close();
    
    // Organize answers into nested structure and add delete permissions
    $answer_tree = buildAnswerTree($answers, $question['student_name']);
    
    echo json_encode(['success' => true, 'answers' => $answer_tree]);
}

// Function to build nested answer tree with delete permissions
function buildAnswerTree($answers, $question_author) {
    $tree = [];
    $refs = [];
    $current_user = getCurrentUsername();
    $is_admin = isAdmin();
    
    // First pass: create references
    foreach ($answers as $answer) {
        $answer['children'] = [];
        $answer['student_name'] = sanitizeOutput($answer['student_name']);
        $answer['answer_text'] = sanitizeOutput($answer['answer_text']);
        $answer['created_at'] = formatTimeAgo($answer['created_at']);
        
        // Check delete permissions: answer author, question author, or admin
        $is_answer_author = $current_user === $answer['student_name'];
        $is_question_author = $current_user === $question_author;
        $answer['can_delete'] = isLoggedIn() && ($is_answer_author || $is_question_author || $is_admin);
        
        $refs[$answer['id']] = $answer;
    }
    
    // Second pass: build tree
    foreach ($refs as $id => &$answer) {
        if ($answer['parent_id'] === null) {
            $tree[] = &$answer;
        } else {
            if (isset($refs[$answer['parent_id']])) {
                $refs[$answer['parent_id']]['children'][] = &$answer;
            }
        }
    }
    
    return $tree;
}

// Function to handle image uploads
function handleImageUpload($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File too large (max 2MB)'];
    }
    
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension']);
    
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, GIF allowed.'];
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $target_path = UPLOAD_DIR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'path' => $target_path];
    } else {
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
}

// Function to format timestamp as "time ago"
function formatTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . 'm';
    if ($time < 86400) return floor($time/3600) . 'h';
    if ($time < 2592000) return floor($time/86400) . 'd';
    if ($time < 31536000) return floor($time/2592000) . 'mo';
    return floor($time/31536000) . 'y';
}

// Sanitize input data
function sanitizeInput($input) {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

// Sanitize output data
function sanitizeOutput($output) {
    return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
}
?>