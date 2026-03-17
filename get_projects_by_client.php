<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

// Only GET allowed
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Only GET method allowed"
    ]);
    exit;
}

// Validate client_id
$client_id = $_GET["client_id"] ?? null;

if (!$client_id) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "client_id is required"
    ]);
    exit;
}

// Ensure client exists
$checkClient = $conn->prepare("SELECT id FROM clients WHERE id = ?");
$checkClient->bind_param("i", $client_id);
$checkClient->execute();
$checkClient->store_result();

if ($checkClient->num_rows === 0) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Client not found"
    ]);
    exit;
}

// SQL filtered by client_id
$sql = "
    SELECT 
        p.id AS project_id,
        p.title,
        p.location,
        p.status,
        p.overall_progress,
        p.budget,
        p.start_date,
        p.end_date,
        p.created_at,

        c.id AS client_id,
        c.client_name AS client_name,
        c.email AS client_email,
        c.phone AS client_phone,

        ctr.id AS contractor_id,
        ctr.name AS contractor_name,
        ctr.email AS contractor_email
    FROM projects p
    INNER JOIN clients c ON p.client_id = c.id
    INNER JOIN contractors ctr ON p.contractor_id = ctr.id
    WHERE p.client_id = ?
    ORDER BY p.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

$projects = [];

while ($row = $result->fetch_assoc()) {
    $projects[] = [
        "project_id" => (int)$row["project_id"],
        "title" => $row["title"],
        "location" => $row["location"],
        "status" => $row["status"],
        "overall_progress" => (int)$row["overall_progress"],
        "budget" => $row["budget"],
        "start_date" => $row["start_date"],
        "end_date" => $row["end_date"],
        "created_at" => $row["created_at"],

        "client" => [
            "client_id" => (int)$row["client_id"],
            "name" => $row["client_name"],
            "email" => $row["client_email"],
            "phone" => $row["client_phone"]
        ],

        "contractor" => [
            "contractor_id" => (int)$row["contractor_id"],
            "name" => $row["contractor_name"],
            "email" => $row["contractor_email"]
        ]
    ];
}

echo json_encode([
    "status" => "success",
    "client_id" => (int)$client_id,
    "count" => count($projects),
    "projects" => $projects
]);

$stmt->close();
$conn->close();
