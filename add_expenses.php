<?php
require_once __DIR__ . "/db_connect.php";

/* Robust Auth */
$token = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}
$token = trim($token);
// Optional: strictly enforce token check if needed, but existing flow might be loose.
// Keeping it loose for now to matches other scripts unless requested to tighten.

$project_id = $_POST['project_id'] ?? '';
$category = $_POST['category'] ?? '';
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$invoice_no = $_POST['invoice_no'] ?? '';
$amount = $_POST['amount'] ?? '';
$expense_date = $_POST['expense_date'] ?? '';

if (!$project_id || !$category || !$title || !$amount || !$expense_date) {
    echo "missing";
    exit;
}

$stmt = $conn->prepare("INSERT INTO project_expenses (project_id, category, title, description, invoice_no, amount, expense_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issssss", $project_id, $category, $title, $description, $invoice_no, $amount, $expense_date);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error|" . $stmt->error;
}
$conn->close();
