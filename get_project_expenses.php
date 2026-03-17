<?php
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

if (!$token) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authorization missing"]);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE api_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid token"]);
    exit;
}

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($project_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Project ID"]);
    exit;
}

/* 1. Get Total Expense */
$stmtTotal = $conn->prepare("SELECT SUM(amount) AS total FROM project_expenses WHERE project_id = ?");
$stmtTotal->bind_param("i", $project_id);
$stmtTotal->execute();
$totalRes = $stmtTotal->get_result()->fetch_assoc();
$total = $totalRes['total'] ?? 0;

/* 2. Get Detailed List (Recommended Option) */
$stmt = $conn->prepare("
    SELECT
        category,
        title,
        description,
        invoice_no,
        amount,
        expense_date
    FROM project_expenses
    WHERE project_id = ?
    ORDER BY expense_date DESC
");

$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
}

echo json_encode([
    "status" => "success",
    "total" => (float)$total,
    "expenses" => $expenses
]);

$conn->close();
