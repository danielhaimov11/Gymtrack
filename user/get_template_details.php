<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
checkLogin();

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Template ID not provided']);
    exit();
}

$template_id = sanitizeInput($_GET['id']);

try {
    // Remove user_id check to allow access to all templates
    $sql = "SELECT 
        te.id as template_exercise_id,
        te.exercise_id,
        e.name as exercise_name,
        ts.set_number,
        ts.default_reps,
        ts.default_weight,
        ts.is_warmup,
        ts.is_dropset
    FROM workout_templates wt
    JOIN template_exercises te ON wt.id = te.template_id
    JOIN exercises e ON te.exercise_id = e.id
    LEFT JOIN template_sets ts ON te.id = ts.template_exercise_id
    WHERE wt.id = ?
    ORDER BY te.id, ts.set_number";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $template_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $exercises = [];
    $current_exercise = null;
    
    while ($row = mysqli_fetch_assoc($result)) {
        if (!isset($exercises[$row['template_exercise_id']])) {
            $exercises[$row['template_exercise_id']] = [
                'exercise_id' => $row['exercise_id'],
                'name' => $row['exercise_name'],
                'sets' => []
            ];
        }
        
        if ($row['set_number']) {
            $exercises[$row['template_exercise_id']]['sets'][] = [
                'set_number' => $row['set_number'],
                'default_reps' => $row['default_reps'],
                'default_weight' => $row['default_weight'],
                'is_warmup' => $row['is_warmup'],
                'is_dropset' => $row['is_dropset']
            ];
        }
    }
    
    echo json_encode(['exercises' => array_values($exercises)]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}