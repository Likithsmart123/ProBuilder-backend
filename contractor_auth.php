<?php
session_start();

if (!isset($_SESSION['contractor_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'contractor') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit;
}
?>
