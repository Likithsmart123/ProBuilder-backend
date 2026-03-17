<?php
require 'db_connect.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$project_id = $_POST['project_id'] ?? ($data['project_id'] ?? null);

if (!$project_id) {
    echo json_encode([
        "status"=>"error",
        "message"=>"Missing project_id"
    ]);
    exit;
}

$project_id = intval($project_id);

$conn->begin_transaction();

try {

    /* DELETE PROJECT INVITES */

    $stmt1 = $conn->prepare("DELETE FROM project_invites WHERE project_id=?");
    if($stmt1){
        $stmt1->bind_param("i",$project_id);
        $stmt1->execute();
        $stmt1->close();
    }

    /* DELETE PROJECT PAYMENTS */

    $stmt2 = $conn->prepare("DELETE FROM project_payments WHERE project_id=?");
    if($stmt2){
        $stmt2->bind_param("i",$project_id);
        $stmt2->execute();
        $stmt2->close();
    }

    /* DELETE PROJECT */

    $stmt = $conn->prepare("DELETE FROM projects WHERE id=?");
    $stmt->bind_param("i",$project_id);
    $stmt->execute();

    if($stmt->affected_rows == 0){
        throw new Exception("Project not found");
    }

    $stmt->close();

    $conn->commit();

    echo json_encode([
        "status"=>"success",
        "message"=>"Project deleted successfully"
    ]);

} catch(Exception $e){

    $conn->rollback();

    echo json_encode([
        "status"=>"error",
        "message"=>$e->getMessage()
    ]);
}

$conn->close();
?>