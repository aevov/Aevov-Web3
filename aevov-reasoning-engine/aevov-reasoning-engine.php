<?php
/*
Plugin Name: Aevov Reasoning Engine
Plugin URI:
Description: An analogy-based reasoning engine for the Aevov network.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-reasoning-engine
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovReasoningEngine {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    private $admin_page_hook_suffix;

    public function init() {
        $this->include_dependencies();
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        new \AevovReasoningEngine\API\ReasoningEndpoint();
    }

    public function add_admin_menu() {
        $this->admin_page_hook_suffix = add_menu_page(
            __( 'Aevov Reasoning Engine', 'aevov-reasoning-engine' ),
            __( 'Aevov Reasoning Engine', 'aevov-reasoning-engine' ),
            'manage_options',
            'aevov-reasoning-engine',
            [ $this, 'render_admin_page' ],
            'dashicons-lightbulb',
            90
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'aevov-reasoning-query',
            plugin_dir_url( __FILE__ ) . 'assets/js/reasoning-query.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-reasoning-endpoint.php';
    }
}

new AevovReasoningEngine();
