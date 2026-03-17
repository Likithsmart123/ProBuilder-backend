<?php
include 'db.php';

$contractor_id = $_POST['contractor_id'];
$project_id = $_POST['project_id'];
$client_id = $_POST['client_id'];
$title = $_POST['title'];
$description = $_POST['description'];
$amount = $_POST['amount'];

$sql = "INSERT INTO quotations (contractor_id, project_id, client_id, title, description, amount) 
        VALUES ('$contractor_id', '$project_id', '$client_id', '$title', '$description', '$amount')";

if ($conn->query($sql) === TRUE) {
    echo "success";
} else {
    echo "error|" . $conn->error;
}
$conn->close();
?>
