<?php
include 'db.php';
header('Content-Type: application/json');

// Require contractor_id to enforce data isolation
$contractor_id = isset($_GET['contractor_id']) ? intval($_GET['contractor_id']) : 0;

if ($contractor_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "contractor_id is required"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        id,
        material_name,
        unit,
        current_stock,
        min_stock
    FROM materials
    WHERE contractor_id = ?
    ORDER BY material_name ASC
");

$stmt->bind_param("i", $contractor_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
$stmt->close();
$conn->close();
