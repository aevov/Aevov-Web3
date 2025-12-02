<?php
/**
 * Fired when the Aevov Pattern Sync Protocol plugin is uninstalled.
 *
 * @package   AevovPatternSyncProtocol
 * @since     1.0.0
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Delete Plugin Options
$options_to_delete = [
    'aps_api_keys',
    'aps_settings',
    'aps_bloom_last_sync',
    'aps_bloom_sync_errors',
    'aps_confidence_threshold',
    'aps_pattern_match_threshold',
    'aps_version',
    'aps_options',
    'aps_processor_id',
    'aps_db_version',
    'featured_pattern',
    'pattern_of_the_day',
    'aps_last_pattern_sync',
    'aps_last_tensor_sync',
    'aps_last_metrics_sync',
    'aps_bloom_sync_status',
    'aps_suppressed_alerts',
    'aps_slack_webhook_url',
    'aps_last_ping',
    'aps_processing_status',
    'aps_last_activity',
    'aps_last_network_sync',
    'aps_sync_frequency',
    'aps_last_bloom_connection',
    'aps_min_confidence',
    'aps_similarity_threshold',
    'aps_sync_interval'
];

foreach ( $options_to_delete as $option_name ) {
    delete_option( $option_name );
}

// Delete any other options with a specific prefix
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'aps\_%'" );
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '\_transient\_aps\_%'" );
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '\_transient\_timeout\_aps\_%'" );

// 2. Drop Custom Database Tables
$tables_to_drop = [
    $wpdb->prefix . 'aps_patterns',
    $wpdb->prefix . 'aps_queue',
    $wpdb->prefix . 'aps_metrics',
    $wpdb->prefix . 'aps_metrics_aggregates',
    $wpdb->prefix . 'aps_alerts',
    $wpdb->prefix . 'aps_sync_log',
    $wpdb->prefix . 'aps_network_state',
    $wpdb->prefix . 'aps_patterns_cache',
    $wpdb->prefix . 'aps_symbolic_patterns',
    $wpdb->prefix . 'aps_pattern_relationships',
    $wpdb->prefix . 'aps_pattern_chunks',
    $wpdb->prefix . 'aps_health_log',
    $wpdb->prefix . 'aps_emergency_log',
];

foreach ( $tables_to_drop as $table_name ) {
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
}

// 3. Delete Post Meta
$post_meta_to_delete = [
    '_aps_processed',
    '_aps_chunk_id',
    '_aps_error'
];

foreach ( $post_meta_to_delete as $meta_key ) {
    $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s", $meta_key ) );
}

// 4. Clear any cached data
wp_cache_flush();
