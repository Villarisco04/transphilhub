<?php
require_once 'includes/db.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in and is client
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client'){
    echo json_encode(['success' => false, 'message' => 'Please login to save favorites']);
    exit;
}

$client_id = $_SESSION['user_id'];
$property_id = isset($_POST['property_id']) ? (int)$_POST['property_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : 'toggle';

if(!$property_id){
    echo json_encode(['success' => false, 'message' => 'Invalid property']);
    exit;
}

if($action == 'add'){
    // Add to favorites
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO favorites (client_id, property_id) VALUES (?, ?)");
        $stmt->execute([$client_id, $property_id]);
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Added to favorites']);
    } catch(Exception $e){
        echo json_encode(['success' => false, 'message' => 'Failed to add to favorites']);
    }
} 
elseif($action == 'remove'){
    // Remove from favorites
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE client_id = ? AND property_id = ?");
    $stmt->execute([$client_id, $property_id]);
    echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removed from favorites']);
}
else {
    // Toggle
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE client_id = ? AND property_id = ?");
    $stmt->execute([$client_id, $property_id]);
    if($stmt->fetch()){
        // Remove
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE client_id = ? AND property_id = ?");
        $stmt->execute([$client_id, $property_id]);
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removed from favorites']);
    } else {
        // Add
        $stmt = $pdo->prepare("INSERT IGNORE INTO favorites (client_id, property_id) VALUES (?, ?)");
        $stmt->execute([$client_id, $property_id]);
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Added to favorites']);
    }
}
?>