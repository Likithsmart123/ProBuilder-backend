<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

/* AUTH */
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"Authorization missing"]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE api_token = ? AND role = 'contractor'");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"Invalid token"]);
    exit;
}

$contractor_id = (int)$res->fetch_assoc()['id'];

/* FETCH ALL QUOTATIONS FOR CONTRACTOR */
$stmt = $conn->prepare("
    SELECT
        q.id,
        q.title,
        q.amount,
        q.status,
        q.created_at,
        q.description,
        q.project_id,
        q.client_id,
        p.title AS project_title,
        c.client_name
    FROM quotations q
    LEFT JOIN projects p ON p.id = q.project_id
    LEFT JOIN clients c ON c.id = p.client_id
    WHERE q.contractor_id = ?
    ORDER BY q.created_at DESC
");
$stmt->bind_param("i", $contractor_id);
$stmt->execute();

$result = $stmt->get_result();
$quotations = [];

while ($row = $result->fetch_assoc()) {
    $quotations[] = [
        "quotation_id" => (int)$row["id"],
        "project_id" => (int)$row["project_id"],
        "title" => $row["title"],
        "amount" => (float)$row["amount"],
        "status" => $row["status"],
        "project_title" => $row["project_title"] ?? "Unknown Project",
        "client_name" => $row["client_name"] ?? "Unknown Client",
        "created_at" => $row["created_at"],
        "description" => $row["description"] ?? ""
    ];
}

echo json_encode([
    "status" => "success",
    "count" => count($quotations),
    "quotations" => $quotations
]);
