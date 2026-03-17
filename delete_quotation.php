<?php
require 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$quotation_id = isset($_POST['quotation_id']) ? $_POST['quotation_id'] : (isset($data['quotation_id']) ? $data['quotation_id'] : null);

if (!$quotation_id) {
    echo json_encode(["status" => "error", "message" => "Missing quotation_id"]);
    exit;
}

// Ensure the ID is numeric to prevent basic injection
if (!is_numeric($quotation_id)) {
    echo json_encode(["status" => "error", "message" => "Invalid quotation_id"]);
    exit;
}

$quotation_id = intval($quotation_id);

$sql = "DELETE FROM quotations WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("i", $quotation_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Quotation deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Quotation not found"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
