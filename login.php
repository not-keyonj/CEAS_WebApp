<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: ceas-dashboard.php');
    exit();
}

// Built-in admin accounts (plain-text passwords, hashed at compare time)
$admin_accounts = [
    'admin@tamcc.edu.gd' => ['password' => 'admin123', 'name' => 'Administrator',   'role' => 'System Administrator', 'campus' => 'tamcc_stgeorge'],
    'keyon@tamcc.edu.gd' => ['password' => 'keyon123', 'name' => 'Keyon Alexander', 'role' => 'System Administrator', 'campus' => 'tamcc_stgeorge'],
    'admin@sgu.edu'      => ['password' => 'sgu123',   'name' => 'SGU Administrator','role' => 'System Administrator', 'campus' => 'sgu'],
];

$users_file = 'registered_users.json';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password.';
    } else {
        $authenticated = false;

        // 1. Check built-in admin accounts (plain-text compare)
        if (isset($admin_accounts[$email]) && $admin_accounts[$email]['password'] === $password) {
            $acc = $admin_accounts[$email];
            $_SESSION['user'] = [
                'name'       => $acc['name'],
                'role'       => $acc['role'],
                'email'      => $email,
                'campus'     => $acc['campus'],
                'login_time' => date('Y-m-d H:i:s'),
            ];
            $authenticated = true;
        }

        // 2. Check registered users (password_hash / password_verify)
        if (!$authenticated && file_exists($users_file)) {
            $reg_users = json_decode(file_get_contents($users_file), true) ?: [];
            foreach ($reg_users as $u) {
                if (strtolower($u['email']) === $email) {
                    if (password_verify($password, $u['password'])) {
                        // Determine role based on user_type
                        if ($u['user_type'] === 'admin') {
                            $role = 'System Administrator';
                        } else {
                            $role = ucfirst($u['user_type']);
                        }
                        $_SESSION['user'] = [
                            'name'       => $u['full_name'],
                            'role'       => $role,
                            'email'      => $email,
                            'campus'     => $u['campus'] ?? 'tamcc_stgeorge',
                            'login_time' => date('Y-m-d H:i:s'),
                        ];
                        $authenticated = true;
                    }
                    break; // email found, stop searching
                }
            }
        }

        if ($authenticated) {
            header('Location: ceas-dashboard.php');
            exit();
        } else {
            $error_message = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>CEAS – Login</title>
    <link rel="manifest" href="manifest.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg: #060d1f;
            --surface: #0f1e38;
            --surface2: #1a2e50;
            --border: rgba(59,130,246,.18);
            --accent: #2563eb;
            --accent-bright: #3b82f6;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --danger: #ef4444;
            --success: #10b981;
            --yellow: #eab308;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow-x: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 20%, rgba(37,99,235,.15) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 80%, rgba(16,185,129,.08) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            animation: fadeUp .5s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Header */
        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-badge {
            width: 72px; height: 72px;
            margin: 0 auto 1.25rem;
            background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 32px rgba(29,78,216,.4);
            animation: glow 3s ease-in-out infinite;
        }

        @keyframes glow {
            0%, 100% { box-shadow: 0 0 32px rgba(29,78,216,.4); }
            50%       { box-shadow: 0 0 48px rgba(29,78,216,.7); }
        }

        .logo-badge svg { width: 36px; height: 36px; stroke: #fff; stroke-width: 2.5; fill: none; }

        .brand h1 {
            font-family: 'Syne', sans-serif;
            font-size: 2.25rem; font-weight: 800;
            letter-spacing: -.03em;
            background: linear-gradient(135deg, #60a5fa, #38bdf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand p { color: var(--muted); font-size: .9rem; margin-top: .25rem; }

        /* Card */
        .card {
            background: rgba(15,30,56,.7);
            backdrop-filter: blur(24px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 16px 48px rgba(0,0,0,.4);
        }

        /* Grenada school alert banner */
        .school-notice {
            background: rgba(37,99,235,.1);
            border: 1px solid rgba(37,99,235,.25);
            border-radius: 10px;
            padding: .75rem 1rem;
            font-size: .8125rem;
            color: #93c5fd;
            margin-bottom: 1.5rem;
            display: flex; gap: .5rem; align-items: flex-start;
        }
        .school-notice svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; fill: none; flex-shrink: 0; margin-top: 1px; }

        /* Error */
        .error-box {
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.3);
            color: #fca5a5;
            padding: .75rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.25rem;
            font-size: .875rem;
            display: flex; gap: .5rem; align-items: center;
            animation: shake .4s ease;
        }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }
        .error-box svg { width: 16px; height: 16px; stroke: currentColor; stroke-width: 2; fill: none; flex-shrink: 0; }

        /* Form */
        .field { margin-bottom: 1.25rem; }
        .field label { display: block; font-size: .8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: .4rem; }

        .input-wrap { position: relative; }
        .input-icon {
            position: absolute; left: .875rem; top: 50%; transform: translateY(-50%);
            width: 18px; height: 18px; stroke: var(--muted); stroke-width: 2; fill: none; pointer-events: none;
        }

        .field input {
            width: 100%; padding: .75rem .875rem .75rem 2.75rem;
            background: rgba(6,13,31,.8);
            border: 1px solid rgba(59,130,246,.2);
            border-radius: 10px;
            color: var(--text); font-size: .9375rem; font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }
        .field input:focus { outline: none; border-color: var(--accent-bright); box-shadow: 0 0 0 3px rgba(59,130,246,.12); }
        .field input::placeholder { color: #475569; }

        .row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
        .check-label { display: flex; align-items: center; gap: .5rem; font-size: .8125rem; color: var(--muted); cursor: pointer; }
        .check-label input[type=checkbox] { accent-color: var(--accent-bright); }
        .forgot { font-size: .8125rem; color: var(--accent-bright); text-decoration: none; }
        .forgot:hover { color: #93c5fd; }

        .btn-primary {
            width: 100%; padding: .875rem;
            background: linear-gradient(135deg, #1d4ed8, #2563eb);
            border: none; border-radius: 10px;
            color: #fff; font-size: .9375rem; font-weight: 600;
            font-family: 'Syne', sans-serif; letter-spacing: .01em;
            cursor: pointer; transition: all .25s;
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            box-shadow: 0 4px 16px rgba(29,78,216,.35);
        }
        .btn-primary svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2; fill: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(29,78,216,.5); }
        .btn-primary:active { transform: translateY(0); }

        .divider { display: flex; align-items: center; gap: 1rem; color: #334155; font-size: .8125rem; margin: 1.25rem 0; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(59,130,246,.1); }

        .register-link { text-align: center; font-size: .875rem; color: var(--muted); }
        .register-link a { color: var(--accent-bright); font-weight: 600; text-decoration: none; }
        .register-link a:hover { color: #93c5fd; text-decoration: underline; }

        /* Demo creds */
        .demo-box {
            background: rgba(6,13,31,.6);
            border: 1px solid rgba(59,130,246,.12);
            border-radius: 12px; padding: 1rem; margin-top: 1.25rem;
        }
        .demo-title { font-size: .75rem; font-weight: 700; color: #60a5fa; text-transform: uppercase; letter-spacing: .08em; margin-bottom: .75rem; }
        .demo-row { display: flex; justify-content: space-between; align-items: center; padding: .3rem 0; font-size: .8rem; }
        .demo-row:not(:last-child) { border-bottom: 1px solid rgba(59,130,246,.08); }
        .demo-label { color: #475569; }
        .demo-val { font-family: 'Courier New', monospace; color: #cbd5e1; background: rgba(15,30,56,.8); padding: .2rem .5rem; border-radius: 4px; font-size: .75rem; }

        footer { text-align: center; margin-top: 1.5rem; color: #334155; font-size: .8125rem; }
        footer a { color: #3b82f6; text-decoration: none; }

        /* Install prompt */
        .install-banner {
            display: none;
            background: rgba(16,185,129,.1);
            border: 1px solid rgba(16,185,129,.25);
            border-radius: 12px; padding: .875rem 1rem;
            margin-bottom: 1.25rem; align-items: center; gap: .75rem; font-size: .8125rem; color: #6ee7b7;
        }
        .install-banner.show { display: flex; }
        .install-banner button {
            margin-left: auto; padding: .4rem .875rem;
            background: rgba(16,185,129,.2); border: 1px solid rgba(16,185,129,.35);
            border-radius: 6px; color: #6ee7b7; font-size: .8rem; cursor: pointer;
            font-family: inherit; white-space: nowrap;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="brand">
        <div class="logo-badge">
            <svg viewBox="0 0 24 24">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <h1>CEAS</h1>
        <p>Campus Emergency Alert System • Grenada</p>
    </div>

    <div class="card">
        <div id="installBanner" class="install-banner">
            <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:currentColor;stroke-width:2;fill:none;flex-shrink:0"><path d="M12 2v10m0 0l-3-3m3 3l3-3M3 17v2a2 2 0 002 2h14a2 2 0 002-2v-2"/></svg>
            Install CEAS as an app for quick emergency access
            <button id="installBtn">Install</button>
        </div>

        <div class="school-notice">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            <span>Serving <strong>TAMCC, SGU</strong> &amp; other Grenada schools. Select your campus during registration.</span>
        </div>

        <?php if (!empty($error_message)): ?>
        <div class="error-box">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <form method="POST" autocomplete="on">
            <div class="field">
                <label for="email">Email Address</label>
                <div class="input-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <input type="email" id="email" name="email" placeholder="you@tamcc.edu.gd" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
                </div>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <svg class="input-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                </div>
            </div>
            <div class="row">
                <label class="check-label"><input type="checkbox" name="remember"> Remember me</label>
                <a href="forgot_password.php" class="forgot">Forgot password?</a>
            </div>
            <button type="submit" class="btn-primary">
                <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Sign In to CEAS
            </button>
        </form>

        <div class="divider">or</div>
        <div class="register-link">No account? <a href="signup.php">Register your campus account</a></div>

        <div class="demo-box">
            <div class="demo-title">Admin / Demo Accounts</div>
            <div class="demo-row">
                <span class="demo-label">TAMCC Admin</span>
                <span class="demo-val">admin@tamcc.edu.gd / admin123</span>
            </div>
            <div class="demo-row">
                <span class="demo-label">Keyon (Admin)</span>
                <span class="demo-val">keyon@tamcc.edu.gd / keyon123</span>
            </div>
            <div class="demo-row" style="margin-top:.5rem;padding-top:.5rem;border-top:1px solid rgba(59,130,246,.1)">
                <span class="demo-label" style="color:#6ee7b7">Your account:</span>
                <span class="demo-val" style="color:#6ee7b7">use your signup email &amp; password</span>
            </div>
        </div>
    </div>

    <footer>&copy; 2025 CEAS &mdash; <a href="#">Privacy</a> &bull; <a href="#">Terms</a></footer>
</div>

<script>
// PWA install prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    document.getElementById('installBanner').classList.add('show');
});

document.getElementById('installBtn')?.addEventListener('click', async () => {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    deferredPrompt = null;
    document.getElementById('installBanner').style.display = 'none';
});

// Register service worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}
</script>
</body>
</html>