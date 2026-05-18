<?php
/**
 * Notification System Helper Functions
 * Complete working version with all notification functions
 */

function get_unread_count($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

function get_notifications($user_id, $limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function mark_as_read($notification_id, $user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$notification_id, $user_id]);
}

function mark_all_as_read($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    return $stmt->execute([$user_id]);
}

function add_notification($user_id, $message, $link = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
    return $stmt->execute([$user_id, $message, $link]);
}

// ============================================
// USER REGISTRATION NOTIFICATIONS
// ============================================

function notify_new_registration($user_id, $user_name) {
    // Send notification to admin (user_id = 1 is typically the main admin)
    $admin_id = 1;
    $message = "New user registered: {$user_name}";
    $link = "admin/users.php";
    add_notification($admin_id, $message, $link);
    
    // Also send a welcome notification to the new user
    $welcome_message = "Welcome to Trans-Phil House Hub, {$user_name}! 🎉 We're excited to have you on board.";
    $welcome_link = "dashboard.php";
    add_notification($user_id, $welcome_message, $welcome_link);
    
    // Log the registration for tracking
    error_log("New user registration notification sent for: {$user_name} (User ID: {$user_id})");
}

// ============================================
// PROPERTY NOTIFICATIONS
// ============================================

function notify_new_property($property_id, $property_title) {
    $admin_id = 1; // Main admin user
    $message = "New property added: {$property_title}";
    $link = "admin/properties.php?edit={$property_id}";
    add_notification($admin_id, $message, $link);
}

function notify_featured_property($property_id, $property_title) {
    $admin_id = 1;
    $message = "Property marked as featured: {$property_title}";
    $link = "admin/properties.php?edit={$property_id}";
    add_notification($admin_id, $message, $link);
}

function notify_property_updated($property_id, $property_title) {
    $admin_id = 1;
    $message = "Property updated: {$property_title}";
    $link = "admin/properties.php?edit={$property_id}";
    add_notification($admin_id, $message, $link);
}

function notify_property_deleted($property_title) {
    $admin_id = 1;
    $message = "Property deleted: {$property_title}";
    $link = "admin/properties.php";
    add_notification($admin_id, $message, $link);
}

// ============================================
// LEAD MANAGEMENT NOTIFICATIONS
// ============================================

function notify_lead_assigned($lead_id, $agent_id, $client_name, $property_title) {
    $message = "New lead assigned: {$client_name} - {$property_title}";
    $link = "agent/dashboard.php?lead={$lead_id}";
    add_notification($agent_id, $message, $link);
}

function notify_stage_updated($lead_id, $client_id, $property_title, $new_stage) {
    $stage_messages = [
        'new' => 'received and is being processed',
        'contacted' => 'been contacted by our agent',
        'viewing' => 'a viewing scheduled',
        'negotiation' => 'in the negotiation phase',
        'closed' => 'completed successfully! Congratulations!'
    ];
    $action = $stage_messages[$new_stage] ?? 'been updated';
    $message = "Your inquiry for {$property_title} has {$action}.";
    $link = "client/dashboard.php?lead={$lead_id}";
    add_notification($client_id, $message, $link);
}

function notify_new_inquiry($lead_id, $admin_id, $client_name, $property_title) {
    $message = "New inquiry from {$client_name} for {$property_title}";
    $link = "admin/leads.php?view={$lead_id}";
    add_notification($admin_id, $message, $link);
}

function notify_lead_created($lead_id, $client_id, $client_name, $property_title) {
    $message = "Your inquiry for {$property_title} has been submitted successfully! Our team will contact you soon.";
    $link = "client/dashboard.php?lead={$lead_id}";
    add_notification($client_id, $message, $link);
}

// ============================================
// REVIEW NOTIFICATIONS
// ============================================

function notify_new_review($review_id, $admin_id, $client_name, $agent_name) {
    $message = "New review from {$client_name} for agent {$agent_name}";
    $link = "admin/reviews.php?view={$review_id}";
    add_notification($admin_id, $message, $link);
}

function notify_review_approved($client_id, $agent_name) {
    $message = "Your review for agent {$agent_name} has been approved and published.";
    $link = "client/dashboard.php?reviews=1";
    add_notification($client_id, $message, $link);
}

function notify_review_rejected($client_id, $agent_name, $reason = null) {
    $message = "Your review for agent {$agent_name} was not approved.";
    if ($reason) {
        $message .= " Reason: {$reason}";
    }
    $link = "client/dashboard.php?reviews=1";
    add_notification($client_id, $message, $link);
}

// ============================================
// APPOINTMENT NOTIFICATIONS
// ============================================

function notify_appointment_scheduled($client_id, $agent_id, $property_title, $appointment_date) {
    // Notify client
    $client_message = "Your viewing appointment for {$property_title} has been scheduled for " . date('F j, Y g:i A', strtotime($appointment_date));
    add_notification($client_id, $client_message, "client/appointments.php");
    
    // Notify agent
    $agent_message = "New appointment scheduled for {$property_title} on " . date('F j, Y g:i A', strtotime($appointment_date));
    add_notification($agent_id, $agent_message, "agent/appointments.php");
}

function notify_appointment_reminder($user_id, $property_title, $appointment_date, $role) {
    $message = "REMINDER: Your appointment for {$property_title} is scheduled for " . date('F j, Y g:i A', strtotime($appointment_date));
    $link = ($role == 'client') ? "client/appointments.php" : "agent/appointments.php";
    add_notification($user_id, $message, $link);
}

// ============================================
// MESSAGE NOTIFICATIONS
// ============================================

function notify_new_message($recipient_id, $sender_name, $message_preview) {
    $message = "New message from {$sender_name}: " . (strlen($message_preview) > 50 ? substr($message_preview, 0, 50) . '...' : $message_preview);
    $link = "messages.php";
    add_notification($recipient_id, $message, $link);
}

// ============================================
// SYSTEM NOTIFICATIONS
// ============================================

function notify_system_maintenance($user_role, $message, $start_time, $end_time) {
    // This would typically notify all users of a specific role
    // Implementation depends on your user management system
    error_log("System maintenance notification: {$message} from {$start_time} to {$end_time}");
}

function notify_error_alert($admin_id, $error_message, $error_location) {
    $message = "System Error: {$error_message} at {$error_location}";
    add_notification($admin_id, $message, "admin/system-logs.php");
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function delete_old_notifications($days = 30) {
    global $pdo;
    
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND is_read = 1");
    return $stmt->execute([$days]);
}

function get_notification_by_id($notification_id, $user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $user_id]);
    return $stmt->fetch();
}

function get_all_unread_count() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT user_id, COUNT(*) as count FROM notifications WHERE is_read = 0 GROUP BY user_id");
    return $stmt->fetchAll();
}

function bulk_add_notifications($user_ids, $message, $link = null) {
    global $pdo;
    
    $success = true;
    foreach ($user_ids as $user_id) {
        if (!add_notification($user_id, $message, $link)) {
            $success = false;
        }
    }
    return $success;
}
?>