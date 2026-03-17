<?php
require 'db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$conversation_id = isset($_POST['conversation_id']) ? $_POST['conversation_id'] : (isset($data['conversation_id']) ? $data['conversation_id'] : null);
$sender_id       = isset($_POST['sender_id'])       ? $_POST['sender_id']       : (isset($data['sender_id'])       ? $data['sender_id']       : null);
$sender_type     = isset($_POST['sender_type'])     ? $_POST['sender_type']     : (isset($data['sender_type'])     ? $data['sender_type']     : 'contractor');
$message         = isset($_POST['message'])         ? $_POST['message']         : (isset($data['message'])         ? $data['message']         : '');
$file_type       = isset($_POST['message_type'])    ? $_POST['message_type']    : (isset($data['message_type'])   ? $data['message_type']    : 'text');
$file_url        = isset($_POST['file_url'])        ? $_POST['file_url']        : (isset($data['file_url'])        ? $data['file_url']        : null);
$reply_to_msg_id = isset($_POST['reply_to_message_id']) ? (int)$_POST['reply_to_message_id'] : 0;

if (!$conversation_id || !$sender_id) {
    echo json_encode(["status" => "error", "message" => "Missing required parameters"]);
    exit;
}

// Handle base64 image upload
$image_data = isset($_POST['image_data']) ? $_POST['image_data'] : (isset($data['image_data']) ? $data['image_data'] : null);

if ($image_data) {
    $target_dir = "uploads/chat_media/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

    $file_extension = "jpg";
    $new_filename   = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file    = $target_dir . $new_filename;

    if (file_put_contents($target_file, base64_decode($image_data))) {
        $file_url = $target_file; // Store relative path
    } else {
        echo json_encode(["status" => "error", "message" => "Base64 image upload failed"]);
        exit;
    }
} else if (isset($_FILES['file'])) {
    $target_dir = "uploads/chat_media/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

    $file_extension = pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
    $new_filename   = uniqid() . '_' . time() . '.' . $file_extension;
    $target_file    = $target_dir . $new_filename;

    if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
        $file_url = $target_file; // Store relative path
    } else {
        echo json_encode(["status" => "error", "message" => "File upload failed"]);
        exit;
    }
}

// Check if reply_to_message_id column exists (MySQL 5.7 compatible)
$has_reply_col = false;
$col_check = $conn->query("SHOW COLUMNS FROM messages LIKE 'reply_to_message_id'");
if ($col_check && $col_check->num_rows > 0) {
    $has_reply_col = true;
} else {
    @$conn->query("ALTER TABLE messages ADD COLUMN reply_to_message_id INT DEFAULT NULL");
    $col_check2 = $conn->query("SHOW COLUMNS FROM messages LIKE 'reply_to_message_id'");
    if ($col_check2 && $col_check2->num_rows > 0) $has_reply_col = true;
}

if ($has_reply_col && $reply_to_msg_id > 0) {
    $sql  = "INSERT INTO messages (conversation_id, sender_id, sender_type, message, file_url, file_type, reply_to_message_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissssi", $conversation_id, $sender_id, $sender_type, $message, $file_url, $file_type, $reply_to_msg_id);
} else {
    $sql  = "INSERT INTO messages (conversation_id, sender_id, sender_type, message, file_url, file_type) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissss", $conversation_id, $sender_id, $sender_type, $message, $file_url, $file_type);
}

if ($stmt->execute()) {
    $new_id = $conn->insert_id;
    $upd = $conn->prepare("UPDATE conversations SET last_message_at = CURRENT_TIMESTAMP WHERE id = ?");
    $upd->bind_param("i", $conversation_id);
    $upd->execute();
    echo json_encode(["status" => "success", "message_id" => $new_id, "file_url" => $file_url]);
} else {
    echo json_encode(["status" => "error", "message" => $conn->error]);
}
?>
