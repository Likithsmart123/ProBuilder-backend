<?php
header('Content-Type: application/json');

/* DB CONNECTION */
$conn = new mysqli("localhost","root","","pro_builder");
if ($conn->connect_error) {
    echo json_encode(["error"=>"DB connection failed"]);
    exit;
}

/* READ INPUTS SAFELY */
$material_id = $_POST['material_id'] ?? null;
$quantity    = $_POST['quantity'] ?? null;
$location    = "Chittoor";

/* VALIDATE INPUTS */
if (!$material_id || !$quantity) {
    echo json_encode([
        "error" => "material_id and quantity are required",
        "example" => [
            "material_id" => 3,
            "quantity" => 100
        ]
    ]);
    exit;
}

/* GET MATERIAL NAME + UNIT */
$sql1 = "SELECT name, unit FROM materials WHERE id=?";
$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param("i", $material_id);
$stmt1->execute();
$res1 = $stmt1->get_result();
$row1 = $res1->fetch_assoc();

if (!$row1) {
    echo json_encode(["error"=>"Material not found"]);
    exit;
}

$material_name = $row1['name'];
$unit          = $row1['unit'];

/* GET LAST 5 PRICES AND PREDICT */
$sql2 = "
SELECT AVG(price) AS predicted_price
FROM (
    SELECT price
    FROM material_price_history
    WHERE material=? AND location=?
    ORDER BY record_date DESC
    LIMIT 5
) recent_prices
";

$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("ss", $material_name, $location);
$stmt2->execute();
$res2 = $stmt2->get_result();
$row2 = $res2->fetch_assoc();

$predicted_price = $row2['predicted_price'] ?? 0;

/* CALCULATE TOTAL */
$total_cost = $predicted_price * $quantity;

/* STORE PREDICTION (simple insert) */
$sql3 = "
INSERT INTO price_predictions
(material_id,current_price,predicted_price,prediction_date)
VALUES (?,?,?,CURDATE())
";

$stmt3 = $conn->prepare($sql3);
$stmt3->bind_param("idd", $material_id, $predicted_price, $predicted_price);
$stmt3->execute();

/* RESPONSE */
echo json_encode([
    "material"        => $material_name,
    "predicted_price" => $predicted_price,
    "unit"            => $unit,
    "quantity"        => $quantity,
    "total_cost"      => $total_cost
]);