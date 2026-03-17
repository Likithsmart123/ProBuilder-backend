<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

/* =========================
   AUTH (CLIENT ONLY)
========================= */
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';
error_log("CLIENT TOKEN RECEIVED = " . $token); // DEBUG LOG

if (!$token) {
    http_response_code(401);
    echo json_encode(["error" => "Authorization missing"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, client_name 
    FROM clients 
    WHERE api_token = ?
");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["error" => "Invalid client token"]);
    exit;
}

$client = $res->fetch_assoc();
$client_id = (int)$client['id'];

/* =========================
   FETCH CLIENT QUOTATIONS
========================= */
$stmt = $conn->prepare("
    SELECT
        q.id,
        q.title,
        q.description,
        q.amount,
        q.status,
        q.created_at,

        p.title AS project_title,
        p.location,

        u.name AS contractor_name
    FROM quotations q
    JOIN projects p ON p.id = q.project_id
    JOIN users u ON u.id = q.contractor_id
    WHERE q.client_id = ?
    ORDER BY q.created_at DESC
");

$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

$quotations = [];
while ($row = $result->fetch_assoc()) {
    $quotations[] = $row;
}

echo json_encode([
    "status" => "success",
    "client_id" => $client_id,
    "count" => count($quotations),
    "quotations" => $quotations
]);

$conn->close();
