<?php
require_once '../includes/db.php';
require_once '../includes/notifications.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

// Handle approve review
if(isset($_POST['approve_review'])){
    $review_id = (int)$_POST['review_id'];
    
    $stmt = $pdo->prepare("UPDATE reviews SET is_approved = 1, approved_at = NOW() WHERE id = ?");
    $stmt->execute([$review_id]);
    
    // Get review details for notification
    $stmt = $pdo->prepare("
        SELECT r.*, c.full_name as client_name, c.email as client_email,
               a.full_name as agent_name, a.id as agent_id
        FROM reviews r
        JOIN users c ON r.client_id = c.id
        JOIN users a ON r.agent_id = a.id
        WHERE r.id = ?
    ");
    $stmt->execute([$review_id]);
    $review = $stmt->fetch();
    
    // Update agent's average rating
    $stmt = $pdo->prepare("
        UPDATE users u
        SET avg_rating = (
            SELECT AVG(rating) FROM reviews WHERE agent_id = u.id AND is_approved = 1
        ),
        total_reviews = (
            SELECT COUNT(*) FROM reviews WHERE agent_id = u.id AND is_approved = 1
        )
        WHERE u.id = ?
    ");
    $stmt->execute([$review['agent_id']]);
    
    // Send notification to client
    add_notification($review['client_id'], "Your review for agent {$review['agent_name']} has been approved and published!", "client/dashboard.php?reviews=1");
    
    $success = "Review approved successfully!";
}

// Handle reject review
if(isset($_POST['reject_review'])){
    $review_id = (int)$_POST['review_id'];
    
    // Get client ID for notification
    $stmt = $pdo->prepare("SELECT client_id FROM reviews WHERE id = ?");
    $stmt->execute([$review_id]);
    $client_id = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->execute([$review_id]);
    
    // Notify client
    add_notification($client_id, "Your review was not approved. Please check our guidelines and try again.", "client/submit_review.php");
    
    $success = "Review rejected and removed.";
}

// Handle feature review (for homepage)
if(isset($_POST['feature_review'])){
    $review_id = (int)$_POST['review_id'];
    
    // First, remove featured status from all reviews
    $pdo->prepare("UPDATE reviews SET is_featured = 0 WHERE is_featured = 1")->execute();
    
    // Then feature the selected review
    $stmt = $pdo->prepare("UPDATE reviews SET is_featured = 1 WHERE id = ?");
    $stmt->execute([$review_id]);
    
    $success = "Review featured on homepage successfully!";
}

// Handle remove featured
if(isset($_POST['remove_featured'])){
    $stmt = $pdo->prepare("UPDATE reviews SET is_featured = 0 WHERE is_featured = 1");
    $stmt->execute();
    
    $success = "Featured review removed from homepage.";
}

// Get reviews with additional info
$reviews = $pdo->query("
    SELECT r.*, 
           c.full_name as client_name, c.email as client_email,
           a.full_name as agent_name,
           p.title as property_title
    FROM reviews r
    JOIN users c ON r.client_id = c.id
    JOIN users a ON r.agent_id = a.id
    LEFT JOIN properties p ON r.property_id = p.id
    ORDER BY 
        CASE WHEN r.is_featured = 1 THEN 0 ELSE 1 END,
        r.is_approved DESC,
        r.created_at DESC
")->fetchAll();

$pending_count = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_approved = 0")->fetchColumn();
$approved_count = $pdo->query("SELECT COUNT(*) FROM reviews WHERE is_approved = 1")->fetchColumn();
$avg_rating = $pdo->query("SELECT AVG(rating) FROM reviews WHERE is_approved = 1")->fetchColumn();
$featured_review = $pdo->query("SELECT r.id, r.comment, r.rating, c.full_name as client_name 
                                FROM reviews r 
                                JOIN users c ON r.client_id = c.id 
                                WHERE r.is_featured = 1 
                                LIMIT 1")->fetch();

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Review Management — Trans-Phil House Hub</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root {
    --navy: #1a3a6b;
    --navy2: #0f2340;
    --green: #2db12b;
    --green2: #218f1f;
    --orange: #f07800;
    --orange2: #c96400;
    --white: #ffffff;
    --bg: #f0eff5;
    --card: #ffffff;
    --border: #e4e2ee;
    --text: #1e1c2e;
    --muted: #6b6880;
    --radius: 14px;
    --shadow: 0 2px 12px rgba(26,58,107,.07);
}
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
.stat-card{background:var(--card);border-radius:var(--radius);padding:20px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow);border:1px solid var(--border);}
.stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon i{font-size:22px;}
.stat-num{font-size:28px;font-weight:700;color:var(--navy);line-height:1;}
.stat-label{font-size:12px;color:var(--muted);margin-top:3px;}

.card{background:var(--card);border-radius:var(--radius);padding:22px 24px;box-shadow:var(--shadow);border:1px solid var(--border);margin-bottom:20px;}
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
.card-head h2{font-size:15px;font-weight:700;color:var(--navy);}
.card-head h2 i{margin-right:8px;color:var(--orange);}

/* Featured Alert */
.featured-alert{
    background: linear-gradient(135deg, #fef9e0, #fef3c7);
    border-left: 4px solid #fbbf24;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.featured-alert-content{
    display: flex;
    align-items: center;
    gap: 12px;
}
.featured-alert i{font-size: 24px; color: #fbbf24;}
.featured-alert strong{color: #92400e;}

/* Table */
.tbl-wrap{overflow-x:auto;}
.tbl{width:100%;border-collapse:collapse;min-width:900px;}
.tbl th{padding:12px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);background:#f8f7fc;border-bottom:1px solid var(--border);text-align:left;}
.tbl td{padding:12px;font-size:13px;border-bottom:1px solid var(--border);vertical-align:middle;}
.tbl tr.featured-row{background: #fef9e0;}
.tbl tr.featured-row td{border-bottom-color: #fde68a;}

.stars{display:inline-flex;gap:2px;}
.stars i{font-size:12px;color:#fbbf24;}
.stars i.empty{color:#e5e7eb;}

.badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
.badge-pending{background:#fef3c7;color:#92400e;}
.badge-approved{background:#d1fae5;color:#065f46;}
.badge-featured{background:#fef9e0;color:#a16207;}

.btn-sm{padding:6px 12px;border-radius:6px;border:none;cursor:pointer;font-size:11px;font-weight:600;margin:0 3px;transition:.2s;}
.btn-approve{background:#d1fae5;color:#065f46;}
.btn-approve:hover{background:#a7f3d0;}
.btn-reject{background:#fee2e2;color:#991b1b;}
.btn-reject:hover{background:#fecaca;}
.btn-feature{background:#fef9e0;color:#92400e;}
.btn-feature:hover{background:#fde68a;}
.btn-featured{background:#fbbf24;color:#78350f;cursor:default;}

.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;}
.alert-success{background:#e6f7e6;color:var(--green2);border-left:4px solid var(--green);}

@media(max-width:900px){
    .sidebar{display:none;}
    .main{margin-left:0;}
    .stats-row{grid-template-columns:repeat(2,1fr);}
}
</style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sb-toggle" id="sbToggle"><i class="fas fa-chevron-left"></i></div>
    <div class="sb-logo">
        <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="18" fill="none" stroke="#1a3a6b" stroke-width="2.5" stroke-dasharray="95 15"/><polygon points="20,5 30,15 10,15" fill="#2db12b"/><rect x="11" y="15" width="7" height="11" fill="#f07800"/><rect x="22" y="15" width="7" height="11" fill="#f07800"/><rect x="17" y="15" width="6" height="16" fill="#2db12b"/></svg>
        <div class="sb-logo-text"><div class="t1">Trans-Phil Hub</div><div class="t2">Administrator</div></div>
    </div>
    <div class="sb-section">Main Menu</div>
    <a href="dashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="properties.php" class="nav-item"><i class="fas fa-building"></i><span>Properties</span></a>
    <a href="leads.php" class="nav-item"><i class="fas fa-funnel-dollar"></i><span>Lead Management</span></a>
    <div class="sb-section">Management</div>
    <a href="users.php" class="nav-item"><i class="fas fa-users"></i><span>User Management</span></a>
    <a href="reviews.php" class="nav-item active"><i class="fas fa-star"></i><span>Reviews & Ratings</span></a>
    <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i><span>Reports & Analytics</span></a>
    <div class="sb-footer">
        <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h1>Reviews & Ratings</h1>
            <p><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="user-chip">
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'],0,1)); ?></div>
            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
    </div>

    <div class="content">
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef9e0;"><i class="fas fa-clock" style="color:#92400e;"></i></div>
                <div><div class="stat-num"><?php echo $pending_count; ?></div><div class="stat-label">Pending Approval</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#d1fae5;"><i class="fas fa-check-circle" style="color:#065f46;"></i></div>
                <div><div class="stat-num"><?php echo $approved_count; ?></div><div class="stat-label">Approved Reviews</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef9e0;"><i class="fas fa-star" style="color:#fbbf24;"></i></div>
                <div><div class="stat-num"><?php echo number_format($avg_rating, 1); ?></div><div class="stat-label">Average Rating</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef9e0;"><i class="fas fa-star" style="color:#fbbf24;"></i></div>
                <div><div class="stat-num"><?php echo $featured_review ? 1 : 0; ?></div><div class="stat-label">Featured on Homepage</div></div>
            </div>
        </div>

        <!-- Featured Review Alert -->
        <?php if($featured_review): ?>
        <div class="featured-alert">
            <div class="featured-alert-content">
                <i class="fas fa-star"></i>
                <div>
                    <strong>Featured Review on Homepage</strong>
                    <p style="font-size:12px; margin-top:2px;">"<?php echo htmlspecialchars(substr($featured_review['comment'], 0, 80)); ?>..." - <?php echo htmlspecialchars($featured_review['client_name']); ?> (<?php echo $featured_review['rating']; ?>★)</p>
                </div>
            </div>
            <form method="POST">
                <button type="submit" name="remove_featured" class="btn-sm btn-reject" onclick="return confirm('Remove this review from homepage?')">
                    <i class="fas fa-times"></i> Remove from Homepage
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Reviews Table -->
        <div class="card">
            <div class="card-head">
                <h2><i class="fas fa-star"></i> All Client Reviews</h2>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Agent</th>
                            <th>Rating</th>
                            <th>Review</th>
                            <th>Property</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reviews as $review): ?>
                            <tr class="<?php echo ($review['is_featured'] ?? 0) ? 'featured-row' : ''; ?>">
                                <td>
                                    <div><strong><?php echo htmlspecialchars($review['client_name']); ?></strong></div>
                                    <div style="font-size:11px;color:var(--muted);"><?php echo htmlspecialchars($review['client_email']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($review['agent_name']); ?></td>
                                <td>
                                    <div class="stars">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? '' : 'empty'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td style="max-width:250px;"><?php echo nl2br(htmlspecialchars(substr($review['comment'], 0, 100))); ?>...</td>
                                <td><?php echo htmlspecialchars($review['property_title'] ?? '—'); ?></td>
                                <td style="font-size:11px;"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
                                <td>
                                    <?php if($review['is_featured'] ?? 0): ?>
                                        <span class="badge badge-featured"><i class="fas fa-star"></i> Featured</span>
                                    <?php else: ?>
                                        <span class="badge badge-<?php echo $review['is_approved'] ? 'approved' : 'pending'; ?>">
                                            <?php echo $review['is_approved'] ? 'Approved' : 'Pending'; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap;">
                                    <?php if(!$review['is_approved'] && !($review['is_featured'] ?? 0)): ?>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                            <button type="submit" name="approve_review" class="btn-sm btn-approve" onclick="return confirm('Approve this review?')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                            <button type="submit" name="reject_review" class="btn-sm btn-reject" onclick="return confirm('Reject and delete this review?')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if($review['is_approved'] && !($review['is_featured'] ?? 0)): ?>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                            <button type="submit" name="feature_review" class="btn-sm btn-feature" onclick="return confirm('Feature this review on the homepage?')">
                                                <i class="fas fa-star"></i> Feature on Homepage
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if($review['is_featured'] ?? 0): ?>
                                        <span class="btn-sm btn-featured">
                                            <i class="fas fa-star"></i> Currently Featured
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($reviews)): ?>
                            <tr><td colspan="8" style="text-align:center;padding:40px;">No reviews yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const sidebar = document.getElementById('sidebar');
const sbToggle = document.getElementById('sbToggle');

sbToggle.addEventListener('click', e => {
    e.stopPropagation();
    sidebar.classList.toggle('collapsed');
    localStorage.setItem('sb', sidebar.classList.contains('collapsed') ? '1' : '0');
});

if(window.innerWidth > 768 && localStorage.getItem('sb') === '1'){
    sidebar.classList.add('collapsed');
}
</script>

</body>
</html>