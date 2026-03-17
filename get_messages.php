<?php
require 'db_connect.php';

$conversation_id = isset($_GET['conversation_id']) ? $_GET['conversation_id'] : null;
$last_msg_id     = isset($_GET['last_msg_id'])     ? (int)$_GET['last_msg_id'] : 0;
$user_id         = isset($_GET['user_id'])         ? (int)$_GET['user_id'] : null;

if (!$conversation_id) {
    echo json_encode(["status" => "error", "message" => "Missing conversation_id"]);
    exit;
}

// Get cleared_at timestamp for this user to hide old messages if they deleted the chat history
$cleared_at = '1970-01-01 00:00:00';
if ($user_id) {
    $c_sql = "SELECT contractor_id, client_id, cleared_at_contractor, cleared_at_client FROM conversations WHERE id = ?";
    $c_stmt = $conn->prepare($c_sql);
    $c_stmt->bind_param("i", $conversation_id);
    $c_stmt->execute();
    $c_res = $c_stmt->get_result();
    if ($c_row = $c_res->fetch_assoc()) {
        if ($c_row['contractor_id'] == $user_id && !empty($c_row['cleared_at_contractor'])) {
            $cleared_at = $c_row['cleared_at_contractor'];
        } else if ($c_row['client_id'] == $user_id && !empty($c_row['cleared_at_client'])) {
            $cleared_at = $c_row['cleared_at_client'];
        }
    }
}

$has_reply_col = true;

if ($has_reply_col) {
    $sql = "SELECT m.id, m.conversation_id, m.sender_id, m.sender_type,
                   m.message, m.file_type AS message_type,
                   CONCAT('http://', ?, '/oct/spic_730/probuilder/', m.file_url) as file_url, m.is_read, m.created_at, m.reply_to_message_id,
                   rm.message AS reply_to_text, rm.file_type AS reply_to_type
            FROM messages m
            LEFT JOIN messages rm ON m.reply_to_message_id = rm.id
            WHERE m.conversation_id = ? AND m.id > ? AND m.created_at > '$cleared_at'
            ORDER BY m.created_at ASC";
} else {
    $sql = "SELECT id, conversation_id, sender_id, sender_type,
                   message, file_type AS message_type,
                   CONCAT('http://', ?, '/oct/spic_730/probuilder/', file_url) as file_url, is_read, created_at,
                   0 AS reply_to_message_id, '' AS reply_to_text, '' AS reply_to_type
            FROM messages
            WHERE conversation_id = ? AND id > ? AND created_at > '$cleared_at'
            ORDER BY created_at ASC";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "SQL Prepare Error: " . $conn->error]);
    exit;
}
$host = $_SERVER['HTTP_HOST'];
$stmt->bind_param("sii", $host, $conversation_id, $last_msg_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

// Fetch deleted message IDs to allow the client app to sync deletions live without reloading
$deleted_sql = "SELECT id FROM messages WHERE conversation_id = ? AND file_type = 'deleted'";
$del_stmt = $conn->prepare($deleted_sql);
$del_stmt->bind_param("i", $conversation_id);
$del_stmt->execute();
$del_result = $del_stmt->get_result();

$deleted_ids = [];
while ($del_row = $del_result->fetch_assoc()) {
    $deleted_ids[] = $del_row['id'];
}

// Fetch the maximum read ID sent by the requesting user
$last_read_id = 0;
if ($user_id) {
    $read_sql = "SELECT MAX(id) as max_id FROM messages WHERE conversation_id = ? AND sender_id = ? AND is_read = 1";
    $read_stmt = $conn->prepare($read_sql);
    $read_stmt->bind_param("ii", $conversation_id, $user_id);
    $read_stmt->execute();
    $read_res = $read_stmt->get_result();
    if ($read_row = $read_res->fetch_assoc()) {
        $last_read_id = $read_row['max_id'] ? (int)$read_row['max_id'] : 0;
    }
}

echo json_encode([
    "status" => "success",
    "data" => $messages,
    "deleted_ids" => $deleted_ids,
    "last_read_id" => $last_read_id
]);
?>
