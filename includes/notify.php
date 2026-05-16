<?php

function createNotification($pdo, $user_id, $message, $link = null){

    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            message,
            link
        )
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $user_id,
        $message,
        $link
    ]);
}
?>