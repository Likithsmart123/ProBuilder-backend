<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

/* ===============================
   1. ONLY GET ALLOWED
================================ */
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Only GET allowed"
    ]);
    exit;
}

/* ===============================
   2. VALIDATE client_id
================================ */
$client_id = intval($_GET["client_id"] ?? 0);

if ($client_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "client_id is required"
    ]);
    exit;
}

/* ===============================
   3. FETCH CLIENT PROJECTS
   (FIXED COLUMN NAMES)
================================ */
/* ===============================
   3. FETCH CLIENT PROJECTS (OPTIMIZED)
================================ */
$sql = "
    SELECT 
        p.id AS project_id,
        p.title AS project_name,
        p.location,
        p.status,
        p.overall_progress,

        -- last activity (from work logs)
        MAX(w.created_at) AS last_activity_date,

        -- photo existence
        COUNT(pm.id) > 0 AS has_photos

    FROM projects p
    INNER JOIN clients c ON c.project_id = p.id
    LEFT JOIN work_logs w ON w.project_id = p.id
    LEFT JOIN project_media pm ON pm.project_id = p.id

    WHERE c.id = ?
    GROUP BY p.id
    ORDER BY last_activity_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

/* ===============================
   4. BUILD RESPONSE
================================ */
$projects = [];
while ($row = $result->fetch_assoc()) {
    // Format boolean for JSON
    $row['has_photos'] = (bool)$row['has_photos'];
    
    // Ensure date isn't null
    if (!$row['last_activity_date']) {
        $row['last_activity_date'] = $row['start_date'] ?? date('Y-m-d'); 
    }
    
    $projects[] = $row;
}

/* ===============================
   5. FINAL JSON
================================ */
echo json_encode([
    "status"  => "success",
    "count"   => count($projects),
    "projects"=> $projects
]);

$stmt->close();
$conn->close();
