<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

// Only GET allowed
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Only GET method allowed"
    ]);
    exit;
}

// Validate project_id
$project_id = $_GET["project_id"] ?? null;

if (!$project_id) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "project_id is required"
    ]);
    exit;
}

// CORRECT SQL — USES REAL COLUMNS
$sql = "
    SELECT
        id AS expense_id,
        amount,
        category,
        title,
        expense_date,
        description,
        invoice_no
    FROM project_expenses
    WHERE project_id = ?
    ORDER BY expense_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];

while ($row = $result->fetch_assoc()) {
    $expenses[] = [
        "expense_id" => (int)$row["expense_id"],
        "amount" => $row["amount"],
        "category" => $row["category"],
        "title" => $row["title"],
        "expense_date" => $row["expense_date"],
        "description" => $row["description"],
        "invoice_no" => $row["invoice_no"]
    ];
}

echo json_encode([
    "status" => "success",
    "count" => count($expenses),
    "expenses" => $expenses
]);

$stmt->close();
$conn->close();
