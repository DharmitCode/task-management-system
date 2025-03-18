<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db.php';

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

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_user_id'])) {
    $user_id = $_POST['reset_user_id'];
    $otp = bin2hex(random_bytes(4)); // Generate a 8-character OTP
    $hashed_otp = password_hash($otp, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE users SET password = ?, force_password_reset = 1 WHERE id = ?");
    $stmt->bind_param("si", $hashed_otp, $user_id);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $success = "Password reset for user ID $user_id. One-time password: $otp. User must reset password on next login.";
            $check_stmt = $conn->prepare("SELECT force_password_reset FROM users WHERE id = ?");
            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $updated_user = $result->fetch_assoc();
            error_log("Debug: force_password_reset for user $user_id is " . $updated_user['force_password_reset']);
        } else {
            $error = "No user found with ID $user_id.";
        }
    } else {
        $error = "Error resetting password: " . $conn->error;
    }
}

// Fetch all users
$users = $conn->query("SELECT id, username, role FROM users");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Task Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.css" />
    <link rel="stylesheet" href="../assets/css/admin_styles.css">
</head>
<body>
    <?php include '../includes/menu.php'; ?>
    <script>
        document.getElementById('header-title').textContent = 'Manage Users';
        document.getElementById('nav-link-1').setAttribute('href', 'dashboard.php');
        document.getElementById('nav-link-1').textContent = 'Dashboard';

        // 5-second reload
        setInterval(() => {
            location.reload();
        }, 5000);

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
    </script>
    <div class="content">
        <h2 class="card-title animate__animated animate__slideInDown">User Management</h2>

        <!-- User Creation Form -->
        <div class="card animate__animated animate__slideInUp" style="animation-delay: 0.1s">
            <div class="card-body">
                <h3 class="card-title">Create New User</h3>
                <?php if (isset($success)) echo "<div class='alert alert-success animate__animated animate__fadeIn'>$success</div>"; ?>
                <?php if (isset($error)) echo "<div class='alert alert-danger animate__animated animate__fadeIn'>$error</div>"; ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Username:</label>
                        <input type="text" name="new_username" class="form-control rounded-pill" required>
                    </div>
                    <div class="mb-3 position-relative">
                        <label class="form-label">Password:</label>
                        <input type="password" name="new_password" class="form-control rounded-pill" id="new_password" required>
                        <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y reveal-btn" data-target="#new_password" style="padding: 0 8px;">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role:</label>
                        <select name="new_role" class="form-select rounded-pill" required>
                            <option value="team">Team Member</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary rounded-pill w-100 animate__animated animate__bounceIn" style="transition: transform 0.3s;">Create User</button>
                </form>
            </div>
        </div>

        <!-- User List -->
        <div class="card mt-4 animate__animated animate__slideInUp" style="animation-delay: 0.2s">
            <div class="card-body">
                <h3 class="card-title">User List</h3>
                <?php if ($users->num_rows > 0): ?>
                    <table class="table table-striped rounded-pill overflow-hidden">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo $user['username']; ?></td>
                                    <td><?php echo $user['role']; ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to reset the password for <?php echo $user['username']; ?>?');">
                                            <input type="hidden" name="reset_user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-warning btn-sm rounded-pill animate__animated animate__bounceIn">Reset Password</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No users found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"></script>
    <script>
        // Reveal password toggle
        document.querySelectorAll('.reveal-btn').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.querySelector(button.getAttribute('data-target'));
                const icon = button.querySelector('i');
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
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