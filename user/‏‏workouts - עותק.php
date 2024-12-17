<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
checkLogin();

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['workout_date']) && isset($_POST['notes'])) {
    try {
        $workout_date = sanitizeInput($_POST['workout_date']);
        $notes = sanitizeInput($_POST['notes']);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        $sql = "INSERT INTO workouts (user_id, workout_date, notes) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $workout_date, $notes);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error saving workout: " . mysqli_error($conn));
        }
        
        $workout_id = mysqli_insert_id($conn);
        
        // Handle exercises
        if (isset($_POST['exercises']) && is_array($_POST['exercises'])) {
            foreach ($_POST['exercises'] as $index => $exercise) {
                if (empty($exercise['exercise_id'])) continue;
                
                // Insert exercise
                $sql = "INSERT INTO workout_exercises (workout_id, exercise_id) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                
                if (!$stmt) {
                    throw new Exception("Error preparing exercise statement: " . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt, "ii", $workout_id, $exercise['exercise_id']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error saving exercise: " . mysqli_error($conn));
                }
                
                $exercise_id = mysqli_insert_id($conn);
                
                // Handle sets for this exercise
                if (isset($exercise['sets']) && is_array($exercise['sets'])) {
                    foreach ($exercise['sets'] as $set_number => $set) {
                        if (empty($set['reps']) && empty($set['weight'])) continue;
                        
                        $sql = "INSERT INTO workout_sets (workout_exercise_id, set_number, reps, weight, is_warmup, is_dropset) 
                               VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $sql);
                        
                        // Convert values to variables before binding
                        $set_num = $set_number + 1;
                        $reps = intval($set['reps']);
                        $weight = floatval($set['weight']);
                        $is_warmup = isset($set['warmup']) ? 1 : 0;
                        $is_dropset = isset($set['dropset']) ? 1 : 0;
                        
                        mysqli_stmt_bind_param($stmt, "iiidii", 
                            $exercise_id,
                            $set_num,
                            $reps,
                            $weight,
                            $is_warmup,
                            $is_dropset
                        );
                        
                        if (!mysqli_stmt_execute($stmt)) {
                            throw new Exception("Error saving set: " . mysqli_error($conn));
                        }
                    }
                }
            }
        }
        
        // Commit transaction
        mysqli_commit($conn);
        $success = "Workout saved successfully!";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = $e->getMessage();
    }
}

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

// Get available exercises grouped by body area
$sql = "SELECT *, UPPER(body_area) as body_area FROM exercises WHERE approved = 1 ORDER BY body_area, name";
$result = mysqli_query($conn, $sql);
$exercises = mysqli_fetch_all($result, MYSQLI_ASSOC);

$grouped_exercises = [];
foreach ($exercises as $exercise) {
    $body_area = $exercise['body_area'] ?: 'Other';
    if (!isset($grouped_exercises[$body_area])) {
        $grouped_exercises[$body_area] = [];
    }
    $grouped_exercises[$body_area][] = $exercise;
}

// Get user's workouts
$sql = "SELECT w.*, COUNT(we.id) as exercise_count 
        FROM workouts w 
        LEFT JOIN workout_exercises we ON w.id = we.workout_id 
        WHERE w.user_id = ? 
        GROUP BY w.id 
        ORDER BY w.workout_date DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$workouts = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

// Add workout summary data
$period = $_GET['period'] ?? 'today';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$summary_data = getWorkoutSummary($conn, $user_id, $period, $start_date, $end_date);
$summary = $summary_data['summary'];
$period_text = $summary_data['period_text'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workouts - GymTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-content">
        <div class="container mt-4">
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-container">
                            <div class="card-header">
                                <h5 class="card-title">Add New Workout</h5>
                            </div>
                            <div class="card-content">
                                <form id="workoutForm" method="POST">
                                    <div class="mb-3">
                                        <label>Date</label>
                                        <input type="date" name="workout_date" class="form-control" required>
                                    </div>
                                    <div id="exercises-container">
                                        <!-- Exercise entries will be added here dynamically -->
                                    </div>
                                    <button type="button" class="btn btn-secondary mb-3" onclick="addExercise()">
                                        Add Exercise
                                    </button>
                                    <div class="mb-3">
                                        <label>Notes</label>
                                        <textarea name="notes" class="form-control"></textarea>
                                    </div>
                                    <div class="card-footer text-end">
                                        <button type="submit" class="btn btn-primary">Save Workout</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <?php 
                    // Ensure all required variables are set for workout_summary
                    $period = $_GET['period'] ?? 'today';
                    $start_date = $_GET['start_date'] ?? null;
                    $end_date = $_GET['end_date'] ?? null;
                    $summary_data = getWorkoutSummary($conn, $user_id, $period, $start_date, $end_date);
                    $summary = $summary_data['summary'];
                    $period_text = $summary_data['period_text']; // Add this line
                    
                    include '../includes/workout_summary.php';
                    ?>
                    
                    <div class="card mt-4">
                        <div class="card-container">
                            <div class="card-header">
                                <h5 class="card-title">Workout History</h5>
                            </div>
                            <div class="card-content">
                                <div class="list-group">
                                    <?php foreach ($workouts as $workout): ?>
                                        <div class="list-group-item">
                                            <!-- ...existing workout history HTML... -->
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- Close main-content -->

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add click handler for the Add Exercise button
        document.querySelector('[onclick="addExercise()"]').onclick = function() {
            addExercise();
        };
    });

    function addExercise() {
        const container = document.getElementById('exercises-container');
        const index = container.children.length;
        const exerciseHtml = `
            <div class="exercise-entry border p-2 mb-2">
                <div class="mb-2">
                    <input type="text" class="form-control exercise-search" 
                           placeholder="Search exercise..." 
                           onkeyup="filterExercises(this)">
                </div>
                <div class="mb-2">
                    <select class="form-select body-area-select" onchange="filterExercisesByArea(this)">
                        <option value="">All Body Areas</option>
                        <?php foreach (array_keys($grouped_exercises) as $area): ?>
                            <option value="<?php echo htmlspecialchars($area); ?>">
                                <?php echo htmlspecialchars($area); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <select name="exercises[${index}][exercise_id]" class="form-control mb-2 exercise-select" required>
                    <option value="">Select Exercise</option>
                    <?php foreach ($grouped_exercises as $area => $area_exercises): ?>
                        <optgroup label="<?php echo htmlspecialchars($area); ?>">
                            <?php foreach ($area_exercises as $exercise): ?>
                                <option value="<?php echo $exercise['id']; ?>" 
                                        data-area="<?php echo htmlspecialchars($exercise['body_area']); ?>">
                                    <?php echo htmlspecialchars($exercise['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
                <div class="sets-container mb-2">
                    <h6>Sets</h6>
                    ${generateSetsHtml(index, 4)}
                </div>
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="addSet(${index})">
                        Add Set
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeExercise(this)">
                        Remove Exercise
                    </button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', exerciseHtml);
    }

    // Fix the set numbering by initializing the data-exercise attribute
    function generateSetsHtml(exerciseIndex, numSets) {
        let html = '';
        const existingSetsContainer = document.querySelector(`[data-exercise="${exerciseIndex}"]`);
        const startIndex = existingSetsContainer ? 
            existingSetsContainer.querySelectorAll('.set-entry').length : 0;
        
        for (let i = 0; i < numSets; i++) {
            const setNumber = startIndex + i + 1;
            html += `
                <div class="set-entry row mb-3 align-items-center">
                    <div class="col-auto" style="width: 80px;">  <!-- Increased width for longer text -->
                        <label class="set-number fw-bold">Set Number ${setNumber}</label>  <!-- Changed from "Set" to "Set Number" -->
                    </div>
                    <div class="col">
                        <div class="input-group">
                            <input type="number" name="exercises[${exerciseIndex}][sets][${setNumber-1}][reps]" 
                                   class="form-control" placeholder="Reps" min="0" style="width: 80px;">
                            <span class="input-group-text">reps</span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="input-group">
                            <input type="number" step="0.5" name="exercises[${exerciseIndex}][sets][${setNumber-1}][weight]" 
                                   class="form-control" placeholder="Weight" min="0" style="width: 80px;">
                            <span class="input-group-text">kg</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <div class="btn-group">
                            <div class="form-check me-2">
                                <input type="checkbox" class="form-check-input" 
                                       name="exercises[${exerciseIndex}][sets][${setNumber-1}][warmup]" 
                                       id="warmup-${exerciseIndex}-${setNumber-1}">
                                <label class="form-check-label" for="warmup-${exerciseIndex}-${setNumber-1}">Warmup</label>
                            </div>
                            <div class="form-check me-2">
                                <input type="checkbox" class="form-check-input" 
                                       name="exercises[${exerciseIndex}][sets][${setNumber-1}][dropset]" 
                                       id="dropset-${exerciseIndex}-${setNumber-1}">
                                <label class="form-check-label" for="dropset-${exerciseIndex}-${setNumber-1}">Drop Set</label>
                            </div>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeSet(this)">×</button>
                        </div>
                    </div>
                </div>
            `;
        }
        return html;
    }

    function addSet(exerciseIndex) {
        if (!event) return; // Guard against undefined event
        const exerciseEntry = event.target.closest('.exercise-entry');
        if (!exerciseEntry) return; // Guard against null element
        
        const setsContainer = exerciseEntry.querySelector('.sets-container');
        if (!setsContainer) return;
        
        // Add data-exercise attribute if it doesn't exist
        if (!exerciseEntry.hasAttribute('data-exercise')) {
            exerciseEntry.setAttribute('data-exercise', exerciseIndex);
        }
        
        const setHtml = generateSetsHtml(exerciseIndex, 1);
        setsContainer.insertAdjacentHTML('beforeend', setHtml);
    }

    function removeSet(button) {
        const setEntry = button.closest('.set-entry');
        const setsContainer = button.closest('.sets-container');
        setEntry.remove();
        // Renumber remaining sets
        Array.from(setsContainer.getElementsByClassName('set-number')).forEach((label, index) => {
            label.textContent = `Set ${index + 1}`;
            
            // Update input names and ids
            const setDiv = label.closest('.set-entry');
            const inputs = setDiv.querySelectorAll('input');
            inputs.forEach(input => {
                const name = input.name;
                const newName = name.replace(/\[sets\]\[\d+\]/, `[sets][${index}]`);
                input.name = newName;
                
                if (input.type === 'checkbox') {
                    input.id = input.id.replace(/\-\d+$/, `-${index}`);
                    const label = input.nextElementSibling;
                    if (label) {
                        label.htmlFor = input.id;
                    }
                }
            });
        });
    }

    function filterExercises(searchInput) {
        const exerciseEntry = searchInput.closest('.exercise-entry');
        const select = exerciseEntry.querySelector('.exercise-select');
        const bodyAreaSelect = exerciseEntry.querySelector('.body-area-select');
        const searchText = searchInput.value.toLowerCase();
        const selectedArea = bodyAreaSelect.value;

        // Loop through all optgroup elements
        Array.from(select.getElementsByTagName('optgroup')).forEach(optgroup => {
            let hasVisibleOptions = false;
            
            // Loop through options in this optgroup
            Array.from(optgroup.getElementsByTagName('option')).forEach(option => {
                const matchesSearch = option.text.toLowerCase().includes(searchText);
                const matchesArea = !selectedArea || option.dataset.area === selectedArea;
                
                if (matchesSearch && matchesArea) {
                    option.style.display = '';
                    hasVisibleOptions = true;
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Show/hide the entire optgroup based on whether it has visible options
            optgroup.style.display = hasVisibleOptions ? '' : 'none';
        });

        // Show/hide the placeholder option
        const placeholderOption = select.querySelector('option[value=""]');
        if (placeholderOption) {
            placeholderOption.style.display = '';
        }
    }

    function filterExercisesByArea(areaSelect) {
        const exerciseEntry = areaSelect.closest('.exercise-entry');
        const searchInput = exerciseEntry.querySelector('.exercise-search');
        filterExercises(searchInput);
    }

    function removeExercise(button) {
        button.closest('.exercise-entry').remove();
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>