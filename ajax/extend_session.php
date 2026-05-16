<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/session_timeout.php';

$response = [];

if (!isset($_SESSION['user_id'])) {
    $response['success'] = false;
    $response['message'] = 'Not logged in';
    echo json_encode($response);
    exit;
}

if (isset($_GET['extend']) && $_GET['extend'] == 1) {
    $_SESSION['last_activity'] = time();
    $response['success'] = true;
    $response['message'] = 'Session extended';
    $response['remaining'] = SESSION_TIMEOUT;
    $response['remaining_formatted'] = get_session_remaining_formatted();
    echo json_encode($response);
    exit;
}

$response['success'] = true;
$response['remaining'] = get_session_remaining();
$response['remaining_formatted'] = get_session_remaining_formatted();
$response['warning_needed'] = session_warning_needed();

echo json_encode($response);
?>