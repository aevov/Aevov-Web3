<?php
/**
 * Template for the monitoring dashboard
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="monitor-controls">
        <div class="control-group">
            <select id="update-interval" class="aps-select">
                <option value="1000"><?php _e('1 second', 'aps'); ?></option>
                <option value="5000" selected><?php _e('5 seconds', 'aps'); ?></option>
                <option value="15000"><?php _e('15 seconds', 'aps'); ?></option>
                <option value="30000"><?php _e('30 seconds', 'aps'); ?></option>
            </select>
            <button id="toggle-monitoring" class="button button-secondary">
                <?php _e('Pause', 'aps'); ?>
            </button>
            <button id="clear-data" class="button button-secondary">
                <?php _e('Clear Data', 'aps'); ?>
            </button>
        </div>

        <div class="status-overview">
            <div class="status-item">
                <span class="status-label"><?php _e('System', 'aps'); ?></span>
                <span id="system-status" class="status-indicator healthy"></span>
            </div>
            <div class="status-item">
                <span class="status-label"><?php _e('Network', 'aps'); ?></span>
                <span id="network-status" class="status-indicator healthy"></span>
            </div>
            <div class="status-item">
                <span class="status-label"><?php _e('Processing', 'aps'); ?></span>
                <span id="processing-status" class="status-indicator healthy"></span>
            </div>
        </div>
    </div>

    <div class="metrics-summary">
        <div class="metric-item">
            <div class="metric-label"><?php _e('Patterns Processed', 'aps'); ?></div>
            <div id="patterns-processed" class="metric-value">0</div>
        </div>
        <div class="metric-item">
            <div class="metric-label"><?php _e('Success Rate', 'aps'); ?></div>
            <div id="success-rate" class="metric-value">0%</div>
        </div>
        <div class="metric-item">
            <div class="metric-label"><?php _e('Active Nodes', 'aps'); ?></div>
            <div id="active-nodes" class="metric-value">0</div>
        </div>
        <div class="metric-item">
            <div class="metric-label"><?php _e('Queue Size', 'aps'); ?></div>
            <div id="queue-size" class="metric-value">0</div>
        </div>
    </div>

    <div class="monitor-grid">
        <div class="monitor-card">
            <h3><?php _e('CPU Usage', 'aps'); ?></h3>
            <div class="chart-container">
                <canvas id="cpu-usage-chart"></canvas>
            </div>
        </div>

        <div class="monitor-card">
            <h3><?php _e('Memory Usage', 'aps'); ?></h3>
            <div class="chart-container">
                <canvas id="memory-usage-chart"></canvas>
            </div>
        </div>

        <div class="monitor-card">
            <h3><?php _e('Network Status', 'aps'); ?></h3>
            <div class="chart-container">
                <canvas id="network-status-chart"></canvas>
            </div>
        </div>

        <div class="monitor-card">
            <h3><?php _e('System Events', 'aps'); ?></h3>
            <div class="event-log">
                <div id="event-list" class="event-list">
                    <!-- Dynamically populated by JS -->
                </div>
            </div>
        </div>
    </div>

    <div class="monitor-card">
        <h3><?php _e('Pattern Distribution', 'aps'); ?></h3>
        <div id="pattern-distribution"></div>
    </div>
</div>

<script type="text/template" id="event-item-template">
    <div class="event-item">
        <div class="event-timestamp"><%- timestamp %></div>
        <div class="event-message"><%- message %></div>
    </div>
</script>