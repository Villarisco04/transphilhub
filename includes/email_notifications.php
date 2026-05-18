<?php
/**
 * Email Notification System
 * Sends automated emails for all important events
 */

// Fix path to phpmailer (now in root folder, not includes)
require_once __DIR__ . '/../phpmailer/Exception.php';
require_once __DIR__ . '/../phpmailer/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Email configuration
$smtp_user = 'iamfaye011@gmail.com';
$smtp_pass = 'zjtmslmoqorhhhxo'; // Your app password

function send_email($to_email, $to_name, $subject, $body) {
    global $smtp_user, $smtp_pass;
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom($smtp_user, 'Trans-Phil House Hub');
        $mail->addAddress($to_email, $to_name);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $mail->ErrorInfo);
        return false;
    }
}

// ============================================
// 1. WELCOME EMAIL (After Registration)
// ============================================
function send_welcome_email($email, $name, $role) {
    $subject = "Welcome to Trans-Phil House Hub! 🏠";
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #1a3a6b, #22508a); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h2 style='color: #fff; margin: 0;'>Trans-Phil House Hub</h2>
            <p style='color: #f07800; margin: 5px 0 0;'>Welcome to the family!</p>
        </div>
        <div style='background: #fff; padding: 30px; border: 1px solid #e4e2ee; border-top: none; border-radius: 0 0 10px 10px;'>
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Thank you for registering with Trans-Phil House Hub! We're excited to have you.</p>
            <p>As a <strong>" . ucfirst($role) . "</strong>, you can now browse properties, save favorites, and submit inquiries.</p>
            <p>Get started by exploring our property listings.</p>
            <hr style='border: 1px solid #e4e2ee; margin: 20px 0;'>
            <p style='color: #6b7280; font-size: 12px;'>Need help? Contact us at info@transphilhouse.com</p>
        </div>
    </div>";
    
    return send_email($email, $name, $subject, $body);
}

// ============================================
// 2. INQUIRY CONFIRMATION EMAIL
// ============================================
function send_inquiry_confirmation_email($email, $name, $property_title) {
    $subject = "Inquiry Received: {$property_title}";
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #1a3a6b, #22508a); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h2 style='color: #fff; margin: 0;'>Inquiry Received</h2>
        </div>
        <div style='background: #fff; padding: 30px; border: 1px solid #e4e2ee; border-top: none; border-radius: 0 0 10px 10px;'>
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Thank you for your interest in <strong>{$property_title}</strong>!</p>
            <p>Your inquiry has been received and one of our agents will contact you within 24 hours.</p>
            <hr style='border: 1px solid #e4e2ee; margin: 20px 0;'>
            <a href='https://transphilhub.infinityfreeapp.com/properties.php' style='background: #f07800; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Browse More Properties</a>
        </div>
    </div>";
    
    return send_email($email, $name, $subject, $body);
}

// ============================================
// 3. LEAD ASSIGNED EMAIL (To Agent)
// ============================================
function send_lead_assigned_email($email, $agent_name, $client_name, $property_title) {
    $subject = "New Lead Assigned - {$property_title}";
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #1a3a6b, #22508a); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h2 style='color: #fff; margin: 0;'>New Lead Assigned</h2>
        </div>
        <div style='background: #fff; padding: 30px; border: 1px solid #e4e2ee; border-top: none; border-radius: 0 0 10px 10px;'>
            <p>Dear Agent <strong>{$agent_name}</strong>,</p>
            <p>A new lead has been assigned to you!</p>
            <div style='background: #eef2f9; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                <p><strong>Client:</strong> {$client_name}</p>
                <p><strong>Property:</strong> {$property_title}</p>
            </div>
            <p>Please contact the client as soon as possible.</p>
            <hr style='border: 1px solid #e4e2ee; margin: 20px 0;'>
            <a href='https://transphilhub.infinityfreeapp.com/agent/dashboard.php' style='background: #f07800; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Dashboard</a>
        </div>
    </div>";
    
    return send_email($email, $agent_name, $subject, $body);
}

// ============================================
// 4. LEAD STAGE UPDATE EMAIL (To Client)
// ============================================
function send_lead_status_email($email, $client_name, $property_title, $new_stage, $notes = '') {
    $stage_messages = [
        'new' => 'received and is being processed',
        'contacted' => 'has been contacted by our agent',
        'viewing' => 'has been scheduled for viewing',
        'negotiation' => 'is in negotiation phase',
        'closed' => 'has been completed successfully! Congratulations!'
    ];
    
    $message = $stage_messages[$new_stage] ?? 'has been updated';
    $subject = "Lead Update: {$property_title} - " . ucfirst($new_stage);
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #1a3a6b, #22508a); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h2 style='color: #fff; margin: 0;'>Lead Status Update</h2>
        </div>
        <div style='background: #fff; padding: 30px; border: 1px solid #e4e2ee; border-top: none; border-radius: 0 0 10px 10px;'>
            <p>Dear <strong>{$client_name}</strong>,</p>
            <p>Your inquiry for <strong>{$property_title}</strong> {$message}.</p>
            " . ($notes ? "<div style='background: #eef2f9; padding: 10px; border-radius: 5px; margin: 10px 0;'><strong>Agent's notes:</strong> {$notes}</div>" : "") . "
            <p>Log in to your dashboard for more details.</p>
            <hr style='border: 1px solid #e4e2ee; margin: 20px 0;'>
            <a href='https://transphilhub.infinityfreeapp.com/client/dashboard.php' style='background: #f07800; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Dashboard</a>
        </div>
    </div>";
    
    return send_email($email, $client_name, $subject, $body);
}

// ============================================
// 5. REVIEW APPROVED EMAIL (To Client)
// ============================================
function send_review_approved_email($email, $client_name, $agent_name) {
    $subject = "Your Review Has Been Published! ⭐";
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #1a3a6b, #22508a); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h2 style='color: #fff; margin: 0;'>Review Published</h2>
        </div>
        <div style='background: #fff; padding: 30px; border: 1px solid #e4e2ee; border-top: none; border-radius: 0 0 10px 10px;'>
            <p>Dear <strong>{$client_name}</strong>,</p>
            <p>Thank you for your feedback! Your review for agent <strong>{$agent_name}</strong> has been approved and published.</p>
            <p>Your feedback helps other clients make informed decisions.</p>
            <hr style='border: 1px solid #e4e2ee; margin: 20px 0;'>
            <a href='https://transphilhub.infinityfreeapp.com/properties.php' style='background: #f07800; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Browse More Properties</a>
        </div>
    </div>";
    
    return send_email($email, $client_name, $subject, $body);
}

// ============================================
// 6. TRANSACTION COMPLETED EMAIL (To Client)
// ============================================
function send_transaction_completed_email($email, $client_name, $property_title, $property_price, $agent_name, $transaction_date, $notes = '') {
    $subject = "🎉 Congratulations! Your Property Transaction is Complete!";
    
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #1a3a6b, #22508a); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h2 style='color: #fff; margin: 0;'>Transaction Completed!</h2>
        </div>
        <div style='background: #fff; padding: 30px; border: 1px solid #e4e2ee; border-top: none; border-radius: 0 0 10px 10px;'>
            <p>Dear <strong>{$client_name}</strong>,</p>
            <p>Congratulations on your new property! 🏠</p>
            <div style='background: #eef2f9; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                <h3 style='color: #1a3a6b;'>Transaction Details</h3>
                <p><strong>Property:</strong> {$property_title}</p>
                <p><strong>Price:</strong> ₱ " . number_format($property_price, 2) . "</p>
                <p><strong>Agent:</strong> {$agent_name}</p>
                <p><strong>Completion Date:</strong> " . date('F d, Y', strtotime($transaction_date)) . "</p>
                " . ($notes ? "<p><strong>Notes:</strong> {$notes}</p>" : "") . "
            </div>
            <p>Thank you for choosing Trans-Phil House Hub!</p>
        </div>
    </div>";
    
    return send_email($email, $client_name, $subject, $body);
}
?>