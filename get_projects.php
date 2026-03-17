<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

// Allow only GET
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method Not Allowed"]);
    exit;
}

$token = '';
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
}
$token = trim($token);

if ($token === '') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authorization missing"]);
    exit;
}

// 1. Authenticate Contractor
$stmt = $conn->prepare("SELECT id FROM users WHERE api_token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid token"]);
    exit;
}

$user = $res->fetch_assoc();
$contractor_id = $user['id'];

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;

// 2. Fetch Projects with Client Info
$sql = "
    SELECT
        p.id AS project_id,
        p.title,
        p.location,
        p.status,
        p.overall_progress, -- Keep this for progress bar
        p.start_date,
        p.end_date,
        p.created_at,
        p.budget, -- Added budget

        c.id AS client_id,
        c.client_name AS client_name,
        c.phone AS client_phone,
        c.email AS client_email

    FROM projects p
    LEFT JOIN clients c ON c.project_id = p.id
    WHERE p.contractor_id = ?
";

if ($client_id) {
    $sql .= " AND c.id = ?";
}
$sql .= " ORDER BY p.created_at DESC";

$stmt = $conn->prepare($sql);
if ($client_id) {
    $stmt->bind_param("ii", $contractor_id, $client_id);
} else {
    $stmt->bind_param("i", $contractor_id);
}
$stmt->execute();
$result = $stmt->get_result();

$projects = [];

while ($row = $result->fetch_assoc()) {
    $clientObj = null;
    if (!empty($row["client_id"])) {
        $clientObj = [
            "id" => (int)$row["client_id"],
            "name" => $row["client_name"] ?? "Unknown",
            "phone" => $row["client_phone"] ?? "",
            "email" => $row["client_email"] ?? ""
        ];
    }

    $projects[] = [
        "project_id" => (int)$row["project_id"],
        "title" => $row["title"],
        "location" => $row["location"],
        "status" => $row["status"],
        "estimated_cost" => (double)$row["budget"], // Expose budget
        "overall_progress" => (int)$row["overall_progress"], // For existing progress bar support
        "start_date" => $row["start_date"], // Exposed flat for easy parsing
        "end_date" => $row["end_date"],     // Exposed flat for easy parsing
        "client" => $clientObj,
        "dates" => [
            "start" => $row["start_date"],
            "end" => $row["end_date"]
        ]
    ];
}

echo json_encode([
    "status" => "success",
    "count" => count($projects),
    "projects" => $projects
]);

$conn->close();
?>
