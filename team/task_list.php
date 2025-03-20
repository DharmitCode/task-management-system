<?php
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['team', 'admin'])) {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db.php';

// Handle status update (if the form submits a new status)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $task_id = $_POST['task_id'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?");
    $stmt->bind_param("sii", $status, $task_id, $_SESSION['user_id']);
    if ($stmt->execute()) {
        $success = "Task status updated successfully!";
    } else {
        $error = "Error updating task status: " . $stmt->error;
    }
}

// Fetch the logged-in user's details
$user_query = "SELECT username FROM users WHERE id = " . $_SESSION['user_id'];
$user_result = $conn->query($user_query);
$user = $user_result->fetch_assoc();
$username = $user['username'];
$role_display = $_SESSION['role'] === 'team' ? 'Team Member' : 'Admin';

// Fetch tasks assigned to the current team member
$current_date = date('Y-m-d H:i:s');
$tasks_query = "SELECT t.id, t.title, t.description, t.deadline, t.status, u.username AS created_by 
                FROM tasks t 
                JOIN users u ON t.created_by = u.id 
                WHERE t.assigned_to = " . $_SESSION['user_id'];
$tasks = $conn->query($tasks_query);
error_log("Debug: Tasks query executed, rows returned: " . $tasks->num_rows . ", assigned_to: " . $_SESSION['user_id']);

// Fetch notifications (simplified, adjust based on your notifications table)
$notifications_query = "SELECT message FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " ORDER BY id DESC LIMIT 5";
$notifications = $conn->query($notifications_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Task List - Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.css" />
    <link rel="stylesheet" href="../assets/css/styles.css"> 
    <link rel="stylesheet" href="../assets/css/admin_styles.css">
    <link rel="stylesheet" href="../assets/css/team_tasks.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include '../includes/menu.php'; ?>
    <script>
        document.getElementById('header-title').textContent = 'Team Task List';
        document.getElementById('nav-link-1').setAttribute('href', 'task_list.php');
        document.getElementById('nav-link-1').textContent = 'My Tasks';

        // Temporary message fade-out
        document.addEventListener('DOMContentLoaded', () => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.add('fade-out');
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });

            // Function to update notifications and tasks
            function updateNotificationsAndTasks() {
                // Fetch notifications
                fetch('fetch_notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'user_id=<?php echo $_SESSION['user_id']; ?>'
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('notifications-section').innerHTML = data.notifications;
                })
                .catch(error => console.error('Error updating notifications:', error));

                // Fetch tasks
                fetch('fetch_tasks.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'user_id=<?php echo $_SESSION['user_id']; ?>'
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('tasks-section').innerHTML = data.tasks;
                })
                .catch(error => console.error('Error updating tasks:', error));
            }

            // Initial update
            updateNotificationsAndTasks();

            // Update every 5 seconds
            setInterval(updateNotificationsAndTasks, 5000);
        });
    </script>
    <div class="content">
        <!-- Display logged-in user and role -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="card-title animate__animated animate__fadeInDown">My Tasks</h2>
            <div class="user-info">
                <span class="badge bg-primary">Logged in as: <?php echo htmlspecialchars($username); ?></span>
                <span class="badge bg-secondary ms-2">Role: <?php echo htmlspecialchars($role_display); ?></span>
            </div>
        </div>

        <!-- Notifications Section -->
        <div class="card mt-4 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
            <div class="card-body">
                <h3 class="card-title">Notifications</h3>
                <div id="notifications-section">
                    <?php if ($notifications->num_rows > 0): ?>
                        <ul class="list-group">
                            <?php while ($notification = $notifications->fetch_assoc()): ?>
                                <li class="list-group-item"><?php echo htmlspecialchars($notification['message']); ?></li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">No notifications.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Tasks Assigned to You -->
        <div class="card mt-4 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
            <div class="card-body">
                <h3 class="card-title">Tasks Assigned to You</h3>
                <div id="tasks-section">
                    <?php if ($tasks->num_rows > 0): ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Description</th>
                                    <th>Deadline</th>
                                    <th>Status & Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($task = $tasks->fetch_assoc()): ?>
                                    <?php
                                    $is_overdue = (strtotime($task['deadline']) < strtotime($current_date));
                                    $is_completed = ($task['status'] === 'completed');
                                    // Team member can edit if: not overdue AND not completed
                                    // Admin can edit regardless of overdue, but not if completed (unless explicitly allowed)
                                    $is_editable = ($_SESSION['role'] === 'admin') ? true : (!$is_overdue && !$is_completed);
                                    $row_class = $is_overdue ? 'table-danger' : ($is_completed ? 'table-secondary' : '');
                                    $disabled = $is_editable ? '' : 'disabled';
                                    ?>
                                    <tr class="<?php echo $row_class; ?>">
                                        <td><?php echo $task['id']; ?></td>
                                        <td><?php echo htmlspecialchars($task['title']); ?></td>
                                        <td><?php echo htmlspecialchars($task['description']); ?></td>
                                        <td><?php echo $task['deadline']; ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <select name="status" class="form-select ios-status-select animate__animated animate__fadeIn" style="animation-delay: 0.3s" onchange="this.form.submit()" <?php echo $disabled; ?>>
                                                    <option value="pending" <?php echo $task['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="in_progress" <?php echo $task['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                    <option value="completed" <?php echo $task['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                </select>
                                                <input type="hidden" name="update_status" value="1">
                                            </form>
                                            <?php if ($is_overdue): ?>
                                                <span class="text-danger ms-2">Overdue</span>
                                            <?php elseif ($is_completed): ?>
                                                <span class="text-muted ms-2">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No tasks assigned to you.</p>
                    <?php endif; ?>
                </div>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success animate__animated animate__fadeIn"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger animate__animated animate__fadeIn"><?php echo $error; ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>