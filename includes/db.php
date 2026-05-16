<?php
$http_host  = $_SERVER['HTTP_HOST'];
$is_local   = ($http_host === 'localhost' || $http_host === '127.0.0.1');
$is_render  = str_contains($http_host, 'onrender.com');
$is_ifree   = str_contains($http_host, 'infinityfree');

if($is_local){
    // ── Local XAMPP ──
    $host     = "localhost";
    $dbname   = "transphilhub";
    $username = "root";
    $password = "";
    $port     = "3306";

} elseif($is_render){
    // ── Render.com ──
    $host     = getenv('DB_HOST');
    $dbname   = getenv('DB_NAME');
    $username = getenv('DB_USER');
    $password = getenv('DB_PASS');
    $port     = getenv('DB_PORT') ?: '3306';

} else {
    // ── InfinityFree ──
    $host     = "sql209.infinityfree.com";
    $dbname   = "if0_41911748_transphilhub";
    $username = "if0_41911748";
    $password = "Faye06042005";
    $port     = "3306";
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8",
        $username,
        $password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET time_zone = '+08:00'");
} catch(PDOException $e){
    error_log("DB Error: " . $e->getMessage());
    die("Unable to connect to database. Please try again later.");
}
?>