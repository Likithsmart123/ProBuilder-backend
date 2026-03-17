<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require 'db.php';

// ADAPTATION: Create PDO connection using db.php credentials
// The provided code uses $pdo, but db.php provides $conn (mysqli).
// We create a local $pdo instance here to match the provided logic without breaking db.php.
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("DB Connection Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['token'] ?? '';
    $name  = $_POST['client_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$token || !$name || !$email || !$password) {
        die("Missing required fields");
    }

    // 1. Validate invite
    $stmt = $pdo->prepare("
        SELECT project_id
        FROM project_invites
        WHERE invite_token = ?
        AND is_used = 0
        AND expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $invite = $stmt->fetch();

    if (!$invite) {
        die("Invalid or expired invite");
    }

    $project_id = $invite['project_id'];

    // 2. Insert client
    $hash = password_hash($password, PASSWORD_BCRYPT);

    // FIX: Added 'password' to INSERT to store the hash
    $stmt = $pdo->prepare("
        INSERT INTO clients (client_name, email, phone, project_id, password)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $email, $phone, $project_id, $hash]);

    // 3. Mark invite used
    $stmt = $pdo->prepare("
        UPDATE project_invites
        SET is_used = 1
        WHERE invite_token = ?
    ");
    $stmt->execute([$token]);

    echo "SUCCESS";
    exit;
}
?>
