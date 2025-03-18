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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 10px 20px;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .header .menu-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #007aff;
            transition: transform 0.3s ease, color 0.3s ease;
        }
        .header .menu-btn:hover {
            color: #005bb5;
            transform: scale(1.1);
        }
        .header .menu-btn:active {
            transform: scale(0.98);
        }
        .content {
            padding-top: 60px; /* Adjust for header height */
            transition: margin-left 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .nav-panel {
            position: fixed;
            top: 0;
            left: -250px;
            width: 250px;
            height: 100%;
            background-color: #ffffff;
            box-shadow: 2px 0 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            z-index: 1001;
            transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .nav-panel.active {
            transform: translateX(250px);
        }
        .nav-panel .nav-item {
            margin-bottom: 15px;
        }
        .nav-panel .nav-link {
            display: block;
            padding: 12px 15px;
            color: #007aff;
            text-decoration: none;
            font-weight: 500;
            border-radius: 12px;
            transition: background-color 0.3s ease, transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .nav-panel .nav-link:hover, .nav-panel .nav-link.active {
            background-color: #f2f2f7;
            transform: scale(1.02);
        }
    </style>
</head>
<body>
    <header class="header">
        <button class="menu-btn" id="menu-toggle">&#9776;</button>
        <h4 class="m-0">Dashboard</h4>
        <div></div> <!-- Spacer for alignment -->
    </header>
    <div class="nav-panel" id="nav-panel">
        <div class="nav-panel-header">
            <h3>Menu</h3>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">Logout</a>
            </li>
        </ul>
    </div>
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
                                <div style="position: relative; height: 200px;">
                                    <canvas id="completionChart"></canvas>
                                </div>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('menu-toggle').addEventListener('click', function() {
            const navPanel = document.getElementById('nav-panel');
            navPanel.classList.toggle('active');
            if (navPanel.classList.contains('active')) {
                navPanel.classList.add('animate__slideInLeft');
                navPanel.classList.remove('animate__slideOutLeft');
            } else {
                navPanel.classList.add('animate__slideOutLeft');
                navPanel.classList.remove('animate__slideInLeft');
            }
            setTimeout(() => {
                navPanel.classList.remove('animate__slideInLeft', 'animate__slideOutLeft');
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
                if (diff > 100 && !document.getElementById('nav-panel').classList.contains('active')) {
                    document.getElementById('nav-panel').classList.add('active');
                    document.getElementById('nav-panel').classList.add('animate__slideInLeft');
                    setTimeout(() => document.getElementById('nav-panel').classList.remove('animate__slideInLeft'), 500);
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

        function updateDashboard() {
            fetch('get_tasks.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        console.log('Error from server:', data.error);
                        return;
                    }
                    // Update task list
                    let html = '<table class="table table-striped"><thead><tr><th>ID</th><th>Title</th><th>Deadline</th><th>Assigned To</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
                    data.forEach(task => {
                        html += `<tr><td>${task.id}</td><td>${task.title}</td><td>${task.deadline}</td><td>${task.username}</td><td>${task.status}</td><td><button class='btn btn-warning btn-sm me-2 edit-btn' data-bs-toggle='modal' data-bs-target='#editModal' data-id='${task.id}' data-title='${task.title}' data-deadline='${task.deadline}' data-assigned-to='${task.username}'>Edit</button><form method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to delete this task?\");'><input type='hidden' name='delete_task_id' value='${task.id}'><button type='submit' class='btn btn-danger btn-sm'>Delete</button></form></td></tr>`;
                    });
                    html += '</tbody></table>';
                    if (data.length === 0) html = '<p>No tasks assigned yet.</p>';
                    document.getElementById('taskList').innerHTML = html;

                    // Update chart data
                    const pending = data.filter(t => t.status === 'pending').length;
                    const inProgress = data.filter(t => t.status === 'in_progress').length;
                    const completed = data.filter(t => t.status === 'completed').length;
                    completionChart.data.datasets[0].data = [pending, inProgress, completed];
                    completionChart.update();

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

        // Chart.js Configuration
        const ctx = document.getElementById('completionChart').getContext('2d');
        const completionChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed'],
                datasets: [{
                    data: [<?php echo $pending_tasks; ?>, <?php echo $in_progress_tasks; ?>, <?php echo $completed_tasks; ?>],
                    backgroundColor: [
                        'rgba(255, 59, 48, 0.8)',   // Red for Pending
                        'rgba(255, 204, 0, 0.8)',   // Yellow for In Progress
                        'rgba(52, 199, 89, 0.8)'    // Green for Completed
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
                    animateRotate: true
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#1c1c1e',
                            font: {
                                size: 14
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#ffffff',
                        titleColor: '#1c1c1e',
                        bodyColor: '#1c1c1e',
                        borderColor: '#e5e5ea',
                        borderWidth: 1
                    }
                }
            }
        });

        setInterval(updateDashboard, 5000);
        updateDashboard();
    </script>
</body>
</html>