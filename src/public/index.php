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
    
    // Nette fix: www/ → public/
    if (is_dir($projectDir . '/www')) {
        sendEvent('output', "Detected Nette www/ structure. Moving to public/...");
        
        if (!is_dir($projectDir . '/public')) {
            mkdir($projectDir . '/public', 0755, true);
        }
        
        $wwwIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectDir . '/www', RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($wwwIterator as $item) {
            $relPath = substr($item->getPathname(), strlen($projectDir . '/www/'));
            $destPath = $projectDir . '/public/' . $relPath;
            
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                rename($item->getPathname(), $destPath);
            }
        }
        
        $rmIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectDir . '/www', RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rmIterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($projectDir . '/www');
        
        sendEvent('output', "Nette www/ → public/ move completed.");
    }
    
    sendEvent('progress', '98');
    sendEvent('output', "Files moved successfully.");
    
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
            --card-gradient-1: #ffffff;
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
        }

        .framework-logo svg {
            width: 40px;
            height: 40px;
            fill: currentColor;
        }

        .symfony .framework-logo { color: #ffffff; }
        .laravel .framework-logo { color: #FF2D20; }
        .nette .framework-logo { color: #3484D2; }

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

        .symfony .btn-install { background: linear-gradient(135deg, #d97706, #f59e0b); }
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
                <div class="framework-logo symfony-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 257"><circle cx="128" cy="128.827" r="128" fill="currentColor"/><path fill="var(--bg)" d="M183.706 48.124c-12.986.453-24.32 7.61-32.757 17.51c-9.342 10.855-15.557 23.73-20.035 36.872c-8.01-6.565-14.19-15.064-27.041-18.77c-9.933-2.852-20.366-1.674-29.96 5.474c-4.545 3.395-7.676 8.527-9.165 13.351c-3.855 12.537 4.053 23.694 7.645 27.7l7.853 8.416c1.619 1.65 5.518 5.955 3.612 12.127c-2.06 6.71-10.15 11.055-18.448 8.495c-3.706-1.13-9.03-3.891-7.838-7.779c.493-1.59 1.631-2.78 2.241-4.155c.56-1.181.827-2.067.997-2.587c1.516-4.95-.555-11.39-5.857-13.025c-4.946-1.516-10.007-.315-11.969 6.054c-2.225 7.235 1.237 20.366 19.783 26.084c21.729 6.676 40.1-5.155 42.717-20.586c1.642-9.665-2.722-16.845-10.717-26.08l-6.514-7.204c-3.946-3.942-5.301-10.661-1.217-15.825c3.446-4.356 8.354-6.215 16.392-4.029c11.733 3.186 16.963 11.327 25.69 17.893c-3.603 11.819-5.958 23.682-8.09 34.32l-1.299 7.931c-6.238 32.721-11 50.688-23.375 61.003c-2.493 1.773-6.057 4.427-11.429 4.612c-2.816.087-3.726-1.85-3.765-2.694c-.067-1.977 1.599-2.883 2.706-3.773c1.654-.902 4.155-2.398 3.985-7.191c-.18-5.664-4.872-10.575-11.654-10.35c-5.08.173-12.823 4.954-12.532 13.705c.303 9.039 8.728 15.813 21.43 15.384c6.79-.233 21.952-2.997 36.895-20.76c17.392-20.362 22.256-43.705 25.915-60.79l4.084-22.556c2.269.272 4.695.453 7.334.516c21.661.457 32.496-10.763 32.657-18.924c.107-4.939-3.241-9.799-7.928-9.689c-3.355.095-7.57 2.328-8.582 6.968c-.988 4.552 6.893 8.66.733 12.65c-4.376 2.832-12.221 4.828-23.269 3.206l2.009-11.103c4.1-21.055 9.157-46.954 28.341-47.584c1.398-.071 6.514.063 6.633 3.446c.035 1.13-.245 1.418-1.568 4.005c-1.347 2.017-1.855 3.734-1.792 5.707c.185 5.376 4.273 8.909 10.185 8.696c7.916-.256 10.193-7.963 10.063-11.921c-.32-9.3-10.122-15.175-23.1-14.75"/></svg>
                </div>
                <div class="framework-name">Symfony</div>
                <div class="framework-label">Enterprise</div>
                <div class="framework-desc">Robust architecture with reusable components and excellent documentation.</div>
                <button class="btn-install">Install Symfony</button>
            </div>

            <div class="framework-card laravel" data-framework="laravel">
                <div class="framework-logo">
                    <svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>Laravel</title><path d="M23.642 5.43a.364.364 0 01.014.1v5.149c0 .135-.073.26-.189.326l-4.323 2.49v4.934a.378.378 0 01-.188.326L9.93 23.949a.316.316 0 01-.066.027c-.008.002-.016.008-.024.01a.348.348 0 01-.192 0c-.011-.002-.02-.008-.03-.012-.02-.008-.042-.014-.062-.025L.533 18.755a.376.376 0 01-.189-.326V2.974c0-.033.005-.066.014-.098.003-.012.01-.02.014-.032a.369.369 0 01.023-.058c.004-.013.015-.022.023-.033l.033-.045c.012-.01.025-.018.037-.027.014-.012.027-.024.041-.034H.53L5.043.05a.375.375 0 01.375 0L9.93 2.647h.002c.015.01.027.021.04.033l.038.027c.013.014.02.03.033.045.008.011.02.021.025.033.01.02.017.038.024.058.003.011.01.021.013.032.01.031.014.064.014.098v9.652l3.76-2.164V5.527c0-.033.004-.066.013-.098.003-.01.01-.02.013-.032a.487.487 0 01.024-.059c.007-.012.018-.02.025-.033.012-.015.021-.03.033-.043.012-.012.025-.02.037-.028.014-.01.026-.023.041-.032h.001l4.513-2.598a.375.375 0 01.375 0l4.513 2.598c.016.01.027.021.042.031.012.01.025.018.036.028.013.014.022.03.034.044.008.012.019.021.024.033.011.02.018.04.024.06.006.01.012.021.015.032zm-.74 5.032V6.179l-1.578.908-2.182 1.256v4.283zm-4.51 7.75v-4.287l-2.147 1.225-6.126 3.498v4.325zM1.093 3.624v14.588l8.273 4.761v-4.325l-4.322-2.445-.002-.003H5.04c-.014-.01-.025-.021-.04-.031-.011-.01-.024-.018-.035-.027l-.001-.002c-.013-.012-.021-.025-.031-.04-.01-.011-.021-.022-.028-.036h-.002c-.008-.014-.013-.031-.02-.047-.006-.016-.014-.027-.018-.043a.49.49 0 01-.008-.057c-.002-.014-.006-.027-.006-.041V5.789l-2.18-1.257zM5.23.81L1.47 2.974l3.76 2.164 3.758-2.164zm1.956 13.505l2.182-1.256V3.624l-1.58.91-2.182 1.255v9.435zm11.581-10.95l-3.76 2.163 3.76 2.163 3.759-2.164zm-.376 4.978L16.21 7.087 14.63 6.18v4.283l2.182 1.256 1.58.908zm-8.65 9.654l5.514-3.148 2.756-1.572-3.757-2.163-4.323 2.489-3.941 2.27z"/></svg>
                </div>
                <div class="framework-name">Laravel</div>
                <div class="framework-label">Elegant</div>
                <div class="framework-desc">Expressive syntax, rich ecosystem, and rapid development experience.</div>
                <button class="btn-install">Install Laravel</button>
            </div>

            <div class="framework-card nette" data-framework="nette">
                <div class="framework-logo">
                    <svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><title>Nette</title><path d="M6.244 14.334c-.341.243-.558.39-.65.443-.488.29-.934.437-1.338.437-.226 0-.446-.053-.663-.155a1.17 1.17 0 0 1-.486-.403.988.988 0 0 1-.162-.556c0-.292.099-.663.296-1.113.282-.658.433-1.01.452-1.057a.497.497 0 0 1-.015-.127 2.511 2.511 0 0 0-.268.127 7.1 7.1 0 0 0-.774.578 13.77 13.77 0 0 0-.691.676 6.005 6.005 0 0 0-.085 1.001c0 .253.015.507.043.761l-1.705.268A6.198 6.198 0 0 1 0 13.706c0-.292.028-.588.085-.889.056-.3.16-.638.309-1.014.104-.263.249-.592.437-.987l1.959-.324c-.122.301-.211.526-.267.677a9.26 9.26 0 0 0-.254.691c.47-.433.94-.767 1.409-1.001.376-.188.714-.282 1.015-.282.17 0 .343.032.522.098.178.066.307.17.387.311.08.141.12.309.12.507 0 .282-.08.629-.24 1.043-.188.47-.371.939-.549 1.409 0 .066.024.106.07.12a.49.49 0 0 0 .141.02c.189 0 .469-.098.841-.294a1.74 1.74 0 0 1-.052-.424c0-.226.032-.441.098-.648.066-.207.166-.423.297-.648.234-.386.564-.714.986-.987a3.45 3.45 0 0 1 1.339-.521c.17-.019.3-.029.395-.029.31 0 .587.052.831.156.244.103.446.272.606.507.094.15.141.301.141.45 0 .236-.117.466-.352.691-.169.16-.397.311-.684.451a6.777 6.777 0 0 1-.853.352c-.206.066-.498.147-.873.24.122.254.296.459.522.614.225.154.464.232.718.232.386 0 .771-.099 1.156-.296.018-.01.312-.195.883-.557a4.035 4.035 0 0 1 .096-.641l.047-.214 2.035-.121-.151.525a1.982 1.982 0 0 0-.092.529c0 .226.045.383.135.471.089.09.217.135.387.135.244 0 .563-.103.958-.31l.454-.274c.003-.075.009-.156.018-.241.014-.135.043-.303.084-.5l.048-.213 2.034-.122c-.048.17-.098.345-.151.525-.06.211-.092.388-.092.529 0 .226.045.383.135.471.089.09.218.135.387.135.245 0 .565-.103.959-.31l.294-.178a1.505 1.505 0 0 1-.013-.203c0-.226.034-.441.099-.648.066-.207.165-.423.296-.648.234-.386.564-.714.986-.987.424-.272.87-.447 1.339-.521.17-.019.302-.029.396-.029.309 0 .586.052.831.156.243.103.446.272.605.507.094.15.141.301.141.45 0 .236-.117.466-.352.691-.168.16-.396.311-.683.451a6.902 6.902 0 0 1-.853.352c-.207.066-.498.147-.874.24.122.254.296.459.522.614.226.154.465.232.718.232.386 0 .771-.099 1.156-.296.019-.01.338-.211.958-.606v.634c-.056.038-.281.198-.675.479a4.575 4.575 0 0 1-.72.437c-.47.207-.987.31-1.55.31-.375 0-.709-.045-1.001-.133a2.078 2.078 0 0 1-.803-.473 1.58 1.58 0 0 1-.357-.456c-.414.3-.732.513-.954.64-.497.281-.949.422-1.352.422-.227 0-.41-.014-.62-.113-.358-.15-.607-.345-.748-.584a1.504 1.504 0 0 1-.158-.397c-.435.316-.768.54-.997.672-.498.281-.949.422-1.353.422-.227 0-.41-.014-.62-.113-.358-.15-.606-.345-.748-.584a1.505 1.505 0 0 1-.177-.493c-.099.067-.307.216-.625.443a4.667 4.667 0 0 1-.719.437c-.47.207-.987.31-1.55.31-.377 0-.71-.045-1.001-.133a2.089 2.089 0 0 1-.804-.473 1.66 1.66 0 0 1-.224-.245zm2.832-2.574a.786.786 0 0 0 .013-.169c0-.244-.102-.366-.309-.366a.757.757 0 0 0-.155.028c-.282.066-.503.245-.663.536a1.885 1.885 0 0 0-.239.915c.395-.102.681-.206.859-.309.292-.16.456-.371.494-.635zm12.782 0a.715.715 0 0 0 .014-.169c0-.244-.103-.366-.31-.366a.768.768 0 0 0-.155.028c-.281.066-.503.245-.662.536-.16.291-.24.597-.24.915.395-.102.682-.206.86-.309.291-.16.455-.371.493-.635zm-10.838.043l.283-1.113.578-.028.549-1.509 2.085-.366-.591 1.776.944-.043-.253 1.057c-1.198.082-2.395.155-3.595.226zm3.877 0l.281-1.113.578-.028.549-1.509 2.086-.366-.592 1.776.944-.043-.253 1.057c-1.201.082-2.408.156-3.593.226z"/></svg>
                </div>
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
            <div class="modal-buttons">
                <button class="btn btn-secondary" id="deleteSetupBtn">Delete setup.php</button>
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

    </script>

</body>
</html>
<?php
}
?>
