<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json'); // Ensure JSON response

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$tasks = [];

try {
    error_log("get_tasks.php executed for user_id: $user_id, role: $role"); // Debug log
    if ($role == 'admin') {
        $result = $conn->query("SELECT t.id, t.title, t.deadline, t.status, u.username 
                                FROM tasks t 
                                JOIN users u ON t.assigned_to = u.id 
                                WHERE t.created_by = $user_id");
        if ($result === false) {
            throw new Exception("Query failed: " . $conn->error);
        }
        while ($task = $result->fetch_assoc()) {
            $tasks[] = $task;
        }
    } elseif ($role == 'team') {
        $result = $conn->query("SELECT t.id, t.title, t.deadline, t.status, u.username as assigned_by, t.remarks, t.media_path 
                                FROM tasks t 
                                JOIN users u ON t.created_by = u.id 
                                WHERE t.assigned_to = $user_id");
        if ($result === false) {
            throw new Exception("Query failed: " . $conn->error);
        }
        while ($task = $result->fetch_assoc()) {
            // Ensure deadline is in UTC for consistency
            $deadline = new DateTime($task['deadline'], new DateTimeZone('UTC'));
            $task['deadline'] = $deadline->format('Y-m-d H:i:s');
            $tasks[] = $task;
        }
    }
    echo json_encode($tasks);
} catch (Exception $e) {
    error_log("Error in get_tasks.php: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error: ' . $e->getMessage()]);
}
exit();