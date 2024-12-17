<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
checkLogin();

// Add error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['workout_date'])) {
    try {
        $workout_date = sanitizeInput($_POST['workout_date']);
        $workout_name = sanitizeInput($_POST['workout_name']);
        $notes = sanitizeInput($_POST['notes']);
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        $sql = "INSERT INTO workouts (user_id, name, workout_date, notes) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $workout_name, $workout_date, $notes);
        
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

// Add error handling for template query
$templates_sql = "SELECT wt.* 
                 FROM workout_templates wt 
                 ORDER BY wt.name ASC";
$stmt = mysqli_prepare($conn, $templates_sql);
if (!$stmt) {
    die('Error preparing template query: ' . mysqli_error($conn));
}

if (!mysqli_stmt_execute($stmt)) {
    die('Error executing template query: ' . mysqli_error($conn));
}

$templates = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

// Group exercises by body area
$grouped_exercises = [];
foreach ($exercises as $exercise) {
    $body_area = $exercise['body_area'] ?: 'Other';
    if (!isset($grouped_exercises[$body_area])) {
        $grouped_exercises[$body_area] = [];
    }
    $grouped_exercises[$body_area][] = $exercise;
}

// Get user's workouts - update the query to include name and template info
$sql = "SELECT w.*, COUNT(we.id) as exercise_count,
        CASE 
            WHEN w.name IS NOT NULL AND w.name != '' THEN w.name
            WHEN w.template_id IS NOT NULL THEN CONCAT('From template: ', wt.name)
            ELSE DATE_FORMAT(w.workout_date, '%d/%m/%Y')
        END as display_name
        FROM workouts w 
        LEFT JOIN workout_exercises we ON w.id = we.workout_id 
        LEFT JOIN workout_templates wt ON w.template_id = wt.id
        WHERE w.user_id = ? 
        GROUP BY w.id 
        ORDER BY w.workout_date DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$workouts = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo translate('workouts_title'); ?> - GymTrack</title>
    <?php if (isRTL()): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
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
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo translate('workouts_add_new'); ?></h5>
                            <!-- Add template selection buttons -->
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#templateModal">
                                    <?php echo translate('workouts_use_template'); ?>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="startEmptyWorkout()">
                                    <?php echo translate('workouts_empty_workout'); ?>
                                </button>
                            </div>
                            <form id="workoutForm" method="POST" style="display: none;">
                                <div class="mb-3">
                                    <label><?php echo translate('workouts_name'); ?></label>
                                    <input type="text" name="workout_name" class="form-control" placeholder="<?php echo translate('workouts_name'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label><?php echo translate('workouts_date'); ?></label>
                                    <input type="date" name="workout_date" class="form-control" required>
                                </div>
                                <div id="exercises-container">
                                    <!-- Exercise entries will be added here dynamically -->
                                </div>
                                <button type="button" class="btn btn-secondary mb-3" onclick="addExercise()">
                                    <?php echo translate('workouts_add_exercise'); ?>
                                </button>
                                <div class="mb-3">
                                    <label><?php echo translate('workouts_notes'); ?></label>
                                    <textarea name="notes" class="form-control"></textarea>
                                </div>
                            </form>
                            <div class="fixed-action-buttons">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <h3><?php echo translate('workouts_history'); ?></h3>
                    <div class="list-group">
                        <?php foreach ($workouts as $workout): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($workout['display_name']); ?></h5>
                                        <small class="text-muted"><?php echo htmlspecialchars($workout['workout_date']); ?></small>
                                    </div>
                                    <small><?php echo htmlspecialchars($workout['exercise_count']); ?> exercises</small>
                                </div>
                                <?php if (!empty($workout['notes'])): ?>
                                    <p class="mb-1"><?php echo htmlspecialchars($workout['notes']); ?></p>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between">
                                    <a href="workout_detail.php?id=<?php echo $workout['id']; ?>" 
                                       class="btn btn-primary btn-sm"><?php echo translate('workouts_view_details'); ?></a>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="delete_workout_id" 
                                               value="<?php echo $workout['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('<?php echo translate('workouts_delete_confirm'); ?>')">
                                            <?php echo translate('workouts_delete'); ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div> <!-- Close main-content -->

<!-- Move the floating save button outside of main-content -->
<button type="submit" form="workoutForm" class="btn btn-primary floating-save-button" style="display: none;">
    <i class="fas fa-save"></i> <?php echo translate('workouts_save'); ?>
</button>

    <!-- Template Selection Modal -->
    <div class="modal fade" id="templateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo translate('workouts_template_title'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($templates)): ?>
                        <p><?php echo translate('workouts_no_templates'); ?></p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($templates as $template): ?>
                                <button type="button" 
                                        class="list-group-item list-group-item-action" 
                                        onclick="useTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($template['name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($template['description']); ?>
                                    </small>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Define 'sidebar' and 'overlay' before using them
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        // Remove or comment out the sidebar reference if it's not needed
        // if (sidebar) {
        //     sidebar.classList.remove('show');
        // }
        
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
                           placeholder="${<?php echo json_encode(translate('workouts_search_exercise')); ?>}" 
                           onkeyup="filterExercises(this)">
                </div>
                <div class="mb-2">
                    <select class="form-select body-area-select" onchange="filterExercisesByArea(this)">
                        <option value="">${<?php echo json_encode(translate('workouts_all_areas')); ?>}</option>
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

    // Add new JavaScript functions
    function startEmptyWorkout() {
        const form = document.getElementById('workoutForm');
        const saveButton = document.querySelector('.floating-save-button');
        form.style.display = 'block';
        saveButton.style.display = 'block';
        form.reset();
        document.getElementById('exercises-container').innerHTML = '';
    }

    function useTemplate(template) {
        // Show the workout form
        const form = document.getElementById('workoutForm');
        const saveButton = document.querySelector('.floating-save-button');
        form.style.display = 'block';
        saveButton.style.display = 'block';
        
        // Set template name as workout name
        const nameInput = form.querySelector('input[name="workout_name"]');
        nameInput.value = template.name;
        
        // Set today's date with proper error handling
        const dateInput = form.querySelector('input[name="workout_date"]');
        if (dateInput) {
            const today = new Date();
            const formattedDate = today.toISOString().split('T')[0];
            dateInput.value = formattedDate;
        }
        
        // Fetch template details and populate form
        fetch(`get_template_details.php?id=${template.id}`)
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('exercises-container');
                container.innerHTML = ''; // Clear existing exercises
                
                data.exercises.forEach((exercise, index) => {
                    addExercise(); // Add exercise entry
                    
                    // Set exercise selection
                    const exerciseSelect = container.lastElementChild.querySelector('select[name*="exercise_id"]');
                    exerciseSelect.value = exercise.exercise_id;
                    
                    // Add sets from template
                    const setsContainer = container.lastElementChild.querySelector('.sets-container');
                    setsContainer.innerHTML = ''; // Clear default sets
                    
                    exercise.sets.forEach(set => {
                        const setHtml = generateSetHtml(index, set.set_number, set);
                        setsContainer.insertAdjacentHTML('beforeend', setHtml);
                    });
                });
                
                // Close and cleanup modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('templateModal'));
                if (modal) {
                    modal.hide();
                    modal._element.addEventListener('hidden.bs.modal', cleanupModal);
                } else {
                    cleanupModal();
                }
            });
    }

    function generateSetHtml(exerciseIndex, setNumber, setData) {
        return `
            <div class="set-entry row mb-3 align-items-center">
                <div class="col-auto">
                    <label class="set-number fw-bold">Set ${setNumber}</label>
                </div>
                <div class="col">
                    <div class="input-group">
                        <input type="number" name="exercises[${exerciseIndex}][sets][${setNumber-1}][reps]" 
                               class="form-control" placeholder="Reps" min="0" value="${setData.default_reps || ''}">
                        <span class="input-group-text">reps</span>
                    </div>
                </div>
                <div class="col">
                    <div class="input-group">
                        <input type="number" step="0.5" name="exercises[${exerciseIndex}][sets][${setNumber-1}][weight]" 
                               class="form-control" placeholder="Weight" min="0" value="${setData.default_weight || ''}">
                        <span class="input-group-text">kg</span>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="btn-group">
                        <div class="form-check me-2">
                            <input type="checkbox" class="form-check-input" 
                                   name="exercises[${exerciseIndex}][sets][${setNumber-1}][warmup]"
                                   ${setData.is_warmup ? 'checked' : ''}>
                            <label class="form-check-label">Warmup</label>
                        </div>
                        <div class="form-check me-2">
                            <input type="checkbox" class="form-check-input" 
                                   name="exercises[${exerciseIndex}][sets][${setNumber-1}][dropset]"
                                   ${setData.is_dropset ? 'checked' : ''}>
                            <label class="form-check-label">Drop Set</label>
                        </div>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeSet(this)">×</button>
                    </div>
                </div>
            </div>
        `;
    }

    // Add this function to handle modal cleanup
    function cleanupModal() {
        // Remove any stuck backdrop
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>