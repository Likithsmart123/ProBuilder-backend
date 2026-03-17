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
$userRes = $stmt->get_result();

if ($userRes->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid token"]);
    exit;
}

$user = $userRes->fetch_assoc();
$contractor_id = (int)$user['id'];

/* ---------- INPUT ---------- */
$data = json_decode(file_get_contents("php://input"), true);

$project_id  = (int)($data['project_id'] ?? 0);
$title       = trim($data['title'] ?? '');
$description = trim($data['description'] ?? '');
$amount      = (float)($data['amount'] ?? 0);

if ($project_id <= 0 || $title === '' || $amount <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

/* ---------- FETCH CLIENT FROM PROJECT ---------- */
$stmt = $conn->prepare("
    SELECT client_id 
    FROM projects 
    WHERE id = ? AND contractor_id = ?
");
$stmt->bind_param("ii", $project_id, $contractor_id);
$stmt->execute();
$projRes = $stmt->get_result();

if ($projRes->num_rows === 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid project"]);
    exit;
}

$project = $projRes->fetch_assoc();
$client_id = (int)$project['client_id'];

/* ---------- INSERT QUOTATION ---------- */
$stmt = $conn->prepare("
    INSERT INTO quotations
    (contractor_id, client_id, project_id, title, description, amount)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param(
    "iiissd",
    $contractor_id,
    $client_id,
    $project_id,
    $title,
    $description,
    $amount
);

$stmt->execute();

echo json_encode([
    "status" => "success",
    "quotation_id" => $stmt->insert_id
]);
