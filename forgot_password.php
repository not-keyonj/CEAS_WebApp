<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: ceas-dashboard.php');
    exit();
}

$users_file = 'registered_users.json';

// ── Built-in admin emails (cannot be reset via this page) ─────────────
$admin_emails = ['admin@tamcc.edu.gd', 'keyon@tamcc.edu.gd', 'admin@sgu.edu'];

$step          = 'find';    // find | reset | done
$error_message = '';
$success_message = '';
$found_email   = '';

// ── STEP 1 – Locate account by email ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['find_account'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (in_array($email, $admin_emails)) {
        $error_message = 'Admin accounts cannot be reset here. Please contact the system administrator.';
    } else {
        $users    = file_exists($users_file) ? (json_decode(file_get_contents($users_file), true) ?: []) : [];
        $exists   = false;
        foreach ($users as $u) {
            if (strtolower($u['email']) === $email) { $exists = true; break; }
        }

        if (!$exists) {
            // Security: don't reveal whether email is registered
            // Show success-looking message regardless
            $success_message = 'If that email is registered, a reset link has been sent. (Demo: continue below to reset directly.)';
            $found_email = $email;
            $step = 'reset';
        } else {
            $found_email = $email;
            $step = 'reset';
        }
    }
}

// ── STEP 2 – Set new password ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $email    = strtolower(trim($_POST['hidden_email'] ?? ''));
    $password = $_POST['new_password']     ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Session expired. Please start again.';
        $step = 'find';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
        $found_email = $email;
        $step = 'reset';
    } elseif ($password !== $confirm) {
        $error_message = 'Passwords do not match.';
        $found_email = $email;
        $step = 'reset';
    } else {
        $users  = file_exists($users_file) ? (json_decode(file_get_contents($users_file), true) ?: []) : [];
        $found  = false;

        foreach ($users as &$u) {
            if (strtolower($u['email']) === $email) {
                $u['password'] = password_hash($password, PASSWORD_DEFAULT);
                $found = true;
                break;
            }
        }
        unset($u);

        if ($found) {
            $tmp = $users_file . '.tmp';
            file_put_contents($tmp, json_encode($users, JSON_PRETTY_PRINT));
            rename($tmp, $users_file);
            $step = 'done';
        } else {
            // Email not in file – still show success (security)
            $step = 'done';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#060d1f">
    <title>CEAS – Reset Password</title>
    <link rel="manifest" href="manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --bg: #060d1f; --border: rgba(59,130,246,.18);
            --accent-b: #3b82f6; --accent-bright: #60a5fa;
            --text: #e2e8f0; --muted: #94a3b8; --dim: #475569;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg); color: var(--text);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 2rem 1.25rem;
        }
        body::before {
            content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 20%, rgba(37,99,235,.14) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 80%, rgba(234,179,8,.06) 0%, transparent 60%);
        }

        .wrap { position: relative; z-index: 1; width: 100%; max-width: 440px; animation: fadeUp .45s ease both; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

        /* Brand */
        .brand { text-align: center; margin-bottom: 1.75rem; }
        .logo-badge {
            width: 62px; height: 62px; margin: 0 auto 1rem;
            background: linear-gradient(135deg,#1d4ed8,#0ea5e9);
            border-radius: 18px; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 28px rgba(29,78,216,.4); animation: glow 3s ease-in-out infinite;
        }
        @keyframes glow { 0%,100%{box-shadow:0 0 28px rgba(29,78,216,.4)} 50%{box-shadow:0 0 44px rgba(29,78,216,.65)} }
        .logo-badge svg { width: 30px; height: 30px; stroke: #fff; stroke-width: 2.5; fill: none; }
        .brand h1 { font-family:'Syne',sans-serif; font-size:1.75rem; font-weight:800; letter-spacing:-.03em; background:linear-gradient(135deg,#60a5fa,#38bdf8); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
        .brand p  { color: var(--muted); font-size: .875rem; margin-top: .2rem; }

        /* Card */
        .card {
            background: rgba(15,30,56,.72); backdrop-filter: blur(24px);
            border: 1px solid var(--border); border-radius: 20px; padding: 2rem;
            box-shadow: 0 16px 48px rgba(0,0,0,.4);
        }

        /* Step indicator */
        .steps {
            display: flex; align-items: center; gap: 0; margin-bottom: 1.75rem;
        }
        .step-dot {
            width: 28px; height: 28px; border-radius: 50%; border: 2px solid rgba(59,130,246,.25);
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700; color: var(--muted); flex-shrink: 0;
            transition: all .3s;
        }
        .step-dot.active { border-color: var(--accent-b); background: rgba(59,130,246,.15); color: var(--accent-bright); }
        .step-dot.done   { border-color: #10b981; background: rgba(16,185,129,.15); color: #6ee7b7; }
        .step-line { flex: 1; height: 2px; background: rgba(59,130,246,.12); }
        .step-line.done { background: rgba(16,185,129,.3); }
        .step-label {
            font-size: .7rem; color: var(--muted); text-align: center;
            margin-top: .35rem; white-space: nowrap;
        }
        .steps-wrap { display: flex; flex-direction: column; gap: .25rem; }
        .steps-labels { display: flex; justify-content: space-between; padding: 0 4px; }
        .slabel { font-size: .68rem; color: var(--dim); flex: 1; text-align: center; }
        .slabel:first-child { text-align: left; }
        .slabel:last-child  { text-align: right; }

        /* Messages */
        .msg { padding: .8rem 1rem; border-radius: 10px; margin-bottom: 1.25rem; font-size: .875rem; display: flex; gap: .5rem; align-items: flex-start; line-height: 1.5; }
        .msg svg { width:16px; height:16px; stroke:currentColor; stroke-width:2; fill:none; flex-shrink:0; margin-top:2px; }
        .msg.error   { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); color:#fca5a5; animation:shake .4s ease; }
        .msg.info    { background:rgba(37,99,235,.08); border:1px solid rgba(37,99,235,.2); color:#93c5fd; }
        .msg.success { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.3); color:#6ee7b7; }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-8px)} 75%{transform:translateX(8px)} }

        /* Field */
        .field { margin-bottom: 1.25rem; }
        .field label { display: block; font-size: .8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: .4rem; }
        .input-wrap { position: relative; }
        .icon { position: absolute; left: .875rem; top: 50%; transform: translateY(-50%); width: 17px; height: 17px; stroke: var(--muted); stroke-width: 2; fill: none; pointer-events: none; }
        .field input {
            width: 100%; padding: .75rem .875rem .75rem 2.55rem;
            background: rgba(6,13,31,.85); border: 1px solid rgba(59,130,246,.2);
            border-radius: 10px; color: var(--text); font-size: .9rem; font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }
        .field input:focus { outline: none; border-color: var(--accent-b); box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
        .field input::placeholder { color: #475569; }

        /* Password strength */
        .pw-bars { display: flex; gap: .3rem; margin-top: .4rem; }
        .pw-bar  { flex:1; height:3px; background:rgba(59,130,246,.1); border-radius:99px; transition:background .3s; }
        .pw-bar.weak  { background:#ef4444; }
        .pw-bar.fair  { background:#f97316; }
        .pw-bar.good  { background:#eab308; }
        .pw-bar.strong{ background:#10b981; }
        .hint { font-size:.7rem; color:var(--dim); margin-top:.2rem; min-height:.9rem; }
        .hint.ok  { color:#6ee7b7; }
        .hint.bad { color:#fca5a5; }

        /* Email badge */
        .email-badge {
            background: rgba(37,99,235,.08); border: 1px solid rgba(37,99,235,.2);
            border-radius: 8px; padding: .625rem .875rem; margin-bottom: 1.25rem;
            display: flex; align-items: center; gap: .5rem; font-size: .875rem; color: #93c5fd;
        }
        .email-badge svg { width:16px; height:16px; stroke:currentColor; stroke-width:2; fill:none; flex-shrink:0; }
        .email-badge strong { color: var(--accent-bright); }

        /* Buttons */
        .btn-primary {
            width: 100%; padding: .9rem; background: linear-gradient(135deg,#1d4ed8,#2563eb);
            border: none; border-radius: 10px; color: #fff; font-size: 1rem; font-weight: 700;
            font-family: 'Syne', sans-serif; cursor: pointer; transition: all .25s;
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            box-shadow: 0 4px 16px rgba(29,78,216,.35);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(29,78,216,.5); }
        .btn-primary:active { transform: translateY(0); }
        .btn-primary svg { width:18px; height:18px; stroke:currentColor; stroke-width:2.5; fill:none; }

        .btn-success {
            width: 100%; padding: .9rem; background: linear-gradient(135deg,#059669,#10b981);
            border: none; border-radius: 10px; color: #fff; font-size: 1rem; font-weight: 700;
            font-family: 'Syne', sans-serif; cursor: pointer; transition: all .25s;
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            box-shadow: 0 4px 16px rgba(16,185,129,.3);
            text-decoration: none;
        }
        .btn-success:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(16,185,129,.45); }
        .btn-success svg { width:18px; height:18px; stroke:currentColor; stroke-width:2.5; fill:none; }

        .back-link { text-align: center; margin-top: 1.25rem; font-size: .875rem; color: var(--muted); }
        .back-link a { color: var(--accent-b); font-weight: 600; text-decoration: none; }
        .back-link a:hover { color: var(--accent-bright); text-decoration: underline; }

        /* Done state */
        .done-icon {
            width: 72px; height: 72px; margin: 0 auto 1.5rem;
            background: linear-gradient(135deg,#059669,#10b981);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 32px rgba(16,185,129,.4);
            animation: popIn .5s cubic-bezier(.34,1.56,.64,1) both;
        }
        @keyframes popIn { from{opacity:0;transform:scale(0)} to{opacity:1;transform:scale(1)} }
        .done-icon svg { width:36px; height:36px; stroke:#fff; stroke-width:2.5; fill:none; }
        .done-text { text-align: center; }
        .done-text h2 { font-family:'Syne',sans-serif; font-size:1.4rem; font-weight:800; margin-bottom:.625rem; color:#f1f5f9; }
        .done-text p  { color: var(--muted); font-size:.9rem; line-height:1.6; margin-bottom:1.5rem; }

        footer { text-align: center; margin-top: 1.5rem; color: #334155; font-size: .8125rem; }
        footer a { color: var(--accent-b); text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">

    <div class="brand">
        <div class="logo-badge">
            <svg viewBox="0 0 24 24">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <h1>Reset Password</h1>
        <p>Recover access to your CEAS account</p>
    </div>

    <div class="card">

        <?php if ($step !== 'done'): ?>
        <!-- Step indicator -->
        <div class="steps-wrap" style="margin-bottom:1.75rem">
            <div class="steps">
                <div class="step-dot <?= $step === 'find' ? 'active' : 'done' ?>">
                    <?= $step === 'find' ? '1' : '✓' ?>
                </div>
                <div class="step-line <?= $step !== 'find' ? 'done' : '' ?>"></div>
                <div class="step-dot <?= $step === 'reset' ? 'active' : ($step === 'done' ? 'done' : '') ?>">
                    <?= $step === 'done' ? '✓' : '2' ?>
                </div>
                <div class="step-line"></div>
                <div class="step-dot <?= $step === 'done' ? 'done' : '' ?>">3</div>
            </div>
            <div class="steps-labels">
                <span class="slabel">Find Account</span>
                <span class="slabel" style="text-align:center">New Password</span>
                <span class="slabel">Done</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="msg error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= htmlspecialchars($error_message) ?></span>
        </div>
        <?php endif; ?>

        <!-- ══ STEP 1 — FIND ACCOUNT ══════════════════════════════ -->
        <?php if ($step === 'find'): ?>
        <div class="msg info">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>Enter the email address you registered with. We'll let you set a new password.</span>
        </div>
        <form method="POST">
            <div class="field">
                <label for="email">Registered Email Address</label>
                <div class="input-wrap">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <input type="email" id="email" name="email"
                           placeholder="yourname@tamcc.edu.gd"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           autocomplete="email" required>
                </div>
            </div>
            <button type="submit" name="find_account" class="btn-primary">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Find My Account
            </button>
        </form>

        <!-- ══ STEP 2 — NEW PASSWORD ═══════════════════════════════ -->
        <?php elseif ($step === 'reset'): ?>
        <div class="email-badge">
            <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            Resetting password for <strong>&nbsp;<?= htmlspecialchars($found_email) ?></strong>
        </div>

        <form method="POST" id="resetForm" novalidate>
            <input type="hidden" name="hidden_email" value="<?= htmlspecialchars($found_email) ?>">

            <div class="field">
                <label for="new_password">New Password</label>
                <div class="input-wrap">
                    <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    <input type="password" id="new_password" name="new_password"
                           placeholder="Min. 6 characters"
                           autocomplete="new-password" required>
                </div>
                <div class="pw-bars">
                    <div class="pw-bar" id="b1"></div>
                    <div class="pw-bar" id="b2"></div>
                    <div class="pw-bar" id="b3"></div>
                    <div class="pw-bar" id="b4"></div>
                </div>
                <div class="hint" id="pwHint"></div>
            </div>

            <div class="field">
                <label for="confirm_password">Confirm New Password</label>
                <div class="input-wrap">
                    <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    <input type="password" id="confirm_password" name="confirm_password"
                           placeholder="Repeat new password"
                           autocomplete="new-password" required>
                </div>
                <div class="hint" id="matchHint"></div>
            </div>

            <button type="submit" name="reset_password" class="btn-primary">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                Set New Password
            </button>
        </form>

        <!-- ══ STEP 3 — DONE ═══════════════════════════════════════ -->
        <?php elseif ($step === 'done'): ?>
        <div class="done-icon">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="done-text">
            <h2>Password Updated!</h2>
            <p>Your password has been changed successfully. You can now sign in with your new password.</p>
            <a href="login.php" class="btn-success">
                <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Sign In Now
            </a>
        </div>
        <?php endif; ?>

    </div>

    <div class="back-link">
        <a href="login.php">← Back to Sign In</a>
        &nbsp;&bull;&nbsp;
        <a href="signup.php">Create new account</a>
    </div>

    <footer>
        &copy; 2025 CEAS &mdash; <a href="#">Privacy Policy</a>
    </footer>
</div>

<?php if ($step === 'reset'): ?>
<script>
const pwInput   = document.getElementById('new_password');
const confirmPw = document.getElementById('confirm_password');
const bars      = [1,2,3,4].map(i => document.getElementById('b'+i));
const pwHint    = document.getElementById('pwHint');
const matchHint = document.getElementById('matchHint');

function score(pw) {
    let s = 0;
    if (pw.length >= 6)  s++;
    if (pw.length >= 10) s++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
    if (/[0-9]/.test(pw) || /[^A-Za-z0-9]/.test(pw)) s++;
    return s;
}

pwInput.addEventListener('input', function() {
    const s = score(this.value);
    const labels  = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    const classes = ['', 'weak', 'fair', 'good', 'strong'];
    bars.forEach((b,i) => {
        b.className = 'pw-bar' + (this.value.length > 0 && i < s ? ' ' + classes[s] : '');
    });
    pwHint.textContent = this.value.length > 0 ? labels[s] : '';
    checkMatch();
});

confirmPw.addEventListener('input', checkMatch);

function checkMatch() {
    if (!confirmPw.value) { matchHint.textContent = ''; return; }
    const ok = pwInput.value === confirmPw.value;
    matchHint.textContent = ok ? '✓ Passwords match' : '✗ Does not match';
    matchHint.className   = 'hint ' + (ok ? 'ok' : 'bad');
}

document.getElementById('resetForm').addEventListener('submit', function(e) {
    if (pwInput.value.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters.');
        pwInput.focus();
        return;
    }
    if (pwInput.value !== confirmPw.value) {
        e.preventDefault();
        alert('Passwords do not match.');
        confirmPw.focus();
    }
});
</script>
<?php endif; ?>
</body>
</html>
