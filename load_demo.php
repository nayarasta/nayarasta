<?php
session_start();
require_once "db.php";

// Clear any existing demo session data
unset($_SESSION['demo_questions']);
unset($_SESSION['demo_answers']);

try {
    // Get 10 random questions from each category
    $categories = ['Physics', 'Chemistry', 'Biology', 'English'];
    $allQuestions = [];
    
    foreach ($categories as $category) {
        // Get 10 random questions from this category
        $stmt = $conn->prepare("
            SELECT id, question, option_a, option_b, option_c, option_d, correct_answer, category 
            FROM mcqs 
            WHERE category = ? AND type = 'regular'
            ORDER BY RAND() 
            LIMIT 10
        ");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $categoryQuestions = [];
        while ($row = $result->fetch_assoc()) {
            $categoryQuestions[] = [
                'id' => (int)$row['id'],
                'question' => $row['question'],
                'option_a' => $row['option_a'],
                'option_b' => $row['option_b'],
                'option_c' => $row['option_c'],
                'option_d' => $row['option_d'],
                'correct_answer' => (int)$row['correct_answer'],
                'category' => $row['category']
            ];
        }
        
        $stmt->close();
        
        // Add to all questions array
        $allQuestions = array_merge($allQuestions, $categoryQuestions);
        
        // Log for debugging
        error_log("Loaded " . count($categoryQuestions) . " questions from $category category");
    }
    
    // Check if we have enough questions
    if (count($allQuestions) < 20) {
        throw new Exception("Not enough questions available. Found: " . count($allQuestions));
    }
    
    // Shuffle all questions for random order
    shuffle($allQuestions);
    
    // Store questions in session
    $_SESSION['demo_questions'] = $allQuestions;
    $_SESSION['demo_start_time'] = time();
    
    // Log success
    error_log("Demo test loaded successfully with " . count($allQuestions) . " questions");
    
    // Redirect to demo test
    header('Location: demo.php?q=0');
    exit;
    
} catch (Exception $e) {
    error_log("Demo load error: " . $e->getMessage());
    
    // Fallback: Load any available questions if category-specific loading fails
    try {
        $stmt = $conn->prepare("
            SELECT id, question, option_a, option_b, option_c, option_d, correct_answer, category 
            FROM mcqs 
            WHERE type = 'regular'
            ORDER BY RAND() 
            LIMIT 40
        ");
        
        if (!$stmt) {
            throw new Exception("Fallback query failed: " . $conn->error);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $fallbackQuestions = [];
        while ($row = $result->fetch_assoc()) {
            $fallbackQuestions[] = [
                'id' => (int)$row['id'],
                'question' => $row['question'],
                'option_a' => $row['option_a'],
                'option_b' => $row['option_b'],
                'option_c' => $row['option_c'],
                'option_d' => $row['option_d'],
                'correct_answer' => (int)$row['correct_answer'],
                'category' => $row['category']
            ];
        }
        
        $stmt->close();
        
        if (count($fallbackQuestions) > 0) {
            $_SESSION['demo_questions'] = $fallbackQuestions;
            $_SESSION['demo_start_time'] = time();
            
            error_log("Fallback demo loaded with " . count($fallbackQuestions) . " questions");
            header('Location: demo.php?q=0');
            exit;
        } else {
            throw new Exception("No questions available in database");
        }
        
    } catch (Exception $fallbackError) {
        error_log("Fallback demo load error: " . $fallbackError->getMessage());
        
        // Show user-friendly error page
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Demo Test - Error</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        </head>
        <body class="bg-light">
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5><i class="fas fa-exclamation-triangle me-2"></i>Demo Test Unavailable</h5>
                            </div>
                            <div class="card-body text-center">
                                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                <h4>Questions Not Available</h4>
                                <p class="text-muted">
                                    We're currently updating our question database. 
                                    Please try again in a few minutes or contact our admin.
                                </p>
                                <div class="alert alert-info mt-4">
                                    <i class="fas fa-phone me-2"></i>
                                    Contact <strong>03328335332</strong> for immediate access or registration
                                </div>
                                <a href="index.html" class="btn btn-primary">
                                    <i class="fas fa-home me-2"></i>Back to Home
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        <script>
         window.onbeforeunload = null;
        </script>

        </html>
        <?php
        exit;
    }
}

$conn->close();
?>