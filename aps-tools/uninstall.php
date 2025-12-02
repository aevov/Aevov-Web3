<?php
/**
 * Fired when the APS Tools plugin is uninstalled.
 *
 * @package   APS_Tools
 * @since     1.0.0
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Delete Plugin Options
$options_to_delete = [
    'aps_dual_storage_enabled',
    'aps_validate_json',
    'aps_sync_interval',
    'aps_debug_mode',
    'aps_max_batch_size',
    'aps_cubbit_access_key',
    'aps_cubbit_secret_key',
    'aps_cubbit_bucket_name',
    'aps_litespeed_tensor_cache',
    'aps_litespeed_pattern_cache',
    'aps_litespeed_cubbit_cache',
    'aps_litespeed_cache_ttl',
    'aps_scanner_config'
];

foreach ( $options_to_delete as $option_name ) {
    delete_option( $option_name );
}

// It's possible some options are shared with AevovPatternSyncProtocol.
// This is intentional to ensure a full cleanup.
// A wildcard delete is not used here to avoid accidentally deleting options from the other plugin if it's still active.


// 2. Delete Log Files and Directories
$upload_dir = wp_upload_dir();
$log_file = $upload_dir['basedir'] . '/aps-tools/integration-test.log';
$log_dir = $upload_dir['basedir'] . '/aps-tools';

if ( file_exists( $log_file ) ) {
    unlink( $log_file );
}
if ( is_dir( $log_dir ) ) {
    // Check if the directory is empty before removing
    if (count(scandir($log_dir)) == 2) { // '.' and '..'
        rmdir( $log_dir );
    }
}

// 3. Clear any cached data
wp_cache_flush();
