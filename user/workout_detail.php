<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';  // Add language include
checkLogin();

if (!isset($_GET['id'])) {
    header('Location: workouts.php');
    exit();
}

$workout_id = sanitizeInput($_GET['id']);

// Handle workout deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_workout_id'])) {
    $workout_id = sanitizeInput($_POST['delete_workout_id']);
    $sql = "DELETE FROM workouts WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $workout_id);
    mysqli_stmt_execute($stmt);
    header('Location: workouts.php');
    exit();
}

// Update the SQL query to include warmup and drop set information
$sql = "SELECT w.*, we.id as exercise_id, e.name as exercise_name,
        ws.set_number, ws.reps, ws.weight, ws.is_warmup, ws.is_dropset
        FROM workouts w
        LEFT JOIN workout_exercises we ON w.id = we.workout_id
        LEFT JOIN exercises e ON we.exercise_id = e.id
        LEFT JOIN workout_sets ws ON we.id = ws.workout_exercise_id
        WHERE w.id = ? AND w.user_id = ?
        ORDER BY we.id, ws.set_number";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $workout_id, $_SESSION['user_id']);

if (!mysqli_stmt_execute($stmt)) {
    $error = "Failed to retrieve workout details: " . mysqli_error($conn);
    header('Location: workouts.php?error=' . urlencode($error));
    exit();
}

$result = mysqli_stmt_get_result($stmt);
$workout_details = mysqli_fetch_all($result, MYSQLI_ASSOC);

if (empty($workout_details)) {
    header('Location: workouts.php?error=Workout not found');
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo translate('workout_details'); ?> - GymTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-0">
                            <?php 
                            if (!empty($workout_details[0]['name'])) {
                                echo htmlspecialchars($workout_details[0]['name']);
                            } else {
                                echo translate('workout_details');
                            }
                            ?>
                        </h2>
                        <p class="text-muted mb-0"><?php echo translate('workouts_date'); ?>: <?php echo htmlspecialchars($workout_details[0]['workout_date']); ?></p>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" class="d-inline" action="save_template.php">
                            <input type="hidden" name="from_workout" value="<?php echo $workout_id; ?>">
                            <button type="button" class="btn btn-success" 
                                    data-bs-toggle="modal" data-bs-target="#saveTemplateModal">
                                <?php echo translate('workout_save_template'); ?>
                            </button>
                        </form>
                        <a href="edit_workout.php?id=<?php echo $workout_id; ?>" 
                           class="btn btn-warning">
                            <?php echo translate('workout_edit'); ?>
                        </a>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="delete_workout_id" value="<?php echo $workout_id; ?>">
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm('<?php echo translate('workouts_delete_confirm'); ?>')">
                                <?php echo translate('workout_delete'); ?>
                            </button>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($workout_details[0]['notes'])): ?>
                    <p><?php echo translate('workouts_notes'); ?>: <?php echo htmlspecialchars($workout_details[0]['notes']); ?></p>
                <?php endif; ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?php echo translate('workout_exercise'); ?></th>
                            <th><?php echo translate('workout_set_number'); ?></th>
                            <th><?php echo translate('workout_reps'); ?></th>
                            <th><?php echo translate('workout_weight'); ?></th>
                            <th><?php echo translate('workout_type'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_exercise = null;
                        foreach ($workout_details as $detail): 
                            if ($detail['exercise_name'] !== $current_exercise) {
                                $current_exercise = $detail['exercise_name'];
                                echo "<tr><td colspan='5' class='table-secondary'><strong>" . 
                                     htmlspecialchars($detail['exercise_name']) . "</strong></td></tr>";
                            }
                            if ($detail['set_number']): 
                                ?>
                                <tr>
                                    <td></td>
                                    <td><?php echo htmlspecialchars($detail['set_number']); ?></td>
                                    <td><?php echo htmlspecialchars($detail['reps']); ?></td>
                                    <td><?php echo htmlspecialchars($detail['weight']); ?></td>
                                    <td>
                                        <?php if ($detail['is_warmup']): ?>
                                            <span class="badge bg-info"><?php echo translate('workouts_warmup'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($detail['is_dropset']): ?>
                                            <span class="badge bg-warning"><?php echo translate('workouts_dropset'); ?></span>
                                        <?php endif; ?>
                                        <?php if (!$detail['is_warmup'] && !$detail['is_dropset']): ?>
                                            <span class="badge bg-secondary"><?php echo translate('workout_regular'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif;
                        endforeach; ?>
                    </tbody>
                </table>
                <a href="workouts.php" class="btn btn-primary"><?php echo translate('workout_back'); ?></a>
            </div>
        </div>
    </div>

    <!-- Save Template Modal -->
    <div class="modal fade" id="saveTemplateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo translate('workout_save_template'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="saveTemplateForm" method="POST" action="save_template.php">
                        <input type="hidden" name="from_workout" value="<?php echo $workout_id; ?>">
                        <div class="mb-3">
                            <label class="form-label"><?php echo translate('workout_template_name'); ?></label>
                            <input type="text" class="form-control" name="template_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo translate('workout_template_desc'); ?></label>
                            <textarea class="form-control" name="template_description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo translate('workout_cancel'); ?></button>
                    <button type="submit" form="saveTemplateForm" class="btn btn-primary"><?php echo translate('workout_save'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>