<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'team') {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db.php';

// Set default timezone to match the server's timezone (UTC-6:00)
date_default_timezone_set('America/Chicago');

// Fetch notifications
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = " . $_SESSION['user_id'] . " AND is_read = 0");

// Handle task status update and file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task_id']) && isset($_POST['status'])) {
    $task_id = $_POST['task_id'];
    $status = $_POST['status'];
    $user_id = $_SESSION['user_id'];
    $remarks = $_POST['remarks'] ?? '';
    $media_path = null;

    $task = $conn->query("SELECT deadline FROM tasks WHERE id = $task_id AND assigned_to = $user_id")->fetch_assoc();
    if (!$task) {
        $error = "Task not found.";
    } else {
        $deadline = new DateTime($task['deadline'], new DateTimeZone('UTC'));
        $deadline->setTimezone(new DateTimeZone('America/Chicago'));
        $now = new DateTime('now', new DateTimeZone('America/Chicago'));
        error_log("Task $task_id - Deadline (Local, America/Chicago): " . $deadline->format('Y-m-d H:i:s') . ", Now (Local, America/Chicago): " . $now->format('Y-m-d H:i:s'));

        if ($deadline < $now) {
            $error = "Cannot edit task after the deadline has passed.";
            error_log("Task $task_id edit blocked: Deadline passed.");
        } else {
            if (isset($_FILES['media']) && $_FILES['media']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.css" />
    <link rel="stylesheet" href="../assets/css/styles.css">
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
        <h4 class="m-0">Task List</h4>
        <div></div> <!-- Spacer for alignment -->
    </header>
    <div class="nav-panel" id="nav-panel">
        <div class="nav-panel-header">
            <h3>Menu</h3>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="task_list.php">Task List</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">Logout</a>
            </li>
        </ul>
    </div>
    <div class="content">
        <h2 class="card-title animate__animated animate__fadeIn">Task List</h2>

        <!-- Notifications -->
        <?php
        if ($notifications->num_rows > 0) {
            echo "<div class='alert alert-info animate__animated animate__fadeIn'>";
            echo "<h5>Notifications</h5>";
            while ($notification = $notifications->fetch_assoc()) {
                echo "<p>" . htmlspecialchars($notification['message']) . " (<small>" . $notification['created_at'] . "</small>)</p>";
                $conn->query("UPDATE notifications SET is_read = 1 WHERE id = " . $notification['id']);
            }
            echo "</div>";
        }
        ?>

        <!-- Task List -->
        <div class="card animate__animated animate__fadeInUp">
            <div class="card-body">
                <h3 class="card-title">Your Tasks</h3>
                <?php if (isset($success)) echo "<div class='alert alert-success animate__animated animate__fadeIn'>$success</div>"; ?>
                <?php if (isset($error)) echo "<div class='alert alert-danger animate__animated animate__fadeIn'>$error</div>"; ?>
                <div id="taskList">
                    <?php
                    if ($tasks->num_rows > 0) {
                        echo "<table class='table table-striped'>";
                        echo "<thead><tr><th>ID</th><th>Title</th><th>Deadline</th><th>Assigned By</th><th>Status</th><th>Remarks</th><th>Media</th><th>Action</th></tr></thead>";
                        echo "<tbody>";
                        while ($task = $tasks->fetch_assoc()) {
                            $deadline = new DateTime($task['deadline'], new DateTimeZone('UTC'));
                            $deadline->setTimezone(new DateTimeZone('America/Chicago'));
                            $now = new DateTime('now', new DateTimeZone('America/Chicago'));
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
                    const userTimezoneOffset = -360;
                    data.forEach(task => {
                        const deadline = new Date(task.deadline + 'Z');
                        const deadlineLocal = new Date(deadline.getTime() + userTimezoneOffset * 60 * 1000);
                        const now = new Date();
                        const nowLocal = new Date(now.getTime() + userTimezoneOffset * 60 * 1000);
                        const isPastDeadline = deadlineLocal < nowLocal;
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

        setInterval(updateTaskList, 5000);
        updateTaskList();
    </script>
</body>
</html>