<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        mysqli_begin_transaction($conn);
        
        $workout_id = sanitizeInput($_POST['workout_id']);
        $workout_date = sanitizeInput($_POST['workout_date']);
        $workout_name = sanitizeInput($_POST['workout_name']);
        $notes = sanitizeInput($_POST['notes']);

        // Update workout details
        $sql = "UPDATE workouts SET workout_date = ?, name = ?, notes = ? WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssii", $workout_date, $workout_name, $notes, $workout_id, $_SESSION['user_id']);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error updating workout: " . mysqli_error($conn));
        }

        // Update existing exercises
        if (isset($_POST['exercises'])) {
            foreach ($_POST['exercises'] as $exercise_id => $exercise_data) {
                if (!isset($exercise_data['id'])) continue;
                
                $exercise_id = $exercise_data['id'];
                
                // Update exercise type if changed
                if (isset($exercise_data['exercise_id'])) {
                    $sql = "UPDATE workout_exercises SET exercise_id = ? WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "ii", $exercise_data['exercise_id'], $exercise_id);
                    
                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception("Error updating exercise: " . mysqli_error($conn));
                    }
                }

                // Update sets
                if (isset($exercise_data['sets'])) {
                    foreach ($exercise_data['sets'] as $set_id => $set) {
                        // Handle new sets (keys starting with 'new_')
                        if (strpos($set_id, 'new_') === 0) {
                            $reps = intval($set['reps']);
                            $weight = floatval($set['weight']);
                            $is_warmup = isset($set['is_warmup']) ? 1 : 0;
                            $is_dropset = isset($set['is_dropset']) ? 1 : 0;
                            $set_number = intval(str_replace('new_', '', $set_id));

                            $sql = "INSERT INTO workout_sets 
                                   (workout_exercise_id, set_number, reps, weight, is_warmup, is_dropset)
                                   VALUES (?, ?, ?, ?, ?, ?)";
                            $stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($stmt, "iidiii", 
                                $exercise_id,
                                $set_number,
                                $reps,
                                $weight,
                                $is_warmup,
                                $is_dropset
                            );
                            
                            if (!mysqli_stmt_execute($stmt)) {
                                throw new Exception("Error adding new set: " . mysqli_error($conn));
                            }
                            continue;
                        }

                        // Update existing sets
                        // Skip if not numeric (new sets are handled separately)
                        if (!is_numeric($set_id)) continue;
                        
                        $reps = intval($set['reps']);
                        $weight = floatval($set['weight']);
                        $is_warmup = isset($set['warmup']) ? 1 : 0;
                        $is_dropset = isset($set['dropset']) ? 1 : 0;

                        $sql = "UPDATE workout_sets 
                               SET reps = ?, weight = ?, is_warmup = ?, is_dropset = ? 
                               WHERE id = ?";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "idiii", $reps, $weight, $is_warmup, $is_dropset, $set_id);
                        
                        if (!mysqli_stmt_execute($stmt)) {
                            throw new Exception("Error updating set: " . mysqli_error($conn));
                        }
                    }
                }
            }
        }

        // Handle new exercises
        if (isset($_POST['new_exercises'])) {
            foreach ($_POST['new_exercises'] as $exercise) {
                if (empty($exercise['exercise_id'])) continue;

                // Insert new exercise
                $sql = "INSERT INTO workout_exercises (workout_id, exercise_id) VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ii", $workout_id, $exercise['exercise_id']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error adding new exercise: " . mysqli_error($conn));
                }

                $new_exercise_id = mysqli_insert_id($conn);

                // Add sets for new exercise
                if (isset($exercise['sets'])) {
                    foreach ($exercise['sets'] as $set_number => $set) {
                        if (empty($set['reps']) && empty($set['weight'])) continue;

                        $reps = intval($set['reps']);
                        $weight = floatval($set['weight']);
                        $is_warmup = isset($set['warmup']) ? 1 : 0;
                        $is_dropset = isset($set['dropset']) ? 1 : 0;
                        $set_num = $set_number + 1;

                        $sql = "INSERT INTO workout_sets 
                               (workout_exercise_id, set_number, reps, weight, is_warmup, is_dropset)
                               VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "iiidii", 
                            $new_exercise_id, $set_num, $reps, $weight, $is_warmup, $is_dropset);
                        
                        if (!mysqli_stmt_execute($stmt)) {
                            throw new Exception("Error adding new set: " . mysqli_error($conn));
                        }
                    }
                }
            }
        }

        // Handle deletions
        if (isset($_POST['deleted_sets']) && is_array($_POST['deleted_sets'])) {
            $sql = "DELETE FROM workout_sets WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            
            foreach ($_POST['deleted_sets'] as $set_id) {
                mysqli_stmt_bind_param($stmt, "i", $set_id);
                mysqli_stmt_execute($stmt);
            }
        }

        if (isset($_POST['deleted_exercises']) && is_array($_POST['deleted_exercises'])) {
            $sql = "DELETE FROM workout_exercises WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            
            foreach ($_POST['deleted_exercises'] as $exercise_id) {
                mysqli_stmt_bind_param($stmt, "i", $exercise_id);
                mysqli_stmt_execute($stmt);
            }
        }

        // For each exercise and its sets
        foreach ($exercises as $exercise_id => $exercise_data) {
            // ...existing exercise handling code...
            
            if (isset($exercise_data['sets'])) {
                foreach ($exercise_data['sets'] as $set_id => $set_data) {
                    if (strpos($set_id, 'new_') === 0) {
                        // This is a new set
                        $sql = "INSERT INTO workout_sets (workout_exercise_id, set_number, reps, weight, is_warmup, is_dropset) 
                                VALUES (?, ?, ?, ?, ?, ?)";
                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "iiddii", 
                            $workout_exercise_id,
                            $set_data['set_number'],
                            $set_data['reps'],
                            $set_data['weight'],
                            $set_data['is_warmup'] ?? 0,
                            $set_data['is_dropset'] ?? 0
                        );
                        mysqli_stmt_execute($stmt);
                    }
                    // ...existing set update code...
                }
            }
        }

        mysqli_commit($conn);
        header('Location: workout_detail.php?id=' . $workout_id . '&success=1');
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        header('Location: edit_workout.php?id=' . $workout_id . '&error=' . urlencode($e->getMessage()));
        exit();
    }
}

header('Location: workouts.php');
exit();
