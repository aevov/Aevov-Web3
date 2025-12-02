<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1><?php _e('APS System Status', 'aps-tools'); ?></h1>

    <div class="aps-status-container">
        <div class="status-header">
            <div class="status-actions">
                <button type="button" class="button button-primary" id="refresh-status">
                    <?php _e('Refresh Status', 'aps-tools'); ?>
                </button>
            </div>
            <div class="status-indicators">
                <div class="status-item">
                    <span class="status-label"><?php _e('System', 'aps-tools'); ?></span>
                    <span id="system-health" class="status-indicator"></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><?php _e('Processing', 'aps-tools'); ?></span>
                    <span id="processing-health" class="status-indicator"></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><?php _e('Network', 'aps-tools'); ?></span>
                    <span id="network-health" class="status-indicator"></span>
                </div>
            </div>
        </div>
        
        <div class="status-grid">
            <div class="status-card">
                <h3><?php _e('System Resources', 'aps-tools'); ?></h3>
                <div id="resources-chart" class="chart-container"></div>
            </div>

            <div class="status-card">
                <h3><?php _e('Processing Queue', 'aps-tools'); ?></h3>
                <div id="queue-chart" class="chart-container"></div>
            </div>

            <div class="status-card">
                <h3><?php _e('Pattern Distribution', 'aps-tools'); ?></h3>
                <div id="distribution-chart" class="chart-container"></div>
            </div>

            <div class="status-card">
                <h3><?php _e('Recent Events', 'aps-tools'); ?></h3>
                <div id="events-list" class="scrollable-list">
                </div>
            </div>
        </div>
    </div>
</div>