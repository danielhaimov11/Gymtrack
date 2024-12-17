<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: user/dashboard.php");
    exit();
}

// Check remember me token
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    if (validateRememberMeToken()) {
        header("Location: user/dashboard.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $email = sanitizeInput($_POST['email']);
        $password = $_POST['password'];
        $remember = isset($_POST['remember']);
        
        // Check login attempts
        if (!checkLoginAttempts($email)) {
            throw new Exception("Too many failed attempts. Please try again later.");
        }

        $sql = "SELECT id, email, password, role, status FROM users WHERE email = ? AND status != 'deleted'";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);
                if (password_verify($password, $user['password'])) {
                    if ($user['status'] == 'approved') {
                        // Reset login attempts
                        resetLoginAttempts($email);
                        
                        // Set session data
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
                        
                        // Handle remember me
                        if ($remember) {
                            createRememberMeToken($user['id']);
                        }
                        
                        // Update last login
                        updateLastLogin($user['id']);
                        
                        // Redirect based on role
                        header("Location: " . ($user['role'] == 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php'));
                        exit();
                    } else {
                        throw new Exception("Account is {$user['status']}. Please contact support.");
                    }
                }
            }
            // Invalid credentials
            recordFailedLogin($email);
            throw new Exception("Invalid credentials");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Login error for {$email}: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GymTrack</title>
    <?php if (isRTL()): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
    .login-container {
        max-width: 400px;
        margin: 2rem auto;
        padding: 2rem;
        background: var(--white);
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }
    
    .brand-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    
    .brand-logo {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="language-switcher text-end mb-3">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="languageDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php echo strtoupper($_SESSION['lang']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
                    <li><a class="dropdown-item" href="?lang=en">English</a></li>
                    <li><a class="dropdown-item" href="?lang=he">עברית</a></li>
                </ul>
            </div>
        </div>
        <div class="auth-container">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <h2><?php echo translate('login_welcome'); ?></h2>
            </div>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="mb-3">
                    <label class="form-label"><?php echo translate('login_email'); ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo translate('login_password'); ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" name="remember" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember"><?php echo translate('login_remember'); ?></label>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3"><?php echo translate('login_button'); ?></button>
                <div class="text-center">
                    <a href="register.php" class="text-decoration-none">Don't have an account? Register</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Theme Switcher Button -->
    <div class="theme-switcher">
        <button class="btn btn-sm btn-outline-secondary" id="themeSwitcher">
            <i class="fas fa-moon dark-icon"></i>
            <i class="fas fa-sun light-icon d-none"></i>
        </button>
    </div>

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

    // Debug info for development
    console.log('Cookies:', document.cookie);
    
    // Form validation
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        const email = this.querySelector('input[name="email"]').value;
        const password = this.querySelector('input[name="password"]').value;
        
        if (!email || !password) {
            e.preventDefault();
            alert('Please fill in all fields');
        }
    });
    </script>
</body>
</html>