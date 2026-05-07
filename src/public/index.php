<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Starter Kit</title>
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
            --purple: #a855f7;
            --pink: #ec4899;
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

        /* Aurora Background */
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
            background: var(--purple);
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
            background: var(--pink);
            top: 50%;
            left: 50%;
            animation-delay: -10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(50px, -30px) scale(1.1); }
            66% { transform: translate(-30px, 40px) scale(0.9); }
        }

        /* Noise overlay */
        .noise {
            position: fixed;
            inset: 0;
            z-index: 1;
            opacity: 0.03;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            pointer-events: none;
        }

        /* Content */
        .content {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1000px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Hero */
        .hero {
            text-align: center;
            margin-bottom: 8px;
        }

        .hero-badge {
            display: inline-block;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(10px);
            color: var(--accent);
            font-size: 0.7rem;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 100px;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero h1 {
            font-size: 2.4rem;
            font-weight: 800;
            letter-spacing: -1.5px;
            background: linear-gradient(135deg, #fff 0%, var(--accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }

        .hero p {
            font-size: 1rem;
            color: var(--text-dim);
            max-width: 500px;
            margin: 0 auto;
        }

        /* Glass Card Base */
        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .glass:hover {
            border-color: var(--glass-border-hover);
            box-shadow: 0 0 30px var(--accent-glow), inset 0 1px 0 rgba(255,255,255,0.05);
        }

        /* Stack Grid */
        .stack-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
        }

        .stack-card {
            padding: 16px 12px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .stack-card svg {
            width: 28px;
            height: 28px;
            color: var(--accent);
            opacity: 0.8;
        }

        .stack-name {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .stack-meta {
            font-size: 0.65rem;
            color: var(--text-dim);
        }

        /* Commands */
        .commands {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .commands-label {
            font-size: 0.75rem;
            color: var(--text-dim);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .cmd-tags {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .cmd-tag {
            background: rgba(56, 189, 248, 0.1);
            border: 1px solid rgba(56, 189, 248, 0.2);
            color: var(--accent);
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 0.8rem;
            padding: 5px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            user-select: all;
        }

        .cmd-tag:hover {
            background: rgba(56, 189, 248, 0.2);
            border-color: var(--accent);
            box-shadow: 0 0 12px rgba(56, 189, 248, 0.3);
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .info-card {
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-dim);
        }

        .info-value {
            font-family: 'SF Mono', Monaco, monospace;
            font-size: 0.9rem;
            color: var(--accent);
            font-weight: 600;
        }

        /* Footer */
        .footer {
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-dim);
            margin-top: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stack-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .hero h1 {
                font-size: 1.8rem;
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
        <!-- Hero -->
        <div class="hero">
            <div class="hero-badge">PHP Starter Kit</div>
            <h1>Modern PHP Development Stack</h1>
            <p>Ready-to-use environment with Docker, FrankenPHP, Vite and everything you need.</p>
        </div>

        <!-- Stack -->
        <div class="stack-grid">
            <div class="glass stack-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 18l6-6-6-6"/>
                    <path d="M8 6l-6 6 6 6"/>
                </svg>
                <div class="stack-name">PHP</div>
                <div class="stack-meta">8.5+</div>
            </div>
            <div class="glass stack-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
                </svg>
                <div class="stack-name">FrankenPHP</div>
                <div class="stack-meta">Caddy</div>
            </div>
            <div class="glass stack-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/>
                    <rect x="2" y="14" width="20" height="8" rx="2" ry="2"/>
                    <line x1="6" y1="6" x2="6.01" y2="6"/>
                    <line x1="6" y1="18" x2="6.01" y2="18"/>
                </svg>
                <div class="stack-name">Docker</div>
                <div class="stack-meta">Compose</div>
            </div>
            <div class="glass stack-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="16 18 22 12 16 6"/>
                    <polyline points="8 6 2 12 8 18"/>
                </svg>
                <div class="stack-name">Vite</div>
                <div class="stack-meta">Build</div>
            </div>
            <div class="glass stack-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="9" ry="3"/>
                    <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                    <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                </svg>
                <div class="stack-name">Database</div>
                <div class="stack-meta">PostgreSQL</div>
            </div>
            <div class="glass stack-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v4"/>
                    <path d="M12 18v4"/>
                    <path d="M4.93 4.93l2.83 2.83"/>
                    <path d="M16.24 16.24l2.83 2.83"/>
                    <path d="M2 12h4"/>
                    <path d="M18 12h4"/>
                    <path d="M4.93 19.07l2.83-2.83"/>
                    <path d="M16.24 7.76l2.83-2.83"/>
                </svg>
                <div class="stack-name">Redis</div>
                <div class="stack-meta">Cache</div>
            </div>
        </div>

        <!-- Commands -->
        <div class="glass commands">
            <span class="commands-label">Quick Commands</span>
            <div class="cmd-tags">
                <code class="cmd-tag">make init</code>
                <code class="cmd-tag">make up</code>
                <code class="cmd-tag">make php</code>
                <code class="cmd-tag">make down</code>
                <code class="cmd-tag">make logs</code>
            </div>
        </div>

        <!-- System Info -->
        <div class="info-grid">
            <div class="glass info-card">
                <div class="info-label">PHP Version</div>
                <div class="info-value"><?php echo PHP_VERSION; ?></div>
            </div>
            <div class="glass info-card">
                <div class="info-label">Server</div>
                <div class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
            </div>
            <div class="glass info-card">
                <div class="info-label">Environment</div>
                <div class="info-value"><?php echo file_exists('/.dockerenv') ? 'Docker' : 'Local'; ?></div>
            </div>
            <div class="glass info-card">
                <div class="info-label">Server Time</div>
                <div class="info-value"><?php echo date('H:i'); ?></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            PHP Starter Kit &copy; <?php echo date('Y'); ?>
        </div>
    </div>

</body>
</html>
