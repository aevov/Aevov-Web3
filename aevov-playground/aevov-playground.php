<?php
/*
Plugin Name: Aevov Playground
Plugin URI:
Description: A unified, composable playground for all Aevov engines.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-playground
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovPlayground {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    private $admin_page_hook_suffix;

    public function init() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        $this->include_dependencies();
        new \AevovPlayground\API\PlaygroundEndpoint();
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-playground-endpoint.php';
    }

    public function add_admin_menu() {
        $this->admin_page_hook_suffix = add_menu_page(
            __( 'Aevov Playground', 'aevov-playground' ),
            __( 'Aevov Playground', 'aevov-playground' ),
            'manage_options',
            'aevov-playground',
            [ $this, 'render_admin_page' ],
            'dashicons-buddicons-buddypress-logo',
            3
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'aevov-playground',
            plugin_dir_url( __FILE__ ) . 'assets/js/playground.js',
            [ 'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable' ],
            '1.0.0',
            true
        );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }
}

new AevovPlayground();
