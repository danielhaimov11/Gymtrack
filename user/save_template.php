<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
checkLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        mysqli_begin_transaction($conn);
        
        $name = sanitizeInput($_POST['template_name']);
        $description = sanitizeInput($_POST['template_description']);
        $user_id = $_SESSION['user_id'];

        // Insert template
        $sql = "INSERT INTO workout_templates (user_id, name, description) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $user_id, $name, $description);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error creating template");
        }
        
        $template_id = mysqli_insert_id($conn);
        
        // If saving from an existing workout
        if (isset($_POST['from_workout'])) {
            $workout_id = sanitizeInput($_POST['from_workout']);
            
            // First, copy exercises from the workout to the template
            $sql = "INSERT INTO template_exercises (template_id, exercise_id)
                    SELECT ?, e.id
                    FROM workout_exercises we
                    JOIN exercises e ON we.exercise_id = e.id
                    WHERE we.workout_id = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $template_id, $workout_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error copying exercises to template");
            }

            // Then, copy all set details for each exercise, excluding weight
            $sql = "INSERT INTO template_sets 
                    (template_exercise_id, set_number, default_reps, is_warmup, is_dropset)
                    SELECT 
                        te.id,
                        ws.set_number,
                        ws.reps,
                        ws.is_warmup,
                        ws.is_dropset
                    FROM workout_exercises we
                    JOIN workout_sets ws ON we.id = ws.workout_exercise_id
                    JOIN template_exercises te ON te.template_id = ? 
                        AND te.exercise_id = we.exercise_id
                    WHERE we.workout_id = ?
                    ORDER BY we.id, ws.set_number";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $template_id, $workout_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error copying set details to template");
            }
        }
        // Handle manually added exercises (existing code)
        else if (isset($_POST['exercises']) && is_array($_POST['exercises'])) {
            $sql = "INSERT INTO template_exercises (template_id, exercise_id, default_sets) VALUES (?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            
            foreach ($_POST['exercises'] as $exercise) {
                mysqli_stmt_bind_param($stmt, "iii", $template_id, $exercise['exercise_id'], $exercise['sets']);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Error adding exercise to template");
                }
            }
        }
        
        mysqli_commit($conn);
        
        // Redirect back to the appropriate page
        if (isset($_POST['from_workout'])) {
            header('Location: workout_detail.php?id=' . $workout_id . '&success=Template created successfully');
        } else {
            header('Location: exercises.php?success=Template created successfully');
        }
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $redirect = isset($_POST['from_workout']) ? 
            'workout_detail.php?id=' . $_POST['from_workout'] : 
            'exercises.php';
        header('Location: ' . $redirect . '&error=' . urlencode($e->getMessage()));
        exit();
    }
}