<?php
require_once '../includes/db.php';
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Mark single notification as read
if(isset($_GET['mark_read']) && isset($_GET['id'])){
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['id'], $user_id]);
    header("Location: notifications.php");
    exit;
}

// Mark all as read
if(isset($_GET['mark_all'])){
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    header("Location: notifications.php");
    exit;
}

// Delete notification
if(isset($_GET['delete']) && isset($_GET['id'])){
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['id'], $user_id]);
    header("Location: notifications.php");
    exit;
}

// Get all notifications
$notifications = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$notifications->execute([$user_id]);
$notifications = $notifications->fetchAll();

$unread_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_count->execute([$user_id]);
$unread_count = $unread_count->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Admin | Trans-Phil</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'DM Sans', sans-serif; 
            background: #f0eff5; 
            padding: 30px; 
            min-height: 100vh;
        }
        .container { max-width: 800px; margin: 0 auto; }
        
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 25px; 
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 { 
            font-size: 24px; 
            color: #1a3a6b; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header h1 i { color: #f07800; }
        
        .btn-back { 
            background: #f07800; 
            color: white; 
            padding: 8px 16px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 13px;
            transition: 0.2s;
        }
        .btn-back:hover { background: #d86d00; }
        
        .btn-mark-all { 
            background: #e0e8f7; 
            color: #1a3a6b; 
            padding: 8px 16px; 
            border-radius: 8px; 
            text-decoration: none; 
            font-size: 13px; 
            margin-left: 10px;
            transition: 0.2s;
        }
        .btn-mark-all:hover { background: #c5d3e8; }
        
        .notif-item {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 10px;
            border: 1px solid #e4e2ee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: 0.2s;
        }
        .notif-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .notif-item.unread { 
            background: #eef2f9; 
            border-left: 4px solid #f07800; 
        }
        .notif-content { flex: 1; }
        .notif-message { 
            font-size: 14px; 
            color: #1e1c2e; 
            margin-bottom: 5px;
            line-height: 1.5;
        }
        .notif-time { 
            font-size: 11px; 
            color: #6b7280; 
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .notif-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .notif-link { 
            color: #f07800; 
            font-size: 12px; 
            text-decoration: none; 
            font-weight: 500;
        }
        .notif-link:hover { text-decoration: underline; }
        
        .notif-delete {
            color: #dc2626;
            font-size: 12px;
            text-decoration: none;
        }
        .notif-delete:hover { text-decoration: underline; }
        
        .empty { 
            text-align: center; 
            padding: 50px; 
            color: #6b7280; 
            background: white; 
            border-radius: 12px;
            border: 1px solid #e4e2ee;
        }
        .empty i { 
            font-size: 48px; 
            margin-bottom: 15px; 
            opacity: 0.5; 
        }
        
        @media (max-width: 600px) {
            body { padding: 20px; }
            .notif-item { flex-direction: column; gap: 10px; align-items: flex-start; }
            .notif-actions { align-self: flex-end; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-bell"></i> 
            Notifications
            <?php if($unread_count > 0): ?>
                <span style="font-size: 12px; background: #f07800; color: white; padding: 2px 8px; border-radius: 20px;"><?php echo $unread_count; ?> new</span>
            <?php endif; ?>
        </h1>
        <div>
            <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            <?php if($unread_count > 0): ?>
                <a href="?mark_all=1" class="btn-mark-all"><i class="fas fa-check-double"></i> Mark all as read</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if(count($notifications) > 0): ?>
        <?php foreach($notifications as $notif): ?>
            <div class="notif-item <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                <div class="notif-content">
                    <div class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                    <div class="notif-time">
                        <i class="far fa-clock"></i>
                        <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                    </div>
                </div>
                <div class="notif-actions">
                    <?php if($notif['is_read'] == 0): ?>
                        <a href="?mark_read=1&id=<?php echo $notif['id']; ?>" class="notif-link">Mark read</a>
                    <?php endif; ?>
                    <a href="?delete=1&id=<?php echo $notif['id']; ?>" class="notif-delete" onclick="return confirm('Delete this notification?')">Delete</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty">
            <i class="fas fa-bell-slash"></i>
            <p>No notifications yet</p>
            <p style="font-size: 12px; margin-top: 8px;">When you receive notifications, they will appear here.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>