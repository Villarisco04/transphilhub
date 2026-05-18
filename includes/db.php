<?php
// includes/db.php - Works with Neon PostgreSQL

// Get database URL from environment
$database_url = getenv('DATABASE_URL');

// If running locally
if (!$database_url) {
    $host = 'localhost';
    $dbname = 'transphilhub';
    $user = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        define('DB_DRIVER', 'mysql');
    } catch(PDOException $e) {
        die("Local DB Error: " . $e->getMessage());
    }
} else {
    // Neon PostgreSQL connection
    try {
        // Convert postgresql:// to pgsql:// for PDO
        $database_url = str_replace('postgresql://', 'pgsql://', $database_url);
        
        $pdo = new PDO($database_url);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        define('DB_DRIVER', 'pgsql');
        
    } catch(PDOException $e) {
        die("Neon DB Error: " . $e->getMessage());
    }
}
?>