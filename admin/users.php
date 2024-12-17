<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
checkLogin();

if (!isAdmin()) {
    header("Location: ../user/dashboard.php");
    exit();
}

// Handle user status updates and role changes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['user_id'])) {
    try {
        $user_id = sanitizeInput($_POST['user_id']);
        $action = sanitizeInput($_POST['action']);
        
        if ($action == 'approve' || $action == 'delete' || $action == 'suspend') {
            $status = $action == 'approve' ? 'approved' : ($action == 'delete' ? 'deleted' : 'suspended');
            $sql = "UPDATE users SET status = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $status, $user_id);
        } elseif ($action == 'make_admin') {
            $sql = "UPDATE users SET role = 'admin' WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
        }
        
        mysqli_stmt_execute($stmt);
        
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $success = "User status/role updated successfully.";
        } else {
            throw new Exception("Failed to update user status/role.");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all users except current admin
$sql = "SELECT * FROM users WHERE id != ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$users = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - GymTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>User Management</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="table-responsive mt-4">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Role</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['status']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <?php if ($user['status'] == 'pending'): ?>
                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                                <?php endif; ?>
                                <?php if ($user['status'] != 'deleted'): ?>
                                    <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm">Delete</button>
                                <?php endif; ?>
                                <?php if ($user['status'] == 'approved'): ?>
                                    <button type="submit" name="action" value="suspend" class="btn btn-warning btn-sm">Suspend</button>
                                <?php endif; ?>
                                <?php if ($user['role'] != 'admin'): ?>
                                    <button type="submit" name="action" value="make_admin" class="btn btn-primary btn-sm">Make Admin</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div> <!-- Close main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
