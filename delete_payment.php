<?php
require 'db_connect.php';

header('Content-Type: application/json');

/* READ INPUT (supports JSON + form-data) */

$data = json_decode(file_get_contents("php://input"), true);

$payment_id = $_POST['payment_id'] ?? ($data['payment_id'] ?? null);

/* VALIDATION */

if (!$payment_id) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing payment_id"
    ]);
    exit;
}

if (!is_numeric($payment_id)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid payment_id"
    ]);
    exit;
}

$payment_id = intval($payment_id);

/* DELETE PAYMENT */

$sql = "DELETE FROM project_payments WHERE id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $payment_id);

/* EXECUTE DELETE */

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {

        echo json_encode([
            "status" => "success",
            "message" => "Payment deleted successfully"
        ]);

    } else {

        echo json_encode([
            "status" => "error",
            "message" => "Payment not found"
        ]);
    }

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>