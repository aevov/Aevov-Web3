<?php
/**
 * Utility functions for the APS Tools plugin
 */

function aps_tools_get_system_metrics() {
    $aps = aps_tools_get_aps_instance();
    if (!$aps) {
        return new \WP_Error('aps_missing', __('APS Plugin is not available', 'aps-tools'));
    }

    $monitor = $aps->get_system_monitor();
    return $monitor->get_system_status();
}

function aps_tools_get_recent_comparisons() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}aps_comparisons 
         ORDER BY created_at DESC LIMIT 10"
    );
}

function aps_tools_get_available_patterns() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}aps_patterns 
         WHERE status = 'active' 
         ORDER BY created_at DESC"
    );
}

function aps_tools_get_system_status() {
    $aps = aps_tools_get_aps_instance();
    if (!$aps) {
        return new \WP_Error('aps_missing', __('APS Plugin is not available', 'aps-tools'));
    }

    return $aps->get_system_monitor()->get_system_status();
}