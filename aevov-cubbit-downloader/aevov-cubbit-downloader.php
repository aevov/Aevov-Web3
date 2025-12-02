<?php
/*
Plugin Name: Aevov Cubbit Downloader
Plugin URI:
Description: Integrates the Cubbit Authenticated Downloader with the Aevov Pattern Sync Protocol.
Version: 1.1.0
Author: Aevov
Author URI:
License: GPL2
Text Domain: acd
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovCubbitDownloader {

    public function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ], 20 );
    }

    public function init() {
        // Check if the required plugins are active.
        if ( ! class_exists( 'APS_Plugin' ) || ! class_exists( 'CubbitAuthenticatedDownloader' ) ) {
            add_action( 'admin_notices', [ $this, 'missing_plugins_notice' ] );
            return;
        }

        // Add the "Download" button to the pattern list.
        add_filter( 'aps_pattern_actions', [ $this, 'add_download_button' ], 10, 2 );

        // Add the "Download Selected" button to the top of the pattern list.
        add_action( 'aps_before_pattern_list', [ $this, 'add_download_selected_button' ] );

        // Enqueue the necessary scripts.
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Handle the download request.
        add_action( 'wp_ajax_acd_download_pattern', [ $this, 'handle_download_request' ] );

        // Handle the cancel download request.
        add_action( 'wp_ajax_acd_cancel_download', [ $this, 'handle_cancel_download' ] );

        // Add a cron job to clean up temporary files.
        add_action( 'acd_cleanup_temp_file', [ $this, 'cleanup_temp_file' ] );
    }

    public function missing_plugins_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e( 'Aevov Cubbit Downloader requires the Aevov Pattern Sync Protocol and Cubbit Authenticated Downloader plugins to be active.', 'acd' ); ?></p>
        </div>
        <?php
    }

    public function add_download_button( $actions, $pattern ) {
        $actions['download'] = '<input type="checkbox" class="acd-pattern-checkbox" value="' . $pattern->id . '">';
        return $actions;
    }

    public function add_download_selected_button() {
        ?>
        <div class="alignleft actions bulkactions">
            <button id="acd-download-selected" class="button" disabled><?php _e( 'Download Selected', 'acd' ); ?></button>
        </div>
        <div id="acd-progress-container" style="display: none;">
            <div id="acd-progress-bar-container">
                <div id="acd-progress-bar" style="width: 0%; height: 20px; background-color: #0073aa;"></div>
            </div>
            <div id="acd-progress-text"></div>
            <button id="acd-cancel-download" class="button"><?php _e( 'Cancel', 'acd' ); ?></button>
        </div>
        <?php
    }

    public function enqueue_scripts( $hook ) {
        if ( 'toplevel_page_aps-dashboard' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'acd-admin',
            plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
            [ 'jquery' ],
            '1.1.0',
            true
        );

        wp_localize_script(
            'acd-admin',
            'acdAdmin',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'acd-download-nonce' ),
            ]
        );
    }

    public function handle_download_request() {
        check_ajax_referer( 'acd-download-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
            return;
        }

        $pattern_ids = isset( $_POST['pattern_ids'] ) ? array_map( 'intval', wp_unslash( $_POST['pattern_ids'] ) ) : [];

        if ( empty( $pattern_ids ) ) {
            wp_send_json_error( [ 'message' => 'No patterns selected' ] );
            return;
        }

        $temp_files = [];
        $temp_keys = [];

        foreach ( $pattern_ids as $pattern_id ) {
            // Get the pattern data.
            $pattern = \APS\DB\APS_Pattern_DB::get_instance()->get( $pattern_id );

            if ( ! $pattern ) {
                continue;
            }

            // Create a temporary file with the pattern data.
            $temp_dir = get_temp_dir();
            $temp_file = wp_tempnam( 'pattern', $temp_dir );
            file_put_contents( $temp_file, $pattern->pattern_data );

            // Upload the temporary file to Cubbit.
            $cubbit_manager = new CubbitDirectoryManager();
            $temp_key = 'temp/' . uniqid() . '.json';
            $upload_result = $cubbit_manager->upload_file( $temp_file, $temp_key, 'application/json', 'private' );

            if ( ! $upload_result ) {
                // Clean up any files that were already created.
                foreach ( $temp_files as $file ) {
                    unlink( $file );
                }
                wp_send_json_error( [ 'message' => 'Failed to upload pattern to Cubbit.' ] );
                return;
            }

            $temp_files[] = $temp_file;
            $temp_keys[] = $temp_key;
        }

        // Initiate the download using the Cubbit Authenticated Downloader.
        $_POST['items'] = $temp_keys;
        $cubbit_downloader = new CubbitAuthenticatedDownloader();
        $cubbit_downloader->ajax_auth_download();

        // Schedule the temporary files for deletion.
        foreach ( $temp_keys as $key ) {
            wp_schedule_single_event( time() + HOUR_IN_SECONDS, 'acd_cleanup_temp_file', [ $key ] );
        }

        // Clean up the local temporary files.
        foreach ( $temp_files as $file ) {
            unlink( $file );
        }
    }

    public function cleanup_temp_file( $key ) {
        $cubbit_manager = new CubbitDirectoryManager();
        $cubbit_manager->delete_file( new WP_REST_Request( 'POST', '', [ 'file_path' => $key ] ) );
    }

    public function handle_cancel_download() {
        check_ajax_referer( 'acd-download-nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions' ] );
            return;
        }

        $download_id = isset( $_POST['download_id'] ) ? sanitize_text_field( wp_unslash( $_POST['download_id'] ) ) : '';

        if ( empty( $download_id ) ) {
            wp_send_json_error( [ 'message' => 'Invalid download ID' ] );
            return;
        }

        $download_job = get_transient('cubbit_download_job_' . $download_id);

        if ( ! empty( $download_job ) ) {
            // Schedule the temporary files for deletion.
            foreach ( $download_job['files'] as $file ) {
                wp_schedule_single_event( time(), 'acd_cleanup_temp_file', [ $file['key'] ] );
            }

            // Delete the download job transient.
            delete_transient('cubbit_download_job_' . $download_id);
        }

        wp_send_json_success();
    }
}

new AevovCubbitDownloader();
