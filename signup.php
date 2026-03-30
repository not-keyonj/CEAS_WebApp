<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: ceas-dashboard.php');
    exit();
}

// ── Available schools ─────────────────────────────────────────────────
$schools = [
    'tamcc_stgeorge'  => "TAMCC – St. George's Campus",
    'tamcc_stpatrick' => "TAMCC – St. Patrick's Campus",
    'sgu'             => "St. George's University (SGU)",
    'st_josephs'      => "St. Joseph's Convent",
    'presentation'    => "Presentation Brothers' College",
    'st_andrews'      => "St. Andrew's Anglican Secondary",
    'westmorland'     => "Westmorland Secondary School",
    'other'           => "Other Grenada Institution",
];

$users_file = 'registered_users.json';
if (!file_exists($users_file)) {
    file_put_contents($users_file, '[]');
}

$error_message   = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name         = trim($_POST['full_name']            ?? '');
    $email             = strtolower(trim($_POST['email']     ?? ''));
    $student_id        = trim($_POST['student_id']           ?? '');
    $phone             = trim($_POST['phone']                ?? '');
    $password          = $_POST['password']                  ?? '';
    $confirm           = $_POST['confirm_password']          ?? '';
    $user_type         = $_POST['user_type']                 ?? 'student';
    $campus            = $_POST['campus']                    ?? 'tamcc_stgeorge';
    $other_institution = trim($_POST['other_institution']    ?? '');

    // ── Validation ─────────────────────────────────────────────────
    if (empty($full_name) || empty($email) || empty($student_id) || empty($phone) || empty($password) || empty($confirm)) {
        $error_message = 'All fields are required. Please fill in every field.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm) {
        $error_message = 'Passwords do not match. Please try again.';
    } elseif (!array_key_exists($campus, $schools)) {
        $error_message = 'Please select a valid campus.';
    } elseif ($campus === 'other' && empty($other_institution)) {
        $error_message = 'Please type the name of your institution.';
    } elseif (!in_array($user_type, ['student', 'teacher', 'faculty', 'admin'])) {
        $error_message = 'Please select a valid user type.';
    } else {
        // ── Check duplicate email ──────────────────────────────────
        $existing  = json_decode(file_get_contents($users_file), true) ?: [];
        $duplicate = false;
        foreach ($existing as $u) {
            if (strtolower($u['email']) === $email) {
                $duplicate = true;
                break;
            }
        }

        if ($duplicate) {
            $error_message = 'An account with this email already exists. <a href="login.php" style="color:#60a5fa">Sign in instead</a>.';
        } else {
            // ── Save new user ──────────────────────────────────────
            $new_user = [
                'full_name'   => $full_name,
                'email'       => $email,
                'student_id'  => $student_id,
                'phone'       => $phone,
                'password'    => password_hash($password, PASSWORD_DEFAULT),
                'user_type'   => $user_type,
                'campus'      => $campus,
                'school_name' => ($campus === 'other' && !empty($other_institution))
                                   ? htmlspecialchars($other_institution)
                                   : $schools[$campus],
                'registered'  => date('Y-m-d H:i:s'),
                'status'      => 'active',
            ];

            $existing[] = $new_user;

            // Atomic write via temp file
            $tmp = $users_file . '.tmp';
            if (file_put_contents($tmp, json_encode($existing, JSON_PRETTY_PRINT)) !== false) {
                rename($tmp, $users_file);

                // Auto-login immediately after registration
                // Determine role for session
                $role = ($user_type === 'admin') ? 'System Administrator' : ucfirst($user_type);
                $_SESSION['user'] = [
                    'name'       => $full_name,
                    'role'       => $role,
                    'email'      => $email,
                    'campus'     => $campus,
                    'login_time' => date('Y-m-d H:i:s'),
                ];
                header('Location: ceas-dashboard.php');
                exit();
            } else {
                $error_message = 'Could not save your account. The server may have a file permission issue.';
            }
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
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>CEAS – Create Account</title>
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
            min-height: 100vh; display: flex; align-items: flex-start; justify-content: center;
            padding: 2rem 1.25rem; overflow-x: hidden;
        }
        body::before {
            content: ''; position: fixed; inset: 0; pointer-events: none; z-index: 0;
            background:
                radial-gradient(ellipse 80% 50% at 20% 20%, rgba(37,99,235,.14) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 80%, rgba(16,185,129,.07) 0%, transparent 60%);
        }

        .wrap { position: relative; z-index: 1; width: 100%; max-width: 530px; animation: fadeUp .45s ease both; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }

        /* ── Brand ── */
        .brand { text-align: center; margin-bottom: 1.75rem; }
        .logo-badge {
            width: 62px; height: 62px; margin: 0 auto 1rem;
            background: linear-gradient(135deg,#1d4ed8,#0ea5e9);
            border-radius: 18px; display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 28px rgba(29,78,216,.4); animation: glow 3s ease-in-out infinite;
        }
        @keyframes glow { 0%,100%{box-shadow:0 0 28px rgba(29,78,216,.4)} 50%{box-shadow:0 0 44px rgba(29,78,216,.65)} }
        .logo-badge svg { width: 30px; height: 30px; stroke: #fff; stroke-width: 2.5; fill: none; }
        .brand h1 { font-family:'Syne',sans-serif; font-size:1.9rem; font-weight:800; letter-spacing:-.03em; background:linear-gradient(135deg,#60a5fa,#38bdf8); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
        .brand p  { color: var(--muted); font-size: .875rem; margin-top: .2rem; }

        /* ── Card ── */
        .card {
            background: rgba(15,30,56,.72); backdrop-filter: blur(24px);
            border: 1px solid var(--border); border-radius: 20px; padding: 1.75rem 2rem;
            box-shadow: 0 16px 48px rgba(0,0,0,.4);
        }

        .section-label {
            font-family: 'Syne', sans-serif; font-size: .7rem; font-weight: 700;
            letter-spacing: .1em; text-transform: uppercase; color: var(--accent-bright);
            margin: 1.35rem 0 .75rem; padding-bottom: .5rem;
            border-bottom: 1px solid rgba(59,130,246,.12);
        }
        .section-label:first-child { margin-top: 0; }

        /* ── Messages ── */
        .msg { padding: .8rem 1rem; border-radius: 10px; margin-bottom: 1.25rem; font-size: .875rem; display: flex; gap: .5rem; align-items: flex-start; line-height: 1.5; }
        .msg svg { width:16px; height:16px; stroke:currentColor; stroke-width:2; fill:none; flex-shrink:0; margin-top:2px; }
        .msg.error   { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); color:#fca5a5; animation:shake .4s ease; }
        .msg.success { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.3); color:#6ee7b7; }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-8px)} 75%{transform:translateX(8px)} }

        /* ── Campus picker ── */
        .campus-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .45rem; }

        /* ── Other institution reveal ── */
        .other-field {
            display: none; margin-top: .75rem;
            background: rgba(234,179,8,.05); border: 1px solid rgba(234,179,8,.2);
            border-radius: 10px; padding: .875rem 1rem;
            animation: fadeUp .25s ease;
        }
        .other-field.show { display: block; }
        .other-field label { font-size: .8125rem; font-weight: 600; color: #fde68a; margin-bottom: .4rem; display: block; }
        .other-field input {
            width: 100%; padding: .7rem .875rem;
            background: rgba(6,13,31,.85); border: 1px solid rgba(234,179,8,.25);
            border-radius: 8px; color: var(--text); font-size: .9rem; font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }
        .other-field input:focus { outline: none; border-color: #eab308; box-shadow: 0 0 0 3px rgba(234,179,8,.1); }
        .other-field input::placeholder { color: #475569; }
        .campus-item { position: relative; }
        .campus-radio { position: absolute; opacity: 0; width: 0; height: 0; }
        .campus-lbl {
            display: flex; align-items: center; gap: .5rem; padding: .55rem .75rem;
            border: 1px solid rgba(59,130,246,.15); border-radius: 8px; cursor: pointer;
            font-size: .78rem; color: var(--muted); transition: all .18s; line-height: 1.3;
            user-select: none;
        }
        .campus-lbl::before {
            content: ''; width: 13px; min-width: 13px; height: 13px;
            border: 2px solid rgba(59,130,246,.3); border-radius: 50%;
            flex-shrink: 0; transition: all .18s;
        }
        .campus-radio:checked + .campus-lbl {
            border-color: var(--accent-b); color: var(--accent-bright);
            background: rgba(59,130,246,.1);
        }
        .campus-radio:checked + .campus-lbl::before {
            background: var(--accent-b); border-color: var(--accent-b);
        }

        /* ── Form fields ── */
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; }
        .field { margin-bottom: 1.1rem; }
        .field label { display: block; font-size: .8125rem; font-weight: 600; color: #cbd5e1; margin-bottom: .4rem; }
        .input-wrap { position: relative; }
        .icon { position: absolute; left: .875rem; top: 50%; transform: translateY(-50%); width: 17px; height: 17px; stroke: var(--muted); stroke-width: 2; fill: none; pointer-events: none; }
        .field input, .field select {
            width: 100%; padding: .72rem .875rem .72rem 2.55rem;
            background: rgba(6,13,31,.85); border: 1px solid rgba(59,130,246,.2);
            border-radius: 10px; color: var(--text); font-size: .9rem; font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }
        .field select { padding-left: .875rem; }
        .field input:focus, .field select:focus { outline: none; border-color: var(--accent-b); box-shadow: 0 0 0 3px rgba(59,130,246,.1); }
        .field input::placeholder { color: #475569; }

        /* Password strength */
        .pw-bars { display: flex; gap: .3rem; margin-top: .4rem; }
        .pw-bar { flex: 1; height: 3px; background: rgba(59,130,246,.1); border-radius: 99px; transition: background .3s; }
        .pw-bar.weak   { background: #ef4444; }
        .pw-bar.fair   { background: #f97316; }
        .pw-bar.good   { background: #eab308; }
        .pw-bar.strong { background: #10b981; }
        .hint { font-size: .7rem; color: var(--dim); margin-top: .25rem; min-height: .9rem; }
        .hint.ok  { color: #6ee7b7; }
        .hint.bad { color: #fca5a5; }

        /* Terms */
        .terms-row { display: flex; gap: .5rem; align-items: flex-start; margin-bottom: 1.25rem; }
        .terms-row input[type=checkbox] { accent-color: var(--accent-b); margin-top: 2px; flex-shrink: 0; }
        .terms-row label { font-size: .8125rem; color: var(--muted); line-height: 1.55; }
        .terms-row a { color: var(--accent-b); }

        /* Submit */
        .btn-submit {
            width: 100%; padding: .9rem; background: linear-gradient(135deg,#1d4ed8,#2563eb);
            border: none; border-radius: 10px; color: #fff; font-size: 1rem; font-weight: 700;
            font-family: 'Syne', sans-serif; letter-spacing: .01em; cursor: pointer;
            transition: all .25s; display: flex; align-items: center; justify-content: center; gap: .5rem;
            box-shadow: 0 4px 16px rgba(29,78,216,.35);
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(29,78,216,.5); }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit svg { width: 18px; height: 18px; stroke: currentColor; stroke-width: 2.5; fill: none; }
        .btn-submit:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        .login-link { text-align: center; margin-top: 1.25rem; font-size: .875rem; color: var(--muted); }
        .login-link a { color: var(--accent-b); font-weight: 600; text-decoration: none; }
        .login-link a:hover { color: var(--accent-bright); text-decoration: underline; }

        footer { text-align: center; margin-top: 1.5rem; color: #334155; font-size: .8125rem; }
        footer a { color: var(--accent-b); text-decoration: none; }

        @media (max-width: 480px) {
            .grid2 { grid-template-columns: 1fr; }
            .campus-grid { grid-template-columns: 1fr; }
            .card { padding: 1.5rem 1.25rem; }
        }
    </style>
</head>
<body>
<div class="wrap">

    <!-- Brand -->
    <div class="brand">
        <div class="logo-badge">
            <svg viewBox="0 0 24 24">
                <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <h1>Join CEAS</h1>
        <p>Create your account to receive emergency alerts</p>
    </div>

    <div class="card">

        <!-- Messages -->
        <?php if (!empty($error_message)): ?>
        <div class="msg error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= $error_message ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" id="signupForm" novalidate>

            <!-- ── Campus ── -->
            <div class="section-label">Select Your Campus</div>
            <div class="campus-grid">
                <?php foreach ($schools as $val => $name): ?>
                <div class="campus-item">
                    <input type="radio" class="campus-radio" name="campus"
                           id="c_<?= $val ?>" value="<?= $val ?>"
                           <?= (($_POST['campus'] ?? 'tamcc_stgeorge') === $val) ? 'checked' : '' ?>>
                    <label class="campus-lbl" for="c_<?= $val ?>"><?= htmlspecialchars($name) ?></label>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Other institution text input (shown when "Other" is selected) -->
            <div class="other-field <?= (($_POST['campus'] ?? '') === 'other') ? 'show' : '' ?>" id="otherFieldWrap">
                <label for="other_institution">
                    ✏️ Enter your institution name
                </label>
                <input type="text" id="other_institution" name="other_institution"
                       placeholder="e.g. St. David's Secondary School"
                       value="<?= htmlspecialchars($_POST['other_institution'] ?? '') ?>"
                       maxlength="100">
            </div>

            <!-- ── Personal Info ── -->
            <div class="section-label">Personal Information</div>

            <div class="field">
                <label for="full_name">Full Name</label>
                <div class="input-wrap">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <input type="text" id="full_name" name="full_name"
                           placeholder="Your full legal name"
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="grid2">
                <div class="field">
                    <label for="student_id">Student / Staff ID</label>
                    <div class="input-wrap">
                        <svg class="icon" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        <input type="text" id="student_id" name="student_id"
                               placeholder="e.g. 20210001"
                               value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="field">
                    <label for="user_type">User Type</label>
                    <select id="user_type" name="user_type" required>
                        <option value="student" <?= (($_POST['user_type'] ?? 'student') === 'student') ? 'selected' : '' ?>>Student</option>
                        <option value="teacher" <?= (($_POST['user_type'] ?? '') === 'teacher') ? 'selected' : '' ?>>Teacher</option>
                        <option value="faculty" <?= (($_POST['user_type'] ?? '') === 'faculty') ? 'selected' : '' ?>>Faculty</option>
                        <option value="admin" <?= (($_POST['user_type'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="email">Email Address</label>
                <div class="input-wrap">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <input type="email" id="email" name="email"
                           placeholder="yourname@tamcc.edu.gd"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           autocomplete="email" required>
                </div>
            </div>

            <div class="field">
                <label for="phone">Phone Number (Grenada)</label>
                <div class="input-wrap">
                    <svg class="icon" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 12.79 19.79 19.79 0 01.21 4.16 2 2 0 012.18 2H5a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 9.91A16 16 0 0012 15.91l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
                    <input type="tel" id="phone" name="phone"
                           placeholder="+1 (473) 400-0000"
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
            </div>

            <!-- ── Password ── -->
            <div class="section-label">Create Password</div>

            <div class="grid2">
                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        <input type="password" id="password" name="password"
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
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-wrap">
                        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        <input type="password" id="confirm_password" name="confirm_password"
                               placeholder="Repeat password"
                               autocomplete="new-password" required>
                    </div>
                    <div class="hint" id="matchHint"></div>
                </div>
            </div>

            <!-- Terms -->
            <div class="terms-row">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">
                    I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>,
                    and I consent to receive emergency alerts via SMS, email, and push notifications.
                </label>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                Create Account &amp; Sign In
            </button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Sign In</a>
        </div>
    </div>

    <footer>
        &copy; 2025 CEAS &mdash; <a href="#">Privacy Policy</a> &bull; <a href="#">Terms of Service</a>
    </footer>
</div>

<script>
// Show / hide the "Other institution" text field
document.querySelectorAll('.campus-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        const wrap = document.getElementById('otherFieldWrap');
        const input = document.getElementById('other_institution');
        if (this.value === 'other') {
            wrap.classList.add('show');
            input.focus();
        } else {
            wrap.classList.remove('show');
            input.value = '';
        }
    });
});

const pwInput    = document.getElementById('password');
const confirmPw  = document.getElementById('confirm_password');
const bars       = [1,2,3,4].map(i => document.getElementById('b'+i));
const pwHint     = document.getElementById('pwHint');
const matchHint  = document.getElementById('matchHint');

function scorePassword(pw) {
    let s = 0;
    if (pw.length >= 6)  s++;
    if (pw.length >= 10) s++;
    if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
    if (/[0-9]/.test(pw) || /[^A-Za-z0-9]/.test(pw)) s++;
    return s;
}

pwInput.addEventListener('input', function() {
    const s = scorePassword(this.value);
    const levels  = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    const classes = ['', 'weak', 'fair', 'good', 'strong'];
    bars.forEach((b,i) => {
        b.className = 'pw-bar' + (this.value.length > 0 && i < s ? ' ' + classes[s] : '');
    });
    pwHint.textContent  = this.value.length > 0 ? levels[s] : '';
    pwHint.className    = 'hint';
    checkMatch();
});

confirmPw.addEventListener('input', checkMatch);

function checkMatch() {
    if (!confirmPw.value) { matchHint.textContent = ''; return; }
    const match = pwInput.value === confirmPw.value;
    matchHint.textContent = match ? '✓ Passwords match' : '✗ Does not match';
    matchHint.className   = 'hint ' + (match ? 'ok' : 'bad');
}

document.getElementById('signupForm').addEventListener('submit', function(e) {
    const campus = document.querySelector('input[name="campus"]:checked');
    if (!campus) {
        e.preventDefault();
        alert('Please select your campus before continuing.');
        return;
    }
    if (campus.value === 'other') {
        const otherName = document.getElementById('other_institution').value.trim();
        if (!otherName) {
            e.preventDefault();
            alert('Please type the name of your institution.');
            document.getElementById('other_institution').focus();
            return;
        }
    }
    if (pwInput.value.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long.');
        pwInput.focus();
        return;
    }
    if (pwInput.value !== confirmPw.value) {
        e.preventDefault();
        alert('Passwords do not match. Please re-enter your password.');
        confirmPw.focus();
        return;
    }
    if (!document.getElementById('terms').checked) {
        e.preventDefault();
        alert('Please accept the Terms of Service to create your account.');
        return;
    }
    // Disable button to prevent double-submit
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Creating account...';
});
</script>
</body>
</html>