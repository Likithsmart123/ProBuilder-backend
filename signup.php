<?php
include 'db.php';

header("Content-Type: text/plain");

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$phone = $_POST['phone'] ?? '';

if (empty($name) || empty($email) || empty($password)) {
    echo "error|Missing required fields";
    exit;
}

// Check if email exists in users
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "email_exists";
    $stmt->close();
    exit;
}
$stmt->close();

// Start transaction to ensure atomicity
$conn->begin_transaction();

try {
    // 1. Insert into users table
    // Users table has: id, name, email, password, phone, role
    // We must hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $role = 'contractor';

    $stmt1 = $conn->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt1) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt1->bind_param("sssss", $name, $email, $hashed_password, $phone, $role);
    
    if (!$stmt1->execute()) {
        throw new Exception("Execute failed (users): " . $stmt1->error);
    }
    
    $user_id = $conn->insert_id;
    $stmt1->close();

    // 2. Insert into contractors table
    // Contractors table link via user_id
    // It has: user_id, name, email, password_hash
    // We store the same hash in password_hash for redundancy if needed by legacy code
    $stmt2 = $conn->prepare("INSERT INTO contractors (user_id, name, email, password_hash) VALUES (?, ?, ?, ?)");
    if (!$stmt2) {
        throw new Exception("Prepare failed (contractors): " . $conn->error);
    }
    $stmt2->bind_param("isss", $user_id, $name, $email, $hashed_password);
    
    if (!$stmt2->execute()) {
        throw new Exception("Execute failed (contractors): " . $stmt2->error);
    }
    $stmt2->close();

    // Commit transaction
    $conn->commit();
    echo "success";

} catch (Exception $e) {
    $conn->rollback();
    echo "error|" . $e->getMessage();
}

$conn->close();
?>
