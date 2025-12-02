<?php
/*
Plugin Name: Aevov Simulation Engine
Plugin URI:
Description: Real-time, infinitely running simulation engine for the Aevov network.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-simulation-engine
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovSimulationEngine {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    private $admin_page_hook_suffix;

    public function init() {
        $this->include_dependencies();
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        new \AevovSimulationEngine\API\SimulationEndpoint();
    }

    public function register_settings() {
        register_setting( 'aevov_simulation_engine_options', 'aevov_simulation_engine_options' );
        add_settings_section( 'aevov_simulation_engine_main', __( 'Main Settings', 'aevov-simulation-engine' ), null, 'aevov_simulation_engine' );
        add_settings_field( 'aevov_simulation_engine_tick_rate', __( 'Tick Rate', 'aevov-simulation-engine' ), [ $this, 'render_tick_rate_field' ], 'aevov_simulation_engine', 'aevov_simulation_engine_main' );
    }

    public function render_tick_rate_field() {
        $options = get_option( 'aevov_simulation_engine_options' );
        $tick_rate = isset( $options['tick_rate'] ) ? $options['tick_rate'] : 1;
        echo '<input type="text" name="aevov_simulation_engine_options[tick_rate]" value="' . esc_attr( $tick_rate ) . '">';
    }

    public function add_admin_menu() {
        $this->admin_page_hook_suffix = add_menu_page(
            __( 'Aevov Simulation Engine', 'aevov-simulation-engine' ),
            __( 'Aevov Simulation Engine', 'aevov-simulation-engine' ),
            'manage_options',
            'aevov-simulation-engine',
            [ $this, 'render_admin_page' ],
            'dashicons-games',
            84
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'aevov-simulation-viewer',
            plugin_dir_url( __FILE__ ) . 'assets/js/simulation-viewer.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }

    public function render_virtual_brain( $model ) {
        // This is a placeholder.
        // In a real implementation, this would render a 3D visualization
        // of the composed model.
        return '<pre>' . print_r( $model, true ) . '</pre>';
    }

    public function render_virtual_hippocampus( $memory_system ) {
        // This is a placeholder.
        // In a real implementation, this would render a 3D visualization
        // of the memory system.
        return '<pre>' . print_r( $memory_system, true ) . '</pre>';
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-simulation-weaver.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-job-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-websocket-server.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-simulation-worker.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-simulation-endpoint.php';
    }
}

new AevovSimulationEngine();
