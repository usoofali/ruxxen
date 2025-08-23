<?php
/**
 * Ruxxen LPG - Production Setup Script
 * 
 * This script can be run directly in hosting environments to set up the system.
 * Run this script by accessing: https://your-domain.com/setup.php
 * 
 * IMPORTANT: Delete this file after successful setup for security reasons.
 */

// Prevent direct access if not in web context
if (php_sapi_name() === 'cli') {
    echo "This script should be run via web browser.\n";
    exit(1);
}

// Check if Laravel is properly installed
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die('Laravel dependencies not found. Please run: composer install');
}

// Load Laravel
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check if system is already configured
try {
    $setupService = new \App\Services\ProductionSetupService();
    $isConfigured = !$setupService->isSetupRequired();
} catch (Exception $e) {
    $isConfigured = false;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    try {
        $setupService = new \App\Services\ProductionSetupService();
        $results = $setupService->runSetup();
        $setupSuccess = $results['success'];
        $setupMessage = $setupSuccess ? 'Setup completed successfully!' : 'Setup failed. Please check the errors below.';
        $setupSteps = $results['steps'] ?? [];
        $setupErrors = $results['errors'] ?? [];
    } catch (Exception $e) {
        $setupSuccess = false;
        $setupMessage = 'Setup failed: ' . $e->getMessage();
        $setupSteps = [];
        $setupErrors = [$e->getMessage()];
    }
}

// Get current progress
try {
    $progress = $setupService->getSetupProgress();
} catch (Exception $e) {
    $progress = [
        'database_migrated' => false,
        'admin_created' => false,
        'company_configured' => false,
        'system_configured' => false,
        'total_steps' => 4,
        'completed_steps' => 0,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruxxen LPG - Production Setup</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 800px;
            width: 100%;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .progress-bar {
            background: #f0f0f0;
            border-radius: 10px;
            height: 20px;
            margin: 20px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 10px;
        }
        
        .step {
            display: flex;
            align-items: center;
            padding: 15px;
            margin: 10px 0;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #fafafa;
        }
        
        .step.completed {
            background: #f0f9ff;
            border-color: #0ea5e9;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .step.completed .step-number {
            background: #0ea5e9;
            color: white;
        }
        
        .step-content {
            flex: 1;
        }
        
        .step-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .step-description {
            color: #666;
            font-size: 0.9rem;
        }
        
        .step-status {
            margin-left: 15px;
        }
        
        .status-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
        }
        
        .status-icon.completed {
            background: #10b981;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .status-icon.pending {
            background: #e0e0e0;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        
        .alert-warning {
            background: #fffbeb;
            border: 1px solid #fed7aa;
            color: #d97706;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Ruxxen LPG Setup</h1>
            <p>Production System Configuration</p>
        </div>
        
        <div class="content">
            <?php if ($isConfigured): ?>
                <div class="alert alert-success">
                    <strong>✅ System Already Configured!</strong><br>
                    The system has been successfully set up and is ready for use.
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="/" class="btn">Go to Dashboard</a>
                </div>
                
            <?php else: ?>
                <!-- Progress Section -->
                <div style="margin-bottom: 30px;">
                    <h3>Setup Progress</h3>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= ($progress['completed_steps'] / $progress['total_steps']) * 100 ?>%"></div>
                    </div>
                    <p style="text-align: center; color: #666;">
                        <?= $progress['completed_steps'] ?>/<?= $progress['total_steps'] ?> steps completed
                    </p>
                </div>
                
                <!-- Setup Steps -->
                <div style="margin-bottom: 30px;">
                    <h3>Setup Steps</h3>
                    
                    <div class="step <?= $progress['database_migrated'] ? 'completed' : '' ?>">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <div class="step-title">Database Setup</div>
                            <div class="step-description">Initialize database tables and structure</div>
                        </div>
                        <div class="step-status">
                            <div class="status-icon <?= $progress['database_migrated'] ? 'completed' : 'pending' ?>">
                                <?= $progress['database_migrated'] ? '✓' : '' ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="step <?= $progress['admin_created'] ? 'completed' : '' ?>">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <div class="step-title">Admin Account</div>
                            <div class="step-description">Create default administrator account</div>
                        </div>
                        <div class="step-status">
                            <div class="status-icon <?= $progress['admin_created'] ? 'completed' : 'pending' ?>">
                                <?= $progress['admin_created'] ? '✓' : '' ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="step <?= $progress['company_configured'] ? 'completed' : '' ?>">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <div class="step-title">Company Settings</div>
                            <div class="step-description">Initialize company information and settings</div>
                        </div>
                        <div class="step-status">
                            <div class="status-icon <?= $progress['company_configured'] ? 'completed' : 'pending' ?>">
                                <?= $progress['company_configured'] ? '✓' : '' ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="step <?= $progress['system_configured'] ? 'completed' : '' ?>">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <div class="step-title">System Configuration</div>
                            <div class="step-description">Finalize system setup and optimization</div>
                        </div>
                        <div class="step-status">
                            <div class="status-icon <?= $progress['system_configured'] ? 'completed' : 'pending' ?>">
                                <?= $progress['system_configured'] ? '✓' : '' ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Setup Results -->
                <?php if (isset($setupSuccess)): ?>
                    <?php if ($setupSuccess): ?>
                        <div class="alert alert-success">
                            <strong>✅ <?= htmlspecialchars($setupMessage) ?></strong>
                        </div>
                        
                        <?php if (!empty($setupSteps)): ?>
                            <h4>Setup Results:</h4>
                            <?php foreach ($setupSteps as $step): ?>
                                <div class="alert <?= $step['status'] === 'success' ? 'alert-success' : ($step['status'] === 'error' ? 'alert-error' : 'alert-warning') ?>">
                                    <strong><?= htmlspecialchars($step['name']) ?>:</strong> <?= htmlspecialchars($step['message']) ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="/" class="btn">Go to Dashboard</a>
                        </div>
                        
                    <?php else: ?>
                        <div class="alert alert-error">
                            <strong>❌ <?= htmlspecialchars($setupMessage) ?></strong>
                        </div>
                        
                        <?php if (!empty($setupErrors)): ?>
                            <h4>Errors:</h4>
                            <?php foreach ($setupErrors as $error): ?>
                                <div class="alert alert-error">
                                    <?= htmlspecialchars($error) ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if (!empty($setupSteps)): ?>
                            <h4>Step Results:</h4>
                            <?php foreach ($setupSteps as $step): ?>
                                <div class="alert <?= $step['status'] === 'success' ? 'alert-success' : ($step['status'] === 'error' ? 'alert-error' : 'alert-warning') ?>">
                                    <strong><?= htmlspecialchars($step['name']) ?>:</strong> <?= htmlspecialchars($step['message']) ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Setup Form -->
                <?php if (!isset($setupSuccess) || !$setupSuccess): ?>
                    <form method="POST" id="setup-form">
                        <div style="text-align: center; margin: 30px 0;">
                            <button type="submit" name="run_setup" class="btn" id="setup-btn">
                                🚀 Start Setup
                            </button>
                            
                            <?php if (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false): ?>
                                <button type="button" class="btn btn-danger" onclick="resetSetup()">
                                    🔄 Reset Setup
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                    
                    <div class="loading" id="loading">
                        <div class="spinner"></div>
                        <p>Setting up your system...</p>
                        <p style="font-size: 0.9rem; color: #666;">Please wait, this may take a few moments.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>Ruxxen LPG Gas Plant Management System</p>
            <p>Version 1.0.0</p>
        </div>
    </div>
    
    <script>
        // Show loading when form is submitted
        document.getElementById('setup-form')?.addEventListener('submit', function() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('setup-btn').disabled = true;
            document.getElementById('setup-btn').textContent = 'Setting up...';
        });
        
        // Reset setup function (development only)
        function resetSetup() {
            if (confirm('Are you sure you want to reset the setup? This will clear all configuration data.')) {
                fetch('/setup/reset', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Setup reset successfully. Please refresh the page.');
                        location.reload();
                    } else {
                        alert('Failed to reset setup: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error resetting setup: ' + error.message);
                });
            }
        }
    </script>
</body>
</html>
