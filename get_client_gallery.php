<?php
error_reporting(0); 
ini_set('display_errors', 0);
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;

if ($client_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Client ID"]);
    exit;
}

// Use current server host for full URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost'; // Fallback if undefined
$BASE_URL = "$protocol://$host/oct/spic_730/probuilder/";

// Fetch all media from projects owned by this client
// We join project_media -> projects to filter by client_id
$stmt = $conn->prepare("
    SELECT 
        pm.file_path, 
        pm.media_type, 
        pm.created_at,
        p.title as project_name
    FROM project_media pm
    JOIN projects p ON pm.project_id = p.id
    WHERE 
        p.client_id = ? 
        OR 
        p.id IN (SELECT project_id FROM clients WHERE id = ?)
    ORDER BY pm.created_at DESC
");

if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    exit;
}

$stmt->bind_param("ii", $client_id, $client_id);
$stmt->execute();
$result = $stmt->get_result();

$mediaList = [];

while ($row = $result->fetch_assoc()) {
    $mediaList[] = [
        "url" => $BASE_URL . $row["file_path"],
        "media_type" => $row["media_type"] ?? "photo",
        "project_name" => $row["project_name"],
        "created_at" => $row["created_at"]
    ];
}

echo json_encode([
    "status" => "success",
    "data" => $mediaList
]);

$stmt->close();
$conn->close();
?>
