<?php
/*
Plugin Name: Aevov Stream
Plugin URI:
Description: Real-time streaming of Aevov patterns.
Version: 1.0.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: aevov-stream
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovStream {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        $this->include_dependencies();
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_shortcode( 'aevov_stream_player', [ $this, 'render_stream_player' ] );
        register_activation_hook( __FILE__, [ $this, 'activate' ] );
        new \AevovStream\API\StreamEndpoint();
    }

    public function register_settings() {
        register_setting( 'aevov_stream_options', 'aevov_stream_options' );
        add_settings_section( 'aevov_stream_main', __( 'Main Settings', 'aevov-stream' ), null, 'aevov_stream' );
        add_settings_field( 'aevov_stream_bitrates', __( 'Bitrates', 'aevov-stream' ), [ $this, 'render_bitrates_field' ], 'aevov_stream', 'aevov_stream_main' );
    }

    public function render_bitrates_field() {
        $options = get_option( 'aevov_stream_options' );
        $bitrates = isset( $options['bitrates'] ) ? $options['bitrates'] : '250000,750000';
        echo '<input type="text" name="aevov_stream_options[bitrates]" value="' . esc_attr( $bitrates ) . '">';
    }

    public function activate() {
        $this->create_sessions_table();
    }

    private function create_sessions_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'aevov_stream_sessions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            params text NOT NULL,
            playlist text NOT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'Aevov Stream', 'aevov-stream' ),
            __( 'Aevov Stream', 'aevov-stream' ),
            'manage_options',
            'aevov-stream',
            [ $this, 'render_admin_page' ],
            'dashicons-video-alt3',
            81
        );
    }

    public function render_admin_page() {
        include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
    }

    public function render_stream_player() {
        if ( class_exists( 'LiteSpeed_Cache_API' ) && LiteSpeed_Cache_API::is_esi_enabled() ) {
            $esi = new \AevovStream\ESI();
            return $esi->render_esi_block( 'aevov_stream_player', [] );
        } else {
            ob_start();
            include plugin_dir_path( __FILE__ ) . 'templates/stream-player.php';
            return ob_get_clean();
        }
    }

    public function enqueue_scripts() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'aevov_stream_player' ) ) {
            wp_enqueue_style( 'video-js', 'https://vjs.zencdn.net/7.17.0/video-js.css' );
            wp_enqueue_script( 'video-js', 'https://vjs.zencdn.net/7.17.0/video.min.js', [], '7.17.0', true );
            wp_enqueue_script(
                'aevov-stream-player',
                plugin_dir_url( __FILE__ ) . 'assets/js/stream-player.js',
                [ 'jquery', 'video-js' ],
                '1.0.0',
                true
            );
        }
    }

    private function include_dependencies() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-playlist-generator.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-session-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-stream-weaver.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-drm-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-watermark-manager.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-esi.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/api/class-stream-endpoint.php';
    }
}

new AevovStream();
