<?php
require_once '../includes/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

// ── HANDLE ADD USER ──
if(isset($_POST['add_user'])){
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $status = $_POST['status'];
    
    $errors = [];
    if(empty($full_name)) $errors[] = "Full name required";
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
    if(strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if($stmt->rowCount() > 0) $errors[] = "Email already exists";
    
    if(empty($errors)){
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, phone, address, status) VALUES (?,?,?,?,?,?,?)");
        if($stmt->execute([$full_name, $email, $hashed, $role, $phone, $address, $status])){
            $success = "User added successfully!";
        } else {
            $error = "Failed to add user";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// ── HANDLE EDIT USER ──
if(isset($_POST['edit_user'])){
    $user_id = (int)$_POST['user_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, role=?, phone=?, address=?, status=? WHERE id=?");
    if($stmt->execute([$full_name, $email, $role, $phone, $address, $status, $user_id])){
        $success = "User updated successfully!";
    } else {
        $error = "Failed to update user";
    }
}

// ── HANDLE RESET PASSWORD ──
if(isset($_POST['reset_password'])){
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'];
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
    if($stmt->execute([$hashed, $user_id])){
        $success = "Password reset successfully! New password: <strong>" . htmlspecialchars($new_password) . "</strong>";
    } else {
        $error = "Failed to reset password";
    }
}

// ── HANDLE DELETE USER ──
if(isset($_GET['delete'])){
    $user_id = (int)$_GET['delete'];
    if($user_id == $_SESSION['user_id']){
        $error = "You cannot delete your own account!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
        if($stmt->execute([$user_id])){
            $success = "User deleted successfully!";
        } else {
            $error = "Failed to delete user";
        }
    }
}

// ── HANDLE TOGGLE STATUS ──
if(isset($_GET['toggle_status'])){
    $user_id = (int)$_GET['toggle_status'];
    $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active', 'inactive', 'active') WHERE id=?");
    $stmt->execute([$user_id]);
    $success = "User status updated!";
    header("Location: users.php");
    exit;
}

// ── GET USER FOR EDITING ──
$edit_user = null;
if(isset($_GET['edit'])){
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_user = $stmt->fetch();
}

// ── GET USER FOR PASSWORD RESET ──
$reset_user = null;
if(isset($_GET['reset'])){
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['reset']]);
    $reset_user = $stmt->fetch();
}

// ── FILTERING ──
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = "WHERE 1=1";
$params = [];

if($role_filter !== 'all'){
    $where .= " AND role = ?";
    $params[] = $role_filter;
}
if($status_filter !== 'all'){
    $where .= " AND status = ?";
    $params[] = $status_filter;
}
if(!empty($search)){
    $where .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query = "SELECT * FROM users $where ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// ── STATISTICS ──
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['admins'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$stats['agents'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='agent'")->fetchColumn();
$stats['clients'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();
$stats['active'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$stats['inactive'] = $pdo->query("SELECT COUNT(*) FROM users WHERE status='inactive'")->fetchColumn();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Management — Trans-Phil House Hub</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{--navy:#1a3a6b;--navy2:#0f2340;--green:#2db12b;--green2:#218f1f;--orange:#f07800;--orange2:#c96400;--white:#ffffff;--bg:#f0eff5;--card:#ffffff;--border:#e4e2ee;--text:#1e1c2e;--muted:#6b6880;--radius:14px;--shadow:0 2px 12px rgba(26,58,107,.07);}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;}
.sidebar{width:260px;background:var(--navy2);display:flex;flex-direction:column;position:fixed;height:100vh;z-index:200;}
.sb-logo{padding:24px 20px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:12px;}
.sb-logo svg{width:40px;height:40px;flex-shrink:0;}
.t1{font-size:15px;font-weight:700;color:#fff;}.t2{font-size:10px;color:var(--orange);letter-spacing:1.2px;text-transform:uppercase;}
.sb-section{padding:16px 20px 4px;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,.3);}
.nav-item{display:flex;align-items:center;gap:14px;padding:11px 20px;color:rgba(255,255,255,.6);text-decoration:none;transition:.2s;border-left:3px solid transparent;font-size:14px;font-weight:500;}
.nav-item i{font-size:17px;width:20px;text-align:center;}
.nav-item:hover{color:#fff;background:rgba(255,255,255,.06);}
.nav-item.active{color:#fff;background:rgba(45,177,43,.15);border-left:3px solid var(--green);}
.nav-badge{background:var(--orange);color:#fff;font-size:10px;padding:2px 7px;border-radius:20px;margin-left:auto;}
.sb-footer{margin-top:auto;padding:16px 0;border-top:1px solid rgba(255,255,255,.08);}
.main{flex:1;margin-left:260px;min-width:0;}
.topbar{background:var(--card);padding:0 28px;height:64px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:100;}
.topbar h1{font-size:20px;color:var(--navy);font-weight:700;}
.topbar p{font-size:12px;color:var(--muted);}
.user-chip{display:flex;align-items:center;gap:10px;background:var(--bg);padding:6px 14px 6px 6px;border-radius:30px;}
.user-avatar{width:32px;height:32px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;}
.user-chip span{font-size:13px;font-weight:600;color:var(--navy);}
.content{padding:28px;}

/* Stats Cards */
.stats-row{display:grid;grid-template-columns:repeat(6,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:var(--card);border-radius:12px;padding:16px;text-align:center;border:1px solid var(--border);}
.stat-num{font-size:28px;font-weight:700;color:var(--navy);}
.stat-label{font-size:11px;color:var(--muted);margin-top:4px;text-transform:uppercase;letter-spacing:.5px;}

/* Filter Bar */
.filter-bar{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center;}
.filter-group{display:flex;align-items:center;gap:8px;background:var(--card);padding:6px 12px;border-radius:30px;border:1px solid var(--border);}
.filter-group i{color:var(--muted);font-size:13px;}
.filter-group select,.filter-group input{padding:6px 8px;border:none;background:transparent;font-size:13px;outline:none;}
.filter-btn{background:var(--orange);color:#fff;border:none;padding:6px 16px;border-radius:30px;font-size:12px;font-weight:600;cursor:pointer;}
.filter-reset{background:var(--bg);color:var(--muted);border:1px solid var(--border);padding:6px 16px;border-radius:30px;font-size:12px;text-decoration:none;}

/* Card */
.card{background:var(--card);border-radius:var(--radius);padding:22px 24px;box-shadow:var(--shadow);border:1px solid var(--border);margin-bottom:20px;}
.card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px;}
.card-head h2{font-size:15px;font-weight:700;color:var(--navy);}
.card-head h2 i{margin-right:8px;color:var(--orange);}

/* Form Grid */
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:16px;}
.fg label{display:block;font-size:11px;font-weight:600;color:var(--muted);margin-bottom:5px;text-transform:uppercase;letter-spacing:.5px;}
.fg input,.fg select,.fg textarea{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:13px;background:#fafaf8;}
.fg input:focus,.fg select:focus{outline:none;border-color:var(--orange);}
.btn-primary{background:var(--orange);color:#fff;padding:10px 22px;border:none;border-radius:8px;cursor:pointer;font-weight:600;font-size:13px;transition:.2s;}
.btn-primary:hover{background:var(--orange2);}
.btn-secondary{background:var(--bg);color:var(--text);padding:8px 16px;border-radius:8px;text-decoration:none;font-size:12px;font-weight:500;margin-left:10px;border:1px solid var(--border);}

/* Table */
.tbl-wrap{overflow-x:auto;}
.tbl{width:100%;border-collapse:collapse;min-width:800px;}
.tbl th{padding:10px 12px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--muted);background:#f8f7fc;border-bottom:1px solid var(--border);text-align:left;}
.tbl td{padding:11px 12px;font-size:13px;border-bottom:1px solid var(--border);vertical-align:middle;}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:hover td{background:#fafaf8;}

/* Badges */
.badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
.role-admin{background:#e0e8f7;color:var(--navy);}
.role-agent{background:#fff0df;color:var(--orange2);}
.role-client{background:#e6f7e6;color:var(--green2);}
.status-active{background:#e6f7e6;color:var(--green2);}
.status-inactive{background:#fef0f0;color:#c0392b;}

/* Action Buttons */
.action-group{display:flex;gap:6px;flex-wrap:wrap;}
.action-btn{padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;text-decoration:none;display:inline-block;transition:.2s;cursor:pointer;border:none;}
.btn-edit{background:#e0e8f7;color:var(--navy);}
.btn-reset{background:#fef9e0;color:#a07000;}
.btn-toggle{background:#e6f7e6;color:var(--green2);}
.btn-delete{background:#fef0f0;color:#c0392b;}
.btn-edit:hover{background:#c5d3e8;}
.btn-reset:hover{background:#f5e6a0;}
.btn-toggle:hover{background:#c8eec8;}
.btn-delete:hover{background:#fdd;}

/* Alert */
.alert{padding:12px 16px;border-radius:8px;margin-bottom:18px;font-size:13px;}
.alert-success{background:#e6f7e6;color:var(--green2);border-left:4px solid var(--green);}
.alert-error{background:#fef0f0;color:#c0392b;border-left:4px solid #c0392b;}

/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;align-items:center;justify-content:center;}
.modal-bg.open{display:flex;}
.modal-box{background:#fff;border-radius:16px;padding:28px;width:90%;max-width:460px;box-shadow:0 8px 40px rgba(0,0,0,.15);}
.modal-box h3{font-size:16px;color:var(--navy);margin-bottom:18px;}
.modal-close{float:right;cursor:pointer;color:var(--muted);font-size:18px;background:none;border:none;}
.modal-box .btn-primary{width:100%;}

/* User Avatar */
.user-avatar-sm{width:32px;height:32px;border-radius:50%;background:var(--navy);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:12px;flex-shrink:0;}

@media(max-width:1100px){.stats-row{grid-template-columns:repeat(3,1fr);}}
@media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr);}.main{margin-left:0;}.sidebar{display:none;}}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sb-logo">
        <svg viewBox="0 0 40 40"><circle cx="20" cy="20" r="18" fill="none" stroke="#1a3a6b" stroke-width="2.5" stroke-dasharray="95 15"/><polygon points="20,5 30,15 10,15" fill="#2db12b"/><rect x="11" y="15" width="7" height="11" fill="#f07800"/><rect x="22" y="15" width="7" height="11" fill="#f07800"/><rect x="17" y="15" width="6" height="16" fill="#2db12b"/></svg>
        <div><div class="t1">Trans-Phil Hub</div><div class="t2">Administrator</div></div>
    </div>
    <div class="sb-section">Main Menu</div>
    <a href="dashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="properties.php" class="nav-item"><i class="fas fa-building"></i> Properties</a>
    <a href="leads.php" class="nav-item"><i class="fas fa-funnel-dollar"></i> Lead Management</a>
    <div class="sb-section">Management</div>
    <a href="users.php" class="nav-item active"><i class="fas fa-users"></i> User Management</a>
    <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i> Reports & Analytics</a>
    <div class="sb-footer">
        <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div><h1>User Management</h1><p><?php echo date('l, F j, Y'); ?></p></div>
        <div class="user-chip">
            <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['full_name'],0,1)); ?></div>
            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
    </div>

    <div class="content">

        <?php if(isset($success)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- STATS CARDS -->
        <div class="stats-row">
            <div class="stat-card"><div class="stat-num"><?php echo $stats['total']; ?></div><div class="stat-label">Total Users</div></div>
            <div class="stat-card"><div class="stat-num" style="color:var(--navy);"><?php echo $stats['admins']; ?></div><div class="stat-label">Admins</div></div>
            <div class="stat-card"><div class="stat-num" style="color:var(--orange);"><?php echo $stats['agents']; ?></div><div class="stat-label">Agents</div></div>
            <div class="stat-card"><div class="stat-num" style="color:var(--green);"><?php echo $stats['clients']; ?></div><div class="stat-label">Clients</div></div>
            <div class="stat-card"><div class="stat-num" style="color:#059669;"><?php echo $stats['active']; ?></div><div class="stat-label">Active</div></div>
            <div class="stat-card"><div class="stat-num" style="color:#c0392b;"><?php echo $stats['inactive']; ?></div><div class="stat-label">Inactive</div></div>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <div class="filter-group">
                <i class="fas fa-filter"></i>
                <select id="roleFilter" onchange="applyFilters()">
                    <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>All Roles</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="agent" <?php echo $role_filter == 'agent' ? 'selected' : ''; ?>>Agent</option>
                    <option value="client" <?php echo $role_filter == 'client' ? 'selected' : ''; ?>>Client</option>
                </select>
            </div>
            <div class="filter-group">
                <i class="fas fa-circle"></i>
                <select id="statusFilter" onchange="applyFilters()">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="filter-group">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search name or email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button class="filter-btn" onclick="applyFilters()"><i class="fas fa-search"></i> Filter</button>
            <a href="users.php" class="filter-reset"><i class="fas fa-undo-alt"></i> Reset</a>
        </div>

        <!-- ADD USER FORM -->
        <?php if(!$edit_user && !$reset_user): ?>
        <div class="card">
            <div class="card-head">
                <h2><i class="fas fa-user-plus"></i> Add New User</h2>
            </div>
            <form method="POST">
                <div class="form-grid">
                    <div class="fg"><label>Full Name *</label><input type="text" name="full_name" required></div>
                    <div class="fg"><label>Email *</label><input type="email" name="email" required></div>
                    <div class="fg"><label>Password *</label><input type="password" name="password" required></div>
                    <div class="fg">
                        <label>Role *</label>
                        <select name="role">
                            <option value="client">Client</option>
                            <option value="agent">Real Estate Agent</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="fg"><label>Phone</label><input type="text" name="phone" placeholder="09123456789"></div>
                    <div class="fg">
                        <label>Status</label>
                        <select name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="fg"><label>Address</label><textarea name="address" rows="2"></textarea></div>
                <button type="submit" name="add_user" class="btn-primary"><i class="fas fa-save"></i> Add User</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- EDIT USER FORM -->
        <?php if($edit_user): ?>
        <div class="card">
            <div class="card-head">
                <h2><i class="fas fa-edit"></i> Edit User: <?php echo htmlspecialchars($edit_user['full_name']); ?></h2>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <div class="form-grid">
                    <div class="fg"><label>Full Name *</label><input type="text" name="full_name" value="<?php echo htmlspecialchars($edit_user['full_name']); ?>" required></div>
                    <div class="fg"><label>Email *</label><input type="email" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required></div>
                    <div class="fg">
                        <label>Role *</label>
                        <select name="role">
                            <option value="client" <?php echo $edit_user['role'] == 'client' ? 'selected' : ''; ?>>Client</option>
                            <option value="agent" <?php echo $edit_user['role'] == 'agent' ? 'selected' : ''; ?>>Agent</option>
                            <option value="admin" <?php echo $edit_user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="fg"><label>Phone</label><input type="text" name="phone" value="<?php echo htmlspecialchars($edit_user['phone'] ?? ''); ?>"></div>
                    <div class="fg">
                        <label>Status</label>
                        <select name="status">
                            <option value="active" <?php echo ($edit_user['status'] ?? 'active') == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($edit_user['status'] ?? '') == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="fg"><label>Address</label><textarea name="address" rows="2"><?php echo htmlspecialchars($edit_user['address'] ?? ''); ?></textarea></div>
                <button type="submit" name="edit_user" class="btn-primary"><i class="fas fa-save"></i> Update User</button>
                <a href="users.php" class="btn-secondary">Cancel</a>
            </form>
        </div>
        <?php endif; ?>

        <!-- USERS TABLE -->
        <div class="card">
            <div class="card-head">
                <h2><i class="fas fa-list-alt"></i> All Users <span style="font-size:13px;font-weight:400;color:var(--muted);">(<?php echo count($users); ?> records)</span></h2>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="user-avatar-sm"><?php echo strtoupper(substr($user['full_name'],0,1)); ?></div>
                                    <div>
                                        <div style="font-weight:600;"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                        <div style="font-size:11px;color:var(--muted);"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:12px;"><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></div>
                            </td>
                            <td><span class="badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><span class="badge status-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                            <td style="font-size:11px;color:var(--muted);"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td class="action-group">
                                <a href="?edit=<?php echo $user['id']; ?>" class="action-btn btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                <a href="?reset=<?php echo $user['id']; ?>" class="action-btn btn-reset"><i class="fas fa-key"></i> Reset</a>
                                <a href="?toggle_status=<?php echo $user['id']; ?>" class="action-btn btn-toggle" onclick="return confirm('Toggle user status?')">
                                    <i class="fas fa-<?php echo $user['status'] == 'active' ? 'ban' : 'check-circle'; ?>"></i> <?php echo $user['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                </a>
                                <?php if($user['id'] != $_SESSION['user_id']): ?>
                                <a href="?delete=<?php echo $user['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Delete this user?')"><i class="fas fa-trash"></i> Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($users)): ?>
                        <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--muted);">No users found matching your filters.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal-bg" id="resetModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('resetModal')"><i class="fas fa-times"></i></button>
        <h3><i class="fas fa-key" style="color:var(--orange);margin-right:8px;"></i>Reset Password</h3>
        <?php if($reset_user): ?>
        <form method="POST">
            <input type="hidden" name="user_id" value="<?php echo $reset_user['id']; ?>">
            <div class="fg" style="margin-bottom:16px;">
                <label>User: <?php echo htmlspecialchars($reset_user['full_name']); ?></label>
                <input type="text" name="new_password" required placeholder="Enter new password" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;margin-top:8px;">
                <small style="color:var(--muted);font-size:11px;">Minimum 6 characters</small>
            </div>
            <button type="submit" name="reset_password" class="btn-primary" style="width:100%;"><i class="fas fa-sync-alt"></i> Reset Password</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
function applyFilters() {
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value;
    window.location.href = `users.php?role=${role}&status=${status}&search=${encodeURIComponent(search)}`;
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

// Auto-open reset modal if reset parameter is present
<?php if($reset_user): ?>
document.getElementById('resetModal').classList.add('open');
<?php endif; ?>

document.querySelectorAll('.modal-bg').forEach(m => m.addEventListener('click', function(e){
    if(e.target === this) this.classList.remove('open');
}));

// Search on Enter key
document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if(e.key === 'Enter') applyFilters();
});
</script>
</body>
</html>