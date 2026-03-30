<?php
session_start();

// ── Campus definitions ──────────────────────────────────────────────
$campuses = [
    'tamcc_stgeorge'  => ['name'=>"TAMCC – St. George's",       'lat'=>12.0529, 'lon'=>-61.7489],
    'tamcc_grenville' => ['name'=>"TAMCC – Grenville",           'lat'=>12.1197, 'lon'=>-61.6319],
    'tamcc_carriacou' => ['name'=>"TAMCC – Carriacou",           'lat'=>12.4774, 'lon'=>-61.4551],
    'sgu'             => ['name'=>"St. George's University",     'lat'=>12.0261, 'lon'=>-61.7539],
    'gnbs'            => ['name'=>"Grenada National Bible School",'lat'=>12.0500, 'lon'=>-61.7450],
    'gcss'            => ['name'=>"Grenada Community Secondary", 'lat'=>12.0489, 'lon'=>-61.7471],
    'other'           => ['name'=>"Grenada Institution",         'lat'=>12.0529, 'lon'=>-61.7489],
];

// ── Auth guard ───────────────────────────────────────────────────────
if (!isset($_SESSION['user'])) {
    // Allow demo access
    $_SESSION['user'] = [
        'name'       => 'Administrator',
        'role'       => 'System Administrator',
        'email'      => 'admin@tamcc.edu.gd',
        'campus'     => 'tamcc_stgeorge',
        'login_time' => date('Y-m-d H:i:s')
    ];
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit();
}

$user   = $_SESSION['user'];
$campus = $campuses[$user['campus'] ?? 'tamcc_stgeorge'] ?? $campuses['tamcc_stgeorge'];

// Role‑based access: only System Administrators have full rights
$is_admin = ($user['role'] === 'System Administrator');

// ── Default settings ─────────────────────────────────────────────────
$defaults = [
    'campus_key'         => $user['campus'] ?? 'tamcc_stgeorge',
    'admin_name'         => $user['name'],
    'admin_email'        => $user['email'],
    'sms_enabled'        => true,
    'email_enabled'      => true,
    'push_enabled'       => true,
    'notification_sound' => true,
    'auto_archive_days'  => 30,
    'alert_retention'    => 90,
    'max_recipients'     => 5000,
    'timezone'           => 'America/Grenada',
    'weather_alerts'     => true,
];

if (!isset($_SESSION['settings'])) {
    $_SESSION['settings'] = $defaults;
}

// ── Handle settings save (admin only) ───────────────────────────────
$settings_saved = false;
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    foreach (['admin_name','admin_email','campus_key','timezone','auto_archive_days','alert_retention','max_recipients'] as $k) {
        if (isset($_POST[$k])) $_SESSION['settings'][$k] = $_POST[$k];
    }
    foreach (['sms_enabled','email_enabled','push_enabled','notification_sound','weather_alerts'] as $k) {
        $_SESSION['settings'][$k] = isset($_POST[$k]);
    }
    $settings_saved = true;
}

$s = $_SESSION['settings'];

// ── Registered users list (admin only) ──────────────────────────────
$users_file = 'registered_users.json';
$reg_users  = ($is_admin && file_exists($users_file)) ? (json_decode(file_get_contents($users_file), true) ?: []) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#060d1f">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<title>CEAS – <?= htmlspecialchars($campus['name']) ?></title>
<link rel="manifest" href="manifest.json">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,400;0,500;0,600;1,400&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
/* All CSS unchanged – same as original */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}

:root{
  --bg:#060d1f;
  --surface:#0c1830;
  --surface2:#122040;
  --surface3:#1a2e52;
  --border:rgba(59,130,246,.14);
  --border2:rgba(59,130,246,.22);
  --accent:#2563eb;
  --accent-b:#3b82f6;
  --accent-bright:#60a5fa;
  --text:#e2e8f0;
  --muted:#94a3b8;
  --dim:#475569;
  --red:#ef4444; --red-bg:rgba(239,68,68,.1); --red-border:rgba(239,68,68,.25);
  --orange:#f97316; --orange-bg:rgba(249,115,22,.1); --orange-border:rgba(249,115,22,.25);
  --yellow:#eab308; --yellow-bg:rgba(234,179,8,.1); --yellow-border:rgba(234,179,8,.25);
  --green:#10b981; --green-bg:rgba(16,185,129,.1); --green-border:rgba(16,185,129,.25);
  --sidebar:240px;
  --header:56px;
  --radius:14px;
}

html,body{height:100%;overflow:hidden}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;flex-direction:column}

body::before{
  content:'';position:fixed;inset:0;
  background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='4' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='200' height='200' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
  opacity:.4;pointer-events:none;z-index:0;
}

.header{
  height:var(--header);
  background:rgba(6,13,31,.95);
  backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;
  padding:0 1.5rem;gap:1rem;
  position:relative;z-index:200;
  flex-shrink:0;
}

.header-brand{display:flex;align-items:center;gap:.75rem;flex:1}
.logo-box{
  width:36px;height:36px;
  background:linear-gradient(135deg,#1d4ed8,#0ea5e9);
  border-radius:10px;display:flex;align-items:center;justify-content:center;
  animation:logoGlow 3s ease-in-out infinite;flex-shrink:0;
}
@keyframes logoGlow{0%,100%{box-shadow:0 0 12px rgba(29,78,216,.4)}50%{box-shadow:0 0 24px rgba(29,78,216,.7)}}
.logo-box svg{width:20px;height:20px;stroke:#fff;stroke-width:2.5;fill:none}
.brand-text h1{font-family:'Syne',sans-serif;font-size:1.125rem;font-weight:800;letter-spacing:-.02em;color:#60a5fa}
.brand-text span{font-size:.7rem;color:var(--muted);display:block;line-height:1}

.alert-ticker{
  flex:1;height:38px;overflow:hidden;
  border-radius:8px;border:1px solid rgba(59,130,246,.22);
  background:rgba(9,18,44,.9);display:flex;align-items:center;
  padding:0 1rem;position:relative;gap:0;
}
.ticker-label{
  font-size:.7rem;font-weight:800;letter-spacing:.12em;
  text-transform:uppercase;color:#22d3ee;
  white-space:nowrap;padding-right:1rem;
  border-right:1px solid rgba(59,130,246,.3);margin-right:1rem;
  flex-shrink:0;display:flex;align-items:center;gap:.35rem;
}
.ticker-dot{
  width:7px;height:7px;background:#22d3ee;border-radius:50%;
  animation:blink 1.2s ease-in-out infinite;flex-shrink:0;
}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
.ticker-wrap{overflow:hidden;flex:1;height:100%;display:flex;align-items:center}
.ticker-content{
  font-size:.85rem;font-weight:500;color:#e2e8f0;white-space:nowrap;
  animation:ticker 40s linear infinite;
  letter-spacing:.015em;
}
.ticker-content span{color:#60a5fa;font-weight:700}
@keyframes ticker{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}

.user-badge{
  display:flex;align-items:center;gap:.625rem;
  padding:.375rem .875rem .375rem .375rem;
  background:rgba(37,99,235,.1);border:1px solid var(--border2);border-radius:10px;
  cursor:pointer;flex-shrink:0;
}
.avatar{
  width:32px;height:32px;border-radius:8px;
  background:linear-gradient(135deg,#1d4ed8,#0ea5e9);
  display:flex;align-items:center;justify-content:center;
  font-family:'Syne',sans-serif;font-weight:700;font-size:.875rem;color:#fff;
}
.user-name{font-size:.8125rem;font-weight:600;color:var(--text)}
.user-role{font-size:.6875rem;color:var(--muted)}

.layout{display:flex;flex:1;overflow:hidden;position:relative;z-index:1}

.sidebar{
  width:var(--sidebar);flex-shrink:0;
  background:rgba(6,13,31,.8);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  overflow-y:auto;
}
.nav-section{padding:1rem .75rem .5rem}
.nav-label{font-size:.6875rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--dim);padding:.5rem .625rem;margin-bottom:.25rem}

.nav-btn{
  width:100%;display:flex;align-items:center;gap:.75rem;
  padding:.65rem .875rem;background:transparent;border:none;
  border-radius:10px;color:var(--muted);font-size:.875rem;font-weight:500;
  font-family:inherit;text-align:left;cursor:pointer;
  transition:all .2s;position:relative;
}
.nav-btn:hover{background:rgba(59,130,246,.08);color:var(--accent-bright)}
.nav-btn.active{background:rgba(37,99,235,.15);color:var(--accent-bright);font-weight:600}
.nav-btn.active::before{content:'';position:absolute;left:0;top:20%;bottom:20%;width:3px;background:var(--accent-b);border-radius:0 2px 2px 0}
.nav-btn svg{width:16px;height:16px;stroke:currentColor;stroke-width:2;fill:none;flex-shrink:0}

.nav-badge{
  margin-left:auto;padding:.15rem .5rem;
  background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.3);
  color:#fca5a5;font-size:.6rem;font-weight:700;border-radius:99px;
  font-family:'JetBrains Mono',monospace;
}

.nav-btn.danger{color:#fca5a5}
.nav-btn.danger:hover{background:rgba(239,68,68,.1);color:#f87171}

.sidebar-footer{margin-top:auto;padding:.75rem;border-top:1px solid var(--border)}
.campus-badge{
  padding:.5rem .75rem;background:rgba(37,99,235,.07);
  border:1px solid var(--border);border-radius:8px;font-size:.75rem;color:var(--muted);line-height:1.4;
}
.campus-badge strong{color:var(--text);display:block;font-weight:600}

.main{flex:1;overflow-y:auto;padding:1.5rem}

.view{display:none}
.view.active{display:block}

.page-title{
  font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;
  color:#f1f5f9;letter-spacing:-.02em;margin-bottom:.25rem;
}
.page-sub{color:var(--muted);font-size:.875rem;margin-bottom:1.5rem}

.card{
  background:rgba(12,24,48,.75);backdrop-filter:blur(16px);
  border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;
}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem}
.card-title{font-family:'Syne',sans-serif;font-size:.9375rem;font-weight:700;color:#f1f5f9;display:flex;align-items:center;gap:.625rem}
.card-title svg{width:16px;height:16px;stroke:var(--accent-b);stroke-width:2.5;fill:none}

.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.25rem}
.stat-card{
  background:rgba(12,24,48,.75);border:1px solid var(--border);border-radius:var(--radius);
  padding:1.125rem 1.25rem;
  display:flex;align-items:center;gap:.875rem;
  transition:border-color .25s,transform .25s;overflow:visible;
  min-height:88px;
}
.stat-card:hover{border-color:var(--border2);transform:translateY(-2px)}
.stat-card-top{display:none}
.stat-icon{
  width:44px;min-width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.stat-icon svg{width:21px;height:21px;stroke:#fff;stroke-width:2;fill:none}
.stat-icon.blue{background:linear-gradient(135deg,#1d4ed8,#3b82f6)}
.stat-icon.green{background:linear-gradient(135deg,#059669,#10b981)}
.stat-icon.orange{background:linear-gradient(135deg,#c2410c,#f97316)}
.stat-icon.purple{background:linear-gradient(135deg,#6d28d9,#8b5cf6)}
.stat-icon.red{background:linear-gradient(135deg,#b91c1c,#ef4444)}
.stat-body{min-width:0;flex:1}

.stat-label{font-size:.75rem;color:#cbd5e1;font-weight:600;margin-top:.1rem}
.stat-change{font-size:.7rem;margin-top:.15rem}
.stat-change.up{color:var(--green)} .stat-change.down{color:var(--red)} .stat-change.ok{color:var(--muted)}

.dash-grid{display:grid;grid-template-columns:1fr 360px;gap:1.25rem}

.weather-card{background:rgba(12,24,48,.75);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden}

.weather-header{
  padding:1.25rem 1.5rem 1rem;
  display:flex;align-items:center;justify-content:space-between;
}
.weather-location{font-size:.75rem;color:var(--muted);margin-bottom:.125rem;display:flex;align-items:center;gap:.375rem}
.weather-location svg{width:12px;height:12px;stroke:currentColor;stroke-width:2;fill:none}
.weather-status-text{font-family:'Syne',sans-serif;font-size:1.25rem;font-weight:700}

.weather-danger{
  padding:.5rem 1rem;font-size:.8125rem;font-weight:600;
  display:flex;align-items:center;gap:.5rem;
}
.weather-danger svg{width:14px;height:14px;stroke:currentColor;stroke-width:2.5;fill:none}
.danger-safe    {background:var(--green-bg);border-top:1px solid var(--green-border);color:#6ee7b7}
.danger-caution {background:var(--yellow-bg);border-top:1px solid var(--yellow-border);color:#fde68a}
.danger-warning {background:var(--orange-bg);border-top:1px solid var(--orange-border);color:#fed7aa}
.danger-danger  {background:var(--red-bg);border-top:1px solid var(--red-border);color:#fca5a5;animation:pulseRed 1.5s ease infinite}
@keyframes pulseRed{0%,100%{background:rgba(239,68,68,.1)}50%{background:rgba(239,68,68,.2)}}

.weather-body{padding:1rem 1.5rem 1.25rem;display:grid;grid-template-columns:1fr 1fr 1fr;gap:.875rem}
.w-metric{text-align:center}
.w-metric-val{font-family:'JetBrains Mono',monospace;font-size:1.25rem;font-weight:600;color:#f1f5f9}
.w-metric-label{font-size:.6875rem;color:var(--muted);margin-top:.15rem}
.w-metric-unit{font-size:.75rem;color:var(--dim)}
.weather-loading{padding:2rem;text-align:center;color:var(--muted);font-size:.875rem}

.alert-feed{display:flex;flex-direction:column;gap:.5rem;max-height:420px;overflow-y:auto;padding-right:.25rem}
.alert-feed::-webkit-scrollbar{width:4px}
.alert-feed::-webkit-scrollbar-track{background:rgba(59,130,246,.05)}
.alert-feed::-webkit-scrollbar-thumb{background:rgba(59,130,246,.25);border-radius:4px}
.alert-item{border-radius:10px;border-left:4px solid transparent;overflow:hidden;animation:slideIn .3s ease}
@keyframes slideIn{from{opacity:0;transform:translateX(-10px)}to{opacity:1;transform:translateX(0)}}
.alert-item.severe{background:rgba(239,68,68,.07);border-color:#ef4444}
.alert-item.high  {background:rgba(249,115,22,.07);border-color:#f97316}
.alert-item.medium{background:rgba(234,179,8,.07); border-color:#eab308}
.alert-item.low   {background:rgba(59,130,246,.07);border-color:#3b82f6}
.ai-head{display:flex;align-items:center;gap:.625rem;padding:.75rem .875rem .4rem;flex-wrap:wrap}
.ai-sev-badge{padding:.2rem .625rem;border-radius:99px;font-size:.65rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;white-space:nowrap;flex-shrink:0}
.sev-severe{background:rgba(239,68,68,.2);color:#fca5a5;border:1px solid rgba(239,68,68,.3)}
.sev-high  {background:rgba(249,115,22,.2);color:#fed7aa;border:1px solid rgba(249,115,22,.3)}
.sev-medium{background:rgba(234,179,8,.2); color:#fde68a;border:1px solid rgba(234,179,8,.3)}
.sev-low   {background:rgba(59,130,246,.2);color:#93c5fd;border:1px solid rgba(59,130,246,.3)}
.ai-title{font-weight:700;font-size:.875rem;color:#f1f5f9;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ai-time{font-size:.7rem;color:var(--muted);white-space:nowrap;flex-shrink:0;margin-left:auto}
.ai-body{padding:.1rem .875rem .45rem}
.ai-message{font-size:.8rem;color:#cbd5e1;line-height:1.55}
.ai-foot{display:flex;align-items:center;justify-content:space-between;padding:.45rem .875rem .65rem;border-top:1px solid rgba(255,255,255,.05);gap:.5rem;flex-wrap:wrap}
.ai-chips{display:flex;gap:.375rem;flex-wrap:wrap}
.ai-chip{padding:.15rem .5rem;border-radius:4px;font-size:.65rem;font-weight:600;background:rgba(59,130,246,.1);color:#93c5fd;border:1px solid rgba(59,130,246,.2)}
.ai-chip.loc {background:rgba(16,185,129,.08);color:#6ee7b7;border-color:rgba(16,185,129,.2)}
.ai-chip.type{background:rgba(234,179,8,.08);color:#fde68a;border-color:rgba(234,179,8,.2)}
.ai-dismiss{padding:.25rem .75rem;border-radius:6px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;font-size:.7rem;font-weight:600;cursor:pointer;font-family:inherit;transition:background .15s;white-space:nowrap;flex-shrink:0}
.ai-dismiss:hover{background:rgba(239,68,68,.22)}
.alert-empty{padding:2rem;text-align:center;color:var(--dim);font-size:.875rem}
.alert-empty svg{width:32px;height:32px;stroke:currentColor;stroke-width:1.5;fill:none;margin:0 auto .75rem;display:block}

.quick-emergency{
  width:100%;padding:1rem;margin-bottom:1.25rem;
  background:linear-gradient(135deg,#b91c1c,#ef4444);
  border:1px solid rgba(239,68,68,.4);border-radius:var(--radius);
  color:#fff;font-size:1rem;font-weight:700;font-family:'Syne',sans-serif;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.625rem;
  transition:all .25s;box-shadow:0 4px 20px rgba(239,68,68,.3);letter-spacing:.01em;
}
.quick-emergency:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(239,68,68,.45)}
.quick-emergency:active{transform:translateY(0)}
.quick-emergency svg{width:20px;height:20px;stroke:currentColor;stroke-width:2.5;fill:none}

.form-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:1.25rem}

label.fl{display:block;font-size:.8125rem;font-weight:600;color:#cbd5e1;margin-bottom:.4rem}
.field-group{margin-bottom:1.125rem}

.form-control{
  width:100%;padding:.7rem .875rem;
  background:rgba(6,13,31,.85);border:1px solid rgba(59,130,246,.2);
  border-radius:10px;color:var(--text);font-size:.9rem;font-family:inherit;
  transition:border-color .2s,box-shadow .2s;
}
.form-control:focus{outline:none;border-color:var(--accent-b);box-shadow:0 0 0 3px rgba(59,130,246,.1)}
.form-control::placeholder{color:#475569}
textarea.form-control{resize:vertical;min-height:100px}

.row2{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.char-c{font-size:.7rem;color:var(--dim);margin-top:.2rem;text-align:right}

.channel-pills{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.4rem}
.channel-pill{
  display:flex;align-items:center;gap:.375rem;padding:.375rem .75rem;
  border-radius:99px;border:1px solid var(--border2);font-size:.8rem;cursor:pointer;
  color:var(--muted);transition:all .2s;
}
.channel-pill input[type=checkbox]{display:none}
.channel-pill:has(input:checked){background:rgba(37,99,235,.18);border-color:var(--accent-b);color:var(--accent-bright)}
.channel-pill svg{width:13px;height:13px;stroke:currentColor;stroke-width:2;fill:none}

.severity-picker{display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;margin-top:.4rem}
.sev-opt{padding:.5rem;text-align:center;border-radius:8px;border:1px solid var(--border);cursor:pointer;font-size:.8rem;font-weight:600;transition:all .2s}
.sev-opt:has(input:checked).low   {background:rgba(59,130,246,.15);border-color:var(--accent-b);color:var(--accent-bright)}
.sev-opt:has(input:checked).medium{background:rgba(234,179,8,.15);border-color:var(--yellow);color:#fde68a}
.sev-opt:has(input:checked).high  {background:rgba(249,115,22,.15);border-color:var(--orange);color:#fed7aa}
.sev-opt:has(input:checked).severe{background:rgba(239,68,68,.15);border-color:var(--red);color:#fca5a5}
.sev-opt input{display:none}

.btn-row{display:flex;gap:.75rem;margin-top:1.25rem}
.btn-send{
  flex:1;padding:.875rem;background:linear-gradient(135deg,#1d4ed8,#2563eb);
  border:none;border-radius:10px;color:#fff;font-size:.9375rem;font-weight:700;
  font-family:'Syne',sans-serif;cursor:pointer;transition:all .25s;
  display:flex;align-items:center;justify-content:center;gap:.5rem;
  box-shadow:0 4px 16px rgba(29,78,216,.35);
}
.btn-send:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(29,78,216,.5)}
.btn-send svg{width:16px;height:16px;stroke:currentColor;stroke-width:2.5;fill:none}
.btn-secondary{
  padding:.875rem 1.25rem;background:rgba(59,130,246,.07);
  border:1px solid var(--border2);border-radius:10px;
  color:var(--accent-bright);font-size:.875rem;font-weight:600;font-family:inherit;cursor:pointer;transition:all .2s;
}
.btn-secondary:hover{background:rgba(59,130,246,.14)}

.sms-preview{
  background:rgba(6,13,31,.9);border:1px solid var(--border);border-radius:12px;overflow:hidden;height:fit-content;
}
.sms-preview-hd{background:linear-gradient(135deg,#1d4ed8,#2563eb);padding:.625rem 1rem;font-size:.75rem;font-weight:700;color:#fff;letter-spacing:.05em;text-transform:uppercase}
.sms-preview-body{padding:1.25rem;font-family:'JetBrains Mono',monospace;font-size:.825rem;color:#cbd5e1;line-height:1.7;min-height:120px}
.sms-preview-chars{padding:.5rem 1rem;border-top:1px solid var(--border);font-size:.7rem;color:var(--dim);display:flex;justify-content:space-between}

.video-intro{
  background:rgba(37,99,235,.07);border:1px solid rgba(37,99,235,.18);
  border-radius:var(--radius);padding:1rem 1.25rem;margin-bottom:1.25rem;
  display:flex;gap:.875rem;align-items:center;font-size:.875rem;color:#93c5fd;
}
.video-intro svg{width:20px;height:20px;stroke:currentColor;stroke-width:2;fill:none;flex-shrink:0}

.videos-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1rem}

.video-card{
  background:rgba(12,24,48,.75);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;
  transition:border-color .25s,transform .25s;
}
.video-card:hover{border-color:var(--border2);transform:translateY(-3px)}

.video-thumb{position:relative;aspect-ratio:16/9;background:#0c1830;cursor:pointer;overflow:hidden}
.video-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.video-thumb-placeholder{
  width:100%;height:100%;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,rgba(29,78,216,.15),rgba(6,13,31,.9));
}
.video-thumb-placeholder svg{width:48px;height:48px;stroke:var(--accent-b);stroke-width:1.5;fill:none;opacity:.6}
.play-overlay{
  position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  background:rgba(6,13,31,.5);opacity:0;transition:opacity .2s;
}
.video-thumb:hover .play-overlay{opacity:1}
.play-btn{
  width:48px;height:48px;background:rgba(37,99,235,.9);border-radius:50%;
  display:flex;align-items:center;justify-content:center;
}
.play-btn svg{width:22px;height:22px;fill:#fff;margin-left:3px}

.severity-tag{
  position:absolute;top:.5rem;left:.5rem;padding:.25rem .6rem;
  border-radius:6px;font-size:.65rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
}
.st-severe{background:rgba(239,68,68,.85);color:#fff}
.st-high  {background:rgba(249,115,22,.85);color:#fff}
.st-medium{background:rgba(234,179,8,.85);color:#000}
.st-low   {background:rgba(37,99,235,.85);color:#fff}

.video-info{padding:.875rem 1rem}
.video-title{font-weight:700;font-size:.875rem;color:#f1f5f9;margin-bottom:.25rem}
.video-desc{font-size:.75rem;color:var(--muted);line-height:1.5}
.video-footer{
  padding:.625rem 1rem;border-top:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
}
.video-duration{font-size:.7rem;color:var(--dim);display:flex;align-items:center;gap:.3rem}
.video-duration svg{width:12px;height:12px;stroke:currentColor;stroke-width:2;fill:none}
.btn-watch{
  padding:.3rem .75rem;background:rgba(37,99,235,.14);border:1px solid rgba(37,99,235,.25);
  border-radius:6px;font-size:.75rem;font-weight:600;color:var(--accent-bright);
  cursor:pointer;font-family:inherit;transition:all .15s;text-decoration:none;display:inline-block;
}
.btn-watch:hover{background:rgba(37,99,235,.25)}

.modal-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(6,13,31,.92);backdrop-filter:blur(12px);
  z-index:1000;align-items:center;justify-content:center;padding:1.5rem;
}
.modal-overlay.open{display:flex}
.modal-box{
  width:100%;max-width:800px;background:var(--surface2);
  border:1px solid var(--border2);border-radius:20px;overflow:hidden;
  animation:modalIn .3s ease;
}
@keyframes modalIn{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:scale(1)}}
.modal-header{
  display:flex;align-items:center;justify-content:space-between;
  padding:1rem 1.25rem;border-bottom:1px solid var(--border);
}
.modal-title{font-family:'Syne',sans-serif;font-weight:700;color:#f1f5f9;font-size:1rem}
.modal-close{
  width:32px;height:32px;border-radius:8px;background:rgba(239,68,68,.1);
  border:1px solid rgba(239,68,68,.2);color:#fca5a5;font-size:1rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:all .15s;
}
.modal-close:hover{background:rgba(239,68,68,.2)}
.modal-body{padding:1.25rem}
.video-embed{position:relative;padding-bottom:56.25%;border-radius:10px;overflow:hidden;background:#000;margin-bottom:1rem}
.video-embed iframe{position:absolute;inset:0;width:100%;height:100%;border:none}
.modal-steps{display:grid;grid-template-columns:1fr 1fr;gap:.625rem;margin-top:.75rem}
.modal-step{
  background:rgba(37,99,235,.06);border:1px solid var(--border);
  border-radius:8px;padding:.75rem;display:flex;gap:.625rem;align-items:flex-start;
}
.step-num{
  width:22px;height:22px;border-radius:50%;background:var(--accent-b);
  display:flex;align-items:center;justify-content:center;
  font-size:.7rem;font-weight:700;color:#fff;flex-shrink:0;margin-top:1px;
}
.step-text{font-size:.8rem;color:#cbd5e1;line-height:1.5}

.settings-grid{display:grid;gap:1.25rem}
.settings-section{background:rgba(12,24,48,.75);border:1px solid var(--border);border-radius:var(--radius);padding:1.25rem 1.5rem}
.settings-section h3{font-family:'Syne',sans-serif;font-size:.9rem;font-weight:700;color:#f1f5f9;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.settings-section h3 svg{width:16px;height:16px;stroke:var(--accent-b);stroke-width:2;fill:none}

.setting-row{display:flex;align-items:center;justify-content:space-between;padding:.75rem 0;border-bottom:1px solid var(--border)}
.setting-row:last-of-type{border-bottom:none;padding-bottom:0}
.setting-info .setting-label{font-weight:600;font-size:.875rem;color:#f1f5f9;margin-bottom:.15rem}
.setting-info .setting-desc{font-size:.75rem;color:var(--muted)}

.toggle{position:relative;width:46px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0;position:absolute}
.toggle-track{
  position:absolute;inset:0;background:rgba(59,130,246,.15);border-radius:12px;
  transition:background .25s;cursor:pointer;
}
.toggle-thumb{
  position:absolute;top:3px;left:3px;width:18px;height:18px;
  background:#fff;border-radius:50%;transition:transform .25s;box-shadow:0 1px 4px rgba(0,0,0,.3);
}
.toggle input:checked ~ .toggle-track{background:var(--accent-b)}
.toggle input:checked ~ .toggle-thumb{transform:translateX(22px)}

.setting-input{
  width:100px;padding:.5rem .75rem;background:rgba(6,13,31,.8);
  border:1px solid var(--border2);border-radius:8px;color:var(--text);
  font-family:'JetBrains Mono',monospace;font-size:.875rem;text-align:center;
}
.setting-input:focus{outline:none;border-color:var(--accent-b)}

.btn-save{
  margin-top:1.5rem;width:100%;padding:1rem;
  background:linear-gradient(135deg,#059669,#10b981);
  border:none;border-radius:10px;color:#fff;font-size:1rem;font-weight:700;
  font-family:'Syne',sans-serif;cursor:pointer;transition:all .25s;
  box-shadow:0 4px 16px rgba(16,185,129,.3);
}
.btn-save:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(16,185,129,.45)}

.save-toast{
  display:none;background:var(--green-bg);border:1px solid var(--green-border);
  color:#6ee7b7;padding:.75rem 1rem;border-radius:10px;margin-bottom:1.25rem;
  font-size:.875rem;align-items:center;gap:.5rem;
}
.save-toast.show{display:flex}
.save-toast svg{width:16px;height:16px;stroke:currentColor;stroke-width:2;fill:none}

.users-table{width:100%;border-collapse:collapse}
.users-table th{text-align:left;padding:.625rem .875rem;font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border)}
.users-table td{padding:.75rem .875rem;font-size:.8125rem;border-bottom:1px solid rgba(59,130,246,.06);vertical-align:middle}
.users-table tr:hover td{background:rgba(59,130,246,.04)}
.user-chip{display:inline-flex;align-items:center;gap:.375rem;padding:.2rem .6rem;border-radius:99px;font-size:.7rem;font-weight:700;letter-spacing:.04em}
.chip-student{background:rgba(37,99,235,.15);color:#93c5fd;border:1px solid rgba(37,99,235,.25)}
.chip-faculty{background:rgba(234,179,8,.15);color:#fde68a;border:1px solid rgba(234,179,8,.25)}
.chip-staff  {background:rgba(16,185,129,.15);color:#6ee7b7;border:1px solid rgba(16,185,129,.25)}
.school-tag{font-size:.7rem;color:var(--dim)}

.history-filter{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap}
.filter-btn{
  padding:.375rem .875rem;border-radius:8px;border:1px solid var(--border);
  background:transparent;color:var(--muted);font-size:.8rem;cursor:pointer;font-family:inherit;transition:all .15s;
}
.filter-btn.active,.filter-btn:hover{background:rgba(59,130,246,.1);border-color:var(--border2);color:var(--accent-bright)}

.history-item{
  border:1px solid var(--border);border-radius:10px;padding:1rem 1.125rem;margin-bottom:.625rem;
  display:flex;gap:1rem;align-items:flex-start;transition:border-color .2s;
}
.history-item:hover{border-color:var(--border2)}
.history-icon{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.history-icon svg{width:18px;height:18px;stroke:#fff;stroke-width:2;fill:none}
.history-title{font-weight:600;font-size:.875rem;color:#f1f5f9;margin-bottom:.2rem}
.history-meta{font-size:.75rem;color:var(--muted);display:flex;gap:.75rem;flex-wrap:wrap}
.history-actions{margin-left:auto;display:flex;gap:.5rem;flex-shrink:0}
.btn-xs{padding:.3rem .75rem;border-radius:6px;font-size:.75rem;font-weight:600;cursor:pointer;font-family:inherit;border:none;transition:all .15s}
.btn-xs-warn{background:rgba(239,68,68,.1);color:#fca5a5;border:1px solid rgba(239,68,68,.2)}
.btn-xs-warn:hover{background:rgba(239,68,68,.2)}
.btn-xs-blue{background:rgba(37,99,235,.1);color:#93c5fd;border:1px solid rgba(37,99,235,.2)}
.btn-xs-blue:hover{background:rgba(37,99,235,.2)}

.logout-wrapper{max-width:480px;margin:0 auto;text-align:center}
.logout-icon{
  width:72px;height:72px;margin:0 auto 1.5rem;
  background:linear-gradient(135deg,#b91c1c,#ef4444);border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 0 32px rgba(239,68,68,.3);
}
.logout-icon svg{width:36px;height:36px;stroke:#fff;stroke-width:2;fill:none}
.logout-wrapper h2{font-family:'Syne',sans-serif;font-size:1.5rem;font-weight:700;margin-bottom:.75rem}
.logout-wrapper p{color:var(--muted);margin-bottom:1.5rem;font-size:.9rem;line-height:1.6}
.session-panel{background:rgba(12,24,48,.8);border:1px solid var(--border);border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;text-align:left}
.srow{display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid var(--border);font-size:.825rem}
.srow:last-child{border-bottom:none}
.slabel{color:var(--muted)} .sval{font-weight:600;color:#f1f5f9}
.logout-btns{display:flex;gap:.75rem}
.btn-logout{
  flex:1;padding:.875rem;background:linear-gradient(135deg,#b91c1c,#ef4444);
  border:none;border-radius:10px;color:#fff;font-weight:700;font-size:.9375rem;
  font-family:'Syne',sans-serif;cursor:pointer;transition:all .25s;
}
.btn-logout:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(239,68,68,.4)}
.btn-cancel-l{
  flex:1;padding:.875rem;background:rgba(59,130,246,.07);border:1px solid var(--border2);
  border-radius:10px;color:var(--accent-bright);font-weight:600;font-size:.9375rem;
  font-family:inherit;cursor:pointer;transition:all .2s;
}
.btn-cancel-l:hover{background:rgba(59,130,246,.14)}

.toast-stack{position:fixed;bottom:1.25rem;right:1.25rem;z-index:500;display:flex;flex-direction:column;gap:.5rem;max-width:340px}
.toast{
  padding:.875rem 1.125rem;border-radius:12px;backdrop-filter:blur(12px);
  display:flex;align-items:center;gap:.75rem;font-size:.875rem;font-weight:500;
  animation:toastIn .3s ease;box-shadow:0 8px 24px rgba(0,0,0,.4);
}
@keyframes toastIn{from{opacity:0;transform:translateX(24px)}to{opacity:1;transform:translateX(0)}}
.toast.success{background:rgba(16,185,129,.15);border:1px solid var(--green-border);color:#6ee7b7}
.toast.error  {background:rgba(239,68,68,.15);border:1px solid var(--red-border);color:#fca5a5}
.toast.info   {background:rgba(37,99,235,.15);border:1px solid rgba(37,99,235,.3);color:#93c5fd}
.toast.warning{background:rgba(234,179,8,.15);border:1px solid var(--yellow-border);color:#fde68a}
.toast svg{width:18px;height:18px;stroke:currentColor;stroke-width:2;fill:none;flex-shrink:0}

@media(max-width:1100px){
  .videos-grid{grid-template-columns:repeat(2,1fr)}
  .stats-row{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:900px){
  .dash-grid{grid-template-columns:1fr}
  .form-grid{grid-template-columns:1fr}
}
@media(max-width:720px){
  :root{--sidebar:0px}
  .sidebar{display:none}
  .videos-grid{grid-template-columns:1fr}
  .modal-steps{grid-template-columns:1fr}
  .stats-row{grid-template-columns:1fr 1fr}
}
</style>
</head>
<body>

<header class="header">
  <div class="header-brand">
    <div class="logo-box">
      <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    </div>
    <div class="brand-text">
      <h1>CEAS</h1>
      <span><?= htmlspecialchars($campus['name']) ?></span>
    </div>
  </div>

  <div class="alert-ticker">
    <span class="ticker-label"><span class="ticker-dot"></span>LIVE</span>
    <div class="ticker-wrap">
      <span class="ticker-content" id="tickerText">
        System operational  No active emergencies  Weather: Loading... System operational No active emergencies  Weather: Loading...
      </span>
    </div>
  </div>

  <div class="user-badge">
    <div class="avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
    <div>
      <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
      <div class="user-role"><?= htmlspecialchars($user['role']) ?></div>
    </div>
  </div>
</header>

<div class="layout">

  <aside class="sidebar">
    <div class="nav-section">
      <div class="nav-label">Main</div>
      <button class="nav-btn active" data-view="dashboard">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
      </button>
      <?php if ($is_admin): ?>
      <button class="nav-btn" data-view="create">
        <svg viewBox="0 0 24 24"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Send Alert
        <span class="nav-badge" id="alertBadge" style="display:none">0</span>
      </button>
      <?php endif; ?>
      <button class="nav-btn" data-view="history">
        <svg viewBox="0 0 24 24"><polyline points="12 8 12 12 14 14"/><circle cx="12" cy="12" r="10"/></svg>
        Alert History
      </button>
    </div>

    <div class="nav-section">
      <div class="nav-label">Resources</div>
      <button class="nav-btn" data-view="videos">
        <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        Emergency Guides
      </button>
      <?php if ($is_admin): ?>
      <button class="nav-btn" data-view="users">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        Registered Users
      </button>
      <?php endif; ?>
    </div>

    <div class="nav-section">
      <div class="nav-label">System</div>
      <?php if ($is_admin): ?>
      <button class="nav-btn" data-view="settings">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        Settings
      </button>
      <?php endif; ?>
      <button class="nav-btn danger" data-view="logout">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Log Out
      </button>
    </div>

    <div class="sidebar-footer">
      <div class="campus-badge">
        <strong><?= htmlspecialchars($campus['name']) ?></strong>
        <?= htmlspecialchars($user['email']) ?>
      </div>
    </div>
  </aside>

  <main class="main">

    <div id="view-dashboard" class="view active">
      <div class="page-title">Dashboard</div>
      <div class="page-sub">Real-time emergency overview for <?= htmlspecialchars($campus['name']) ?></div>

      <?php if ($is_admin): ?>
      <button class="quick-emergency" onclick="switchView('create')">
        <svg viewBox="0 0 24 24"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        BROADCAST EMERGENCY ALERT
      </button>
      <?php endif; ?>

      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon red"><svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
          <div class="stat-body"><div class="stat-label">Active Alerts</div><div class="stat-change ok" id="s-active-sub">System clear</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
          <div class="stat-body"><div class="stat-label">Registered Users</div><div class="stat-change up">+<?= count($reg_users) ?> campus members</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
          <div class="stat-body"><div class="stat-label">System Uptime</div><div class="stat-change ok">All services online</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon orange"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div>
          <div class="stat-body"><div class="stat-label">Alerts Today</div><div class="stat-change ok" id="s-today-sub">No alerts sent</div></div>
        </div>
      </div>

      <div class="dash-grid">
        <!-- Weather -->
        <div>
          <div class="weather-card" id="weatherCard">
            <div class="weather-header">
              <div>
                <div class="weather-location"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg><?= htmlspecialchars($campus['name']) ?></div>
                <div class="weather-status-text" id="weatherCondition">Loading weather...</div>
              </div>
              <div style="text-align:right">
                <div style="font-size:2.5rem;font-family:'JetBrains Mono',monospace;font-weight:700;color:#f1f5f9;line-height:1" id="weatherTemp">--</div>
                <div style="font-size:.75rem;color:var(--muted)">°C</div>
              </div>
            </div>
            <div class="weather-danger" id="weatherDanger">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <span id="weatherDangerText">Checking conditions...</span>
            </div>
            <div class="weather-body">
              <div class="w-metric"><div class="w-metric-val" id="w-wind">--</div><div class="w-metric-unit">mph</div><div class="w-metric-label">Wind Speed</div></div>
              <div class="w-metric"><div class="w-metric-val" id="w-gust">--</div><div class="w-metric-unit">mph</div><div class="w-metric-label">Wind Gusts</div></div>
              <div class="w-metric"><div class="w-metric-val" id="w-humid">--</div><div class="w-metric-unit">%</div><div class="w-metric-label">Humidity</div></div>
            </div>
          </div>

          <!-- Active alerts feed -->
          <div class="card" style="margin-top:1.25rem">
            <div class="card-header">
              <div class="card-title"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>Active Alerts</div>
              <?php if ($is_admin): ?>
              <button onclick="switchView('create')" style="padding:.35rem .875rem;border-radius:8px;background:rgba(37,99,235,.1);border:1px solid rgba(37,99,235,.2);color:#93c5fd;font-size:.8rem;cursor:pointer;font-family:inherit">+ New</button>
              <?php endif; ?>
            </div>
            <div class="alert-feed" id="alertFeed">
              <div class="alert-empty" id="alertEmpty">
                <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                No active alerts. System is clear.
              </div>
            </div>
          </div>
        </div>

        <!-- Right column: quick stats + weather forecast summary -->
        <div>
          <?php if ($is_admin): ?>
          <div class="card">
            <div class="card-header">
              <div class="card-title"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>Current Status</div>
            </div>
            <div id="systemStatusList" style="display:flex;flex-direction:column;gap:.625rem"></div>
          </div>
          <?php endif; ?>

          <div class="card" style="<?= !$is_admin ? 'margin-top:0' : 'margin-top:1.25rem' ?>">
            <div class="card-header">
              <div class="card-title"><svg viewBox="0 0 24 24"><path d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999A5.002 5.002 0 003 15"/></svg>Weather Summary</div>
            </div>
            <div id="weatherSummary" style="font-size:.875rem;color:var(--muted);line-height:1.7">
              Fetching current Grenada weather data...
            </div>
            <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);font-size:.75rem;color:var(--dim)">
              Data from Open-Meteo API • Updated every 5 minutes
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($is_admin): ?>
    <div id="view-create" class="view">
      <div class="page-title">Send Emergency Alert</div>
      <div class="page-sub">Broadcast an emergency to <?= htmlspecialchars($campus['name']) ?> community</div>

      <div class="form-grid">
        <div class="card">
          <div class="field-group">
            <label class="fl">Alert Title *</label>
            <input type="text" class="form-control" id="aTitle" placeholder="Brief, clear title (e.g. Fire Alarm – Main Building)" maxlength="80">
          </div>
          <div class="row2">
            <div class="field-group">
              <label class="fl">Emergency Type</label>
              <select class="form-control" id="aType">
                <option value="fire">🔥 Fire Emergency</option>
                <option value="hurricane">🌀 Hurricane / Storm</option>
                <option value="earthquake">🫨 Earthquake</option>
                <option value="medical">🏥 Medical Emergency</option>
                <option value="lockdown">🔒 Campus Lockdown</option>
                <option value="weather">⛈️ Severe Weather</option>
                <option value="security">🚨 Security Threat</option>
                <option value="chemical">☣️ Chemical/Hazmat</option>
                <option value="tsunami">🌊 Tsunami Warning</option>
                <option value="all_clear">✅ All Clear</option>
              </select>
            </div>
            <div class="field-group">
              <label class="fl">Campus / Location</label>
              <select class="form-control" id="aLocation">
                <option value="tamcc_stgeorge">TAMCC – St. George's</option>
                <option value="tamcc_grenville">TAMCC – Grenville</option>
                <option value="tamcc_carriacou">TAMCC – Carriacou</option>
                <option value="sgu">St. George's University</option>
                <option value="gnbs">GNBS</option>
                <option value="all">All Grenada Campuses</option>
              </select>
            </div>
          </div>
          <div class="field-group">
            <label class="fl">Severity Level</label>
            <div class="severity-picker">
              <label class="sev-opt low">  <input type="radio" name="severity" value="low">    Low</label>
              <label class="sev-opt medium"><input type="radio" name="severity" value="medium" checked>Medium</label>
              <label class="sev-opt high">  <input type="radio" name="severity" value="high">  High</label>
              <label class="sev-opt severe"><input type="radio" name="severity" value="severe">Severe</label>
            </div>
          </div>
          <div class="field-group">
            <label class="fl">Alert Message *</label>
            <textarea class="form-control" id="aMessage" rows="4" placeholder="Provide clear instructions. Include: what is happening, where, and what people should do." maxlength="500"></textarea>
            <div class="char-c"><span id="charCount">0</span>/500 chars &nbsp;|&nbsp; SMS cuts at 160</div>
          </div>
          <div class="field-group">
            <label class="fl">Target Groups</label>
            <div class="channel-pills">
              <label class="channel-pill"><input type="checkbox" id="tStudents" checked><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Students</label>
              <label class="channel-pill"><input type="checkbox" id="tFaculty" checked><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Faculty</label>
              <label class="channel-pill"><input type="checkbox" id="tStaff" checked><svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/></svg>Staff</label>
            </div>
          </div>
          <div class="field-group">
            <label class="fl">Delivery Channels</label>
            <div class="channel-pills">
              <label class="channel-pill"><input type="checkbox" id="cSMS" <?= $s['sms_enabled']?'checked':'' ?>><svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 12.79 19.79 19.79 0 01.21 4.16 2 2 0 012.18 2H5a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>SMS</label>
              <label class="channel-pill"><input type="checkbox" id="cEmail" <?= $s['email_enabled']?'checked':'' ?>><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>Email</label>
              <label class="channel-pill"><input type="checkbox" id="cPush" <?= $s['push_enabled']?'checked':'' ?>><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>Push</label>
              <label class="channel-pill"><input type="checkbox" id="cWeb" checked><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/></svg>Web</label>
            </div>
          </div>
          <div class="row2" style="align-items:end">
            <div class="field-group">
              <label class="fl" style="display:flex;align-items:center;gap:.5rem"><input type="checkbox" id="sendNow" checked style="accent-color:var(--accent-b);width:auto"> Send Immediately</label>
            </div>
            <div class="field-group" id="scheduleWrap" style="display:none">
              <label class="fl">Schedule Time</label>
              <input type="datetime-local" class="form-control" id="scheduleTime">
            </div>
          </div>
          <div class="btn-row">
            <button class="btn-send" id="sendBtn">
              <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
              Broadcast Alert
            </button>
            <button class="btn-secondary" id="draftBtn">Save Draft</button>
          </div>
        </div>
        <div>
          <div class="sms-preview">
            <div class="sms-preview-hd">📱 SMS Preview</div>
            <div class="sms-preview-body" id="smsPreview">
              [CEAS ALERT] &lt;Title&gt;<br>
              &lt;Your message will appear here...&gt;<br><br>
              — <?= htmlspecialchars($campus['name']) ?>
            </div>
            <div class="sms-preview-chars">
              <span id="smsChars">0 / 160 chars</span>
              <span id="smsParts">1 part</span>
            </div>
          </div>
          <div class="card" style="margin-top:1rem">
            <div class="card-title" style="margin-bottom:.875rem;font-size:.8125rem"><svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:var(--accent-b);stroke-width:2.5;fill:none;margin-right:.4rem"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>BROWSER NOTIFICATION PREVIEW</div>
            <div style="background:rgba(6,13,31,.9);border:1px solid var(--border);border-radius:10px;padding:.875rem;display:flex;gap:.75rem">
              <div style="width:36px;height:36px;background:linear-gradient(135deg,#1d4ed8,#ef4444);border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center">
                <svg viewBox="0 0 24 24" style="width:18px;height:18px;stroke:#fff;stroke-width:2;fill:none"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
              </div>
              <div style="flex:1">
                <div style="font-weight:600;font-size:.875rem;color:#f1f5f9;margin-bottom:.2rem" id="notifTitle">CEAS Alert</div>
                <div style="font-size:.8rem;color:var(--muted)" id="notifBody">Your message preview...</div>
                <div style="font-size:.7rem;color:var(--dim);margin-top:.25rem"><?= htmlspecialchars($campus['name']) ?> • Now</div>
              </div>
            </div>
          </div>
          <div class="card" style="margin-top:1rem">
            <div style="font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:.75rem">Alert Checklist</div>
            <div id="alertChecklist" style="display:flex;flex-direction:column;gap:.5rem;font-size:.8rem"></div>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div id="view-videos" class="view">
      <div class="page-title">Emergency Guides</div>
      <div class="page-sub">Video procedures and step-by-step guides for every campus emergency scenario</div>
      <div class="video-intro">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        <span>Click any guide to view the full video and step-by-step emergency procedures. Share these with students and staff for preparedness training.</span>
      </div>
      <div class="videos-grid" id="videosGrid"></div>
    </div>

    <div id="view-history" class="view">
      <div class="page-title">Alert History</div>
      <div class="page-sub">Record of all sent alerts for <?= htmlspecialchars($campus['name']) ?></div>
      <div class="card">
        <div class="history-filter">
          <button class="filter-btn active" onclick="filterHistory('all',this)">All</button>
          <button class="filter-btn" onclick="filterHistory('severe',this)">Severe</button>
          <button class="filter-btn" onclick="filterHistory('high',this)">High</button>
          <button class="filter-btn" onclick="filterHistory('medium',this)">Medium</button>
          <button class="filter-btn" onclick="filterHistory('low',this)">Low</button>
        </div>
        <div id="historyList"></div>
      </div>
    </div>

    <?php if ($is_admin): ?>
    <div id="view-users" class="view">
      <div class="page-title">Registered Users</div>
      <div class="page-sub"><?= count($reg_users) ?> campus members registered across Grenada</div>
      <div class="card">
        <?php if (empty($reg_users)): ?>
          <div style="text-align:center;padding:2.5rem;color:var(--muted);font-size:.9rem">
            <svg viewBox="0 0 24 24" style="width:40px;height:40px;stroke:currentColor;stroke-width:1.5;fill:none;margin:0 auto .875rem;display:block"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            No users registered yet. Share the registration link with campus members.
          </div>
        <?php else: ?>
          <table class="users-table">
            <thead><tr><th>Name</th><th>Email</th><th>Campus</th><th>Type</th><th>ID</th><th>Registered</th></tr></thead>
            <tbody>
            <?php foreach ($reg_users as $u): ?>
              <tr>
                <td style="font-weight:600"><?= htmlspecialchars($u['full_name']) ?></td>
                <td style="color:var(--muted)"><?= htmlspecialchars($u['email']) ?></td>
                <td><span class="school-tag"><?= htmlspecialchars($u['school_name'] ?? $u['campus'] ?? '—') ?></span></td>
                <td><span class="user-chip chip-<?= htmlspecialchars($u['user_type'] ?? 'student') ?>"><?= ucfirst(htmlspecialchars($u['user_type'] ?? 'student')) ?></span></td>
                <td style="font-family:'JetBrains Mono',monospace;font-size:.75rem;color:var(--dim)"><?= htmlspecialchars($u['student_id'] ?? '—') ?></td>
                <td style="color:var(--dim);font-size:.75rem"><?= htmlspecialchars(date('M d, Y', strtotime($u['registered'] ?? date('Y-m-d')))) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($is_admin): ?>
    <div id="view-settings" class="view">
      <div class="page-title">System Settings</div>
      <div class="page-sub">Configure CEAS for your campus and notification preferences</div>
      <?php if ($settings_saved): ?>
      <div class="save-toast show" id="saveToast" style="display:flex!important">
        <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        Settings saved successfully!
      </div>
      <?php endif; ?>
      <form method="POST" action="">
        <div class="settings-grid">
          <div class="settings-section">
            <h3><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>Campus Configuration</h3>
            <div class="setting-row">
              <div class="setting-info"><div class="setting-label">Active Campus</div><div class="setting-desc">Alerts are targeted to this campus</div></div>
              <select name="campus_key" class="form-control" style="width:220px">
                <?php foreach([
                  'tamcc_stgeorge'=>"TAMCC St. George's",
                  'tamcc_grenville'=>'TAMCC Grenville',
                  'tamcc_carriacou'=>'TAMCC Carriacou',
                  'sgu'=>"St. George's University",
                  'gnbs'=>'GNBS','other'=>'Other'
                ] as $k=>$n): ?>
                <option value="<?=$k?>" <?=($s['campus_key']===$k?'selected':'')?>>
                  <?=htmlspecialchars($n)?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="setting-row">
              <div class="setting-info"><div class="setting-label">Admin Name</div></div>
              <input type="text" name="admin_name" value="<?= htmlspecialchars($s['admin_name']) ?>" class="form-control" style="width:220px">
            </div>
            <div class="setting-row">
              <div class="setting-info"><div class="setting-label">Admin Email</div></div>
              <input type="email" name="admin_email" value="<?= htmlspecialchars($s['admin_email']) ?>" class="form-control" style="width:220px">
            </div>
            <div class="setting-row">
              <div class="setting-info"><div class="setting-label">Timezone</div></div>
              <select name="timezone" class="form-control" style="width:220px">
                <option value="America/Grenada" <?= $s['timezone']==='America/Grenada'?'selected':'' ?>>Grenada (AST -4)</option>
                <option value="America/Barbados" <?= $s['timezone']==='America/Barbados'?'selected':'' ?>>Barbados</option>
                <option value="America/New_York" <?= $s['timezone']==='America/New_York'?'selected':'' ?>>New York (EST)</option>
              </select>
            </div>
          </div>
          <div class="settings-section">
            <h3><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>Notification Channels</h3>
            <div class="setting-row"><div class="setting-info"><div class="setting-label">SMS Alerts</div><div class="setting-desc">Text message to registered phones</div></div><label class="toggle"><input type="checkbox" name="sms_enabled" <?= $s['sms_enabled']?'checked':'' ?>><span class="toggle-track"></span><span class="toggle-thumb"></span></label></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-label">Email Alerts</div><div class="setting-desc">Email to campus addresses</div></div><label class="toggle"><input type="checkbox" name="email_enabled" <?= $s['email_enabled']?'checked':'' ?>><span class="toggle-track"></span><span class="toggle-thumb"></span></label></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-label">Browser Push Notifications</div><div class="setting-desc">Real-time alerts via Web Push API</div></div><label class="toggle"><input type="checkbox" name="push_enabled" <?= $s['push_enabled']?'checked':'' ?>><span class="toggle-track"></span><span class="toggle-thumb"></span></label></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-label">Weather Danger Alerts</div><div class="setting-desc">Auto-alert when dangerous weather detected</div></div><label class="toggle"><input type="checkbox" name="weather_alerts" <?= $s['weather_alerts']?'checked':'' ?>><span class="toggle-track"></span><span class="toggle-thumb"></span></label></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-label">Notification Sound</div><div class="setting-desc">Play alert sound in browser</div></div><label class="toggle"><input type="checkbox" name="notification_sound" <?= $s['notification_sound']?'checked':'' ?>><span class="toggle-track"></span><span class="toggle-thumb"></span></label></div>
          </div>
          <div class="settings-section">
            <h3><svg viewBox="0 0 24 24"><polyline points="12 8 12 12 14 14"/><circle cx="12" cy="12" r="10"/></svg>Data &amp; Retention</h3>
            <div class="setting-row"><div class="setting-info"><div class="setting-label">Auto-Archive After</div><div class="setting-desc">Days before alerts auto-archive</div></div><input type="number" name="auto_archive_days" value="<?= (int)$s['auto_archive_days'] ?>" class="setting-input" min="1" max="365"></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-label">Alert Retention Period</div><div class="setting-desc">Days to keep history</div></div><input type="number" name="alert_retention" value="<?= (int)$s['alert_retention'] ?>" class="setting-input" min="30" max="365"></div>
            <div class="setting-row"><div class="setting-info"><div class="setting-label">Max Recipients Per Alert</div></div><input type="number" name="max_recipients" value="<?= (int)$s['max_recipients'] ?>" class="setting-input" min="100" max="50000"></div>
          </div>
        </div>
        <button type="submit" name="save_settings" class="btn-save">Save All Settings</button>
      </form>
    </div>
    <?php endif; ?>

    <div id="view-logout" class="view">
      <div class="logout-wrapper">
        <div class="logout-icon"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></div>
        <h2>Sign Out</h2>
        <p>You're about to leave CEAS. The system will continue monitoring and broadcasting alerts.</p>
        <div class="session-panel">
          <div class="srow"><span class="slabel">User</span><span class="sval"><?= htmlspecialchars($user['name']) ?></span></div>
          <div class="srow"><span class="slabel">Campus</span><span class="sval"><?= htmlspecialchars($campus['name']) ?></span></div>
          <div class="srow"><span class="slabel">Role</span><span class="sval"><?= htmlspecialchars($user['role']) ?></span></div>
          <div class="srow"><span class="slabel">Login Time</span><span class="sval"><?= date('M d, Y g:i A', strtotime($user['login_time'])) ?></span></div>
        </div>
        <div class="logout-btns">
          <button class="btn-logout" onclick="window.location.href='?action=logout'">Confirm Sign Out</button>
          <button class="btn-cancel-l" onclick="switchView('dashboard')">Stay Logged In</button>
        </div>
      </div>
    </div>

  </main>
</div>

<div id="videoModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title" id="modalTitle">Emergency Procedure</div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="video-embed" id="videoEmbed"></div>
      <div id="modalSteps" class="modal-steps"></div>
    </div>
  </div>
</div>

<div class="toast-stack" id="toastStack"></div>

<script>
const CAMPUS = {
  lat: <?= $campus['lat'] ?>,
  lon: <?= $campus['lon'] ?>,
  name: <?= json_encode($campus['name']) ?>
};
const STORAGE_KEY = 'ceas_alerts_v2';
const isAdmin = <?= json_encode($is_admin) ?>;

// ── Emergency video data ─────────────────────────────────────────────
const VIDEOS = [
  {
    id: 'fire',
    title: 'Fire Emergency Evacuation',
    desc: 'How to safely evacuate during a campus fire. Follow fire wardens and proceed to designated assembly points.',
    severity: 'severe',
    duration: '9:39',
    youtubeId: 'TuMWWquiqV0',
    steps: [
      'Activate the nearest fire alarm immediately',
      'Call 911 (Grenada Emergency Services)',
      'Evacuate via nearest stairwell — NEVER use elevators',
      'Close doors behind you to contain fire/smoke',
      'Proceed to assigned Assembly Point',
      'Do not re-enter until fire services declare all-clear',
    ]
  },
  {
    id: 'hurricane',
    title: 'Hurricane & Storm Preparedness',
    desc: 'Caribbean hurricane safety for students and faculty. Shelter-in-place protocol and evacuation routes.',
    severity: 'severe',
    duration: '1:40',
    youtubeId: 'nA6lrH3V18E',
    steps: [
      'Monitor Grenada Meteorological Service for alerts',
      'Move to designated hurricane shelter on campus',
      'Stay away from windows and glass doors',
      'Secure or bring inside all loose outdoor items',
      'Keep emergency kit: water, food, radio, medication',
      'Await all-clear from campus authorities before leaving',
    ]
  },
  {
    id: 'earthquake',
    title: 'Earthquake — Drop, Cover, Hold On',
    desc: 'Immediate response to seismic activity. Grenada is in an active seismic zone — know what to do.',
    severity: 'high',
    duration: '0:16',
    youtubeId: 't36YzCnmjEU',
    steps: [
      'DROP to hands and knees immediately',
      'Take COVER under a sturdy table or desk',
      'HOLD ON until shaking stops — protect head & neck',
      'Stay inside until shaking completely stops',
      'Evacuate calmly once shaking ends',
      'Watch for aftershocks and stay away from damaged structures',
    ]
  },
  {
    id: 'medical',
    title: 'Medical Emergency First Response',
    desc: 'Immediate steps when a student or faculty member requires urgent medical attention on campus.',
    severity: 'high',
    duration: '4:03',
    youtubeId: 'ea1RJUOiNfQ',
    steps: [
      'Call 911 (Grenada) or campus security immediately',
      'Stay with the person — do NOT leave them alone',
      'If unconscious and not breathing, start CPR if trained',
      'Send someone to guide emergency services to location',
      'Do NOT move the person unless they are in immediate danger',
      'Provide first aid within your training level only',
    ]
  },
  {
    id: 'tsunami',
    title: 'Tsunami / Coastal Flood Warning',
    desc: 'Grenada coastal emergency procedures. Move to high ground immediately when a tsunami warning is issued.',
    severity: 'severe',
    duration: '8:19',
    youtubeId: 'fQAciMgl-kM',
    steps: [
      'Upon warning: move IMMEDIATELY to high ground',
      'Do not wait to see the wave — move fast',
      'Stay at least 100 feet above sea level',
      'Do not return to coast until official all-clear',
      'Avoid flood water — it may be contaminated',
      'Help others evacuate — especially elderly/disabled',
    ]
  },
];

function loadAlerts() {
  try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); }
  catch { return []; }
}
function saveAlerts(alerts) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(alerts));
}

function switchView(name) {
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  const target = document.getElementById('view-' + name);
  if (target) target.classList.add('active');
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  document.querySelector(`.nav-btn[data-view="${name}"]`)?.classList.add('active');
}

document.querySelectorAll('.nav-btn[data-view]').forEach(btn => {
  btn.addEventListener('click', () => switchView(btn.dataset.view));
});

function toast(msg, type = 'info', dur = 4000) {
  const icons = {
    success: '<polyline points="20 6 9 17 4 12"/>',
    error: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    warning: '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
  };
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<svg viewBox="0 0 24 24">${icons[type]||icons.info}</svg><span>${msg}</span>`;
  document.getElementById('toastStack').appendChild(el);
  setTimeout(() => el.remove(), dur);
}

async function requestNotifPermission() {
  if (!('Notification' in window)) return false;
  if (Notification.permission === 'granted') return true;
  const perm = await Notification.requestPermission();
  return perm === 'granted';
}

function sendBrowserNotif(title, body, sev) {
  if (Notification.permission !== 'granted') return;
  const n = new Notification(`🚨 CEAS: ${title}`, {
    body,
    icon: '/favicon.ico',
    badge: '/favicon.ico',
    tag: 'ceas-' + Date.now(),
    requireInteraction: sev === 'severe' || sev === 'high',
    vibrate: [200, 100, 200, 100, 400],
  });
  n.onclick = () => { window.focus(); n.close(); };
}

function playAlertSound(sev) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const freqs = {severe:[880,660,880],high:[660,550],medium:[440],low:[330]};
    const seq = freqs[sev] || freqs.medium;
    seq.forEach((f, i) => {
      const osc = ctx.createOscillator();
      const g = ctx.createGain();
      osc.connect(g); g.connect(ctx.destination);
      osc.type = sev === 'severe' ? 'sawtooth' : 'sine';
      osc.frequency.value = f;
      const t = ctx.currentTime + i * 0.35;
      g.gain.setValueAtTime(0, t);
      g.gain.linearRampToValueAtTime(0.4, t + 0.05);
      g.gain.exponentialRampToValueAtTime(0.001, t + 0.3);
      osc.start(t); osc.stop(t + 0.35);
    });
  } catch {}
}

document.getElementById('sendBtn')?.addEventListener('click', async () => {
  if (!isAdmin) { toast('Access denied. Only administrators can send alerts.', 'error'); return; }
  const title = document.getElementById('aTitle').value.trim();
  const message = document.getElementById('aMessage').value.trim();
  const type = document.getElementById('aType').value;
  const loc = document.getElementById('aLocation').value;
  const sev = document.querySelector('input[name="severity"]:checked')?.value || 'medium';
  if (!title) { toast('Please enter an alert title.', 'error'); return; }
  if (!message) { toast('Please enter an alert message.', 'error'); return; }
  const channels = [];
  if (document.getElementById('cSMS').checked) channels.push('SMS');
  if (document.getElementById('cEmail').checked) channels.push('Email');
  if (document.getElementById('cPush').checked) channels.push('Push');
  if (document.getElementById('cWeb').checked) channels.push('Web');
  if (!channels.length) { toast('Select at least one delivery channel.', 'error'); return; }
  const alert = {
    id: Date.now(),
    title, message, type, severity: sev,
    location: loc, channels,
    campus: CAMPUS.name,
    timestamp: new Date().toISOString(),
    status: 'active',
    sentBy: <?= json_encode($user['name']) ?>
  };
  const alerts = loadAlerts();
  alerts.unshift(alert);
  saveAlerts(alerts);
  const hasPerm = await requestNotifPermission();
  if (hasPerm) sendBrowserNotif(title, message, sev);
  playAlertSound(sev);
  updateAlertFeed();
  updateStats();
  toast(`✅ Alert broadcast via ${channels.join(', ')}`, 'success', 5000);
  document.getElementById('aTitle').value = '';
  document.getElementById('aMessage').value = '';
  document.getElementById('charCount').textContent = '0';
  updateSMSPreview();
  updateChecklist();
  switchView('dashboard');
});

document.getElementById('draftBtn')?.addEventListener('click', () => {
  const title = document.getElementById('aTitle').value.trim();
  if (!title) { toast('Enter a title to save draft.', 'warning'); return; }
  toast('Draft saved locally.', 'info');
});

document.getElementById('sendNow')?.addEventListener('change', function() {
  document.getElementById('scheduleWrap').style.display = this.checked ? 'none' : 'block';
});

function updateSMSPreview() {
  const title = document.getElementById('aTitle')?.value || '<Title>';
  const message = document.getElementById('aMessage')?.value || '<Message>';
  const preview = `[CEAS ALERT] ${title}\n${message.slice(0,160)}\n— ${CAMPUS.name}`;
  const smsElem = document.getElementById('smsPreview');
  if (smsElem) smsElem.textContent = preview;
  const notifTitle = document.getElementById('notifTitle');
  const notifBody = document.getElementById('notifBody');
  if (notifTitle) notifTitle.textContent = `CEAS: ${title}`;
  if (notifBody) notifBody.textContent = message.slice(0, 100);
  const total = preview.length;
  const charsSpan = document.getElementById('smsChars');
  if (charsSpan) charsSpan.textContent = `${Math.min(total,160)} / 160 chars`;
  const partsSpan = document.getElementById('smsParts');
  if (partsSpan) partsSpan.textContent = `${Math.ceil(total/160)} part${total>160?'s':''}`;
}

const titleInput = document.getElementById('aTitle');
const msgInput = document.getElementById('aMessage');
if (titleInput) titleInput.addEventListener('input', () => { updateSMSPreview(); updateChecklist(); });
if (msgInput) msgInput.addEventListener('input', function() {
  const countSpan = document.getElementById('charCount');
  if (countSpan) countSpan.textContent = this.value.length;
  updateSMSPreview(); updateChecklist();
});

function updateChecklist() {
  const title = document.getElementById('aTitle')?.value.trim();
  const msg = document.getElementById('aMessage')?.value.trim();
  const chs = ['cSMS','cEmail','cPush','cWeb'].filter(id => document.getElementById(id)?.checked);
  const items = [
    { ok: (title?.length > 3), text: 'Alert title filled in' },
    { ok: (msg?.length > 10), text: 'Message is descriptive' },
    { ok: (msg?.length <= 160), text: 'SMS-friendly length (≤160 chars)' },
    { ok: (chs.length > 0), text: 'At least one channel selected' },
    { ok: (chs.length > 1), text: 'Multiple channels selected' },
  ];
  const checklistDiv = document.getElementById('alertChecklist');
  if (checklistDiv) {
    checklistDiv.innerHTML = items.map(({ok,text}) =>
      `<div style="display:flex;align-items:center;gap:.5rem;color:${ok?'#6ee7b7':'#64748b'}">
        <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;stroke-width:2.5;fill:none">
          ${ok ? '<polyline points="20 6 9 17 4 12"/>' : '<circle cx="12" cy="12" r="10"/>'}
        </svg>${text}</div>`
    ).join('');
  }
}

function severityIcon(sev) {
  const icons = {
    severe: '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    high:   '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    medium: '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>',
    low:    '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
  };
  return icons[sev] || icons.medium;
}

function updateAlertFeed() {
  const alerts = loadAlerts().filter(a => a.status === 'active').slice(0, 8);
  const feed = document.getElementById('alertFeed');
  const empty = document.getElementById('alertEmpty');
  if (!feed) return;
  Array.from(feed.querySelectorAll('.alert-item')).forEach(el => el.remove());
  if (!alerts.length) { if (empty) empty.style.display = ''; return; }
  if (empty) empty.style.display = 'none';
  const sevLabel = { severe:'&#x1F534; Severe', high:'&#x1F7E0; High', medium:'&#x1F7E1; Medium', low:'&#x1F535; Low' };
  alerts.forEach(a => {
    const el = document.createElement('div');
    el.className = `alert-item ${a.severity}`;
    el.dataset.id = a.id;
    const t = new Date(a.timestamp);
    const timeStr = t.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    const dateStr = t.toLocaleDateString([], {month:'short', day:'numeric'});
    const msg = (a.message || '').slice(0, 120) + ((a.message||'').length > 120 ? '\u2026' : '');
    const channels = (a.channels || []).join(' \u00b7 ') || 'No channel';
    const typeFmt = a.type ? a.type.charAt(0).toUpperCase() + a.type.slice(1) : 'Alert';
    const locFmt = a.location || 'Campus';
    const msgHtml = msg ? `<div class="ai-body"><div class="ai-message">${escHtml(msg)}</div></div>` : '';
    el.innerHTML = `
      <div class="ai-head">
        <span class="ai-sev-badge sev-${a.severity}">${sevLabel[a.severity]||a.severity}</span>
        <span class="ai-title">${escHtml(a.title)}</span>
        <span class="ai-time">${dateStr} \u00b7 ${timeStr}</span>
      </div>
      ${msgHtml}
      <div class="ai-foot">
        <div class="ai-chips">
          <span class="ai-chip type">${escHtml(typeFmt)}</span>
          <span class="ai-chip loc">\ud83d\udccd ${escHtml(locFmt)}</span>
          <span class="ai-chip">${escHtml(channels)}</span>
        </div>
        <button class="ai-dismiss" onclick="dismissAlert(${a.id})">Dismiss</button>
      </div>`;
    feed.insertBefore(el, empty);
  });
}

function dismissAlert(id) {
  const alerts = loadAlerts().map(a => a.id == id ? {...a, status:'dismissed'} : a);
  saveAlerts(alerts);
  updateAlertFeed();
  updateStats();
  toast('Alert dismissed.', 'info');
}
window.dismissAlert = dismissAlert;

function updateStats() {
  const alerts = loadAlerts();
  const active = alerts.filter(a => a.status === 'active').length;
  const today = alerts.filter(a => new Date(a.timestamp).toDateString() === new Date().toDateString()).length;
  const activeSub = document.getElementById('s-active-sub');
  if (activeSub) activeSub.textContent = active > 0 ? `${active} alert${active>1?'s':''} in effect` : 'System clear';
  const todaySub = document.getElementById('s-today-sub');
  if (todaySub) todaySub.textContent = today > 0 ? `${today} sent today` : 'No alerts sent';
  const badge = document.getElementById('alertBadge');
  if (badge) { if (active > 0) { badge.style.display = ''; badge.textContent = active; } else { badge.style.display = 'none'; } }
  const statusList = document.getElementById('systemStatusList');
  if (statusList) {
    const statuses = [
      { name: 'SMS Gateway', ok: true },
      { name: 'Email Server', ok: true },
      { name: 'Push Service', ok: 'Notification' in window },
      { name: 'Weather Feed', ok: true },
      { name: 'Database / Storage', ok: true },
    ];
    statusList.innerHTML = statuses.map(s =>
      `<div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--border);font-size:.8125rem">
        <span style="color:#f1f5f9">${s.name}</span>
        <span style="color:${s.ok?'#6ee7b7':'#fca5a5'};font-weight:600;font-size:.75rem">${s.ok?'✓ Online':'✗ Offline'}</span>
      </div>`
    ).join('');
  }
}

let historyFilter = 'all';
function filterHistory(f, btn) {
  historyFilter = f;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  renderHistory();
}
window.filterHistory = filterHistory;

function renderHistory() {
  const alerts = loadAlerts().filter(a => historyFilter === 'all' || a.severity === historyFilter);
  const list = document.getElementById('historyList');
  if (!list) return;
  if (!alerts.length) { list.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--muted);font-size:.875rem">No alerts found.</div>'; return; }
  const typeColors = { severe:'red', high:'orange', medium:'orange', low:'blue' };
  list.innerHTML = alerts.map(a => {
    const t = new Date(a.timestamp);
    return `<div class="history-item">
      <div class="history-icon stat-icon ${typeColors[a.severity]||'blue'}"><svg viewBox="0 0 24 24">${severityIcon(a.severity)}</svg></div>
      <div style="flex:1">
        <div class="history-title">${escHtml(a.title)}</div>
        <div class="history-meta">
          <span>${escHtml(a.type)}</span>
          <span>${escHtml(a.severity?.toUpperCase())}</span>
          <span>${escHtml(a.location)}</span>
          <span>${t.toLocaleString()}</span>
          <span>By: ${escHtml(a.sentBy||'Admin')}</span>
        </div>
        <div style="font-size:.75rem;color:#64748b;margin-top:.25rem">${escHtml(a.message?.slice(0,100))}${a.message?.length>100?'…':''}</div>
      </div>
      <div class="history-actions">
        <span class="btn-xs" style="background:rgba(59,130,246,.1);color:#93c5fd;border:1px solid rgba(59,130,246,.2);padding:.3rem .75rem;border-radius:6px;font-size:.75rem;font-weight:600">${a.status||'sent'}</span>
      </div>
    </div>`;
  }).join('');
}

function renderVideos() {
  const grid = document.getElementById('videosGrid');
  if (!grid) return;
  grid.innerHTML = VIDEOS.map(v => `
    <div class="video-card">
      <div class="video-thumb" onclick="openVideo('${v.id}')">
        <img src="https://img.youtube.com/vi/${v.youtubeId}/hqdefault.jpg"
             alt="${escHtml(v.title)}"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div class="video-thumb-placeholder" style="display:none">
          <svg viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        </div>
        <div class="play-overlay">
          <div class="play-btn"><svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:white"><polygon points="5 3 19 12 5 21 5 3"/></svg></div>
        </div>
        <span class="severity-tag st-${v.severity}">${v.severity}</span>
      </div>
      <div class="video-info">
        <div class="video-title">${escHtml(v.title)}</div>
        <div class="video-desc">${escHtml(v.desc)}</div>
      </div>
      <div class="video-footer">
        <span class="video-duration"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>${v.duration}</span>
        <button class="btn-watch" onclick="openVideo('${v.id}')">▶ Watch Guide</button>
      </div>
    </div>
  `).join('');
}

function openVideo(id) {
  const v = VIDEOS.find(x => x.id === id);
  if (!v) return;
  document.getElementById('modalTitle').textContent = v.title;
  document.getElementById('videoEmbed').innerHTML = `
    <iframe src="https://www.youtube-nocookie.com/embed/${v.youtubeId}?autoplay=1&rel=0"
            allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>`;
  document.getElementById('modalSteps').innerHTML = v.steps.map((s,i) =>
    `<div class="modal-step"><div class="step-num">${i+1}</div><div class="step-text">${escHtml(s)}</div></div>`
  ).join('');
  document.getElementById('videoModal').classList.add('open');
}
window.openVideo = openVideo;

function closeModal() {
  document.getElementById('videoModal').classList.remove('open');
  document.getElementById('videoEmbed').innerHTML = '';
}
window.closeModal = closeModal;

document.getElementById('videoModal')?.addEventListener('click', e => {
  if (e.target === e.currentTarget) closeModal();
});

const WMO = {
  0:'Clear Sky',1:'Mainly Clear',2:'Partly Cloudy',3:'Overcast',
  45:'Foggy',48:'Icy Fog',
  51:'Light Drizzle',53:'Drizzle',55:'Heavy Drizzle',
  61:'Light Rain',63:'Rain',65:'Heavy Rain',
  71:'Light Snow',73:'Snow',75:'Heavy Snow',
  80:'Light Showers',81:'Showers',82:'Heavy Showers',
  95:'Thunderstorm',96:'Thunderstorm + Hail',99:'Severe Thunderstorm',
};

function weatherDanger(code, wind, gusts) {
  if (gusts >= 74 || code >= 96)  return { level:'danger',  text:'⚠️ DANGER: Hurricane-force conditions. Seek shelter immediately!', cls:'danger-danger' };
  if (gusts >= 39 || code === 95) return { level:'warning', text:'⚠️ WARNING: Tropical Storm conditions. Avoid outdoor activities.', cls:'danger-warning' };
  if (wind >= 20  || (code >= 61 && code <= 82)) return { level:'caution', text:'⚡ CAUTION: Adverse weather conditions. Stay alert.', cls:'danger-caution' };
  return { level:'safe', text:'✅ SAFE: Weather conditions are normal for campus activities.', cls:'danger-safe' };
}

async function loadWeather() {
  try {
    const url = `https://api.open-meteo.com/v1/forecast?latitude=${CAMPUS.lat}&longitude=${CAMPUS.lon}&current=temperature_2m,relative_humidity_2m,wind_speed_10m,wind_gusts_10m,weathercode,precipitation&wind_speed_unit=mph&forecast_days=1`;
    const res = await fetch(url);
    const data = await res.json();
    const c = data.current;
    const code = c.weathercode;
    const temp = Math.round(c.temperature_2m);
    const wind = Math.round(c.wind_speed_10m);
    const gust = Math.round(c.wind_gusts_10m);
    const hum = Math.round(c.relative_humidity_2m);
    const cond = WMO[code] || 'Unknown';
    const tempElem = document.getElementById('weatherTemp');
    const condElem = document.getElementById('weatherCondition');
    if (tempElem) tempElem.textContent = temp;
    if (condElem) condElem.textContent = cond;
    const windElem = document.getElementById('w-wind');
    const gustElem = document.getElementById('w-gust');
    const humidElem = document.getElementById('w-humid');
    if (windElem) windElem.textContent = wind;
    if (gustElem) gustElem.textContent = gust;
    if (humidElem) humidElem.textContent = hum;
    const { level, text, cls } = weatherDanger(code, wind, gust);
    const dangerEl = document.getElementById('weatherDanger');
    if (dangerEl) {
      dangerEl.className = 'weather-danger ' + cls;
      const dangerText = document.getElementById('weatherDangerText');
      if (dangerText) dangerText.textContent = text;
    }
    const summaryDiv = document.getElementById('weatherSummary');
    if (summaryDiv) {
      summaryDiv.innerHTML =
        `Currently <strong>${cond}</strong> at <strong>${temp}°C</strong> with <strong>${hum}%</strong> humidity.
         Wind speed <strong>${wind} mph</strong> with gusts up to <strong>${gust} mph</strong>.
         ${level !== 'safe' ? '<br><br><strong style="color:#fde68a">Action Required:</strong> ' + text : 'Conditions are normal.'}`;
    }
    const tickerText = `${CAMPUS.name} — ${cond} ${temp}°C | Wind: ${wind}mph | Humidity: ${hum}% | ${text}`;
    const ticker = document.getElementById('tickerText');
    if (ticker) ticker.textContent = tickerText + ' &nbsp;|&nbsp; ' + tickerText;
    if ((level === 'danger' || level === 'warning') && isAdmin) {
      toast(`Weather alert: ${cond}. ${text}`, level === 'danger' ? 'warning' : 'info', 8000);
    }
  } catch (e) {
    const condElem = document.getElementById('weatherCondition');
    if (condElem) condElem.textContent = 'Weather unavailable';
    const summaryDiv = document.getElementById('weatherSummary');
    if (summaryDiv) summaryDiv.innerHTML = 'Unable to fetch weather data. Check your connection.';
  }
}

function escHtml(s) {
  if (!s) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(() => {});
}

updateAlertFeed();
updateStats();
if (isAdmin) {
  updateChecklist();
  renderVideos();
} else {
  renderVideos();
}
renderHistory();
loadWeather();

setInterval(loadWeather, 300_000);
setInterval(() => { updateAlertFeed(); updateStats(); renderHistory(); }, 30_000);

if (isAdmin) {
  document.getElementById('sendBtn')?.addEventListener('mouseenter', requestNotifPermission, { once: true });
}
</script>
</body>
</html>