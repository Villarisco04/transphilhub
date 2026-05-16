<?php
require_once '../includes/db.php';
session_start();

// Check if user is logged in and is agent
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent'){
    header("Location: ../login.php");
    exit;
}

$agent_id = $_SESSION['user_id'];

// Get all clients assigned to this agent (through leads assigned by ADMIN)
$clients = $pdo->prepare("
    SELECT DISTINCT
        c.id,
        c.full_name,
        c.email,
        c.phone,
        c.address,
        c.status,
        c.created_at as client_since,
        COUNT(l.id) as total_inquiries,
        SUM(CASE WHEN l.stage = 'new' THEN 1 ELSE 0 END) as new_inquiries,
        SUM(CASE WHEN l.stage = 'contacted' THEN 1 ELSE 0 END) as contacted_inquiries,
        SUM(CASE WHEN l.stage = 'viewing' THEN 1 ELSE 0 END) as viewing_inquiries,
        SUM(CASE WHEN l.stage = 'negotiation' THEN 1 ELSE 0 END) as negotiation_inquiries,
        SUM(CASE WHEN l.stage = 'closed' THEN 1 ELSE 0 END) as closed_inquiries,
        MAX(l.created_at) as last_inquiry_date
    FROM leads l
    JOIN users c ON l.client_id = c.id
    WHERE l.agent_id = ?
    GROUP BY c.id
    ORDER BY last_inquiry_date DESC
");
$clients->execute([$agent_id]);
$clients = $clients->fetchAll();

// Get statistics
$stats = [];
$stats['total_clients'] = count($clients);
$stats['total_inquiries'] = 0;
$stats['active_inquiries'] = 0;
$stats['closed_deals'] = 0;

foreach($clients as $client){
    $stats['total_inquiries'] += $client['total_inquiries'];
    $stats['closed_deals'] += $client['closed_inquiries'];
    $active = $client['new_inquiries'] + $client['contacted_inquiries'] + $client['viewing_inquiries'] + $client['negotiation_inquiries'];
    if($active > 0){
        $stats['active_inquiries']++;
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Assigned Clients — Trans-Phil House Hub</title>
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

.main{flex:1;margin-left:260px;transition:.3s;min-width:0;}
.sidebar.collapsed ~ .main{margin-left:72px;}

.topbar{background:var(--card);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
.topbar-left h1{font-size:20px;color:var(--navy);font-weight:700;}
.topbar-left p{font-size:12px;color:var(--muted);}
.user-chip{display:flex;align-items:center;gap:10px;background:var(--bg);padding:6px 14px 6px 6px;border-radius:30px;}
.user-avatar{width:32px;height:32px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;}
.user-chip span{font-size:13px;font-weight:600;color:var(--navy);}
.content{padding:28px;}

/* Stats Row */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px;}
.stat-card{background:var(--card);border-radius:var(--radius);padding:20px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow);border:1px solid var(--border);transition:.2s;}
.stat-card:hover{transform:translateY(-3px);}
.stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon i{font-size:22px;}
.stat-num{font-size:28px;font-weight:700;color:var(--navy);line-height:1;}
.stat-label{font-size:12px;color:var(--muted);margin-top:3px;}

/* Welcome Banner */
.welcome-banner{background:linear-gradient(135deg, var(--navy2), var(--navy));border-radius:var(--radius);padding:28px 32px;margin-bottom:24px;}
.welcome-banner h2{font-size:22px;color:#fff;margin-bottom:6px;}
.welcome-banner p{color:rgba(255,255,255,.7);font-size:13px;}
.welcome-banner small{display:block;margin-top:8px;font-size:11px;opacity:0.6;}

/* Filter Bar */
.filter-bar{background:white;border-radius:60px;padding:8px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;box-shadow:var(--shadow);border:1px solid var(--border);}
.filter-search{flex:1;min-width:200px;display:flex;align-items:center;background:var(--bg);border-radius:40px;padding:4px 16px;gap:8px;}
.filter-search i{color:var(--muted);}
.filter-search input{flex:1;border:none;background:transparent;padding:10px 0;font-size:14px;outline:none;}

/* Clients Grid */
.clients-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(350px,1fr));gap:20px;}
.client-card{background:white;border-radius:16px;border:1px solid var(--border);overflow:hidden;transition:.2s;}
.client-card:hover{transform:translateY(-3px);box-shadow:0 8px 25px rgba(0,0,0,0.1);}
.client-header{background:linear-gradient(135deg, var(--navy2), var(--navy));padding:20px;color:white;position:relative;}
.client-avatar{width:50px;height:50px;border-radius:50%;background:var(--orange);display:flex;align-items:center;justify-content:center;margin-bottom:12px;}
.client-avatar i{font-size:24px;color:white;}
.client-name{font-size:18px;font-weight:700;margin-bottom:4px;}
.client-since{font-size:11px;opacity:0.7;}
.client-body{padding:20px;}
.client-info{margin-bottom:16px;}
.info-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:13px;color:var(--text);}
.info-row i{width:20px;color:var(--orange);}
.client-stats{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
.stat-badge{background:var(--bg);padding:6px 12px;border-radius:20px;font-size:11px;font-weight:600;}
.stat-badge i{margin-right:4px;}
.client-actions{display:flex;gap:8px;border-top:1px solid var(--border);padding-top:16px;}
.client-actions button{flex:1;padding:8px;border-radius:8px;border:none;cursor:pointer;font-size:12px;font-weight:600;transition:.2s;}
.btn-view{background:#e0e8f7;color:var(--navy);}
.btn-view:hover{background:#c5d3e8;}
.btn-contact{background:#e6f7e6;color:var(--green2);}
.btn-contact:hover{background:#c8eec8;}

/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal-box{background:#fff;border-radius:16px;padding:28px;width:90%;max-width:700px;max-height:80vh;overflow-y:auto;}
.modal-box h3{font-size:16px;color:var(--navy);margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid var(--border);}
.modal-close{float:right;cursor:pointer;color:var(--muted);background:none;border:none;font-size:18px;}
.info-note{background:#fef9e0;padding:10px 15px;border-radius:8px;margin-bottom:15px;font-size:12px;color:#a07000;}
.info-note i{margin-right:6px;}
.leads-table{width:100%;border-collapse:collapse;}
.leads-table th{padding:10px;font-size:11px;text-transform:uppercase;color:var(--muted);background:#f8f7fc;text-align:left;}
.leads-table td{padding:10px;font-size:12px;border-bottom:1px solid var(--border);}
.leads-table tr:last-child td{border-bottom:none;}
.property-img-sm{width:40px;height:35px;object-fit:cover;border-radius:6px;}
.badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
.b-new{background:#e0e8f7;color:#1a3a6b;}
.b-contacted{background:#fff0df;color:#f07800;}
.b-viewing{background:#e6f7e6;color:#2db12b;}
.b-negotiation{background:#fef9e0;color:#a07000;}
.b-closed{background:#e8f7e8;color:#218f1f;}
.no-data{text-align:center;padding:60px;color:var(--muted);}
.no-data i{font-size:48px;margin-bottom:15px;opacity:0.5;}

/* Mobile */
.mob-toggle{display:none;position:fixed;bottom:20px;right:20px;width:48px;height:48px;background:var(--navy);border-radius:50%;align-items:center;justify-content:center;z-index:400;cursor:pointer;border:none;color:#fff;font-size:18px;box-shadow:0 4px 16px rgba(0,0,0,.2);}

@media(max-width:1100px){.stats-row{grid-template-columns:repeat(2,1fr);}}
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);width:260px;}
    .sidebar.show{transform:translateX(0);}
    .main{margin-left:0!important;}
    .mob-toggle{display:flex;}
    .stats-row{grid-template-columns:1fr;}
    .clients-grid{grid-template-columns:1fr;}
    .filter-bar{flex-direction:column;border-radius:20px;padding:16px;}
    .filter-search{width:100%;}
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
            <h1>My Assigned Clients</h1>
            <p><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="user-chip">
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'],0,1)); ?></div>
            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
    </div>

    <div class="content">

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>My Assigned Client List </h2>
            <p>These clients have been assigned to you by the administrator. Track their inquiries and update lead stages.</p>
            <small><i class="fas fa-info-circle"></i> Leads are assigned by Admin. You cannot assign leads to yourself.</small>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e8f5e9;"><i class="fas fa-users" style="color:var(--green);"></i></div>
                <div><div class="stat-num"><?php echo $stats['total_clients']; ?></div><div class="stat-label">Assigned Clients</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff3e0;"><i class="fas fa-chat" style="color:var(--orange);"></i></div>
                <div><div class="stat-num"><?php echo $stats['active_inquiries']; ?></div><div class="stat-label">Active Inquiries</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e3f2fd;"><i class="fas fa-envelope" style="color:var(--navy);"></i></div>
                <div><div class="stat-num"><?php echo $stats['total_inquiries']; ?></div><div class="stat-label">Total Inquiries</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fce4ec;"><i class="fas fa-trophy" style="color:#f07800;"></i></div>
                <div><div class="stat-num"><?php echo $stats['closed_deals']; ?></div><div class="stat-label">Closed Deals</div></div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="filter-bar">
            <div class="filter-search">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search assigned clients by name, email, or phone...">
            </div>
        </div>

        <!-- Clients Grid -->
        <?php if(count($clients) > 0): ?>
            <div class="clients-grid" id="clientsGrid">
                <?php foreach($clients as $client): ?>
                    <div class="client-card" data-client-name="<?php echo strtolower($client['full_name']); ?>" data-client-email="<?php echo strtolower($client['email']); ?>" data-client-phone="<?php echo strtolower($client['phone'] ?? ''); ?>">
                        <div class="client-header">
                            <div class="client-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="client-name"><?php echo htmlspecialchars($client['full_name']); ?></div>
                            <div class="client-since">Assigned since <?php echo date('M Y', strtotime($client['client_since'])); ?></div>
                        </div>
                        <div class="client-body">
                            <div class="client-info">
                                <div class="info-row"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($client['email']); ?></div>
                                <div class="info-row"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($client['phone'] ?: 'Not provided'); ?></div>
                                <div class="info-row"><i class="fas fa-clock"></i> Last activity: <?php echo date('M d, Y', strtotime($client['last_inquiry_date'])); ?></div>
                            </div>
                            <div class="client-stats">
                                <span class="stat-badge"><i class="fas fa-star"></i> New: <?php echo $client['new_inquiries']; ?></span>
                                <span class="stat-badge"><i class="fas fa-phone"></i> Contacted: <?php echo $client['contacted_inquiries']; ?></span>
                                <span class="stat-badge"><i class="fas fa-eye"></i> Viewing: <?php echo $client['viewing_inquiries']; ?></span>
                                <span class="stat-badge"><i class="fas fa-handshake"></i> Negotiation: <?php echo $client['negotiation_inquiries']; ?></span>
                                <span class="stat-badge"><i class="fas fa-check-circle"></i> Closed: <?php echo $client['closed_inquiries']; ?></span>
                            </div>
                            <div class="client-actions">
                                <button class="btn-view" onclick="viewClientDetails(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['full_name']); ?>')">
                                    <i class="fas fa-chart-line"></i> View Inquiries
                                </button>
                                <button class="btn-contact" onclick="contactClient('<?php echo htmlspecialchars($client['email']); ?>')">
                                    <i class="fas fa-envelope"></i> Contact Client
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card" style="padding:0;">
                <div class="no-data">
                    <i class="fas fa-users"></i>
                    <p>No clients assigned to you yet.</p>
                    <p style="font-size:12px;margin-top:8px;">The administrator will assign leads to you. Check back later!</p>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Client Details Modal -->
<div class="modal-bg" id="clientModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('clientModal')"><i class="fas fa-times"></i></button>
        <h3 id="modalTitle"><i class="fas fa-chart-line"></i> Client Inquiries</h3>
        <div class="info-note">
            <i class="fas fa-info-circle"></i> These inquiries were assigned to you by the administrator. Update their stages as you progress.
        </div>
        <div id="clientLeadsContent">
            <div style="text-align:center; padding:20px;">Loading...</div>
        </div>
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

// Search functionality
const searchInput = document.getElementById('searchInput');
if(searchInput){
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const cards = document.querySelectorAll('.client-card');
        cards.forEach(card => {
            const name = card.getAttribute('data-client-name');
            const email = card.getAttribute('data-client-email');
            const phone = card.getAttribute('data-client-phone');
            if(name.includes(searchTerm) || email.includes(searchTerm) || phone.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
}

function viewClientDetails(clientId, clientName) {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user"></i> ' + clientName + ' - Property Inquiries';
    
    // Fetch client leads via AJAX
    fetch(`get_client_leads.php?client_id=${clientId}`)
        .then(response => response.json())
        .then(data => {
            if(data.success && data.leads.length > 0) {
                let html = '<table class="leads-table">';
                html += '<thead><tr><th>Property</th><th>Price</th><th>Stage</th><th>Date</th><th>Action</th></tr></thead><tbody>';
                data.leads.forEach(lead => {
                    html += `
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <img src="../assets/images/${lead.image || 'property1.png'}" class="property-img-sm" onerror="this.src='../assets/images/property1.png'">
                                    <div>
                                        <div style="font-weight:600;">${escapeHtml(lead.property_title)}</div>
                                        <div style="font-size:11px;color:var(--muted);">${escapeHtml(lead.location)}</div>
                                    </div>
                                </div>
                            </td>
                            <td>₱ ${parseInt(lead.price).toLocaleString()}</td>
                            <td><span class="badge b-${lead.stage}">${lead.stage.charAt(0).toUpperCase() + lead.stage.slice(1)}</span></td>
                            <td style="font-size:12px;color:var(--muted);">${new Date(lead.created_at).toLocaleDateString()}</td>
                            <td>
                                <a href="dashboard.php" style="color:var(--orange);text-decoration:none;font-size:12px;">
                                    <i class="fas fa-exchange-alt"></i> Update
                                </a>
                            </td>
                        </tr>
                    `;
                });
                html += '</tbody></table>';
                document.getElementById('clientLeadsContent').innerHTML = html;
            } else {
                document.getElementById('clientLeadsContent').innerHTML = '<div class="no-data"><i class="fas fa-folder-open"></i><p>No property inquiries found for this client.</p></div>';
            }
        })
        .catch(error => {
            document.getElementById('clientLeadsContent').innerHTML = '<div class="no-data"><i class="fas fa-exclamation-circle"></i><p>Error loading client data.</p></div>';
        });
    
    document.getElementById('clientModal').classList.add('open');
}

function contactClient(email) {
    window.location.href = `mailto:${email}?subject=Property Inquiry Follow-up - Trans-Phil House Hub`;
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('open');
}

function escapeHtml(str) {
    if(!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if(m === '&') return '&amp;';
        if(m === '<') return '&lt;';
        if(m === '>') return '&gt;';
        return m;
    });
}

document.querySelectorAll('.modal-bg').forEach(m => m.addEventListener('click', function(e){
    if(e.target === this) this.classList.remove('open');
}));
</script>

</body>
</html>