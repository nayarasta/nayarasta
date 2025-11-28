<?php
// config.php - Configuration file for StudyFeed

// Database configuration - Update these with your actual database credentials
define('DB_HOST', 'sql100.infinityfree.com'); // Your InfinityFree MySQL host
define('DB_USERNAME', 'if0_39610050'); // Your database username
define('DB_PASSWORD', 'otgdcSrpmfYvX6S'); // Your database password
define('DB_NAME', 'if0_39610050_doctors'); // Your database name

// File upload configuration
define('UPLOAD_DIR', 'uploads/studyfeed/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Function to get database connection
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
                       DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch(PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// Create tables if they don't exist
function createStudyFeedTables() {
    $pdo = getDBConnection();
    
    // Create questions table
    $questions_sql = "CREATE TABLE IF NOT EXISTS studyfeed_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_name VARCHAR(100) NOT NULL,
        question_text TEXT NOT NULL,
        image_path VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        INDEX idx_student_name (student_name)
    ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Create answers table
    $answers_sql = "CREATE TABLE IF NOT EXISTS studyfeed_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        parent_id INT NULL,
        student_name VARCHAR(100) NOT NULL,
        answer_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (question_id) REFERENCES studyfeed_questions(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES studyfeed_answers(id) ON DELETE CASCADE,
        INDEX idx_question_id (question_id),
        INDEX idx_parent_id (parent_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($questions_sql);
    $pdo->exec($answers_sql);
}

// Call this function once to create tables (you can comment it out after first run)
// createStudyFeedTables();
?>