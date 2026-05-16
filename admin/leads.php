<?php
require_once '../includes/notify.php';
require_once '../includes/db.php';
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

    $stmt = $pdo->prepare("UPDATE leads SET agent_id=? WHERE id=?");
    $stmt->execute([$agent_id, $lead_id]);

    $notif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $notif->execute([$agent_id, "You have been assigned a new lead (#$lead_id)."]);

    $success = "Lead assigned successfully!";
}

/* ─────────────────────────────────────────────
   HANDLE UPDATE STAGE
───────────────────────────────────────────── */
if(isset($_POST['update_stage'])){
    $lead_id = (int)$_POST['lead_id'];
    $stage   = $_POST['stage'];
    $notes   = trim($_POST['notes']);

    $stmt = $pdo->prepare("UPDATE leads SET stage=?, notes=? WHERE id=?");
    $stmt->execute([$stage, $notes, $lead_id]);

    $success = "Lead stage updated!";
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

    ORDER BY
        FIELD(l.priority,'high','medium','low'),
        l.created_at DESC

    LIMIT $limit OFFSET $offset
")->fetchAll();

/* ─────────────────────────────────────────────
   STAGE COUNTS
───────────────────────────────────────────── */

$stage_counts = [];

foreach(['new','contacted','viewing','negotiation','closed'] as $s){

    $stage_counts[$s] = $pdo->query("
        SELECT COUNT(*)
        FROM leads
        WHERE stage='$s'
    ")->fetchColumn();
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

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'DM Sans',sans-serif;
    background:var(--bg);
    color:var(--text);
    display:flex;
    min-height:100vh;
}

/* SIDEBAR */

.sidebar{
    width:260px;
    background:var(--navy2);
    position:fixed;
    height:100vh;
    display:flex;
    flex-direction:column;
    z-index:200;
}

.sb-logo{
    padding:24px 20px;
    border-bottom:1px solid rgba(255,255,255,.08);
    display:flex;
    align-items:center;
    gap:12px;
}

.sb-logo svg{
    width:42px;
    height:42px;
}

.t1{
    font-size:15px;
    font-weight:700;
    color:#fff;
}

.t2{
    font-size:10px;
    color:var(--orange);
    text-transform:uppercase;
    letter-spacing:1px;
}

.sb-section{
    padding:18px 20px 5px;
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:1.5px;
    color:rgba(255,255,255,.35);
}

.nav-item{
    display:flex;
    align-items:center;
    gap:14px;
    padding:12px 20px;
    color:rgba(255,255,255,.68);
    text-decoration:none;
    font-size:14px;
    font-weight:500;
    border-left:3px solid transparent;
    transition:.2s;
}

.nav-item:hover{
    color:#fff;
    background:rgba(255,255,255,.05);
}

.nav-item.active{
    background:rgba(45,177,43,.12);
    color:#fff;
    border-left:3px solid var(--green);
}

.nav-badge{
    margin-left:auto;
    background:var(--orange);
    color:#fff;
    font-size:10px;
    padding:2px 7px;
    border-radius:20px;
}

.sb-footer{
    margin-top:auto;
    padding:15px 0;
    border-top:1px solid rgba(255,255,255,.08);
}

/* MAIN */

.main{
    flex:1;
    margin-left:260px;
}

.topbar{
    height:70px;
    background:#fff;
    border-bottom:1px solid var(--border);
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 30px;
    position:sticky;
    top:0;
    z-index:100;
}

.topbar h1{
    font-size:22px;
    color:var(--navy);
}

.topbar p{
    font-size:12px;
    color:var(--muted);
    margin-top:3px;
}

.user-chip{
    display:flex;
    align-items:center;
    gap:10px;
    background:var(--bg);
    padding:6px 14px 6px 6px;
    border-radius:40px;
}

.user-avatar{
    width:34px;
    height:34px;
    border-radius:50%;
    background:var(--navy);
    display:flex;
    align-items:center;
    justify-content:center;
    color:#fff;
    font-weight:700;
}

.user-chip span{
    font-size:13px;
    font-weight:600;
}

.content{
    padding:28px;
}

/* ALERT */

.alert{
    padding:14px 16px;
    border-radius:10px;
    margin-bottom:20px;
    background:#e8f8e8;
    border-left:4px solid var(--green);
    color:#218f1f;
    font-size:13px;
}

/* PIPELINE */

.pipe-grid{
    display:grid;
    grid-template-columns:repeat(5,1fr);
    gap:16px;
    margin-bottom:24px;
}

.pipe-card{
    background:#fff;
    border-radius:14px;
    padding:18px;
    text-align:center;
    text-decoration:none;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
    transition:.2s;
}

.pipe-card:hover{
    transform:translateY(-3px);
}

.active-filter{
    border-color:var(--orange);
    box-shadow:0 0 0 3px rgba(240,120,0,.12);
}

.pipe-num{
    font-size:28px;
    font-weight:700;
}

.pipe-label{
    margin-top:5px;
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.8px;
    color:var(--muted);
}

.pipe-pct{
    margin-top:4px;
    font-size:11px;
    font-weight:600;
}

/* CARD */

.card{
    background:#fff;
    border-radius:var(--radius);
    padding:24px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
    margin-bottom:22px;
}

.card-head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:18px;
    flex-wrap:wrap;
    gap:12px;
}

.card-head h2{
    font-size:16px;
    color:var(--navy);
}

.card-head i{
    margin-right:8px;
    color:var(--orange);
}

/* SEARCH */

.search-bar{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:20px;
}

.search-bar input{
    width:280px;
    padding:11px 14px;
    border:1px solid var(--border);
    border-radius:10px;
    font-family:inherit;
    font-size:13px;
}

.search-bar input:focus{
    outline:none;
    border-color:var(--orange);
}

/* FORM */

.form-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:16px;
}

.fg label{
    display:block;
    margin-bottom:6px;
    font-size:11px;
    font-weight:700;
    color:var(--muted);
    text-transform:uppercase;
}

.fg input,
.fg select,
.fg textarea{
    width:100%;
    padding:11px 13px;
    border:1px solid var(--border);
    border-radius:10px;
    font-family:inherit;
    background:#fafafb;
    font-size:13px;
}

.fg input:focus,
.fg select:focus,
.fg textarea:focus{
    outline:none;
    border-color:var(--orange);
}

.btn-primary{
    margin-top:16px;
    background:var(--orange);
    color:#fff;
    border:none;
    padding:11px 22px;
    border-radius:10px;
    cursor:pointer;
    font-weight:600;
    transition:.2s;
}

.btn-primary:hover{
    background:var(--orange2);
}

/* TABLE */

.tbl-wrap{
    overflow-x:auto;
}

.tbl{
    width:100%;
    min-width:950px;
    border-collapse:collapse;
}

.tbl th{
    padding:12px;
    background:#fafafb;
    border-bottom:1px solid var(--border);
    text-align:left;
    font-size:11px;
    text-transform:uppercase;
    color:var(--muted);
    letter-spacing:.7px;
}

.tbl td{
    padding:14px 12px;
    border-bottom:1px solid var(--border);
    font-size:13px;
    vertical-align:middle;
}

.tbl tr:hover td{
    background:#fcfcfe;
}

/* PROPERTY - FIXED IMAGE PATH */
.property-cell{
    display:flex;
    align-items:center;
    gap:12px;
}

.property-cell img{
    width:64px;
    height:48px;
    object-fit:cover;
    border-radius:10px;
    background:#f3f4f6;
}

/* BADGES */

.badge{
    display:inline-block;
    padding:4px 10px;
    border-radius:30px;
    font-size:11px;
    font-weight:700;
}

.b-new{
    background:#e3ebfb;
    color:var(--navy);
}

.b-contacted{
    background:#fff0df;
    color:#d66900;
}

.b-viewing{
    background:#e8f8e8;
    color:#218f1f;
}

.b-negotiation{
    background:#fff8dc;
    color:#a07000;
}

.b-closed{
    background:#e4f8e4;
    color:#1b8a1b;
}

.p-high{
    background:#ffe4e4;
    color:#c0392b;
}

.p-medium{
    background:#fff0df;
    color:#d66900;
}

.p-low{
    background:#e8f8e8;
    color:#218f1f;
}

/* BUTTONS */

.btn-sm{
    border:none;
    padding:7px 12px;
    border-radius:8px;
    cursor:pointer;
    font-size:11px;
    font-weight:600;
    transition:.2s;
}

.btn-assign{
    background:#e5ebf8;
    color:var(--navy);
}

.btn-stage{
    background:#e8f8e8;
    color:#218f1f;
}

.btn-delete{
    background:#ffeaea;
    color:#c0392b;
}

.btn-sm:hover{
    opacity:.88;
}

/* PAGINATION */

.pagination{
    display:flex;
    justify-content:center;
    gap:10px;
    margin-top:22px;
}

.page-btn{
    padding:9px 14px;
    border-radius:10px;
    text-decoration:none;
    border:1px solid var(--border);
    background:#fff;
    color:var(--text);
    font-size:13px;
    font-weight:600;
}

.page-btn.active{
    background:var(--orange);
    color:#fff;
    border-color:var(--orange);
}

/* MODAL */

.modal-bg{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.4);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:500;
}

.modal-bg.open{
    display:flex;
}

.modal-box{
    background:#fff;
    width:90%;
    max-width:450px;
    border-radius:18px;
    padding:28px;
}

.modal-box h3{
    margin-bottom:18px;
    color:var(--navy);
}

.modal-close{
    float:right;
    background:none;
    border:none;
    cursor:pointer;
    font-size:18px;
    color:var(--muted);
}

/* RESPONSIVE */

@media(max-width:1100px){
    .pipe-grid{
        grid-template-columns:repeat(3,1fr);
    }
}

@media(max-width:900px){

    .sidebar{
        display:none;
    }

    .main{
        margin-left:0;
    }

    .content{
        padding:18px;
    }

    .pipe-grid{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:650px){

    .pipe-grid{
        grid-template-columns:1fr;
    }

    .topbar{
        padding:0 18px;
    }

    .search-bar input{
        width:100%;
    }
}

</style>
</head>
<body>

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="sb-logo">

        <svg viewBox="0 0 40 40">
            <circle cx="20" cy="20" r="18" fill="none" stroke="#1a3a6b" stroke-width="2.5" stroke-dasharray="95 15"/>
            <polygon points="20,5 30,15 10,15" fill="#2db12b"/>
            <rect x="11" y="15" width="7" height="11" fill="#f07800"/>
            <rect x="22" y="15" width="7" height="11" fill="#f07800"/>
            <rect x="17" y="15" width="6" height="16" fill="#2db12b"/>
        </svg>

        <div>
            <div class="t1">Trans-Phil Hub</div>
            <div class="t2">Administrator</div>
        </div>
    </div>

    <div class="sb-section">Main Menu</div>

    <a href="dashboard.php" class="nav-item">
        <i class="fas fa-th-large"></i> Dashboard
    </a>

    <a href="properties.php" class="nav-item">
        <i class="fas fa-building"></i> Properties
    </a>

    <a href="leads.php" class="nav-item active">
        <i class="fas fa-funnel-dollar"></i> Lead Management

        <?php if($stage_counts['new'] > 0): ?>
            <span class="nav-badge"><?php echo $stage_counts['new']; ?></span>
        <?php endif; ?>
    </a>

    <div class="sb-section">Management</div>

    <a href="users.php" class="nav-item">
        <i class="fas fa-users"></i> User Management
    </a>

    <a href="reports.php" class="nav-item">
        <i class="fas fa-chart-bar"></i> Reports & Analytics
    </a>

    <div class="sb-footer">
        <a href="../logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

</aside>

<div class="main">

    <div class="topbar">

        <div>
            <h1>Lead Management</h1>
            <p><?php echo date('l, F j, Y'); ?></p>
        </div>

        <div class="user-chip">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'],0,1)); ?>
            </div>

            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>

    </div>

    <div class="content">

        <?php if(isset($success)): ?>
            <div class="alert">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- PIPELINE -->

        <div class="pipe-grid">

            <?php
            $pipe_colors = [
                'new'=>'#1a3a6b',
                'contacted'=>'#f07800',
                'viewing'=>'#2db12b',
                'negotiation'=>'#a07000',
                'closed'=>'#218f1f'
            ];

            foreach($stage_counts as $s => $cnt):

                $pct = $total_leads > 0
                    ? round($cnt/$total_leads*100)
                    : 0;

                $active = ($stage_filter == $s)
                    ? 'active-filter'
                    : '';
            ?>

            <a href="?stage=<?php echo $s; ?>" class="pipe-card <?php echo $active; ?>">

                <div class="pipe-num" style="color:<?php echo $pipe_colors[$s]; ?>">
                    <?php echo $cnt; ?>
                </div>

                <div class="pipe-label">
                    <?php echo ucfirst($s); ?>
                </div>

                <div class="pipe-pct" style="color:<?php echo $pipe_colors[$s]; ?>">
                    <?php echo $pct; ?>%
                </div>

            </a>

            <?php endforeach; ?>

        </div>

        <!-- CREATE LEAD -->

        <div class="card">

            <div class="card-head">
                <h2><i class="fas fa-plus-circle"></i>Create New Lead</h2>
            </div>

            <form method="POST">

                <div class="form-grid">

                    <div class="fg">
                        <label>Client *</label>

                        <select name="client_id" required>

                            <option value="">Select client...</option>

                            <?php foreach($clients as $c): ?>

                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['full_name']); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="fg">
                        <label>Property *</label>

                        <select name="property_id" required>

                            <option value="">Select property...</option>

                            <?php foreach($properties as $p): ?>

                                <option value="<?php echo $p['id']; ?>">
                                    <?php echo htmlspecialchars($p['title']); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="fg">
                        <label>Assign Agent</label>

                        <select name="agent_id">

                            <option value="">Unassigned</option>

                            <?php foreach($agents as $a): ?>

                                <option value="<?php echo $a['id']; ?>">
                                    <?php echo htmlspecialchars($a['full_name']); ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="fg">
                        <label>Priority</label>

                        <select name="priority">

                            <option value="high">High</option>
                            <option value="medium" selected>Medium</option>
                            <option value="low">Low</option>

                        </select>
                    </div>

                    <div class="fg" style="grid-column:1/-1;">
                        <label>Notes</label>

                        <textarea
                            name="notes"
                            rows="3"
                            placeholder="Optional lead notes..."
                        ></textarea>
                    </div>

                </div>

                <button type="submit" name="add_lead" class="btn-primary">
                    <i class="fas fa-plus"></i>
                    Create Lead
                </button>

            </form>

        </div>

        <!-- LEADS TABLE -->

        <div class="card">

            <div class="card-head">

                <h2>
                    <i class="fas fa-list-alt"></i>
                    Lead Directory
                </h2>

            </div>

            <!-- SEARCH -->

            <form method="GET" class="search-bar">

                <input
                    type="text"
                    name="search"
                    placeholder="Search client, property or agent..."
                    value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                >

                <?php if($stage_filter !== 'all'): ?>
                    <input type="hidden" name="stage" value="<?php echo $stage_filter; ?>">
                <?php endif; ?>

                <button type="submit" class="btn-primary">
                    <i class="fas fa-search"></i>
                    Search
                </button>

            </form>

            <!-- TABLE -->

            <div class="tbl-wrap">

                <table class="tbl">

                    <thead>

                        <tr>
                            <th>#</th>
                            <th>Client</th>
                            <th>Property</th>
                            <th>Agent</th>
                            <th>Priority</th>
                            <th>Stage</th>
                            <th>Notes</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach($leads as $lead): ?>

                        <tr>

                            <td>
                                #<?php echo $lead['id']; ?>
                            </td>

                            <td>

                                <div style="font-weight:700;">
                                    <?php echo htmlspecialchars($lead['client_name'] ?? '—'); ?>
                                </div>

                                <div style="font-size:11px;color:var(--muted);">
                                    <?php echo htmlspecialchars($lead['client_email'] ?? ''); ?>
                                </div>

                            </td>

                            <td>

                                <div class="property-cell">

                                    <?php
                                    // FIXED IMAGE PATH - Check multiple possible locations
                                    $image_name = $lead['property_image'] ?? 'property1.png';
                                    $image_path = '';

                                    // Check in assets/uploads first
                                    if(file_exists('../assets/uploads/' . $image_name) && $image_name != 'property1.png'){
                                        $image_path = '../assets/uploads/' . $image_name;
                                    }
                                    // Check in assets/images
                                    elseif(file_exists('../assets/images/' . $image_name)){
                                        $image_path = '../assets/images/' . $image_name;
                                    }
                                    // Check for numbered property images
                                    elseif(file_exists('../assets/images/property1.png')){
                                        $image_path = '../assets/images/property1.png';
                                    }
                                    // Fallback to default
                                    else {
                                        $image_path = '../assets/images/property1.png';
                                    }
                                    ?>

                                    <img
                                        src="<?php echo $image_path; ?>"
                                        onerror="this.src='../assets/images/property1.png'"
                                    >

                                    <div>

                                        <div style="font-weight:600;">
                                            <?php echo htmlspecialchars($lead['property_title'] ?? '—'); ?>
                                        </div>

                                        <?php if($lead['property_price']): ?>

                                            <div style="font-size:11px;color:var(--muted);">
                                                ₱ <?php echo number_format($lead['property_price']); ?>
                                            </div>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </td>

                            <td>
                                <?php echo htmlspecialchars($lead['agent_name'] ?? 'Unassigned'); ?>
                            </td>

                            <td>

                                <span class="badge p-<?php echo $lead['priority']; ?>">
                                    <?php echo ucfirst($lead['priority']); ?>
                                </span>

                            </td>

                            <td>

                                <span class="badge b-<?php echo $lead['stage']; ?>">
                                    <?php echo ucfirst($lead['stage']); ?>
                                </span>

                            </td>

                            <td style="max-width:180px;">

                                <div style="
                                    overflow:hidden;
                                    text-overflow:ellipsis;
                                    white-space:nowrap;
                                    color:var(--muted);
                                    font-size:12px;
                                ">
                                    <?php echo htmlspecialchars($lead['notes'] ?: '—'); ?>
                                </div>

                            </td>

                            <td style="font-size:11px;color:var(--muted);">
                                <?php echo date('M d, Y', strtotime($lead['created_at'])); ?>
                            </td>

                            <td style="white-space:nowrap;">

                                <button
                                    class="btn-sm btn-assign"
                                    onclick="openAssign(<?php echo $lead['id']; ?>)"
                                >
                                    <i class="fas fa-user-tag"></i>
                                </button>

                                <button
                                    class="btn-sm btn-stage"
                                    onclick="openStage(
                                        <?php echo $lead['id']; ?>,
                                        '<?php echo $lead['stage']; ?>',
                                        '<?php echo addslashes($lead['notes']); ?>'
                                    )"
                                >
                                    <i class="fas fa-edit"></i>
                                </button>

                                <a
                                    href="?delete=<?php echo $lead['id']; ?>"
                                    class="btn-sm btn-delete"
                                    onclick="return confirm('Delete this lead?')"
                                >
                                    <i class="fas fa-trash"></i>
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    <?php if(empty($leads)): ?>

                        <tr>
                            <td colspan="9" style="text-align:center;padding:40px;color:var(--muted);">
                                No leads found.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <!-- PAGINATION -->

            <?php if($total_pages > 1): ?>

            <div class="pagination">

                <?php for($i = 1; $i <= $total_pages; $i++): ?>

                    <a
                        class="page-btn <?php echo $page == $i ? 'active' : ''; ?>"
                        href="?page=<?php echo $i; ?>&stage=<?php echo $stage_filter; ?>&search=<?php echo urlencode($search); ?>"
                    >
                        <?php echo $i; ?>
                    </a>

                <?php endfor; ?>

            </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<!-- ASSIGN MODAL -->

<div class="modal-bg" id="assignModal">

    <div class="modal-box">

        <button class="modal-close" onclick="closeModal('assignModal')">
            <i class="fas fa-times"></i>
        </button>

        <h3>
            <i class="fas fa-user-tag" style="color:var(--orange);margin-right:8px;"></i>
            Assign Lead
        </h3>

        <form method="POST">

            <input type="hidden" name="lead_id" id="assign_lead_id">

            <div class="fg">

                <label>Select Agent</label>

                <select name="agent_id" required>

                    <option value="">Choose agent...</option>

                    <?php foreach($agents as $a): ?>

                        <option value="<?php echo $a['id']; ?>">
                            <?php echo htmlspecialchars($a['full_name']); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <button type="submit" name="assign_lead" class="btn-primary" style="width:100%;">
                Assign Agent
            </button>

        </form>

    </div>

</div>

<!-- STAGE MODAL -->

<div class="modal-bg" id="stageModal">

    <div class="modal-box">

        <button class="modal-close" onclick="closeModal('stageModal')">
            <i class="fas fa-times"></i>
        </button>

        <h3>
            <i class="fas fa-edit" style="color:var(--green);margin-right:8px;"></i>
            Update Lead Stage
        </h3>

        <form method="POST">

            <input type="hidden" name="lead_id" id="stage_lead_id">

            <div class="fg" style="margin-bottom:16px;">

                <label>Stage</label>

                <select name="stage" id="stage_select">

                    <option value="new">New</option>
                    <option value="contacted">Contacted</option>
                    <option value="viewing">Viewing</option>
                    <option value="negotiation">Negotiation</option>
                    <option value="closed">Closed</option>

                </select>

            </div>

            <div class="fg">

                <label>Notes</label>

                <textarea
                    name="notes"
                    rows="4"
                    id="stage_notes"
                ></textarea>

            </div>

            <button type="submit" name="update_stage" class="btn-primary" style="width:100%;">
                Update Stage
            </button>

        </form>

    </div>

</div>

<script>

function openAssign(id){

    document.getElementById('assign_lead_id').value = id;

    document.getElementById('assignModal')
        .classList.add('open');
}

function openStage(id, stage, notes){

    document.getElementById('stage_lead_id').value = id;

    document.getElementById('stage_select').value = stage;

    document.getElementById('stage_notes').value = notes;

    document.getElementById('stageModal')
        .classList.add('open');
}

function closeModal(id){

    document.getElementById(id)
        .classList.remove('open');
}

document.querySelectorAll('.modal-bg').forEach(modal => {

    modal.addEventListener('click', function(e){

        if(e.target === this){

            this.classList.remove('open');
        }
    });
});

</script>

</body>
</html>