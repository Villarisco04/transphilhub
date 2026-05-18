<?php
// includes/db.php - Neon PostgreSQL Connection

$database_url = getenv('DATABASE_URL');

// For local testing (XAMPP)
if (!$database_url && ($_SERVER['HTTP_HOST'] ?? '') === 'localhost') {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=transphilhub", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        die("Local DB Error: " . $e->getMessage());
    }
} 
// For Render with Neon
elseif ($database_url) {
    try {
        // Convert postgresql:// to pgsql:// for PDO
        $db_url = str_replace('postgresql://', 'pgsql://', $database_url);
        
        // Add SSL requirement for Neon
        if (strpos($db_url, 'sslmode') === false) {
            $db_url .= '?sslmode=require';
        }
        
        $pdo = new PDO($db_url);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
    } catch(PDOException $e) {
        die("Neon DB Error: " . $e->getMessage());
    }
} 
else {
    die("DATABASE_URL not set. Please add it in Render environment variables.");
}
?>