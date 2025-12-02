<?php
/*
Plugin Name: Aevov Chunk Registry
Plugin URI:
Description: A central registry for all Aevov chunks.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-chunk-registry
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovChunkRegistry {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        $this->include_dependencies();
        new \AevovChunkRegistry\ChunkRegistry();
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-aevov-chunk.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-chunk-registry.php';
        require_once plugin_dir_path( __FILE__ ) . '../aevov-embedding-engine/includes/class-embedding-manager.php';
    }

    public function activate() {
        $this->create_chunks_table();
    }

    private function create_chunks_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aevov_chunks';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (\n            id mediumint(9) NOT NULL AUTO_INCREMENT,\n            chunk_id varchar(255) NOT NULL,\n            type varchar(255) NOT NULL,\n            cubbit_key varchar(255) DEFAULT NULL,\n            metadata text NOT NULL,\n            dependencies text NOT NULL,\n            embedding longtext NOT NULL,\n            PRIMARY KEY  (id),\n            KEY chunk_id (chunk_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}

new AevovChunkRegistry();
