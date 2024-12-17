<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
checkLogin();
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Panel - GymTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
    .admin-sidebar {
        background: var(--dark-bg);
    }

    .admin-sidebar .logo-details {
        background: rgba(0,0,0,0.2);
        padding: 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .admin-sidebar .logo-details i {
        color: var(--primary-color);
        font-size: 1.75rem;
    }

    .admin-sidebar .logo_name {
        color: var(--white);
        font-size: 1.25rem;
        font-weight: 600;
        margin-left: 1rem;
    }

    .admin-sidebar .nav-links li {
        position: relative;
        transition: all 0.3s ease;
        padding: 0;
    }

    .admin-sidebar .nav-links a {
        color: var(--text-light);
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
        text-decoration: none;
        border-left: 3px solid transparent;
    }

    .admin-sidebar .nav-links a:hover,
    .admin-sidebar .nav-links a.active {
        background: rgba(255,255,255,0.05);
        color: var(--white);
        border-left-color: var(--primary-color);
    }

    .admin-sidebar .nav-links a i {
        font-size: 1.25rem;
        min-width: 2rem;
    }

    .admin-sidebar .nav-links .logout-link {
        margin-top: 2rem;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .admin-sidebar .nav-links .logout-link a {
        color: var(--danger-color);
    }

    /* Mobile Adjustments */
    @media (max-width: 768px) {
        .admin-title {
            font-size: 1.25rem;
            margin-left: 3.5rem;
            color: var(--text-dark);
        }

        .admin-sidebar .nav-links a {
            padding: 1rem;
        }
    }
    </style>
</head>
<body>
    <button class="sidebar-toggle d-md-none" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="layout-container">
        <div class="sidebar admin-sidebar">
            <button class="sidebar-close d-md-none" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
            <div class="logo-details">
                <i class="fas fa-shield-alt"></i>
                <span class="logo_name">Admin Panel</span>
            </div>
            <!-- Add language switcher here -->
            <div class="dropdown ps-3 pe-3 mb-3 mt-3">
                <button class="btn btn-sm btn-outline-light dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php echo strtoupper($_SESSION['lang']); ?>
                </button>
                <ul class="dropdown-menu w-100">
                    <li><a class="dropdown-item" href="?lang=en">English</a></li>
                    <li><a class="dropdown-item" href="?lang=he">עברית</a></li>
                </ul>
            </div>
            <ul class="nav-links">
                <li>
                    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="manage_exercises.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_exercises.php' ? 'active' : ''; ?>">
                        <i class="fas fa-dumbbell"></i>
                        <span>Manage Exercises</span>
                    </a>
                </li>
                <li>
                    <a href="users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Manage Users</span>
                    </a>
                </li>
                <li>
                    <a href="user_progress.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'user_progress.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span>User Progress</span>
                    </a>
                </li>
                <li>
                    <a href="../user/dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>User Area</span>
                    </a>
                </li>
                <li class="logout-link">
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="main-content">
            <h4 class="admin-title d-md-none mb-4">Admin Panel</h4>
            <!-- התוכן של הדף יוכנס כאן - אל תסגור את ה-div -->
    </div>
    <script>
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const overlay = document.querySelector('.sidebar-overlay');
        
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            document.body.classList.toggle('sidebar-open');
            overlay.classList.toggle('active');
        }

        sidebarToggle.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);

        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                document.body.classList.remove('sidebar-open');
                overlay.classList.remove('active');
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>