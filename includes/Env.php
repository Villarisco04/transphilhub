<?php
/**
 * .env File Loader
 * Loads environment variables from .env file
 */

class Env {
    private static $loaded = false;
    private static $variables = [];
    
    /**
     * Load .env file
     */
    public static function load($path = null) {
        if (self::$loaded) return;
        
        $envFile = $path ?: dirname(__DIR__) . '/.env';
        
        if (!file_exists($envFile)) {
            error_log(".env file not found at: " . $envFile);
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) continue;
            
            // Parse key=value
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                
                // Remove quotes if present
                if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                    $value = substr($value, 1, -1);
                }
                if (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                    $value = substr($value, 1, -1);
                }
                
                self::$variables[$key] = $value;
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable
     */
    public static function get($key, $default = null) {
        $value = getenv($key);
        if ($value !== false) return $value;
        
        return isset(self::$variables[$key]) ? self::$variables[$key] : $default;
    }
    
    /**
     * Check if in production environment
     */
    public static function isProduction() {
        return self::get('APP_ENV') === 'production';
    }
    
    /**
     * Check if in local environment
     */
    public static function isLocal() {
        return self::get('APP_ENV') === 'local';
    }
    
    /**
     * Get database config based on environment
     */
    public static function getDbConfig() {
        if (self::isLocal()) {
            return [
                'host' => self::get('DB_LOCAL_HOST', 'localhost'),
                'name' => self::get('DB_LOCAL_NAME', 'transphilhub'),
                'user' => self::get('DB_LOCAL_USER', 'root'),
                'pass' => self::get('DB_LOCAL_PASS', ''),
                'port' => self::get('DB_LOCAL_PORT', '3306')
            ];
        } else {
            return [
                'host' => self::get('DB_LIVE_HOST', 'sql209.infinityfree.com'),
                'name' => self::get('DB_LIVE_NAME', ''),
                'user' => self::get('DB_LIVE_USER', ''),
                'pass' => self::get('DB_LIVE_PASS', ''),
                'port' => self::get('DB_LIVE_PORT', '3306')
            ];
        }
    }
}
?>