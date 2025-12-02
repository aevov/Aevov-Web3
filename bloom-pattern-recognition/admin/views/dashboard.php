<?php
require_once BLOOM_PATH . 'admin/templates/partials/header.php';
?>

<div class="dashboard-summary">
    <div class="summary-card">
        <div class="label"><?php _e('Total Patterns', 'bloom-pattern-system'); ?></div>
        <div class="value" id="total-patterns">0</div>
    </div>
    <div class="summary-card">
        <div class="label"><?php _e('Active Sites', 'bloom-pattern-system'); ?></div>
        <div class="value" id="active-sites">0</div>
    </div>
    <div class="summary-card">
        <div class="label"><?php _e('Avg Processing Time', 'bloom-pattern-system'); ?></div>
        <div class="value" id="avg-processing-time">0ms</div>
    </div>
    <div class="summary-card">
        <div class="label"><?php _e('Success Rate', 'bloom-pattern-system'); ?></div>
        <div class="value" id="success-rate">0%</div>
    </div>
</div>

<div class="metrics-grid">
    <div class="metric-card">
        <div class="chart-controls">
            <h3><?php _e('Pattern Distribution', 'bloom-pattern-system'); ?></h3>
            <select id="pattern-type-filter">
                <option value="all"><?php _e('All Types', 'bloom-pattern-system'); ?></option>
                <option value="sequential"><?php _e('Sequential', 'bloom-pattern-system'); ?></option>
                <option value="structural"><?php _e('Structural', 'bloom-pattern-system'); ?></option>
                <option value="statistical"><?php _e('Statistical', 'bloom-pattern-system'); ?></option>
            </select>
        </div>
        <div class="chart-container">
            <canvas id="pattern-distribution-chart"></canvas>
        </div>
    </div>
    
    <div class="metric-card">
        <div class="chart-controls">
            <h3><?php _e('Processing Performance', 'bloom-pattern-system'); ?></h3>
            <select id="time-range">
                <option value="hour"><?php _e('Last Hour', 'bloom-pattern-system'); ?></option>
                <option value="day"><?php _e('24 Hours', 'bloom-pattern-system'); ?></option>
                <option value="week"><?php _e('7 Days', 'bloom-pattern-system'); ?></option>
            </select>
        </div>
        <div class="chart-container">
            <canvas id="performance-chart"></canvas>
        </div>
    </div>

    <div class="metric-card">
        <div class="chart-controls">
            <h3><?php _e('Network Health', 'bloom-pattern-system'); ?></h3>
            <button id="refresh-metrics" class="bloom-button bloom-button-secondary">
                <?php _e('Refresh', 'bloom-pattern-system'); ?>
            </button>
        </div>
        <div class="chart-container">
            <canvas id="network-health-chart"></canvas>
        </div>
    </div>

    <div class="metric-card">
        <h3><?php _e('Recent Activity', 'bloom-pattern-system'); ?></h3>
        <div class="activity-list" id="recent-activity">
            <!-- Dynamically populated by JS -->
        </div>
    </div>
</div>

<?php require_once BLOOM_PATH . 'admin/templates/partials/footer.php'; ?>
