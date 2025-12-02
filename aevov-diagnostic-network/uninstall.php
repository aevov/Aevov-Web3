<?php
/**
 * Aevov Diagnostic Network - Uninstall Script
 * 
 * This file is executed when the plugin is uninstalled via WordPress admin.
 * It handles complete removal of plugin data, files, and database tables.
 * 
 * @package AevovDiagnosticNetwork
 * @since 1.0.0
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define plugin constants
define('ADN_VERSION', '1.0.0');
define('ADN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADN_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load the deactivator class
require_once ADN_PLUGIN_DIR . 'includes/Core/Deactivator.php';

// Execute complete uninstall
ADN\Core\Deactivator::uninstall();

// Additional cleanup for multisite
if (is_multisite()) {
    global $wpdb;
    
    // Get all blog IDs
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
    
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        
        // Run uninstall for each site
        ADN\Core\Deactivator::uninstall();
        
        restore_current_blog();
    }
}

// Clear any remaining cache
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Log final uninstall
error_log('Aevov Diagnostic Network: Complete uninstall finished');