<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }
    checkSession();
}

function checkAdminLogin() {
    session_start();
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: ../login.php");
        exit();
    }
}

function isAdmin() {
    global $conn;
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT role FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    return $user && $user['role'] === 'admin';
}

function sanitizeInput($data) {
    global $conn;
    if (!isset($conn)) {
        throw new Exception("Database connection not available");
    }
    return mysqli_real_escape_string($conn, trim($data));
}

function logError($message) {
    error_log($message, 0);
}

function checkLoginAttempts($email) {
    global $conn;
    $sql = "SELECT COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
            FROM login_attempts 
            WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt)->fetch_assoc();
    
    return $result['attempts'] < 5;
}

function recordFailedLogin($email) {
    global $conn;
    $sql = "INSERT INTO login_attempts (email, attempt_time, ip_address) VALUES (?, NOW(), ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ss", $email, $_SERVER['REMOTE_ADDR']);
    mysqli_stmt_execute($stmt);
}

function resetLoginAttempts($email) {
    global $conn;
    $sql = "DELETE FROM login_attempts WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
}

function updateLastLogin($user_id) {
    global $conn;
    $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
}

function checkSession() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        session_destroy();
        header("Location: /login.php");
        exit();
    }
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== $_SERVER['REMOTE_ADDR']) {
        session_destroy();
        header("Location: /login.php");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function createRememberMeToken($user_id) {
    global $conn;
    try {
        // Delete any existing tokens for this user
        $sql = "DELETE FROM auth_tokens WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);

        // Create new token
        $selector = bin2hex(random_bytes(8));
        $validator = bin2hex(random_bytes(32));
        $hashedValidator = password_hash($validator, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
        
        $sql = "INSERT INTO auth_tokens (user_id, selector, hash, expires) 
                VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $selector, $hashedValidator, $expires);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to execute statement: " . mysqli_stmt_error($stmt));
        }
        
        // Set cookie
        setcookie(
            'remember_me',
            $selector . ':' . $validator,
            time() + (30 * 24 * 60 * 60),
            '/',
            '',
            true,
            true
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Remember Me Token Error: " . $e->getMessage());
        return false;
    }
}

function validateRememberMeToken() {
    global $conn;
    
    if (!isset($_COOKIE['remember_me'])) {
        return false;
    }
    
    list($selector, $validator) = explode(':', $_COOKIE['remember_me']);
    
    $sql = "SELECT * FROM auth_tokens WHERE selector = ? AND expires > NOW()";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $selector);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($token = mysqli_fetch_assoc($result)) {
        if (password_verify($validator, $token['hash'])) {
            // Token is valid - log the user in
            $sql = "SELECT * FROM users WHERE id = ? AND status = 'approved'";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $token['user_id']);
            mysqli_stmt_execute($stmt);
            $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
                
                // Generate new remember me token
                deleteRememberMeToken($selector);
                createRememberMeToken($user['id']);
                return true;
            }
        }
    }
    
    // Token invalid or expired - delete it
    deleteRememberMeToken($selector);
    return false;
}

function deleteRememberMeToken($selector) {
    global $conn;
    $sql = "DELETE FROM auth_tokens WHERE selector = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $selector);
    mysqli_stmt_execute($stmt);
}

function exerciseNameExists($conn, $name) {
    $sql = "SELECT COUNT(*) as count FROM exercises WHERE LOWER(name) = LOWER(?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] > 0;
}

function clearAllAuthTokens() {
    global $conn;
    $sql = "DELETE FROM auth_tokens";
    $stmt = mysqli_prepare($conn, $sql);
    return mysqli_stmt_execute($stmt);
}

?>