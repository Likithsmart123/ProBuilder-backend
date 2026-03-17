<?php
include 'db.php';
header('Content-Type: application/json');

$work_log_id = intval($_GET['work_log_id'] ?? 0);
if ($work_log_id <= 0) {
    echo json_encode([]);
    exit;
}

$sql = "
    SELECT file_path, media_type
    FROM project_media
    WHERE work_log_id = ?
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $work_log_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$conn->close();
