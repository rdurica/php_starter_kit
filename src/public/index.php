<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$action = $_GET['action'] ?? 'view';

switch ($action) {
    case 'install':
        handleInstall();
        break;
    case 'delete-setup':
        handleDeleteSetup();
        break;
    case 'restart-frankenphp':
        handleRestartFrankenphp();
        break;
    default:
        showUI();
        break;
}

function validateCsrf() {
    $token = $_GET['token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo "data: error|Invalid CSRF token\n\n";
        exit;
    }
}

function handleInstall() {
    validateCsrf();
    
    $framework = $_GET['framework'] ?? '';
    $allowed = ['symfony', 'laravel', 'nette'];
    
    if (!in_array($framework, $allowed, true)) {
        http_response_code(400);
        echo "data: error|Invalid framework\n\n";
        exit;
    }
    
    $lockFile = '/tmp/setup.lock';
    if (file_exists($lockFile)) {
        echo "data: error|Installation already in progress\n\n";
        exit;
    }
    
    file_put_contents($lockFile, getmypid());
    
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
    
    ob_implicit_flush(true);
    if (ob_get_level()) ob_end_flush();
    
    $projectDir = '/app/src';
    $tmpDir = '/tmp/framework-install-' . uniqid();
    
    $packages = [
        'symfony' => 'symfony/skeleton',
        'laravel' => 'laravel/laravel',
        'nette' => 'nette/web-project'
    ];
    
    $package = $packages[$framework];
    
    sendEvent('output', "Creating temporary directory...");
    mkdir($tmpDir, 0777, true);
    
    sendEvent('output', "Installing {$framework} (this may take a few minutes)...");
    sendEvent('progress', '5');
    
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    
    $cmd = "cd {$tmpDir} && COMPOSER_HOME=/tmp/composer composer create-project {$package} . --no-interaction 2>&1";
    $process = proc_open($cmd, $descriptors, $pipes);
    
    if (!is_resource($process)) {
        sendEvent('error', 'Failed to start composer');
        cleanup($lockFile, $tmpDir);
        exit;
    }
    
    $progress = 5;
    
    while (!feof($pipes[1])) {
        $line = fgets($pipes[1]);
        if ($line === false) break;
        
        $line = trim($line);
        if (empty($line)) continue;
        
        sendEvent('output', $line);
        
        if (strpos($line, 'Installing') !== false || strpos($line, 'Downloading') !== false) {
            $progress = min(95, $progress + 2);
            sendEvent('progress', (string)$progress);
        } elseif (strpos($line, 'Creating project') !== false) {
            $progress = min(95, $progress + 5);
            sendEvent('progress', (string)$progress);
        }
    }
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $exitCode = proc_close($process);
    
    if ($exitCode !== 0) {
        sendEvent('error', 'Installation failed with exit code ' . $exitCode);
        cleanup($lockFile, $tmpDir);
        exit;
    }
    
    sendEvent('progress', '95');
    sendEvent('output', "Installation completed. Moving files...");
    
    if (file_exists($projectDir . '/public/index.php')) {
        copy($projectDir . '/public/index.php', $projectDir . '/public/setup.php');
        sendEvent('output', "Landing page backed up to /setup.php");
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $destPath = $projectDir . '/' . substr($item->getPathname(), strlen($tmpDir) + 1);
        
        if ($item->isDir()) {
            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }
        } else {
            copy($item->getPathname(), $destPath);
        }
    }
    
    sendEvent('progress', '98');
    sendEvent('output', "Files moved successfully.");
    
    sendEvent('output', "Restarting FrankenPHP...");
    $restartOutput = shell_exec('killall -USR2 frankenphp 2>&1 || pkill -USR2 frankenphp 2>&1');
    
    if ($restartOutput !== null && strlen($restartOutput) > 0) {
        sendEvent('output', "FrankenPHP restart signal sent.");
        sendEvent('restart', 'success');
    } else {
        sendEvent('output', "Could not auto-restart FrankenPHP. Please run: make restart");
        sendEvent('restart', 'needed');
    }
    
    sendEvent('progress', '100');
    sendEvent('complete', $framework);
    
    cleanup($lockFile, $tmpDir);
    exit;
}

function sendEvent($type, $data) {
    echo "data: {$type}|" . json_encode($data) . "\n\n";
    flush();
}

function cleanup($lockFile, $tmpDir) {
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
    if (is_dir($tmpDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($tmpDir);
    }
}

function handleDeleteSetup() {
    validateCsrf();
    header('Content-Type: application/json');
    
    $setupFile = '/app/src/public/setup.php';
    if (file_exists($setupFile)) {
        if (unlink($setupFile)) {
            echo json_encode(['success' => true, 'message' => 'setup.php deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete setup.php']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'setup.php already deleted']);
    }
    exit;
}

function handleRestartFrankenphp() {
    validateCsrf();
    header('Content-Type: application/json');
    
    $output = shell_exec('killall -USR2 frankenphp 2>&1 || pkill -USR2 frankenphp 2>&1');
    
    if ($output !== null && strlen($output) > 0) {
        echo json_encode(['success' => true, 'message' => 'FrankenPHP restart signal sent']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to restart FrankenPHP']);
    }
    exit;
}

function showUI() {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Starter Kit — Setup Wizard</title>
    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg: #030712;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-border-hover: rgba(255, 255, 255, 0.2);
            --text: #f8fafc;
            --text-dim: #94a3b8;
            --accent: #38bdf8;
            --accent-glow: rgba(56, 189, 248, 0.4);
            --success: #22c55e;
            --error: #ef4444;
            --warning: #f59e0b;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .aurora {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .aurora-blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.35;
            animation: float 20s infinite ease-in-out;
        }

        .aurora-blob:nth-child(1) {
            width: 600px;
            height: 600px;
            background: #a855f7;
            top: -200px;
            left: -100px;
            animation-delay: 0s;
        }

        .aurora-blob:nth-child(2) {
            width: 500px;
            height: 500px;
            background: var(--accent);
            bottom: -150px;
            right: -100px;
            animation-delay: -5s;
        }

        .aurora-blob:nth-child(3) {
            width: 400px;
            height: 400px;
            background: #ec4899;
            top: 50%;
            left: 50%;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(50px, -30px) scale(1.1); }
            66% { transform: translate(-30px, 40px) scale(0.9); }
        }

        .noise {
            position: fixed;
            inset: 0;
            z-index: 1;
            opacity: 0.03;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            pointer-events: none;
        }

        .content {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1200px;
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 32px;
        }

        .hero {
            text-align: center;
        }

        .hero-badge {
            display: inline-block;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(10px);
            color: var(--accent);
            font-size: 0.85rem;
            font-weight: 700;
            padding: 6px 18px;
            border-radius: 100px;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            letter-spacing: -2px;
            background: linear-gradient(135deg, #fff 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
        }

        .hero p {
            font-size: 1.3rem;
            color: var(--text-dim);
            max-width: 600px;
            margin: 0 auto;
        }

        .framework-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
            width: 100%;
            max-width: 1000px;
        }

        .framework-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 48px 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .framework-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 20px;
            padding: 1.5px;
            background: linear-gradient(135deg, var(--card-gradient-1), var(--card-gradient-2));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .framework-card:hover::before {
            opacity: 1;
        }

        .framework-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .framework-card.symfony {
            --card-gradient-1: #000000;
            --card-gradient-2: #fbbf24;
        }

        .framework-card.laravel {
            --card-gradient-1: #ef4444;
            --card-gradient-2: #f97316;
        }

        .framework-card.nette {
            --card-gradient-1: #3b82f6;
            --card-gradient-2: #06b6d4;
        }

        .framework-logo {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            font-size: 32px;
            font-weight: 700;
        }

        .symfony .framework-logo { color: #fbbf24; }
        .laravel .framework-logo { color: #ef4444; }
        .nette .framework-logo { color: #3b82f6; }

        .framework-name {
            font-size: 1.7rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .framework-label {
            font-size: 0.9rem;
            color: var(--text-dim);
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .framework-desc {
            font-size: 1.05rem;
            color: var(--text-dim);
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .btn-install {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 36px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            color: white;
            position: relative;
            z-index: 1;
        }

        .symfony .btn-install { background: linear-gradient(135deg, #000000, #b45309); }
        .laravel .btn-install { background: linear-gradient(135deg, #dc2626, #ea580c); }
        .nette .btn-install { background: linear-gradient(135deg, #2563eb, #0891b2); }

        .btn-install:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }

        /* Server Stats */
        .stats-section {
            width: 100%;
            max-width: 900px;
        }

        .stats-title {
            font-size: 0.85rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 14px;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }

        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 24px 18px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            border-color: var(--glass-border-hover);
        }

        .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-dim);
            margin-bottom: 8px;
        }

        .stat-value {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 1.15rem;
            color: var(--accent);
            font-weight: 600;
        }

        /* Modals */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(3, 7, 18, 0.85);
            backdrop-filter: blur(10px);
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: linear-gradient(135deg, #0f172a, #1e293b);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 48px;
            max-width: 560px;
            width: 100%;
            text-align: center;
            position: relative;
            animation: modalIn 0.3s ease;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-title {
            font-size: 1.7rem;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .modal-text {
            color: var(--text-dim);
            margin-bottom: 28px;
            line-height: 1.6;
            font-size: 1rem;
        }

        .modal-text code {
            background: var(--glass-bg);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 0.85em;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--accent);
            color: var(--bg);
        }

        .btn-primary:hover {
            background: var(--accent-glow);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--glass-bg);
            color: var(--text);
            border: 1px solid var(--glass-border);
        }

        .btn-secondary:hover {
            background: var(--glass-border);
        }

        .progress-modal {
            max-width: 680px;
        }

        .progress-bar-container {
            width: 100%;
            height: 10px;
            background: var(--glass-bg);
            border-radius: 100px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), #a855f7);
            border-radius: 100px;
            width: 0%;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 0.9rem;
            color: var(--text-dim);
            margin-bottom: 16px;
        }

        .terminal {
            background: #020617;
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 18px;
            height: 220px;
            overflow-y: auto;
            text-align: left;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
            font-size: 0.8rem;
            line-height: 1.5;
            color: var(--text-dim);
        }

        .terminal-line {
            margin-bottom: 2px;
            word-break: break-all;
        }

        .success-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, var(--success), #16a34a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .success-modal .modal-title {
            color: var(--success);
        }

        .setup-info {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            padding: 10px;
            margin: 14px 0;
            font-size: 0.8rem;
            color: var(--text-dim);
        }

        .setup-info code {
            color: var(--accent);
            font-family: monospace;
        }

        .restart-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 10px;
            padding: 10px;
            margin: 14px 0;
            font-size: 0.8rem;
            color: var(--warning);
            display: none;
        }

        .restart-warning.active {
            display: block;
        }

        .footer {
            font-size: 0.7rem;
            color: var(--text-dim);
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .framework-grid {
                grid-template-columns: 1fr;
                max-width: 320px;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .hero h1 {
                font-size: 1.7rem;
            }
            .modal {
                padding: 24px;
            }
        }
    </style>
</head>
<body>

    <div class="aurora">
        <div class="aurora-blob"></div>
        <div class="aurora-blob"></div>
        <div class="aurora-blob"></div>
    </div>
    <div class="noise"></div>

    <div class="content">
        <div class="hero">
            <div class="hero-badge">PHP Starter Kit</div>
            <h1>Choose Your Framework</h1>
            <p>Select a framework to install and set up your project automatically.</p>
        </div>

        <div class="framework-grid">
            <div class="framework-card symfony" data-framework="symfony">
                <div class="framework-logo">Sf</div>
                <div class="framework-name">Symfony</div>
                <div class="framework-label">Enterprise</div>
                <div class="framework-desc">Robust architecture with reusable components and excellent documentation.</div>
                <button class="btn-install">Install Symfony</button>
            </div>

            <div class="framework-card laravel" data-framework="laravel">
                <div class="framework-logo">Lv</div>
                <div class="framework-name">Laravel</div>
                <div class="framework-label">Elegant</div>
                <div class="framework-desc">Expressive syntax, rich ecosystem, and rapid development experience.</div>
                <button class="btn-install">Install Laravel</button>
            </div>

            <div class="framework-card nette" data-framework="nette">
                <div class="framework-logo">Nt</div>
                <div class="framework-name">Nette</div>
                <div class="framework-label">Smart & Secure</div>
                <div class="framework-desc">Czech-made framework focused on security and developer happiness.</div>
                <button class="btn-install">Install Nette</button>
            </div>
        </div>

        <div class="stats-section">
            <div class="stats-title">Server Statistics</div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">PHP Version</div>
                    <div class="stat-value"><?php echo PHP_VERSION; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Server</div>
                    <div class="stat-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Environment</div>
                    <div class="stat-value"><?php echo file_exists('/.dockerenv') ? 'Docker' : 'Local'; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Server Time</div>
                    <div class="stat-value"><?php echo date('H:i'); ?></div>
                </div>
            </div>
        </div>

        <div class="footer">
            PHP Starter Kit &copy; <?php echo date('Y'); ?>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal">
            <h3 class="modal-title" id="confirmTitle">Install Framework?</h3>
            <p class="modal-text" id="confirmText">
                This will download and install the framework into your project.<br>
                Your current landing page will be preserved at <code>/setup.php</code>.
            </p>
            <div class="modal-buttons">
                <button class="btn btn-secondary" onclick="closeModal('confirmModal')">Cancel</button>
                <button class="btn btn-primary" id="confirmInstallBtn">Install</button>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div class="modal-overlay" id="progressModal">
        <div class="modal progress-modal">
            <h3 class="modal-title">Installing...</h3>
            <div class="progress-bar-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <div class="progress-text" id="progressText">Preparing installation...</div>
            <div class="terminal" id="terminal"></div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="successModal">
        <div class="modal success-modal">
            <div class="success-icon">✓</div>
            <h3 class="modal-title" id="successTitle">Installation Complete!</h3>
            <p class="modal-text" id="successText">Your framework has been installed successfully.</p>
            <div class="setup-info">
                Setup page preserved at <code>/setup.php</code> — delete it when you no longer need it.
            </div>
            <div class="restart-warning" id="restartWarning">
                ⚠️ FrankenPHP needs to be restarted. Please run: <code>make restart</code>
            </div>
            <div class="modal-buttons">
                <button class="btn btn-secondary" id="deleteSetupBtn">Delete setup.php</button>
                <button class="btn btn-primary" id="restartFrankenBtn" style="display:none;">Restart FrankenPHP</button>
                <a href="/" class="btn btn-primary" id="goToAppBtn">Go to Application</a>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
        let selectedFramework = null;
        let eventSource = null;

        document.querySelectorAll('.btn-install').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                selectedFramework = btn.closest('.framework-card').dataset.framework;
                const names = { symfony: 'Symfony', laravel: 'Laravel', nette: 'Nette' };
                document.getElementById('confirmTitle').textContent = `Install ${names[selectedFramework]}?`;
                openModal('confirmModal');
            });
        });

        document.getElementById('confirmInstallBtn').addEventListener('click', () => {
            closeModal('confirmModal');
            startInstallation(selectedFramework);
        });

        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function startInstallation(framework) {
            openModal('progressModal');
            document.getElementById('progressBar').style.width = '0%';
            document.getElementById('terminal').innerHTML = '';
            document.getElementById('progressText').textContent = 'Starting installation...';

            const terminal = document.getElementById('terminal');
            
            eventSource = new EventSource(`index.php?action=install&framework=${framework}&token=${csrfToken}`);

            eventSource.onmessage = (e) => {
                const separatorIndex = e.data.indexOf('|');
                const type = e.data.substring(0, separatorIndex);
                let data = e.data.substring(separatorIndex + 1);
                
                if (data.startsWith('"')) {
                    try {
                        data = JSON.parse(data);
                    } catch (err) {}
                }

                switch (type) {
                    case 'output':
                        const line = document.createElement('div');
                        line.className = 'terminal-line';
                        line.textContent = data;
                        terminal.appendChild(line);
                        terminal.scrollTop = terminal.scrollHeight;
                        break;
                    
                    case 'progress':
                        document.getElementById('progressBar').style.width = data + '%';
                        document.getElementById('progressText').textContent = `Installing... ${data}%`;
                        break;
                    
                    case 'restart':
                        if (data === 'needed') {
                            document.getElementById('restartWarning').classList.add('active');
                            document.getElementById('restartFrankenBtn').style.display = 'inline-flex';
                        }
                        break;
                    
                    case 'complete':
                        eventSource.close();
                        closeModal('progressModal');
                        const names = { symfony: 'Symfony', laravel: 'Laravel', nette: 'Nette' };
                        document.getElementById('successTitle').textContent = `${names[data]} Installed!`;
                        document.getElementById('successText').textContent = `Your ${names[data]} application is ready.`;
                        openModal('successModal');
                        break;
                    
                    case 'error':
                        eventSource.close();
                        closeModal('progressModal');
                        alert('Installation failed: ' + data);
                        break;
                }
            };

            eventSource.onerror = () => {
                eventSource.close();
                closeModal('progressModal');
                alert('Connection lost. Please check if the installation is still running.');
            };
        }

        document.getElementById('deleteSetupBtn').addEventListener('click', async () => {
            try {
                const response = await fetch(`index.php?action=delete-setup&token=${csrfToken}`, {
                    method: 'POST'
                });
                const data = await response.json();
                if (data.success) {
                    document.getElementById('deleteSetupBtn').style.display = 'none';
                    document.querySelector('.setup-info').textContent = 'Setup page deleted.';
                } else {
                    alert('Failed to delete setup.php: ' + data.message);
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        });

        document.getElementById('restartFrankenBtn').addEventListener('click', async () => {
            try {
                const response = await fetch(`index.php?action=restart-frankenphp&token=${csrfToken}`, {
                    method: 'POST'
                });
                const data = await response.json();
                if (data.success) {
                    document.getElementById('restartWarning').classList.remove('active');
                    document.getElementById('restartFrankenBtn').style.display = 'none';
                    alert('FrankenPHP restarted successfully!');
                } else {
                    alert('Failed to restart: ' + data.message);
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        });
    </script>

</body>
</html>
<?php
}
?>
