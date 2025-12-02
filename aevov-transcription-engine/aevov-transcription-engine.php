<?php
/*
Plugin Name: Aevov Transcription Engine
Plugin URI:
Description: A state-of-the-art speech-to-text engine for the Aevov network.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-transcription-engine
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovTranscriptionEngine {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        $this->include_dependencies();
        new \AevovTranscriptionEngine\API\TranscriptionEndpoint();
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-transcription-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-transcription-endpoint.php';
    }
}

new AevovTranscriptionEngine();
