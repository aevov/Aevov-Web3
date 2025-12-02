<?php
/**
 * Shared helper functions for the APS Tools plugin
 */

function aps_tools_get_aps_instance() {
    if (class_exists('APS\APS_Plugin')) {
        return \APS\APS_Plugin::instance();
    }
    return null;
}

function aps_tools_format_time($timestamp) {
    return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($timestamp));
}

function aps_tools_format_bytes($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $exp = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $exp), 2) . ' ' . $units[$exp];
}