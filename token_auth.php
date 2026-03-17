<?php
include __DIR__ . "/config/db.php";

/* Read Authorization header */
$token = '';

if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['HTTP_AUTHORIZATION'];
} elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

$token = trim($token);

if ($token === '') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Token missing"]);
    exit;
}

$token = str_replace("Bearer ", "", $token);

/* Validate token */
$stmt = $conn->prepare(
    "SELECT user_id FROM user_tokens WHERE token=?"
);
$stmt->bind_param("s", $token);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid token"
    ]);
    exit;
}

/* This is the LOGGED-IN USER */
$row = $result->fetch_assoc();
$userId = $row['user_id'];