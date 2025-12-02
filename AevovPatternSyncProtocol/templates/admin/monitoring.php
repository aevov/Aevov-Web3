<?php
/**
 * Template for the APS monitoring page
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php _e('APS Monitoring', 'aps'); ?></h1>

    <div class="aps-monitoring">
        <div class="aps-card">
            <h2><?php _e('System Metrics', 'aps'); ?></h2>
            <div id="system-metrics"></div>
        </div>

        <div class="aps-card">
            <h2><?php _e('Network Metrics', 'aps'); ?></h2>
            <div id="network-metrics"></div>
        </div>

        <div class="aps-card">
            <h2><?php _e('Processing Metrics', 'aps'); ?></h2>
            <div id="processing-metrics"></div>
        </div>
    </div>
</div>