<?php
include 'db.php';

// Allow any origin for testing; restrict in production
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/plain"); // Returning plain text as per Android logic

$contractor_id = $_POST['contractor_id'] ?? null;
$project_id = $_POST['project_id'] ?? null;

if (!$contractor_id || !$project_id) {
    echo "error|Missing parameters";
    exit();
}

// Verify Project Ownership
$checkStmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND contractor_id = ?");
$checkStmt->bind_param("ii", $project_id, $contractor_id);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows == 0) {
    echo "error|Invalid Project";
    $checkStmt->close();
    exit();
}
$checkStmt->close();

// Generate Token
$token = bin2hex(random_bytes(16));
// Note: client_register.php expects POST, so this link is likely for the App to trigger or for a frontend logic.
// Aligning columns with client_register.php expectations: 'invite_token', 'is_used', 'expires_at'
$invite_link = "client_register.php?token=" . $token;

// Expiration date (e.g., 7 days)
$expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));

// Save to DB
// client_register.php uses 'invite_token', 'is_used' (0 unchecked), 'expires_at'
$stmt = $conn->prepare("INSERT INTO project_invites (project_id, invite_token, is_used, expires_at) VALUES (?, ?, 0, ?)");
if (!$stmt) {
    echo "error|Database Error: " . $conn->error;
    exit();
}

$stmt->bind_param("iss", $project_id, $token, $expires_at);

if ($stmt->execute()) {
    echo "success|" . $token;
} else {
    echo "error|Failed to save invite: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
