<?php
require 'db_connect.php';

header("Content-Type: application/json");

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : null;

if ($user_id <= 0 || !$user_type) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

/* ===============================
   Fetch conversations
================================ */
if ($user_type === 'client') {

    $sql = "SELECT c.id as conversation_id, c.contractor_id as other_user_id,
                   c.last_message_at, u.name as other_user_name,
                   '' as business_name, c.cleared_at_client as cleared_at
            FROM conversations c
            LEFT JOIN users u ON c.contractor_id = u.id
            WHERE c.client_id = ?
            AND (c.cleared_at_client IS NULL OR c.last_message_at > c.cleared_at_client)
            ORDER BY c.last_message_at DESC";

} else {

    $sql = "SELECT c.id as conversation_id, c.client_id as other_user_id,
                   c.last_message_at, cl.client_name as other_user_name,
                   c.cleared_at_contractor as cleared_at
            FROM conversations c
            LEFT JOIN clients cl ON c.client_id = cl.id
            WHERE c.contractor_id = ?
            AND (c.cleared_at_contractor IS NULL OR c.last_message_at > c.cleared_at_contractor)
            ORDER BY c.last_message_at DESC";
}

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(["status"=>"error","message"=>$conn->error]);
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$conversations = [];

while ($row = $result->fetch_assoc()) {

    $conv_id = intval($row['conversation_id']);
    $cleared_at = $row['cleared_at'];

    /* ===============================
       Fetch last message
    ================================ */

    $msg_sql = "SELECT message, file_type, file_url, is_read, sender_id, created_at
                FROM messages
                WHERE conversation_id = ?";

    if (!empty($cleared_at)) {
        $msg_sql .= " AND created_at > ?";
    }

    $msg_sql .= " ORDER BY created_at DESC LIMIT 1";

    $msg_stmt = $conn->prepare($msg_sql);

    if (!empty($cleared_at)) {
        $msg_stmt->bind_param("is", $conv_id, $cleared_at);
    } else {
        $msg_stmt->bind_param("i", $conv_id);
    }

    $msg_stmt->execute();
    $msg_res = $msg_stmt->get_result();

    $row['last_message'] = "";
    $row['is_read'] = 1;
    $row['unread_count'] = 0;

    if ($msg_res && ($msg_row = $msg_res->fetch_assoc())) {

        /* ---- SAFE VALUES (prevents trim(NULL)) ---- */

        $message  = isset($msg_row['message']) ? trim($msg_row['message']) : '';
        $file_url = isset($msg_row['file_url']) ? trim($msg_row['file_url']) : '';
        $file_type = $msg_row['file_type'] ?? '';

        $hasNoText  = ($message === '');
        $hasNoImage = ($file_url === '');

        if ($file_type === 'deleted' || ($hasNoText && $hasNoImage)) {
            $row['last_message'] = "🚫 This message was deleted";
        } 
        elseif ($hasNoText && !$hasNoImage) {
            $row['last_message'] = "📷 Photo";
        } 
        else {
            $row['last_message'] = $message;
        }

        $row['last_message_time'] = $msg_row['created_at'];
        $row['is_read'] = $msg_row['is_read'];
        $row['last_sender_id'] = $msg_row['sender_id'];
    }

    /* ===============================
       Fetch unread count
    ================================ */

    $unread_sql = "SELECT COUNT(*) as unread
                   FROM messages
                   WHERE conversation_id = ?
                   AND sender_id != ?
                   AND is_read = 0";

    $unread_stmt = $conn->prepare($unread_sql);
    $unread_stmt->bind_param("ii", $conv_id, $user_id);
    $unread_stmt->execute();
    $unread_res = $unread_stmt->get_result();

    if ($unread_row = $unread_res->fetch_assoc()) {
        $row['unread_count'] = intval($unread_row['unread']);
    }

    $conversations[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $conversations
]);

$conn->close();
?>