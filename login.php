<?php
session_start();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        error_log("Stored hash for $username: " . $user['password']);
        error_log("Input password: $password, Verified: " . (password_verify($password, $user['password']) ? 'true' : 'false'));
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            // No action needed if already hashed
        } else {
            // Check if password is plain text (legacy)
            if (strlen($user['password']) < 60 || !preg_match('/^\$2y\$/', $user['password'])) { // Rough check for bcrypt
                if ($password === $user['password']) { // Legacy plain-text match
                    $new_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $new_hash, $user['id']);
                    if ($stmt_update->execute()) {
                        error_log("Migrated plain-text password for $username to hash: $new_hash");
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = $user['role'];
                    } else {
                        $error = "Login failed due to password migration error.";
                    }
                } else {
                    $error = "Invalid username or password.";
                }
            } else {
                $error = "Invalid username or password.";
            }
        }

        if (isset($_SESSION['user_id'])) {
            if ($user['role'] == 'admin') {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: team/task_list.php");
            }
            exit();
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.css" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card animate__animated animate__fadeIn">
            <div class="card-body">
                <h2 class="card-title">Login</h2>
                <?php if (isset($error)) echo "<div class='alert alert-danger animate__animated animate__fadeIn'>$error</div>"; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Username:</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password:</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>