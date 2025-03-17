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
    } else {
        $error = "Error assigning task.";
    }
}

// Fetch all team members for the dropdown
$team_members = $conn->query("SELECT id, username FROM users WHERE role = 'team'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Task Management System</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <div class="container">
        <h2>Welcome, Admin!</h2>
        <p>This is your dashboard.</p>
        <a href="../logout.php">Logout</a>

        <!-- Task Assignment Form -->
        <h3>Assign a New Task</h3>
        <?php if (isset($success)) echo "<p style='color: green;'>$success</p>"; ?>
        <?php if (isset($error)) echo "<p style='color: red;'>$error</p>"; ?>
        <form method="POST" action="">
            <label>Title:</label>
            <input type="text" name="title" required><br>
            <label>Description:</label>
            <textarea name="description" rows="4"></textarea><br>
            <label>Deadline:</label>
            <input type="datetime-local" name="deadline" required><br>
            <label>Assign to:</label>
            <select name="assigned_to" required>
                <option value="">Select a team member</option>
                <?php while ($member = $team_members->fetch_assoc()): ?>
                    <option value="<?php echo $member['id']; ?>"><?php echo $member['username']; ?></option>
                <?php endwhile; ?>
            </select><br>
            <button type="submit">Assign Task</button>
        </form>

        <!-- Task List -->
        <h3>Assigned Tasks</h3>
        <?php
        $tasks = $conn->query("SELECT t.id, t.title, t.deadline, t.status, u.username 
                               FROM tasks t 
                               JOIN users u ON t.assigned_to = u.id 
                               WHERE t.created_by = " . $_SESSION['user_id']);
        if ($tasks->num_rows > 0) {
            echo "<table border='1'><tr><th>ID</th><th>Title</th><th>Deadline</th><th>Assigned To</th><th>Status</th></tr>";
            while ($task = $tasks->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $task['id'] . "</td>";
                echo "<td>" . $task['title'] . "</td>";
                echo "<td>" . $task['deadline'] . "</td>";
                echo "<td>" . $task['username'] . "</td>";
                echo "<td>" . $task['status'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No tasks assigned yet.</p>";
        }
        ?>
    </div>
</body>
</html>