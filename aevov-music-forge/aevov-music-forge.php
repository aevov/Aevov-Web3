<?php
/*
Plugin Name: Aevov Music Forge
Plugin URI:
Description: Sophisticated music generation for the Aevov network.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-music-forge
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovMusicForge {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    private $admin_page_hook_suffix;

    public function init() {
        $this->include_dependencies();
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        add_action( 'aevov_music_forge_cron', [ $this, 'run_worker' ] );
        new \AevovMusicForge\API\MusicEndpoint();
    }

    public function activate() {
        $this->create_music_jobs_table();
        if ( ! wp_next_scheduled( 'aevov_music_forge_cron' ) ) {
            wp_schedule_event( time(), 'hourly', 'aevov_music_forge_cron' );
        }
    }

    private function create_music_jobs_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aevov_music_jobs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            job_id varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            params text NOT NULL,
            status varchar(255) NOT NULL,
            track_url varchar(255) NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY job_id (job_id),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function register_settings() {
        register_setting( 'aevov_music_forge_options', 'aevov_music_forge_options' );
        add_settings_section( 'aevov_music_forge_main', __( 'Main Settings', 'aevov-music-forge' ), null, 'aevov_music_forge' );
        add_settings_field( 'aevov_music_forge_instruments', __( 'Instruments', 'aevov-music-forge' ), [ $this, 'render_instruments_field' ], 'aevov_music_forge', 'aevov_music_forge_main' );
    }

    public function render_instruments_field() {
        $options = get_option( 'aevov_music_forge_options' );
        $instruments = isset( $options['instruments'] ) ? $options['instruments'] : 'piano,guitar,drums';
        echo '<input type="text" name="aevov_music_forge_options[instruments]" value="' . esc_attr( $instruments ) . '">';
    }

    public function add_admin_menu() {
        $this->admin_page_hook_suffix = add_menu_page(
            __( 'Aevov Music Forge', 'aevov-music-forge' ),
            __( 'Aevov Music Forge', 'aevov-music-forge' ),
            'manage_options',
            'aevov-music-forge',
            [ $this, 'render_admin_page' ],
            'dashicons-format-audio',
            83
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== $this->admin_page_hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'aevov-music-composer',
            plugin_dir_url( __FILE__ ) . 'assets/js/music-composer.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-music-weaver.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-job-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-music-worker.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-music-endpoint.php';
    }

    public function run_worker() {
        $worker = new \AevovMusicForge\MusicWorker();
        $worker->run();
    }
}

new AevovMusicForge();
