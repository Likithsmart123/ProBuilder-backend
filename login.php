<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "db.php";

header("Content-Type: application/json");

$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$role     = $_POST['role'] ?? 'contractor';

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Missing credentials"]);
    exit;
}

/* =========================================================
   CONTRACTOR LOGIN
   ========================================================= */
if ($role === 'contractor') {

    $stmt = $conn->prepare("
        SELECT id, name, password 
        FROM users 
        WHERE email = ? AND role = 'contractor'
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        http_response_code(401);
        echo json_encode(["status"=>"error","message"=>"Invalid credentials"]);
        exit;
    }

    $row = $res->fetch_assoc();

    if (!password_verify($password, $row['password'])) {
        http_response_code(401);
        echo json_encode(["status"=>"error","message"=>"Invalid credentials"]);
        exit;
    }

    // Generate token
    $token = bin2hex(random_bytes(32));

    // Save token
    $upd = $conn->prepare("UPDATE users SET api_token = ? WHERE id = ?");
    $upd->bind_param("si", $token, $row['id']);
    $upd->execute();

    echo json_encode([
        "status" => "success",
        "role" => "contractor",
        "token" => $token,
        "contractor_id" => (int)$row['id'],
        "name" => $row['name']
    ]);
    exit;
}

/* =========================================================
   CLIENT LOGIN
   ========================================================= */
if ($role === 'client') {

    $stmt = $conn->prepare("
        SELECT id, client_name, password_hash, project_id
        FROM clients
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        http_response_code(401);
        echo json_encode(["status"=>"error","message"=>"Invalid credentials"]);
        exit;
    }

    $row = $res->fetch_assoc();

    if (!password_verify($password, $row['password_hash'])) {
        http_response_code(401);
        echo json_encode(["status"=>"error","message"=>"Invalid credentials"]);
        exit;
    }

    // Generate token
    $token = bin2hex(random_bytes(32));

    // Save token
    $upd = $conn->prepare("UPDATE clients SET api_token = ? WHERE id = ?");
    $upd->bind_param("si", $token, $row['id']);
    $upd->execute();

    echo json_encode([
        "status" => "success",
        "role" => "client",
        "token" => $token,
        "client_id" => (int)$row['id'],
        "client_name" => $row['client_name'],
        "project_id" => (int)$row['project_id']
    ]);
    exit;
}

/* ========================================================= */
http_response_code(400);
echo json_encode(["status"=>"error","message"=>"Invalid role"]);
