<?php
/*
Plugin Name: Aevov Cognitive Engine
Plugin URI:
Description: The cognitive layer of the Aevov network, integrating the Hierarchical Reasoning Model (HRM).
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-cognitive-engine
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovCognitiveEngine {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    private $admin_page_hook_suffix;

    public function init() {
        $this->include_dependencies();
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        new \AevovCognitiveEngine\API\CognitiveEndpoint();
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-cognitive-conductor.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-hrm-module.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-cognitive-endpoint.php';
    }

    public function add_admin_menu() {
        $this->admin_page_hook_suffix = add_menu_page(
            __( 'Aevov Cognitive Engine', 'aevov-cognitive-engine' ),
            __( 'Aevov Cognitive Engine', 'aevov-cognitive-engine' ),
            'manage_options',
            'aevov-cognitive-engine',
            [ $this, 'render_admin_page' ],
            'dashicons-brain',
            91
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'aevov-problem-solver',
            plugin_dir_url( __FILE__ ) . 'assets/js/problem-solver.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }
}

new AevovCognitiveEngine();
