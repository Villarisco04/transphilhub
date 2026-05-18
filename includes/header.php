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

// Load notification functions if user is logged in
if(isset($_SESSION['user_id'])){
    if(file_exists(__DIR__ . '/notifications.php')){
        require_once __DIR__ . '/notifications.php';
        $unread_count = function_exists('get_unread_count') ? get_unread_count($_SESSION['user_id']) : 0;
    } else {
        $unread_count = 0;
    }
}
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

    <!-- Notification Styles -->
    <style>
        .notif-wrapper {
            position: relative;
            margin-right: 10px;
        }
        .notif-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
            position: relative;
        }
        .notif-btn:hover {
            background: #f0eff5;
        }
        .notif-icon {
            font-size: 20px;
            color: #1a3a6b;
        }
        .notif-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #f07800;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }
        .notif-dropdown {
            position: absolute;
            top: 45px;
            right: 0;
            width: 380px;
            max-width: calc(100vw - 20px);
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
            border: 1px solid #e4e2ee;
        }
        .notif-dropdown.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .notif-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid #e4e2ee;
        }
        .notif-header h4 {
            font-size: 14px;
            font-weight: 700;
            color: #1a3a6b;
        }
        .notif-mark-all {
            font-size: 11px;
            color: #f07800;
            cursor: pointer;
            background: none;
            border: none;
        }
        .notif-mark-all:hover {
            text-decoration: underline;
        }
        .notif-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid #f0ede8;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            color: inherit;
        }
        .notif-item:hover {
            background: #faf9f6;
        }
        .notif-item.unread {
            background: #eef2f9;
        }
        .notif-item.unread:hover {
            background: #e5eaf2;
        }
        .notif-icon-small {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notif-icon-small i {
            font-size: 14px;
        }
        .notif-content {
            flex: 1;
        }
        .notif-message {
            font-size: 13px;
            color: #1e1c2e;
            margin-bottom: 4px;
            line-height: 1.4;
        }
        .notif-time {
            font-size: 10px;
            color: #6b7280;
        }
        .notif-empty {
            text-align: center;
            padding: 30px;
            color: #6b7280;
            font-size: 13px;
        }
        .notif-empty i {
            font-size: 30px;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        /* Mobile Nav */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            gap: 5px;
        }
        .hamburger span {
            width: 25px;
            height: 3px;
            background: #1a3a6b;
            transition: 0.3s;
        }
        @media (max-width: 768px) {
            .hamburger { display: flex; }
            .nav-links {
                display: none;
                flex-direction: column;
                position: absolute;
                top: 70px;
                left: 0;
                width: 100%;
                background: white;
                padding: 20px;
                box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            }
            .nav-links.show { display: flex; }
        }
    </style>

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
            <!-- Notification Bell -->
            <div class="notif-wrapper">
                <button class="notif-btn" id="notifBell">
                    <i class="fas fa-bell notif-icon"></i>
                    <?php if(isset($unread_count) && $unread_count > 0): ?>
                    <span class="notif-badge" id="notifCount"><?php echo $unread_count > 99 ? '99+' : $unread_count; ?></span>
                    <?php else: ?>
                    <span class="notif-badge" id="notifCount" style="display: none;">0</span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <h4>Notifications</h4>
                        <button class="notif-mark-all" id="markAllRead">Mark all as read</button>
                    </div>
                    <div class="notif-list" id="notifList">
                        <div class="notif-empty">
                            <i class="fas fa-bell-slash"></i>
                            <p>Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
            
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

<!-- Notification System JavaScript -->
<?php if(isset($_SESSION['user_id'])): ?>
<script>
// Notification System
const notifBell = document.getElementById('notifBell');
const notifDropdown = document.getElementById('notifDropdown');
const notifList = document.getElementById('notifList');
const notifCount = document.getElementById('notifCount');
const markAllReadBtn = document.getElementById('markAllRead');
let notificationInterval;
let baseUrl = '<?php echo $base_url; ?>';

function loadNotificationCount() {
    fetch(baseUrl + '/ajax/get_notifications.php?action=count')
        .then(response => response.json())
        .then(data => {
            if(data.success && data.count > 0) {
                notifCount.textContent = data.count > 99 ? '99+' : data.count;
                notifCount.style.display = 'inline-block';
            } else {
                notifCount.style.display = 'none';
            }
        })
        .catch(error => console.error('Error:', error));
}

function loadNotifications() {
    fetch(baseUrl + '/ajax/get_notifications.php?action=list')
        .then(response => response.json())
        .then(data => {
            if(data.success && data.notifications && data.notifications.length > 0) {
                let html = '';
                data.notifications.forEach(notif => {
                    const isUnread = notif.is_read == 0 ? 'unread' : '';
                    const timeAgo = getTimeAgo(notif.created_at);
                    let iconClass = 'fa-bell';
                    if(notif.message.includes('inquiry')) iconClass = 'fa-envelope';
                    else if(notif.message.includes('lead')) iconClass = 'fa-user-tie';
                    else if(notif.message.includes('appointment')) iconClass = 'fa-calendar-check';
                    else if(notif.message.includes('review')) iconClass = 'fa-star';
                    else if(notif.message.includes('completed')) iconClass = 'fa-check-circle';
                    
                    html += `
                        <a href="${notif.link || '#'}" class="notif-item ${isUnread}" data-id="${notif.id}">
                            <div class="notif-content">
                                <div class="notif-message">${escapeHtml(notif.message)}</div>
                                <div class="notif-time">${timeAgo}</div>
                            </div>
                        </a>
                    `;
                });
                notifList.innerHTML = html;
                
                // Add click handler to mark as read
                document.querySelectorAll('.notif-item').forEach(item => {
                    item.addEventListener('click', function(e) {
                        const notifId = this.dataset.id;
                        if(notifId) {
                            fetch(baseUrl + `/ajax/get_notifications.php?action=mark_read&id=${notifId}`)
                                .then(() => loadNotificationCount());
                        }
                    });
                });
            } else {
                notifList.innerHTML = `
                    <div class="notif-empty">
                        <i class="fas fa-bell-slash"></i>
                        <p>No notifications</p>
                    </div>
                `;
            }
        })
        .catch(error => console.error('Error:', error));
}

function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if(seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if(minutes < 60) return `${minutes} min ago`;
    const hours = Math.floor(minutes / 60);
    if(hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    const days = Math.floor(hours / 24);
    if(days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;
    return date.toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function toggleNav() {
    const navLinks = document.getElementById('navLinks');
    navLinks.classList.toggle('show');
}

// Toggle dropdown
if(notifBell) {
    notifBell.addEventListener('click', (e) => {
        e.stopPropagation();
        notifDropdown.classList.toggle('show');
        if(notifDropdown.classList.contains('show')) {
            loadNotifications();
        }
    });
}

// Mark all as read
if(markAllReadBtn) {
    markAllReadBtn.addEventListener('click', () => {
        fetch(baseUrl + '/ajax/get_notifications.php?action=mark_all_read')
            .then(() => {
                loadNotificationCount();
                loadNotifications();
            });
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if(notifBell && notifDropdown && !notifBell.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown.classList.remove('show');
    }
});

// Load notification count on page load
loadNotificationCount();

// Poll for new notifications every 30 seconds
notificationInterval = setInterval(loadNotificationCount, 30000);
</script>
<?php endif; ?>