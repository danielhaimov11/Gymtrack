<?php
global $conn;
$cache_version = '1.0'; // Default version
$sql = "SELECT value FROM site_settings WHERE setting_key = 'cache_version'";
$result = mysqli_query($conn, $sql);
if ($row = mysqli_fetch_assoc($result)) {
    $cache_version = $row['value'];
}
?>
// ...existing code...
<link href="../assets/css/style.css?v=<?php echo $cache_version; ?>" rel="stylesheet">
<script src="../assets/js/main.js?v=<?php echo $cache_version; ?>" defer></script>
// ...existing code...

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
}

[data-theme="dark"] .dark-icon {
    display: none;
}

[data-theme="dark"] .light-icon {
    display: inline-block !important;
}
</style>

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
