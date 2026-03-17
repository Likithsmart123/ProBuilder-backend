<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

// 1. Auth Check
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

// 2. Validate Token and Get Contractor ID
$stmt = $conn->prepare("SELECT id FROM users WHERE api_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid token"]);
    exit;
}

$user = $res->fetch_assoc();
$contractor_id = $user['id'];

$project_id = intval($_GET['project_id'] ?? 0);

// 3. Query with Contractor Filter
// FIX: Use p.title as project_name, c.client_name
$sql = "
    SELECT
        pp.id,
        pp.amount,
        pp.payment_date,
        pp.payment_mode,
        pp.notes,
        p.title AS project_name, 
        c.client_name
    FROM project_payments pp
    JOIN projects p ON pp.project_id = p.id
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE p.contractor_id = ?
";

$params = [$contractor_id];
$types = "i";

if ($project_id > 0) {
    $sql .= " AND pp.project_id = ?";
    $params[] = $project_id;
    $types .= "i";
}

$sql .= " ORDER BY pp.payment_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$payments = [];
$total_received = 0;

$header_project = "All Projects";
$header_client = "All Clients";

// Fetch header info if specific project
if ($project_id > 0) {
    // FIX: Use p.title
    $pDetailsStmt = $conn->prepare("SELECT p.title AS project_name, c.client_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE p.id = ? AND p.contractor_id = ?");
    $pDetailsStmt->bind_param("ii", $project_id, $contractor_id);
    $pDetailsStmt->execute();
    $pRes = $pDetailsStmt->get_result();
    if ($pRow = $pRes->fetch_assoc()) {
        $header_project = $pRow['project_name'];
        $header_client = $pRow['client_name'] ?? "Unknown";
    }
}

while ($row = $result->fetch_assoc()) {
    $total_received += (float)$row['amount'];
    
    $payments[] = [
        "id" => $row['id'],
        "amount" => $row['amount'],
        "payment_date" => $row['payment_date'],
        "payment_mode" => $row['payment_mode'],
        "notes" => $row['notes'],
        "project_name" => $row['project_name'],
        "client_name" => $row['client_name'] ?? "Unknown"
    ];
}

echo json_encode([
    "project_name" => $header_project,
    "client_name" => $header_client,
    "total_received" => $total_received,
    "payments" => $payments
]);

$conn->close();
?>
