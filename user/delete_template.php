
<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['template_id'])) {
    $template_id = sanitizeInput($_POST['template_id']);
    $user_id = $_SESSION['user_id'];
    
    // Only allow users to delete their own templates
    $sql = "DELETE FROM workout_templates WHERE id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $template_id, $user_id);
    
    $success = mysqli_stmt_execute($stmt);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
    exit();
}

http_response_code(400);
echo json_encode(['success' => false]);