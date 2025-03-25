<?php
// Database connection parameters
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'ai_website_db';

// Email to update
$email = 'jahidultoon@gmail.com';

// Connect to database
$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current user information
$stmt = $conn->prepare("SELECT id, username, email, is_admin FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "No user found with email: $email\n";
    exit;
}

$user = $result->fetch_assoc();
echo "Current status: \n";
echo "Username: {$user['username']}\n";
echo "Email: {$user['email']}\n";
echo "Admin status: " . ($user['is_admin'] ? "Yes" : "No") . "\n\n";

// Update user to admin role
$update = $conn->prepare("UPDATE users SET is_admin = 1 WHERE email = ?");
$update->bind_param("s", $email);

if ($update->execute()) {
    echo "Updated successfully!\n";
    
    // Verify the change
    $stmt = $conn->prepare("SELECT id, username, email, is_admin FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    echo "New status: \n";
    echo "Username: {$user['username']}\n";
    echo "Email: {$user['email']}\n";
    echo "Admin status: " . ($user['is_admin'] ? "Yes" : "No") . "\n";
} else {
    echo "Update failed: " . $conn->error . "\n";
}

$conn->close();
?> 