<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

/* ---------------- AUTH ---------------- */
$token = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}
$token = trim($token);

if ($token === '') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authorization missing"]);
    exit;
}

/* Identify contractor */
$stmt = $conn->prepare("SELECT id FROM users WHERE api_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid token"]);
    exit;
}
$contractor_id = (int)$res->fetch_assoc()['id'];

/* ---------------- INPUT ---------------- */
$project_id = intval($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "project_id is required"]);
    exit;
}

/* ---------------- PROJECT + CLIENT + BUDGET ---------------- */
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.title AS project_name,
        p.location,
        p.status,
        p.overall_progress,
        p.start_date,
        p.end_date,

        c.id AS client_id,
        c.client_name,

        q.amount AS budget

    FROM projects p
    LEFT JOIN clients c ON c.project_id = p.id
    LEFT JOIN quotations q 
        ON q.project_id = p.id
       AND q.status = 'created'

    WHERE p.id = ?
      AND p.contractor_id = ?

    ORDER BY q.created_at DESC
    LIMIT 1
");

$stmt->bind_param("ii", $project_id, $contractor_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Project not found"]);
    exit;
}

$row = $result->fetch_assoc();

/* ---------------- STAGES ---------------- */
$stageStmt = $conn->prepare("
    SELECT id, stage_name, weight, progress
    FROM project_stages
    WHERE project_id = ?
    ORDER BY id ASC
");
$stageStmt->bind_param("i", $project_id);
$stageStmt->execute();
$stageResult = $stageStmt->get_result();

$stages = [];
while ($s = $stageResult->fetch_assoc()) {
    $stages[] = [
        "id" => (int)$s['id'],
        "stage_name" => $s['stage_name'],
        "weight" => (int)$s['weight'],
        "progress" => (int)$s['progress']
    ];
}

/* ---------------- RESPONSE ---------------- */
echo json_encode([
    "status" => "success",
    "project" => [
        "id" => (int)$row['id'],
        "name" => $row['project_name'],
        "location" => $row['location'],
        "status" => $row['status'],
        "overall_progress" => (int)$row['overall_progress'],
        "start_date" => $row['start_date'],
        "end_date" => $row['end_date'],
        "budget" => (float)($row['budget'] ?? 0),

        "client" => $row['client_id'] ? [
            "id" => (int)$row['client_id'],
            "name" => $row['client_name']
        ] : null,

        "stages" => $stages
    ]
]);

$conn->close();
