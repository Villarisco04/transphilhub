<?php
require_once '../includes/db.php';
session_start();

// Check if user is logged in and is agent
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent'){
    header("Location: ../login.php");
    exit;
}

$agent_id = $_SESSION['user_id'];

// Get assigned leads
$leads = $pdo->prepare("
    SELECT l.*, 
           c.full_name as client_name, c.email as client_email, c.phone as client_phone,
           p.title as property_title, p.location, p.price, p.type, p.image
    FROM leads l
    JOIN users c ON l.client_id = c.id
    JOIN properties p ON l.property_id = p.id
    WHERE l.agent_id = ?
    ORDER BY 
        CASE l.stage 
            WHEN 'new' THEN 1
            WHEN 'contacted' THEN 2
            WHEN 'viewing' THEN 3
            WHEN 'negotiation' THEN 4
            WHEN 'closed' THEN 5
        END,
        l.created_at DESC
");
$leads->execute([$agent_id]);
$leads = $leads->fetchAll();

// Get statistics
$stats = [];
$stats['total_leads'] = count($leads);
$stats['new_leads'] = 0;
$stats['contacted_leads'] = 0;
$stats['viewing_leads'] = 0;
$stats['negotiation_leads'] = 0;
$stats['closed_leads'] = 0;

foreach($leads as $lead){
    switch($lead['stage']){
        case 'new': $stats['new_leads']++; break;
        case 'contacted': $stats['contacted_leads']++; break;
        case 'viewing': $stats['viewing_leads']++; break;
        case 'negotiation': $stats['negotiation_leads']++; break;
        case 'closed': $stats['closed_leads']++; break;
    }
}

// Calculate conversion rate
$conversion_rate = $stats['total_leads'] > 0 ? round(($stats['closed_leads'] / $stats['total_leads']) * 100) : 0;

// Get agent info
$agent_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$agent_stmt->execute([$agent_id]);
$agent = $agent_stmt->fetch();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agent Dashboard — Trans-Phil House Hub</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{--navy:#1a3a6b;--navy2:#0f2340;--green:#2db12b;--green2:#218f1f;--orange:#f07800;--orange2:#c96400;--white:#ffffff;--bg:#f0eff5;--card:#ffffff;--border:#e4e2ee;--text:#1e1c2e;--muted:#6b6880;--radius:14px;--shadow:0 2px 12px rgba(26,58,107,.07);}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;}

/* Sidebar */
.sidebar{width:260px;background:var(--navy2);display:flex;flex-direction:column;position:fixed;height:100vh;z-index:200;transition:.3s;}
.sidebar.collapsed{width:72px;}
.sb-logo{padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:12px;min-height:76px;}
.sb-logo svg{width:40px;height:40px;flex-shrink:0;}
.sb-logo-text{overflow:hidden;transition:.3s;}
.sb-logo-text .t1{font-size:15px;font-weight:700;color:#fff;white-space:nowrap;}
.sb-logo-text .t2{font-size:10px;color:var(--orange);letter-spacing:1.2px;text-transform:uppercase;white-space:nowrap;}
.sidebar.collapsed .sb-logo-text{width:0;opacity:0;}

.sb-section{padding:12px 20px 4px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,.3);overflow:hidden;white-space:nowrap;transition:.2s;}
.sidebar.collapsed .sb-section{opacity:0;}

.nav-item{display:flex;align-items:center;gap:14px;padding:11px 20px;color:rgba(255,255,255,.6);text-decoration:none;transition:.2s;border-left:3px solid transparent;font-size:14px;font-weight:500;}
.nav-item i{font-size:17px;width:20px;text-align:center;flex-shrink:0;}
.nav-item span{white-space:nowrap;overflow:hidden;transition:.2s;}
.sidebar.collapsed .nav-item span{width:0;opacity:0;}
.nav-item:hover{color:#fff;background:rgba(255,255,255,.06);}
.nav-item.active{color:#fff;background:rgba(45,177,43,.15);border-left:3px solid var(--green);}
.sidebar.collapsed .nav-item::after{content:attr(data-tip);position:absolute;left:76px;top:50%;transform:translateY(-50%);background:var(--navy);color:#fff;padding:6px 12px;border-radius:8px;font-size:12px;white-space:nowrap;opacity:0;pointer-events:none;transition:.15s;z-index:300;}
.sidebar.collapsed .nav-item:hover::after{opacity:1;}

.sb-toggle{position:absolute;top:22px;right:-14px;width:28px;height:28px;background:var(--orange);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff;z-index:300;transition:.3s;}
.sb-toggle i{color:#fff;font-size:12px;transition:.3s;}
.sidebar.collapsed .sb-toggle i{transform:rotate(180deg);}
.sb-footer{margin-top:auto;padding:16px 0;border-top:1px solid rgba(255,255,255,.08);}

/* Main Content */
.main{flex:1;margin-left:260px;transition:.3s;min-width:0;}
.sidebar.collapsed ~ .main{margin-left:72px;}

.topbar{background:var(--card);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
.topbar-left h1{font-size:20px;color:var(--navy);font-weight:700;}
.topbar-left p{font-size:12px;color:var(--muted);}
.user-chip{display:flex;align-items:center;gap:10px;background:var(--bg);padding:6px 14px 6px 6px;border-radius:30px;}
.user-avatar{width:32px;height:32px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;}
.user-chip span{font-size:13px;font-weight:600;color:var(--navy);}
.content{padding:28px;}

/* Alert Messages */
.alert-success{
    background:#e6f7e6;
    border-left:4px solid #2db12b;
    padding:12px 16px;
    border-radius:8px;
    margin-bottom:20px;
    color:#166534;
}
.alert-error{
    background:#fee2e2;
    border-left:4px solid #dc2626;
    padding:12px 16px;
    border-radius:8px;
    margin-bottom:20px;
    color:#991b1b;
}

/* Welcome Banner */
.welcome-banner{background:linear-gradient(135deg, var(--navy2), var(--navy));border-radius:var(--radius);padding:28px 32px;margin-bottom:24px;}
.welcome-banner h2{font-size:22px;color:#fff;margin-bottom:6px;}
.welcome-banner p{color:rgba(255,255,255,.7);font-size:13px;}

/* Stats Row */
.stats-row{display:grid;grid-template-columns:repeat(5,1fr);gap:18px;margin-bottom:24px;}
.stat-card{background:var(--card);border-radius:var(--radius);padding:20px;text-align:center;box-shadow:var(--shadow);border:1px solid var(--border);transition:.2s;}
.stat-card:hover{transform:translateY(-3px);}
.stat-num{font-size:28px;font-weight:700;}
.stat-label{font-size:11px;color:var(--muted);margin-top:4px;text-transform:uppercase;letter-spacing:.5px;}
.stat-new .stat-num{color:#1a3a6b;}
.stat-contacted .stat-num{color:#f07800;}
.stat-viewing .stat-num{color:#2db12b;}
.stat-negotiation .stat-num{color:#a07000;}
.stat-closed .stat-num{color:#218f1f;}

/* Grid Layout */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;}
.card{background:var(--card);border-radius:var(--radius);padding:22px 24px;box-shadow:var(--shadow);border:1px solid var(--border);}
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;}
.card-head h2{font-size:15px;font-weight:700;color:var(--navy);}
.card-head h2 i{margin-right:8px;color:var(--orange);}

/* Conversion Ring */
.conversion-ring{display:flex;align-items:center;gap:20px;flex-wrap:wrap;}
.ring-chart{position:relative;width:120px;height:120px;}
.ring-bg{position:absolute;width:100%;height:100%;border-radius:50%;background:var(--bg);}
.ring-fill{position:absolute;width:100%;height:100%;border-radius:50%;}
.ring-inner{position:absolute;top:15px;left:15px;width:90px;height:90px;border-radius:50%;background:white;display:flex;align-items:center;justify-content:center;flex-direction:column;}
.ring-percent{font-size:28px;font-weight:800;color:var(--navy);}
.ring-label{font-size:10px;color:var(--muted);}
.conversion-stats{flex:1;}
.conversion-stats .stat-item{display:flex;justify-content:space-between;margin-bottom:12px;}
.conversion-stats .stat-label{color:var(--muted);font-size:12px;}
.conversion-stats .stat-value{font-weight:700;}

/* Profile Info */
.profile-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;}
.profile-field{display:flex;flex-direction:column;gap:4px;}
.profile-field label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
.profile-field .value{font-size:14px;font-weight:500;color:var(--text);}

/* Table */
.tbl-wrap{overflow-x:auto;}
.tbl{width:100%;border-collapse:collapse;min-width:800px;}
.tbl th{padding:10px 12px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);background:#f8f7fc;border-bottom:1px solid var(--border);text-align:left;}
.tbl td{padding:11px 12px;font-size:13px;border-bottom:1px solid var(--border);vertical-align:middle;}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:hover td{background:#fafaf8;}
.property-img{width:55px;height:45px;object-fit:cover;border-radius:8px;}
.badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
.b-new{background:#e0e8f7;color:#1a3a6b;}
.b-contacted{background:#fff0df;color:#f07800;}
.b-viewing{background:#e6f7e6;color:#2db12b;}
.b-negotiation{background:#fef9e0;color:#a07000;}
.b-closed{background:#e8f7e8;color:#218f1f;}
.action-btn{padding:5px 10px;border-radius:6px;border:none;cursor:pointer;font-size:11px;font-weight:600;transition:.2s;}
.btn-contact{background:#e0e8f7;color:#1a3a6b;}
.btn-contact:hover{background:#c5d3e8;}
.btn-view{background:#e6f7e6;color:#2db12b;}
.btn-view:hover{background:#c8eec8;}
.price{font-weight:700;color:var(--navy);}
.no-data{text-align:center;padding:40px;color:var(--muted);}

/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal-box{background:#fff;border-radius:16px;padding:28px;width:90%;max-width:480px;}
.modal-box h3{font-size:16px;color:var(--navy);margin-bottom:18px;}
.modal-close{float:right;cursor:pointer;color:var(--muted);background:none;border:none;font-size:18px;}
.modal-box select,.modal-box textarea{width:100%;padding:10px;border:1px solid var(--border);border-radius:8px;margin-bottom:15px;font-family:inherit;}
.modal-box .btn-primary{width:100%;background:var(--orange);color:white;border:none;padding:12px;border-radius:8px;cursor:pointer;font-weight:600;}

/* Mobile */
.mob-toggle{display:none;position:fixed;bottom:20px;right:20px;width:48px;height:48px;background:var(--navy);border-radius:50%;align-items:center;justify-content:center;z-index:400;cursor:pointer;border:none;color:#fff;font-size:18px;box-shadow:0 4px 16px rgba(0,0,0,.2);}

@media(max-width:1100px){.stats-row{grid-template-columns:repeat(3,1fr);}.grid-2{grid-template-columns:1fr;}}
@media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr);}}
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);width:260px;}
    .sidebar.show{transform:translateX(0);}
    .main{margin-left:0!important;}
    .mob-toggle{display:flex;}
    .stats-row{grid-template-columns:1fr;}
    .profile-grid{grid-template-columns:1fr;}
    .content{padding:16px;}
    .topbar{padding:0 16px;}
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sb-toggle" id="sbToggle"><i class="fas fa-chevron-left"></i></div>
    <div class="sb-logo">
        <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="18" fill="none" stroke="#1a3a6b" stroke-width="2.5" stroke-dasharray="95 15"/><polygon points="20,5 30,15 10,15" fill="#2db12b"/><rect x="11" y="15" width="7" height="11" fill="#f07800"/><rect x="22" y="15" width="7" height="11" fill="#f07800"/><rect x="17" y="15" width="6" height="16" fill="#2db12b"/></svg>
        <div class="sb-logo-text"><div class="t1">Trans-Phil Hub</div><div class="t2">Agent Portal</div></div>
    </div>
    <div class="sb-section">Main Menu</div>
    <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" data-tip="Dashboard">
        <i class="fas fa-th-large"></i><span>Dashboard</span>
    </a>
    <a href="myclients.php" class="nav-item <?php echo $current_page == 'myclients.php' ? 'active' : ''; ?>" data-tip="My Clients">
        <i class="fas fa-users"></i><span>My Assigned Clients</span>
    </a>
    <div class="sb-footer">
        <a href="../logout.php" class="nav-item" data-tip="Logout">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span>
        </a>
    </div>
</aside>

<button class="mob-toggle" id="mobToggle"><i class="fas fa-bars"></i></button>

<!-- MAIN CONTENT -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h1>Agent Dashboard</h1>
            <p><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="user-chip">
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'],0,1)); ?></div>
            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
    </div>

    <div class="content">

        <!-- Display success/error messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>Welcome back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>! </h2>
            <p>Manage your leads, track client inquiries, and schedule property viewings.</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card stat-new">
                <div class="stat-num"><?php echo $stats['new_leads']; ?></div>
                <div class="stat-label">New Leads</div>
            </div>
            <div class="stat-card stat-contacted">
                <div class="stat-num"><?php echo $stats['contacted_leads']; ?></div>
                <div class="stat-label">Contacted</div>
            </div>
            <div class="stat-card stat-viewing">
                <div class="stat-num"><?php echo $stats['viewing_leads']; ?></div>
                <div class="stat-label">Viewing</div>
            </div>
            <div class="stat-card stat-negotiation">
                <div class="stat-num"><?php echo $stats['negotiation_leads']; ?></div>
                <div class="stat-label">Negotiation</div>
            </div>
            <div class="stat-card stat-closed">
                <div class="stat-num"><?php echo $stats['closed_leads']; ?></div>
                <div class="stat-label">Closed</div>
            </div>
        </div>

        <!-- Two Column Layout: Profile + Conversion -->
        <div class="grid-2">
            <!-- Profile Card -->
            <div class="card">
                <div class="card-head">
                    <h2><i class="fas fa-user-circle"></i> My Profile</h2>
                    <a href="#" class="action-link" style="color:var(--orange);text-decoration:none;font-size:12px;">Edit Profile →</a>
                </div>
                <div class="profile-grid">
                    <div class="profile-field"><label>Full Name</label><div class="value"><?php echo htmlspecialchars($agent['full_name']); ?></div></div>
                    <div class="profile-field"><label>Email Address</label><div class="value"><?php echo htmlspecialchars($agent['email']); ?></div></div>
                    <div class="profile-field"><label>Phone Number</label><div class="value"><?php echo htmlspecialchars($agent['phone'] ?: 'Not provided'); ?></div></div>
                    <div class="profile-field"><label>Member Since</label><div class="value"><?php echo date('F d, Y', strtotime($agent['created_at'])); ?></div></div>
                </div>
            </div>

            <!-- Conversion Rate Card -->
            <div class="card">
                <div class="card-head">
                    <h2><i class="fas fa-chart-line"></i> Conversion Rate</h2>
                </div>
                <div class="conversion-ring">
                    <div class="ring-chart">
                        <div class="ring-bg"></div>
                        <div class="ring-fill" style="background: conic-gradient(var(--green) 0deg, var(--green) <?php echo ($conversion_rate / 100) * 360; ?>deg, var(--bg) <?php echo ($conversion_rate / 100) * 360; ?>deg);"></div>
                        <div class="ring-inner">
                            <div class="ring-percent"><?php echo $conversion_rate; ?>%</div>
                            <div class="ring-label">Conversion</div>
                        </div>
                    </div>
                    <div class="conversion-stats">
                        <div class="stat-item"><span class="stat-label">Total Leads</span><span class="stat-value"><?php echo $stats['total_leads']; ?></span></div>
                        <div class="stat-item"><span class="stat-label">Closed Deals</span><span class="stat-value"><?php echo $stats['closed_leads']; ?></span></div>
                        <div class="stat-item"><span class="stat-label">Active Leads</span><span class="stat-value"><?php echo $stats['total_leads'] - $stats['closed_leads']; ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Leads Table -->
        <div class="card">
            <div class="card-head">
                <h2><i class="fas fa-list-alt"></i> My Assigned Leads</h2>
                <span style="font-size:12px;color:var(--muted);"><?php echo $stats['total_leads']; ?> total leads</span>
            </div>
            <div class="tbl-wrap">
                <?php if(count($leads) > 0): ?>
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Client / Property</th>
                            <th>Property Details</th>
                            <th>Stage</th>
                            <th>Inquiry Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($leads as $lead): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <img src="../assets/images/<?php echo $lead['image'] ?: 'property1.png'; ?>" class="property-img" onerror="this.src='../assets/images/property1.png'">
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($lead['client_name']); ?></div>
                                        <div style="font-size:11px;color:var(--muted);"><?php echo htmlspecialchars($lead['property_title']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="price">₱ <?php echo number_format($lead['price'], 0); ?></div>
                                <div style="font-size:11px;color:var(--muted);"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($lead['location']); ?></div>
                            </td>
                            <td><span class="badge b-<?php echo $lead['stage']; ?>"><?php echo ucfirst($lead['stage']); ?></span></td>
                            <td><span style="font-size:12px;color:var(--muted);"><?php echo date('M d, Y', strtotime($lead['created_at'])); ?></span></td>
                            <td>
                                <button class="action-btn btn-contact" onclick="openContactModal(<?php echo $lead['id']; ?>, '<?php echo htmlspecialchars($lead['client_name']); ?>', '<?php echo htmlspecialchars($lead['client_email']); ?>', '<?php echo htmlspecialchars($lead['client_phone']); ?>')">
                                    <i class="fas fa-phone"></i> Contact
                                </button>
                                <button class="action-btn btn-view" onclick="openStageModal(<?php echo $lead['id']; ?>, '<?php echo $lead['stage']; ?>')">
                                    <i class="fas fa-exchange-alt"></i> Update
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size:48px;margin-bottom:15px;color:var(--border);"></i>
                    <p>No leads assigned yet. The administrator will assign leads to you.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Contact Modal -->
<div class="modal-bg" id="contactModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('contactModal')"><i class="fas fa-times"></i></button>
        <h3><i class="fas fa-phone" style="color:var(--orange);margin-right:8px;"></i> Contact Client</h3>
        <div id="contactInfo">
            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:var(--muted);">Client Name</label>
                <div id="contactName" style="padding:8px 0;font-size:14px;"></div>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:var(--muted);">Email Address</label>
                <div id="contactEmail" style="padding:8px 0;font-size:14px;"></div>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:12px;font-weight:600;color:var(--muted);">Phone Number</label>
                <div id="contactPhone" style="padding:8px 0;font-size:14px;"></div>
            </div>
        </div>
        <a href="#" id="contactLink" class="btn-primary" style="display:block;text-align:center;text-decoration:none;">Send Email</a>
    </div>
</div>

<!-- Update Stage Modal -->
<div class="modal-bg" id="stageModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('stageModal')"><i class="fas fa-times"></i></button>
        <h3><i class="fas fa-exchange-alt" style="color:var(--green);margin-right:8px;"></i> Update Lead Stage</h3>
        <form method="POST" action="update_lead_stage.php">
            <input type="hidden" name="lead_id" id="stageLeadId">
            <select name="stage" id="stageSelect" required>
                <option value="new">New</option>
                <option value="contacted">Contacted</option>
                <option value="viewing">Viewing Scheduled</option>
                <option value="negotiation">Negotiation</option>
                <option value="closed">Closed - Deal Completed</option>
            </select>
            <textarea name="notes" id="stageNotes" rows="3" placeholder="Add notes about this lead..."></textarea>
            <button type="submit" name="update_stage" class="btn-primary">Update Stage</button>
        </form>
    </div>
</div>

<script>
const sidebar = document.getElementById('sidebar');
const sbToggle = document.getElementById('sbToggle');
const mobToggle = document.getElementById('mobToggle');

sbToggle.addEventListener('click', e => {
    e.stopPropagation();
    sidebar.classList.toggle('collapsed');
    localStorage.setItem('sb', sidebar.classList.contains('collapsed') ? '1' : '0');
});

mobToggle.addEventListener('click', () => sidebar.classList.toggle('show'));

document.addEventListener('click', e => {
    if(window.innerWidth <= 768 && !sidebar.contains(e.target) && !mobToggle.contains(e.target)){
        sidebar.classList.remove('show');
    }
});

if(window.innerWidth > 768 && localStorage.getItem('sb') === '1'){
    sidebar.classList.add('collapsed');
}

function openContactModal(leadId, clientName, clientEmail, clientPhone) {
    document.getElementById('contactName').innerHTML = '<i class="fas fa-user"></i> ' + clientName;
    document.getElementById('contactEmail').innerHTML = '<i class="fas fa-envelope"></i> ' + clientEmail;
    document.getElementById('contactPhone').innerHTML = '<i class="fas fa-phone"></i> ' + (clientPhone || 'Not provided');
    document.getElementById('contactLink').href = 'mailto:' + clientEmail + '?subject=Property Inquiry Follow-up';
    document.getElementById('contactModal').classList.add('open');
}

function openStageModal(leadId, currentStage) {
    document.getElementById('stageLeadId').value = leadId;
    document.getElementById('stageSelect').value = currentStage;
    document.getElementById('stageModal').classList.add('open');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('open');
}

document.querySelectorAll('.modal-bg').forEach(m => m.addEventListener('click', function(e){
    if(e.target === this) this.classList.remove('open');
}));
</script>

</body>
</html>