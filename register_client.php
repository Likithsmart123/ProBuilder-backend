<?php
header("Content-Type: application/json");
require_once __DIR__ . "/db_connect.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["status"=>"error","message"=>"Only POST allowed"]);
    exit;
}

$name     = trim($_POST["name"] ?? "");
$email    = trim($_POST["email"] ?? "");
$phone    = trim($_POST["phone"] ?? "");
$password = $_POST["password"] ?? "";
$token    = trim($_POST["api_token"] ?? "");

if ($name === "" || $email === "" || $password === "" || $token === "") {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Missing fields"]);
    exit;
}

/* 0️⃣ Check if email exists & is valid */
$check = $conn->prepare("SELECT id, password_hash FROM clients WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check_res = $check->get_result();

$existing_user_id = null;

if ($check_res->num_rows > 0) {
    $row = $check_res->fetch_assoc();
    // If password hash is valid (length > 0), then it's a real user -> Block registration
    if (!empty($row['password_hash'])) {
        http_response_code(400);
        echo json_encode(["status"=>"error","message"=>"Email already registered"]);
        exit;
    }
    // If hash is empty, it's a phantom/invalid user -> We will OVERWRITE this record
    $existing_user_id = $row['id'];
}

/* 1️⃣ Validate invite token */
// Updated to use project_invites table and correct columns
$stmt = $conn->prepare("
    SELECT project_id
    FROM project_invites
    WHERE invite_token = ? AND is_used = 0
");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Invalid or expired invite"]);
    exit;
}

$invite = $res->fetch_assoc();
$project_id = (int)$invite["project_id"];

// Fetch contractor_id from projects table
$p_stmt = $conn->prepare("SELECT contractor_id FROM projects WHERE id = ?");
$p_stmt->bind_param("i", $project_id);
$p_stmt->execute();
$p_res = $p_stmt->get_result();

if ($p_res->num_rows === 0) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Project not found"]);
    exit;
}
$project_data = $p_res->fetch_assoc();
$contractor_id = (int)$project_data["contractor_id"];

/* 2️⃣ Hash password */
$password_hash = password_hash($password, PASSWORD_BCRYPT);

/* 3️⃣ Create client */
/* 3️⃣ Create or Update client */
if ($existing_user_id) {
    // Overwrite existing invalid user
    $stmt = $conn->prepare("
        UPDATE clients 
        SET client_name=?, phone=?, password_hash=?, contractor_id=?, project_id=?, api_token=?
        WHERE id=?
    ");
    $stmt->bind_param("sssissi", $name, $phone, $password_hash, $contractor_id, $project_id, $token, $existing_user_id);
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["status"=>"error","message"=>"Client update failed"]);
        exit;
    }
    $client_id = $existing_user_id;
} else {
    // Insert new user
    $insert = $conn->prepare("
        INSERT INTO clients
        (client_name, email, phone, password_hash, contractor_id, project_id, api_token)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->bind_param(
        "ssssiss",
        $name,
        $email,
        $phone,
        $password_hash,
        $contractor_id,
        $project_id,
        $token
    );

    if (!$insert->execute()) {
        http_response_code(500);
        echo json_encode(["status"=>"error","message"=>"Client creation failed"]);
        exit;
    }
    $client_id = $insert->insert_id;
}



/* 4️⃣ Mark invite used */
$upd = $conn->prepare("UPDATE project_invites SET is_used = 1 WHERE invite_token = ?");
$upd->bind_param("s", $token);
$upd->execute();

/* 5️⃣ Success */
echo json_encode([
    "status" => "success",
    "client_id" => $client_id,
    "project_id" => $project_id,
    "token" => $token
]);

$conn->close();
