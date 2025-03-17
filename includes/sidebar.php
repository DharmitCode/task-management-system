<div class="sidebar">
    <div class="sidebar-header">
        <h3>Task Manager</h3>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link active" href="<?php echo $_SESSION['role'] == 'admin' ? 'dashboard.php' : 'task_list.php'; ?>">Dashboard</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#">Tasks</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="../logout.php">Logout</a>
        </li>
    </ul>
</div>