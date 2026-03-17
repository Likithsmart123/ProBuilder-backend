<?php
include __DIR__ . "/config/db.php";

if (!isset($_GET['user_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "User ID required"
    ]);
    exit;
}

$userId = intval($_GET['user_id']);

$stmt = $conn->prepare(
    "SELECT id, name, email, phone, role, profile_image, created_at
     FROM users WHERE id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode([
        "status" => "error",
        "message" => "User not found"
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "profile" => $result->fetch_assoc()
]);