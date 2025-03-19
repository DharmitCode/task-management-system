<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db.php';

// Handle task submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['title'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $deadline = $_POST['deadline'];
    $assigned_to = $_POST['assigned_to'];
    $created_by = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO tasks (title, description, deadline, assigned_to, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssii", $title, $description, $deadline, $assigned_to, $created_by);
    if ($stmt->execute()) {
        $success = "Task assigned successfully!";
        $message = "New task '$title' assigned to you by Admin.";
        $stmt_notify = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt_notify->bind_param("is", $assigned_to, $message);
        $stmt_notify->execute();
    } else {
        $error = "Error assigning task: " . $conn->error;
    }
}

// Handle task editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_task_id'])) {
    $task_id = $_POST['edit_task_id'];
    $title = !empty($_POST['edit_title']) ? $_POST['edit_title'] : null;
    $deadline = $_POST['edit_deadline'];
    $assigned_to = $_POST['edit_assigned_to'];

    $deadlineObj = DateTime::createFromFormat('Y-m-d\TH:i', $deadline);
    if ($deadlineObj === false) {
        $error = "Invalid deadline format: $deadline";
    } else {
        $deadline = $deadlineObj->format('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE tasks SET title = IFNULL(?, title), deadline = ?, assigned_to = ? WHERE id = ? AND created_by = ?");
        $stmt->bind_param("sssii", $title, $deadline, $assigned_to, $task_id, $_SESSION['user_id']);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success = "Task updated successfully!";
            } else {
                $error = "No changes were made to the task.";
            }
        } else {
            $error = "Error updating task: " . $stmt->error;
        }
    }
}

// Handle task deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_task_id'])) {
    $task_id = $_POST['delete_task_id'];

    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $task_id, $_SESSION['user_id']);
    if ($stmt->execute()) {
        $success = "Task deleted successfully!";
    } else {
        $error = "Error deleting task: " . $stmt->error;
    }
}

// Handle export requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['export'])) {
    $status_filter = $_POST['status_filter'] ?? '';
    $assigned_to_filter = $_POST['assigned_to_filter'] ?? '';

    $query = "SELECT t.id, t.title, t.deadline, t.status, u.username 
              FROM tasks t 
              JOIN users u ON t.assigned_to = u.id 
              WHERE t.created_by = " . $_SESSION['user_id'];
    
    if ($status_filter) {
        $query .= " AND t.status = '$status_filter'";
    }
    if ($assigned_to_filter) {
        $query .= " AND t.assigned_to = $assigned_to_filter";
    }

    $result = $conn->query($query);
    $tasks = [];
    while ($task = $result->fetch_assoc()) {
        $tasks[] = $task;
    }

    if ($_POST['export'] == 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="task_report.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Title', 'Deadline', 'Assigned To', 'Status']);
        foreach ($tasks as $task) {
            fputcsv($output, [$task['id'], $task['title'], $task['deadline'], $task['username'], $task['status']]);
        }
        fclose($output);
        exit();
    }
}

// Handle status filter from clickable badges
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Fetch all team members for the dropdown
$team_members = $conn->query("SELECT id, username FROM users WHERE role = 'team'");

// Fetch task statistics
$total_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = " . $_SESSION['user_id'])->fetch_assoc()['count'];
$pending_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = " . $_SESSION['user_id'] . " AND status = 'pending'")->fetch_assoc()['count'];
$in_progress_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = " . $_SESSION['user_id'] . " AND status = 'in_progress'")->fetch_assoc()['count'];
$completed_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = " . $_SESSION['user_id'] . " AND status = 'completed'")->fetch_assoc()['count'];

// Fetch tasks based on status filter
$tasks_query = "SELECT t.id, t.title, t.deadline, t.status, u.username 
                FROM tasks t 
                JOIN users u ON t.assigned_to = u.id 
                WHERE t.created_by = " . $_SESSION['user_id'];
if ($status_filter) {
    $tasks_query .= " AND t.status = '$status_filter'";
}
$tasks = $conn->query($tasks_query);
error_log("Debug: Dashboard query executed, rows returned: " . $tasks->num_rows . ", created_by: " . $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.css" />
    <link rel="stylesheet" href="../assets/css/admin_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../includes/menu.php'; ?>
    <script>
        document.getElementById('header-title').textContent = 'Admin Dashboard';
        document.getElementById('nav-link-1').setAttribute('href', 'dashboard.php');
        document.getElementById('nav-link-1').textContent = 'Dashboard';

        // Temporary message fade-out
        document.addEventListener('DOMContentLoaded', () => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.add('fade-out');
                    setTimeout(() => alert.remove(), 500); // Remove after fade
                }, 5000); // Show for 5 seconds
            });
        });

        // Password reveal toggle (for task assignment form)
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const passwordField = this.previousElementSibling;
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                this.classList.toggle('fa-eye-slash');
            });
        });

        // AJAX to reload task summary and chart
        let completionChart;
        function updateTaskSummaryAndChart() {
            fetch('fetch_task_summary.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'user_id=<?php echo $_SESSION['user_id']; ?>'
            })
            .then(response => response.json())
            .then(data => {
                // Update task summary
                document.getElementById('total-tasks').textContent = data.total_tasks;
                document.getElementById('pending-tasks').textContent = data.pending_tasks;
                document.getElementById('in-progress-tasks').textContent = data.in_progress_tasks;
                document.getElementById('completed-tasks').textContent = data.completed_tasks;

                // Update chart
                completionChart.data.datasets[0].data = [data.pending_tasks, data.in_progress_tasks, data.completed_tasks];
                completionChart.update();
            })
            .catch(error => console.error('Error updating task summary and chart:', error));
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize chart
            const ctx = document.getElementById('completionChart').getContext('2d');
            completionChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'In Progress', 'Completed'],
                    datasets: [{
                        data: [<?php echo $pending_tasks; ?>, <?php echo $in_progress_tasks; ?>, <?php echo $completed_tasks; ?>],
                        backgroundColor: [
                            'rgba(255, 59, 48, 0.8)',
                            'rgba(255, 204, 0, 0.8)',
                            'rgba(52, 199, 89, 0.8)'
                        ],
                        borderColor: [
                            'rgba(255, 59, 48, 1)',
                            'rgba(255, 204, 0, 1)',
                            'rgba(52, 199, 89, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 800
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#1c1c1e',
                                font: {
                                    size: 14,
                                    family: '-apple-system, BlinkMacSystemFont, "San Francisco", "Helvetica Neue", sans-serif'
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#ffffff',
                            titleColor: '#1c1c1e',
                            bodyColor: '#1c1c1e',
                            borderColor: '#e5e5ea',
                            borderWidth: 1,
                            borderRadius: 8
                        }
                    }
                }
            });

            // Start periodic updates
            setInterval(updateTaskSummaryAndChart, 5000);
        });
    </script>
    <div class="content">
        <h2 class="card-title animate__animated animate__fadeInDown">Dashboard Overview</h2>

        <!-- Task Statistics -->
        <div class="row g-4">
            <div class="col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Task Summary</h5>
                        <h1 class="display-4" id="total-tasks"><?php echo $total_tasks; ?></h1>
                        <div class="d-flex justify-content-around mt-3">
                            <div>
                                <a href="?status_filter=pending" class="badge bg-danger clickable" id="pending-tasks"><?php echo $pending_tasks; ?></a> Pending
                            </div>
                            <div>
                                <a href="?status_filter=in_progress" class="badge bg-warning clickable" id="in-progress-tasks"><?php echo $in_progress_tasks; ?></a> In Progress
                            </div>
                            <div>
                                <a href="?status_filter=completed" class="badge bg-success clickable" id="completed-tasks"><?php echo $completed_tasks; ?></a> Completed
                            </div>
                            <div>
                                <a href="?status_filter=" class="badge bg-secondary clickable" id="all-tasks"><?php echo $total_tasks; ?></a> All
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Completion Rate</h5>
                        <div style="position: relative; height: 200px;">
                            <canvas id="completionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Assignment Form -->
        <div class="card mt-4 animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
            <div class="card-body">
                <h3 class="card-title">Assign New Task</h3>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success animate__animated animate__fadeIn"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger animate__animated animate__fadeIn"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="form-label">Title:</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Description:</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Deadline:</label>
                        <input type="datetime-local" name="deadline" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Assign to:</label>
                        <select name="assigned_to" class="form-select" required>
                            <option value="">Select a team member</option>
                            <?php while ($member = $team_members->fetch_assoc()): ?>
                                <option value="<?php echo $member['id']; ?>"><?php echo $member['username']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Assign Task</button>
                </form>
            </div>
        </div>

        <!-- Task List -->
        <div class="card mt-4 animate__animated animate__fadeInUp" style="animation-delay: 0.4s">
            <div class="card-body">
                <h3 class="card-title">Task Management</h3>
                <div id="taskList">
                    <?php
                    if ($tasks->num_rows > 0) {
                        echo "<table class='table table-striped'>";
                        echo "<thead><tr><th>ID</th><th>Title</th><th>Deadline</th><th>Assigned To</th><th>Status</th><th>Actions</th></tr></thead>";
                        echo "<tbody>";
                        while ($task = $tasks->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $task['id'] . "</td>";
                            echo "<td>" . $task['title'] . "</td>";
                            echo "<td>" . $task['deadline'] . "</td>";
                            echo "<td>" . $task['username'] . "</td>";
                            echo "<td>" . $task['status'] . "</td>";
                            echo "<td>";
                            echo "<button class='btn btn-warning btn-sm me-2 edit-btn' data-bs-toggle='modal' data-bs-target='#editModal' data-id='" . $task['id'] . "' data-title='" . htmlspecialchars($task['title']) . "' data-deadline='" . $task['deadline'] . "' data-assigned-to='" . $task['username'] . "'>Edit</button>";
                            echo "<form method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to delete this task?\");'>";
                            echo "<input type='hidden' name='delete_task_id' value='" . $task['id'] . "'>";
                            echo "<button type='submit' class='btn btn-danger btn-sm'>Delete</button>";
                            echo "</form>";
                            echo "</td>";
                            echo "</tr>";
                        }
                        echo "</tbody></table>";
                    } else {
                        echo "<p>No tasks found.</p>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Task Report Section -->
        <div class="card mt-4 animate__animated animate__fadeInUp" style="animation-delay: 0.5s">
            <div class="card-body">
                <h3 class="card-title">Generate Report</h3>
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Filter by Status:</label>
                            <select name="status_filter" class="form-select">
                                <option value="">All</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Filter by Team Member:</label>
                            <select name="assigned_to_filter" class="form-select">
                                <option value="">All</option>
                                <?php
                                $team_members->data_seek(0);
                                while ($member = $team_members->fetch_assoc()): ?>
                                    <option value="<?php echo $member['id']; ?>"><?php echo $member['username']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="export" value="csv" class="btn btn-success mt-3">Export as CSV</button>
                </form>
            </div>
        </div>

        <!-- Edit Task Modal -->
        <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" action="" novalidate>
                            <input type="hidden" name="edit_task_id" id="edit_task_id">
                            <div class="mb-4">
                                <label class="form-label">Title:</label>
                                <input type="text" name="edit_title" id="edit_title" class="form-control">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Deadline:</label>
                                <input type="datetime-local" name="edit_deadline" id="edit_deadline" class="form-control" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Assign to:</label>
                                <select name="edit_assigned_to" id="edit_assigned_to" class="form-select" required>
                                    <?php
                                    $team_members->data_seek(0);
                                    while ($member = $team_members->fetch_assoc()): ?>
                                        <option value="<?php echo $member['id']; ?>"><?php echo $member['username']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('edit_task_id').value = this.getAttribute('data-id');
                document.getElementById('edit_title').value = this.getAttribute('data-title');
                let deadline = new Date(this.getAttribute('data-deadline') + ' UTC');
                let formattedDeadline = deadline.toISOString().slice(0, 16);
                document.getElementById('edit_deadline').value = formattedDeadline;
                document.getElementById('edit_assigned_to').value = Array.from(document.getElementById('edit_assigned_to').options).find(option => option.text === this.getAttribute('data-assigned-to'))?.value || '';
            });
        });
    </script>
    <style>
        .fade-out {
            animation: fadeOut 0.5s ease-in-out forwards;
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    </style>
</body>
</html>