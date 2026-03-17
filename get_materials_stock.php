<?php
include 'db.php';
header('Content-Type: application/json');

$contractor_id = intval($_GET['contractor_id'] ?? 0);

if ($contractor_id <= 0) {
    echo json_encode([]);
    exit;
}

/* ==================================================
   FIX-2 IS HERE ⬇️⬇️⬇️
   This query ENSURES:
   - No duplicate materials
   - Correct quantities
   - Exactly 1 row per material
================================================== */
$sql = "
    SELECT 
        MIN(id) AS id,
        LOWER(TRIM(material_name)) AS material_name,
        SUM(current_stock) AS quantity,
        unit
    FROM materials
    WHERE contractor_id = ?
    GROUP BY LOWER(TRIM(material_name)), unit
";
/* ==================================================
   FIX-2 ENDS HERE ⬆️⬆️⬆️
================================================== */

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $contractor_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

/* === DEBUG (TEMP – REMOVE AFTER CONFIRMATION) === */
error_log("API_COUNT = " . count($data));
/* ================================================ */

echo json_encode($data);
$conn->close();
?>
