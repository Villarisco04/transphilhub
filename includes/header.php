<?php
// =============================================
// SESSION TIMEOUT CHECK - MUST BE FIRST
// =============================================
require_once 'session_timeout.php';
check_session_timeout();

// Then continue with normal session
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// =============================================
// AUTO-DETECT ENVIRONMENT & BASE URL
// =============================================
$http_host = $_SERVER['HTTP_HOST'];
$is_local  = ($http_host === 'localhost' || $http_host === '127.0.0.1');
$is_render = str_contains($http_host, 'onrender.com');

if($is_local){
    $base_url = 'http://localhost/transphilhub';
} elseif($is_render){
    $base_url = 'https://' . $http_host;
} else {
    // InfinityFree
    $base_url = 'https://' . $http_host . '/transphilhub';
}
// ← THIS WAS MISSING — closing PHP tag before HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trans-Phil House Hub</title>

    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <?php if(isset($_SESSION['user_id']) && function_exists('session_warning_needed') && session_warning_needed()): ?>
    <style>
        #session-warning {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #fff3e0;
            border-left: 4px solid #f07800;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            z-index: 10000;
            min-width: 280px;
            animation: slideInRight 0.3s ease;
        }
        #session-warning .warning-title { font-weight: 700; color: #f07800; margin-bottom: 5px; }
        #session-warning .warning-text  { font-size: 13px; color: #1e1c2e; margin-bottom: 10px; }
        #session-warning .warning-timer { font-size: 20px; font-weight: 700; color: #1a3a6b; }
        #session-warning .warning-actions { display: flex; gap: 10px; margin-top: 10px; }
        #session-warning .btn-extend {
            background: #f07800; color: white; border: none;
            padding: 6px 15px; border-radius: 6px; cursor: pointer;
            font-size: 12px; font-weight: 600;
        }
        #session-warning .btn-extend:hover { background: #d86d00; }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0); opacity: 1; }
        }
    </style>
    <?php endif; ?>
</head>
<body>

<div class="wrap">

<nav class="nav">
    <a href="<?php echo $base_url; ?>/index.php" class="nav-logo">
        <img src="<?php echo $base_url; ?>/assets/images/logo.jpg" class="logo-icon" alt="Trans-Phil Logo">
        <div class="logo-text">
            <span class="lt1">Trans-Phil</span>
            <span class="lt2">House Hub</span>
        </div>
    </a>

    <!-- Hamburger for mobile -->
    <div class="hamburger" id="hamburger" onclick="toggleNav()">
        <span></span><span></span><span></span>
    </div>

    <div class="nav-links" id="navLinks">
        <a href="<?php echo $base_url; ?>/index.php">Home</a>
        <a href="<?php echo $base_url; ?>/properties.php">Properties</a>
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="<?php echo $base_url; ?>/<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn-login">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="<?php echo $base_url; ?>/logout.php" class="btn-reg">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        <?php else: ?>
            <a href="<?php echo $base_url; ?>/login.php" class="btn-login">Login</a>
            <a href="<?php echo $base_url; ?>/register.php" class="btn-reg">Register</a>
        <?php endif; ?>
    </div>
</nav>

<!-- Session Warning Popup -->
<?php if(isset($_SESSION['user_id']) && function_exists('session_warning_needed') && session_warning_needed()): ?>
<div id="session-warning">
    <div class="warning-title"><i class="fas fa-clock"></i> Session Expiring Soon</div>
    <div class="warning-text">
        Your session will expire in
        <span class="warning-timer" id="session-timer">
            <?php echo function_exists('get_session_remaining_formatted') ? get_session_remaining_formatted() : '05:00'; ?>
        </span>
    </div>
    <div class="warning-actions">
        <button class="btn-extend" onclick="extendSession()">
            <i class="fas fa-refresh"></i> Stay Logged In
        </button>
    </div>
</div>

<script>
let warningTimer;

function updateWarningTimer(){
    fetch('<?php echo $base_url; ?>/ajax/extend_session.php')
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('session-timer');
            if(el && data.remaining_formatted) el.textContent = data.remaining_formatted;
            if(data.remaining <= 0){
                clearInterval(warningTimer);
                window.location.href = '<?php echo $base_url; ?>/login.php?timeout=1';
            }
        }).catch(e => console.error(e));
}

function extendSession(){
    fetch('<?php echo $base_url; ?>/ajax/extend_session.php?extend=1')
        .then(r => r.json())
        .then(data => {
            if(data.success){
                document.getElementById('session-warning').style.display = 'none';
                clearInterval(warningTimer);
            }
        }).catch(e => console.error(e));
}

if(document.getElementById('session-warning')){
    warningTimer = setInterval(updateWarningTimer, 1000);
}
</script>
<?php endif; ?>