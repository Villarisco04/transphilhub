<?php
require_once '../includes/db.php';
require_once '../includes/notifications.php';
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
    
    // Verify lead belongs to this agent and get property details
    $stmt = $pdo->prepare("
        SELECT l.client_id, l.property_id, p.title as property_title, c.full_name as client_name
        FROM leads l
        JOIN properties p ON l.property_id = p.id
        JOIN users c ON l.client_id = c.id
        WHERE l.id = ? AND l.agent_id = ?
    ");
    $stmt->execute([$lead_id, $_SESSION['user_id']]);
    $lead = $stmt->fetch();
    
    if($lead){
        // Update the lead stage with notes
        $update = $pdo->prepare("
            UPDATE leads 
            SET stage = ?, 
                notes = CONCAT(IFNULL(notes, ''), '\n---\n', DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i'), ' - Agent Update: ', ?) 
            WHERE id = ?
        ");
        $update->execute([$stage, $notes, $lead_id]);
        
        // ============================================
        // SEND NOTIFICATION TO CLIENT
        // ============================================
        
        // Stage descriptions for better notification message
        $stage_messages = [
            'new' => 'received and is being processed',
            'contacted' => 'contacted by our agent',
            'viewing' => 'scheduled for viewing',
            'negotiation' => 'in negotiation phase',
            'closed' => 'completed successfully'
        ];
        
        $message = $stage_messages[$stage] ?? 'updated';
        $notif_message = "Your inquiry for '{$lead['property_title']}' has been {$message}.";
        
        // Add notification for client
        add_notification($lead['client_id'], $notif_message, "client/dashboard.php?lead={$lead_id}");
        
        // Also log the activity
        error_log("Lead #{$lead_id} updated to {$stage} by agent {$_SESSION['user_id']} at " . date('Y-m-d H:i:s'));
        
        $_SESSION['success'] = "Lead stage updated to " . ucfirst($stage) . " successfully!";
    } else {
        $_SESSION['error'] = "You don't have permission to update this lead.";
    }
    
    header("Location: dashboard.php");
    exit;
}
?>