<?php
// reset_password.php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
include 'db_connect.php';

$email = $_POST['email'] ?? '';
$new_password = $_POST['new_password'] ?? '';

if (empty($email) || empty($new_password)) {
    echo json_encode(["status" => "error", "message" => "Missing parameters"]);
    exit();
}

// 1. Security Check: ensure there is a RECENTLY USED OTP for this email
$stmt = $conn->prepare("SELECT id FROM email_otp 
                        WHERE email = ? AND is_used = 1 
                        AND created_at >= NOW() - INTERVAL 10 MINUTE 
                        ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    echo json_encode(["status" => "error", "message" => "Session expired or unauthorized. Please verify OTP again."]);
    $stmt->close();
    exit();
}
$stmt->close();

// 2. Update Password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$updated = false;

// Try updating users table (Contractors)
$update_users = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$update_users->bind_param("ss", $hashed_password, $email);
$update_users->execute();

if ($update_users->affected_rows > 0) {
    $updated = true;
}
$update_users->close();

// If not updated in users, try clients table (Clients)
if (!$updated) {
    $update_clients = $conn->prepare("UPDATE clients SET password_hash = ? WHERE email = ?");
    $update_clients->bind_param("ss", $hashed_password, $email);
    $update_clients->execute();
    
    if ($update_clients->affected_rows > 0) {
        $updated = true;
    }
    $update_clients->close();
}

if ($updated) {
    echo json_encode(["status" => "success", "message" => "Password updated successfully"]);
} else {
    // It's possible the email exists but password is same (0 affected rows), or email doesn't exist.
    // However, if OTP check passed, the email exists in OTP table. But maybe not in users/clients?
    // We can assume if OTP existed, we should have found user.
    // Let's check if user exists at all to give better error? No, generic error is safer.
    echo json_encode(["status" => "error", "message" => "User account not found or password unchanged"]);
}

$conn->close();
?>
