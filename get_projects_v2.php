<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

/* AUTH - Robust Implementation */
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

if (!$token) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authorization missing"]);
    exit;
}

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
$contractor_id = (int)$user['id'];

/* FETCH PROJECTS */
$client_id_filter = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

$sql = "SELECT id, title, location FROM projects WHERE contractor_id = ?";
$types = "i";
$params = [$contractor_id];

if ($client_id_filter > 0) {
    $sql .= " AND client_id = ?";
    $types .= "i";
    $params[] = $client_id_filter;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$result = $stmt->get_result();
$projects = [];

while ($row = $result->fetch_assoc()) {
    $projects[] = [
        "project_id" => (int)$row["id"],
        "title" => $row["title"],
        "location" => $row["location"]
    ];
}

echo json_encode([
    "status" => "success",
    "count" => count($projects),
    "projects" => $projects
]);

$conn->close();
?>
