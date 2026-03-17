<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

/* ---------------------------------------
   1. METHOD + PARAM VALIDATION
--------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Only GET method allowed"
    ]);
    exit;
}

$project_id = $_GET["project_id"] ?? null;

if (!$project_id) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "project_id is required"
    ]);
    exit;
}

/* ---------------------------------------
   2. PROJECT + CLIENT
--------------------------------------- */
$stmt = $conn->prepare("
    SELECT
        p.id AS project_id,
        p.title,
        p.location,
        p.status,
        p.start_date,
        p.end_date,
        p.budget,
        p.created_at,
        c.id AS client_id,
        c.client_name,
        c.email,
        c.phone
    FROM projects p
    LEFT JOIN clients c ON c.project_id = p.id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Project not found"
    ]);
    exit;
}

$project = $res->fetch_assoc();
$budget = (float)($project["budget"] ?? 0);

/* ---------------------------------------
   3. FINANCIALS: PAYMENTS & EXPENSES
--------------------------------------- */
// Fetch Total Paid (from project_payments)
$stmt_pay = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_paid FROM project_payments WHERE project_id = ?");
$stmt_pay->bind_param("i", $project_id);
$stmt_pay->execute();
$total_paid = (float)$stmt_pay->get_result()->fetch_assoc()["total_paid"];

// Fetch Total Expenses (from project_expenses)
$stmt_exp = $conn->prepare("SELECT COALESCE(SUM(amount), 0) AS total_expenses FROM project_expenses WHERE project_id = ?");
$stmt_exp->bind_param("i", $project_id);
$stmt_exp->execute();
$total_expenses = (float)$stmt_exp->get_result()->fetch_assoc()["total_expenses"];

/* ---------------------------------------
   4. STAGES + WEIGHTED PROGRESS
--------------------------------------- */
$stmt_stage = $conn->prepare("
    SELECT id, stage_name, weight, progress
    FROM project_stages
    WHERE project_id = ?
    ORDER BY id ASC
");
$stmt_stage->bind_param("i", $project_id);
$stmt_stage->execute();
$res_stage = $stmt_stage->get_result();

$stages = [];
$total_weight = 0;
$weighted_sum = 0;

while ($row = $res_stage->fetch_assoc()) {
    $weight = (int)$row["weight"];
    $progress = (int)$row["progress"];

    $total_weight += $weight;
    $weighted_sum += ($progress * $weight);

    $stages[] = [
        "id" => (int)$row["id"],
        "stage_name" => $row["stage_name"],
        "weight" => $weight,
        "progress" => $progress
    ];
}

$overall_progress = 0;
if ($total_weight > 0) {
    $overall_progress = round($weighted_sum / $total_weight);
}

/* ---------------------------------------
   5. DERIVED VALUES
--------------------------------------- */
$pending_amount = max(0, $budget - $total_paid);
$budget_exceeded = ($budget > 0 && $total_expenses > $budget);

/* ---------------------------------------
   6. FINAL RESPONSE
--------------------------------------- */
$response = [
    "status" => "success",
    "project" => [
        "project_id" => (int)$project["project_id"],
        "title" => $project["title"],
        "location" => $project["location"] ?? "",
        "status" => $project["status"],
        "overall_progress" => $overall_progress,
        "start_date" => $project["start_date"],
        "end_date" => $project["end_date"],

        "estimated_cost" => $budget,
        "total_paid" => $total_paid,
        "total_expenses" => $total_expenses,
        "pending_amount" => $pending_amount,
        "budget_exceeded" => $budget_exceeded,

        "created_at" => $project["created_at"],

        "client" => [
            "client_id" => (int)$project["client_id"],
            "name" => $project["client_name"],
            "email" => $project["email"],
            "phone" => $project["phone"]
        ]
    ],
    "stages" => $stages
];

echo json_encode($response);
$conn->close();
