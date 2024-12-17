<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
checkLogin();

$user_id = $_SESSION['user_id'];
$success = $error = '';

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        $height = sanitizeInput($_POST['height']);
        $weight = sanitizeInput($_POST['weight']);
        
        $sql = "UPDATE users SET name = ?, email = ?, height = ?, weight = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssddi", $name, $email, $height, $weight, $user_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success = "Profile updated successfully";
        } else {
            throw new Exception("Failed to update profile");
        }
        
        // Handle password change if provided
        if (!empty($_POST['new_password'])) {
            if (!password_verify($_POST['current_password'], $user['password'])) {
                throw new Exception("Current password is incorrect");
            }
            $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $new_password, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success .= " Password updated successfully";
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get user data
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_stmt_get_result($stmt)->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo translate('profile_title'); ?> - GymTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h2><?php echo translate('profile_title'); ?></h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><?php echo translate('profile_personal_info'); ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label><?php echo translate('profile_name'); ?></label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label><?php echo translate('profile_email'); ?></label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label><?php echo translate('profile_height'); ?> (cm)</label>
                                <input type="number" step="0.1" name="height" class="form-control" value="<?php echo $user['height']; ?>">
                            </div>
                            <div class="mb-3">
                                <label><?php echo translate('profile_weight'); ?> (kg)</label>
                                <input type="number" step="0.1" name="weight" class="form-control" value="<?php echo $user['weight']; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo translate('profile_save_changes'); ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><?php echo translate('profile_change_password'); ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label><?php echo translate('profile_current_password'); ?></label>
                                <input type="password" name="current_password" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label><?php echo translate('profile_new_password'); ?></label>
                                <input type="password" name="new_password" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-warning"><?php echo translate('profile_change_password'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>