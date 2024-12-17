<?php
require_once 'config/db_config.php';
require_once 'includes/functions.php';
require_once 'includes/language.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (name, email, password, status) VALUES (?, ?, ?, 'pending')";
        $stmt = mysqli_prepare($conn, $sql);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "sss", $name, $email, $password);
            if (mysqli_stmt_execute($stmt)) {
                $success = translate('register_success');
            } else {
                throw new Exception("Registration failed");
            }
            mysqli_stmt_close($stmt);
        }
    } catch (Exception $e) {
        $error = translate('register_error') . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - GymTrack</title>
    <?php if (isRTL()): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
    .register-container {
        max-width: 400px;
        margin: 2rem auto;
        padding: 2rem;
        background: var(--white);
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
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
        <div class="register-container">
            <div class="brand-header">
                <div class="brand-logo">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <h2 class="text-center mb-4"><?php echo translate('register_title'); ?></h2>
            </div>
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if(isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label"><?php echo translate('register_name'); ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo translate('register_email'); ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?php echo translate('register_password'); ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3"><?php echo translate('register_button'); ?></button>
                <div class="text-center">
                    <a href="login.php" class="text-decoration-none"><?php echo translate('register_have_account'); ?></a>
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
    </script>
</body>
</html>