<?php
header("Content-Type: application/json");
require_once "db.php";

try {
    // Check if we have enough questions for demo
    $categories = ['Physics', 'Chemistry', 'Biology', 'English'];
    $totalAvailable = 0;
    $categoryCount = [];
    
    foreach ($categories as $category) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM mcqs WHERE category = ? AND type = 'regular'");
        $stmt->bind_param("s", $category);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)$row['count'];
        
        $categoryCount[$category] = $count;
        $totalAvailable += $count;
        $stmt->close();
    }
    
    // Check if we have at least 20 questions total (minimum for a demo)
    $isAvailable = $totalAvailable >= 20;
    
    echo json_encode([
        "status" => "success",
        "available" => $isAvailable,
        "total_questions" => $totalAvailable,
        "category_breakdown" => $categoryCount,
        "message" => $isAvailable ? "Demo test is available" : "Not enough questions for demo test"
    ]);
    
} catch (Exception $e) {
    error_log("Demo check error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "available" => false,
        "message" => "Unable to check demo availability"
    ]);
}

$conn->close();
?>