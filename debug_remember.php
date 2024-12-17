
<?php
require_once 'config/db_config.php';

echo "<h2>Remember Me Debug Info:</h2>";
echo "<pre>";

// בדיקת טבלה
$tableCheck = mysqli_query($conn, "SHOW TABLES LIKE 'auth_tokens'");
echo "Auth Tokens Table exists: " . (mysqli_num_rows($tableCheck) > 0 ? "Yes" : "No") . "\n\n";

// בדיקת tokens קיימים
$tokens = mysqli_query($conn, "SELECT * FROM auth_tokens");
echo "Active Tokens: " . mysqli_num_rows($tokens) . "\n";
while ($token = mysqli_fetch_assoc($tokens)) {
    echo "Token ID: " . $token['id'] . "\n";
    echo "User ID: " . $token['user_id'] . "\n";
    echo "Expires: " . $token['expires'] . "\n";
    echo "Created: " . $token['created_at'] . "\n\n";
}

// בדיקת Cookie
echo "Remember Me Cookie: " . (isset($_COOKIE['remember_me']) ? "Exists" : "Not Found") . "\n";
if (isset($_COOKIE['remember_me'])) {
    list($selector, $validator) = explode(':', $_COOKIE['remember_me']);
    echo "Selector: " . $selector . "\n";
    echo "Validator exists: " . (!empty($validator) ? "Yes" : "No") . "\n";
}

echo "</pre>";