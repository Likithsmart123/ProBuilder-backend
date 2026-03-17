<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Content-Type: application/json");
include 'db_connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

$email = $_POST['email'] ?? '';

// 1. Basic validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email"]);
    exit();
}

// 2. Check email exists (Contractors OR Clients)
$found = false;

// Check Users (Contractors)
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $found = true;
}
$stmt->close();

// Check Clients if not found
if (!$found) {
    $stmt = $conn->prepare("SELECT id FROM clients WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $found = true;
    }
    $stmt->close();
}

if (!$found) {
    echo json_encode(["status" => "error", "message" => "Email not found"]);
    exit();
}

// 3. Generate OTP
$otp = random_int(100000, 999999);
$expires_at = date("Y-m-d H:i:s", strtotime("+5 minutes"));

// 4. Setup mail FIRST
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;

    // 🔴 Credentials from user file
    $mail->Username   = 'govindmaheswar34@gmail.com';
    $mail->Password   = 'zryw bfjj iuqa moey';

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('govindmaheswar34@gmail.com', 'ProBuilder App');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Your ProBuilder OTP';
    $mail->Body    = "
        <h3>Your OTP: <b>$otp</b></h3>
        <p>This OTP is valid for 5 minutes.</p>
    ";
    $mail->AltBody = "Your OTP is $otp. Valid for 5 minutes.";

    // 5. Send email
    $mail->send();

    // 6. Save OTP ONLY after mail success
    $stmt = $conn->prepare(
        "INSERT INTO email_otp (email, otp, expires_at) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("sss", $email, $otp, $expires_at);
    $stmt->execute();

    echo json_encode(["status" => "success", "message" => "OTP sent successfully"]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Mailer Error: " . $mail->ErrorInfo
    ]);
}
?>
