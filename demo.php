<?php
session_start();

// If no questions in session, redirect to load questions
if (!isset($_SESSION['demo_questions'])) {
    header('Location: load_demo.php');
    exit;
}

$questions = $_SESSION['demo_questions'];
$currentIndex = isset($_GET['q']) ? (int)$_GET['q'] : 0;

// Validate question index
if ($currentIndex < 0 || $currentIndex >= count($questions)) {
    $currentIndex = 0;
}

$currentQuestion = $questions[$currentIndex];
$totalQuestions = count($questions);
$progress = (($currentIndex + 1) / $totalQuestions) * 100;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store answer in session
    if (!isset($_SESSION['demo_answers'])) {
        $_SESSION['demo_answers'] = [];
    }
    
    $answer = isset($_POST['answer']) ? (int)$_POST['answer'] : null;
    $_SESSION['demo_answers'][$currentIndex] = $answer;
    
    // Store answer timestamp for analytics
    if (!isset($_SESSION['answer_times'])) {
        $_SESSION['answer_times'] = [];
    }
    $_SESSION['answer_times'][$currentIndex] = time();
    
    // Check if this is the last question
    if ($currentIndex >= $totalQuestions - 1) {
        // Redirect to results
        header('Location: demo_result.php');
        exit;
    } else {
        // Go to next question
        header('Location: demo.php?q=' . ($currentIndex + 1));
        exit;
    }
}

// Get user's previous answer for this question
$userAnswer = isset($_SESSION['demo_answers'][$currentIndex]) ? $_SESSION['demo_answers'][$currentIndex] : null;

// Calculate time spent on test
$startTime = $_SESSION['test_start_time'] ?? time();
$_SESSION['test_start_time'] = $startTime;
$timeSpent = time() - $startTime;
$minutes = floor($timeSpent / 60);
$seconds = $timeSpent % 60;

// Count answered questions
$answeredCount = isset($_SESSION['demo_answers']) ? count($_SESSION['demo_answers']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MDCAT Demo Test - Question <?= $currentIndex + 1 ?> | Nayarasta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #3a4750;
            --primary-light: #5a6670;
            --accent-light: #e8ecef;
            --text-dark: #2c3e50;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .demo-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-light) 100%);
            color: white;
            padding: 2rem 0;
            position: relative;
            overflow: hidden;
        }

        .demo-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .demo-header .container {
            position: relative;
            z-index: 2;
        }

        .progress-custom {
            height: 12px;
            border-radius: 20px;
            background-color: rgba(255,255,255,0.2);
            overflow: hidden;
        }

        .progress-custom .progress-bar {
            border-radius: 20px;
            background: linear-gradient(45deg, var(--success), #2ecc71);
            position: relative;
            overflow: hidden;
        }

        .progress-custom .progress-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .question-card {
            border: none;
            box-shadow: 0 15px 35px rgba(58, 71, 80, 0.1);
            border-radius: 20px;
            margin-bottom: 2rem;
            background: white;
            position: relative;
            overflow: hidden;
        }

        .question-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-dark), var(--primary-light));
        }

        .option-btn {
            border: 2px solid var(--accent-light);
            border-radius: 15px;
            padding: 18px 25px;
            margin-bottom: 12px;
            background: white;
            color: var(--text-dark);
            text-align: left;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .option-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(58, 71, 80, 0.1), transparent);
            transition: left 0.5s;
        }

        .option-btn:hover {
            border-color: var(--primary-dark);
            background-color: rgba(58, 71, 80, 0.05);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(58, 71, 80, 0.15);
        }

        .option-btn:hover::before {
            left: 100%;
        }

        .option-btn.selected {
            border-color: var(--primary-dark);
            background: linear-gradient(135deg, rgba(58, 71, 80, 0.1), rgba(90, 102, 112, 0.1));
            color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(58, 71, 80, 0.2);
        }

        .category-badge {
            position: absolute;
            top: -12px;
            right: 25px;
            background: linear-gradient(45deg, var(--primary-dark), var(--primary-light));
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 5px 15px rgba(58, 71, 80, 0.3);
        }

        .demo-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid var(--warning);
            border-radius: 15px;
            color: #856404;
            padding: 20px;
        }

        .navigation-btn {
            border-radius: 30px;
            padding: 12px 35px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .navigation-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .navigation-btn:hover::before {
            left: 100%;
        }

        .btn-next {
            background: linear-gradient(45deg, var(--primary-dark), var(--primary-light));
            color: white;
        }

        .btn-previous {
            background: linear-gradient(45deg, #6c757d, #5a6268);
            color: white;
        }

        .btn-finish {
            background: linear-gradient(45deg, var(--success), #27ae60);
            color: white;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-light));
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .timer-display {
            background: rgba(255,255,255,0.1);
            padding: 10px 15px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .question-nav-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid var(--accent-light);
            background: white;
            color: var(--text-dark);
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }

        .question-nav-btn.answered {
            background: var(--success);
            color: white;
            border-color: var(--success);
        }

        .question-nav-btn.current {
            background: var(--primary-dark);
            color: white;
            border-color: var(--primary-dark);
            transform: scale(1.1);
        }

        .question-nav-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .bookmark-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            color: var(--warning);
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .bookmark-btn:hover {
            background: var(--warning);
            color: white;
            transform: scale(1.1);
        }

        .bookmark-btn.bookmarked {
            background: var(--warning);
            color: white;
        }

        .floating-help {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .help-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--info);
            color: white;
            border: none;
            font-size: 1.5rem;
            box-shadow: 0 5px 20px rgba(52, 152, 219, 0.4);
            transition: all 0.3s ease;
        }

        .help-btn:hover {
            transform: scale(1.1);
            background: #2980b9;
        }

        .time-warning {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .demo-header {
                padding: 1.5rem 0;
            }
            
            .option-btn {
                padding: 15px 20px;
                font-size: 0.9rem;
            }
            
            .navigation-btn {
                padding: 10px 25px;
                font-size: 0.8rem;
            }
            
            .question-nav-btn {
                width: 35px;
                height: 35px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <div class="demo-header fade-in">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2><i class="fas fa-graduation-cap me-2"></i>MDCAT Demo Test</h2>
                    <p class="mb-0">Question <?= $currentIndex + 1 ?> of <?= $totalQuestions ?> • Nayarasta</p>
                </div>
                <div class="col-md-3 text-center">
                    <div class="timer-display">
                        <i class="fas fa-clock me-2"></i>
                        <span id="timer"><?= sprintf('%02d:%02d', $minutes, $seconds) ?></span>
                    </div>
                </div>
                <div class="col-md-3 text-end">
                    <h4><?= number_format($progress, 1) ?>% Complete</h4>
                    <small><?= $answeredCount ?> of <?= $totalQuestions ?> answered</small>
                </div>
            </div>
            <div class="progress progress-custom mt-3">
                <div class="progress-bar" style="width: <?= $progress ?>%"></div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Stats Card -->
        <div class="stats-card fade-in">
            <div class="row text-center">
                <div class="col-3">
                    <h5><?= $answeredCount ?></h5>
                    <small>Answered</small>
                </div>
                <div class="col-3">
                    <h5><?= $totalQuestions - $answeredCount ?></h5>
                    <small>Remaining</small>
                </div>
                <div class="col-3">
                    <h5 id="questionsPerMinute">--</h5>
                    <small>Q/Min</small>
                </div>
                <div class="col-3">
                    <h5><?= htmlspecialchars($currentQuestion['category']) ?></h5>
                    <small>Category</small>
                </div>
            </div>
        </div>

        <!-- Demo Warning -->
        <div class="alert demo-warning fade-in">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Demo Test:</strong> This is a sample test. Full results and detailed analysis are available after registration.
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-phone me-2"></i>Contact: <strong>03328335332</strong>
                </div>
            </div>
        </div>

        <!-- Question Card -->
        <div class="card question-card position-relative fade-in">
            <span class="category-badge"><?= htmlspecialchars($currentQuestion['category']) ?></span>
            
            <!-- Bookmark Button -->
            <button type="button" class="bookmark-btn" onclick="toggleBookmark(<?= $currentIndex ?>)" 
                    id="bookmarkBtn" title="Bookmark this question">
                <i class="fas fa-bookmark"></i>
            </button>
            
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <h4 class="card-title mb-0">
                        Q<?= $currentIndex + 1 ?>. <?= htmlspecialchars($currentQuestion['question']) ?>
                    </h4>
                </div>

                <form method="POST" id="answerForm">
                    <div class="row">
                        <?php 
                        $options = [
                            $currentQuestion['option_a'],
                            $currentQuestion['option_b'], 
                            $currentQuestion['option_c'],
                            $currentQuestion['option_d']
                        ];
                        
                        foreach ($options as $index => $option): 
                        ?>
                        <div class="col-12 mb-2">
                            <button type="button" 
                                    class="option-btn <?= ($userAnswer === $index) ? 'selected' : '' ?>" 
                                    onclick="selectOption(<?= $index ?>)"
                                    data-option="<?= $index ?>">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <strong style="background: rgba(58, 71, 80, 0.1); padding: 5px 10px; border-radius: 50%;">
                                            <?= chr(65 + $index) ?>
                                        </strong>
                                    </div>
                                    <div><?= htmlspecialchars($option) ?></div>
                                </div>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" name="answer" id="selectedAnswer" value="<?= $userAnswer ?>">
                </form>
            </div>
        </div>

        <!-- Navigation -->
        <div class="row mb-4">
            <div class="col-md-4">
                <?php if ($currentIndex > 0): ?>
                <a href="demo.php?q=<?= $currentIndex - 1 ?>" class="btn btn-previous navigation-btn">
                    <i class="fas fa-arrow-left me-2"></i>Previous
                </a>
                <?php endif; ?>
            </div>
            <div class="col-md-4 text-center">
                <button type="button" class="btn btn-outline-secondary" onclick="clearAnswer()">
                    <i class="fas fa-eraser me-2"></i>Clear Answer
                </button>
            </div>
            <div class="col-md-4 text-end">
                <?php if ($currentIndex < $totalQuestions - 1): ?>
                <button type="button" class="btn btn-next navigation-btn" onclick="submitAnswer()">
                    Next<i class="fas fa-arrow-right ms-2"></i>
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-finish navigation-btn" onclick="submitAnswer()">
                    <i class="fas fa-check me-2"></i>Finish Test
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Question Navigator -->
        <div class="card">
            <div class="card-header" style="background: linear-gradient(135deg, var(--primary-dark), var(--primary-light)); color: white;">
                <h6 class="mb-0"><i class="fas fa-map me-2"></i>Question Navigator</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php for ($i = 0; $i < $totalQuestions; $i++): ?>
                    <div class="col-auto">
                        <?php 
                        $answered = isset($_SESSION['demo_answers'][$i]);
                        $isCurrent = ($i === $currentIndex);
                        $btnClass = $isCurrent ? 'current' : ($answered ? 'answered' : '');
                        ?>
                        <a href="demo.php?q=<?= $i ?>" class="question-nav-btn <?= $btnClass ?>" 
                           title="Question <?= $i + 1 ?><?= $answered ? ' (Answered)' : '' ?>">
                            <?= $i + 1 ?>
                        </a>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Help Button -->
    <div class="floating-help">
        <button class="help-btn" data-bs-toggle="modal" data-bs-target="#helpModal" title="Help & Shortcuts">
            <i class="fas fa-question"></i>
        </button>
    </div>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-dark); color: white;">
                    <h5 class="modal-title"><i class="fas fa-keyboard me-2"></i>Keyboard Shortcuts & Help</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-keyboard me-2"></i>Navigation</h6>
                            <ul class="list-unstyled">
                                <li><kbd>→</kbd> or <kbd>Enter</kbd> Next question</li>
                                <li><kbd>←</kbd> Previous question</li>
                                <li><kbd>1-4</kbd> Select options A-D</li>
                                <li><kbd>Space</kbd> Clear answer</li>
                                <li><kbd>B</kbd> Bookmark question</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-lightbulb me-2"></i>Tips</h6>
                            <ul class="list-unstyled">
                                <li>• Use bookmarks for review</li>
                                <li>• Monitor your pace</li>
                                <li>• Auto-save is enabled</li>
                                <li>• Click question numbers to jump</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let startTime = <?= $startTime ?>;
        let currentTime = <?= time() ?>;
        let timeOffset = currentTime - startTime;

        function updateTimer() {
            timeOffset++;
            const minutes = Math.floor(timeOffset / 60);
            const seconds = timeOffset % 60;
            document.getElementById('timer').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            // Update questions per minute
            const totalAnswered = <?= $answeredCount ?>;
            if (timeOffset > 0 && totalAnswered > 0) {
                const qpm = (totalAnswered / (timeOffset / 60)).toFixed(1);
                document.getElementById('questionsPerMinute').textContent = qpm;
            }
            
            // Warning for slow pace (less than 1 question per 2 minutes)
            if (timeOffset > 120 && totalAnswered < timeOffset / 120) {
                document.getElementById('timer').parentElement.classList.add('time-warning');
            }
        }

        setInterval(updateTimer, 1000);

        function selectOption(index) {
            // Remove selected class from all options
            document.querySelectorAll('.option-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            event.target.closest('.option-btn').classList.add('selected');
            
            // Update hidden input
            document.getElementById('selectedAnswer').value = index;
            
            // Auto-advance after 1 second (optional)
            // setTimeout(() => submitAnswer(), 1000);
        }

        function submitAnswer() {
            document.getElementById('answerForm').submit();
        }

        function clearAnswer() {
            document.querySelectorAll('.option-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            document.getElementById('selectedAnswer').value = '';
        }

        function toggleBookmark(questionIndex) {
            let bookmarks = JSON.parse(localStorage.getItem('demo_bookmarks') || '[]');
            const btn = document.getElementById('bookmarkBtn');
            
            if (bookmarks.includes(questionIndex)) {
                bookmarks = bookmarks.filter(q => q !== questionIndex);
                btn.classList.remove('bookmarked');
                btn.title = 'Bookmark this question';
            } else {
                bookmarks.push(questionIndex);
                btn.classList.add('bookmarked');
                btn.title = 'Remove bookmark';
            }
            
            localStorage.setItem('demo_bookmarks', JSON.stringify(bookmarks));
        }

        // Load bookmarks on page load
        document.addEventListener('DOMContentLoaded', function() {
            const bookmarks = JSON.parse(localStorage.getItem('demo_bookmarks') || '[]');
            if (bookmarks.includes(<?= $currentIndex ?>)) {
                document.getElementById('bookmarkBtn').classList.add('bookmarked');
                document.getElementById('bookmarkBtn').title = 'Remove bookmark';
            }
        });

        // Enhanced keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            switch(e.key) {
                case 'ArrowRight':
                case 'Enter':
                    e.preventDefault();
                    submitAnswer();
                    break;
                case 'ArrowLeft':
                    if (<?= $currentIndex ?> > 0) {
                        e.preventDefault();
                        window.location.href = 'demo.php?q=<?= $currentIndex - 1 ?>';
                    }
                    break;
                case '1': case '2': case '3': case '4':
                    e.preventDefault();
                    selectOption(parseInt(e.key) - 1);
                    break;
                case ' ':
                    e.preventDefault();
                    clearAnswer();
                    break;
                case 'b':
                case 'B':
                    e.preventDefault();
                    toggleBookmark(<?= $currentIndex ?>);
                    break;
            }
        });

        // Auto-save functionality
        setInterval(function() {
            const selectedAnswer = document.getElementById('selectedAnswer').value;
            if (selectedAnswer !== '') {
                localStorage.setItem('demo_answer_<?= $currentIndex ?>', selectedAnswer);
                localStorage.setItem('demo_last_question', <?= $currentIndex ?>);
            }
        }, 5000);

        // Page visibility API for pause detection
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                localStorage.setItem('demo_pause_time', Date.now());
            } else {
                const pauseTime = localStorage.getItem('demo_pause_time');
                if (pauseTime) {
                    console.log('Test resumed after', Math.round((Date.now() - pauseTime) / 1000), 'seconds');
                }
            }
        });

        // Smooth transitions
        document.querySelectorAll('.option-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 100);
            });
        });

        // Progress animation
        window.addEventListener('load', function() {
            const progressBar = document.querySelector('.progress-bar');
            progressBar.style.transition = 'width 1s ease-in-out';
        });
    </script>
</body>
</html>