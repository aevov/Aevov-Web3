<?php
/*
Plugin Name: Aevov Language Engine v2
Plugin URI:
Description: Aevov's GPU-free, CDN-powered Large Language Model (LLM) engine.
Version: 2.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-language-engine-v2
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovLanguageEngineV2 {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        $this->include_dependencies();
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        new \AevovLanguageEngineV2\API\LanguageEndpoint();
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'aevov-language-assembler',
            plugin_dir_url( __FILE__ ) . 'assets/js/language-assembler.js',
            [ 'jquery' ],
            '2.0.0',
            true
        );
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-llm-ingestion-engine.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-language-weaver.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-language-endpoint.php';
    }
}

new AevovLanguageEngineV2();
