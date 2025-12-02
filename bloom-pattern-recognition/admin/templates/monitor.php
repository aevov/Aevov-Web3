
<?php
require_once BLOOM_PATH . 'admin/templates/partials/header.php';
?>

<div class="monitor-controls">
    <div class="control-group">
        <select id="update-interval" class="bloom-select">
            <option value="1000"><?php _e('1 second', 'bloom-pattern-system'); ?></option>
            <option value="5000" selected><?php _e('5 seconds', 'bloom-pattern-system'); ?></option>
            <option value="15000"><?php _e('15 seconds', 'bloom-pattern-system'); ?></option>
            <option value="30000"><?php _e('30 seconds', 'bloom-pattern-system'); ?></option>
        </select>
        <button id="toggle-monitoring" class="bloom-button bloom-button-secondary">
            <?php _e('Pause', 'bloom-pattern-system'); ?>
        </button>
        <button id="clear-data" class="bloom-button bloom-button-secondary">
            <?php _e('Clear Data', 'bloom-pattern-system'); ?>
        </button>
    </div>

    <div class="status-overview">
        <div class="status-item">
            <span class="status-label"><?php _e('System', 'bloom-pattern-system'); ?></span>
            <span id="system-status" class="status-indicator healthy"></span>
        </div>
        <div class="status-item">
            <span class="status-label"><?php _e('Network', 'bloom-pattern-system'); ?></span>
            <span id="network-status" class="status-indicator healthy"></span>
        </div>
        <div class="status-item">
            <span class="status-label"><?php _e('Processing', 'bloom-pattern-system'); ?></span>
            <span id="processing-status" class="status-indicator healthy"></span>
        </div>
    </div>
</div>

<div class="monitor-grid">
    <div class="monitor-card">
        <h3><?php _e('CPU Usage', 'bloom-pattern-system'); ?></h3>
        <div class="chart-container">
            <canvas id="cpu-usage-chart"></canvas>
        </div>
    </div>

    <div class="monitor-card">
        <h3><?php _e('Memory Usage', 'bloom-pattern-system'); ?></h3>
        <div class="chart-container">
            <canvas id="memory-usage-chart"></canvas>
        </div>
    </div>

    <div class="monitor-card">
        <h3><?php _e('Network Status', 'bloom-pattern-system'); ?></h3>
        <div class="chart-container">
            <canvas id="network-status-chart"></canvas>
        </div>
    </div>

    <div class="monitor-card">
        <h3><?php _e('System Events', 'bloom-pattern-system'); ?></h3>
        <div class="event-log" id="system-events">
            <div class="event-list">
                <!-- Dynamically populated by JS -->
            </div>
        </div>
    </div>
</div>

<?php require_once BLOOM_PATH . 'admin/templates/partials/footer.php'; ?>