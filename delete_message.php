<?php
require 'db_connect.php';
$message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : null;
$sender_id  = isset($_POST['sender_id'])  ? (int)$_POST['sender_id']  : null;
$delete_for = isset($_POST['delete_for']) ? $_POST['delete_for']       : 'me'; // 'me' or 'everyone'

if (!$message_id || !$sender_id) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

// Convert file_type from ENUM to VARCHAR if needed to support 'deleted' flag
$conn->query("ALTER TABLE messages MODIFY COLUMN file_type VARCHAR(50) DEFAULT 'text'");

if ($delete_for === 'everyone') {
    // Soft-delete for everyone: mark as deleted so both sides see the placeholder
    $sql = "UPDATE messages SET message = '', file_type = 'deleted' WHERE id = ? AND sender_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $message_id, $sender_id);
} else {
    // Delete for me — soft delete
    $sql = "UPDATE messages SET message = '', file_type = 'deleted' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $message_id);
}

if ($stmt->execute()) {
    echo json_encode(["status" => "success"]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}
?>
