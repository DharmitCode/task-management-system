<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'team') {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db.php';

// Set default timezone to match the server's timezone (UTC-6:00)
date_default_timezone_set('America/Chicago'); // UTC-6:00 (CST)

// Fetch notifications
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " AND is_read = 0");

// Handle task status update and file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_id']) && isset($_POST['status'])) {
    $task_id = $_POST['task_id'];
    $status = $_POST['status'];
    $user_id = $_SESSION['user_id'];
    $remarks = $_POST['remarks'] ?? '';
    $media_path = null;

    // Check if deadline has passed
    $task = $conn->query("SELECT deadline FROM tasks WHERE id = $task_id AND assigned_to = $user_id")->fetch_assoc();
    if (!$task) {
        $error = "Task not found.";
    } else {
        $deadline = new DateTime($task['deadline'], new DateTimeZone('UTC')); // Deadline is stored in UTC
        $deadline->setTimezone(new DateTimeZone('America/Chicago')); // Convert to local timezone for comparison
        $now = new DateTime('now', new DateTimeZone('America/Chicago')); // Current time in local timezone
        error_log("Task $task_id - Deadline (Local, America/Chicago): " . $deadline->format('Y-m-d H:i:s') . ", Now (Local, America/Chicago): " . $now->format('Y-m-d H:i:s'));

        if ($deadline < $now) {
            $error = "Cannot edit task after the deadline has passed.";
            error_log("Task $task_id edit blocked: Deadline passed.");
        } else {
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
                $error = "Error updating task status: " . $stmt->error;
            }
        }
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
                                $deadline = new DateTime($task['deadline'], new DateTimeZone('UTC')); // Deadline is in UTC
                                $deadline->setTimezone(new DateTimeZone('America/Chicago')); // Convert to local timezone
                                $now = new DateTime('now', new DateTimeZone('America/Chicago')); // Current time in local timezone
                                $isPastDeadline = $deadline < $now;
                                error_log("PHP - Task {$task['id']} - Deadline (Local, America/Chicago): " . $deadline->format('Y-m-d H:i:s') . ", Now (Local, America/Chicago): " . $now->format('Y-m-d H:i:s') . ", Past Due: " . ($isPastDeadline ? 'Yes' : 'No'));
                                echo "<tr>";
                                echo "<td>" . $task['id'] . "</td>";
                                echo "<td>" . $task['title'] . "</td>";
                                echo "<td>" . $deadline->format('Y-m-d H:i:s') . " " . ($isPastDeadline ? "<span class='badge bg-danger'>Past Due</span>" : "") . "</td>";
                                echo "<td>" . $task['assigned_by'] . "</td>";
                                echo "<td>" . $task['status'] . "</td>";
                                echo "<td>" . ($task['remarks'] ? htmlspecialchars($task['remarks']) : '-') . "</td>";
                                echo "<td>" . ($task['media_path'] ? "<a href='" . $task['media_path'] . "' target='_blank'>View</a>" : '-') . "</td>";
                                echo "<td>";
                                if (!$isPastDeadline) {
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
                                } else {
                                    echo "<span class='badge bg-secondary'>No edits allowed</span>";
                                }
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
            fetch('../admin/get_tasks.php')
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
                    let html = '<table class="table table-striped"><thead><tr><th>ID</th><th>Title</th><th>Deadline</th><th>Assigned By</th><th>Status</th><th>Remarks</th><th>Media</th><th>Action</th></tr></thead><tbody>';
                    const userTimezoneOffset = -360; // UTC-6:00 in minutes (6 hours * 60)
                    data.forEach(task => {
                        const deadline = new Date(task.deadline + 'Z'); // Deadline in UTC
                        // Convert deadline to local timezone (UTC-6:00)
                        const deadlineLocal = new Date(deadline.getTime() + userTimezoneOffset * 60 * 1000);
                        const now = new Date(); // Current local time
                        // Convert now to local timezone (UTC-6:00) for comparison
                        const nowLocal = new Date(now.getTime() + userTimezoneOffset * 60 * 1000);
                        const isPastDeadline = deadlineLocal < nowLocal;
                        console.log(`JS - Task ${task.id} - Deadline (Local, UTC-6:00): ${deadlineLocal.toISOString()}, Now (Local, UTC-6:00): ${nowLocal.toISOString()}, Past Due: ${isPastDeadline}`);
                        html += `<tr><td>${task.id}</td><td>${task.title}</td><td>${deadlineLocal.toISOString().slice(0, 19).replace('T', ' ')} ${isPastDeadline ? '<span class="badge bg-danger">Past Due</span>' : ''}</td><td>${task.assigned_by}</td><td>${task.status}</td><td>${task.remarks ? task.remarks : '-'}</td><td>${task.media_path ? `<a href='${task.media_path}' target='_blank'>View</a>` : '-'}</td><td>${!isPastDeadline ? `<form method='POST' enctype='multipart/form-data' style='display:inline;'><input type='hidden' name='task_id' value='${task.id}'><select name='status' class='form-select' onchange='this.form.submit()'><option value='pending' ${task.status === 'pending' ? 'selected' : ''}>Pending</option><option value='in_progress' ${task.status === 'in_progress' ? 'selected' : ''}>In Progress</option><option value='completed' ${task.status === 'completed' ? 'selected' : ''}>Completed</option></select><textarea name='remarks' class='form-control mt-2' placeholder='Add remarks'>${task.remarks || ''}</textarea><input type='file' name='media' class='form-control mt-2' accept='.jpg,.jpeg,.png,.pdf,.doc,.docx'></form>` : '<span class="badge bg-secondary">No edits allowed</span>'}</td></tr>`;
                    });
                    html += '</tbody></table>';
                    if (data.length === 0) html = '<p>No tasks assigned to you yet.</p>';
                    document.getElementById('taskList').innerHTML = html;
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    document.getElementById('taskList').innerHTML = '<p>Error loading tasks. Check console for details.</p>';
                });
        }

        // Update every 5 seconds
        setInterval(updateTaskList, 5000);
        // Initial update
        updateTaskList();
    </script>
</body>
</html>