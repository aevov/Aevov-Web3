<?php
/*
Plugin Name: Aevov Embedding Engine
Plugin URI:
Description: A service for converting text, images, and other data into embeddings.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-embedding-engine
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovEmbeddingEngine {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        $this->include_dependencies();
        new \AevovEmbeddingEngine\API\EmbeddingEndpoint();
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-embedding-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-embedding-endpoint.php';
    }
}

new AevovEmbeddingEngine();
