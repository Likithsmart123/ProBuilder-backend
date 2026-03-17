<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

/* AUTH */
$token = '';

if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

$token = trim($token);

if ($token === '') {
    http_response_code(401);
    echo json_encode(["error" => "Authorization missing"]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM clients WHERE api_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["error"=>"Invalid token"]);
    exit;
}

/* INPUT */
$project_id = intval($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(["error"=>"Invalid project id"]);
    exit;
}

/* PROJECT DETAILS */
$stmt = $conn->prepare("
    SELECT
        id,
        title AS project_name,
        start_date,
        end_date,
        location,
        status,
        overall_progress,
        budget
    FROM projects
    WHERE id = ?
");

$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if ($project) {
    // Override budget with Total Quotations Amount
    $stmt_q = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_quote FROM quotations WHERE project_id = ?");
    $stmt_q->bind_param("i", $project_id);
    $stmt_q->execute();
    $res_q = $stmt_q->get_result()->fetch_assoc();
    $project['budget'] = floatval($res_q['total_quote']);
    $stmt_q->close();
}

/* PROJECT MATERIALS */
$stmt = $conn->prepare("
    SELECT
        m.material_name,
        m.unit,
        SUM(wlm.used_quantity) AS total_used
    FROM work_log_materials wlm
    JOIN work_logs wl ON wl.id = wlm.work_log_id
    JOIN materials m ON m.id = wlm.material_id
    WHERE wl.project_id = ?
    GROUP BY m.id
");
$stmt->bind_param("i", $project_id);
$stmt->execute();

$res = $stmt->get_result();
$materials = [];

while ($row = $res->fetch_assoc()) {
    $materials[] = $row;
}

echo json_encode([
    "status" => "success",
    "project" => $project,
    "materials" => $materials
]);
