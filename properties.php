<?php
require_once 'includes/db.php';
session_start();

// Get filter parameters
$type_filter = $_GET['type'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query with filters
$where = "WHERE 1=1";
$params = [];

if($type_filter !== 'all'){
    $where .= " AND type = ?";
    $params[] = $type_filter;
}
if(!empty($search)){
    $where .= " AND (title LIKE ? OR location LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query = "SELECT * FROM properties $where ORDER BY is_featured DESC, created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$properties = $stmt->fetchAll();

// If client is logged in, get their favorites
$favorites = [];
if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'client'){
    $fav_stmt = $pdo->prepare("SELECT property_id FROM favorites WHERE client_id = ?");
    $fav_stmt->execute([$_SESSION['user_id']]);
    $favorites = $fav_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get statistics
$total_properties = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$sale_count = $pdo->query("SELECT COUNT(*) FROM properties WHERE type='sale'")->fetchColumn();
$rent_count = $pdo->query("SELECT COUNT(*) FROM properties WHERE type='rent'")->fetchColumn();
$project_count = $pdo->query("SELECT COUNT(*) FROM properties WHERE type='project'")->fetchColumn();
?>

<?php include 'includes/header.php'; ?>

<style>
:root{--navy:#1a3a6b;--navy2:#0f2340;--green:#2db12b;--green2:#218f1f;--orange:#f07800;--orange2:#c96400;--white:#ffffff;--bg:#f0eff5;--card:#ffffff;--border:#e4e2ee;--text:#1e1c2e;--muted:#6b6880;--radius:14px;--shadow:0 2px 12px rgba(26,58,107,.07);}

/* Page Header */
.page-header{position:relative;padding:80px 24px 60px;text-align:center;background:linear-gradient(135deg, var(--navy2), var(--navy));overflow:hidden;}
.page-header::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background-image:url('assets/images/hero-house.jpg');background-size:cover;background-position:center;opacity:0.15;z-index:0;}
.page-header h1,.page-header p{position:relative;z-index:1;}
.page-header h1{color:white;font-size:48px;margin-bottom:15px;font-weight:700;}
.page-header p{color:rgba(255,255,255,0.85);max-width:600px;margin:0 auto;font-size:16px;}

/* Stats Bar */
.stats-bar{display:flex;justify-content:center;gap:24px;margin-top:24px;flex-wrap:wrap;}
.stat-item{background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);padding:12px 24px;border-radius:40px;text-align:center;}
.stat-num{font-size:24px;font-weight:700;color:#fff;}
.stat-label{font-size:11px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:1px;}

/* Filter Bar */
.filter-bar{background:white;border-radius:60px;padding:8px;margin:-30px auto 0;max-width:800px;position:relative;z-index:10;box-shadow:0 4px 20px rgba(0,0,0,0.1);display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:8px;}
.filter-group{display:flex;align-items:center;gap:4px;background:var(--bg);border-radius:40px;padding:4px;}
.filter-btn{background:transparent;border:none;padding:10px 24px;border-radius:40px;font-size:14px;font-weight:500;cursor:pointer;transition:.2s;color:var(--text);}
.filter-btn.active{background:var(--orange);color:white;}
.filter-search{flex:1;min-width:200px;display:flex;align-items:center;background:var(--bg);border-radius:40px;padding:4px 16px;gap:8px;}
.filter-search i{color:var(--muted);}
.filter-search input{flex:1;border:none;background:transparent;padding:10px 0;font-size:14px;outline:none;}
.filter-search button{background:var(--navy);border:none;color:white;padding:8px 20px;border-radius:30px;cursor:pointer;font-size:13px;font-weight:500;}

/* Properties Container */
.properties-container{max-width:1200px;margin:0 auto;padding:50px 24px;}
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:15px;}
.section-header h2{font-size:22px;color:var(--navy);font-weight:700;}
.section-header h2 i{color:var(--orange);margin-right:8px;}
.result-count{color:var(--muted);font-size:13px;}

/* Cards Grid */
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:28px;}
.property-card{background:white;border-radius:20px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,0.08);transition:transform 0.3s,box-shadow 0.3s;border:1px solid var(--border);position:relative;}
.property-card:hover{transform:translateY(-6px);box-shadow:0 15px 35px rgba(0,0,0,0.12);}
.card-img{position:relative;height:240px;overflow:hidden;background:linear-gradient(135deg, var(--navy), var(--navy2));}
.card-img img{width:100%;height:100%;object-fit:cover;transition:transform 0.4s;}
.property-card:hover .card-img img{transform:scale(1.05);}
.card-badge{position:absolute;top:14px;left:14px;padding:5px 14px;border-radius:25px;font-size:11px;font-weight:700;text-transform:uppercase;z-index:2;}
.badge-sale{background:var(--green);color:white;}
.badge-rent{background:var(--orange);color:white;}
.badge-project{background:var(--navy);color:white;}
.card-feat{position:absolute;top:14px;right:14px;background:#ffd700;color:var(--navy);padding:5px 12px;border-radius:25px;font-size:10px;font-weight:700;z-index:2;}

/* Favorite Button */
.fav-btn{position:absolute;bottom:14px;right:14px;width:38px;height:38px;border-radius:50%;background:white;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.15);transition:.2s;z-index:5;}
.fav-btn i{font-size:18px;color:var(--muted);transition:.2s;}
.fav-btn:hover{transform:scale(1.1);background:var(--orange);}
.fav-btn:hover i{color:white;}
.fav-btn.active{background:var(--orange);}
.fav-btn.active i{color:white;}

.card-body{padding:20px;}
.card-price{font-size:26px;font-weight:800;color:var(--navy);margin-bottom:8px;}
.card-price span{font-size:13px;font-weight:400;color:var(--muted);}
.card-title{font-size:18px;font-weight:600;color:var(--text);margin-bottom:6px;}
.card-loc{font-size:13px;color:var(--muted);margin-bottom:16px;display:flex;align-items:center;gap:5px;}
.card-specs{display:flex;gap:15px;margin-bottom:18px;padding-bottom:15px;border-bottom:1px solid var(--border);}
.spec{font-size:12px;color:var(--muted);display:flex;align-items:center;gap:5px;}
.spec i{color:var(--orange);}
.property-btn{display:block;background:var(--orange);color:white;text-align:center;padding:12px;border-radius:12px;text-decoration:none;font-weight:600;font-size:14px;transition:.2s;}
.property-btn:hover{background:var(--orange2);}
.property-btn-outline{background:transparent;border:2px solid var(--orange);color:var(--orange);}
.property-btn-outline:hover{background:var(--orange);color:white;}

/* Toast Notification */
.toast-notification{position:fixed;bottom:30px;right:30px;background:var(--navy);color:white;padding:12px 24px;border-radius:40px;box-shadow:0 4px 15px rgba(0,0,0,0.2);z-index:1000;display:none;align-items:center;gap:10px;animation:slideIn 0.3s ease;}
.toast-notification.show{display:flex;}
.toast-notification i{font-size:18px;}
.toast-notification.added i{color:var(--green);}
.toast-notification.removed i{color:var(--orange);}
@keyframes slideIn{from{transform:translateX(100px);opacity:0;}to{transform:translateX(0);opacity:1;}}

/* Empty State */
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:20px;border:1px solid var(--border);grid-column:1/-1;}
.empty-state i{font-size:64px;color:var(--border);margin-bottom:20px;}
.empty-state h3{color:var(--text);margin-bottom:10px;}
.empty-state p{color:var(--muted);margin-bottom:20px;}
.empty-state a{display:inline-block;background:var(--orange);color:white;padding:12px 30px;border-radius:40px;text-decoration:none;font-weight:600;}

/* Responsive */
@media(max-width:768px){
    .page-header h1{font-size:32px;}
    .filter-bar{flex-direction:column;border-radius:20px;padding:16px;margin:-20px 20px 0;}
    .filter-group{width:100%;justify-content:center;}
    .filter-search{width:100%;}
    .stats-bar{gap:12px;}
    .stat-item{padding:8px 16px;}
    .stat-num{font-size:18px;}
    .cards-grid{gap:20px;}
    .properties-container{padding:30px 16px;}
}

@media(max-width:480px){
    .card-price{font-size:22px;}
    .card-title{font-size:16px;}
    .card-specs{flex-wrap:wrap;gap:10px;}
}
</style>

<!-- Page Header -->
<div class="page-header">
    <h1>Find Your Dream Property</h1>
    <p>Discover premium residential and commercial spaces in Metro Manila</p>
    
    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-item"><div class="stat-num"><?php echo $total_properties; ?>+</div><div class="stat-label">Properties</div></div>
        <div class="stat-item"><div class="stat-num"><?php echo $sale_count; ?>+</div><div class="stat-label">For Sale</div></div>
        <div class="stat-item"><div class="stat-num"><?php echo $rent_count; ?>+</div><div class="stat-label">For Rent</div></div>
        <div class="stat-item"><div class="stat-num"><?php echo $project_count; ?>+</div><div class="stat-label">Projects</div></div>
    </div>
</div>

<!-- Filter Bar (Wishlist count removed) -->
<div class="filter-bar">
    <div class="filter-group">
        <button class="filter-btn <?php echo $type_filter == 'all' ? 'active' : ''; ?>" onclick="window.location.href='?type=all&search=<?php echo urlencode($search); ?>'">All</button>
        <button class="filter-btn <?php echo $type_filter == 'sale' ? 'active' : ''; ?>" onclick="window.location.href='?type=sale&search=<?php echo urlencode($search); ?>'">For Sale</button>
        <button class="filter-btn <?php echo $type_filter == 'rent' ? 'active' : ''; ?>" onclick="window.location.href='?type=rent&search=<?php echo urlencode($search); ?>'">For Rent</button>
        <button class="filter-btn <?php echo $type_filter == 'project' ? 'active' : ''; ?>" onclick="window.location.href='?type=project&search=<?php echo urlencode($search); ?>'">Projects</button>
    </div>
    <form class="filter-search" method="GET" action="">
        <i class="fas fa-search"></i>
        <input type="text" name="search" placeholder="Search by title, location..." value="<?php echo htmlspecialchars($search); ?>">
        <input type="hidden" name="type" value="<?php echo $type_filter; ?>">
        <button type="submit"><i class="fas fa-arrow-right"></i></button>
    </form>
</div>

<!-- Properties Container -->
<div class="properties-container">
    <div class="section-header">
        <h2><i class="fas fa-home"></i> Featured Properties</h2>
        <div class="result-count">Showing <?php echo count($properties); ?> properties</div>
    </div>

    <div class="cards-grid">
        <?php if(count($properties) > 0): ?>
            <?php foreach($properties as $property): ?>
                <div class="property-card" data-property-id="<?php echo $property['id']; ?>">
                    <div class="card-img">
                        <?php 
                        $imageFile = !empty($property['image']) ? $property['image'] : 'property1.png';
                        $imagePath = 'assets/images/' . $imageFile;
                        if(!file_exists($imagePath) && !empty($property['image']) && file_exists('assets/uploads/' . $property['image'])){
                            $imagePath = 'assets/uploads/' . $property['image'];
                        }
                        ?>
                        <img src="<?php echo $imagePath; ?>" alt="<?php echo htmlspecialchars($property['title']); ?>" onerror="this.src='assets/images/property1.png'">
                        <span class="card-badge badge-<?php echo $property['type'] == 'sale' ? 'sale' : ($property['type'] == 'rent' ? 'rent' : 'project'); ?>">
                            <?php echo $property['type'] == 'sale' ? 'For Sale' : ($property['type'] == 'rent' ? 'For Rent' : 'Project'); ?>
                        </span>
                        <?php if($property['is_featured']): ?>
                            <span class="card-feat"><i class="fas fa-star"></i> Featured</span>
                        <?php endif; ?>
                        
                        <!-- Favorite Button (Heart) -->
                        <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'client'): ?>
                            <button class="fav-btn <?php echo in_array($property['id'], $favorites) ? 'active' : ''; ?>" onclick="toggleFavorite(<?php echo $property['id']; ?>, this)">
                                <i class="fas fa-heart"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="card-price">
                            ₱ <?php echo number_format($property['price'], 0); ?>
                            <span><?php echo $property['type'] == 'rent' ? '/month' : ($property['type'] == 'sale' ? '' : '/unit'); ?></span>
                        </div>
                        <div class="card-title"><?php echo htmlspecialchars($property['title']); ?></div>
                        <div class="card-loc"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($property['location']); ?></div>
                        
                        <?php if(isset($property['bedrooms']) && $property['bedrooms'] > 0): ?>
                        <div class="card-specs">
                            <?php if($property['bedrooms'] > 0): ?>
                                <span class="spec"><i class="fas fa-bed"></i> <?php echo $property['bedrooms']; ?> beds</span>
                            <?php endif; ?>
                            <?php if($property['bathrooms'] > 0): ?>
                                <span class="spec"><i class="fas fa-bath"></i> <?php echo $property['bathrooms']; ?> baths</span>
                            <?php endif; ?>
                            <?php if($property['area'] > 0): ?>
                                <span class="spec"><i class="fas fa-arrows-alt"></i> <?php echo $property['area']; ?> m²</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'client'): ?>
                            <a href="inquiry.php?property_id=<?php echo $property['id']; ?>" class="property-btn">
                                <i class="fas fa-paper-plane"></i> Inquire Now
                            </a>
                        <?php elseif(isset($_SESSION['user_id'])): ?>
                            <a href="inquiry.php?property_id=<?php echo $property['id']; ?>" class="property-btn">
                                <i class="fas fa-paper-plane"></i> Send Inquiry
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="property-btn property-btn-outline">
                                <i class="fas fa-lock"></i> Login to Inquire
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-building"></i>
                <h3>No Properties Found</h3>
                <p>Try adjusting your search or filter criteria</p>
                <a href="properties.php">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast-notification">
    <i class="fas fa-heart"></i>
    <span id="toastMessage">Added to favorites</span>
</div>

<script>
function toggleFavorite(propertyId, button) {
    const isActive = button.classList.contains('active');
    const action = isActive ? 'remove' : 'add';
    
    fetch('toggle_favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `property_id=${propertyId}&action=${action}`
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            if(data.action === 'added') {
                button.classList.add('active');
                showToast('Added to wishlist! ❤️', 'added');
            } else {
                button.classList.remove('active');
                showToast('Removed from wishlist', 'removed');
            }
        } else {
            if(data.message.includes('login')) {
                window.location.href = 'login.php';
            } else {
                showToast(data.message, 'error');
            }
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
    
    if(type === 'added') {
        toastIcon.className = 'fas fa-heart';
        toast.style.background = '#2db12b';
    } else if(type === 'removed') {
        toastIcon.className = 'far fa-heart';
        toast.style.background = '#f07800';
    } else {
        toastIcon.className = 'fas fa-exclamation-circle';
        toast.style.background = '#dc2626';
    }
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 2500);
}
</script>

<?php include 'includes/footer.php'; ?>