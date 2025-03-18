<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if ($role == 'admin') {
    $tasks = $conn->query("SELECT t.id, t.title, t.deadline, t.status, u.username 
                           FROM tasks t 
                           JOIN users u ON t.assigned_to = u.id 
                           WHERE t.created_by = $user_id");
} elseif ($role == 'team') {
    $tasks = $conn->query("SELECT t.id, t.title, t.deadline, t.status, t.remarks, t.media_path, u.username as assigned_by 
                           FROM tasks t 
                           JOIN users u ON t.created_by = u.id 
                           WHERE t.assigned_to = $user_id");
} else {
    echo json_encode(['error' => 'Invalid role']);
    exit();
}

$task_data = [];
while ($task = $tasks->fetch_assoc()) {
    $task_data[] = $task;
}

echo json_encode($task_data);
?>