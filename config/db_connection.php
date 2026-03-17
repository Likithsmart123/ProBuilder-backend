<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "probuilder";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log("DB ERROR: " . $conn->connect_error);
}
