<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'team') {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db.php';

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_id']) && isset($_POST['status'])) {
    $task_id = $_POST['task_id'];
    $status = $_POST['status'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?");
    $stmt->bind_param("sii", $status, $task_id, $user_id);
    if ($stmt->execute()) {
        $success = "Task status updated successfully!";
    } else {
        $error = "Error updating task status.";
    }
}

// Fetch tasks assigned to the logged-in team member
$tasks = $conn->query("SELECT t.id, t.title, t.deadline, t.status, u.username as assigned_by 
                       FROM tasks t 
                       JOIN users u ON t.created_by = u.id 
                       WHERE t.assigned_to = " . $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Task List - Task Management System</title>
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
            <h2>Task List</h2>

            <!-- Task List -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Your Tasks</h5>
                    <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
                    <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                    <?php
                    if ($tasks->num_rows > 0) {
                        echo "<table class='table table-striped'>";
                        echo "<thead><tr><th>ID</th><th>Title</th><th>Deadline</th><th>Assigned By</th><th>Status</th><th>Action</th></tr></thead>";
                        echo "<tbody>";
                        while ($task = $tasks->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $task['id'] . "</td>";
                            echo "<td>" . $task['title'] . "</td>";
                            echo "<td>" . $task['deadline'] . "</td>";
                            echo "<td>" . $task['assigned_by'] . "</td>";
                            echo "<td>" . $task['status'] . "</td>";
                            echo "<td>";
                            echo "<form method='POST' style='display:inline;'>";
                            echo "<input type='hidden' name='task_id' value='" . $task['id'] . "'>";
                            if ($task['status'] == 'pending') {
                                echo "<select name='status' class='form-select' onchange='this.form.submit()'>";
                                echo "<option value='pending' " . ($task['status'] == 'pending' ? 'selected' : '') . ">Pending</option>";
                                echo "<option value='in_progress'>In Progress</option>";
                                echo "<option value='completed'>Completed</option>";
                                echo "</select>";
                            } elseif ($task['status'] == 'in_progress') {
                                echo "<select name='status' class='form-select' onchange='this.form.submit()'>";
                                echo "<option value='in_progress' " . ($task['status'] == 'in_progress' ? 'selected' : '') . ">In Progress</option>";
                                echo "<option value='completed'>Completed</option>";
                                echo "</select>";
                            } else {
                                echo "<span>Completed</span>";
                            }
                            echo "</form>";
                            echo "</td>";
                            echo "</tr>";
                        }
                        echo "</tbody></table>";
                    } else {
                        echo "<p>No tasks assigned to you yet.</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>