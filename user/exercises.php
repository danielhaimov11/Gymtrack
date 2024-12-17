<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';  // Add language include
checkLogin();

// Change deletion handler to request deletion instead
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_delete_id'])) {
    $exercise_id = sanitizeInput($_POST['request_delete_id']);
    $sql = "UPDATE exercises SET deletion_requested = 1 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $exercise_id);
    $result = mysqli_stmt_execute($stmt);
    
    if ($result) {
        $success = "Deletion request submitted for admin approval.";
    } else {
        $error = "Error submitting deletion request: " . mysqli_error($conn);
    }
    
    // Debug output
    echo "<!-- Debug: Request for exercise ID " . $exercise_id . " - Result: " . ($result ? 'Success' : 'Failed') . " -->";
}

// Update templates query to show all templates and include creator's name
$templates_sql = "SELECT wt.*, u.name as creator_name 
                 FROM workout_templates wt 
                 LEFT JOIN users u ON wt.user_id = u.id 
                 ORDER BY wt.created_at DESC";
$stmt = mysqli_prepare($conn, $templates_sql);

if (!mysqli_stmt_execute($stmt)) {
    die('Error executing template query: ' . mysqli_error($conn));
}
$templates = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

// Get exercises with status
$sql = "SELECT *, 
        CASE 
            WHEN deletion_requested = 1 THEN 'Deletion Pending'
            ELSE 'Active'
        END as status 
        FROM exercises 
        WHERE approved = 1 
        ORDER BY name ASC";
$result = mysqli_query($conn, $sql);
$exercises = mysqli_fetch_all($result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo translate('exercise_library'); ?> - GymTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .main-content {
            padding-top: 0.5rem; /* Reduced from 1rem */
        }
        .card {
            margin-bottom: 0.75rem; /* Reduced spacing between cards */
        }
        .row > [class^="col-"] {
            margin-bottom: 0.75rem; /* Reduced spacing between columns */
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-content">
        <div class="container mt-4">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo translate($success); ?></div>
            <?php endif; ?>
            
            <!-- Add Templates Section -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?php echo translate('template_title'); ?></h5>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createTemplateModal">
                        <?php echo translate('template_create'); ?>
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($templates)): ?>
                        <p class="text-muted"><?php echo translate('template_no_templates'); ?></p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($templates as $template): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($template['name']); ?></h6>
                                        <small class="text-muted">
                                            <?php if ($template['creator_name']): ?>
                                                <?php echo translate('template_created_by'); ?> <?php echo htmlspecialchars($template['creator_name']); ?><br>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($template['description']); ?>
                                        </small>
                                    </div>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="window.location.href='workouts.php?template_id=<?php echo $template['id']; ?>'">
                                            <?php echo translate('template_use'); ?>
                                        </button>
                                        <?php if ($template['user_id'] == $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteTemplate(<?php echo $template['id']; ?>)">
                                                <?php echo translate('template_delete'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <h2><?php echo translate('exercise_library'); ?></h2>
            <a href="create_workout.php" class="btn btn-primary mb-3"><?php echo translate('workouts_add_new'); ?></a>
            <a href="create_exercise.php" class="btn btn-secondary mb-3"><?php echo translate('exercise_create'); ?></a>
            <div class="row">
                <?php foreach ($exercises as $exercise): ?>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($exercise['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($exercise['description']); ?></p>
                            <p class="card-text">
                                <small class="text-muted">
                                    <?php echo translate('exercise_target'); ?> <?php echo htmlspecialchars($exercise['target_muscle']); ?>
                                </small>
                            </p>
                            <p class="card-text">
                                <small class="text-<?php echo $exercise['status'] == 'Active' ? 'success' : 'warning'; ?>">
                                    <?php echo translate('exercise_status'); ?> <?php echo htmlspecialchars($exercise['status']); ?>
                                </small>
                            </p>
                            <?php if ($exercise['status'] == 'Active'): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo translate('exercise_delete_confirm'); ?>');">
                                    <input type="hidden" name="request_delete_id" value="<?php echo $exercise['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><?php echo translate('exercise_request_delete'); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div> <!-- Close main-content -->
    </div>

    <!-- Create Template Modal -->
    <div class="modal fade" id="createTemplateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo translate('template_create'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createTemplateForm" method="POST" action="save_template.php">
                        <div class="mb-3">
                            <label class="form-label"><?php echo translate('workout_template_name'); ?></label>
                            <input type="text" class="form-control" name="template_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo translate('workout_template_desc'); ?></label>
                            <textarea class="form-control" name="template_description" rows="3"></textarea>
                        </div>
                        <div id="template-exercises">
                            <!-- Exercises will be added here dynamically -->
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addExerciseToTemplate()">
                            <?php echo translate('workouts_add_exercise'); ?>
                        </button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo translate('workout_cancel'); ?></button>
                    <button type="submit" form="createTemplateForm" class="btn btn-primary"><?php echo translate('workout_save'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Add new JavaScript functions
    function addExerciseToTemplate() {
        const container = document.getElementById('template-exercises');
        const exerciseCount = container.children.length;
        
        const exerciseHtml = `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="mb-2">
                        <select name="exercises[${exerciseCount}][exercise_id]" class="form-select" required>
                            <option value="">Select Exercise</option>
                            <?php foreach ($grouped_exercises as $area => $area_exercises): ?>
                                <optgroup label="<?php echo htmlspecialchars($area); ?>">
                                    <?php foreach ($area_exercises as $exercise): ?>
                                        <option value="<?php echo $exercise['id']; ?>">
                                            <?php echo htmlspecialchars($exercise['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label>Default Sets</label>
                        <input type="number" name="exercises[${exerciseCount}][sets]" class="form-control" value="3" min="1">
                    </div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.parentElement.remove()">
                        Remove
                    </button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', exerciseHtml);
    }

    function startWorkoutFromTemplate(templateId) {
        window.location.href = `workouts.php?template_id=${templateId}`;
    }

    function deleteTemplate(templateId) {
        if (confirm('Are you sure you want to delete this template?')) {
            fetch('delete_template.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `template_id=${templateId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error deleting template');
                }
            });
        }
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
