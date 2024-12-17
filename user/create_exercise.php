<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
checkLogin();

$target_muscles = getUniqueTargetMuscles($conn);
$body_areas = getUniqueBodyAreas($conn);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $target_muscle = $_POST['target_muscle'] === 'new' ? $_POST['new_target_muscle'] : $_POST['target_muscle'];
    $body_area = $_POST['body_area'] === 'new' ? $_POST['new_body_area'] : $_POST['body_area'];

    // Check if exercise name already exists
    if (exerciseNameExists($conn, $name)) {
        $error = "An exercise with this name already exists.";
    } else {
        $sql = "INSERT INTO exercises (name, description, target_muscle, body_area, approved) VALUES (?, ?, ?, ?, 0)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssss', $name, $description, $target_muscle, $body_area);
        mysqli_stmt_execute($stmt);
        
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $success = "Exercise submitted for admin approval!";
            header('Location: exercises.php');
            exit();
        } else {
            $error = "Error creating exercise.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exercise - GymTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2>Create Exercise</h2>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php elseif (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <div class="mb-3">
                <label for="name" class="form-label">Exercise Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
            </div>
            <div class="mb-3">
                <label for="target_muscle" class="form-label">Target Muscle</label>
                <select class="form-control" id="target_muscle" name="target_muscle" required>
                    <option value="">Select Target Muscle</option>
                    <?php foreach ($target_muscles as $muscle): ?>
                        <option value="<?php echo htmlspecialchars($muscle); ?>"><?php echo htmlspecialchars($muscle); ?></option>
                    <?php endforeach; ?>
                    <option value="new">Add New Target Muscle</option>
                </select>
                <input type="text" class="form-control mt-2" id="new_target_muscle" style="display: none;" placeholder="Enter new target muscle">
            </div>
            <div class="mb-3">
                <label for="body_area" class="form-label">Body Area</label>
                <select class="form-control" id="body_area" name="body_area" required>
                    <option value="">Select Body Area</option>
                    <?php foreach ($body_areas as $area): ?>
                        <option value="<?php echo htmlspecialchars($area); ?>"><?php echo htmlspecialchars($area); ?></option>
                    <?php endforeach; ?>
                    <option value="new">Add New Body Area</option>
                </select>
                <input type="text" class="form-control mt-2" id="new_body_area" style="display: none;" placeholder="Enter new body area">
            </div>
            <button type="submit" class="btn btn-primary">Create Exercise</button>
        </form>
    </div>

    <script>
        document.getElementById('target_muscle').addEventListener('change', function() {
            document.getElementById('new_target_muscle').style.display = 
                this.value === 'new' ? 'block' : 'none';
        });

        document.getElementById('body_area').addEventListener('change', function() {
            document.getElementById('new_body_area').style.display = 
                this.value === 'new' ? 'block' : 'none';
        });

        document.querySelector('form').addEventListener('submit', function(e) {
            const targetMuscle = document.getElementById('target_muscle');
            const bodyArea = document.getElementById('body_area');

            if (targetMuscle.value === 'new') {
                targetMuscle.value = document.getElementById('new_target_muscle').value;
            }
            if (bodyArea.value === 'new') {
                bodyArea.value = document.getElementById('new_body_area').value;
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>