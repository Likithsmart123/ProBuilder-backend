<?php
header('Content-Type: application/json');
require_once "db.php";

$clientId = $_GET['client_id'] ?? '';

if (!$clientId) {
    echo json_encode(["status"=>"error","message"=>"Missing client ID"]);
    exit;
}

// Fetch Client Info & Assigned Contractor
// Assuming clients table has project_id, and projects table has contractor_id
$query = "
    SELECT 
        c.client_name, 
        c.email, 
        c.phone, 
        IFNULL(u.name, 'Not Assigned') as contractor_name
    FROM clients c
    LEFT JOIN projects p ON c.project_id = p.id
    LEFT JOIN users u ON p.contractor_id = u.id
    WHERE c.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $clientId);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["status"=>"error","message"=>"Client not found"]);
    exit;
}

$data = $res->fetch_assoc();

echo json_encode([
    "status" => "success",
    "data" => $data
]);
?>
