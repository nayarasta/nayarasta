<?php
session_start();

// Check if demo was completed
if (!isset($_SESSION['demo_questions']) || !isset($_SESSION['demo_answers'])) {
    header('Location: index.html');
    exit;
}

$questions = $_SESSION['demo_questions'];
$answers = $_SESSION['demo_answers'];
$startTime = $_SESSION['demo_start_time'] ?? time();

// Calculate score
$score = 0;
$totalQuestions = count($questions);
$categoryScores = [];

foreach ($questions as $index => $question) {
    $userAnswer = isset($answers[$index]) ? $answers[$index] : null;
    $isCorrect = ($userAnswer === $question['correct_answer']);
    
    if ($isCorrect) {
        $score++;
    }
    
    // Track category performance
    $category = $question['category'];
    if (!isset($categoryScores[$category])) {
        $categoryScores[$category] = ['correct' => 0, 'total' => 0];
    }
    $categoryScores[$category]['total']++;
    if ($isCorrect) {
        $categoryScores[$category]['correct']++;
    }
}

$percentage = round(($score / $totalQuestions) * 100, 1);
$timeTaken = time() - $startTime;

// Clear demo session data
unset($_SESSION['demo_questions']);
unset($_SESSION['demo_answers']);
unset($_SESSION['demo_start_time']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MDCAT Demo Test - Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .result-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
        }
        .score-card {
            border: none;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            border-radius: 20px;
            overflow: hidden;
        }
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: bold;
            color: white;
            margin: 0 auto 2rem;
        }
        .score-excellent { background: linear-gradient(45deg, #28a745, #20c997); }
        .score-good { background: linear-gradient(45deg, #ffc107, #fd7e14); }
        .score-needs-improvement { background: linear-gradient(45deg, #dc3545, #e83e8c); }
        .category-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
        }
        .registration-cta {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .action-btn {
            border-radius: 25px;
            padding: 12px 30px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Header -->
    <div class="result-header">
        <div class="container text-center">
            <h1><i class="fas fa-trophy me-3"></i>Demo Test Complete!</h1>
            <p class="lead mb-0">Here are your results</p>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Main Score Card -->
        <div class="row justify-content-center mb-4">
            <div class="col-md-8">
                <div class="card score-card">
                    <div class="card-body text-center p-5">
                        <?php
                        $scoreClass = $percentage >= 70 ? 'score-excellent' : ($percentage >= 50 ? 'score-good' : 'score-needs-improvement');
                        $scoreMessage = $percentage >= 70 ? 'Excellent!' : ($percentage >= 50 ? 'Good Job!' : 'Keep Practicing!');
                        ?>
                        <div class="score-circle <?= $scoreClass ?>">
                            <?= $percentage ?>%
                        </div>
                        
                        <h2 class="mb-4"><?= $scoreMessage ?></h2>
                        
                        <div class="row text-center mb-4">
                            <div class="col-md-3">
                                <h3 class="text-primary"><?= $score ?></h3>
                                <p class="text-muted">Correct</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-danger"><?= $totalQuestions - $score ?></h3>
                                <p class="text-muted">Incorrect</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-info"><?= $totalQuestions ?></h3>
                                <p class="text-muted">Total</p>
                            </div>
                            <div class="col-md-3">
                                <h3 class="text-success"><?= gmdate("i:s", $timeTaken) ?></h3>
                                <p class="text-muted">Time</p>
                            </div>
                        </div>
                        
                        <div class="progress mb-4" style="height: 15px;">
                            <div class="progress-bar <?= $percentage >= 70 ? 'bg-success' : ($percentage >= 50 ? 'bg-warning' : 'bg-danger') ?>" 
                                 style="width: <?= $percentage ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Performance -->
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Category Performance</h4>
            </div>
            <?php foreach ($categoryScores as $category => $data): ?>
            <div class="col-md-3 mb-3">
                <div class="card category-card">
                    <div class="card-body text-center">
                        <h6 class="card-title"><?= $category ?></h6>
                        <?php 
                        $categoryPercentage = $data['total'] > 0 ? round(($data['correct'] / $data['total']) * 100) : 0;
                        $categoryClass = $categoryPercentage >= 70 ? 'text-success' : ($categoryPercentage >= 50 ? 'text-warning' : 'text-danger');
                        ?>
                        <h4 class="<?= $categoryClass ?>"><?= $data['correct'] ?>/<?= $data['total'] ?></h4>
                        <small class="text-muted"><?= $categoryPercentage ?>%</small>
                        <div class="progress mt-2" style="height: 5px;">
                            <div class="progress-bar <?= $categoryPercentage >= 70 ? 'bg-success' : ($categoryPercentage >= 50 ? 'bg-warning' : 'bg-danger') ?>" 
                                 style="width: <?= $categoryPercentage ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

<!-- Registration CTA -->
<div class="row mb-4">
    <div class="col-12">
        <div class="registration-cta">
            <h3><i class="fas fa-star me-2"></i>Unlock Full Potential!</h3>
            <p class="lead mb-4">
                This was just a demo! Get access to thousands more questions, 
                detailed explanations, progress tracking, and much more.
            </p>
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5><i class="fas fa-phone me-2"></i>Contact for Registration</h5>
                    <h4 class="text-warning">03328335332</h4>
                    <a href="https://wa.me/923328335332" target="_blank" class="btn btn-success mt-3">
                        <i class="fab fa-whatsapp me-2"></i>Register Yourself NOW
                    </a>
                </div>
                <div class="col-md-4 text-center">
                    <i class="fas fa-graduation-cap fa-4x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
</div>


        <!-- Action Buttons -->
        <div class="row text-center">
            <div class="col-12">
                <a href="index.html" class="btn btn-primary action-btn">
                    <i class="fas fa-home me-2"></i>Back to Home
                </a>
                <button onclick="window.print()" class="btn btn-outline-secondary action-btn">
                    <i class="fas fa-print me-2"></i>Print Results
                </button>
                <button onclick="shareResults()" class="btn btn-outline-success action-btn">
                    <i class="fas fa-share me-2"></i>Share Results
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function shareResults() {
            if (navigator.share) {
                navigator.share({
                    title: 'MDCAT Demo Test Results',
                    text: `I scored <?= $percentage ?>% (<?= $score ?>/<?= $totalQuestions ?>) on the MDCAT Demo Test!`,
                    url: window.location.href
                });
            } else {
                // Fallback - copy to clipboard
                const text = `I scored <?= $percentage ?>% (<?= $score ?>/<?= $totalQuestions ?>) on the MDCAT Demo Test! Contact 03328335332 for registration.`;
                navigator.clipboard.writeText(text).then(() => {
                    alert('Results copied to clipboard!');
                });
            }
        }

        // Confetti effect for good scores
        <?php if ($percentage >= 70): ?>
        function createConfetti() {
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.style.position = 'fixed';
                confetti.style.width = '10px';
                confetti.style.height = '10px';
                confetti.style.backgroundColor = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#feca57'][Math.floor(Math.random() * 5)];
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.top = '-10px';
                confetti.style.zIndex = '9999';
                confetti.style.borderRadius = '50%';
                document.body.appendChild(confetti);
                
                confetti.animate([
                    { transform: 'translateY(0px) rotate(0deg)', opacity: 1 },
                    { transform: 'translateY(100vh) rotate(360deg)', opacity: 0 }
                ], {
                    duration: 3000,
                    easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
                }).onfinish = () => confetti.remove();
            }
        }
        
        // Trigger confetti after page load
        setTimeout(createConfetti, 500);
        <?php endif; ?>
    </script>
</body>
</html>