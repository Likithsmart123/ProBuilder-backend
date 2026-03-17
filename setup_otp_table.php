<?php
include 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS email_otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (email),
    INDEX (otp)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table email_otp created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
