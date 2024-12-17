<?php
if (!isset($summary) || !isset($period)) {
    return;
}
?>
<div class="card summary-card">
    <div class="card-header">
        <div class="summary-header">
            <h5 class="card-title">Workout Summary</h5>
            <div class="summary-controls">
                <!-- Date Filter Form -->
                <form class="date-filter-form" method="GET">
                    <div class="date-inputs">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?php echo $_GET['start_date'] ?? ''; ?>"
                                   aria-label="Start Date">
                        </div>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                            <input type="date" name="end_date" class="form-control" 
                                   value="<?php echo $_GET['end_date'] ?? ''; ?>"
                                   aria-label="End Date">
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-filter me-1"></i><span>Filter</span>
                        </button>
                    </div>
                </form>
                
                <!-- Period Buttons -->
                <div class="period-buttons">
                    <a href="?period=today" 
                       class="btn btn-sm btn-outline-primary <?php echo ($period ?? 'today') == 'today' ? 'active' : ''; ?>">
                       Today
                    </a>
                    <a href="?period=week" 
                       class="btn btn-sm btn-outline-primary <?php echo ($period ?? '') == 'week' ? 'active' : ''; ?>">
                       Week
                    </a>
                    <a href="?period=month" 
                       class="btn btn-sm btn-outline-primary <?php echo ($period ?? '') == 'month' ? 'active' : ''; ?>">
                       Month
                    </a>
                    <a href="?period=year" 
                       class="btn btn-sm btn-outline-primary <?php echo ($period ?? '') == 'year' ? 'active' : ''; ?>">
                       Year
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($summary)): ?>
            <div class="alert alert-info">
                No workouts recorded for this period.
            </div>
        <?php else: ?>
            <div class="workout-summary-grid">
                <?php foreach ($summary as $item): ?>
                    <div class="workout-summary-item">
                        <div class="workout-header">
                            <h6 class="workout-area">
                                <?php echo htmlspecialchars($item['body_area'] ?: 'Other'); ?>
                            </h6>
                            <div class="workout-stats">
                                <span class="stat-badge exercises">
                                    <i class="fas fa-dumbbell"></i>
                                    <span><?php echo $item['exercise_count']; ?> <?php echo translate('dashboard_exercises'); ?></span>
                                </span>
                                <span class="stat-badge sets">
                                    <i class="fas fa-layer-group"></i>
                                    <span>
                                        <?php 
                                        $working_sets = intval($item['working_sets'] ?? 0);
                                        $warmup_sets = intval($item['warmup_sets'] ?? 0);
                                        if ($working_sets > 0) {
                                            echo $working_sets . ' ' . translate('workouts_sets');
                                            if ($warmup_sets > 0) {
                                                echo ' <small>(+' . $warmup_sets . ' ' . translate('workouts_warmup') . ')</small>';
                                            }
                                        } else {
                                            echo translate('no_data_available');
                                        }
                                        ?>
                                    </span>
                                </span>
                                <span class="stat-badge volume">
                                    <i class="fas fa-weight-hanging"></i>
                                    <span>
                                        <?php 
                                        $working_volume = floatval($item['working_volume'] ?? 0);
                                        $warmup_volume = floatval($item['warmup_volume'] ?? 0);
                                        if ($working_volume > 0) {
                                            echo number_format($working_volume);
                                            if ($warmup_volume > 0) {
                                                echo ' <small>(+' . number_format($warmup_volume) . ')</small>';
                                            }
                                            echo ' ' . translate('workouts_weight');
                                        } else {
                                            echo translate('no_data_available');
                                        }
                                        ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?php echo ($working_sets / array_sum(array_column($summary, 'total_sets'))) * 100; ?>%"
                                 title="<?php echo number_format(($working_sets / array_sum(array_column($summary, 'total_sets'))) * 100, 1); ?>%">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.summary-card {
    border: none;
    box-shadow: var(--shadow);
}

.date-filter-form {
    min-width: 200px;
}

.date-input-group {
    max-width: 160px;
}

.workout-summary-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.workout-summary-item {
    padding: 0.75rem;
    background: var(--light-bg);
    border-radius: 0.5rem;
    transition: all 0.2s ease;
}

.workout-summary-item:hover {
    background: var(--white);
    box-shadow: var(--shadow-sm);
    transform: translateY(-1px);
}

.workout-area {
    font-size: 0.95rem;
    color: var(--text-dark);
    font-weight: 500;
}

.workout-stats .badge {
    font-weight: 500;
    padding: 0.5em 0.8em;
}

.progress {
    background-color: rgba(0,0,0,0.05);
    overflow: hidden;
}

.progress-bar {
    background: var(--primary-color);
    transition: width 0.6s ease;
}

@media (max-width: 768px) {
    .date-filter-form {
        width: 100%;
    }
    
    .date-input-group {
        max-width: none;
        width: calc(50% - 0.5rem);
    }
    
    .period-buttons {
        width: 100%;
    }
    
    .period-buttons .btn {
        flex: 1;
        padding: 0.375rem;
        font-size: 0.8rem;
    }
    
    .workout-stats {
        margin-top: 0.5rem;
        width: 100%;
        justify-content: space-between;
    }
    
    .workout-stats .badge {
        flex: 1;
        text-align: center;
        white-space: normal;
        font-size: 0.75rem;
    }
}
</style>