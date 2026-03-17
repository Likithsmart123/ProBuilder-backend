<?php
include 'db.php';

header('Content-Type: application/json');

/* READ INPUT */

$contractor_id = $_POST['contractor_id'] ?? null;
$client_id     = $_POST['client_id'] ?? null;
$title         = $_POST['title'] ?? null;
$location      = $_POST['location'] ?? null;
$start_date    = $_POST['start_date'] ?? null;
$end_date      = $_POST['end_date'] ?? null;
$budgetRaw     = $_POST['budget'] ?? '0';

$budget = (float)preg_replace('/[^0-9.]/', '', $budgetRaw);

/* VALIDATION */

if (!$contractor_id || !$client_id || !$title || !$location || !$start_date || !$end_date) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields"
    ]);
    exit();
}

/* CHECK CLIENT BELONGS TO CONTRACTOR */

$checkClient = $conn->prepare("
SELECT id 
FROM clients 
WHERE id = ? AND contractor_id = ?
");

$checkClient->bind_param("ii", $client_id, $contractor_id);
$checkClient->execute();
$res = $checkClient->get_result();

if ($res->num_rows == 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid client"
    ]);
    exit();
}

/* INSERT PROJECT */

$stmt = $conn->prepare("
INSERT INTO projects
(contractor_id, client_id, title, location, start_date, end_date, budget, status)
VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
");

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database error: " . $conn->error
    ]);
    exit();
}

$stmt->bind_param(
    "iissssd",
    $contractor_id,
    $client_id,
    $title,
    $location,
    $start_date,
    $end_date,
    $budget
);

/* EXECUTE PROJECT INSERT */

if ($stmt->execute()) {

    $new_project_id = $stmt->insert_id;

    /* DEFAULT PROJECT STAGES */

    $defaultStages = [
        ["Foundation", 25],
        ["Structure", 35],
        ["Roofing", 20],
        ["Plumbing", 20]
    ];

    $stageStmt = $conn->prepare("
    INSERT INTO project_stages
    (project_id, stage_name, weight, progress)
    VALUES (?, ?, ?, 0)
    ");

    if (!$stageStmt) {
        echo json_encode([
            "status" => "error",
            "message" => "Stage insert error: " . $conn->error
        ]);
        exit();
    }

    foreach ($defaultStages as $stage) {

        $stage_name = $stage[0];
        $stage_weight = $stage[1];

        $stageStmt->bind_param(
            "isi",
            $new_project_id,
            $stage_name,
            $stage_weight
        );

        if (!$stageStmt->execute()) {
            echo json_encode([
                "status" => "error",
                "message" => "Stage insert failed"
            ]);
            exit();
        }
    }

    $stageStmt->close();

    echo json_encode([
        "status" => "success",
        "message" => "Project created successfully",
        "project_id" => $new_project_id
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Failed to create project: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>