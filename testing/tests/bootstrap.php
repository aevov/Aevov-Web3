<?php
/**
 * PHPUnit bootstrap file for WordPress plugin testing.
 *
 * This file sets up the WordPress environment for running tests.
 */

// Define the path to PHPUnit Polyfills
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', __DIR__ . '/../../vendor/yoast/phpunit-polyfills' );
}

// Define WP_TESTS_DIR if not already defined (e.g., by phpunit.xml)
if ( ! defined( 'WP_TESTS_DIR' ) ) {
    define( 'WP_TESTS_DIR', __DIR__ . '/../wordpress-tests-lib' );
}

// Define the path to wp-tests-config.php
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
    define( 'WP_TESTS_CONFIG_FILE_PATH', WP_TESTS_DIR . '/wp-tests-config.php' );
}

// Give access to tests_add_filter() function.
require_once WP_TESTS_DIR . '/functions.php';

// Start up the WP testing environment.
require WP_TESTS_DIR . '/bootstrap.php';

// Ensure plugin.php is loaded for activate_plugin()
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Explicitly load core plugin files that might be needed early
require_once ABSPATH . 'wp-content/plugins/AevovPatternSyncProtocol/Includes/Core/Loader.php';
require_once ABSPATH . 'wp-content/plugins/AevovPatternSyncProtocol/Includes/Core/Logger.php';
require_once ABSPATH . 'wp-content/plugins/AevovPatternSyncProtocol/Includes/Monitoring/AlertManager.php';
require_once ABSPATH . 'wp-content/plugins/AevovPatternSyncProtocol/Includes/Integration/BloomIntegration.php';
require_once ABSPATH . 'wp-content/plugins/AevovPatternSyncProtocol/Includes/Queue/ProcessQueue.php';
require_once ABSPATH . 'wp-content/plugins/AevovPatternSyncProtocol/Includes/DB/MetricsDB.php';
require_once ABSPATH . 'wp-content/plugins/AevovPatternSyncProtocol/Includes/DB/APS_Queue_DB.php';
require_once ABSPATH . 'wp-content/plugins/AevovPatternSyncProtocol/Includes/DB/APS_Pattern_DB.php';
require_once ABSPATH . 'wp-content/plugins/bloom-pattern-recognition/includes/utilities/class-error-handler.php';
require_once ABSPATH . 'wp-content/plugins/bloom-pattern-recognition/includes/models/class-tensor-model.php';
require_once ABSPATH . 'wp-content/plugins/bloom-pattern-recognition/includes/models/class-chunk-model.php';
require_once ABSPATH . 'wp-content/plugins/bloom-pattern-recognition/includes/models/class-pattern-model.php';
require_once ABSPATH . 'wp-content/plugins/bloom-pattern-recognition/includes/network/class-message-queue.php';
require_once ABSPATH . 'wp-content/plugins/bloom-pattern-recognition/includes/core/class-plugin-activator.php';

// Activate necessary plugins
$plugins_to_activate = [
    'aevov-demo-system/aevov-demo-system.php',
    'bloom-pattern-recognition/bloom-pattern-system.php',
    'aps-tools/aps-tools.php',
    'aevov-onboarding-system/aevov-onboarding.php',
    'AevovPatternSyncProtocol/aevov-pattern-sync-protocol.php',
    'aevov-diagnostic-network/aevov-diagnostic-network.php',
];

foreach ( $plugins_to_activate as $plugin_basename ) {
    if ( ! is_plugin_active( $plugin_basename ) ) {
        activate_plugin( $plugin_basename );
    }
}

global $wpdb;

// Ensure APS Queue table is created for testing purposes
if (class_exists('\APS\DB\APS_Queue_DB')) {
    (new \APS\DB\APS_Queue_DB())->create_tables();
}

// Ensure APS Pattern tables are created for testing purposes
if (class_exists('\APS\DB\APS_Pattern_DB')) {
    (new \APS\DB\APS_Pattern_DB())->create_tables();
}

// Ensure APS Metrics tables are created for testing purposes
if (class_exists('\APS\DB\MetricsDB')) {
    $metrics_db = new \APS\DB\MetricsDB();
    $metrics_db->create_tables();
}

// Explicitly create BLOOM plugin tables to ensure they exist for tests
if (class_exists('\BLOOM\Core\PluginActivator')) {
    (new \BLOOM\Core\PluginActivator())->create_tables();
}

// Ensure APS Tools tables are created for testing purposes
$charset_collate = $wpdb->get_charset_collate();
$sql_aps_bloom_tensors = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aps_bloom_tensors (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, chunk_id bigint(20) unsigned NOT NULL, tensor_data_url VARCHAR(255) NOT NULL, PRIMARY KEY (id), UNIQUE KEY chunk_id (chunk_id)) {$charset_collate};";
$sql_aps_tensor_data = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aps_tensor_data (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, chunk_id bigint(20) unsigned NOT NULL, tensor_data longtext NOT NULL, tensor_shape varchar(255), tensor_type varchar(50), processed_at datetime DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY chunk_id (chunk_id)) {$charset_collate};";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql_aps_bloom_tensors);
dbDelta($sql_aps_tensor_data);
// Suppress specific wpdb::prepare warnings in test environment
// Suppress all _doing_it_wrong() notices in test environment
add_filter('doing_it_wrong_trigger_error', '__return_false');

// Define constants for testing
define( 'ADS_TESTING', true );