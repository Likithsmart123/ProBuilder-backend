<?php
header('Content-Type: application/json');
require_once "db.php";

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    echo json_encode(["status"=>"error","message"=>"Missing credentials"]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT id, client_name, password_hash FROM clients WHERE email = ?"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["status"=>"error","message"=>"Invalid credentials"]);
    exit;
}

$user = $res->fetch_assoc();

file_put_contents("debug_login.txt", date("Y-m-d H:i:s") . " - Email: $email, ID: " . $user['id'] . ", Hash Len: " . strlen($user['password_hash']) . ", Hash: " . $user['password_hash'] . "\n", FILE_APPEND);

if (!password_verify($password, $user['password_hash'])) {
    file_put_contents("debug_login.txt", " - VERIFY FAILED\n", FILE_APPEND);
    echo json_encode(["status"=>"error","message"=>"Invalid credentials"]);
    exit;
}
file_put_contents("debug_login.txt", " - VERIFY SUCCESS\n", FILE_APPEND);

/* ✅ Generate token */
$token = bin2hex(random_bytes(32));

$upd = $conn->prepare(
    "UPDATE clients SET api_token = ? WHERE id = ?"
);
$upd->bind_param("si", $token, $user['id']);
$upd->execute();

echo json_encode([
    "status" => "success",
    "token" => $token,
    "client_id" => (int)$user['id'],
    "role" => "client",
    "name" => $user['client_name']
]);
