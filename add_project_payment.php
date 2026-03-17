<?php
include 'db.php';

$project_id   = intval($_POST['project_id'] ?? 0);
$client_id    = intval($_POST['client_id'] ?? 0);
$amount       = floatval($_POST['amount'] ?? 0);
$payment_mode = trim($_POST['payment_mode'] ?? 'Cash');
$payment_date = $_POST['payment_date'] ?? date('Y-m-d');
$notes        = trim($_POST['notes'] ?? '');

if ($project_id <= 0 || $client_id <= 0 || $amount <= 0) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"invalid_input"]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO project_payments
    (project_id, client_id, amount, payment_mode, payment_date, notes)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "iidsss",
    $project_id,
    $client_id,
    $amount,
    $payment_mode,
    $payment_date,
    $notes
);

$stmt->execute();

echo json_encode([
    "status" => "success",
    "payment_id" => $stmt->insert_id
]);
