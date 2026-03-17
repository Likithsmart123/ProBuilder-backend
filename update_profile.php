<?php
include __DIR__ . "/config/db.php";

if (!isset($_POST['user_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "User ID required"
    ]);
    exit;
}

$userId = intval($_POST['user_id']);
$name   = $_POST['name'];
$phone  = $_POST['phone'];

$stmt = $conn->prepare(
    "UPDATE users SET name = ?, phone = ? WHERE id = ?"
);
$stmt->bind_param("ssi", $name, $phone, $userId);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Profile updated"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Update failed"
    ]);
}