<?php
header('Content-Type: application/json');
require_once "db.php";

$clientId = $_POST['client_id'] ?? '';
$name = $_POST['name'] ?? '';
$phone = $_POST['phone'] ?? '';

if (!$clientId || !$name) {
    echo json_encode(["status"=>"error","message"=>"Missing required fields"]);
    exit;
}

$stmt = $conn->prepare("UPDATE clients SET client_name = ?, phone = ? WHERE id = ?");
$stmt->bind_param("ssi", $name, $phone, $clientId);

if ($stmt->execute()) {
    echo json_encode(["status"=>"success", "message"=>"Profile updated successfully"]);
} else {
    echo json_encode(["status"=>"error", "message"=>"Update failed"]);
}
?>
