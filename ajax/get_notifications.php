<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/notifications.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

switch($action){
    case 'count':
        $count = get_unread_count($user_id);
        echo json_encode(['success' => true, 'count' => $count]);
        break;
        
    case 'list':
        $notifications = get_notifications($user_id, 10);
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
        
    case 'mark_read':
        $notif_id = $_GET['id'] ?? 0;
        mark_as_read($notif_id, $user_id);
        echo json_encode(['success' => true]);
        break;
        
    case 'mark_all_read':
        mark_all_as_read($user_id);
        echo json_encode(['success' => true]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>