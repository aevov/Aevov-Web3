<?php
/**
 * Plugin Name: Aevov Pattern Sync Protocol
 * Plugin URI:
 * Description: Core plugin for Aevov Pattern Synchronization and Analysis.
 * Version: 1.0.0
 * Author: Your Name/Company
 * Author URI:
 * License: GPL2
 * Text Domain: aps
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
if ( ! defined( 'APS_FILE' ) ) {
    define( 'APS_FILE', __FILE__ );
}

if ( ! defined( 'AEVOV_PATTERN_SYNC_PATH' ) ) {
    define( 'AEVOV_PATTERN_SYNC_PATH', function_exists('plugin_dir_path') ? plugin_dir_path( __FILE__ ) : dirname( __FILE__ ) . '/' );
}

require_once AEVOV_PATTERN_SYNC_PATH . 'vendor/autoload.php';

// Initialize the plugin.
if ( ! function_exists( 'APS' ) ) {
    function APS() {
        return \APS\Analysis\APS_Plugin::instance();
    }
}

// Globalize the instance.
$GLOBALS['aps'] = APS();