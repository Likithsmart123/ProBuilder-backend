<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

/* ---------- AUTH ---------- */
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(["error" => "Authorization missing"]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE api_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

$contractor_id = (int)$res->fetch_assoc()['id'];

/* ---------- INPUT ---------- */
$project_id = (int)($_GET['project_id'] ?? 0);

if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid project id"]);
    exit;
}

/* ---------- FETCH QUOTATIONS (PROJECT-STRICT) ---------- */
$stmt = $conn->prepare("
    SELECT
        id,
        title,
        description,
        amount,
        status,
        created_at
    FROM quotations
    WHERE project_id = ?
      AND contractor_id = ?
    ORDER BY created_at DESC
");

$stmt->bind_param("ii", $project_id, $contractor_id);
$stmt->execute();
$result = $stmt->get_result();

$quotations = [];
while ($row = $result->fetch_assoc()) {
    $quotations[] = $row;
}

echo json_encode([
    "status" => "success",
    "project_id" => $project_id,
    "count" => count($quotations),
    "quotations" => $quotations
]);
