<?php
$host = 'localhost';
$db = 'task_management';
$user = 'root';
$pass = ''; // Empty for default XAMPP setup
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>