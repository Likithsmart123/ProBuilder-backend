<?php
require 'db_connect.php';

header('Content-Type: application/json');

/* READ INPUT (supports form-data and JSON) */

$data = json_decode(file_get_contents("php://input"), true);

$project_id = $_POST['project_id'] ?? ($data['project_id'] ?? null);

/* VALIDATION */

if (!$project_id) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing project_id"
    ]);
    exit;
}

if (!is_numeric($project_id)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid project_id"
    ]);
    exit;
}

$project_id = intval($project_id);

/* UPDATE PROJECT */

$sql = "
UPDATE projects 
SET 
    status = 'Completed',
    completion_date = CURRENT_DATE,
    overall_progress = 100,
    completed_percentage = 100
WHERE id = ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $project_id);

/* EXECUTE PROJECT UPDATE */

if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {

        /* UPDATE PROJECT STAGES PROGRESS */

        $sql_stages = "
        UPDATE project_stages 
        SET progress = 100
        WHERE project_id = ?
        ";

        $stmt_stages = $conn->prepare($sql_stages);

        if ($stmt_stages) {
            $stmt_stages->bind_param("i", $project_id);
            $stmt_stages->execute();
            $stmt_stages->close();
        }

        echo json_encode([
            "status" => "success",
            "message" => "Project marked as completed"
        ]);

    } else {

        echo json_encode([
            "status" => "error",
            "message" => "Project not found or already completed"
        ]);
    }

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Failed to complete project: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();

?>