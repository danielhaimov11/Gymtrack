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

// Get available exercises for dropdown
$exercises_sql = "SELECT *, UPPER(body_area) as body_area FROM exercises WHERE approved = 1 ORDER BY body_area, name";
$exercises_result = mysqli_query($conn, $exercises_sql); // שינוי מ-$sql ל-$exercises_sql
$exercises = mysqli_fetch_all($exercises_result, MYSQLI_ASSOC);

$grouped_exercises = [];
foreach ($exercises as $exercise) {
    $body_area = $exercise['body_area'] ?: 'Other';
    if (!isset($grouped_exercises[$body_area])) {
        $grouped_exercises[$body_area] = [];
    }
    $grouped_exercises[$body_area][] = $exercise;
}

// Get workout details with exercises and sets
$sql = "SELECT w.*, 
        we.id as exercise_id, 
        e.id as original_exercise_id,
        e.name as exercise_name,
        e.body_area,
        ws.id as set_id,
        ws.set_number,
        ws.reps,
        ws.weight,
        ws.is_warmup,
        ws.is_dropset
        FROM workouts w
        LEFT JOIN workout_exercises we ON w.id = we.workout_id
        LEFT JOIN exercises e ON we.exercise_id = e.id
        LEFT JOIN workout_sets ws ON we.id = ws.workout_exercise_id
        WHERE w.id = ? AND w.user_id = ?
        ORDER BY we.id, ws.set_number";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $workout_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$workout_data = mysqli_fetch_all($result, MYSQLI_ASSOC);

if (empty($workout_data)) {
    header('Location: workouts.php?error=Workout not found');
    exit();
}

// Group exercises and their sets
$workout = $workout_data[0];
$exercises_data = [];
foreach ($workout_data as $row) {
    if ($row['exercise_id']) {
        if (!isset($exercises_data[$row['exercise_id']])) {
            $exercises_data[$row['exercise_id']] = [
                'id' => $row['exercise_id'],
                'exercise_id' => $row['original_exercise_id'],
                'name' => $row['exercise_name'],
                'sets' => []
            ];
        }
        if ($row['set_id']) {
            $exercises_data[$row['exercise_id']]['sets'][] = [
                'id' => $row['set_id'],
                'number' => $row['set_number'],
                'reps' => $row['reps'],
                'weight' => $row['weight'],
                'is_warmup' => $row['is_warmup'],
                'is_dropset' => $row['is_dropset']
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo translate('workout_edit'); ?> - GymTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2><?php echo translate('workout_edit'); ?></h2>
        <form id="editWorkoutForm" method="POST" action="update_workout.php">
            <input type="hidden" name="workout_id" value="<?php echo $workout_id; ?>">
            
            <div class="mb-3">
                <label><?php echo translate('workouts_name'); ?></label>
                <input type="text" name="workout_name" class="form-control" 
                       value="<?php echo htmlspecialchars($workout['name']); ?>" 
                       placeholder="<?php echo translate('workouts_enter_name'); ?>">
            </div>
            
            <div class="mb-3">
                <label><?php echo translate('workouts_date'); ?></label>
                <input type="date" name="workout_date" class="form-control" 
                       value="<?php echo htmlspecialchars($workout['workout_date']); ?>" required>
            </div>

            <div id="exercises-container">
                <?php foreach ($exercises_data as $index => $exercise): ?>
                    <div class="exercise-entry border p-2 mb-2" data-exercise-id="<?php echo $exercise['id']; ?>">
                        <div class="mb-2">
                            <select name="exercises[<?php echo $index; ?>][exercise_id]" class="form-control mb-2" required>
                                <?php foreach ($grouped_exercises as $area => $area_exercises): ?>
                                    <optgroup label="<?php echo htmlspecialchars($area); ?>">
                                        <?php foreach ($area_exercises as $ex): ?>
                                            <option value="<?php echo $ex['id']; ?>" 
                                                <?php echo ($ex['id'] == $exercise['exercise_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($ex['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sets-container mb-2">
                            <h6><?php echo translate('workouts_sets'); ?></h6>
                            <?php foreach ($exercise['sets'] as $set): ?>
                                <div class="set-entry form-set-group" data-set-id="<?php echo $set['id']; ?>">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <strong><?php echo translate('workouts_set'); ?> <?php echo $set['number']; ?></strong>
                                        </div>
                                        <div class="col">
                                            <input type="number" name="exercises[<?php echo $exercise['id']; ?>][sets][<?php echo $set['id']; ?>][reps]" 
                                                   class="form-control" value="<?php echo $set['reps']; ?>" 
                                                   placeholder="<?php echo translate('workouts_reps'); ?>">
                                        </div>
                                        <div class="col">
                                            <input type="number" step="0.5" name="exercises[<?php echo $exercise['id']; ?>][sets][<?php echo $set['id']; ?>][weight]" 
                                                   class="form-control" value="<?php echo $set['weight']; ?>" 
                                                   placeholder="<?php echo translate('workouts_weight'); ?>">
                                        </div>
                                        <div class="col-auto">
                                            <div class="form-check form-check-inline">
                                                <input type="checkbox" class="form-check-input" 
                                                       name="exercises[<?php echo $exercise['id']; ?>][sets][<?php echo $set['id']; ?>][warmup]" 
                                                       <?php echo $set['is_warmup'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label"><?php echo translate('workouts_warmup'); ?></label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input type="checkbox" class="form-check-input" 
                                                       name="exercises[<?php echo $exercise['id']; ?>][sets][<?php echo $set['id']; ?>][dropset]" 
                                                       <?php echo $set['is_dropset'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label"><?php echo translate('workouts_dropset'); ?></label>
                                            </div>
                                            <button type="button" class="btn btn-danger btn-sm" 
                                                    onclick="removeSet(this, <?php echo $set['id']; ?>)">×</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="exercises[<?php echo $index; ?>][id]" value="<?php echo $exercise['id']; ?>">
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addSet(this)"><?php echo translate('workouts_add_set'); ?></button>
                            <button type="button" class="btn btn-danger btn-sm" 
                                    onclick="removeExercise(this, <?php echo $exercise['id']; ?>)"><?php echo translate('workouts_remove_exercise'); ?></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="btn btn-secondary mb-3" onclick="addExercise()">
                <?php echo translate('workouts_add_exercise'); ?>
            </button>

            <div class="mb-3">
                <label><?php echo translate('workouts_notes'); ?></label>
                <textarea name="notes" class="form-control"><?php echo htmlspecialchars($workout['notes']); ?></textarea>
            </div>
        </form>

        <div class="fixed-action-buttons">
            <a href="workout_detail.php?id=<?php echo $workout_id; ?>" class="btn btn-secondary"><?php echo translate('workout_cancel'); ?></a>
            <button type="submit" form="editWorkoutForm" class="btn btn-primary floating-save-button">
                <i class="fas fa-save"></i> <?php echo translate('workout_save'); ?>
            </button>
        </div>
    </div>

    <script>
// Utility functions first
function generateSetsHtml(exerciseIndex, numSets) {
    let html = '';
    const setCount = numSets || 1;
    
    for (let i = 0; i < setCount; i++) {
        const setNumber = i + 1;
        html += `
            <div class="set-entry form-set-group">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <strong>Set ${setNumber}</strong>
                    </div>
                    <div class="col">
                        <div class="input-group">
                            <input type="number" name="new_exercises[${exerciseIndex}][sets][${setNumber-1}][reps]" 
                                   class="form-control" placeholder="Reps" min="0">
                            <span class="input-group-text">reps</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="input-group">
                            <input type="number" step="0.5" name="new_exercises[${exerciseIndex}][sets][${setNumber-1}][weight]" 
                                   class="form-control" placeholder="Weight (kg)" min="0">
                            <span class="input-group-text">kg</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="form-check form-check-inline">
                            <input type="checkbox" class="form-check-input" 
                                   name="new_exercises[${exerciseIndex}][sets][${setNumber-1}][warmup]">
                            <label class="form-check-label">Warmup</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input type="checkbox" class="form-check-input" 
                                   name="new_exercises[${exerciseIndex}][sets][${setNumber-1}][dropset]">
                            <label class="form-check-label">Drop Set</label>
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeSet(this)">×</button>
                    </div>
                </div>
            </div>
        `;
    }
    return html;
}

function generateDefaultSets(exerciseIndex) {
    return generateSetsHtml(exerciseIndex, 4);
}

// Add tracking for deleted items
const deletedSets = new Set();
const deletedExercises = new Set();

// Main functions
function addSet(button) {
    const exerciseEntry = button.closest('.exercise-entry');
    const exerciseId = exerciseEntry.dataset.exerciseId;
    const setsContainer = exerciseEntry.querySelector('.sets-container');
    const setCount = setsContainer.querySelectorAll('.set-entry').length;
    const setNumber = setCount + 1;
    
    const isNew = exerciseId.toString().startsWith('new_');
    const namePrefix = isNew ? 
        `new_exercises[${exerciseId.replace('new_', '')}]` : 
        `exercises[${exerciseId}]`;
    
    const html = `
        <div class="set-entry form-set-group">
            <div class="row align-items-center">
                <div class="col-auto">
                    <strong>Set ${setNumber}</strong>
                </div>
                <div class="col">
                    <input type="number" name="${namePrefix}[sets][new_${setNumber}][reps]" 
                           class="form-control" placeholder="Reps" min="0" value="0">
                </div>
                <div class="col">
                    <input type="number" step="0.5" name="${namePrefix}[sets][new_${setNumber}][weight]" 
                           class="form-control" placeholder="Weight" min="0" value="0">
                </div>
                <div class="col-auto">
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input" 
                               name="${namePrefix}[sets][new_${setNumber}][is_warmup]" value="1">
                        <label class="form-check-label">Warmup</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input" 
                               name="${namePrefix}[sets][new_${setNumber}][is_dropset]" value="1">
                        <label class="form-check-label">Drop Set</label>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeSet(this)">×</button>
                </div>
            </div>
            <input type="hidden" name="${namePrefix}[sets][new_${setNumber}][set_number]" value="${setNumber}">
        </div>
    `;
    
    setsContainer.insertAdjacentHTML('beforeend', html);
}

function removeSet(button, setId) {
    if (setId) {
        console.log('Removing set:', setId);
        deletedSets.add(setId);
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'deleted_sets[]';
        input.value = setId;
        button.closest('form').appendChild(input);
        console.log('Added hidden input for deleted set:', setId);
    }
    button.closest('.set-entry').remove();
    console.log('Set element removed from DOM');
}

function removeExercise(button, exerciseId) {
    if (exerciseId) {
        console.log('Removing exercise:', exerciseId);
        deletedExercises.add(exerciseId);
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'deleted_exercises[]';
        input.value = exerciseId;
        button.closest('form').appendChild(input);
        console.log('Added hidden input for deleted exercise:', exerciseId);
    }
    button.closest('.exercise-entry').remove();
    console.log('Exercise element removed from DOM');
}

function generateSetHtml(setNumber, exerciseId, isNew = true) {
    // שינוי מבנה השמות עבור סטים חדשים
    const namePrefix = isNew ? 
        `new_exercises[${exerciseId}]` : 
        `exercises[${exerciseId}]`;
    
    console.log('Generating set for:', {exerciseId, setNumber, isNew, namePrefix});
    
    return `
        <div class="set-entry form-set-group">
            <div class="row align-items-center">
                <div class="col-auto">
                    <strong>Set ${setNumber}</strong>
                </div>
                <div class="col">
                    <input type="number" name="${namePrefix}[sets][${setNumber-1}][reps]" 
                           class="form-control" placeholder="Reps" min="0">
                </div>
                <div class="col">
                    <input type="number" step="0.5" name="${namePrefix}[sets][${setNumber-1}][weight]" 
                           class="form-control" placeholder="Weight (kg)" min="0">
                </div>
                <div class="col-auto">
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input" 
                               name="${namePrefix}[sets][${setNumber-1}][warmup]">
                        <label class="form-check-label">Warmup</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input" 
                               name="${namePrefix}[sets][${setNumber-1}][dropset]">
                        <label class="form-check-label">Drop Set</label>
                    </div>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeSet(this)">×</button>
                </div>
            </div>
            <input type="hidden" name="${namePrefix}[sets][${setNumber-1}][set_number]" value="${setNumber}">
        </div>
    `;
}

function addExercise() {
    const container = document.getElementById('exercises-container');
    const index = container.children.length;
    const exerciseHtml = `
        <div class="exercise-entry border p-2 mb-2" data-exercise-id="new_${index}">
            <div class="mb-2">
                <select name="new_exercises[${index}][exercise_id]" class="form-control mb-2" required>
                    <?php foreach ($grouped_exercises as $area => $area_exercises): ?>
                        <optgroup label="<?php echo htmlspecialchars($area); ?>">
                            <?php foreach ($area_exercises as $ex): ?>
                                <option value="<?php echo $ex['id']; ?>">
                                    <?php echo htmlspecialchars($ex['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sets-container mb-2">
                <h6>Sets</h6>
                ${generateDefaultSets(index)}
            </div>
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-secondary btn-sm" onclick="addSet(this)">Add Set</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="removeExercise(this)">Remove Exercise</button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', exerciseHtml);
}

// Add logging functions
function logFormData() {
    const formData = new FormData(document.getElementById('editWorkoutForm'));
    console.log('Form data before submit:', Object.fromEntries(formData));
}

// Update the form submit handler
document.getElementById('editWorkoutForm').onsubmit = function(e) {
    logFormData();
    return true;
};

// Add form submission handler with logging
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editWorkoutForm');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        console.log('Form submission started');
        const formData = new FormData(form);
        
        // Log form data
        console.log('Form data:');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }
        
        // Submit the form
        form.submit();
    });
});

// הוספת פונקציית לוג מפורטת
function logFormDataDetailed(formData) {
    console.group('Form Submission Details:');
    console.log('=== START FORM DATA ===');
    
    // Log basic workout info
    console.log('Workout ID:', formData.get('workout_id'));
    console.log('Workout Date:', formData.get('workout_date'));
    console.log('Notes:', formData.get('notes'));
    
    // Log exercises data
    console.group('Exercises:');
    for (let [key, value] of formData.entries()) {
        if (key.includes('exercises')) {
            console.log(`${key}: ${value}`);
        }
    }
    console.groupEnd();
    
    // Log new exercises data
    console.group('New Exercises:');
    for (let [key, value] of formData.entries()) {
        if (key.includes('new_exercises')) {
            console.log(`${key}: ${value}`);
        }
    }
    console.groupEnd();
    
    // Log deleted items
    console.group('Deleted Items:');
    console.log('Deleted Sets:', Array.from(deletedSets));
    console.log('Deleted Exercises:', Array.from(deletedExercises));
    console.groupEnd();
    
    console.log('=== END FORM DATA ===');
    console.groupEnd();
}

// עדכון מאזין הטופס
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editWorkoutForm');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        console.clear(); // נקה קונסול קודם
        
        console.log('Form submission started:', new Date().toISOString());
        const formData = new FormData(this);
        
        // הדפסת לוג מפורט
        logFormDataDetailed(formData);
        
        // Show confirmation before submit
        if (confirm('Are you sure you want to save these changes?')) {
            console.log('Form submission confirmed, sending...');
            this.submit();
        } else {
            console.log('Form submission cancelled by user');
        }
    });
});

// עדכון פונקציות הסרה עם לוגים
function removeSet(button, setId) {
    if (setId) {
        console.log('Removing set:', setId);
        deletedSets.add(setId);
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'deleted_sets[]';
        input.value = setId;
        button.closest('form').appendChild(input);
        console.log('Added hidden input for deleted set:', setId);
    }
    button.closest('.set-entry').remove();
    console.log('Set element removed from DOM');
}

function removeExercise(button, exerciseId) {
    if (exerciseId) {
        console.log('Removing exercise:', exerciseId);
        deletedExercises.add(exerciseId);
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'deleted_exercises[]';
        input.value = exerciseId;
        button.closest('form').appendChild(input);
        console.log('Added hidden input for deleted exercise:', exerciseId);
    }
    button.closest('.exercise-entry').remove();
    console.log('Exercise element removed from DOM');
}

// ...rest of existing code...
</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
