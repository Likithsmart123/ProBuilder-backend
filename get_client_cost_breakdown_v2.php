<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

/* AUTH: Check CLIENTS table */
$token = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
}
$token = trim($token);

if ($token === '') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authorization missing"]);
    exit;
}

// 1. Get Client & Project ID from Token
$stmt = $conn->prepare("SELECT id, project_id FROM clients WHERE api_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid token"]);
    exit;
}

$client = $res->fetch_assoc();
$project_id = (int)$client['project_id'];

if ($project_id <= 0) {
    echo json_encode(["status" => "error", "message" => "No project assigned"]);
    exit;
}

/* 2. FETCH BUDGET FROM QUOTATIONS (Source of Truth) */
// "Client Budget = SUM(quotation.amount)"
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(amount), 0) AS budget 
    FROM quotations 
    WHERE project_id = ?
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$quotation = $stmt->get_result()->fetch_assoc();
$budget = (float)$quotation['budget'];

/* 3. FETCH SPENT */
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(amount), 0) AS spent
    FROM project_expenses
    WHERE project_id = ?
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$spent = (float)$stmt->get_result()->fetch_assoc()['spent'];

/* 4. CALCS (No Negatives) */
$remaining = max($budget - $spent, 0);

$utilization = ($budget > 0)
    ? round(($spent / $budget) * 100, 2)
    : 0;

/* 5. BREAKDOWN */
$stmt = $conn->prepare("
    SELECT category, SUM(amount) AS total
    FROM project_expenses
    WHERE project_id = ?
    GROUP BY category
");
$stmt->bind_param("i", $project_id);
$stmt->execute();

$breakdown = [];
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) {
    $breakdown[] = [
        "category" => $row['category'],
        "total"   => (float)$row['total']
    ];
}

/* 6. RESPONSE */
echo json_encode([
    "status"       => "success",
    "project_id"   => $project_id,
    "budget"       => $budget,
    "source"       => "quotations_sum", // Debug info
    "spent"        => $spent,
    "remaining"    => $remaining,
    "utilization"  => $utilization,
    "breakdown"    => $breakdown
]);

$conn->close();
?>
