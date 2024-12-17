<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';

if(isset($_SESSION['user_id'])){
    header("Location: user/dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang']; ?>" dir="<?php echo $_SESSION['lang'] == 'he' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GymTrack - Your Personal Fitness Journey</title>
    <?php if (isRTL()): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-dumbbell"></i>
                <span>GymTrack</span>
            </a>
            <div class="ms-auto">
                <div class="d-flex align-items-center">
                    <!-- Dropdown לבחירת שפה -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php echo strtoupper($_SESSION['lang']); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                            <li><a class="dropdown-item" href="?lang=en">English</a></li>
                            <li><a class="dropdown-item" href="?lang=he">עברית</a></li>
                        </ul>
                    </div>
                    <!-- כפתורי הרשמה והתחברות -->
                    <a href="login.php" class="btn btn-sm btn-outline-primary" title="<?php echo translate('nav_login'); ?>"><?php echo translate('nav_login'); ?></a>
                    <a href="register.php" class="btn btn-sm btn-primary" title="<?php echo translate('nav_start_free'); ?>"><?php echo translate('nav_start_free'); ?></a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 text-center text-lg-start">
                    <h1 class="display-4 fw-bold mb-4"><?php echo translate('hero_title'); ?></h1>
                    <p class="lead mb-4"><?php echo translate('hero_subtitle'); ?></p>
                    <div class="d-grid gap-3 d-sm-flex">
                        <a href="register.php" class="btn btn-light btn-lg px-4">Get Started Free</a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-4">Learn More</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <video 
                        class="img-fluid rounded shadow"
                        width="600"
                        height="400"
                        autoplay
                        loop
                        muted
                        playsinline>
                        <source src="assets/videos/fitness-video.mp4" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold"><?php echo translate('features_title'); ?></h2>
                <p class="text-muted"><?php echo translate('features_subtitle'); ?></p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card h-100">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3><?php echo translate('features_tracking_title'); ?></h3>
                        <p><?php echo translate('features_tracking_desc'); ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card h-100">
                        <div class="feature-icon">
                            <i class="fas fa-bullseye"></i>
                        </div>
                        <h3><?php echo translate('features_goals_title'); ?></h3>
                        <p><?php echo translate('features_goals_desc'); ?></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card h-100">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h3><?php echo translate('features_mobile_title'); ?></h3>
                        <p><?php echo translate('features_mobile_desc'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section - Hidden by default -->
    <?php if (false): // שנה ל-true כדי להפעיל את הסקציה ?>
    <section class="py-5 bg-light testimonials-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">What Our Users Say</h2>
                <p class="text-muted">Join thousands of satisfied users</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                            </div>
                            <p class="card-text">"Great app for tracking my gym progress. The analytics help me stay motivated!"</p>
                            <div class="d-flex align-items-center">
                                <img 
                                    src="https://placehold.co/48/3b82f6/ffffff?text=User" 
                                    alt="User" 
                                    class="rounded-circle me-3" 
                                    width="48" 
                                    height="48">
                                <div>
                                    <h6 class="mb-0">John Doe</h6>
                                    <small class="text-muted">Fitness Enthusiast</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                            </div>
                            <p class="card-text">"The goal tracking feature is amazing! I can see my progress week by week and adjust my workouts accordingly."</p>
                            <div class="d-flex align-items-center">
                                <img src="https://placehold.co/48/6366f1/ffffff?text=User" alt="User" class="rounded-circle me-3" width="48" height="48">
                                <div>
                                    <h6 class="mb-0">Sarah Wilson</h6>
                                    <small class="text-muted">Personal Trainer</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star text-warning"></i>
                                <i class="fas fa-star-half-alt text-warning"></i>
                            </div>
                            <p class="card-text">"Perfect for keeping track of my strength training. The mobile app makes it easy to log workouts during sessions."</p>
                            <div class="d-flex align-items-center">
                                <img src="https://placehold.co/48/22c55e/ffffff?text=User" alt="User" class="rounded-circle me-3" width="48" height="48">
                                <div>
                                    <h6 class="mb-0">Mike Chen</h6>
                                    <small class="text-muted">CrossFit Athlete</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h2 class="fw-bold mb-4"><?php echo translate('cta_title'); ?></h2>
                    <p class="lead mb-4"><?php echo translate('cta_subtitle'); ?></p>
                    <a href="register.php" class="btn btn-primary btn-lg px-5"><?php echo translate('cta_button'); ?></a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>GymTrack</h5>
                    <p><?php echo translate('footer_tagline'); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="social-links">
                        <a href="#" class="text-light me-3"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-light me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-light"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-12 mt-3">
                    <hr>
                    <p class="text-center mb-0">&copy; <?php echo date('Y'); ?> GymTrack. <?php echo translate('footer_rights'); ?></p>
                </div>
            </div>
        </div>
    </footer>

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
        bottom: 20px;
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
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const themeSwitcher = document.getElementById('themeSwitcher');
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        
        // Load saved theme or use system preference
        const currentTheme = localStorage.getItem('theme') || 
                        (prefersDarkScheme.matches ? 'dark' : 'light');
        
        document.documentElement.setAttribute('data-theme', currentTheme);
        
        themeSwitcher.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
        });
        
        // Listen for system theme changes
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