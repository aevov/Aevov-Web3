<?php
/**
 * Renders the admin pages for the APS Tools plugin
 */

function aps_tools_render_dashboard() {
    $aps = aps_tools_get_aps_instance();
    if (!$aps) {
        aps_tools_render_aps_missing_notice();
        return;
    }

    $metrics = aps_tools_get_system_metrics();
    include plugin_dir_path(__FILE__) . 'templates/dashboard.php';
}

function aps_tools_render_analysis() {
    $aps = aps_tools_get_aps_instance();
    if (!$aps) {
        aps_tools_render_aps_missing_notice();
        return;
    }

    $patterns = aps_tools_get_available_patterns();
    include plugin_dir_path(__FILE__) . 'templates/analysis.php';
}

function aps_tools_render_comparison() {
    $aps = aps_tools_get_aps_instance();
    if (!$aps) {
        aps_tools_render_aps_missing_notice();
        return;
    }

    $recent_comparisons = aps_tools_get_recent_comparisons();
    include plugin_dir_path(__FILE__) . 'templates/comparison.php';
}

function aps_tools_render_status() {
    $aps = aps_tools_get_aps_instance();
    if (!$aps) {
        aps_tools_render_aps_missing_notice();
        return;
    }

    $status = aps_tools_get_system_status();
    include plugin_dir_path(__FILE__) . 'templates/status.php';
}

function aps_tools_render_aps_missing_notice() {
    echo '<div class="notice notice-error"><p>';
    echo esc_html__('APS Plugin is required but not active. Please install and activate the APS Plugin to use these tools.', 'aps-tools');
    echo '</p></div>';
}