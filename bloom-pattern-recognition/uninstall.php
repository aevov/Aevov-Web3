<?php
/**
 * Fired when the BLOOM Pattern Recognition plugin is uninstalled.
 *
 * @package   Bloom_Pattern_Recognition
 * @since     1.0.0
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// 1. Delete Plugin Options
$options_to_delete = [
    'bloom_chunk_scanner_stats',
    'bloom_chunk_scanner_last_scan',
    'bloom_chunk_scanner_total_scanned',
    'bloom_chunk_scanner_chunks_found',
    'bloom_chunk_size',
    'bloom_sites_per_chunk',
    'bloom_sync_interval',
    'bloom_batch_size',
    'bloom_confidence_threshold',
    'bloom_typebot_webhook',
    'bloom_typebot_secret',
    'bloom_options',
    'bloom_typebot_endpoint',
    'bloom_last_sync',
    'bloom_network_status'
];

foreach ( $options_to_delete as $option_name ) {
    delete_option( $option_name );
}

// Delete any other options with a specific prefix
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'bloom\_%'" );
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '\_transient\_bloom\_%'" );
$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '\_transient\_timeout\_bloom\_%'" );

// 2. Drop Custom Database Tables
$tables_to_drop = [
    $wpdb->prefix . 'bloom_tensors',
    $wpdb->prefix . 'bloom_chunks',
    $wpdb->prefix . 'bloom_patterns',
    $wpdb->prefix . 'bloom_error_log'
];

foreach ( $tables_to_drop as $table_name ) {
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
}

// 3. Delete Custom Post Type Data
$cpt_to_delete = 'bloom_chunk';
$posts = get_posts( [
    'post_type'   => $cpt_to_delete,
    'numberposts' => -1,
    'post_status' => 'any',
] );

foreach ( $posts as $post ) {
    wp_delete_post( $post->ID, true );
}

// 4. Delete Post Meta
$post_meta_to_delete = [
    '_chunk_sku'
];

foreach ( $post_meta_to_delete as $meta_key ) {
    $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = %s", $meta_key ) );
}

// 5. Delete Directories
$upload_dir = wp_upload_dir();
$bloom_chunks_dir = $upload_dir['basedir'] . '/bloom-chunks';
$bloom_tensors_dir = $upload_dir['basedir'] . '/bloom-tensors';

if ( is_dir( $bloom_chunks_dir ) ) {
    // Recursively remove directory
    function bloom_delete_dir($dirPath) {
        if (!is_dir($dirPath)) {
            return;
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                bloom_delete_dir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }
    bloom_delete_dir($bloom_chunks_dir);
    bloom_delete_dir($bloom_tensors_dir);
}


// 6. Clear any cached data
wp_cache_flush();
