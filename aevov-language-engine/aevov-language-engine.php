<?php
/*
Plugin Name: Aevov Language Engine
Plugin URI:
Description: Aevov's Large Language Model (LLM) engine, powered by chunked, pre-existing models.
Version: 1.0.0
Author: Jules
Author URI:
License: GPL2
Text Domain: aevov-language-engine
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovLanguageEngine {

    private static $instance = null;
    public $ingestion_engine;
    public $weaver;
    private $admin_page_hook_suffix;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        $this->include_dependencies();
        $this->ingestion_engine = new \AevovLanguageEngine\Core\LLMIngestionEngine();
        $this->weaver = new \AevovLanguageEngine\Core\LanguageWeaver();
        new \AevovLanguageEngine\API\LanguageEndpoint( $this->weaver );

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-llm-ingestion-engine.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-language-weaver.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-language-worker.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-language-endpoint.php';

        // Real NLP components
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-tokenizer.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-language-model.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-text-processor.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-semantic-analyzer.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-template-engine.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-intent-classifier.php';
    }

    public function add_admin_menu() {
        $this->admin_page_hook_suffix = add_menu_page(
            __( 'Aevov Language Engine', 'aevov-language-engine' ),
            __( 'Aevov Language Engine', 'aevov-language-engine' ),
            'manage_options',
            'aevov-language-engine',
            [ $this, 'render_admin_page' ],
            'dashicons-text',
            87
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'aevov-text-generator',
            plugin_dir_url( __FILE__ ) . 'assets/js/text-generator.js',
            [ 'jquery', 'wp-api' ],
            '1.0.1',
            true
        );

        wp_localize_script( 'aevov-text-generator', 'aevovLanguageEngine', [
            'apiUrl' => rest_url( 'aevov-language-engine/v1/generate' ),
            'nonce'  => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }

    /**
     * Ingests a model from a given path.
     *
     * @param string $model_path The path to the model file.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function ingest_model( $model_path ) {
        return $this->ingestion_engine->ingest_from_path( $model_path );
    }

    /**
     * Generates text based on a given prompt and parameters.
     *
     * @param string $prompt The input prompt.
     * @param array $params Generation parameters.
     * @return string The generated text.
     */
    public function generate_text( $prompt, $params = [] ) {
        return $this->weaver->generate( $prompt, $params );
    }
}

new AevovLanguageEngine();
