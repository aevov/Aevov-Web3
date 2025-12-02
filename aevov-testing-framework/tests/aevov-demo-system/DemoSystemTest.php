<?php
/**
 * Class DemoSystemTest
 *
 * @package AevovDemo
 */

/**
 * Sample test case for Aevov Demo System plugin.
 */
class DemoSystemTest extends WP_UnitTestCase {

    /**
     * Test if the Aevov Demo System plugin is active.
     */
    function test_plugin_is_active() {
        $this->assertTrue( is_plugin_active( 'aevov-demo-system/aevov-demo-system.php' ) );
    }

    /**
     * Test if the AevovDemoSystem class exists.
     */
    function test_aevov_demo_system_class_exists() {
        $this->assertTrue( class_exists( '\AevovDemo\AevovDemoSystem' ) );
    }

    /**
     * Test if the main plugin instance can be retrieved.
     */
    function test_aevov_demo_system_instance() {
        $instance = \AevovDemo\AevovDemoSystem::instance();
        $this->assertInstanceOf( '\AevovDemo\AevovDemoSystem', $instance );
    }

    /**
     * Test if the APS metrics table exists.
     */
    function test_aps_metrics_table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aps_metrics';
        $this->assertNotNull( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ), "Table {$table_name} should exist." );
    }
}