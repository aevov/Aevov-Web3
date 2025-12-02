<?php
/*
Plugin Name: Aevov Memory Core
Plugin URI:
Description: A dynamic, biologically-inspired memory system for the Aevov network.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-memory-core
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovMemoryCore {

    private $admin_page_hook_suffix;

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
    }

    public function init() {
        $this->include_dependencies();
        add_action( 'init', [ $this, 'register_astrocyte_post_type' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        new \AevovMemoryCore\API\MemoryEndpoint();
    }

    public function register_astrocyte_post_type() {
        $labels = [
            'name'                  => _x( 'Astrocytes', 'Post type general name', 'aevov-memory-core' ),
            'singular_name'         => _x( 'Astrocyte', 'Post type singular name', 'aevov-memory-core' ),
        ];
        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'astrocyte' ],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => [ 'title', 'editor', 'custom-fields' ],
        ];
        register_post_type( 'astrocyte', $args );
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-memory-pattern.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-memory-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-memory-endpoint.php';
    }

    public function add_admin_menu() {
        $this->admin_page_hook_suffix = add_menu_page(
            __( 'Aevov Memory Core', 'aevov-memory-core' ),
            __( 'Aevov Memory Core', 'aevov-memory-core' ),
            'manage_options',
            'aevov-memory-core',
            [ $this, 'render_admin_page' ],
            'dashicons-database',
            89
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'aevov-memory-designer',
            plugin_dir_url( __FILE__ ) . 'assets/js/memory-designer.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }

    public function activate() {
        $this->create_memory_data_table();
    }

    private function create_memory_data_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aevov_memory_data';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            address varchar(255) NOT NULL,
            data longtext NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY address (address)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}

new AevovMemoryCore();
