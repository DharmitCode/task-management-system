<!-- includes/menu.php -->
<header class="header">
    <button class="menu-btn" id="menu-toggle">☰</button>
    <h4 class="m-0" id="header-title">Default Title</h4>
</header>
<div class="nav-panel" id="nav-panel">
    <div class="nav-panel-header">
        <h3>Menu</h3>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link active" href="#" id="nav-link-1">Home</a>
        </li>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
            <li class="nav-item">
                <a class="nav-link" href="manage_users.php">Manage Users</a>
            </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link" href="../logout.php">Logout</a>
        </li>
    </ul>
</div>
<script>
    document.getElementById('menu-toggle').addEventListener('click', function() {
        const navPanel = document.getElementById('nav-panel');
        const menuBtn = document.getElementById('menu-toggle');
        navPanel.classList.toggle('active');
        if (navPanel.classList.contains('active')) {
            navPanel.classList.add('animate__slideInLeft');
            navPanel.classList.remove('animate__slideOutLeft');
            menuBtn.textContent = '✕'; // Change to close icon
        } else {
            navPanel.classList.add('animate__slideOutLeft');
            navPanel.classList.remove('animate__slideInLeft');
            menuBtn.textContent = '☰'; // Change back to menu icon
        }
        setTimeout(() => {
            navPanel.classList.remove('animate__slideInLeft', 'animate__slideOutLeft');
        }, 400);
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
                document.getElementById('menu-toggle').textContent = '✕';
                setTimeout(() => document.getElementById('nav-panel').classList.remove('animate__slideInLeft'), 400);
                touchStartX = 0;
            }
        }
    });

    document.addEventListener('touchend', () => {
        touchStartX = 0;
    });
</script>