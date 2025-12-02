<?php

namespace APSTools\API;

use Aevov\Security\SecurityHelper;

use AevovCognitiveEngine\CognitiveConductor;

class ChatEndpoint {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'aps-tools/v1', '/chat', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_chat_message' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    public function handle_chat_message( $request ) {
        $message = $request->get_param( 'message' );

        if ( empty( $message ) ) {
            return new \WP_Error( 'missing_message', 'Message is empty.', [ 'status' => 400 ] );
        }

        // Command-based routing
        if (strpos($message, '/') === 0) {
            $parts = explode(' ', $message, 2);
            $command = $parts[0];
            $args = $parts[1] ?? '';

            switch ($command) {
                case '/compose':
                    return $this->handle_compose_command($args);
                case '/reason':
                    return $this->handle_reason_command($args);
                case '/analogies':
                    return $this->handle_analogies_command($args);
                case '/status':
                    return $this->handle_status_command();
                default:
                    return new \WP_Error( 'unknown_command', 'Unknown command.', [ 'status' => 400 ] );
            }
        } else {
            // Default to reasoning
            return $this->handle_reason_command($message);
        }
    }

    private function handle_compose_command($args) {
        $blueprint = json_decode($args, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error( 'invalid_json', 'Invalid blueprint JSON.', [ 'status' => 400 ] );
        }

        $comparator = new \AevovPatternSyncProtocol\Comparison\APS_Comparator();
        $model = $comparator->compose_model($blueprint);

        return new \WP_REST_Response( [ 'reply' => 'Model composed successfully: ' . $model->name ] );
    }

    private function handle_reason_command($args) {
        if ( ! class_exists( 'AevovCognitiveEngine\CognitiveConductor' ) ) {
            return new \WP_Error( 'cognitive_engine_not_found', 'The Aevov Cognitive Engine is not active.', [ 'status' => 500 ] );
        }

        $conductor = new \AevovCognitiveEngine\CognitiveConductor();
        $solution = $conductor->solve_problem( $args );

        return new \WP_REST_Response( [ 'reply' => $solution ] );
    }

    private function handle_analogies_command($args) {
        $pattern = json_decode($args, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error( 'invalid_json', 'Invalid pattern JSON.', [ 'status' => 400 ] );
        }

        $comparator = new \AevovPatternSyncProtocol\Comparison\APS_Comparator();
        $analogies = $comparator->find_analogous_patterns($pattern);

        return new \WP_REST_Response( [ 'reply' => 'Found ' . count($analogies) . ' analogies.', 'data' => $analogies ] );
    }

    private function handle_status_command() {
        if ( ! class_exists( 'APSTools\APSTools' ) ) {
            return new \WP_Error( 'aps_tools_not_found', 'The APS Tools plugin is not active.', [ 'status' => 500 ] );
        }
        $aps_tools = \APSTools\APSTools::instance();
        $status = $aps_tools->get_system_status();

        return new \WP_REST_Response( [ 'reply' => 'System status:', 'data' => $status->get_data() ] );
    }
}
