<?php
if (!isset($_GET['user_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Unauthorized"
    ]);
    exit;
}

$userId = intval($_GET['user_id']);
?>