<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_stmt_get_result($stmt)->fetch_assoc();

// Fetch total workouts
$sql = "SELECT COUNT(*) as total_workouts FROM workouts WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt)->fetch_assoc();
$totalWorkouts = $result['total_workouts'];

// Fetch workouts for the current month
$sql = "SELECT COUNT(*) as month_workouts FROM workouts WHERE user_id = ? AND MONTH(workout_date) = MONTH(CURRENT_DATE()) AND YEAR(workout_date) = YEAR(CURRENT_DATE())";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt)->fetch_assoc();
$monthWorkouts = $result['month_workouts'];
?>

<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo translate('nav_dashboard'); ?> - GymTrack</title>
    <?php if (isRTL()): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
    /* User Sidebar Specific Styles */
    .user-sidebar {
        background: var(--primary-color);
        background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    }

    .user-sidebar .logo-details {
        background: rgba(0,0,0,0.1);
        padding: 1rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .user-sidebar .logo-details i {
        color: var(--white);
        font-size: 1.75rem;
    }

    .user-sidebar .logo_name {
        color: var(--white);
        font-size: 1.25rem;
        font-weight: 600;
        margin-left: 1rem;
    }

    .user-sidebar .nav-links li {
        position: relative;
        transition: all 0.3s ease;
    }

    .user-sidebar .nav-links a {
        color: rgba(255,255,255,0.8);
        padding: 1rem 1.5rem;
        display: flex;
        align-items: center;
        transition: all 0.3s ease;
        border-left: 3px solid transparent;
    }

    .user-sidebar .nav-links a:hover,
    .user-sidebar .nav-links a.active {
        background: rgba(255,255,255,0.1);
        color: var(--white);
        border-left-color: var(--white);
    }

    .user-sidebar .nav-links a i {
        font-size: 1.25rem;
        min-width: 2rem;
    }

    .user-sidebar .nav-links .logout-link {
        margin-top: 2rem;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .user-sidebar .nav-links .logout-link a {
        color: rgba(255,255,255,0.9);
    }

    .user-sidebar .nav-links .logout-link a:hover {
        background: rgba(239, 68, 68, 0.2);
        border-left-color: var(--danger-color);
    }

    /* Quick Stats Card */
    .stats-card {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: var(--white);
    }

    .stats-card .card-title {
        color: rgba(255,255,255,0.9);
        font-size: 1rem;
        font-weight: 500;
    }

    .stats-card p {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    .stats-card .btn {
        background: rgba(255,255,255,0.2);
        border: none;
        color: var(--white);
    }

    .stats-card .btn:hover {
        background: rgba(255,255,255,0.3);
    }

    @media (max-width: 768px) {
        .user-title {
            font-size: 1.25rem;
            margin-left: 3.5rem;
            color: var(--text-dark);
        }

        .bottom-nav {
            background: var(--white);
            border-top: 1px solid var(--border-color);
        }

        .bottom-nav-item.active {
            color: var(--primary-color);
        }
    }

    .main-content {
        padding-top: 0; /* Remove top padding */
    }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <button class="sidebar-toggle d-md-none" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="layout-container">
        <div class="sidebar user-sidebar">
            <button class="sidebar-close d-md-none" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
            <div class="logo-details">
                <i class="fas fa-dumbbell"></i>
                <span class="logo_name">GymTrack</span>
            </div>
            <!-- הוסף את מחליף השפה כאן -->
            <div class="dropdown ps-3 pe-3 mb-3 mt-3">
                <button class="btn btn-sm btn-outline-light dropdown-toggle w-100" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php echo strtoupper($_SESSION['lang']); ?>
                </button>
                <ul class="dropdown-menu w-100" aria-labelledby="languageDropdown">
                    <li><a class="dropdown-item" href="?lang=en">English</a></li>
                    <li><a class="dropdown-item" href="?lang=he">עברית</a></li>
                </ul>
            </div>
            <ul class="nav-links">
                <li>
                    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span class="link_name"><?php echo translate('nav_dashboard'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="workouts.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'workouts.php' ? 'active' : ''; ?>">
                        <i class="fas fa-running"></i>
                        <span class="link_name"><?php echo translate('nav_my_workouts'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="progress.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'progress.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i>
                        <span class="link_name"><?php echo translate('nav_progress'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="exercises.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'exercises.php' ? 'active' : ''; ?>">
                        <i class="fas fa-dumbbell"></i>
                        <span class="link_name"><?php echo translate('nav_exercises'); ?></span>
                    </a>
                </li>
                <?php if (isAdmin()): ?>
                <li>
                    <a href="../admin/dashboard.php" class="<?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-shield-alt"></i>
                        <span class="link_name"><?php echo translate('nav_admin_panel'); ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="profile.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span class="link_name"><?php echo translate('nav_profile'); ?></span>
                    </a>
                </li>
                <li class="logout-link">
                    <a href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="link_name"><?php echo translate('nav_logout'); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="main-content">
            <h4 class="user-title d-md-none mb-4"><?php echo translate('nav_dashboard'); ?></h4>
            <div class="container mt-4">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card stats-card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo translate('nav_quick_stats'); ?></h5>
                                <p><?php echo translate('nav_total_workouts'); ?> <span id="totalWorkouts"><?php echo $totalWorkouts; ?></span></p>
                                <p><?php echo translate('nav_this_month'); ?> <span id="monthWorkouts"><?php echo $monthWorkouts; ?></span></p>
                                <a href="workouts.php" class="btn btn-primary"><?php echo translate('nav_start_workout'); ?></a>
                            </div>
                        </div>
                    </div>            
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo translate('nav_recent_activity'); ?></h5>
                                <div id="recentActivity">
                                    <div class="text-center p-3">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Bottom Navigation for Mobile -->
    <div class="bottom-nav">
        <a href="dashboard.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span><?php echo translate('nav_home'); ?></span>
        </a>
        <a href="workouts.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'workouts.php' ? 'active' : ''; ?>">
            <i class="fas fa-dumbbell"></i>
            <span><?php echo translate('nav_my_workouts'); ?></span>
        </a>
        <a href="progress.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'progress.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span><?php echo translate('nav_progress'); ?></span>
        </a>
        <a href="profile.php" class="bottom-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span><?php echo translate('nav_profile'); ?></span>
        </a>
    </div>

    <!-- Add Theme Switcher Button -->
    <div class="theme-switcher">
        <button class="btn btn-sm btn-outline-secondary" id="themeSwitcher">
            <i class="fas fa-moon dark-icon"></i>
            <i class="fas fa-sun light-icon d-none"></i>
        </button>
    </div>

    <style>
    .theme-switcher {
        position: fixed;
        bottom: 80px;
        right: 20px;
        z-index: 1000;
    }

    .theme-switcher button {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        background-color: var(--white);
        border: 1px solid var(--border-color);
    }

    [data-theme="dark"] .dark-icon {
        display: none;
    }

    [data-theme="dark"] .light-icon {
        display: inline-block !important;
    }

    /* RTL Support for Theme Switcher */
    [dir="rtl"] .theme-switcher {
        left: 20px;
        right: auto;
    }

    /* Mobile Bottom Nav Fix */
    @media (max-width: 768px) {
        .theme-switcher {
            bottom: 100px; /* מעל תפריט הניווט התחתון */
        }
    }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Define 'sidebar' and 'overlay' in the global scope
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.querySelector('#sidebarToggle');
        const closeBtn = document.querySelector('#sidebarClose');
        const body = document.body;

        function openSidebar() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            body.classList.add('sidebar-open');
        }

        function closeSidebar() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            body.classList.remove('sidebar-open');
        }

        toggleBtn.addEventListener('click', openSidebar);
        closeBtn.addEventListener('click', closeSidebar);
        overlay.addEventListener('click', closeSidebar);

        // Close sidebar on window resize if screen becomes larger
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        // Handle touch events
        let touchStartX = 0;
        let touchEndX = 0;

        document.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        document.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });

        function handleSwipe() {
            const swipeDistance = touchEndX - touchStartX;
            if (swipeDistance > 100 && touchStartX < 50) { // Open on right swipe from left edge
                openSidebar();
            } else if (swipeDistance < -100) { // Close on left swipe
                closeSidebar();
            }
        }
    });

        // Add function to load recent activity
        function loadRecentActivity() {
            fetch('get_recent_activity.php')
                .then(response => response.text())
                .then(html => {
                    document.getElementById('recentActivity').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error loading recent activity:', error);
                    document.getElementById('recentActivity').innerHTML = 
                        '<div class="alert alert-danger">Error loading recent activity</div>';
                });
        }

        // Load recent activity when page loads
        document.addEventListener('DOMContentLoaded', loadRecentActivity);

        // Add smooth scrolling and touch events for better mobile experience
        document.addEventListener('touchstart', function() {}, {passive: true});

        const sidebarClose = document.createElement('button');
        sidebarClose.className = 'sidebar-close';
        sidebarClose.innerHTML = '<i class="fas fa-times"></i>';
        sidebar.prepend(sidebarClose);

        sidebarClose.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });

        // Close sidebar when clicking outside
        overlay.addEventListener('touchstart', (e) => {
            e.preventDefault();
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });

        // Prevent scrolling when sidebar is open
        sidebar.addEventListener('touchmove', (e) => {
            e.stopPropagation();
        }, { passive: false });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const toggleBtn = document.querySelector('#sidebarToggle');
    const closeBtn = document.querySelector('#sidebarClose');
    const body = document.body;
    let touchStartX = 0;
    let touchEndX = 0;

    function lockScroll() {
        const scrollY = window.scrollY;
        body.style.position = 'fixed';
        body.style.width = '100%';
        body.style.top = `-${scrollY}px`;
        body.style.overflowY = 'hidden';
    }

    function unlockScroll() {
        const scrollY = body.style.top;
        body.style.position = '';
        body.style.width = '';
        body.style.top = '';
        body.style.overflowY = '';
        window.scrollTo(0, parseInt(scrollY || '0') * -1);
    }

    function openSidebar(e) {
        if (e) e.preventDefault();
        sidebar.classList.add('active');
        overlay.classList.add('active');
        body.classList.add('sidebar-open');
        lockScroll();
    }

    function closeSidebar(e) {
        if (e) e.preventDefault();
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        body.classList.remove('sidebar-open');
        unlockScroll();
    }

    // Event listeners with improved touch handling
    toggleBtn.addEventListener('touchend', openSidebar, {passive: false});
    toggleBtn.addEventListener('click', openSidebar);
    
    closeBtn.addEventListener('touchend', closeSidebar, {passive: false});
    closeBtn.addEventListener('click', closeSidebar);
    
    overlay.addEventListener('touchend', closeSidebar, {passive: false});
    overlay.addEventListener('click', closeSidebar);

    // Improved touch handling
    document.addEventListener('touchstart', e => {
        touchStartX = e.touches[0].clientX;
    }, {passive: true});

    document.addEventListener('touchmove', e => {
        if (!sidebar.classList.contains('active')) return;
        
        const touchX = e.touches[0].clientX;
        const isMovingLeft = touchStartX > touchX;
        
        if (isMovingLeft) {
            e.preventDefault();
        }
    }, {passive: false});

    document.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].clientX;
        const swipeDistance = touchStartX - touchEndX;
        
        if (swipeDistance > 50 && sidebar.classList.contains('active')) {
            closeSidebar();
        }
    }, {passive: true});

    // Handle orientation change
    window.addEventListener('orientationchange', () => {
        setTimeout(closeSidebar, 100);
    });

    // Handle resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    // הוספת קוד Dark Mode
    const themeSwitcher = document.getElementById('themeSwitcher');
    const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
    
    // טעינת הערך השמור או שימוש בהעדפת המערכת
    const currentTheme = localStorage.getItem('theme') || 
                    (prefersDarkScheme.matches ? 'dark' : 'light');
    
    document.documentElement.setAttribute('data-theme', currentTheme);
    
    themeSwitcher.addEventListener('click', function() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
    });
    
    // מעקב אחר שינויים בהעדפות המערכת
    prefersDarkScheme.addListener((e) => {
        if (!localStorage.getItem('theme')) {
            document.documentElement.setAttribute('data-theme', 
                e.matches ? 'dark' : 'light'
            );
        }
    });
});
</script>
</body>
</html>