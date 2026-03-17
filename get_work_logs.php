<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

$project_id = intval($_GET['project_id'] ?? 0);

if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"project_id required"]);
    exit;
}

$logs = [];
$logRes = $conn->query("
    SELECT id, work_date, summary, progress_update
    FROM work_logs
    WHERE project_id = $project_id
    ORDER BY work_date DESC, id DESC
");

while ($log = $logRes->fetch_assoc()) {

    $images = [];
    $imgStmt = $conn->prepare("
        SELECT file_path 
        FROM project_media 
        WHERE work_log_id = ? AND media_type = 'photo'
    ");
    $imgStmt->bind_param("i", $log['id']);
    $imgStmt->execute();
    $imgRes = $imgStmt->get_result();

    while ($img = $imgRes->fetch_assoc()) {
        // Use current server host for full URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = "$protocol://$host/oct/spic_730/probuilder/";
        
        // Fix: DB now stores 'uploads/work_logs/xyz.jpg'
        $images[] = $baseUrl . $img['file_path'];
    }

    $logs[] = [
        "id" => (int)$log['id'],
        "work_date" => $log['work_date'],
        "summary" => $log['summary'],
        "progress_update" => (int)$log['progress_update'],
        "images" => $images
    ];
}

echo json_encode([
    "status"=>"success",
    "count"=>count($logs),
    "logs"=>$logs
]);

$conn->close();
