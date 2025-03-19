<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['user_id']) || $_SESSION['user_id'] != $_POST['user_id']) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_POST['user_id'];

$total_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = $user_id")->fetch_assoc()['count'];
$pending_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = $user_id AND status = 'pending'")->fetch_assoc()['count'];
$in_progress_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = $user_id AND status = 'in_progress'")->fetch_assoc()['count'];
$completed_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = $user_id AND status = 'completed'")->fetch_assoc()['count'];

echo json_encode([
    'total_tasks' => $total_tasks,
    'pending_tasks' => $pending_tasks,
    'in_progress_tasks' => $in_progress_tasks,
    'completed_tasks' => $completed_tasks
]);
?>