<?php
// includes/db.php - For Render PostgreSQL

// Get database URL from Render environment
$database_url = getenv('DATABASE_URL');

if (!$database_url) {
    die("DATABASE_URL environment variable not set. Please add it in Render dashboard.");
}

try {
    // Convert postgresql:// to pgsql:// for PDO
    $database_url = str_replace('postgresql://', 'pgsql://', $database_url);
    
    $pdo = new PDO($database_url);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Set timezone to Manila
    $pdo->exec("SET TIME ZONE 'Asia/Manila'");
    
    // Test connection
    $pdo->query("SELECT 1");
    
} catch(PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Unable to connect to database. Please try again later.");
}
?>