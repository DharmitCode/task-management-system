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

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_username'])) {
    $username = $_POST['new_username'];
    $password = $_POST['new_password'];
    $role = $_POST['new_role'];

    // Validate password strength (optional, can be customized)
    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $hashed_password, $role);
        if ($stmt->execute()) {
            $success = "User '$username' created successfully!";
        } else {
            $error = "Error creating user: " . $conn->error;
        }
    }
}

// Fetch all team members for the dropdown
$team_members = $conn->query("SELECT id, username FROM users WHERE role = 'team'");

// Fetch task statistics
$total_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = " . $_SESSION['user_id'])->fetch_assoc()['count'];
$pending_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = " . $_SESSION['user_id'] . " AND status = 'pending'")->fetch_assoc()['count'];
$in_progress_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = " . $_SESSION['user_id'] . " AND status = 'in_progress'")->fetch_assoc()['count'];
$completed_tasks = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE created_by = " . $_SESSION['user_id'] . " AND status = 'completed'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.css" />
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <button id="sidebar-toggle" class="animate__animated">Menu</button>
    <div class="content">
        <h2 class="card-title animate__animated animate__fadeIn">Dashboard</h2>

        <!-- Task Statistics -->
        <div class="card animate__animated animate__fadeInUp">
            <div class="card-body">
                <h3 class="card-title">Task Statistics</h3>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Current Tasks</h5>
                                <h1><?php echo $total_tasks; ?></h1>
                                <div class="d-flex justify-content-around mt-3">
                                    <div>
                                        <span class="badge bg-danger"><?php echo $pending_tasks; ?></span> Pending
                                    </div>
                                    <div>
                                        <span class="badge bg-warning"><?php echo $in_progress_tasks; ?></span> In Progress
                                    </div>
                                    <div>
                                        <span class="badge bg-success"><?php echo $completed_tasks; ?></span> Completed
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Task Completion Rate</h5>
                                <p>This feature will be added later (e.g., a chart).</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task Assignment Form -->
        <div class="card mt-4 animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
            <div class="card-body">
                <h3 class="card-title">Assign a New Task</h3>
                <?php if (isset($success)) echo "<div class='alert alert-success animate__animated animate__fadeIn'>$success</div>"; ?>
                <?php if (isset($error)) echo "<div class='alert alert-danger animate__animated animate__fadeIn'>$error</div>"; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Title:</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description:</label>
                        <textarea name="description" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deadline:</label>
                        <input type="datetime-local" name="deadline" class="form-control" required>
                    </div>
                    <div class="mb-3">
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

        <!-- User Creation Form -->
        <div class="card mt-4 animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
            <div class="card-body">
                <h3 class="card-title">Create New User</h3>
                <?php if (isset($success)) echo "<div class='alert alert-success animate__animated animate__fadeIn'>$success</div>"; ?>
                <?php if (isset($error)) echo "<div class='alert alert-danger animate__animated animate__fadeIn'>$error</div>"; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Username:</label>
                        <input type="text" name="new_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password:</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role:</label>
                        <select name="new_role" class="form-select" required>
                            <option value="team">Team Member</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Create User</button>
                </form>
            </div>
        </div>

        <!-- Task List -->
        <div class="card mt-4 animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
            <div class="card-body">
                <h3 class="card-title">Assigned Tasks</h3>
                <div id="taskList">
                    <?php
                    $tasks = $conn->query("SELECT t.id, t.title, t.deadline, t.status, u.username 
                                           FROM tasks t 
                                           JOIN users u ON t.assigned_to = u.id 
                                           WHERE t.created_by = " . $_SESSION['user_id']);
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
                        echo "<p>No tasks assigned yet.</p>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Task Report Section -->
        <div class="card mt-4 animate__animated animate__fadeInUp" style="animation-delay: 0.4s">
            <div class="card-body">
                <h3 class="card-title">Generate Task Report</h3>
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Filter by Status:</label>
                            <select name="status_filter" class="form-select">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Filter by Team Member:</label>
                            <select name="assigned_to_filter" class="form-select">
                                <option value="">All</option>
                                <?php
                                $team_members->data_seek(0); // Reset pointer
                                while ($member = $team_members->fetch_assoc()): ?>
                                    <option value="<?php echo $member['id']; ?>"><?php echo $member['username']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="export" value="csv" class="btn btn-success me-2">Export as CSV</button>
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
                            <div class="mb-3">
                                <label class="form-label">Title:</label>
                                <input type="text" name="edit_title" id="edit_title" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deadline:</label>
                                <input type="datetime-local" name="edit_deadline" id="edit_deadline" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Assign to:</label>
                                <select name="edit_assigned_to" id="edit_assigned_to" class="form-select" required>
                                    <?php
                                    $team_members->data_seek(0); // Reset pointer
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
    <?php include '../includes/sidebar.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('sidebar-toggle').addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');
            sidebar.classList.toggle('active');
            content.classList.toggle('shifted');
            if (sidebar.classList.contains('active')) {
                sidebar.classList.add('animate__slideInLeft');
                sidebar.classList.remove('animate__slideOutLeft');
            } else {
                sidebar.classList.add('animate__slideOutLeft');
                sidebar.classList.remove('animate__slideInLeft');
            }
            setTimeout(() => {
                sidebar.classList.remove('animate__slideInLeft', 'animate__slideOutLeft');
            }, 500);
        });

        let touchStartX = 0;
        document.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
        });

        document.addEventListener('touchmove', (e) => {
            if (touchStartX < 50) {
                const touchCurrentX = e.touches[0].clientX;
                const diff = touchCurrentX - touchStartX;
                if (diff > 100 && !document.querySelector('.sidebar').classList.contains('active')) {
                    document.querySelector('.sidebar').classList.add('active');
                    document.querySelector('.content').classList.add('shifted');
                    document.querySelector('.sidebar').classList.add('animate__slideInLeft');
                    setTimeout(() => document.querySelector('.sidebar').classList.remove('animate__slideInLeft'), 500);
                    touchStartX = 0;
                }
            }
        });

        document.addEventListener('touchend', () => {
            touchStartX = 0;
        });

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

        function updateTaskList() {
            fetch('get_tasks.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    const data = JSON.parse(text);
                    if (data.error) {
                        console.log('Error from server:', data.error);
                        return;
                    }
                    let html = '<table class="table table-striped"><thead><tr><th>ID</th><th>Title</th><th>Deadline</th><th>Assigned To</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
                    data.forEach(task => {
                        html += `<tr><td>${task.id}</td><td>${task.title}</td><td>${task.deadline}</td><td>${task.username}</td><td>${task.status}</td><td><button class='btn btn-warning btn-sm me-2 edit-btn' data-bs-toggle='modal' data-bs-target='#editModal' data-id='${task.id}' data-title='${task.title}' data-deadline='${task.deadline}' data-assigned-to='${task.username}'>Edit</button><form method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to delete this task?\");'><input type='hidden' name='delete_task_id' value='${task.id}'><button type='submit' class='btn btn-danger btn-sm'>Delete</button></form></td></tr>`;
                    });
                    html += '</tbody></table>';
                    if (data.length === 0) html = '<p>No tasks assigned yet.</p>';
                    document.getElementById('taskList').innerHTML = html;

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
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    document.getElementById('taskList').innerHTML = '<p>Error loading tasks. Check console for details.</p>';
                });
        }

        setInterval(updateTaskList, 5000);
        updateTaskList();
    </script>
</body>
</html>