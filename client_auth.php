<?php
session_start();

if (!isset($_SESSION['client_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}
?>
