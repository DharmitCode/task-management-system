<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db.php';

// Handle task submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $deadline = $_POST['deadline'];
    $assigned_to = $_POST['assigned_to'];
    $created_by = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO tasks (title, description, deadline, assigned_to, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssii", $title, $description, $deadline, $assigned_to, $created_by);
    if ($stmt->execute()) {
        $success = "Task assigned successfully!";
        // Add notification for the assigned team member
        $message = "New task '$title' assigned to you by Admin.";
        $stmt_notify = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt_notify->bind_param("is", $assigned_to, $message);
        $stmt_notify->execute();
    } else {
        $error = "Error assigning task.";
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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="content flex-grow-1 p-4">
            <h2>Dashboard</h2>

            <!-- Task Statistics -->
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

            <!-- Task Assignment Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Assign a New Task</h5>
                    <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
                    <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
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

            <!-- Task List -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Assigned Tasks</h5>
                    <div id="taskList">
                        <?php
                        $tasks = $conn->query("SELECT t.id, t.title, t.deadline, t.status, u.username 
                                               FROM tasks t 
                                               JOIN users u ON t.assigned_to = u.id 
                                               WHERE t.created_by = " . $_SESSION['user_id']);
                        if ($tasks->num_rows > 0) {
                            echo "<table class='table table-striped'>";
                            echo "<thead><tr><th>ID</th><th>Title</th><th>Deadline</th><th>Assigned To</th><th>Status</th></tr></thead>";
                            echo "<tbody>";
                            while ($task = $tasks->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $task['id'] . "</td>";
                                echo "<td>" . $task['title'] . "</td>";
                                echo "<td>" . $task['deadline'] . "</td>";
                                echo "<td>" . $task['username'] . "</td>";
                                echo "<td>" . $task['status'] . "</td>";
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
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateTaskList() {
            fetch('get_tasks.php')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.log(data.error);
                        return;
                    }
                    let html = '<table class="table table-striped"><thead><tr><th>ID</th><th>Title</th><th>Deadline</th><th>Assigned To</th><th>Status</th></tr></thead><tbody>';
                    data.forEach(task => {
                        html += `<tr><td>${task.id}</td><td>${task.title}</td><td>${task.deadline}</td><td>${task.username}</td><td>${task.status}</td></tr>`;
                    });
                    html += '</tbody></table>';
                    if (data.length === 0) html = '<p>No tasks assigned yet.</p>';
                    document.getElementById('taskList').innerHTML = html;
                })
                .catch(error => console.error('Error:', error));
        }

        // Update every 5 seconds
        setInterval(updateTaskList, 5000);
        // Initial update
        updateTaskList();
    </script>
</body>
</html>