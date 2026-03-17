<?php

header("Content-Type: application/json");

// Get data from frontend
$material_name = $_POST['material_name'] ?? '';
$price_per_unit = $_POST['price_per_unit'] ?? '';
$quantity = $_POST['quantity'] ?? '';

// Validation
if ($price_per_unit == '' || $quantity == '') {

    echo json_encode([
        "status" => "error",
        "message" => "Price and quantity are required"
    ]);
    exit;
}

// Convert to numbers
$price = floatval($price_per_unit);
$qty   = floatval($quantity);

// Calculate total
$total_cost = $price * $qty;

// Return result
echo json_encode([
    "status" => "success",
    "material" => $material_name,
    "price_per_unit" => $price,
    "quantity" => $qty,
    "total_cost" => $total_cost
]);

?>