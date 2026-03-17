<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

/* AUTH (Robust Implementation) */
$token = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}
$token = trim($token);

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

$contractor_id = $res->fetch_assoc()['id'];

/* TOTAL EXPENSE */
$stmt = $conn->prepare("
    SELECT SUM(pe.amount) AS total_expense
    FROM project_expenses pe
    JOIN projects p ON p.id = pe.project_id
    WHERE p.contractor_id = ?
");
$stmt->bind_param("i", $contractor_id);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total_expense'] ?? 0;

/* PROJECT BREAKDOWN */
$stmt = $conn->prepare("
    SELECT
        p.id AS project_id,
        p.title AS project_title,
        COALESCE(SUM(pe.amount), 0) AS total
    FROM project_expenses pe
    JOIN projects p ON p.id = pe.project_id
    WHERE p.contractor_id = ?
    GROUP BY p.id
");
$stmt->bind_param("i", $contractor_id);
$stmt->execute();

$result = $stmt->get_result();
$projects = [];

while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}

echo json_encode([
    "status" => "success",
    "total_expense" => (float)$total,
    "projects" => $projects
]);

$conn->close();
