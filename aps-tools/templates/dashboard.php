<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1><?php _e('Aevov Pattern System', 'aps-tools'); ?></h1>

    <div class="aps-dashboard-grid">
        <div class="aps-card">
            <h2><?php _e('System Overview', 'aps-tools'); ?></h2>
            <p><?php _e('Welcome to the Aevov Pattern System. This dashboard provides a high-level overview of the system and its performance.', 'aps-tools'); ?></p>
            <ul>
                <li><strong><?php _e('Total Patterns:', 'aps-tools'); ?></strong> <span id="total-patterns">0</span></li>
                <li><strong><?php _e('Patterns Processed Today:', 'aps-tools'); ?></strong> <span id="patterns-today">0</span></li>
                <li><strong><?php _e('System Health:', 'aps-tools'); ?></strong> <span id="system-health">OK</span></li>
            </ul>
        </div>

        <div class="aps-card">
            <h2><?php _e('Quick Links', 'aps-tools'); ?></h2>
            <ul>
                <li><a href="<?php echo admin_url('admin.php?page=aps-analysis'); ?>"><?php _e('Analyze a Pattern', 'aps-tools'); ?></a></li>
                <li><a href="<?php echo admin_url('admin.php?page=aps-comparison'); ?>"><?php _e('Compare Patterns', 'aps-tools'); ?></a></li>
                <li><a href="<?php echo admin_url('admin.php?page=aps-patterns'); ?>"><?php _e('View All Patterns', 'aps-tools'); ?></a></li>
                <li><a href="<?php echo admin_url('admin.php?page=apstools-settings'); ?>"><?php _e('System Settings', 'aps-tools'); ?></a></li>
            </ul>
        </div>
    </div>

    <div class="aps-metrics-grid">
        <div class="metric-card">
            <h3><?php _e('System Status', 'aps-tools'); ?></h3>
            <div id="system-status-chart" class="chart-container"></div>
            <div class="metric-summary">
                <div class="metric">
                    <span class="label"><?php _e('CPU Usage', 'aps-tools'); ?></span>
                    <span class="value" id="cpu-usage">0%</span>
                </div>
                <div class="metric">
                    <span class="label"><?php _e('Memory Usage', 'aps-tools'); ?></span>
                    <span class="value" id="memory-usage">0%</span>
                </div>
            </div>
        </div>

        <div class="metric-card">
            <h3><?php _e('Pattern Type Distribution', 'aps-tools'); ?></h3>
            <div id="pattern-type-chart" class="chart-container"></div>
        </div>

        <div class="metric-card">
            <h3><?php _e('Recent Patterns', 'aps-tools'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'aps-tools'); ?></th>
                        <th><?php _e('Type', 'aps-tools'); ?></th>
                        <th><?php _e('Confidence', 'aps-tools'); ?></th>
                        <th><?php _e('Date', 'aps-tools'); ?></th>
                    </tr>
                </thead>
                <tbody id="recent-patterns-table">
                    <!-- Populated via JavaScript -->
                </tbody>
            </table>
        </div>

        <div class="metric-card">
            <h3><?php _e('Pattern Analysis', 'aps-tools'); ?></h3>
            <div id="pattern-metrics-chart" class="chart-container"></div>
            <div class="metric-summary">
                <div class="metric">
                    <span class="label"><?php _e('Patterns Processed', 'aps-tools'); ?></span>
                    <span class="value" id="patterns-processed">0</span>
                </div>
                <div class="metric">
                    <span class="label"><?php _e('Average Confidence', 'aps-tools'); ?></span>
                    <span class="value" id="avg-confidence">0%</span>
                </div>
            </div>
        </div>
    </div>
</div>