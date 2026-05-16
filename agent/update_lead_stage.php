<?php
require_once '../includes/db.php';
session_start();

// Check if user is logged in and is agent
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'agent'){
    header("Location: ../login.php");
    exit;
}

if(isset($_POST['update_stage'])){
    $lead_id = (int)$_POST['lead_id'];
    $stage = $_POST['stage'];
    $notes = trim($_POST['notes']);
    
    // Verify lead belongs to this agent
    $stmt = $pdo->prepare("SELECT client_id, property_id FROM leads WHERE id = ? AND agent_id = ?");
    $stmt->execute([$lead_id, $_SESSION['user_id']]);
    $lead = $stmt->fetch();
    
    if($lead){
        // Update the lead stage
        $update = $pdo->prepare("UPDATE leads SET stage = ?, notes = CONCAT(IFNULL(notes, ''), '\n---\n', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i'), ' - Agent Update: ', ?) WHERE id = ?");
        $update->execute([$stage, $notes, $lead_id]);
        
        // Add notification for client
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notif->execute([$lead['client_id'], "Your inquiry status has been updated to: " . ucfirst($stage)]);
        
        $_SESSION['success'] = "Lead stage updated successfully!";
    } else {
        $_SESSION['error'] = "You don't have permission to update this lead.";
    }
    
    header("Location: dashboard.php");
    exit;
}
?>