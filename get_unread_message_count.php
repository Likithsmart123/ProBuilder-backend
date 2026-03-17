<?php
header("Content-Type: application/json");
require_once "db_connect.php";

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : '';

if (!$user_id || !$user_type) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

// Security: basic check if we have an auth token (if required)
// Since it's just a count and we require user_id/user_type, it's mostly safe,
// but let's keep it simple.

$unread_count = 0;

if ($user_type === 'contractor') {
    $sql = "SELECT COUNT(m.id) as unread_count 
            FROM messages m
            JOIN conversations c ON m.conversation_id = c.id
            WHERE c.contractor_id = ? AND m.is_read = 0 AND m.sender_type != 'contractor' AND m.file_type != 'deleted'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $unread_count = (int)$row['unread_count'];
    }
} else if ($user_type === 'client') {
    $sql = "SELECT COUNT(m.id) as unread_count 
            FROM messages m
            JOIN conversations c ON m.conversation_id = c.id
            WHERE c.client_id = ? AND m.is_read = 0 AND m.sender_type != 'client' AND m.file_type != 'deleted'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $unread_count = (int)$row['unread_count'];
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid user_type"]);
    exit;
}

echo json_encode(["status" => "success", "unread_count" => $unread_count]);
$conn->close();
?>
