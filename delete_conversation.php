<?php
require 'db_connect.php';

$conversation_id = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : null;
$user_id         = isset($_POST['user_id'])         ? (int)$_POST['user_id']         : null;

if (!$conversation_id || !$user_id) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}



// 1. Identify if the user is the contractor or client for this conversation
$sql = "SELECT contractor_id, client_id FROM conversations WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    $update_sql = "";
    if ($row['contractor_id'] == $user_id) {
        $update_sql = "UPDATE conversations SET cleared_at_contractor = NOW() WHERE id = ?";
    } else if ($row['client_id'] == $user_id) {
        $update_sql = "UPDATE conversations SET cleared_at_client = NOW() WHERE id = ?";
    }

    if (!empty($update_sql)) {
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $conversation_id);
        if ($update_stmt->execute()) {
            // Also optionally clear it for the whole system if BOTH have deleted
            @$conn->query("DELETE FROM conversations WHERE cleared_at_contractor IS NOT NULL AND cleared_at_client IS NOT NULL AND id = $conversation_id");
            echo json_encode(["status" => "success"]);
            exit;
        }
    }
}

echo json_encode(["status" => "error", "message" => "Failed to delete for portal"]);
?>
