<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
checkLogin();

$user_id = $_SESSION['user_id'];

// Get date range from request or set defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 months'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// עדכון השאילתה לקבלת נתוני סטים מדויקים
$sql = "SELECT 
            e.name as exercise_name,
            w.workout_date,
            we.id as workout_exercise_id,
            COUNT(CASE WHEN ws.is_warmup = 0 THEN ws.id END) as working_sets_count,
            COUNT(CASE WHEN ws.is_warmup = 1 THEN ws.id END) as warmup_sets_count,
            GROUP_CONCAT(CASE WHEN ws.is_warmup = 0 THEN ws.reps END) as working_reps,
            GROUP_CONCAT(CASE WHEN ws.is_warmup = 0 THEN ws.weight END) as working_weights,
            GROUP_CONCAT(CASE WHEN ws.is_warmup = 1 THEN ws.reps END) as warmup_reps,
            GROUP_CONCAT(CASE WHEN ws.is_warmup = 1 THEN ws.weight END) as warmup_weights,
            MAX(CASE WHEN ws.is_warmup = 0 THEN ws.weight ELSE 0 END) as max_working_weight,
            MAX(CASE WHEN ws.is_warmup = 1 THEN ws.weight ELSE 0 END) as max_warmup_weight,
            SUM(CASE WHEN ws.is_warmup = 0 THEN ws.reps * ws.weight ELSE 0 END) as working_volume,
            SUM(CASE WHEN ws.is_warmup = 1 THEN ws.reps * ws.weight ELSE 0 END) as warmup_volume
        FROM workouts w
        JOIN workout_exercises we ON w.id = we.workout_id
        JOIN exercises e ON we.exercise_id = e.id
        JOIN workout_sets ws ON we.id = ws.workout_exercise_id
        WHERE w.user_id = ?
        AND w.workout_date BETWEEN ? AND ?
        GROUP BY e.name, w.workout_date, we.id
        ORDER BY w.workout_date ASC, e.name";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iss", $user_id, $start_date, $end_date);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$progress_data = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Process data for charting
$chart_data = [];

// עדכון עיבוד הנתונים
foreach ($progress_data as $record) {
    $exercise = $record['exercise_name'];
    if (!isset($chart_data[$exercise])) {
        $chart_data[$exercise] = [
            'dates' => [],
            'max_working_weight' => [],
            'max_warmup_weight' => [],
            'working_volume' => [],
            'warmup_volume' => [],
            'estimated_1rm' => [],
            'best_set' => [],
            'working_sets_count' => [],
            'warmup_sets_count' => []
        ];
    }

    // Parse grouped data for working sets
    $working_reps_array = $record['working_reps'] ? explode(',', $record['working_reps']) : [];
    $working_weights_array = $record['working_weights'] ? explode(',', $record['working_weights']) : [];
    
    // Parse grouped data for warmup sets
    $warmup_reps_array = $record['warmup_reps'] ? explode(',', $record['warmup_reps']) : [];
    $warmup_weights_array = $record['warmup_weights'] ? explode(',', $record['warmup_weights']) : [];
    
    // Calculate metrics
    $daily_max_working_weight = floatval($record['max_working_weight']);
    $daily_max_warmup_weight = floatval($record['max_warmup_weight']);
    $daily_working_volume = floatval($record['working_volume']);
    $daily_warmup_volume = floatval($record['warmup_volume']);
    $daily_working_sets = intval($record['working_sets_count']);
    $daily_warmup_sets = intval($record['warmup_sets_count']);
    
    // Calculate 1RM using working sets only
    $best_1rm = 0;
    $best_set = null;

    for ($i = 0; $i < count($working_reps_array); $i++) {
        $reps = floatval($working_reps_array[$i]);
        $weight = floatval($working_weights_array[$i]);
        
        if ($reps > 0 && $weight > 0) {
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

    // Store the data
    $chart_data[$exercise]['dates'][] = $record['workout_date'];
    $chart_data[$exercise]['max_working_weight'][] = round($daily_max_working_weight, 2);
    $chart_data[$exercise]['max_warmup_weight'][] = round($daily_max_warmup_weight, 2);
    $chart_data[$exercise]['working_volume'][] = round($daily_working_volume, 2);
    $chart_data[$exercise]['warmup_volume'][] = round($daily_warmup_volume, 2);
    $chart_data[$exercise]['estimated_1rm'][] = round($best_1rm, 2);
    $chart_data[$exercise]['best_set'][] = $best_set;
    $chart_data[$exercise]['working_sets_count'][] = $daily_working_sets;
    $chart_data[$exercise]['warmup_sets_count'][] = $daily_warmup_sets;
}

// Debug output
echo "<!-- Debug: Found " . count($progress_data) . " records -->";
echo "<!-- Debug: Chart data: " . json_encode(array_keys($chart_data)) . " -->";

?>

<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo translate('progress_title'); ?> - GymTrack</title>
    <?php if (isRTL()): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.1/dist/chartjs-adapter-moment.min.js"></script>
    <style>
        .progress-container {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 10px;
            margin-top: 20px;
            min-height: 300px;
            width: 100%;
            position: relative;
        }
        @media (max-width: 768px) {
            .progress-container {
                padding: 10px;
            }
            .chart-container {
                padding: 5px;
                min-height: 250px;
            }
            .date-filters .row {
                flex-direction: column;
            }
            .date-filters .col-auto {
                width: 100%;
                margin-bottom: 10px;
            }
            .stats-box .card {
                margin-bottom: 15px;
            }
        }
        select.form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
        }
        h2 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .date-filters {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .stats-box {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="progress-container">
            <h2 class="text-center"><?php echo translate('progress_title'); ?></h2>
            
            <!-- Add date range filters -->
            <div class="date-filters mb-4">
                <form class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label class="col-form-label"><?php echo translate('progress_date_range'); ?></label>
                    </div>
                    <div class="col-auto">
                        <input type="date" class="form-control" name="start_date" 
                               value="<?php echo $start_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-auto">
                        <label class="col-form-label"><?php echo translate('progress_to'); ?></label>
                    </div>
                    <div class="col-auto">
                        <input type="date" class="form-control" name="end_date" 
                               value="<?php echo $end_date; ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary"><?php echo translate('progress_apply'); ?></button>
                    </div>
                </form>
            </div>

            <div class="stats-box mb-4">
                <h5><?php echo translate('progress_stats'); ?></h5>
                <div id="exerciseStats"></div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <select id="exerciseSelect" class="form-select" onchange="updateChart()">
                        <?php foreach (array_keys($chart_data) as $exercise): ?>
                            <option value="<?php echo htmlspecialchars($exercise); ?>">
                                <?php echo htmlspecialchars($exercise); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="chart-container">
                <canvas id="progressChart"></canvas>
            </div>
        </div>
    </div>

    <script>
    // Define chartData first, before any other code
    window.chartData = <?php echo json_encode($chart_data); ?>;
    let currentChart = null;

    function updateChart() {
        try {
            const exerciseSelect = document.getElementById('exerciseSelect');
            if (!exerciseSelect) {
                console.error('Exercise select not found');
                return;
            }

            const exercise = exerciseSelect.value;
            const data = window.chartData[exercise];
            
            console.log('Selected exercise:', exercise);
            console.log('Available data:', data);

            // Ensure we have data
            if (!data || !data.dates || data.dates.length === 0) {
                document.getElementById('exerciseStats').innerHTML = 
                    `<div class="alert alert-info">${<?php echo json_encode(translate('progress_no_data')); ?>}</div>`;
                if (currentChart) {
                    currentChart.destroy();
                }
                return;
            }

            // Update stats display
            const lastIndex = data.dates.length - 1;
            const bestSet = data.best_set[lastIndex];
            const workingWeight = data.max_working_weight[lastIndex];
            const workingVolume = data.working_volume[lastIndex];
            const warmupWeight = data.max_warmup_weight[lastIndex];
            const warmupVolume = data.warmup_volume[lastIndex];
            const estimatedOneRm = data.estimated_1rm[lastIndex];
            const noDataText = <?php echo json_encode(translate('no_data_available')); ?>;

            document.getElementById('exerciseStats').innerHTML = `
                <div class="row">
                    <div class="col-md-4">
                        <div class="card mb-2">
                            <div class="card-body">
                                <h6 class="card-title">${<?php echo json_encode(translate('progress_working_sets')); ?>}</h6>
                                <p class="card-text">
                                    <strong>${<?php echo json_encode(translate('progress_max_weight')); ?>}:</strong> 
                                    ${workingWeight > 0 ? `${workingWeight} kg` : <?php echo json_encode(translate('no_data_available')); ?>}<br>
                                    <strong>${<?php echo json_encode(translate('progress_volume')); ?>}:</strong> 
                                    ${workingVolume > 0 ? `${workingVolume} kg` : <?php echo json_encode(translate('no_data_available')); ?>}<br>
                                    <strong>${<?php echo json_encode(translate('progress_sets')); ?>}:</strong> 
                                    ${data.working_sets_count[lastIndex] || 0}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card mb-2">
                            <div class="card-body">
                                <h6 class="card-title">${<?php echo json_encode(translate('progress_warmup_sets')); ?>}</h6>
                                <p class="card-text">
                                    <strong>${<?php echo json_encode(translate('progress_max_weight')); ?>}:</strong> ${warmupWeight > 0 ? warmupWeight + ' kg' : noDataText}<br>
                                    <strong>${<?php echo json_encode(translate('progress_volume')); ?>}:</strong> ${warmupVolume > 0 ? warmupVolume + ' kg' : noDataText}<br>
                                    <strong>${<?php echo json_encode(translate('progress_sets')); ?>}:</strong> ${data.warmup_sets_count[lastIndex]}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card mb-2">
                            <div class="card-body">
                                <h6 class="card-title">${<?php echo json_encode(translate('progress_personal_best')); ?>}</h6>
                                <p class="card-text">
                                    <strong>${<?php echo json_encode(translate('progress_est_1rm')); ?>}:</strong> ${estimatedOneRm > 0 ? estimatedOneRm + ' kg' : noDataText}<br>
                                    <strong>${<?php echo json_encode(translate('progress_best_set')); ?>}:</strong> ${
                                        bestSet && bestSet.weight > 0 ? `${bestSet.reps}×${bestSet.weight}kg` : noDataText
                                    }
                                </p>
                            </div>
                        </div>
                    </div>
                </div>`;

            // Create/update chart
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
                            label: `${<?php echo json_encode(translate('progress_max_weight')); ?>} (kg)`,
                            data: data.max_working_weight,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            yAxisID: 'y',
                            fill: true
                        },
                        {
                            label: `${<?php echo json_encode(translate('progress_volume')); ?>} (kg)`,
                            data: data.working_volume,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            yAxisID: 'y1',
                            fill: true
                        },
                        {
                            label: `${<?php echo json_encode(translate('progress_est_1rm')); ?>} (kg)`,
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
                        intersect: false
                    },
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'day',
                                displayFormats: {
                                    day: 'MMM D'
                                }
                            },
                            title: {
                                display: false // Hide title on mobile
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                autoSkip: true,
                                maxTicksLimit: 8
                            }
                        },
                        y: {
                            type: 'linear',
                            position: 'left',
                            title: {
                                display: window.innerWidth > 768, // Show only on desktop
                                text: <?php echo json_encode(translate('workouts_weight')); ?>
                            },
                            ticks: {
                                maxTicksLimit: 6
                            }
                        },
                        y1: {
                            type: 'linear',
                            position: 'right',
                            title: {
                                display: window.innerWidth > 768, // Show only on desktop
                                text: <?php echo json_encode(translate('progress_volume')); ?>
                            },
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                maxTicksLimit: 6
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: window.innerWidth > 768 ? 'top' : 'bottom',
                            labels: {
                                boxWidth: window.innerWidth > 768 ? 40 : 20,
                                padding: window.innerWidth > 768 ? 10 : 5
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error updating chart:', error);
            document.getElementById('exerciseStats').innerHTML = 
                `<div class="alert alert-danger">${<?php echo json_encode(translate('progress_error_loading')); ?>}</div>`;
        }
    }

    // Initialize only after DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('exerciseSelect')?.options.length > 0) {
            updateChart();
        }
    });

    // Add date range validation
    const startDate = document.querySelector('input[name="start_date"]');
    const endDate = document.querySelector('input[name="end_date"]');

    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            if (this.value > endDate.value) {
                endDate.value = this.value;
            }
        });

        endDate.addEventListener('change', function() {
            if (this.value < startDate.value) {
                startDate.value = this.value;
            }
        });
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
