<?php
include 'db.php';
header('Content-Type: application/json');

$project_id = intval($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid project_id"]);
    exit;
}

// Fetch materials used in this project from work_log_materials joined with work_logs
$sql = "
    SELECT 
        m.id as material_id, 
        m.material_name, 
        m.unit,
        COALESCE(SUM(wlm.used_quantity), 0) as used_quantity,
        m.current_stock as remaining_quantity,
        wlm.specifications
    FROM work_log_materials wlm
    JOIN work_logs wl ON wlm.work_log_id = wl.id
    JOIN materials m ON wlm.material_id = m.id
    WHERE wl.project_id = ?
    GROUP BY m.id, m.material_name, wlm.specifications, m.unit, m.current_stock
    HAVING used_quantity > 0
    ORDER BY m.material_name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        "material_id" => (int)$row["material_id"],
        "material_name" => $row["material_name"],
        "unit" => $row["unit"] ?? "Units",
        "used_quantity" => (float)$row["used_quantity"],
        "remaining_quantity" => (int)$row["remaining_quantity"],
        "specifications" => $row["specifications"] ?? ""
    ];
}

echo json_encode([
    "status" => "success",
    "materials" => $data
]);

$stmt->close();
$conn->close();
