<?php
require 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);
$conversation_id = isset($_POST['conversation_id']) ? $_POST['conversation_id'] : (isset($data['conversation_id']) ? $data['conversation_id'] : null);
$user_id = isset($_POST['user_id']) ? $_POST['user_id'] : (isset($data['user_id']) ? $data['user_id'] : null);

if (!$conversation_id || !$user_id) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

// Mark messages as read where sender is NOT the current user
$sql = "UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $conversation_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Messages marked as read"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update read status"]);
}
?>
