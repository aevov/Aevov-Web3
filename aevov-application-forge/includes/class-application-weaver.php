<?php

namespace AevovApplicationForge;

class ApplicationWeaver {

    public function __construct() {
        // We will add our hooks and filters here.
    }

    public function get_genesis_state( $params ) {
        $app_name = isset( $params['app_name'] ) ? sanitize_text_field( $params['app_name'] ) : 'Aevov App';
        $version = isset( $params['version'] ) ? sanitize_text_field( $params['version'] ) : '1.0.0';

        // Simulate dynamic generation of UI components and logic rules
        $components = [
            [ 'type' => 'header', 'content' => 'Welcome to ' . $app_name ],
            [ 'type' => 'paragraph', 'content' => 'Version: ' . $version ],
        ];

        $rules = [];

        // Add a button and a rule if 'add_button' param is true
        if ( isset( $params['add_button'] ) && $params['add_button'] ) {
            $components[] = [ 'type' => 'button', 'label' => 'Dynamic Button' ];
            $rules[] = [ 'event' => 'dynamic_button_click', 'action' => 'display_message', 'message' => 'Dynamic button clicked in ' . $app_name . '!' ];
        }

        // Add a text input if 'add_input' param is true
        if ( isset( $params['add_input'] ) && $params['add_input'] ) {
            $components[] = [ 'type' => 'input', 'placeholder' => 'Enter text...' ];
            $rules[] = [ 'event' => 'input_change', 'action' => 'log_input_value' ];
        }

        return [
            'app_name' => $app_name,
            'version' => $version,
            'ui' => [
                'components' => $components,
            ],
            'logic' => [
                'rules' => $rules,
            ],
        ];
    }
}
