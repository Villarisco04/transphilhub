<?php
require_once '../includes/db.php';
session_start();

// Check if user is logged in and is client
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client'){
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get client's inquiries/leads
$inquiries = $pdo->prepare("
    SELECT l.*, p.title as property_title, p.location, p.price, p.type, p.image,
           a.full_name as agent_name
    FROM leads l
    JOIN properties p ON l.property_id = p.id
    LEFT JOIN users a ON l.agent_id = a.id
    WHERE l.client_id = ?
    ORDER BY l.created_at DESC
");
$inquiries->execute([$user_id]);
$inquiries = $inquiries->fetchAll();

// Get appointment count
$appointments = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE client_id = ?");
$appointments->execute([$user_id]);
$appointment_count = $appointments->fetchColumn();

// Get active inquiry count (not closed)
$active_inquiries = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE client_id = ? AND stage != 'closed'");
$active_inquiries->execute([$user_id]);
$active_count = $active_inquiries->fetchColumn();

// Get user details
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// Get favorite properties count
$fav_count = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE client_id = ?");
$fav_count->execute([$user_id]);
$favorite_count = $fav_count->fetchColumn();

// Get favorite properties list
$favorites = $pdo->prepare("
    SELECT p.*, f.created_at as favorited_at
    FROM favorites f
    JOIN properties p ON f.property_id = p.id
    WHERE f.client_id = ?
    ORDER BY f.created_at DESC
    LIMIT 4
");
$favorites->execute([$user_id]);
$favorites = $favorites->fetchAll();

// Get agents client has completed transactions with
$reviewable_agents = $pdo->prepare("
    SELECT DISTINCT 
        a.id, a.full_name,
        p.title as property_title, p.price,
        l.created_at as transaction_date
    FROM leads l
    JOIN users a ON l.agent_id = a.id
    JOIN properties p ON l.property_id = p.id
    WHERE l.client_id = ? 
    AND l.stage = 'closed'
    AND a.id NOT IN (SELECT agent_id FROM reviews WHERE client_id = ?)
    ORDER BY l.created_at DESC
");
$reviewable_agents->execute([$user_id, $user_id]);
$reviewable_agents = $reviewable_agents->fetchAll();

// Get already reviewed agents
$reviewed_agents = $pdo->prepare("
    SELECT a.id, a.full_name, r.rating, r.comment
    FROM reviews r
    JOIN users a ON r.agent_id = a.id
    WHERE r.client_id = ?
    ORDER BY r.created_at DESC
");
$reviewed_agents->execute([$user_id]);
$reviewed_agents = $reviewed_agents->fetchAll();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Dashboard — Trans-Phil House Hub</title>
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

/* Stats Row */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:24px;}
.stat-card{background:var(--card);border-radius:var(--radius);padding:20px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow);border:1px solid var(--border);transition:.2s;}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 6px 24px rgba(26,58,107,.12);}
.stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon i{font-size:22px;}
.stat-num{font-size:28px;font-weight:700;color:var(--navy);line-height:1;}
.stat-label{font-size:12px;color:var(--muted);margin-top:3px;}

/* Welcome Banner */
.welcome-banner{background:linear-gradient(135deg, var(--navy2), var(--navy));border-radius:var(--radius);padding:28px 32px;margin-bottom:24px;border:1px solid rgba(255,255,255,.1);}
.welcome-banner h2{font-size:22px;color:#fff;margin-bottom:6px;}
.welcome-banner p{color:rgba(255,255,255,.7);font-size:13px;}
.btn-browse{display:inline-block;margin-top:14px;background:var(--orange);color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px;transition:.2s;}
.btn-browse:hover{background:var(--orange2);transform:translateY(-2px);}

/* Profile Card */
.card{background:var(--card);border-radius:var(--radius);padding:22px 24px;box-shadow:var(--shadow);border:1px solid var(--border);margin-bottom:24px;}
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;}
.card-head h2{font-size:15px;font-weight:700;color:var(--navy);}
.card-head h2 i{margin-right:8px;color:var(--orange);}
.profile-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;}
.profile-field{display:flex;flex-direction:column;gap:4px;}
.profile-field label{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
.profile-field .value{font-size:14px;font-weight:500;color:var(--text);}

/* Favorites Section */
.favorites-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:8px;}
.fav-card{display:flex;align-items:center;gap:12px;padding:12px;background:var(--bg);border-radius:12px;transition:.2s;text-decoration:none;color:inherit;}
.fav-card:hover{background:var(--border);transform:translateX(5px);}
.fav-img{width:70px;height:60px;border-radius:10px;object-fit:cover;}
.fav-info{flex:1;}
.fav-title{font-size:14px;font-weight:600;color:var(--text);margin-bottom:3px;}
.fav-price{font-size:13px;font-weight:700;color:var(--orange);}
.fav-location{font-size:11px;color:var(--muted);}
.fav-remove{background:none;border:none;color:var(--muted);cursor:pointer;padding:5px;transition:.2s;}
.fav-remove:hover{color:#c0392b;transform:scale(1.1);}
.empty-favs{text-align:center;padding:30px;color:var(--muted);}
.empty-favs i{font-size:40px;margin-bottom:10px;opacity:0.5;}
.empty-favs a{color:var(--orange);text-decoration:none;font-weight:600;}

/* Review Button */
.btn-rate {
    background: var(--green);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
    transition: 0.2s;
    display: inline-block;
}
.btn-rate:hover { background: var(--green2); transform: translateY(-2px); }
.review-list { margin-top: 10px; }
.review-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    padding: 15px;
    background: var(--bg);
    border-radius: 12px;
    margin-bottom: 12px;
}
.review-info .agent-name { font-weight: 700; }
.review-property { font-size: 12px; color: var(--muted); margin-top: 4px; }
.stars-small { display: inline-flex; gap: 2px; }
.stars-small i { font-size: 11px; }
.previous-review { padding: 10px 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Table */
.tbl-wrap{overflow-x:auto;}
.tbl{width:100%;border-collapse:collapse;min-width:700px;}
.tbl th{padding:10px 12px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);background:#f8f7fc;border-bottom:1px solid var(--border);text-align:left;}
.tbl td{padding:11px 12px;font-size:13px;border-bottom:1px solid var(--border);vertical-align:middle;}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:hover td{background:#fafaf8;}
.property-img{width:55px;height:45px;object-fit:cover;border-radius:8px;background:#f3f4f6;}

/* Badges */
.badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
.b-new{background:#e0e8f7;color:var(--navy);}
.b-contacted{background:#fff0df;color:var(--orange2);}
.b-viewing{background:#e6f7e6;color:var(--green2);}
.b-negotiation{background:#fef9e0;color:#a07000;}
.b-closed{background:#e8f7e8;color:var(--green2);}
.price{font-weight:700;color:var(--navy);}
.no-data{text-align:center;padding:40px;color:var(--muted);}
.action-link{color:var(--orange);text-decoration:none;font-size:12px;font-weight:600;}
.action-link:hover{text-decoration:underline;}

/* Toast Notification */
.toast-notification{position:fixed;bottom:30px;right:30px;background:var(--navy);color:white;padding:12px 24px;border-radius:40px;box-shadow:0 4px 15px rgba(0,0,0,0.2);z-index:1000;display:none;align-items:center;gap:10px;animation:slideIn 0.3s ease;}
.toast-notification.show{display:flex;}
.toast-notification i{font-size:18px;}
@keyframes slideIn{from{transform:translateX(100px);opacity:0;}to{transform:translateX(0);opacity:1;}}

/* Mobile */
.mob-toggle{display:none;position:fixed;bottom:20px;right:20px;width:48px;height:48px;background:var(--navy);border-radius:50%;align-items:center;justify-content:center;z-index:400;cursor:pointer;border:none;color:#fff;font-size:18px;box-shadow:0 4px 16px rgba(0,0,0,.2);}

@media(max-width:1100px){.stats-row{grid-template-columns:repeat(2,1fr);}}
@media(max-width:900px){}
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);width:260px;}
    .sidebar.show{transform:translateX(0);}
    .main{margin-left:0!important;}
    .mob-toggle{display:flex;}
    .stats-row{grid-template-columns:1fr;}
    .profile-grid{grid-template-columns:1fr;}
    .favorites-grid{grid-template-columns:1fr;}
    .review-item { flex-direction: column; text-align: center; }
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
        <div class="sb-logo-text"><div class="t1">Trans-Phil Hub</div><div class="t2">Client Portal</div></div>
    </div>
    <div class="sb-section">Main Menu</div>
    <a href="dashboard.php" class="nav-item active" data-tip="Dashboard"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="../properties.php" class="nav-item" data-tip="Properties"><i class="fas fa-building"></i><span>Browse Properties</span></a>
    <div class="sb-footer">
        <a href="../logout.php" class="nav-item" data-tip="Logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>
</aside>

<button class="mob-toggle" id="mobToggle"><i class="fas fa-bars"></i></button>

<!-- MAIN CONTENT -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h1>Client Dashboard</h1>
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
            <h2>Welcome back, <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>! 👋</h2>
            <p>Find your dream property today. Browse our latest listings and schedule a viewing.</p>
            <a href="../properties.php" class="btn-browse"><i class="fas fa-search"></i> Browse Properties</a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e8f5e9;"><i class="fas fa-home" style="color:var(--green);"></i></div>
                <div><div class="stat-num"><?php echo count($inquiries); ?></div><div class="stat-label">Total Inquiries</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff3e0;"><i class="fas fa-clock" style="color:var(--orange);"></i></div>
                <div><div class="stat-num"><?php echo $active_count; ?></div><div class="stat-label">Active Inquiries</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e3f2fd;"><i class="fas fa-calendar" style="color:var(--navy);"></i></div>
                <div><div class="stat-num"><?php echo $appointment_count; ?></div><div class="stat-label">Appointments</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fce4ec;"><i class="fas fa-heart" style="color:var(--orange);"></i></div>
                <div><div class="stat-num"><?php echo $favorite_count; ?></div><div class="stat-label">Saved Properties</div></div>
            </div>
        </div>

        <!-- Profile Card -->
        <div class="card">
            <div class="card-head">
                <h2><i class="fas fa-user-circle"></i> My Profile</h2>
                <a href="#" class="action-link">Edit Profile →</a>
            </div>
            <div class="profile-grid">
                <div class="profile-field"><label>Full Name</label><div class="value"><?php echo htmlspecialchars($user['full_name']); ?></div></div>
                <div class="profile-field"><label>Email Address</label><div class="value"><?php echo htmlspecialchars($user['email']); ?></div></div>
                <div class="profile-field"><label>Phone Number</label><div class="value"><?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></div></div>
                <div class="profile-field"><label>Member Since</label><div class="value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div></div>
            </div>
        </div>

        <!-- RATE YOUR AGENT SECTION -->
        <div class="card">
            <div class="card-head">
                <h2><i class="fas fa-star" style="color:#fbbf24;"></i> Rate Your Agent</h2>
            </div>
            
            <!-- Show review button for completed transactions -->
            <?php if(count($reviewable_agents) > 0): ?>
                <div style="margin-bottom: 15px;">
                    <p style="color: var(--green); font-size: 13px; margin-bottom: 15px;">
                        <i class="fas fa-check-circle"></i> You have completed transactions! Rate your agent below:
                    </p>
                    <?php foreach($reviewable_agents as $agent): ?>
                        <div class="review-item">
                            <div class="review-info">
                                <div class="agent-name"><?php echo htmlspecialchars($agent['full_name']); ?></div>
                                <div class="review-property">
                                    <i class="fas fa-home"></i> <?php echo htmlspecialchars($agent['property_title']); ?>
                                    (₱ <?php echo number_format($agent['price'], 0); ?>)
                                </div>
                            </div>
                            <a href="submit_review.php?agent_id=<?php echo $agent['id']; ?>" class="btn-rate">
                                <i class="fas fa-star"></i> Rate This Agent
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- DEMO BUTTON FOR TESTING - Remove this after testing -->
                <div style="margin-bottom: 15px; padding: 15px; background: #fef9e0; border-radius: 12px;">
                    <p style="color: #92400e; font-size: 13px; margin-bottom: 10px;">
                        <i class="fas fa-info-circle"></i></strong> No completed transactions yet. 
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- Show already submitted reviews -->
            <?php if(count($reviewed_agents) > 0): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                    <h3 style="font-size: 13px; margin-bottom: 12px; color: var(--navy);">
                        <i class="fas fa-history"></i> Your Previous Reviews
                    </h3>
                    <?php foreach($reviewed_agents as $review): ?>
                        <div class="previous-review">
                            <div class="stars-small">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star" style="color: <?php echo $i <= $review['rating'] ? '#fbbf24' : '#e5e7eb'; ?>;"></i>
                                <?php endfor; ?>
                            </div>
                            <span style="font-weight: 500;"><?php echo htmlspecialchars($review['full_name']); ?></span>
                            <span style="color: var(--muted); font-size: 11px;">"<?php echo htmlspecialchars(substr($review['comment'], 0, 50)); ?>..."</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- FAVORITES / WISHLIST SECTION -->
        <div class="card">
            <div class="card-head">
                <h2><i class="fas fa-heart" style="color:#f07800;"></i> My Wishlist <span style="font-size:12px;color:var(--muted);">(<?php echo $favorite_count; ?> saved)</span></h2>
                <a href="../properties.php" class="action-link"><i class="fas fa-plus"></i> Add More</a>
            </div>
            
            <?php if(count($favorites) > 0): ?>
                <div class="favorites-grid">
                    <?php foreach($favorites as $fav): ?>
                        <div class="fav-card" data-fav-id="<?php echo $fav['id']; ?>">
                            <img src="../assets/images/<?php echo $fav['image'] ?: 'property1.png'; ?>" class="fav-img" onerror="this.src='../assets/images/property1.png'">
                            <div class="fav-info">
                                <div class="fav-title"><?php echo htmlspecialchars($fav['title']); ?></div>
                                <div class="fav-price">₱ <?php echo number_format($fav['price'], 0); ?></div>
                                <div class="fav-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($fav['location']); ?></div>
                            </div>
                            <button class="fav-remove" onclick="removeFromFavorites(<?php echo $fav['id']; ?>, this)" title="Remove from wishlist">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-favs">
                    <i class="fas fa-heart"></i>
                    <p>Your wishlist is empty</p>
                    <a href="../properties.php">Browse Properties and click the ❤️ to save</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- My Inquiries Table -->
        <div class="card">
            <div class="card-head">
                <h2><i class="fas fa-list-alt"></i> My Property Inquiries</h2>
                <a href="../properties.php" class="action-link"><i class="fas fa-plus"></i> New Inquiry</a>
            </div>
            <div class="tbl-wrap">
                <?php if(count($inquiries) > 0): ?>
                <table class="tbl">
                    <thead>
                        <tr><th>Property</th><th>Location</th><th>Price</th><th>Status</th><th>Agent</th><th>Date</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($inquiries as $inquiry): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <img src="../assets/images/<?php echo $inquiry['image'] ?: 'property1.png'; ?>" class="property-img" onerror="this.src='../assets/images/property1.png'">
                                    <span style="font-weight:500;"><?php echo htmlspecialchars($inquiry['property_title']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($inquiry['location']); ?></td>
                            <td class="price">₱ <?php echo number_format($inquiry['price'], 0); ?></td>
                            <td><span class="badge b-<?php echo $inquiry['stage'] ?: 'new'; ?>"><?php echo ucfirst($inquiry['stage'] ?: 'New'); ?></span></td>
                            <td><?php echo $inquiry['agent_name'] ?: '<span style="color:var(--muted);">Unassigned</span>'; ?></td>
                            <td><span style="font-size:11px;color:var(--muted);"><?php echo date('M d, Y', strtotime($inquiry['created_at'])); ?></span></td>
                            <td><a href="../inquiry.php?property_id=<?php echo $inquiry['property_id']; ?>" class="action-link"><i class="fas fa-comment"></i> Message</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox" style="font-size:48px;margin-bottom:15px;color:var(--border);"></i>
                    <p>You haven't made any property inquiries yet.</p>
                    <a href="../properties.php" style="display:inline-block;margin-top:15px;background:var(--orange);color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;">Browse Properties Now</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast-notification">
    <i class="fas fa-heart-broken"></i>
    <span id="toastMessage">Removed from wishlist</span>
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

// Remove from favorites function
function removeFromFavorites(propertyId, button) {
    fetch('../toggle_favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `property_id=${propertyId}&action=remove`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            const favCard = button.closest('.fav-card');
            favCard.style.opacity = '0';
            setTimeout(() => {
                favCard.remove();
                showToast('Removed from wishlist', 'removed');
                setTimeout(() => location.reload(), 800);
            }, 300);
        } else {
            showToast('Error removing item', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Something went wrong', 'error');
    });
}

function showToast(message, type) {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toastMessage');
    const toastIcon = toast.querySelector('i');
    
    toastMessage.textContent = message;
    toast.className = 'toast-notification show ' + type;
    
    if(type === 'removed') {
        toastIcon.className = 'fas fa-heart-broken';
        toast.style.background = '#c0392b';
    } else {
        toastIcon.className = 'fas fa-exclamation-circle';
        toast.style.background = '#dc2626';
    }
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 2500);
}
</script>

</body>
</html>