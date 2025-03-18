<?php
require_once 'includes/db.php';

// Fetch all users
$result = $conn->query("SELECT id, username, password FROM users");
while ($user = $result->fetch_assoc()) {
    $plain_password = $user['password']; // Assuming current plain text
    $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password = '$hashed_password' WHERE id = " . $user['id']);
    echo "Hashed password for user ID " . $user['id'] . " (" . $user['username'] . ")<br>";
}

echo "Password hashing complete!";
?>