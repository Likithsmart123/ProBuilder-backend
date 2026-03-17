<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

include("db.php");

header('Content-Type: application/json');

/* READ INPUT */

$contractor_id  = $_POST['contractor_id'] ?? null;
$material_name  = $_POST['material_name'] ?? null;
$unit           = $_POST['unit'] ?? 'bags';
$min_stock      = $_POST['min_stock'] ?? 0;

/* VALIDATION */

if (!$contractor_id || !$material_name) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields"
    ]);
    exit();
}

/* CHECK DUPLICATE MATERIAL */

$check = $conn->prepare("
SELECT id 
FROM materials
WHERE contractor_id = ?
AND material_name = ?
");

$check->bind_param("is", $contractor_id, $material_name);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {

    echo json_encode([
        "status" => "error",
        "message" => "Material already exists"
    ]);
    exit();
}

/* INSERT MATERIAL */

$stmt = $conn->prepare("
INSERT INTO materials
(contractor_id, material_name, unit, min_stock, current_stock)
VALUES (?, ?, ?, ?, 0)
");

$stmt->bind_param(
    "issi",
    $contractor_id,
    $material_name,
    $unit,
    $min_stock
);

if ($stmt->execute()) {

    echo json_encode([
        "status" => "success",
        "message" => "Material added successfully",
        "material_id" => $stmt->insert_id
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Failed to add material"
    ]);
}

$stmt->close();
$conn->close();
?>