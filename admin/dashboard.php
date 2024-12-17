<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
checkLogin();

if (!isAdmin()) {
    header("Location: ../user/dashboard.php");
    exit();
}

// Add handler for clearing all cookies
if (isset($_POST['action']) && $_POST['action'] === 'clear_cookies') {
    if (clearAllAuthTokens()) {
        $clear_message = "All remember-me tokens have been cleared successfully.";
    } else {
        $clear_message = "Error clearing remember-me tokens.";
    }
}

// Handle exercise approval/rejection
if (isset($_POST['action']) && isset($_POST['exercise_id'])) {
    $exercise_id = sanitizeInput($_POST['exercise_id']);
    
    if ($_POST['action'] === 'approve') {
        $approved = 1;
        $sql = "UPDATE exercises SET approved = ? WHERE id = ?";
    } else if ($_POST['action'] === 'reject') {
        $approved = 0;
        $sql = "UPDATE exercises SET approved = ? WHERE id = ?";
    } else if ($_POST['action'] === 'approve_deletion') {
        $sql = "DELETE FROM exercises WHERE id = ?";
    } else if ($_POST['action'] === 'reject_deletion') {
        $sql = "UPDATE exercises SET deletion_requested = 0 WHERE id = ?";
    }
    
    $stmt = mysqli_prepare($conn, $sql);
    if (isset($approved)) {
        mysqli_stmt_bind_param($stmt, "ii", $approved, $exercise_id);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $exercise_id);
    }
    mysqli_stmt_execute($stmt);
}

// Get pending exercises
$sql = "SELECT * FROM exercises WHERE approved = 0";
$pending_exercises = mysqli_query($conn, $sql)->fetch_all(MYSQLI_ASSOC);

// Debug query for pending deletion exercises
$sql = "SELECT * FROM exercises WHERE deletion_requested = 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$deletion_requests = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Add debug output
echo "<!-- Debug: " . count($deletion_requests) . " deletion requests found -->";
echo "<!-- Debug SQL: " . mysqli_error($conn) . " -->";

// Get user statistics
$sql = "SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_users
        FROM users";
$user_stats = mysqli_query($conn, $sql)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Admin Dashboard - GymTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Admin Dashboard</h2>
        
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Pending Exercises for Approval</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_exercises)): ?>
                            <p>No pending exercises</p>
                        <?php else: ?>
                            <?php foreach ($pending_exercises as $exercise): ?>
                                <div class="border-bottom p-2">
                                    <h6><?php echo htmlspecialchars($exercise['name']); ?></h6>
                                    <p><?php echo htmlspecialchars($exercise['description']); ?></p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="exercise_id" value="<?php echo $exercise['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                            Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Pending Exercise Deletions</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($deletion_requests)): ?>
                            <p>No pending deletion requests</p>
                            <!-- Debug info -->
                            <small class="text-muted">
                                Last SQL Error: <?php echo mysqli_error($conn); ?>
                            </small>
                        <?php else: ?>
                            <?php foreach ($deletion_requests as $exercise): ?>
                                <div class="border-bottom p-2">
                                    <h6><?php echo htmlspecialchars($exercise['name']); ?></h6>
                                    <p>Target: <?php echo htmlspecialchars($exercise['target_muscle']); ?></p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="exercise_id" value="<?php echo $exercise['id']; ?>">
                                        <button type="submit" name="action" value="approve_deletion" 
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Are you sure you want to delete this exercise?');">
                                            Approve Deletion
                                        </button>
                                        <button type="submit" name="action" value="reject_deletion" class="btn btn-secondary btn-sm">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>User Statistics</h5>
                    </div>
                    <div class="card-body">
                        <p>Total Users: <?php echo $user_stats['total_users']; ?></p>
                        <p>Active Users (Last 7 days): <?php echo $user_stats['active_users']; ?></p>
                        <a href="users.php" class="btn btn-primary">Manage Users</a>
                        <a href="user_progress.php" class="btn btn-info">View User Progress</a>
                        <form method="POST" class="mt-3">
                            <button type="submit" 
                                    name="action" 
                                    value="clear_cookies" 
                                    class="btn btn-warning"
                                    onclick="return confirm('Are you sure you want to clear all remember-me tokens? This will log out all users who are using remember me.');">
                                Clear All Remember-Me Tokens
                            </button>
                        </form>
                        <?php if (isset($clear_message)): ?>
                            <div class="alert alert-info mt-3">
                                <?php echo $clear_message; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- Close main-content -->
</div> <!-- Close layout-container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ...existing sidebar toggle script...
</script>
</body>
</html>
