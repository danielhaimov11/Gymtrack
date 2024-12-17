<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
checkLogin();

if (!isAdmin()) {
    header("Location: ../user/dashboard.php");
    exit();
}

// Handle deletion requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['approve_deletion'])) {
            $exercise_id = sanitizeInput($_POST['exercise_id']);
            $sql = "DELETE FROM exercises WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $exercise_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error deleting exercise: " . mysqli_error($conn));
            }
            $success = "Exercise deleted successfully";
        } elseif (isset($_POST['reject_deletion'])) {
            $exercise_id = sanitizeInput($_POST['exercise_id']);
            $sql = "UPDATE exercises SET deletion_requested = 0 WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $exercise_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error rejecting deletion: " . mysqli_error($conn));
            }
            $success = "Deletion request rejected";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get exercises with pending deletion requests
$sql = "SELECT * FROM exercises WHERE deletion_requested = 1 ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
$pending_deletions = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exercises - GymTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'admin_navbar.php'; ?>

    <div class="container mt-4">
        <h2>Pending Exercise Deletions</h2>
        
        <?php if (empty($pending_deletions)): ?>
            <p class="alert alert-info">No pending deletion requests.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Target Muscle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_deletions as $exercise): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($exercise['name']); ?></td>
                                <td><?php echo htmlspecialchars($exercise['target_muscle']); ?></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="exercise_id" value="<?php echo $exercise['id']; ?>">
                                        <button type="submit" name="approve_deletion" class="btn btn-danger btn-sm">
                                            Approve Deletion
                                        </button>
                                        <button type="submit" name="reject_deletion" class="btn btn-secondary btn-sm">
                                            Reject Request
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div> <!-- Close main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>