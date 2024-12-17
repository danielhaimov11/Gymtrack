<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'u315958212_danielhimov');
define('DB_PASSWORD', '366575qwQW');
define('DB_NAME', 'u315958212_gymtraker');

try {
    $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if($conn === false){
        throw new Exception("ERROR: Could not connect. " . mysqli_connect_error());
    }
    mysqli_set_charset($conn, "utf8mb4");
} catch (Exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if auth_tokens table exists and create if it doesn't
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'auth_tokens'");
if (mysqli_num_rows($table_check) == 0) {
    $sql = "CREATE TABLE `auth_tokens` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `selector` varchar(255) NOT NULL,
        `hash` varchar(255) NOT NULL,
        `expires` datetime NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `selector` (`selector`),
        KEY `user_id` (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if (!mysqli_query($conn, $sql)) {
        error_log("Error creating auth_tokens table: " . mysqli_error($conn));
    }
}

// Check and create required tables
$tables_sql = [
    "workout_templates" => "CREATE TABLE IF NOT EXISTS workout_templates (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    "template_exercises" => "CREATE TABLE IF NOT EXISTS template_exercises (
        id INT PRIMARY KEY AUTO_INCREMENT,
        template_id INT NOT NULL,
        exercise_id INT NOT NULL,
        default_sets INT DEFAULT 3,
        FOREIGN KEY (template_id) REFERENCES workout_templates(id) ON DELETE CASCADE,
        FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE
    )",
    
    "template_sets" => "CREATE TABLE IF NOT EXISTS template_sets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        template_exercise_id INT NOT NULL,
        set_number INT NOT NULL,
        default_reps INT,
        default_weight DECIMAL(5,2),
        is_warmup TINYINT(1) DEFAULT 0,
        is_dropset TINYINT(1) DEFAULT 0,
        FOREIGN KEY (template_exercise_id) REFERENCES template_exercises(id) ON DELETE CASCADE
    )"
];

foreach ($tables_sql as $table_name => $sql) {
    if (!mysqli_query($conn, $sql)) {
        error_log("Error creating $table_name table: " . mysqli_error($conn));
    }
}

// Add name column to workouts table if it doesn't exist
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM workouts LIKE 'name'");
if (mysqli_num_rows($check_column) == 0) {
    $sql = "ALTER TABLE workouts ADD COLUMN name VARCHAR(255) DEFAULT NULL AFTER user_id";
    if (!mysqli_query($conn, $sql)) {
        error_log("Error adding name column to workouts table: " . mysqli_error($conn));
    }
}

// Add template_id column to workouts table if it doesn't exist
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM workouts LIKE 'template_id'");
if (mysqli_num_rows($check_column) == 0) {
    $sql = "ALTER TABLE workouts ADD COLUMN template_id INT DEFAULT NULL AFTER name, 
            ADD FOREIGN KEY (template_id) REFERENCES workout_templates(id) ON DELETE SET NULL";
    if (!mysqli_query($conn, $sql)) {
        error_log("Error adding template_id column to workouts table: " . mysqli_error($conn));
    }
}

function getUniqueTargetMuscles($conn) {
    $sql = "SELECT DISTINCT target_muscle FROM exercises WHERE approved = 1 ORDER BY target_muscle ASC";
    $result = mysqli_query($conn, $sql);
    $muscles = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $muscles[] = $row['target_muscle'];
    }
    return $muscles;
}

function getUniqueBodyAreas($conn) {
    $sql = "SELECT DISTINCT body_area FROM exercises WHERE approved = 1 ORDER BY body_area ASC";
    $result = mysqli_query($conn, $sql);
    $areas = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $areas[] = $row['body_area'];
    }
    return $areas;
}
?>