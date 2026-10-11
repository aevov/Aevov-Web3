<?php
/*
Plugin Name: Aevov Senton Field
Plugin URI:
Description: An experiential senton lattice for the Aevov network — a phase-coupled field you can perceive into, step, and share across the mesh. Reproduces the observable behaviour of the paradigm without the organism. See BOUNDARY.md.
Version: 1.0.0
Author: Aevov
Author URI: https://aevov.org
License: SSAL-1.0 - Aevov Sovereign Source-Available License v1.0 (not GPL; see LICENSE)
License-URI: https://aevov.org/licenses/ssal-1.0
Text Domain: aevov-senton-field

This plugin is licensed under the Aevov Sovereign Source-Available License (SSAL) v1.0,
not under the GPL that covers the rest of the Aevov-Web3 tree. See LICENSE in this
directory for the full terms, and for the adherence duties (SSAL 2 / SSAL 3) that bind
anyone who reads, runs or copies these files. BOUNDARY.md states what this plugin is
deliberately not.
@package AevovSentonField
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AEVOV_SENTON_FIELD_VERSION', '1.0.0' );
define( 'AEVOV_SENTON_FIELD_PATH', plugin_dir_path( __FILE__ ) );
define( 'AEVOV_SENTON_FIELD_URL', plugin_dir_url( __FILE__ ) );

class AevovSentonField {

    /**
     * @var string Admin page hook suffix.
     */
    private $admin_page_hook_suffix;

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
    }

    public function init() {
        $this->include_dependencies();

        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'rest_api_init', [ 'AevovSentonField\\Senton_Rest_Api', 'register_routes' ] );

        add_action( 'aevov_senton_field_tick', [ $this, 'cron_tick' ] );
        add_filter( 'cron_schedules', [ $this, 'register_schedule' ] );

        AevovSentonField\Senton_Workflow::register_hooks();
        AevovSentonField\Senton_Aps::register_hooks();

        if ( ! wp_next_scheduled( 'aevov_senton_field_tick' ) ) {
            wp_schedule_event( time() + 30, 'aevov_senton_minute', 'aevov_senton_field_tick' );
        }
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-senton-tuning.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-senton-lattice.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-senton-bridges.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-senton-aps.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-senton-organism.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-senton-workflow.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-senton-rest-api.php';
    }

    /**
     * The field runs on its own clock so it is alive between requests.
     *
     * @param array $schedules Existing cron schedules.
     * @return array
     */
    public function register_schedule( $schedules ) {
        if ( ! isset( $schedules['aevov_senton_minute'] ) ) {
            $schedules['aevov_senton_minute'] = [
                'interval' => 60,
                'display'  => __( 'Every minute (Senton Field)', 'aevov-senton-field' ),
            ];
        }
        return $schedules;
    }

    public function cron_tick() {
        AevovSentonField\Senton_Organism::instance()->step( 4 );
    }

    public function activate() {
        if ( false === get_option( 'aevov_senton_field_state' ) ) {
            AevovSentonField\Senton_Organism::instance()->reset();
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook( 'aevov_senton_field_tick' );
    }

    public function add_admin_menu() {
        $this->admin_page_hook_suffix = add_menu_page(
            __( 'Aevov Senton Field', 'aevov-senton-field' ),
            __( 'Senton Field', 'aevov-senton-field' ),
            'manage_options',
            'aevov-senton-field',
            [ $this, 'render_admin_page' ],
            'dashicons-performance',
            92
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'aevov-senton-field',
            AEVOV_SENTON_FIELD_URL . 'assets/css/senton-field.css',
            [],
            AEVOV_SENTON_FIELD_VERSION
        );

        wp_enqueue_script(
            'aevov-senton-field',
            AEVOV_SENTON_FIELD_URL . 'assets/js/senton-field.js',
            [],
            AEVOV_SENTON_FIELD_VERSION,
            true
        );

        wp_localize_script(
            'aevov-senton-field',
            'AevovSentonField',
            [
                'root'   => esc_url_raw( rest_url( 'aevov-senton/v1' ) ),
                'nonce'  => wp_create_nonce( 'wp_rest' ),
                'public' => (bool) apply_filters( 'aevov_senton_field_public_perceive', false ),
            ]
        );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }
}

new AevovSentonField();
