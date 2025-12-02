<?php
/**
 * Plugin Name: Aevov Neuro-Architect
 * Description: A sophisticated framework for composing new models from a library of fundamental neural patterns.
 * Version: 1.0.0
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/includes/class-neural-pattern-catalog.php';
require_once __DIR__ . '/includes/class-blueprint-evolver.php';
require_once __DIR__ . '/includes/api/class-neuro-architect-endpoint.php';

class AevovNeuroArchitect {

    private static $instance;

    private $pattern_catalog;
    private $blueprint_evolver;
    private $endpoint;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->pattern_catalog = new Core\NeuralPatternCatalog();
        $this->blueprint_evolver = new Core\BlueprintEvolver();
        $this->endpoint = new API\NeuroArchitectEndpoint();

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    public function add_admin_menu() {
        add_menu_page(
            'Aevov Neuro-Architect',
            'Neuro-Architect',
            'manage_options',
            'aevov-neuro-architect',
            [ $this, 'render_admin_page' ],
            'dashicons-admin-generic',
            20
        );
    }

    public function render_admin_page() {
        require_once __DIR__ . '/templates/admin-page.php';
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'aevov-neuro-architect',
            plugin_dir_url( __FILE__ ) . 'assets/js/neuro-architect.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );
    }

    public function compose_reasoning_process( $blueprint ) {
        $reasoning_engine = new \APS\Reasoning\ReasoningEngine();
        return $reasoning_engine->execute_reasoning_process( $blueprint );
    }
}

function aevov_neuro_architect() {
    return AevovNeuroArchitect::get_instance();
}

aevov_neuro_architect();
