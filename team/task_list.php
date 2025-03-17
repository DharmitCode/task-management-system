<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'team') {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db.php';

// Fetch notifications
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " AND is_read = 0");

// Handle task status update and file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_id']) && isset($_POST['status'])) {
    $task_id = $_POST['task_id'];
    $status = $_POST['status'];
    $user_id = $_SESSION['user_id'];
    $remarks = $_POST['remarks'] ?? '';
    $media_path = null;

    // Handle file upload
    if (isset($_FILES['media']) && $_FILES['media']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name = uniqid() . '_' . basename($_FILES['media']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

        if (in_array($file_type, $allowed_types)) {
            if (move_uploaded_file($_FILES['media']['tmp_name'], $target_file)) {
                $media_path = 'uploads/' . $file_name;
            } else {
                $error = "Error uploading file.";
            }
        } else {
            $error = "Only JPG, JPEG, PNG, PDF, DOC, and DOCX files are allowed.";
        }
    }

    // Update task in database
    $stmt = $conn->prepare("UPDATE tasks SET status = ?, remarks = ?, media_path = ? WHERE id = ? AND assigned_to = ?");
    $stmt->bind_param("sssii", $status, $remarks, $media_path, $task_id, $user_id);
    if ($stmt->execute()) {
        $success = "Task status updated successfully!";
    } else {
        $error = "Error updating task status.";
    }
}

// Fetch initial tasks (for display on page load)
$tasks = $conn->query("SELECT t.id, t.title, t.deadline, t.status, t.remarks, t.media_path, u.username as assigned_by 
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

            <!-- Notifications -->
            <?php
            if ($notifications->num_rows > 0) {
                echo "<div class='alert alert-info mb-4'>";
                echo "<h5>Notifications</h5>";
                while ($notification = $notifications->fetch_assoc()) {
                    echo "<p>" . htmlspecialchars($notification['message']) . " (<small>" . $notification['created_at'] . "</small>)</p>";
                    // Mark as read
                    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = " . $notification['id']);
                }
                echo "</div>";
            }
            ?>

            <!-- Task List -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Your Tasks</h5>
                    <?php if (isset($success)) echo "<div class='alert alert-success'>$success</div>"; ?>
                    <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                    <div id="taskList">
                        <?php
                        if ($tasks->num_rows > 0) {
                            echo "<table class='table table-striped'>";
                            echo "<thead><tr><th>ID</th><th>Title</th><th>Deadline</th><th>Assigned By</th><th>Status</th><th>Remarks</th><th>Media</th><th>Action</th></tr></thead>";
                            echo "<tbody>";
                            while ($task = $tasks->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $task['id'] . "</td>";
                                echo "<td>" . $task['title'] . "</td>";
                                echo "<td>" . $task['deadline'] . "</td>";
                                echo "<td>" . $task['assigned_by'] . "</td>";
                                echo "<td>" . $task['status'] . "</td>";
                                echo "<td>" . ($task['remarks'] ? htmlspecialchars($task['remarks']) : '-') . "</td>";
                                echo "<td>" . ($task['media_path'] ? "<a href='" . $task['media_path'] . "' target='_blank'>View</a>" : '-') . "</td>";
                                echo "<td>";
                                echo "<form method='POST' enctype='multipart/form-data' style='display:inline;'>";
                                echo "<input type='hidden' name='task_id' value='" . $task['id'] . "'>";
                                echo "<select name='status' class='form-select' onchange='this.form.submit()'>";
                                echo "<option value='pending' " . ($task['status'] == 'pending' ? 'selected' : '') . ">Pending</option>";
                                echo "<option value='in_progress' " . ($task['status'] == 'in_progress' ? 'selected' : '') . ">In Progress</option>";
                                echo "<option value='completed' " . ($task['status'] == 'completed' ? 'selected' : '') . ">Completed</option>";
                                echo "</select>";
                                echo "<textarea name='remarks' class='form-control mt-2' placeholder='Add remarks'>" . ($task['remarks'] ? $task['remarks'] : '') . "</textarea>";
                                echo "<input type='file' name='media' class='form-control mt-2' accept='.jpg,.jpeg,.png,.pdf,.doc,.docx'>";
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
                    let html = '<table class="table table-striped"><thead><tr><th>ID</th><th>Title</th><th>Deadline</th><th>Assigned By</th><th>Status</th><th>Remarks</th><th>Media</th><th>Action</th></tr></thead><tbody>';
                    data.forEach(task => {
                        html += `<tr><td>${task.id}</td><td>${task.title}</td><td>${task.deadline}</td><td>${task.assigned_by}</td><td>${task.status}</td><td>${task.remarks ? task.remarks : '-'}</td><td>${task.media_path ? `<a href='${task.media_path}' target='_blank'>View</a>` : '-'}</td><td><form method='POST' enctype='multipart/form-data' style='display:inline;'><input type='hidden' name='task_id' value='${task.id}'><select name='status' class='form-select' onchange='this.form.submit()'><option value='pending' ${task.status === 'pending' ? 'selected' : ''}>Pending</option><option value='in_progress' ${task.status === 'in_progress' ? 'selected' : ''}>In Progress</option><option value='completed' ${task.status === 'completed' ? 'selected' : ''}>Completed</option></select><textarea name='remarks' class='form-control mt-2' placeholder='Add remarks'>${task.remarks || ''}</textarea><input type='file' name='media' class='form-control mt-2' accept='.jpg,.jpeg,.png,.pdf,.doc,.docx'></form></td></tr>`;
                    });
                    html += '</tbody></table>';
                    if (data.length === 0) html = '<p>No tasks assigned to you yet.</p>';
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