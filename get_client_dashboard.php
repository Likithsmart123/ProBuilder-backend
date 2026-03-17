<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
error_log("DASHBOARD API: Received client_id = " . $client_id);

if ($client_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Client ID"]);
    exit;
}

if ($client_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid Client ID"]);
    exit;
}

$response = [
    "status" => "success",
    "active_projects" => 0,
    "total_quotations" => 0
];

// 1. Get Project Count (Assuming 1 client -> 1 project usually, but logic allows N)
// Using 'projects' table where client_id matches
// Or if the client is linked via 'clients' table structure? 
// In 'get_projects_v2.php', we saw joining clients c on p.client_id = c.id
// But wait, the previous conversation established the 'clients' table has a 'project_id' column too?
// Let's check `register_client.php`: INSERT INTO clients (..., project_id, ...)
// So the client KNOWS their project_id.
// However, the dashboard seems to want a count.
// Let's count projects where this client is assigned.
// IF the relationship is project -> client_id, we count FROM projects WHERE client_id = ?
// IF the relationship is client -> project_id, we count FROM clients WHERE id = ? (and check if project_id is not null)

// Let's look at `get_projects_v2.php` line 1-116 again (from context).
// It queries `projects p` and joins `clients c` on `p.client_id = c.id`.
// So `projects` table has `client_id`.
// 1. Get Active Projects Count (Matches get_client_projects.php)
$stmt = $conn->prepare("
    SELECT COUNT(p.id) as count 
    FROM projects p
    INNER JOIN clients c ON c.project_id = p.id
    WHERE c.id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $response['active_projects'] = $row['count'];
    }
    $stmt->close();
}

// 2. Get Total Quotations Count (Matches get_client_quotations_all.php)
$stmt = $conn->prepare("
    SELECT COUNT(id) as count 
    FROM quotations 
    WHERE client_id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $response['total_quotations'] = $row['count'];
    }
    $stmt->close();
}

echo json_encode($response);
$conn->close();
?>
