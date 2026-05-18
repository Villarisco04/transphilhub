<?php
require_once '../includes/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

// Statistics
$stats = [];
$stats['total_users']       = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['total_agents']      = $pdo->query("SELECT COUNT(*) FROM users WHERE role='agent'")->fetchColumn();
$stats['total_clients']     = $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
$stats['total_properties']  = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$stats['available_props']   = $pdo->query("SELECT COUNT(*) FROM properties WHERE status='available'")->fetchColumn();
$stats['total_leads']       = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$stats['pending_leads']     = $pdo->query("SELECT COUNT(*) FROM leads WHERE stage='new'")->fetchColumn();
$stats['closed_leads']      = $pdo->query("SELECT COUNT(*) FROM leads WHERE stage='closed'")->fetchColumn();
$stats['total_appointments']= $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$stats['pending_appts']     = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetchColumn();

// Recent users
$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 6")->fetchAll();

// Recent leads with join
$recent_leads = $pdo->query("
    SELECT l.*, 
           c.full_name AS client_name,
           a.full_name AS agent_name,
           p.title AS property_title
    FROM leads l
    LEFT JOIN users c ON l.client_id = c.id
    LEFT JOIN users a ON l.agent_id = a.id
    LEFT JOIN properties p ON l.property_id = p.id
    ORDER BY l.created_at DESC LIMIT 5
")->fetchAll();

// Unread notifications count
$notif_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$notif_count->execute([$_SESSION['user_id']]);
$notif_count = $notif_count->fetchColumn();

// Create a sample notification if none exist (for testing)
if($notif_count == 0){
    $sample = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
    $sample->execute([$_SESSION['user_id'], 'Welcome to your admin dashboard! This is a sample notification.', '#']);
    $notif_count = 1;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — Trans-Phil House Hub</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root {
    --navy:   #1a3a6b;
    --navy2:  #0f2340;
    --green:  #2db12b;
    --green2: #218f1f;
    --orange: #f07800;
    --orange2:#c96400;
    --white:  #ffffff;
    --bg:     #f0eff5;
    --card:   #ffffff;
    --border: #e4e2ee;
    --text:   #1e1c2e;
    --muted:  #6b6880;
    --radius: 14px;
    --shadow: 0 2px 12px rgba(26,58,107,.07);
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;}

/* ── SIDEBAR ── */
.sidebar{width:260px;background:var(--navy2);display:flex;flex-direction:column;position:fixed;height:100vh;z-index:200;transition:.3s;}
.sidebar.collapsed{width:72px;}
.sb-logo{padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:12px;min-height:76px;}
.sb-logo-icon{width:40px;height:40px;flex-shrink:0;}
.sb-logo-text{overflow:hidden;transition:.3s;}
.sb-logo-text .t1{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;}
.sb-logo-text .t2{font-size:10px;color:var(--orange);letter-spacing:1.2px;text-transform:uppercase;white-space:nowrap;}
.sidebar.collapsed .sb-logo-text{width:0;opacity:0;}

.sb-section{padding:12px 0 4px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,.3);padding-left:20px;overflow:hidden;white-space:nowrap;transition:.2s;}
.sidebar.collapsed .sb-section{opacity:0;}

.nav-item{display:flex;align-items:center;gap:14px;padding:11px 20px;color:rgba(255,255,255,.6);text-decoration:none;transition:.2s;position:relative;border-left:3px solid transparent;font-size:14px;font-weight:500;}
.nav-item i{font-size:17px;width:20px;text-align:center;flex-shrink:0;}
.nav-item span{white-space:nowrap;overflow:hidden;transition:.2s;}
.sidebar.collapsed .nav-item span{width:0;opacity:0;}
.nav-item:hover{color:#fff;background:rgba(255,255,255,.06);}
.nav-item.active{color:#fff;background:rgba(45,177,43,.15);border-left:3px solid var(--green);}
.nav-badge{background:var(--orange);color:#fff;font-size:10px;padding:2px 7px;border-radius:20px;margin-left:auto;flex-shrink:0;}
.sidebar.collapsed .nav-badge{display:none;}

/* tooltip on collapsed */
.sidebar.collapsed .nav-item::after{content:attr(data-tip);position:absolute;left:76px;top:50%;transform:translateY(-50%);background:var(--navy);color:#fff;padding:6px 12px;border-radius:8px;font-size:12px;white-space:nowrap;opacity:0;pointer-events:none;transition:.15s;z-index:300;}
.sidebar.collapsed .nav-item:hover::after{opacity:1;}

.sb-toggle{position:absolute;top:22px;right:-14px;width:28px;height:28px;background:var(--orange);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff;z-index:300;transition:.3s;}
.sb-toggle i{color:#fff;font-size:12px;transition:.3s;}
.sidebar.collapsed .sb-toggle i{transform:rotate(180deg);}
.sb-footer{margin-top:auto;padding:16px 0;border-top:1px solid rgba(255,255,255,.08);}

/* ── MAIN ── */
.main{flex:1;margin-left:260px;transition:.3s;min-width:0;}
.sidebar.collapsed ~ .main{margin-left:72px;}

/* topbar */
.topbar{background:var(--card);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
.topbar-left h1{font-size:20px;color:var(--navy);font-weight:700;}
.topbar-left p{font-size:12px;color:var(--muted);}
.topbar-right{display:flex;align-items:center;gap:16px;position:relative;}

/* Notification Dropdown Styles */
.notif-dropdown-wrapper {
    position: relative;
}

.notif-btn {
    position: relative;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--bg);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: none;
    color: var(--navy);
    transition: 0.2s;
}

.notif-btn:hover {
    background: var(--border);
}

.notif-dot {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 8px;
    height: 8px;
    background: var(--orange);
    border-radius: 50%;
    border: 2px solid var(--card);
}

.notif-dropdown-panel {
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

.notif-dropdown-panel.show {
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
    background: none;
    border: none;
    font-size: 11px;
    color: #f07800;
    cursor: pointer;
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

.notif-loading {
    text-align: center;
    padding: 30px;
    color: #6b7280;
}

.user-chip{display:flex;align-items:center;gap:10px;background:var(--bg);padding:6px 14px 6px 6px;border-radius:30px;}
.user-avatar{width:32px;height:32px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;}
.user-chip span{font-size:13px;font-weight:600;color:var(--navy);}

/* content area */
.content{padding:28px;}

/* ── STAT CARDS ── */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:28px;}
.stat-card{background:var(--card);border-radius:var(--radius);padding:22px 20px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow);border:1px solid var(--border);text-decoration:none;color:inherit;transition:.2s;}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 6px 24px rgba(26,58,107,.12);}
.stat-icon{width:52px;height:52px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon i{font-size:24px;}
.stat-num{font-size:28px;font-weight:700;color:var(--navy);line-height:1;}
.stat-label{font-size:12px;color:var(--muted);margin-top:3px;}
.stat-sub{font-size:11px;margin-top:5px;font-weight:500;}

/* ── GRID LAYOUT ── */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.card{background:var(--card);border-radius:var(--radius);padding:22px 24px;box-shadow:var(--shadow);border:1px solid var(--border);}
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;}
.card-head h2{font-size:15px;font-weight:700;color:var(--navy);}
.card-head h2 i{margin-right:8px;color:var(--orange);}
.view-link{font-size:12px;color:var(--green);font-weight:600;text-decoration:none;}
.view-link:hover{text-decoration:underline;}

/* quick actions */
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.qa-btn{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600;transition:.2s;border:1.5px solid transparent;}
.qa-btn:hover{transform:translateY(-2px);}
.qa-navy{background:#eef2f9;color:var(--navy);border-color:#c5d3e8;}
.qa-navy:hover{background:#dde6f5;}
.qa-green{background:#e8f7e8;color:var(--green2);border-color:#b6e0b5;}
.qa-green:hover{background:#d1f0d0;}
.qa-orange{background:#fff3e6;color:var(--orange2);border-color:#f5cfa0;}
.qa-orange:hover{background:#ffe8cc;}
.qa-red{background:#fef0f0;color:#c0392b;border-color:#f5b8b8;}
.qa-red:hover{background:#fde0e0;}
.qa-btn i{font-size:18px;}

/* lead pipeline */
.pipeline{display:flex;gap:0;margin-top:4px;}
.pipe-stage{flex:1;text-align:center;padding:12px 6px;border-right:1px solid var(--border);}
.pipe-stage:last-child{border-right:none;}
.pipe-num{font-size:22px;font-weight:700;color:var(--navy);}
.pipe-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-top:2px;}
.pipe-bar{height:4px;border-radius:2px;margin-top:8px;}

/* table */
.tbl{width:100%;border-collapse:collapse;}
.tbl th{padding:10px 12px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);background:#f8f7fc;border-bottom:1px solid var(--border);text-align:left;}
.tbl td{padding:11px 12px;font-size:13px;border-bottom:1px solid var(--border);}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:hover td{background:#fafaf8;}

/* badges */
.badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
.b-admin{background:#e0e8f7;color:var(--navy);}
.b-agent{background:#fff0df;color:var(--orange2);}
.b-client{background:#e6f7e6;color:var(--green2);}
.b-new{background:#e0e8f7;color:var(--navy);}
.b-contacted{background:#fff0df;color:var(--orange2);}
.b-viewing{background:#e6f7e6;color:var(--green2);}
.b-negotiation{background:#fef9e0;color:#a07000;}
.b-closed{background:#e8f7e8;color:var(--green2);}
.b-active{background:#e6f7e6;color:var(--green2);}
.b-inactive{background:#fef0f0;color:#c0392b;}

/* mobile toggle */
.mob-toggle{display:none;position:fixed;bottom:20px;right:20px;width:48px;height:48px;background:var(--navy);border-radius:50%;align-items:center;justify-content:center;z-index:400;cursor:pointer;border:none;color:#fff;font-size:18px;box-shadow:0 4px 16px rgba(0,0,0,.2);}

@media(max-width:1100px){.stats-row{grid-template-columns:repeat(2,1fr);}.grid-2{grid-template-columns:1fr;}}
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);width:260px;}
    .sidebar.show{transform:translateX(0);}
    .main{margin-left:0!important;}
    .mob-toggle{display:flex;}
    .stats-row{grid-template-columns:1fr;}
    .content{padding:16px;}
    .topbar{padding:0 16px;}
    .notif-dropdown-panel{width:300px;right:-50px;}
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sb-toggle" id="sbToggle"><i class="fas fa-chevron-left"></i></div>

    <div class="sb-logo">
        <svg class="sb-logo-icon" viewBox="0 0 40 40">
            <circle cx="20" cy="20" r="18" fill="none" stroke="#1a3a6b" stroke-width="2.5" stroke-dasharray="95 15"/>
            <polygon points="20,5 30,15 10,15" fill="#2db12b"/>
            <rect x="11" y="15" width="7" height="11" fill="#f07800"/>
            <rect x="22" y="15" width="7" height="11" fill="#f07800"/>
            <rect x="17" y="15" width="6" height="16" fill="#2db12b"/>
        </svg>
        <div class="sb-logo-text">
            <div class="t1">Trans-Phil Hub</div>
            <div class="t2">Administrator</div>
        </div>
    </div>

    <div class="sb-section">Main Menu</div>

    <a href="dashboard.php" class="nav-item active" data-tip="Dashboard">
        <i class="fas fa-th-large"></i><span>Dashboard</span>
    </a>
    <a href="properties.php" class="nav-item" data-tip="Properties">
        <i class="fas fa-building"></i><span>Properties</span>
    </a>
    <a href="leads.php" class="nav-item" data-tip="Leads">
        <i class="fas fa-funnel-dollar"></i><span>Lead Management</span>
        <?php if($stats['pending_leads'] > 0): ?>
            <span class="nav-badge"><?php echo $stats['pending_leads']; ?></span>
        <?php endif; ?>
    </a>

    <div class="sb-section">Management</div>
    <a href="users.php" class="nav-item" data-tip="Users">
        <i class="fas fa-users"></i><span>User Management</span>
    </a>
    <a href="reviews.php" class="nav-item" data-tip="Reviews">
        <i class="fas fa-star"></i><span>Reviews & Ratings</span>
    </a>
    <a href="reports.php" class="nav-item" data-tip="Reports">
        <i class="fas fa-chart-bar"></i><span>Reports & Analytics</span>
    </a>

    <div class="sb-footer">
        <a href="../logout.php" class="nav-item" data-tip="Logout">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
    </div>
</aside>

<button class="mob-toggle" id="mobToggle"><i class="fas fa-bars"></i></button>

<!-- MAIN -->
<div class="main" id="main">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <h1>Dashboard</h1>
            <p><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="topbar-right">
            <!-- Notification Bell with Dropdown -->
            <div class="notif-dropdown-wrapper">
                <button class="notif-btn" id="notifBellBtn" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if($notif_count > 0): ?>
                        <span class="notif-dot" id="notifDot"></span>
                    <?php else: ?>
                        <span class="notif-dot" id="notifDot" style="display: none;"></span>
                    <?php endif; ?>
                </button>
                <div class="notif-dropdown-panel" id="notifDropdownPanel">
                    <div class="notif-header">
                        <h4>Notifications</h4>
                        <button id="markAllReadBtn" class="notif-mark-all">Mark all as read</button>
                    </div>
                    <div class="notif-list" id="notifList">
                        <div class="notif-loading">Loading...</div>
                    </div>
                </div>
            </div>
            <div class="user-chip">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'],0,1)); ?></div>
                <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            </div>
        </div>
    </div>

    <div class="content">

        <!-- STAT CARDS -->
        <div class="stats-row">
            <a href="users.php" class="stat-card">
                <div class="stat-icon" style="background:#eef2f9;">
                    <i class="fas fa-users" style="color:var(--navy);"></i>
                </div>
                <div>
                    <div class="stat-num"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-sub" style="color:var(--green);">
                        <?php echo $stats['total_agents']; ?> agents · <?php echo $stats['total_clients']; ?> clients
                    </div>
                </div>
            </a>
            <a href="properties.php" class="stat-card">
                <div class="stat-icon" style="background:#fff0df;">
                    <i class="fas fa-building" style="color:var(--orange);"></i>
                </div>
                <div>
                    <div class="stat-num"><?php echo $stats['total_properties']; ?></div>
                    <div class="stat-label">Properties</div>
                    <div class="stat-sub" style="color:var(--green);">
                        <?php echo $stats['available_props']; ?> available
                    </div>
                </div>
            </a>
            <a href="leads.php" class="stat-card">
                <div class="stat-icon" style="background:#e6f7e6;">
                    <i class="fas fa-funnel-dollar" style="color:var(--green);"></i>
                </div>
                <div>
                    <div class="stat-num"><?php echo $stats['total_leads']; ?></div>
                    <div class="stat-label">Total Leads</div>
                    <div class="stat-sub" style="color:var(--orange);">
                        <?php echo $stats['pending_leads']; ?> new · <?php echo $stats['closed_leads']; ?> closed
                    </div>
                </div>
            </a>
            <a href="appointments.php" class="stat-card">
                <div class="stat-icon" style="background:#fef0f0;">
                    <i class="fas fa-calendar-check" style="color:#c0392b;"></i>
                </div>
                <div>
                    <div class="stat-num"><?php echo $stats['total_appointments']; ?></div>
                    <div class="stat-label">Appointments</div>
                    <div class="stat-sub" style="color:var(--orange);">
                        <?php echo $stats['pending_appts']; ?> pending
                    </div>
                </div>
            </a>
        </div>

        <!-- ROW 2: Quick Actions + Lead Pipeline -->
        <div class="grid-2">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-head">
                    <h2><i class="fas fa-bolt"></i>Quick Actions</h2>
                </div>
                <div class="qa-grid">
                    <a href="properties.php?action=add" class="qa-btn qa-navy">
                        <i class="fas fa-plus-circle"></i>Add Property
                    </a>
                    <a href="users.php?action=add" class="qa-btn qa-green">
                        <i class="fas fa-user-plus"></i>Add User
                    </a>
                    <a href="leads.php" class="qa-btn qa-orange">
                        <i class="fas fa-tasks"></i>Assign Leads
                        <?php if($stats['pending_leads'] > 0): ?>
                            &nbsp;<span style="background:var(--orange);color:#fff;border-radius:20px;padding:1px 7px;font-size:10px;"><?php echo $stats['pending_leads']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="reports.php" class="qa-btn qa-red">
                        <i class="fas fa-file-export"></i>Export Report
                    </a>
                </div>
            </div>

            <!-- Lead Pipeline -->
            <div class="card">
                <div class="card-head">
                    <h2><i class="fas fa-stream"></i>Lead Pipeline</h2>
                    <a href="leads.php" class="view-link">Manage →</a>
                </div>
                <?php
                $stages = ['new','contacted','viewing','negotiation','closed'];
                $stage_colors = ['#1a3a6b','#f07800','#2db12b','#a07000','#218f1f'];
                $pipeline = [];
                foreach($stages as $s){
                    $c = $pdo->query("SELECT COUNT(*) FROM leads WHERE stage='$s'")->fetchColumn();
                    $pipeline[$s] = $c;
                }
                $total_pipe = array_sum($pipeline) ?: 1;
                ?>
                <div class="pipeline">
                    <?php foreach($stages as $i => $s): ?>
                    <div class="pipe-stage">
                        <div class="pipe-num" style="color:<?php echo $stage_colors[$i]; ?>"><?php echo $pipeline[$s]; ?></div>
                        <div class="pipe-label"><?php echo ucfirst($s); ?></div>
                        <div class="pipe-bar" style="background:<?php echo $stage_colors[$i]; ?>;opacity:.25;"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- Conversion rate -->
                <?php $conv = $total_pipe > 1 ? round(($pipeline['closed'] / ($total_pipe)) * 100) : 0; ?>
                <div style="margin-top:16px;">
                    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;">
                        <span style="color:var(--muted);">Conversion Rate</span>
                        <span style="font-weight:700;color:var(--green);"><?php echo $conv; ?>%</span>
                    </div>
                    <div class="prog-bar-bg">
                        <div class="prog-bar-fill" style="width:<?php echo $conv; ?>%;background:var(--green);"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROW 3: Recent Users + Recent Leads -->
        <div class="grid-2">
            <!-- Recent Users -->
            <div class="card">
                <div class="card-head">
                    <h2><i class="fas fa-user-clock"></i>Recent Users</h2>
                    <a href="users.php" class="view-link">View All →</a>
                </div>
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_users as $u): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <div style="width:28px;height:28px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;flex-shrink:0;">
                                        <?php echo strtoupper(substr($u['full_name'],0,1)); ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($u['full_name']); ?></span>
                                </div>
                            </td>
                            <td><span class="badge b-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span></td>
                            <td><span class="badge b-<?php echo $u['status']; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                            <td style="color:var(--muted);font-size:12px;"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recent_users)): ?>
                        <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--muted);">No users yet</td><td colspan="4" style="text-align:center;padding:24px;color:var(--muted);">No users yet</td>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Leads -->
            <div class="card">
                <div class="card-head">
                    <h2><i class="fas fa-history"></i>Recent Leads</h2>
                    <a href="leads.php" class="view-link">View All →</a>
                </div>
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Property</th>
                            <th>Stage</th>
                            <th>Agent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_leads as $lead): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($lead['client_name'] ?? '—'); ?></td>
                            <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                <?php echo htmlspecialchars($lead['property_title'] ?? '—'); ?>
                            </td>
                            <td><span class="badge b-<?php echo $lead['stage']; ?>"><?php echo ucfirst($lead['stage']); ?></span></td>
                            <td style="color:var(--muted);font-size:12px;"><?php echo htmlspecialchars($lead['agent_name'] ?? 'Unassigned'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($recent_leads)): ?>
                        <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--muted);">No leads yet</td><td colspan="4" style="text-align:center;padding:24px;color:var(--muted);">No leads yet</td>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<script>
// Sidebar Toggle
const sidebar = document.getElementById('sidebar');
const sbToggle = document.getElementById('sbToggle');
const mobToggle = document.getElementById('mobToggle');

if(sbToggle){
    sbToggle.addEventListener('click', e => {
        e.stopPropagation();
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sb', sidebar.classList.contains('collapsed') ? '1' : '0');
    });
}

if(mobToggle){
    mobToggle.addEventListener('click', () => sidebar.classList.toggle('show'));
}

document.addEventListener('click', e => {
    if(window.innerWidth <= 768 && !sidebar.contains(e.target) && !mobToggle.contains(e.target)){
        sidebar.classList.remove('show');
    }
});

if(window.innerWidth > 768 && localStorage.getItem('sb') === '1'){
    sidebar.classList.add('collapsed');
}

// ============================================
// NOTIFICATION DROPDOWN - COMPLETE WORKING CODE
// ============================================
const notifBell = document.getElementById('notifBellBtn');
const notifDropdown = document.getElementById('notifDropdownPanel');
const notifList = document.getElementById('notifList');
const notifDot = document.getElementById('notifDot');
const markAllBtn = document.getElementById('markAllReadBtn');

// Helper function to format time ago
function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes} min ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;
    return date.toLocaleDateString();
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load notifications from server
function loadNotifications() {
    if (!notifList) return;
    
    notifList.innerHTML = '<div class="notif-loading">Loading...</div>';
    
    fetch('ajax/get_notifications.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications && data.notifications.length > 0) {
                let html = '';
                data.notifications.forEach(notif => {
                    const isUnread = notif.is_read == 0 ? 'unread' : '';
                    const timeAgo = getTimeAgo(notif.created_at);
                    
                    // Choose icon based on message content
                    let iconClass = 'fa-bell';
                    let iconBg = '#eef2f9';
                    let iconColor = '#1a3a6b';
                    
                    if (notif.message.includes('inquiry')) {
                        iconClass = 'fa-envelope';
                        iconBg = '#fff3e0';
                        iconColor = '#f07800';
                    } else if (notif.message.includes('lead')) {
                        iconClass = 'fa-user-tie';
                        iconBg = '#e6f7e6';
                        iconColor = '#2db12b';
                    } else if (notif.message.includes('appointment')) {
                        iconClass = 'fa-calendar-check';
                        iconBg = '#e3f2fd';
                        iconColor = '#1a3a6b';
                    } else if (notif.message.includes('review')) {
                        iconClass = 'fa-star';
                        iconBg = '#fef9e0';
                        iconColor = '#a07000';
                    }
                    
                    html += `
                        <div class="notif-item ${isUnread}" data-id="${notif.id}" data-link="${notif.link || '#'}">
                            <div class="notif-icon-small" style="background: ${iconBg}">
                                <i class="fas ${iconClass}" style="color: ${iconColor}"></i>
                            </div>
                            <div class="notif-content">
                                <div class="notif-message">${escapeHtml(notif.message)}</div>
                                <div class="notif-time">${timeAgo}</div>
                            </div>
                        </div>
                    `;
                });
                notifList.innerHTML = html;
                
                // Add click handlers to mark as read
                document.querySelectorAll('.notif-item').forEach(item => {
                    item.addEventListener('click', function(e) {
                        const notifId = this.dataset.id;
                        const link = this.dataset.link;
                        if (notifId) {
                            fetch(`ajax/get_notifications.php?action=mark_read&id=${notifId}`)
                                .then(() => {
                                    if (link && link !== '#') {
                                        window.location.href = link;
                                    } else {
                                        loadNotifications();
                                        updateNotifCount();
                                    }
                                });
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
        .catch(error => {
            console.error('Error:', error);
            notifList.innerHTML = `
                <div class="notif-empty">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Error loading notifications</p>
                </div>
            `;
        });
}

// Update unread count badge
function updateNotifCount() {
    fetch('ajax/get_notifications.php?action=count')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.count > 0) {
                if (notifDot) notifDot.style.display = 'block';
            } else {
                if (notifDot) notifDot.style.display = 'none';
            }
        })
        .catch(error => console.error('Error:', error));
}

// Mark all as read
function markAllAsRead() {
    fetch('ajax/get_notifications.php?action=mark_all_read')
        .then(() => {
            loadNotifications();
            if (notifDot) notifDot.style.display = 'none';
        })
        .catch(error => console.error('Error:', error));
}

// Toggle dropdown when bell is clicked
if (notifBell) {
    notifBell.addEventListener('click', (e) => {
        e.stopPropagation();
        notifDropdown.classList.toggle('show');
        if (notifDropdown.classList.contains('show')) {
            loadNotifications();
        }
    });
}

// Mark all read button
if (markAllBtn) {
    markAllBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        markAllAsRead();
    });
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (notifDropdown && notifBell && !notifBell.contains(e.target) && !notifDropdown.contains(e.target)) {
        notifDropdown.classList.remove('show');
    }
});

// Initial load of notification count
updateNotifCount();

// Poll for new notifications every 30 seconds
setInterval(updateNotifCount, 30000);
</script>
</body>
</html>