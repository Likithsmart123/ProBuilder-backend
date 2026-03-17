<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

/* ===============================
   1. Allow ONLY GET
================================ */
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Only GET method allowed"
    ]);
    exit;
}

/* ===============================
   2. Validate contractor_id
================================ */
$contractor_id = isset($_GET["contractor_id"]) ? intval($_GET["contractor_id"]) : 0;

if ($contractor_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "contractor_id is required"
    ]);
    exit;
}

/* ===============================
   3. Fetch clients
   (clients belong to contractor)
================================ */
$stmt = $conn->prepare("
    SELECT 
        id AS client_id,
        client_name,
        email,
        phone
    FROM clients
    WHERE contractor_id = ?
    ORDER BY client_name ASC
");

$stmt->bind_param("i", $contractor_id);
$stmt->execute();
$result = $stmt->get_result();

/* ===============================
   4. Build response
================================ */
$clients = [];
while ($row = $result->fetch_assoc()) {
    $clients[] = [
        "client_id" => (int)$row["client_id"],
        "name"      => $row["client_name"],
        "email"     => $row["email"],
        "phone"     => $row["phone"]
    ];
}

/* ===============================
   5. Final JSON
================================ */
echo json_encode([
    "status" => "success",
    "count"  => count($clients),
    "clients"=> $clients
]);

$stmt->close();
$conn->close();
