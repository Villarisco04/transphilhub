<?php
// includes/db.php - Works with Local XAMPP, Render, InfinityFree, AND Railway!

$http_host  = $_SERVER['HTTP_HOST'];
$is_local   = ($http_host === 'localhost' || $http_host === '127.0.0.1');
$is_render  = str_contains($http_host, 'onrender.com');
$is_railway = str_contains($http_host, 'up.railway.app') || getenv('RAILWAY_ENVIRONMENT') !== false;
$is_ifree   = str_contains($http_host, 'infinityfree');

// Check for Railway's DATABASE_URL first (Railway auto-provides this)
$railway_db_url = getenv('DATABASE_URL');

if($railway_db_url && $is_railway){
    // ── Railway.app (PostgreSQL) ──
    try {
        // Convert postgresql:// to pgsql:// for PDO
        $db_url = str_replace('postgresql://', 'pgsql://', $railway_db_url);
        $pdo = new PDO($db_url);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET TIME ZONE 'Asia/Manila'");
        
        // Mark that we're using PostgreSQL (for any PostgreSQL-specific queries)
        define('DB_DRIVER', 'pgsql');
        
    } catch(PDOException $e){
        error_log("Railway DB Error: " . $e->getMessage());
        die("Unable to connect to database. Please try again later.");
    }
    
} elseif($is_local){
    // ── Local XAMPP (MySQL) ──
    $host     = "localhost";
    $dbname   = "transphilhub";
    $username = "root";
    $password = "";
    $port     = "3306";
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8",
            $username,
            $password
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET time_zone = '+08:00'");
        
        define('DB_DRIVER', 'mysql');
        
    } catch(PDOException $e){
        error_log("Local DB Error: " . $e->getMessage());
        die("Unable to connect to database. Please try again later.");
    }

} elseif($is_render){
    // ── Render.com (MySQL) ──
    $host     = getenv('DB_HOST');
    $dbname   = getenv('DB_NAME');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASS');
    $port     = getenv('DB_PORT') ?: '3306';
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8",
            $username,
            $password
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET time_zone = '+08:00'");
        
        define('DB_DRIVER', 'mysql');
        
    } catch(PDOException $e){
        error_log("Render DB Error: " . $e->getMessage());
        die("Unable to connect to database. Please try again later.");
    }

} elseif($is_ifree){
    // ── InfinityFree (MySQL) ──
    $host     = "sql209.infinityfree.com";
    $dbname   = "if0_41911748_transphilhub";
    $username = "if0_41911748";
    $password = "Faye06042005";
    $port     = "3306";
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8",
            $username,
            $password
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET time_zone = '+08:00'");
        
        define('DB_DRIVER', 'mysql');
        
    } catch(PDOException $e){
        error_log("InfinityFree DB Error: " . $e->getMessage());
        die("Unable to connect to database. Please try again later.");
    }
    
} else {
    // ── Unknown Environment / Fallback to MySQL ──
    $host     = getenv('DB_HOST') ?: 'localhost';
    $dbname   = getenv('DB_NAME') ?: 'transphilhub';
    $username = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';
    $port     = getenv('DB_PORT') ?: '3306';
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8",
            $username,
            $password
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("SET time_zone = '+08:00'");
        
        define('DB_DRIVER', 'mysql');
        
    } catch(PDOException $e){
        error_log("Unknown DB Error: " . $e->getMessage());
        die("Unable to connect to database. Please try again later.");
    }
}

// Optional: Helper function to check if using PostgreSQL
function isPostgreSQL() {
    return defined('DB_DRIVER') && DB_DRIVER === 'pgsql';
}

// Optional: Helper function to get database type
function getDBDriver() {
    return defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
}
?>