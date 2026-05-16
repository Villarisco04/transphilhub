<?php
/**
 * CSRF Protection Helper
 * Prevents Cross-Site Request Forgery attacks
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class CSRFToken {
    
    /**
     * Generate a new CSRF token
     */
    public static function generate() {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        // Generate random token
        $token = bin2hex(random_bytes(32));
        
        // Store with timestamp (expires after 1 hour)
        $_SESSION['csrf_tokens'][$token] = time();
        
        // Clean up old tokens (older than 1 hour)
        foreach ($_SESSION['csrf_tokens'] as $key => $timestamp) {
            if ($timestamp < (time() - 3600)) {
                unset($_SESSION['csrf_tokens'][$key]);
            }
        }
        
        return $token;
    }
    
    /**
     * Verify CSRF token
     */
    public static function verify($token) {
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }
        
        // Check if token is expired (1 hour)
        if ($_SESSION['csrf_tokens'][$token] < (time() - 3600)) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        
        // Token is valid, remove it (one-time use)
        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }
    
    /**
     * Get CSRF token HTML input field
     */
    public static function input() {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
}
?>