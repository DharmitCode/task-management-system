<header class="header bg-light shadow-sm" style="position: fixed; top: 0; left: 0; right: 0; z-index: 1000; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e5ea;">
    <button type="button" class="btn btn-outline-primary" data-bs-toggle="offcanvas" data-bs-target="#menuOffcanvas" aria-controls="menuOffcanvas">
        <span class="navbar-toggler-icon"></span>
    </button>
    <h4 class="m-0 text-dark">Task Management System</h4>
    <a href="../logout.php" class="btn btn-outline-danger">Logout</a>
</header>

<div class="offcanvas offcanvas-start" tabindex="-1" id="menuOffcanvas" aria-labelledby="menuOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="menuOffcanvasLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="nav flex-column">
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">Dashboard</a>
                </li>
            <?php elseif ($_SESSION['role'] == 'team'): ?>
                <li class="nav-item">
                    <a class="nav-link active" href="task_list.php">Task List</a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link" href="../logout.php">Logout</a>
            </li>
        </ul>
    </div>
</div>

<style>
    .header {
        background-color: #f2f2f7; /* iOS light background */
        transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); /* iOS spring */
    }
    .offcanvas {
        background-color: #ffffff;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    .offcanvas .nav-link {
        color: #007aff; /* iOS blue */
        font-weight: 500;
        border-radius: 12px;
        transition: background-color 0.3s ease, transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .offcanvas .nav-link:hover, .offcanvas .nav-link.active {
        background-color: #f2f2f7;
        transform: scale(1.02);
    }
    .content {
        padding-top: 70px; /* Adjust for header height */
        transition: margin-left 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .content.shifted {
        margin-left: 0; /* No shift needed with off-canvas */
    }
</style>