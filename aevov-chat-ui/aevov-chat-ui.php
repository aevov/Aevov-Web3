<?php
/*
Plugin Name: Aevov Chat UI
Description: A reusable chat interface for interacting with the Aevov network.
Version: 1.0.0
Author: Aevov
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AevovChatUI {

    public static function render() {
        // This function will render the chat interface.
        // It will be called from other plugins.
        include plugin_dir_path( __FILE__ ) . 'templates/chat-interface.php';
    }

    public static function enqueue_scripts() {
        wp_enqueue_style(
            'aevov-chat-ui',
            plugin_dir_url( __FILE__ ) . 'assets/css/chat-ui.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'aevov-chat-ui',
            plugin_dir_url( __FILE__ ) . 'assets/js/chat-ui.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );

        wp_localize_script(
            'aevov-chat-ui',
            'aevovChatUISettings',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'aevov-chat-ui-nonce' ),
            ]
        );
    }
}
