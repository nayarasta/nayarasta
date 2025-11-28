<?php
/**
 * MDCAT App Direct Download Page
 */

// Configuration
$app_config = [
    'app_name' => 'Pencil',
    'version' => '1.0.0',
    'apk_direct' => 'https://storage.appilix.com/uploads/app-apk-689621e2a70e6-1754669538.apk',
    'file_size' => '26 MB',
    'min_android' => '6.0',
    'features' => [
        'Interactive Quiz System',
        'Live Study Sessions',
        'Professional Track Records',
        'Complete Past Papers Collection',
        'Student Discussion Forums',
        'Offline Study Mode',
        'Progress Tracking',
        'Expert Solutions'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Download <?php echo htmlspecialchars($app_config['app_name']); ?> - Mobile App</title>
  <meta name="description" content="Download the official MDCAT Study Hub mobile app for quiz practice, live sessions, past papers and more.">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: #333;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
    }
    .container {
      max-width: 800px;
      margin: 0 auto;
      padding: 20px;
    }
    .app-card {
      background: white;
      border-radius: 20px;
      padding: 40px;
      margin: 20px 0;
      box-shadow: 0 20px 40px rgba(0,0,0,0.1);
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .app-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #4CAF50, #2196F3, #FF9800);
    }
    .app-icon {
      width: 120px;
      height: 120px;
      background: linear-gradient(135deg, #4CAF50, #45a049);
      border-radius: 25px;
      margin: 0 auto 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 48px;
      color: white;
      box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
    }
    h1 {
      color: #2c3e50;
      margin-bottom: 10px;
      font-size: 2.5em;
      font-weight: 700;
    }
    .version {
      color: #7f8c8d;
      font-size: 1.1em;
      margin-bottom: 20px;
    }
    .description {
      font-size: 1.2em;
      color: #555;
      margin-bottom: 30px;
      line-height: 1.8;
    }
    .download-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
      margin: 30px 0;
      flex-wrap: wrap;
    }
    .download-btn {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 15px 30px;
      background: linear-gradient(45deg, #FF9800, #F57C00);
      color: white;
      text-decoration: none;
      border-radius: 50px;
      font-weight: 600;
      font-size: 1.1em;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(255, 152, 0, 0.3);
      min-width: 200px;
    }
    .download-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(255, 152, 0, 0.4);
    }
    .features {
      background: #f8f9fa;
      border-radius: 15px;
      padding: 30px;
      margin-top: 30px;
    }
    .features h3 {
      color: #2c3e50;
      margin-bottom: 20px;
      font-size: 1.5em;
    }
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 15px;
    }
    .feature-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .feature-icon {
      width: 24px;
      height: 24px;
      background: #4CAF50;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
    }
    .app-info {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 20px;
      margin: 30px 0;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 15px;
    }
    .info-item {
      text-align: center;
    }
    .info-label {
      font-weight: 600;
      color: #7f8c8d;
      display: block;
      margin-bottom: 5px;
    }
    .info-value {
      font-size: 1.2em;
      color: #2c3e50;
      font-weight: 700;
    }
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 24px;
      background: rgba(255,255,255,0.2);
      color: white;
      text-decoration: none;
      border-radius: 25px;
      font-weight: 500;
      transition: all 0.3s ease;
      backdrop-filter: blur(10px);
    }
    .back-btn:hover {
      background: rgba(255,255,255,0.3);
      transform: translateY(-1px);
    }
    @media (max-width: 768px) {
      .container { padding: 15px; }
      .app-card { padding: 30px 20px; }
      h1 { font-size: 2em; }
      .download-buttons {
        flex-direction: column;
        align-items: center;
      }
      .download-btn {
        min-width: 250px;
      }
    }
    .loading-spinner {
      display: none;
      width: 20px;
      height: 20px;
      border: 2px solid #ffffff;
      border-top: 2px solid transparent;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
  <div class="container">
    <a href="javascript:history.back()" class="back-btn">← Back to Website</a>

    <div class="app-card">
      <div class="app-icon">✏️</div>
      <h1><?php echo htmlspecialchars($app_config['app_name']); ?></h1>
      <div class="version">Version <?php echo htmlspecialchars($app_config['version']); ?></div>

      <div class="description">
        Experience the complete MDCAT preparation journey in your pocket. Access interactive quizzes, 
        join live study sessions, track your progress, and connect with fellow students - all offline capable!
      </div>

      <div class="app-info">
        <div class="info-item">
          <span class="info-label">File Size</span>
          <span class="info-value"><?php echo $app_config['file_size']; ?></span>
        </div>
        <div class="info-item">
          <span class="info-label">Min. Android</span>
          <span class="info-value"><?php echo $app_config['min_android']; ?>+</span>
        </div>
        <div class="info-item">
          <span class="info-label">Category</span>
          <span class="info-value">Education</span>
        </div>
        <div class="info-item">
          <span class="info-label">Downloads</span>
          <span class="info-value">1000+</span>
        </div>
      </div>

      <div class="download-buttons">
        <a href="<?php echo $app_config['apk_direct']; ?>" target="_blank" class="download-btn" onclick="showLoading(this)">
          <span>⬇️</span>
          <span>Direct APK Download</span>
          <div class="loading-spinner"></div>
        </a>
      </div>

      <div class="features">
        <h3>🚀 Key Features</h3>
        <div class="features-grid">
          <?php foreach ($app_config['features'] as $feature): ?>
          <div class="feature-item">
            <div class="feature-icon">✓</div>
            <span><?php echo htmlspecialchars($feature); ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <script>
    function showLoading(button) {
      const spinner = button.querySelector('.loading-spinner');
      const text = button.querySelector('span:last-of-type');

      spinner.style.display = 'block';
      text.textContent = 'Opening...';

      setTimeout(() => {
        spinner.style.display = 'none';
        text.textContent = 'Direct APK Download';
      }, 3000);
    }
  </script>
</body>
</html>
