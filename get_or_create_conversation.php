<?php
require 'db_connect.php';

// Allow JSON body parsing
$data = json_decode(file_get_contents("php://input"), true);

$contractor_id = isset($_POST['contractor_id']) ? $_POST['contractor_id'] : (isset($data['contractor_id']) ? $data['contractor_id'] : null);
$client_id = isset($_POST['client_id']) ? $_POST['client_id'] : (isset($data['client_id']) ? $data['client_id'] : null);
$project_id = isset($_POST['project_id']) ? $_POST['project_id'] : (isset($data['project_id']) ? $data['project_id'] : null);

if (!$contractor_id || !$client_id || $project_id === null || $project_id === '') {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit;
}

$query = "SELECT id FROM conversations WHERE contractor_id=? AND client_id=? AND project_id=?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $contractor_id, $client_id, $project_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(["status" => "success", "conversation_id" => $row['id']]);
} else {
    $insert = "INSERT INTO conversations (contractor_id, client_id, project_id) VALUES (?, ?, ?)";
    $stmt_insert = $conn->prepare($insert);
    $stmt_insert->bind_param("iii", $contractor_id, $client_id, $project_id);
    if ($stmt_insert->execute()) {
        echo json_encode(["status" => "success", "conversation_id" => $conn->insert_id]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database insert failed"]);
    }
}
?>
