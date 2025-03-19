<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'team') {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db.php';

// Fetch tasks assigned to the current team member
$current_date = date('Y-m-d H:i:s');
$tasks_query = "SELECT t.id, t.title, t.description, t.deadline, t.status, u.username AS created_by 
                FROM tasks t 
                JOIN users u ON t.created_by = u.id 
                WHERE t.assigned_to = " . $_SESSION['user_id'];
$tasks = $conn->query($tasks_query);

$output = '';
if ($tasks->num_rows > 0) {
    $output .= '<table class="table table-striped">';
    $output .= '<thead><tr><th>ID</th><th>Title</th><th>Description</th><th>Deadline</th><th>Status & Actions</th></tr></thead>';
    $output .= '<tbody>';
    while ($task = $tasks->fetch_assoc()) {
        $is_overdue = (strtotime($task['deadline']) < strtotime($current_date));
        $row_class = $is_overdue ? 'table-danger' : '';
        $disabled = $is_overdue ? 'disabled' : '';
        $output .= '<tr class="' . $row_class . '">';
        $output .= '<td>' . $task['id'] . '</td>';
        $output .= '<td>' . htmlspecialchars($task['title']) . '</td>';
        $output .= '<td>' . htmlspecialchars($task['description']) . '</td>';
        $output .= '<td>' . $task['deadline'] . '</td>';
        $output .= '<td>';
        $output .= '<form method="POST" style="display:inline;">';
        $output .= '<input type="hidden" name="task_id" value="' . $task['id'] . '">';
        $output .= '<select name="status" class="form-select ios-status-select animate__animated animate__fadeIn" style="animation-delay: 0.3s" onchange="this.form.submit()" ' . $disabled . '>';
        $output .= '<option value="pending" ' . ($task['status'] == 'pending' ? 'selected' : '') . '>Pending</option>';
        $output .= '<option value="in_progress" ' . ($task['status'] == 'in_progress' ? 'selected' : '') . '>In Progress</option>';
        $output .= '<option value="completed" ' . ($task['status'] == 'completed' ? 'selected' : '') . '>Completed</option>';
        $output .= '</select>';
        $output .= '<input type="hidden" name="update_status" value="1">';
        $output .= '</form>';
        if ($is_overdue) {
            $output .= '<span class="text-danger ms-2">Overdue</span>';
        }
        $output .= '</td>';
        $output .= '</tr>';
    }
    $output .= '</tbody>';
    $output .= '</table>';
} else {
    $output .= '<p class="text-muted">No tasks assigned to you.</p>';
}

echo json_encode(['tasks' => $output]);
?>