<?php
require 'db_connect.php';

header('Content-Type: application/json');

$material_id = $_POST['material_id'] ?? null;

if(!$material_id){
    echo json_encode([
        "status"=>"error",
        "message"=>"material_id required"
    ]);
    exit;
}

/* CHECK IF MATERIAL USED IN WORK LOG */

$check = $conn->prepare(
"SELECT COUNT(*) as total FROM work_log_materials WHERE material_id = ?"
);

$check->bind_param("i",$material_id);
$check->execute();
$res = $check->get_result();
$row = $res->fetch_assoc();

if($row['total'] > 0){
    echo json_encode([
        "status"=>"error",
        "message"=>"Material already used in work log. Cannot delete."
    ]);
    exit;
}

/* SAFE DELETE */

$stmt = $conn->prepare("DELETE FROM materials WHERE id=?");
$stmt->bind_param("i",$material_id);

if($stmt->execute()){
    echo json_encode([
        "status"=>"success",
        "message"=>"Material deleted"
    ]);
}else{
    echo json_encode([
        "status"=>"error",
        "message"=>$stmt->error
    ]);
}

?>