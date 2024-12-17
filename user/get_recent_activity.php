<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';  // Add language include
checkLogin();

$user_id = $_SESSION['user_id'];

// Get recent workouts
$sql = "SELECT w.id, w.name, w.workout_date, w.template_id, wt.name as template_name,
        COUNT(DISTINCT we.id) as exercise_count,
        CASE 
            WHEN w.name IS NOT NULL AND w.name != '' THEN w.name
            WHEN w.template_id IS NOT NULL THEN CONCAT('From template: ', wt.name)
            ELSE DATE_FORMAT(w.workout_date, '%d/%m/%Y')
        END as display_name
        FROM workouts w 
        LEFT JOIN workout_exercises we ON w.id = we.workout_id 
        LEFT JOIN workout_templates wt ON w.template_id = wt.id
        WHERE w.user_id = ? AND w.workout_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY w.id 
        ORDER BY w.workout_date DESC 
        LIMIT 5";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recent_workouts = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

// Format each workout for display
$formattedWorkouts = array_map(function($workout) {
    return [
        'id' => $workout['id'],
        'display_name' => $workout['display_name'],
        'workout_date' => $workout['workout_date'],
        'exercise_count' => $workout['exercise_count']
    ];
}, $recent_workouts);

// Return HTML for recent activity
?>
<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title"><?php echo translate('nav_recent_activity'); ?></h5>
                    <?php if (empty($recent_workouts)): ?>
                        <p class="text-muted"><?php echo translate('dashboard_no_workouts'); ?></p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($formattedWorkouts as $workout): ?>
                                <a href="workout_detail.php?id=<?php echo $workout['id']; ?>" 
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo date('j F, Y', strtotime($workout['workout_date'])); ?></h6>
                                        <small><?php echo $workout['exercise_count']; ?> <?php echo translate('dashboard_exercises'); ?></small>
                                    </div>
                                    <?php if (!empty($workout['notes'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($workout['notes']); ?></small>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>