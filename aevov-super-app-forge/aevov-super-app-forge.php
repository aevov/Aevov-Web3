<?php
/*
Plugin Name: Aevov Super-App Forge
Plugin URI:
Description: Universal application replicator and real-time generation engine for the Aevov network.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-super-app-forge
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovSuperAppForge {

    private $admin_page_hook_suffix;

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        $this->include_dependencies();
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        new \AevovSuperAppForge\API\ApplicationEndpoint();
        new \AevovSuperAppForge\API\SimulationEndpoint();
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-app-ingestion-engine.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-super-app-weaver.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-job-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-websocket-server.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-application-endpoint.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-simulation-endpoint.php';
    }

    public function add_admin_menu() {
        $this->admin_page_hook_suffix = add_menu_page(
            __( 'Aevov Super-App Forge', 'aevov-super-app-forge' ),
            __( 'Aevov Super-App Forge', 'aevov-super-app-forge' ),
            'manage_options',
            'aevov-super-app-forge',
            [ $this, 'render_admin_page' ],
            'dashicons-superhero',
            86
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'aevov-app-generator',
            plugin_dir_url( __FILE__ ) . 'assets/js/app-generator.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }
}

new AevovSuperAppForge();
