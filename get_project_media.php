<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode(["status" => "error", "message" => "Only GET allowed"]);
    exit;
}

$project_id = intval($_GET["project_id"] ?? 0);
if ($project_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Missing or invalid project_id"]);
    exit;
}

// Use current server host for full URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST']; // e.g., 10.62.56.234 or 10.0.2.2
$BASE_URL = "$protocol://$host/oct/spic_730/probuilder/";

/*
 TABLE USED: project_media
 COLUMNS EXPECTED:
 - project_id
 - file_path
 - media_type
 - created_at
*/
$stmt = $conn->prepare("
    SELECT file_path, media_type
    FROM project_media
    WHERE project_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

$media = [];

while ($row = $result->fetch_assoc()) {
    $media[] = [
        "url" => $BASE_URL . $row["file_path"],
        "media_type" => $row["media_type"] ?? "photo"
    ];
}

echo json_encode(["status" => "success", "media" => $media]);

$stmt->close();
$conn->close();
