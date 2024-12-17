<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
checkLogin();

if (!isAdmin()) {
    header("Location: ../user/dashboard.php");
    exit();
}

// Get all users for selection
$users_sql = "SELECT id, name, email FROM users ORDER BY name";
$users_result = mysqli_query($conn, $users_sql);
$users = mysqli_fetch_all($users_result, MYSQLI_ASSOC);

$selected_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 months'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$chart_data = [];
$exercise_progress = [];

if ($selected_user) {
    // Get exercise data - using the exact same query as user/progress.php
    $sql = "SELECT 
                e.name as exercise_name,
                w.workout_date,
                we.id as workout_exercise_id,
                COUNT(ws.id) as actual_sets_count,
                GROUP_CONCAT(ws.reps) as all_reps,
                GROUP_CONCAT(ws.weight) as all_weights,
                MAX(ws.weight) as max_weight,
                SUM(ws.reps * ws.weight) as total_volume
            FROM workouts w
            JOIN workout_exercises we ON w.id = we.workout_id
            JOIN exercises e ON we.exercise_id = e.id
            JOIN workout_sets ws ON we.id = ws.workout_exercise_id
            WHERE w.user_id = ?
            AND w.workout_date BETWEEN ? AND ?
            GROUP BY e.name, w.workout_date, we.id
            ORDER BY e.name, w.workout_date ASC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $selected_user, $start_date, $end_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $progress_data = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Process data for both chart and progress summary
    foreach ($progress_data as $record) {
        $exercise = $record['exercise_name'];
        
        // Initialize chart data structure
        if (!isset($chart_data[$exercise])) {
            $chart_data[$exercise] = [
                'dates' => [],
                'max_weight' => [],
                'total_volume' => [],
                'estimated_1rm' => [],
                'best_set' => []
            ];
        }

        // Initialize progress tracking
        if (!isset($exercise_progress[$exercise])) {
            $exercise_progress[$exercise] = [
                'first_date' => $record['workout_date'],
                'last_date' => $record['workout_date'],
                'first_max_weight' => 0,
                'last_max_weight' => 0,
                'first_volume' => 0,
                'last_volume' => 0,
                'best_weight' => 0,
                'best_volume' => 0
            ];
        }

        // Parse set data
        $reps_array = explode(',', $record['all_reps']);
        $weights_array = explode(',', $record['all_weights']);
        
        // Calculate metrics
        $daily_max_weight = floatval($record['max_weight']);
        $daily_volume = floatval($record['total_volume']);
        $best_1rm = 0;
        $best_set = null;

        // Process each set
        for ($i = 0; $i < count($reps_array); $i++) {
            $reps = floatval($reps_array[$i]);
            $weight = floatval($weights_array[$i]);
            
            if ($reps > 0 && $weight > 0) {
                // Calculate 1RM using Epley formula
                $one_rm = $weight * (1 + $reps/30);
                if ($one_rm > $best_1rm) {
                    $best_1rm = $one_rm;
                    $best_set = [
                        'reps' => $reps,
                        'weight' => $weight,
                        'estimated_1rm' => $one_rm
                    ];
                }
            }
        }

        // Update progress tracking
        if ($record['workout_date'] === $exercise_progress[$exercise]['first_date']) {
            $exercise_progress[$exercise]['first_max_weight'] = $daily_max_weight;
            $exercise_progress[$exercise]['first_volume'] = $daily_volume;
        }
        $exercise_progress[$exercise]['last_date'] = $record['workout_date'];
        $exercise_progress[$exercise]['last_max_weight'] = $daily_max_weight;
        $exercise_progress[$exercise]['last_volume'] = $daily_volume;
        $exercise_progress[$exercise]['best_weight'] = max(
            $exercise_progress[$exercise]['best_weight'], 
            $daily_max_weight
        );
        $exercise_progress[$exercise]['best_volume'] = max(
            $exercise_progress[$exercise]['best_volume'], 
            $daily_volume
        );

        // Add to chart data
        $chart_data[$exercise]['dates'][] = $record['workout_date'];
        $chart_data[$exercise]['max_weight'][] = round($daily_max_weight, 2);
        $chart_data[$exercise]['total_volume'][] = round($daily_volume, 2);
        $chart_data[$exercise]['estimated_1rm'][] = round($best_1rm, 2);
        $chart_data[$exercise]['best_set'][] = $best_set;
    }

    // Calculate percentage changes
    foreach ($exercise_progress as $exercise => &$progress) {
        $progress['weight_change'] = calculatePercentageChange(
            $progress['first_max_weight'],
            $progress['last_max_weight']
        );
        $progress['volume_change'] = calculatePercentageChange(
            $progress['first_volume'],
            $progress['last_volume']
        );
    }
}

function calculatePercentageChange($start, $end) {
    if ($start == 0) return 0;
    return round((($end - $start) / $start) * 100, 2);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Progress Analysis - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.1/dist/chartjs-adapter-moment.min.js"></script>
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'admin_navbar.php'; ?>
    
    <div class="container mt-4">
        <h2>User Progress Analysis</h2>

        <!-- User Selection Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Select User</label>
                        <select name="user_id" class="form-select" required onchange="this.form.submit()">
                            <option value="">Choose user...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $selected_user == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">View Progress</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_user && !empty($exercise_progress)): ?>
            <!-- Overall Progress Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h4>Overall Progress Summary</h4>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Exercise</th>
                                    <th>First Weight</th>
                                    <th>Last Weight</th>
                                    <th>Weight Change</th>
                                    <th>First Volume</th>
                                    <th>Last Volume</th>
                                    <th>Volume Change</th>
                                    <th>Best Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exercise_progress as $exercise => $progress): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exercise); ?></td>
                                        <td><?php echo $progress['first_max_weight']; ?> kg</td>
                                        <td><?php echo $progress['last_max_weight']; ?> kg</td>
                                        <td>
                                            <span class="badge bg-<?php echo $progress['weight_change'] >= 0 ? 'success' : 'danger'; ?>">
                                                <?php echo $progress['weight_change']; ?>%
                                            </span>
                                        </td>
                                        <td><?php echo $progress['first_volume']; ?> kg</td>
                                        <td><?php echo $progress['last_volume']; ?> kg</td>
                                        <td>
                                            <span class="badge bg-<?php echo $progress['volume_change'] >= 0 ? 'success' : 'danger'; ?>">
                                                <?php echo $progress['volume_change']; ?>%
                                            </span>
                                        </td>
                                        <td>
                                            Max: <?php echo $progress['best_weight']; ?> kg<br>
                                            Volume: <?php echo $progress['best_volume']; ?> kg
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Exercise Progress Charts -->
            <div class="card">
                <div class="card-body">
                    <h4>Detailed Progress Charts</h4>
                    <select id="exerciseSelect" class="form-select mb-3" onchange="updateChart()">
                        <?php foreach (array_keys($chart_data) as $exercise): ?>
                            <option value="<?php echo htmlspecialchars($exercise); ?>">
                                <?php echo htmlspecialchars($exercise); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="exerciseStats" class="alert alert-info mb-3"></div>
                    
                    <div class="chart-container">
                        <canvas id="progressChart"></canvas>
                    </div>
                </div>
            </div>
        <?php elseif ($selected_user): ?>
            <div class="alert alert-info">No workout data found for this user in the selected date range.</div>
        <?php endif; ?>
    </div>

    <script>
    // Initialize chart data
    const chartData = <?php echo json_encode($chart_data ?? []); ?>;
    let currentChart = null;

    function updateChart() {
        const exercise = document.getElementById('exerciseSelect').value;
        const data = chartData[exercise];

        if (!data || !data.dates || data.dates.length === 0) {
            document.getElementById('exerciseStats').innerHTML = 'No data available for this exercise';
            if (currentChart) {
                currentChart.destroy();
                currentChart = null;
            }
            return;
        }

        const lastIndex = data.dates.length - 1;
        document.getElementById('exerciseStats').innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <strong>Last Max Weight:</strong> ${data.max_weight[lastIndex]} kg
                </div>
                <div class="col-md-4">
                    <strong>Last Volume:</strong> ${data.total_volume[lastIndex]} kg
                </div>
                <div class="col-md-4">
                    <strong>Last Est. 1RM:</strong> ${data.estimated_1rm[lastIndex]} kg
                </div>
            </div>`;

        if (currentChart) {
            currentChart.destroy();
        }

        const ctx = document.getElementById('progressChart').getContext('2d');
        currentChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.dates,
                datasets: [
                    {
                        label: 'Max Weight (kg)',
                        data: data.max_weight,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        yAxisID: 'y',
                        fill: true
                    },
                    {
                        label: 'Volume (kg)',
                        data: data.total_volume,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        yAxisID: 'y1',
                        fill: true
                    },
                    {
                        label: 'Estimated 1RM (kg)',
                        data: data.estimated_1rm,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        yAxisID: 'y',
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day',
                            parser: 'YYYY-MM-DD',
                            tooltipFormat: 'll'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    }

    // Initialize chart when page loads
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('exerciseSelect')?.options.length > 0) {
            updateChart();
        }
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
