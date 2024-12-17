<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
require_once '../includes/language.php';
checkLogin();

$user_id = $_SESSION['user_id'];

// Fetch total workouts and this month's workouts
$stats_sql = "SELECT 
    COUNT(*) as total_workouts,
    SUM(CASE WHEN MONTH(workout_date) = MONTH(CURRENT_DATE()) 
             AND YEAR(workout_date) = YEAR(CURRENT_DATE()) 
        THEN 1 ELSE 0 END) as month_workouts
    FROM workouts 
    WHERE user_id = ?";
$stats_stmt = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($stats_stmt, "i", $user_id);
mysqli_stmt_execute($stats_stmt);
$stats = mysqli_stmt_get_result($stats_stmt)->fetch_assoc();

// Get selected period from URL parameter
$period = isset($_GET['period']) ? sanitizeInput($_GET['period']) : 'today';

// Get date range parameters
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : null;
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : null;

// Modify period handling
if ($start_date && $end_date) {
    $date_filter = "AND w.workout_date BETWEEN '$start_date' AND '$end_date'";
    $period_text = date('M j, Y', strtotime($start_date)) . " - " . date('M j, Y', strtotime($end_date));
    $period = 'custom';
} else {
    switch ($period) {
        case 'week':
            $date_filter = "AND w.workout_date >= DATE_SUB(CURRENT_DATE, INTERVAL 7 DAY)";
            $period_text = "Last 7 Days";
            break;
        case 'month':
            $date_filter = "AND w.workout_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)";
            $period_text = "Last Month";
            break;
        case 'year':
            $date_filter = "AND w.workout_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 YEAR)";
            $period_text = "Last Year";
            break;
        case 'today':
        default:
            $date_filter = "AND DATE(w.workout_date) = CURRENT_DATE";
            $period_text = "Today";
            break;
    }
}

// Fetch body area summary with separate warmup and working sets - updated to exclude 0 weight sets
$summary_sql = "SELECT 
    e.body_area,
    COUNT(DISTINCT we.id) as exercise_count,
    COUNT(CASE WHEN ws.is_warmup = 0 AND ws.weight > 0 THEN ws.id END) as working_sets,
    COUNT(CASE WHEN ws.is_warmup = 1 AND ws.weight > 0 THEN ws.id END) as warmup_sets,
    SUM(CASE WHEN ws.is_warmup = 0 AND ws.weight > 0 THEN ws.reps * ws.weight ELSE 0 END) as working_volume,
    SUM(CASE WHEN ws.is_warmup = 1 AND ws.weight > 0 THEN ws.reps * ws.weight ELSE 0 END) as warmup_volume,
    COUNT(CASE WHEN ws.weight > 0 THEN ws.id END) as total_sets,
    SUM(CASE WHEN ws.weight > 0 THEN ws.reps * ws.weight ELSE 0 END) as total_volume
    FROM workouts w
    JOIN workout_exercises we ON w.id = we.workout_id
    JOIN exercises e ON we.exercise_id = e.id
    JOIN workout_sets ws ON we.id = ws.workout_exercise_id
    WHERE w.user_id = ? $date_filter
    GROUP BY e.body_area
    ORDER BY e.body_area";

$summary_stmt = mysqli_prepare($conn, $summary_sql);
mysqli_stmt_bind_param($summary_stmt, "i", $user_id);
mysqli_stmt_execute($summary_stmt);
$body_area_summary = mysqli_stmt_get_result($summary_stmt)->fetch_all(MYSQLI_ASSOC);

// Fetch recent workouts with exercise details
$recent_sql = "SELECT 
    w.id,
    w.workout_date,
    w.notes,
    COUNT(DISTINCT we.id) as exercise_count,
    GROUP_CONCAT(DISTINCT e.name SEPARATOR ', ') as exercises,
    SUM(CASE WHEN ws.is_warmup = 1 THEN 1 ELSE 0 END) as warmup_sets,
    SUM(CASE WHEN ws.is_dropset = 1 THEN 1 ELSE 0 END) as drop_sets
    FROM workouts w
    LEFT JOIN workout_exercises we ON w.id = we.workout_id
    LEFT JOIN exercises e ON we.exercise_id = e.id
    LEFT JOIN workout_sets ws ON we.id = ws.workout_exercise_id
    WHERE w.user_id = ?
    GROUP BY w.id
    ORDER BY w.workout_date DESC
    LIMIT 5";
$recent_stmt = mysqli_prepare($conn, $recent_sql);
mysqli_stmt_bind_param($recent_stmt, "i", $user_id);
mysqli_stmt_execute($recent_stmt);
$recent_workouts = mysqli_stmt_get_result($recent_stmt)->fetch_all(MYSQLI_ASSOC);

?>

<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>" dir="<?php echo isRTL() ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Dashboard - GymTrack</title>
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
        .share-button {
            font-size: 0.9rem;
            padding: 0.25rem 0.5rem;
        }
        .watermark {
            position: absolute;
            bottom: 8px;
            right: 8px;
            font-size: 0.7rem;
            color: rgba(0,0,0,0.4);
            font-style: italic;
        }
        /* Add these new styles */
        .workout-summary-capture {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: none;
        }

        .workout-summary-capture .list-group-item {
            border-color: #eee;
        }

        .workout-summary-capture .progress {
            height: 6px;
        }

        .period-text {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="main-content">
        <div class="container mt-4">
            <div class="row g-3">  <!-- הוספת g-3 למרווח אחיד -->
                <div class="col-md-4">
                    <div class="card h-100">  <!-- הוספת h-100 לגובה אחיד -->
                        <div class="card-body d-flex flex-column">  <!-- flex-column לסידור אנכי -->
                            <h5 class="card-title mb-3"><?php echo translate('dashboard_quick_stats'); ?></h5>
                            <div class="mb-3">  <!-- wrapper div למרווחים -->
                                <p class="mb-2"><?php echo translate('dashboard_total_workouts'); ?> <span id="totalWorkouts"><?php echo $stats['total_workouts']; ?></span></p>
                                <p class="mb-2"><?php echo translate('dashboard_this_month'); ?> <span id="monthWorkouts"><?php echo $stats['month_workouts']; ?></span></p>
                            </div>
                            <a href="workouts.php" class="btn btn-primary mt-auto"><?php echo translate('dashboard_start_workout'); ?></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0"><?php echo translate('dashboard_workout_summary'); ?></h5>
                                <div class="d-flex align-items-center">
                                    <button id="shareButton" class="btn btn-outline-primary btn-sm share-button me-2">
                                        <i class="fas fa-share-alt"></i> <?php echo translate('share_summary'); ?>
                                    </button>
                                    <div class="d-flex flex-column flex-md-row align-items-center">
                                        <form class="d-flex flex-wrap me-2 mb-2 mb-md-0" method="GET">
                                            <input type="date" name="start_date" class="form-control form-control-sm me-2 mb-2" 
                                                   value="<?php echo $start_date; ?>">
                                            <input type="date" name="end_date" class="form-control form-control-sm me-2 mb-2" 
                                                   value="<?php echo $end_date; ?>">
                                            <button type="submit" class="btn btn-sm btn-primary mb-2"><?php echo translate('dashboard_filter'); ?></button>
                                        </form>
                                        <div class="btn-group flex-wrap">
                                            <a href="?period=today" class="btn btn-sm btn-outline-primary <?php echo $period == 'today' ? 'active' : ''; ?>"><?php echo translate('period_today'); ?></a>
                                            <a href="?period=week" class="btn btn-sm btn-outline-primary <?php echo $period == 'week' ? 'active' : ''; ?>"><?php echo translate('period_week'); ?></a>
                                            <a href="?period=month" class="btn btn-sm btn-outline-primary <?php echo $period == 'month' ? 'active' : ''; ?>"><?php echo translate('period_month'); ?></a>
                                            <a href="?period=year" class="btn btn-sm btn-outline-primary <?php echo $period == 'year' ? 'active' : ''; ?>"><?php echo translate('period_year'); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (empty($body_area_summary)): ?>
                                <div class="alert alert-info">
                                    <?php echo translate('dashboard_no_workouts'); ?>
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($body_area_summary as $summary): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($summary['body_area'] ?: 'Other'); ?></h6>
                                                <div>
                                                    <span class="badge bg-primary">
                                                        <?php echo $summary['exercise_count']; ?> <?php echo translate('dashboard_exercises'); ?>
                                                    </span>
                                                    <span class="badge bg-info">
                                                        <?php echo intval($summary['working_sets']); ?> <?php echo translate('dashboard_sets'); ?>
                                                        <?php if ($summary['warmup_sets'] > 0): ?>
                                                            <span class="badge bg-warning" style="margin-left: 2px;">
                                                                +<?php echo intval($summary['warmup_sets']); ?> <?php echo translate('workouts_warmup'); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="badge bg-success">
                                                        <?php echo number_format(floatval($summary['working_volume'])); ?>
                                                        <?php if ($summary['warmup_volume'] > 0): ?>
                                                            <span class="badge bg-warning" style="margin-left: 2px;">
                                                                +<?php echo number_format(floatval($summary['warmup_volume'])); ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php echo translate('dashboard_total_volume'); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="progress mt-2" style="height: 5px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?php echo ($summary['working_sets'] / array_sum(array_column($body_area_summary, 'total_sets'))) * 100; ?>%">
                                                </div>
                                                <?php if ($summary['warmup_sets'] > 0): ?>
                                                    <div class="progress-bar bg-warning" role="progressbar" 
                                                         style="width: <?php echo ($summary['warmup_sets'] / array_sum(array_column($body_area_summary, 'total_sets'))) * 100; ?>%">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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

        document.getElementById('shareButton').addEventListener('click', async function() {
            // Create container with Instagram story dimensions
            const summaryHTML = `
                <div style="background: #fff; padding: 40px; width: 1080px; height: 1920px; font-family: Arial, sans-serif; display: flex; flex-direction: column; justify-content: center; align-items: center;">
                    <div style="width: 100%; max-width: 900px; background: #ffffff; padding: 40px; border-radius: 20px; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">
                        <h2 style="margin: 0 0 10px 0; color: #333; font-size: 36px; text-align: center;">Workout Summary</h2>
                        <div style="color: #666; font-size: 24px; margin-bottom: 40px; text-align: center;">${'<?php echo $period_text; ?>'}</div>
                        
                        <div style="display: flex; flex-direction: column; gap: 20px;">
                            <?php foreach ($body_area_summary as $summary): ?>
                                <div style="border: 1px solid #eee; padding: 25px; border-radius: 16px; background: #fff;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                        <div style="font-weight: 600; font-size: 28px;"><?php echo htmlspecialchars($summary['body_area'] ?: 'Other'); ?></div>
                                        <div style="display: flex; gap: 10px;">
                                            <span style="background: #0d6efd; color: white; padding: 8px 16px; border-radius: 8px; font-size: 20px;">
                                                <?php echo $summary['exercise_count']; ?> <?php echo translate('dashboard_exercises'); ?>
                                            </span>
                                            <span style="background: #0dcaf0; color: white; padding: 8px 16px; border-radius: 8px; font-size: 20px;">
                                                <?php echo intval($summary['working_sets']); ?> <?php echo translate('dashboard_sets'); ?>
                                                <?php if ($summary['warmup_sets'] > 0): ?>
                                                    <span style="background: #ffc107; padding: 4px 8px; border-radius: 4px; font-size: 18px; margin-left: 5px;">
                                                        +<?php echo intval($summary['warmup_sets']); ?> <?php echo translate('workouts_warmup'); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                            <span style="background: #198754; color: white; padding: 8px 16px; border-radius: 8px; font-size: 20px;">
                                                <?php echo number_format(floatval($summary['working_volume'])); ?>
                                                <?php if ($summary['warmup_volume'] > 0): ?>
                                                    <span style="background: #ffc107; padding: 4px 8px; border-radius: 4px; font-size: 18px; margin-left: 5px;">
                                                        +<?php echo number_format(floatval($summary['warmup_volume'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php echo translate('dashboard_total_volume'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div style="height: 8px; background: #f0f0f0; border-radius: 4px; position: relative; overflow: hidden;">
                                        <?php 
                                            $total_sets = array_sum(array_column($body_area_summary, 'total_sets'));
                                            $working_width = ($summary['working_sets'] / $total_sets) * 100;
                                            $warmup_width = ($summary['warmup_sets'] / $total_sets) * 100;
                                        ?>
                                        <div style="position: absolute; height: 100%; width: <?php echo $working_width; ?>%; background: #0d6efd;"></div>
                                        <?php if ($summary['warmup_sets'] > 0): ?>
                                            <div style="position: absolute; height: 100%; left: <?php echo $working_width; ?>%; width: <?php echo $warmup_width; ?>%; background: #ffc107;"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; color: #999; font-style: italic; font-size: 24px; text-align: center;">
                        Created with GymTrack
                    </div>
                </div>
            `;

            // Create container and add the content
            const container = document.createElement('div');
            Object.assign(container.style, {
                position: 'fixed',
                left: '-9999px',
                top: '0',
                width: '1080px',
                height: '1920px',
                background: '#ffffff'
            });
            container.innerHTML = summaryHTML;
            document.body.appendChild(container);

            // Show loading state
            const originalButtonText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + '<?php echo translate("share_generating"); ?>';
            this.disabled = true;

            try {
                // Wait for rendering
                await new Promise(resolve => setTimeout(resolve, 100));

                // Capture the image
                const canvas = await html2canvas(container, {
                    backgroundColor: '#ffffff',
                    scale: 2,
                    width: 1080,
                    height: 1920,
                    logging: false,
                    useCORS: true,
                    allowTaint: true
                });

                // Convert to blob and handle sharing/download
                canvas.toBlob(async function(blob) {
                    try {
                        if (window.location.protocol === 'https:' && navigator.share && navigator.canShare) {
                            const file = new File([blob], "workout-summary.png", { type: "image/png" });
                            const shareData = {
                                files: [file],
                                title: '<?php echo translate("share_title"); ?>',
                                text: '<?php echo translate("share_text"); ?>'
                            };
                            
                            if (navigator.canShare(shareData)) {
                                await navigator.share(shareData);
                                return;
                            }
                        }
                        
                        // Fallback to download
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'workout-summary.png';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                        
                        alert('<?php echo translate("share_download_success"); ?>');
                    } catch (error) {
                        console.error('Error sharing:', error);
                        alert('<?php echo translate("share_error"); ?>');
                    }
                }, 'image/png', 1.0);
            } catch (error) {
                console.error('Error capturing:', error);
                alert('<?php echo translate("share_error_capture"); ?>');
            } finally {
                document.body.removeChild(container);
                this.innerHTML = originalButtonText;
                this.disabled = false;
            }
        });
    });
    </script>
</body>
</html>
