<?php
/**
 * Session Timeout & Security Helper
 * Auto-logout inactive users after 30 minutes
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout in seconds (30 minutes = 1800 seconds)
define('SESSION_TIMEOUT', 1800);

// Time to show warning (25 minutes = 1500 seconds)
define('SESSION_WARNING_TIME', 1500);

/**
 * Check if session has expired
 */
function check_session_timeout() {
    // Only check for logged-in users
    if (!isset($_SESSION['user_id'])) {
        return true;
    }
    
    // Check if last activity is set
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        
        // Session has expired
        if ($inactive_time > SESSION_TIMEOUT) {
            // Clear session
            $_SESSION = array();
            
            // Destroy session cookie
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
            // Destroy session
            session_destroy();
            
            // Redirect to login
            header("Location: login.php?timeout=1");
            exit;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Get remaining session time in seconds
 */
function get_session_remaining() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        return SESSION_TIMEOUT;
    }
    
    $elapsed = time() - $_SESSION['last_activity'];
    $remaining = SESSION_TIMEOUT - $elapsed;
    
    return max(0, $remaining);
}

/**
 * Get remaining session time formatted (MM:SS)
 */
function get_session_remaining_formatted() {
    $remaining = get_session_remaining();
    $minutes = floor($remaining / 60);
    $seconds = $remaining % 60;
    return sprintf("%02d:%02d", $minutes, $seconds);
}

/**
 * Check if session is about to expire (for warning)
 */
function session_warning_needed() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        return false;
    }
    
    $remaining = get_session_remaining();
    return ($remaining <= SESSION_WARNING_TIME && $remaining > 0);
}
?>