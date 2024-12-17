<?php
session_start();
require_once 'config/db_config.php';
require_once 'includes/functions.php';

try {
    // Delete remember me token if exists
    if (isset($_COOKIE['remember_me'])) {
        list($selector, $validator) = explode(':', $_COOKIE['remember_me']);
        
        // Delete token from database
        deleteRememberMeToken($selector);
        
        // Delete cookie
        setcookie(
            'remember_me',
            '',
            time() - 3600,
            '/',
            '',
            true,
            true
        );
    }

    // Delete all user's tokens if user_id is set
    if (isset($_SESSION['user_id'])) {
        $sql = "DELETE FROM auth_tokens WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
    }
    
    // Clear session
    session_destroy();
    
    // Redirect to login
    header("Location: index.php");
    exit();
    
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    // Still redirect even if error occurs
    header("Location: index.php");
    exit();
}