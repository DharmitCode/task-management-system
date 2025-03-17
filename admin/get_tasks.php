<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$tasks = [];

if ($role == 'admin') {
    $result = $conn->query("SELECT t.id, t.title, t.deadline, t.status, u.username 
                            FROM tasks t 
                            JOIN users u ON t.assigned_to = u.id 
                            WHERE t.created_by = $user_id");
    while ($task = $result->fetch_assoc()) {
        $tasks[] = $task;
    }
} elseif ($role == 'team') {
    $result = $conn->query("SELECT t.id, t.title, t.deadline, t.status, u.username as assigned_by, t.remarks, t.media_path 
                            FROM tasks t 
                            JOIN users u ON t.created_by = u.id 
                            WHERE t.assigned_to = $user_id");
    while ($task = $result->fetch_assoc()) {
        $tasks[] = $task;
    }
}

echo json_encode($tasks);
exit();