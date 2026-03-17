<?php
require 'db_connect.php';

$conversation_id  = isset($_GET['conversation_id'])  ? (int)$_GET['conversation_id']  : null;
$other_user_id    = isset($_GET['other_user_id'])     ? (int)$_GET['other_user_id']     : null;
$other_user_type  = isset($_GET['other_user_type'])   ? $_GET['other_user_type']        : null;

if (!$conversation_id || !$other_user_id || !$other_user_type) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

// Fetch contact info based on type
if ($other_user_type === 'client') {
    $sql = "SELECT client_name AS name, phone, email, '' AS profile_photo FROM clients WHERE id = ?";
} else {
    // Contractors store phone in the users table, link via user_id
    $sql = "SELECT c.name, u.phone, c.email, '' AS profile_photo 
            FROM contractors c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.id = ?";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $other_user_id);
$stmt->execute();
$result = $stmt->get_result();
$contact = $result->fetch_assoc();

if (!$contact) {
    echo json_encode(["status" => "error", "message" => "Contact not found"]);
    exit;
}

// Fetch shared media (images) from the conversation
// Use file_type column (actual DB column name)
$media_sql = "SELECT CONCAT('http://', ?, '/oct/spic_730/probuilder/', file_url) as file_url, created_at FROM messages
              WHERE conversation_id = ? AND file_type = 'image' AND file_url IS NOT NULL
              ORDER BY created_at DESC LIMIT 30";
$media_stmt = $conn->prepare($media_sql);
$host = $_SERVER['HTTP_HOST'];
$media_stmt->bind_param("si", $host, $conversation_id);
$media_stmt->execute();
$media_result = $media_stmt->get_result();

$media = [];
while ($row = $media_result->fetch_assoc()) {
    $media[] = $row;
}

echo json_encode([
    "status"  => "success",
    "contact" => $contact,
    "media"   => $media
]);
?>
