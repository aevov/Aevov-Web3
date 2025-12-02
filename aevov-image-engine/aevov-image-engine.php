<?php
/*
Plugin Name: Aevov Image Engine
Plugin URI:
Description: Sophisticated image generation for the Aevov network.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-image-engine
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovImageEngine {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    private $admin_page_hook_suffix;

    public function init() {
        $this->include_dependencies();
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        add_action( 'aevov_image_engine_cron', [ $this, 'run_worker' ] );
        new \AevovImageEngine\API\ImageEndpoint();
    }

    public function activate() {
        $this->create_image_jobs_table();
        if ( ! wp_next_scheduled( 'aevov_image_engine_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'aevov_image_engine_cron' );
        }
    }

    private function create_image_jobs_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aevov_image_jobs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            job_id varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            params text NOT NULL,
            status varchar(255) NOT NULL,
            image_url varchar(255) NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY job_id (job_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function register_settings() {
        register_setting( 'aevov_image_engine_options', 'aevov_image_engine_options' );
        add_settings_section( 'aevov_image_engine_main', __( 'Main Settings', 'aevov-image-engine' ), null, 'aevov_image_engine' );
        add_settings_field( 'aevov_image_engine_upscaling', __( 'Enable Upscaling', 'aevov-image-engine' ), [ $this, 'render_upscaling_field' ], 'aevov_image_engine', 'aevov_image_engine_main' );
    }

    public function render_upscaling_field() {
        $options = get_option( 'aevov_image_engine_options' );
        $upscaling = isset( $options['upscaling'] ) ? $options['upscaling'] : 0;
        echo '<input type="checkbox" name="aevov_image_engine_options[upscaling]" value="1" ' . checked( 1, $upscaling, false ) . '>';
    }

    public function add_admin_menu() {
        $this->admin_page_hook_suffix = add_menu_page(
            __( 'Aevov Image Engine', 'aevov-image-engine' ),
            __( 'Aevov Image Engine', 'aevov-image-engine' ),
            'manage_options',
            'aevov-image-engine',
            [ $this, 'render_admin_page' ],
            'dashicons-format-image',
            82
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'aevov-image-generator',
            plugin_dir_url( __FILE__ ) . 'assets/js/image-generator.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-image-weaver.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-job-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-image-worker.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-image-endpoint.php';
    }

    public function run_worker() {
        $worker = new \AevovImageEngine\ImageWorker();
        $worker->run();
    }
}

new AevovImageEngine();
