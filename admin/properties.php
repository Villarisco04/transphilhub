<?php
require_once '../includes/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

// Create uploads directory if not exists
$upload_dir = '../assets/uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle Add Property
if(isset($_POST['add_property'])){
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];
    $price = $_POST['price'];
    $location = trim($_POST['location']);
    $status = $_POST['status'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $bedrooms = $_POST['bedrooms'] ?? 0;
    $bathrooms = $_POST['bathrooms'] ?? 0;
    $area = $_POST['area'] ?? 0;
    
    // Handle image upload
    $image_name = 'default-property.jpg';
    if(isset($_FILES['property_image']) && $_FILES['property_image']['error'] == 0){
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['property_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if(in_array($ext, $allowed)){
            $image_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
            move_uploaded_file($_FILES['property_image']['tmp_name'], $upload_dir . $image_name);
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO properties (title, description, type, price, location, status, is_featured, image, bedrooms, bathrooms, area) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if($stmt->execute([$title, $description, $type, $price, $location, $status, $is_featured, $image_name, $bedrooms, $bathrooms, $area])){
        $success = "Property added successfully!";
    } else {
        $error = "Failed to add property.";
    }
}

// Handle Edit Property
if(isset($_POST['edit_property'])){
    $id = $_POST['property_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'];
    $price = $_POST['price'];
    $location = trim($_POST['location']);
    $status = $_POST['status'];
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $bedrooms = $_POST['bedrooms'] ?? 0;
    $bathrooms = $_POST['bathrooms'] ?? 0;
    $area = $_POST['area'] ?? 0;
    
    // Handle image upload
    $image_name = $_POST['current_image'];
    if(isset($_FILES['property_image']) && $_FILES['property_image']['error'] == 0){
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['property_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if(in_array($ext, $allowed)){
            // Delete old image if not default
            if($image_name != 'default-property.jpg' && file_exists($upload_dir . $image_name)){
                unlink($upload_dir . $image_name);
            }
            $image_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
            move_uploaded_file($_FILES['property_image']['tmp_name'], $upload_dir . $image_name);
        }
    }
    
    $stmt = $pdo->prepare("UPDATE properties SET title=?, description=?, type=?, price=?, location=?, status=?, is_featured=?, image=?, bedrooms=?, bathrooms=?, area=? WHERE id=?");
    
    if($stmt->execute([$title, $description, $type, $price, $location, $status, $is_featured, $image_name, $bedrooms, $bathrooms, $area, $id])){
        $success = "Property updated successfully!";
    } else {
        $error = "Failed to update property.";
    }
}

// Handle Delete Property
if(isset($_GET['delete'])){
    $stmt = $pdo->prepare("SELECT image FROM properties WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $image = $stmt->fetchColumn();
    
    if($image && $image != 'default-property.jpg' && file_exists($upload_dir . $image)){
        unlink($upload_dir . $image);
    }
    
    $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ?");
    if($stmt->execute([$_GET['delete']])){
        $success = "Property deleted successfully!";
    } else {
        $error = "Failed to delete property.";
    }
}

// Handle Toggle Featured
if(isset($_GET['toggle_featured'])){
    $id = $_GET['toggle_featured'];
    $stmt = $pdo->prepare("UPDATE properties SET is_featured = NOT is_featured WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Featured status updated!";
    header("Location: properties.php");
    exit;
}

// Get property for editing
$edit_property = null;
if(isset($_GET['edit'])){
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_property = $stmt->fetch();
}

// Get all properties
$properties = $pdo->query("SELECT * FROM properties ORDER BY created_at DESC")->fetchAll();

// Statistics
$stats = [];
$stats['total_properties'] = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$stats['sale_properties'] = $pdo->query("SELECT COUNT(*) FROM properties WHERE type='sale'")->fetchColumn();
$stats['rent_properties'] = $pdo->query("SELECT COUNT(*) FROM properties WHERE type='rent'")->fetchColumn();
$stats['project_properties'] = $pdo->query("SELECT COUNT(*) FROM properties WHERE type='project'")->fetchColumn();
$stats['available_props'] = $pdo->query("SELECT COUNT(*) FROM properties WHERE status='available'")->fetchColumn();
$stats['featured_props'] = $pdo->query("SELECT COUNT(*) FROM properties WHERE is_featured=1")->fetchColumn();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Properties Management — Trans-Phil House Hub</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
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
.sidebar.collapsed .nav-item::after{content:attr(data-tip);position:absolute;left:76px;top:50%;transform:translateY(-50%);background:var(--navy);color:#fff;padding:6px 12px;border-radius:8px;font-size:12px;white-space:nowrap;opacity:0;pointer-events:none;transition:.15s;z-index:300;}
.sidebar.collapsed .nav-item:hover::after{opacity:1;}

.sb-toggle{position:absolute;top:22px;right:-14px;width:28px;height:28px;background:var(--orange);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff;z-index:300;transition:.3s;}
.sb-toggle i{color:#fff;font-size:12px;transition:.3s;}
.sidebar.collapsed .sb-toggle i{transform:rotate(180deg);}
.sb-footer{margin-top:auto;padding:16px 0;border-top:1px solid rgba(255,255,255,.08);}

/* ── MAIN ── */
.main{flex:1;margin-left:260px;transition:.3s;min-width:0;}
.sidebar.collapsed ~ .main{margin-left:72px;}

.topbar{background:var(--card);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
.topbar-left h1{font-size:20px;color:var(--navy);font-weight:700;}
.topbar-left p{font-size:12px;color:var(--muted);}
.topbar-right{display:flex;align-items:center;gap:16px;}
.user-chip{display:flex;align-items:center;gap:10px;background:var(--bg);padding:6px 14px 6px 6px;border-radius:30px;}
.user-avatar{width:32px;height:32px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;}
.user-chip span{font-size:13px;font-weight:600;color:var(--navy);}

.content{padding:28px;}

/* Stats Row */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:28px;}
.stat-card{background:var(--card);border-radius:var(--radius);padding:20px;display:flex;align-items:center;gap:16px;box-shadow:var(--shadow);border:1px solid var(--border);}
.stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.stat-icon i{font-size:22px;}
.stat-num{font-size:26px;font-weight:700;color:var(--navy);line-height:1;}
.stat-label{font-size:12px;color:var(--muted);margin-top:3px;}

/* Form Card */
.form-card{background:var(--card);border-radius:var(--radius);padding:24px;margin-bottom:28px;border:1px solid var(--border);}
.form-card h2{font-size:16px;font-weight:700;color:var(--navy);margin-bottom:20px;}
.form-card h2 i{margin-right:8px;color:var(--orange);}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;margin-bottom:20px;}
.form-group label{display:block;font-size:12px;font-weight:600;color:var(--text);margin-bottom:6px;}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;font-family:inherit;font-size:13px;transition:.2s;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:var(--orange);box-shadow:0 0 0 3px rgba(240,120,0,.1);}
.btn-primary{background:var(--orange);color:#fff;padding:12px 24px;border:none;border-radius:10px;cursor:pointer;font-weight:600;font-size:13px;transition:.2s;}
.btn-primary:hover{background:var(--orange2);transform:translateY(-1px);}
.btn-secondary{background:var(--bg);color:var(--text);padding:10px 20px;border:none;border-radius:10px;cursor:pointer;font-weight:500;font-size:13px;margin-left:10px;}

/* Table Card */
.table-card{background:var(--card);border-radius:var(--radius);padding:24px;border:1px solid var(--border);overflow-x:auto;}
.table-card h2{font-size:16px;font-weight:700;color:var(--navy);margin-bottom:20px;}
.table-card h2 i{margin-right:8px;color:var(--orange);}

.tbl{width:100%;border-collapse:collapse;}
.tbl th{padding:12px 12px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);background:#f8f7fc;border-bottom:1px solid var(--border);text-align:left;}
.tbl td{padding:12px;font-size:13px;border-bottom:1px solid var(--border);vertical-align:middle;}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:hover td{background:#fafaf8;}

.property-img{width:65px;height:50px;object-fit:cover;border-radius:8px;background:#f3f4f6;}
.badge{padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
.b-sale{background:#e0e8f7;color:var(--navy);}
.b-rent{background:#fff0df;color:var(--orange2);}
.b-project{background:#e6f7e6;color:var(--green2);}
.b-available{background:#e6f7e6;color:var(--green2);}
.b-sold{background:#fef0f0;color:#c0392b;}
.b-rented{background:#fef0f0;color:#c0392b;}
.b-featured{background:#fef9e0;color:#a07000;}
.action-btn{padding:6px 10px;border-radius:8px;text-decoration:none;font-size:12px;display:inline-block;margin:0 3px;}
.action-btn i{font-size:11px;}
.btn-edit{background:#e0e8f7;color:var(--navy);}
.btn-delete{background:#fef0f0;color:#c0392b;}
.btn-feature{background:#fef9e0;color:#a07000;}
.alert{padding:14px 18px;border-radius:12px;margin-bottom:20px;}
.alert-success{background:#e6f7e6;color:var(--green2);border-left:4px solid var(--green);}
.alert-error{background:#fef0f0;color:#c0392b;border-left:4px solid #c0392b;}
.mob-toggle{display:none;position:fixed;bottom:20px;right:20px;width:48px;height:48px;background:var(--navy);border-radius:50%;align-items:center;justify-content:center;z-index:400;cursor:pointer;border:none;color:#fff;font-size:18px;box-shadow:0 4px 16px rgba(0,0,0,.2);}

@media(max-width:1100px){.stats-row{grid-template-columns:repeat(2,1fr);}}
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);width:260px;}
    .sidebar.show{transform:translateX(0);}
    .main{margin-left:0!important;}
    .mob-toggle{display:flex;}
    .stats-row{grid-template-columns:1fr;}
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
    <a href="dashboard.php" class="nav-item" data-tip="Dashboard"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="properties.php" class="nav-item active" data-tip="Properties"><i class="fas fa-building"></i><span>Properties</span></a>
    <a href="leads.php" class="nav-item" data-tip="Leads"><i class="fas fa-funnel-dollar"></i><span>Lead Management</span></a>
    <div class="sb-section">Management</div>
    <a href="users.php" class="nav-item" data-tip="Users"><i class="fas fa-users"></i><span>User Management</span></a>
    <a href="reports.php" class="nav-item" data-tip="Reports"><i class="fas fa-chart-bar"></i><span>Reports & Analytics</span></a>
    <div class="sb-footer">
        <a href="../logout.php" class="nav-item" data-tip="Logout"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </div>
</aside>

<button class="mob-toggle" id="mobToggle"><i class="fas fa-bars"></i></button>

<!-- MAIN -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h1>Properties Management</h1>
            <p><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="topbar-right">
            <div class="user-chip">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'],0,1)); ?></div>
                <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            </div>
        </div>
    </div>

    <div class="content">

        <!-- STATS CARDS -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon" style="background:#eef2f9;"><i class="fas fa-home" style="color:var(--navy);"></i></div>
                <div><div class="stat-num"><?php echo $stats['total_properties']; ?></div><div class="stat-label">Total Properties</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff0df;"><i class="fas fa-tag" style="color:var(--orange);"></i></div>
                <div><div class="stat-num"><?php echo $stats['sale_properties']; ?></div><div class="stat-label">For Sale</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e6f7e6;"><i class="fas fa-key" style="color:var(--green);"></i></div>
                <div><div class="stat-num"><?php echo $stats['rent_properties']; ?></div><div class="stat-label">For Rent</div></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fef9e0;"><i class="fas fa-hard-hat" style="color:#a07000;"></i></div>
                <div><div class="stat-num"><?php echo $stats['project_properties']; ?></div><div class="stat-label">Projects</div></div>
            </div>
        </div>

        <!-- ALERTS -->
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- ADD/EDIT PROPERTY FORM -->
        <div class="form-card">
            <h2><i class="fas fa-<?php echo $edit_property ? 'pen' : 'plus'; ?>"></i> <?php echo $edit_property ? 'Edit Property' : 'Add New Property'; ?></h2>
            <form method="POST" enctype="multipart/form-data">
                <?php if($edit_property): ?>
                    <input type="hidden" name="property_id" value="<?php echo $edit_property['id']; ?>">
                    <input type="hidden" name="current_image" value="<?php echo $edit_property['image']; ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Property Title *</label>
                        <input type="text" name="title" required value="<?php echo htmlspecialchars($edit_property['title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Property Type *</label>
                        <select name="type" required>
                            <option value="sale" <?php echo ($edit_property['type'] ?? '') == 'sale' ? 'selected' : ''; ?>>For Sale</option>
                            <option value="rent" <?php echo ($edit_property['type'] ?? '') == 'rent' ? 'selected' : ''; ?>>For Rent</option>
                            <option value="project" <?php echo ($edit_property['type'] ?? '') == 'project' ? 'selected' : ''; ?>>Development Project</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Price (₱) *</label>
                        <input type="number" name="price" required value="<?php echo $edit_property['price'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Location *</label>
                        <input type="text" name="location" required value="<?php echo htmlspecialchars($edit_property['location'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Bedrooms</label>
                        <input type="number" name="bedrooms" value="<?php echo $edit_property['bedrooms'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Bathrooms</label>
                        <input type="number" name="bathrooms" value="<?php echo $edit_property['bathrooms'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Area (sqm)</label>
                        <input type="number" name="area" value="<?php echo $edit_property['area'] ?? ''; ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="available" <?php echo ($edit_property['status'] ?? '') == 'available' ? 'selected' : ''; ?>>Available</option>
                            <option value="sold" <?php echo ($edit_property['status'] ?? '') == 'sold' ? 'selected' : ''; ?>>Sold</option>
                            <option value="rented" <?php echo ($edit_property['status'] ?? '') == 'rented' ? 'selected' : ''; ?>>Rented</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Featured Property</label>
                        <select name="is_featured">
                            <option value="0">No</option>
                            <option value="1" <?php echo ($edit_property['is_featured'] ?? 0) == 1 ? 'selected' : ''; ?>>Yes (Show on Homepage)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Property Image</label>
                    <input type="file" name="property_image" accept="image/*">
                    <?php if($edit_property && $edit_property['image'] && $edit_property['image'] != 'default-property.jpg'): ?>
                        <p style="margin-top:6px; font-size:11px; color:var(--muted);">Current: <?php echo $edit_property['image']; ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="4"><?php echo htmlspecialchars($edit_property['description'] ?? ''); ?></textarea>
                </div>
                
                <button type="submit" name="<?php echo $edit_property ? 'edit_property' : 'add_property'; ?>" class="btn-primary">
                    <i class="fas fa-save"></i> <?php echo $edit_property ? 'Update Property' : 'Add Property'; ?>
                </button>
                <?php if($edit_property): ?>
                    <a href="properties.php" class="btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- PROPERTIES LIST TABLE -->
        <div class="table-card">
            <h2><i class="fas fa-list"></i> All Properties</h2>
            <?php if(count($properties) > 0): ?>
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Title / Location</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Specs</th>
                            <th>Status</th>
                            <th>Featured</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($properties as $prop): ?>
                            <?php 
                            $image_path = '../assets/uploads/' . ($prop['image'] ?? 'default-property.jpg');
                            if(!file_exists($image_path) || ($prop['image'] ?? '') == 'default-property.jpg'){
                                $image_path = '../assets/images/' . ($prop['image'] ?? 'property1.png');
                            }
                            if(!file_exists($image_path)){
                                $image_path = '../assets/images/property1.png';
                            }
                            ?>
                            <tr>
                                <td><img src="<?php echo $image_path; ?>" class="property-img" onerror="this.src='../assets/images/property1.png'"></td>
                                <td>
                                    <div style="font-weight:600;"><?php echo htmlspecialchars($prop['title']); ?></div>
                                    <div style="font-size:11px; color:var(--muted); margin-top:2px;"><i class="fas fa-map-pin"></i> <?php echo htmlspecialchars($prop['location']); ?></div>
                                </td>
                                <td><span class="badge b-<?php echo $prop['type']; ?>"><?php echo ucfirst($prop['type']); ?></span></td>
                                <td><span style="font-weight:700; color:var(--navy);">₱ <?php echo number_format($prop['price'], 0); ?></span></td>
                                <td>
                                    <?php if($prop['bedrooms']): ?><span style="font-size:11px;"><i class="fas fa-bed"></i> <?php echo $prop['bedrooms']; ?></span><?php endif; ?>
                                    <?php if($prop['bathrooms']): ?><span style="font-size:11px; margin-left:6px;"><i class="fas fa-bath"></i> <?php echo $prop['bathrooms']; ?></span><?php endif; ?>
                                    <?php if($prop['area']): ?><span style="font-size:11px; margin-left:6px;"><i class="fas fa-expand"></i> <?php echo $prop['area']; ?>m²</span><?php endif; ?>
                                </td>
                                <td><span class="badge b-<?php echo $prop['status']; ?>"><?php echo ucfirst($prop['status']); ?></span></td>
                                <td>
                                    <?php if($prop['is_featured']): ?>
                                        <span class="badge b-featured"><i class="fas fa-star"></i> Featured</span>
                                    <?php else: ?>
                                        <span style="color:var(--muted); font-size:11px;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="?edit=<?php echo $prop['id']; ?>" class="action-btn btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                    <?php if($prop['is_featured']): ?>
                                        <a href="?toggle_featured=<?php echo $prop['id']; ?>" class="action-btn" style="background:#fef9e0;color:#a07000;"><i class="fas fa-star"></i> Unfeature</a>
                                    <?php else: ?>
                                        <a href="?toggle_featured=<?php echo $prop['id']; ?>" class="action-btn" style="background:#e0e8f7;color:var(--navy);"><i class="far fa-star"></i> Feature</a>
                                    <?php endif; ?>
                                    <a href="?delete=<?php echo $prop['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Delete this property?')"><i class="fas fa-trash-alt"></i> Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align:center; padding:48px; color:var(--muted);">
                    <i class="fas fa-building" style="font-size:48px; margin-bottom:16px; opacity:0.5;"></i>
                    <p>No properties found. Click "Add New Property" to get started.</p>
                </div>
            <?php endif; ?>
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
</script>
</body>
</html>