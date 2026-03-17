<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "pro_builder";

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}
?>