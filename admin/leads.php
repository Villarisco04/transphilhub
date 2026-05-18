<?php
require_once '../includes/db.php';
require_once '../includes/notifications.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

/* ─────────────────────────────────────────────
   HANDLE ASSIGN LEAD
───────────────────────────────────────────── */
if(isset($_POST['assign_lead'])){
    $lead_id  = (int)$_POST['lead_id'];
    $agent_id = (int)$_POST['agent_id'];

    // Get lead details for notification
    $stmt = $pdo->prepare("
        SELECT l.*, c.full_name as client_name, p.title as property_title 
        FROM leads l
        JOIN users c ON l.client_id = c.id
        JOIN properties p ON l.property_id = p.id
        WHERE l.id = ?
    ");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch();

    if($lead){
        // Update lead with agent
        $update = $pdo->prepare("UPDATE leads SET agent_id = ?, assigned_by = ? WHERE id = ?");
        $update->execute([$agent_id, $_SESSION['user_id'], $lead_id]);
        
        // Send notification to agent
        $notif_msg = "New lead assigned: {$lead['client_name']} - {$lead['property_title']}";
        add_notification($agent_id, $notif_msg, "agent/dashboard.php?lead={$lead_id}");
        
        $success = "Lead assigned successfully!";
    } else {
        $error = "Lead not found.";
    }
}

/* ─────────────────────────────────────────────
   HANDLE UPDATE STAGE
───────────────────────────────────────────── */
if(isset($_POST['update_stage'])){
    $lead_id = (int)$_POST['lead_id'];
    $stage   = $_POST['stage'];
    $notes   = trim($_POST['notes']);

    // Get lead details for client notification
    $stmt = $pdo->prepare("
        SELECT l.*, c.id as client_id, p.title as property_title 
        FROM leads l
        JOIN users c ON l.client_id = c.id
        JOIN properties p ON l.property_id = p.id
        WHERE l.id = ?
    ");
    $stmt->execute([$lead_id]);
    $lead = $stmt->fetch();

    if($lead){
        $update = $pdo->prepare("UPDATE leads SET stage=?, notes=CONCAT(IFNULL(notes,''), '\n---\n', NOW(), ' - Admin: ', ?) WHERE id=?");
        $update->execute([$stage, $notes, $lead_id]);
        
        // Notify client about stage update
        $stage_names = [
            'new' => 'received',
            'contacted' => 'has been contacted by our agent',
            'viewing' => 'viewing scheduled',
            'negotiation' => 'in negotiation phase',
            'closed' => 'completed successfully! Congratulations!'
        ];
        $action = $stage_names[$stage] ?? 'updated';
        $notif_msg = "Your inquiry for {$lead['property_title']} has been {$action}.";
        add_notification($lead['client_id'], $notif_msg, "client/dashboard.php?lead={$lead_id}");
        
        $success = "Lead stage updated!";
    } else {
        $error = "Lead not found.";
    }
}

/* ─────────────────────────────────────────────
   HANDLE DELETE
───────────────────────────────────────────── */
if(isset($_GET['delete'])){
    $pdo->prepare("DELETE FROM leads WHERE id=?")->execute([$_GET['delete']]);
    $success = "Lead deleted.";
}

/* ─────────────────────────────────────────────
   HANDLE ADD LEAD
───────────────────────────────────────────── */
if(isset($_POST['add_lead'])){

    $client_id   = (int)$_POST['client_id'];
    $property_id = (int)$_POST['property_id'];
    $agent_id    = !empty($_POST['agent_id']) ? (int)$_POST['agent_id'] : null;
    $notes       = trim($_POST['notes']);
    $priority    = $_POST['priority'];

    $stmt = $pdo->prepare("
        INSERT INTO leads
        (client_id, property_id, agent_id, notes, priority, stage)
        VALUES (?,?,?,?,?,'new')
    ");

    $stmt->execute([
        $client_id,
        $property_id,
        $agent_id,
        $notes,
        $priority
    ]);
    
    $lead_id = $pdo->lastInsertId();
    
    // Get property title for notification
    $prop = $pdo->prepare("SELECT title FROM properties WHERE id = ?");
    $prop->execute([$property_id]);
    $property_title = $prop->fetchColumn();
    
    // Get client name
    $client = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $client->execute([$client_id]);
    $client_name = $client->fetchColumn();
    
    // Notify admin about new lead
    add_notification(1, "New lead created: {$client_name} - {$property_title}", "admin/leads.php?view={$lead_id}");
    
    // If agent assigned, notify them
    if($agent_id){
        add_notification($agent_id, "New lead assigned: {$client_name} - {$property_title}", "agent/dashboard.php?lead={$lead_id}");
    }

    $success = "Lead created successfully!";
}

/* ─────────────────────────────────────────────
   FETCH DATA
───────────────────────────────────────────── */

$agents = $pdo->query("
    SELECT id, full_name
    FROM users
    WHERE role='agent'
    AND status='active'
    ORDER BY full_name
")->fetchAll();

$clients = $pdo->query("
    SELECT id, full_name
    FROM users
    WHERE role='client'
    ORDER BY full_name
")->fetchAll();

$properties = $pdo->query("
    SELECT id, title
    FROM properties
    WHERE status='available'
    ORDER BY title
")->fetchAll();

/* ─────────────────────────────────────────────
   FILTERS + SEARCH
───────────────────────────────────────────── */

$stage_filter = $_GET['stage'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where_parts = [];

if($stage_filter !== 'all'){
    $where_parts[] = "l.stage = ".$pdo->quote($stage_filter);
}

if($search !== ''){
    $search_sql = $pdo->quote("%$search%");
    $where_parts[] = "(
        c.full_name LIKE $search_sql
        OR a.full_name LIKE $search_sql
        OR p.title LIKE $search_sql
    )";
}

$where = '';
if(!empty($where_parts)){
    $where = "WHERE " . implode(" AND ", $where_parts);
}

/* ─────────────────────────────────────────────
   PAGINATION
───────────────────────────────────────────── */

$limit = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$total_rows = $pdo->query("
    SELECT COUNT(*)
    FROM leads l
    LEFT JOIN users c ON l.client_id = c.id
    LEFT JOIN users a ON l.agent_id = a.id
    LEFT JOIN properties p ON l.property_id = p.id
    $where
")->fetchColumn();

$total_pages = ceil($total_rows / $limit);

/* ─────────────────────────────────────────────
   LEADS QUERY
───────────────────────────────────────────── */

$leads = $pdo->query("
    SELECT l.*,
           c.full_name AS client_name,
           c.email AS client_email,
           a.full_name AS agent_name,
           p.title AS property_title,
           p.type AS property_type,
           p.price AS property_price,
           p.image AS property_image
    FROM leads l
    LEFT JOIN users c ON l.client_id = c.id
    LEFT JOIN users a ON l.agent_id = a.id
    LEFT JOIN properties p ON l.property_id = p.id
    $where
    ORDER BY FIELD(l.priority,'high','medium','low'), l.created_at DESC
    LIMIT $limit OFFSET $offset
")->fetchAll();

/* ─────────────────────────────────────────────
   STAGE COUNTS
───────────────────────────────────────────── */

$stage_counts = [];
foreach(['new','contacted','viewing','negotiation','closed'] as $s){
    $stage_counts[$s] = $pdo->query("SELECT COUNT(*) FROM leads WHERE stage='$s'")->fetchColumn();
}
$total_leads = array_sum($stage_counts);

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lead Management — Trans-Phil House Hub</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{
    --navy:#1a3a6b;
    --navy2:#0f2340;
    --green:#2db12b;
    --orange:#f07800;
    --orange2:#d66900;
    --bg:#f3f4fa;
    --card:#ffffff;
    --border:#e5e7ef;
    --text:#1e1d2e;
    --muted:#7a778d;
    --radius:16px;
    --shadow:0 4px 18px rgba(26,58,107,.07);
}

*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;}

/* SIDEBAR */
.sidebar{width:260px;background:var(--navy2);position:fixed;height:100vh;display:flex;flex-direction:column;z-index:200;}
.sb-logo{padding:24px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:12px;}
.sb-logo svg{width:42px;height:42px;}
.t1{font-size:15px;font-weight:700;color:#fff;}
.t2{font-size:10px;color:var(--orange);text-transform:uppercase;letter-spacing:1px;}
.sb-section{padding:18px 20px 5px;font-size:10px;text-transform:uppercase;letter-spacing:1.5px;color:rgba(255,255,255,.35);}
.nav-item{display:flex;align-items:center;gap:14px;padding:12px 20px;color:rgba(255,255,255,.68);text-decoration:none;font-size:14px;font-weight:500;border-left:3px solid transparent;transition:.2s;}
.nav-item:hover{color:#fff;background:rgba(255,255,255,.05);}
.nav-item.active{background:rgba(45,177,43,.12);color:#fff;border-left:3px solid var(--green);}
.nav-badge{margin-left:auto;background:var(--orange);color:#fff;font-size:10px;padding:2px 7px;border-radius:20px;}
.sb-footer{margin-top:auto;padding:15px 0;border-top:1px solid rgba(255,255,255,.08);}

/* MAIN */
.main{flex:1;margin-left:260px;}
.topbar{height:70px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 30px;position:sticky;top:0;z-index:100;}
.topbar h1{font-size:22px;color:var(--navy);}
.topbar p{font-size:12px;color:var(--muted);margin-top:3px;}
.user-chip{display:flex;align-items:center;gap:10px;background:var(--bg);padding:6px 14px 6px 6px;border-radius:40px;}
.user-avatar{width:34px;height:34px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;}
.user-chip span{font-size:13px;font-weight:600;}
.content{padding:28px;}

.alert{padding:14px 16px;border-radius:10px;margin-bottom:20px;background:#e8f8e8;border-left:4px solid var(--green);color:#218f1f;font-size:13px;}
.alert-error{background:#fee2e2;border-left:4px solid #dc2626;color:#991b1b;}

/* PIPELINE */
.pipe-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px;}
.pipe-card{background:#fff;border-radius:14px;padding:18px;text-align:center;text-decoration:none;border:1px solid var(--border);box-shadow:var(--shadow);transition:.2s;}
.pipe-card:hover{transform:translateY(-3px);}
.active-filter{border-color:var(--orange);box-shadow:0 0 0 3px rgba(240,120,0,.12);}
.pipe-num{font-size:28px;font-weight:700;}
.pipe-label{margin-top:5px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);}
.pipe-pct{margin-top:4px;font-size:11px;font-weight:600;}

/* CARD */
.card{background:#fff;border-radius:var(--radius);padding:24px;border:1px solid var(--border);box-shadow:var(--shadow);margin-bottom:22px;}
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:12px;}
.card-head h2{font-size:16px;color:var(--navy);}
.card-head i{margin-right:8px;color:var(--orange);}

/* SEARCH */
.search-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;}
.search-bar input{width:280px;padding:11px 14px;border:1px solid var(--border);border-radius:10px;font-family:inherit;font-size:13px;}
.search-bar input:focus{outline:none;border-color:var(--orange);}

/* FORM */
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;}
.fg label{display:block;margin-bottom:6px;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;}
.fg input,.fg select,.fg textarea{width:100%;padding:11px 13px;border:1px solid var(--border);border-radius:10px;font-family:inherit;background:#fafafb;font-size:13px;}
.fg input:focus,.fg select:focus,.fg textarea:focus{outline:none;border-color:var(--orange);}
.btn-primary{margin-top:16px;background:var(--orange);color:#fff;border:none;padding:11px 22px;border-radius:10px;cursor:pointer;font-weight:600;transition:.2s;}
.btn-primary:hover{background:var(--orange2);}

/* TABLE */
.tbl-wrap{overflow-x:auto;}
.tbl{width:100%;min-width:950px;border-collapse:collapse;}
.tbl th{padding:12px;background:#fafafb;border-bottom:1px solid var(--border);text-align:left;font-size:11px;text-transform:uppercase;color:var(--muted);letter-spacing:.7px;}
.tbl td{padding:14px 12px;border-bottom:1px solid var(--border);font-size:13px;vertical-align:middle;}
.tbl tr:hover td{background:#fcfcfe;}

/* PROPERTY */
.property-cell{display:flex;align-items:center;gap:12px;}
.property-cell img{width:64px;height:48px;object-fit:cover;border-radius:10px;background:#f3f4f6;}

/* BADGES */
.badge{display:inline-block;padding:4px 10px;border-radius:30px;font-size:11px;font-weight:700;}
.b-new{background:#e3ebfb;color:var(--navy);}
.b-contacted{background:#fff0df;color:#d66900;}
.b-viewing{background:#e8f8e8;color:#218f1f;}
.b-negotiation{background:#fff8dc;color:#a07000;}
.b-closed{background:#e4f8e4;color:#1b8a1b;}
.p-high{background:#ffe4e4;color:#c0392b;}
.p-medium{background:#fff0df;color:#d66900;}
.p-low{background:#e8f8e8;color:#218f1f;}

/* BUTTONS */
.btn-sm{border:none;padding:7px 12px;border-radius:8px;cursor:pointer;font-size:11px;font-weight:600;transition:.2s;}
.btn-assign{background:#e5ebf8;color:var(--navy);}
.btn-stage{background:#e8f8e8;color:#218f1f;}
.btn-delete{background:#ffeaea;color:#c0392b;}
.btn-sm:hover{opacity:.88;}

/* PAGINATION */
.pagination{display:flex;justify-content:center;gap:10px;margin-top:22px;}
.page-btn{padding:9px 14px;border-radius:10px;text-decoration:none;border:1px solid var(--border);background:#fff;color:var(--text);font-size:13px;font-weight:600;}
.page-btn.active{background:var(--orange);color:#fff;border-color:var(--orange);}

/* MODAL - FIXED */
.modal-bg {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: none !important;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}
.modal-bg.open {
    display: flex !important;
}
.modal-box {
    background: #fff;
    width: 90%;
    max-width: 450px;
    border-radius: 18px;
    padding: 28px;
    position: relative;
    box-shadow: 0 20px 35px rgba(0,0,0,0.2);
}
.modal-box h3 {
    margin-bottom: 18px;
    color: var(--navy);
    font-size: 20px;
}
.modal-close {
    position: absolute;
    top: 15px;
    right: 20px;
    background: none;
    border: none;
    font-size: 22px;
    cursor: pointer;
    color: var(--muted);
}
.modal-close:hover {
    color: #c0392b;
}
.modal-box .fg {
    margin-bottom: 15px;
}
.modal-box .btn-primary {
    width: 100%;
    margin-top: 10px;
}

@media(max-width:1100px){.pipe-grid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:900px){
    .sidebar{display:none;}
    .main{margin-left:0;}
    .content{padding:18px;}
    .pipe-grid{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:650px){
    .pipe-grid{grid-template-columns:1fr;}
    .topbar{padding:0 18px;}
    .search-bar input{width:100%;}
}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sb-logo">
        <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="18" fill="none" stroke="#1a3a6b" stroke-width="2.5" stroke-dasharray="95 15"/><polygon points="20,5 30,15 10,15" fill="#2db12b"/><rect x="11" y="15" width="7" height="11" fill="#f07800"/><rect x="22" y="15" width="7" height="11" fill="#f07800"/><rect x="17" y="15" width="6" height="16" fill="#2db12b"/></svg>
        <div><div class="t1">Trans-Phil Hub</div><div class="t2">Administrator</div></div>
    </div>
    <div class="sb-section">Main Menu</div>
    <a href="dashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="properties.php" class="nav-item"><i class="fas fa-building"></i> Properties</a>
    <a href="leads.php" class="nav-item active"><i class="fas fa-funnel-dollar"></i> Lead Management
        <?php if($stage_counts['new'] > 0): ?><span class="nav-badge"><?php echo $stage_counts['new']; ?></span><?php endif; ?>
    </a>
    <div class="sb-section">Management</div>
    <a href="users.php" class="nav-item"><i class="fas fa-users"></i> User Management</a>
    <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i> Reports & Analytics</a>
    <div class="sb-footer"><a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
</aside>

<div class="main">
    <div class="topbar">
        <div><h1>Lead Management</h1><p><?php echo date('l, F j, Y'); ?></p></div>
        <div class="user-chip"><div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'],0,1)); ?></div><span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span></div>
    </div>

    <div class="content">
        <?php if(isset($success)): ?>
            <div class="alert"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- PIPELINE -->
        <div class="pipe-grid">
            <?php
            $pipe_colors = ['new'=>'#1a3a6b','contacted'=>'#f07800','viewing'=>'#2db12b','negotiation'=>'#a07000','closed'=>'#218f1f'];
            foreach($stage_counts as $s => $cnt):
                $pct = $total_leads > 0 ? round($cnt/$total_leads*100) : 0;
                $active = ($stage_filter == $s) ? 'active-filter' : '';
            ?>
            <a href="?stage=<?php echo $s; ?>" class="pipe-card <?php echo $active; ?>">
                <div class="pipe-num" style="color:<?php echo $pipe_colors[$s]; ?>"><?php echo $cnt; ?></div>
                <div class="pipe-label"><?php echo ucfirst($s); ?></div>
                <div class="pipe-pct" style="color:<?php echo $pipe_colors[$s]; ?>"><?php echo $pct; ?>%</div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- CREATE LEAD -->
        <div class="card">
            <div class="card-head"><h2><i class="fas fa-plus-circle"></i>Create New Lead</h2></div>
            <form method="POST">
                <div class="form-grid">
                    <div class="fg"><label>Client *</label><select name="client_id" required><option value="">Select client...</option><?php foreach($clients as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['full_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="fg"><label>Property *</label><select name="property_id" required><option value="">Select property...</option><?php foreach($properties as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?></option><?php endforeach; ?></select></div>
                    <div class="fg"><label>Assign Agent</label><select name="agent_id"><option value="">Unassigned</option><?php foreach($agents as $a): ?><option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['full_name']); ?></option><?php endforeach; ?></select></div>
                    <div class="fg"><label>Priority</label><select name="priority"><option value="high">High</option><option value="medium" selected>Medium</option><option value="low">Low</option></select></div>
                    <div class="fg" style="grid-column:1/-1;"><label>Notes</label><textarea name="notes" rows="3" placeholder="Optional lead notes..."></textarea></div>
                </div>
                <button type="submit" name="add_lead" class="btn-primary"><i class="fas fa-plus"></i> Create Lead</button>
            </form>
        </div>

        <!-- LEADS TABLE -->
        <div class="card">
            <div class="card-head"><h2><i class="fas fa-list-alt"></i> Lead Directory</h2></div>
            <form method="GET" class="search-bar">
                <input type="text" name="search" placeholder="Search client, property or agent..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                <?php if($stage_filter !== 'all'): ?><input type="hidden" name="stage" value="<?php echo $stage_filter; ?>"><?php endif; ?>
                <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Search</button>
            </form>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>#</th><th>Client</th><th>Property</th><th>Agent</th><th>Priority</th><th>Stage</th><th>Notes</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($leads as $lead): ?>
                        <tr>
                            <td style="color:var(--muted);">#<?php echo $lead['id']; ?></td>
                            <td><div style="font-weight:700;"><?php echo htmlspecialchars($lead['client_name'] ?? '—'); ?></div><div style="font-size:11px;color:var(--muted);"><?php echo htmlspecialchars($lead['client_email'] ?? ''); ?></div></td>
                            <td><div class="property-cell"><?php $img = $lead['property_image'] ?? 'property1.png'; $path = file_exists('../assets/images/'.$img) ? '../assets/images/'.$img : '../assets/images/property1.png'; ?><img src="<?php echo $path; ?>" onerror="this.src='../assets/images/property1.png'"><div><div style="font-weight:600;"><?php echo htmlspecialchars($lead['property_title'] ?? '—'); ?></div><?php if($lead['property_price']): ?><div style="font-size:11px;color:var(--muted);">₱ <?php echo number_format($lead['property_price']); ?></div><?php endif; ?></div></div></td>
                            <td><?php echo htmlspecialchars($lead['agent_name'] ?? 'Unassigned'); ?></td>
                            <td><span class="badge p-<?php echo $lead['priority']; ?>"><?php echo ucfirst($lead['priority']); ?></span></td>
                            <td><span class="badge b-<?php echo $lead['stage']; ?>"><?php echo ucfirst($lead['stage']); ?></span></td>
                            <td style="max-width:180px;"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);font-size:12px;"><?php echo htmlspecialchars($lead['notes'] ?: '—'); ?></div></td>
                            <td style="font-size:11px;color:var(--muted);"><?php echo date('M d, Y', strtotime($lead['created_at'])); ?></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn-sm btn-assign" onclick="openAssignModal(<?php echo $lead['id']; ?>)"><i class="fas fa-user-tag"></i> Assign</button>
                                <button type="button" class="btn-sm btn-stage" onclick="openStageModal(<?php echo $lead['id']; ?>, '<?php echo $lead['stage']; ?>', '<?php echo addslashes($lead['notes']); ?>')"><i class="fas fa-edit"></i> Stage</button>
                                <a href="?delete=<?php echo $lead['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this lead?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($leads)): ?> hilab<td colspan="9" style="text-align:center;padding:40px;color:var(--muted);">No leads found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if($total_pages > 1): ?>
            <div class="pagination">
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <a class="page-btn <?php echo $page == $i ? 'active' : ''; ?>" href="?page=<?php echo $i; ?>&stage=<?php echo $stage_filter; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ASSIGN MODAL -->
<div class="modal-bg" id="assignModal">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="closeAssignModal()">&times;</button>
        <h3><i class="fas fa-user-tag" style="color:var(--orange);margin-right:8px;"></i> Assign Lead</h3>
        <form method="POST" onsubmit="return confirm('Assign this lead to selected agent?')">
            <input type="hidden" name="lead_id" id="assign_lead_id">
            <div class="fg">
                <label>Select Agent</label>
                <select name="agent_id" required>
                    <option value="">Choose agent...</option>
                    <?php foreach($agents as $a): ?>
                        <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="assign_lead" class="btn-primary">Assign Agent</button>
        </form>
    </div>
</div>

<!-- STAGE MODAL -->
<div class="modal-bg" id="stageModal">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="closeStageModal()">&times;</button>
        <h3><i class="fas fa-edit" style="color:var(--green);margin-right:8px;"></i> Update Lead Stage</h3>
        <form method="POST" onsubmit="return confirm('Update this lead stage?')">
            <input type="hidden" name="lead_id" id="stage_lead_id">
            <div class="fg" style="margin-bottom:16px;">
                <label>Stage</label>
                <select name="stage" id="stage_select" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7ef;">
                    <option value="new">📋 New</option>
                    <option value="contacted">📞 Contacted</option>
                    <option value="viewing">🏠 Viewing</option>
                    <option value="negotiation">🤝 Negotiation</option>
                    <option value="closed">✅ Closed</option>
                </select>
            </div>
            <div class="fg">
                <label>Notes / Updates</label>
                <textarea name="notes" rows="4" id="stage_notes" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7ef; font-family:inherit;"></textarea>
            </div>
            <button type="submit" name="update_stage" class="btn-primary">Update Stage</button>
        </form>
    </div>
</div>

<script>
// Assign Modal Functions
function openAssignModal(leadId) {
    console.log("Opening assign modal for lead:", leadId);
    var modal = document.getElementById('assignModal');
    var input = document.getElementById('assign_lead_id');
    if(modal && input) {
        input.value = leadId;
        modal.classList.add('open');
        console.log("Assign modal opened");
    } else {
        console.error("Assign modal elements not found!");
    }
}

function closeAssignModal() {
    var modal = document.getElementById('assignModal');
    if(modal) {
        modal.classList.remove('open');
    }
}

// Stage Modal Functions - FIXED
function openStageModal(leadId, currentStage, currentNotes) {
    console.log("Opening stage modal for lead:", leadId, "Stage:", currentStage);
    
    var modal = document.getElementById('stageModal');
    var leadIdInput = document.getElementById('stage_lead_id');
    var stageSelect = document.getElementById('stage_select');
    var notesTextarea = document.getElementById('stage_notes');
    
    if(modal && leadIdInput && stageSelect && notesTextarea) {
        leadIdInput.value = leadId;
        stageSelect.value = currentStage;
        notesTextarea.value = currentNotes || '';
        modal.classList.add('open');
        console.log("Stage modal opened successfully");
    } else {
        console.error("Stage modal elements not found!", {
            modal: !!modal,
            leadIdInput: !!leadIdInput,
            stageSelect: !!stageSelect,
            notesTextarea: !!notesTextarea
        });
    }
}

function closeStageModal() {
    var modal = document.getElementById('stageModal');
    if(modal) {
        modal.classList.remove('open');
        console.log("Stage modal closed");
    }
}

// Close modals when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    // Assign modal outside click
    var assignModal = document.getElementById('assignModal');
    if(assignModal) {
        assignModal.addEventListener('click', function(e) {
            if(e.target === this) {
                this.classList.remove('open');
            }
        });
    }
    
    // Stage modal outside click
    var stageModal = document.getElementById('stageModal');
    if(stageModal) {
        stageModal.addEventListener('click', function(e) {
            if(e.target === this) {
                this.classList.remove('open');
            }
        });
    }
    
    // Debug: Check all stage buttons
    var stageButtons = document.querySelectorAll('.btn-stage');
    console.log("Found stage buttons:", stageButtons.length);
    stageButtons.forEach(function(btn, idx) {
        console.log("Stage button " + idx + ":", btn);
        console.log("Button onclick attribute:", btn.getAttribute('onclick'));
    });
    
    console.log("Page loaded successfully");
});
</script>

</body>
</html>