<?php
/*
Plugin Name: Aevov Application Forge
Plugin URI:
Description: Real-time application generation and streaming for the Aevov network.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-application-forge
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovApplicationForge {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    private $admin_page_hook_suffix;

    public function init() {
        $this->include_dependencies();
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        new \AevovApplicationForge\API\ApplicationEndpoint();
    }

    public function register_settings() {
        register_setting( 'aevov_application_forge_options', 'aevov_application_forge_options' );
        add_settings_section( 'aevov_application_forge_main', __( 'Main Settings', 'aevov-application-forge' ), null, 'aevov_application_forge' );
        add_settings_field( 'aevov_application_forge_worker_url', __( 'Worker URL', 'aevov-application-forge' ), [ $this, 'render_worker_url_field' ], 'aevov_application_forge', 'aevov_application_forge_main' );
    }

    public function render_worker_url_field() {
        $options = get_option( 'aevov_application_forge_options' );
        $worker_url = isset( $options['worker_url'] ) ? $options['worker_url'] : '';
        echo '<input type="text" name="aevov_application_forge_options[worker_url]" value="' . esc_attr( $worker_url ) . '">';
    }

    public function add_admin_menu() {
        $this->admin_page_hook_suffix = add_menu_page(
            __( 'Aevov Application Forge', 'aevov-application-forge' ),
            __( 'Aevov Application Forge', 'aevov-application-forge' ),
            'manage_options',
            'aevov-application-forge',
            [ $this, 'render_admin_page' ],
            'dashicons-admin-generic',
            85
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'aevov-application-viewer',
            plugin_dir_url( __FILE__ ) . 'assets/js/application-viewer.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-application-weaver.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-job-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-websocket-server.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-application-worker.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-application-endpoint.php';
    }
}

new AevovApplicationForge();
