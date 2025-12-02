<?php
namespace AevovLanguageEngine\API;

use Aevov\Security\SecurityHelper;

use WP_REST_Controller;
use WP_REST_Server;
use AevovLanguageEngine\Core\LanguageWeaver;

class LanguageEndpoint extends WP_REST_Controller {

    protected $namespace = 'aevov-language-engine/v1';
    protected $rest_base = 'generate';
    private $weaver;

    public function __construct( LanguageWeaver $weaver ) {
        $this->weaver = $weaver;
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'generate_text' ],
                    'permission_callback' => [ $this, 'check_permissions' ],
                    'args'                => [
                        'prompt' => [
                            'required' => true,
                            'type'     => 'string',
                            'description' => 'The text prompt for generation.',
                        ],
                        'params' => [
                            'type'        => 'object',
                            'description' => 'Additional generation parameters.',
                        ],
                    ],
                ],
            ]
        );
    }

    public function generate_text( $request ) {
        $prompt = $request->get_param( 'prompt' );
        $params = $request->get_param( 'params' ) ?? [];
        $response = $this->weaver->generate( $prompt, $params );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return new \WP_REST_Response( [ 'text' => $response ] );
    }

    public function check_permissions( $request ) {
        // For now, only administrators can generate text.
        // In a real-world scenario, you might have more granular permissions.
        return current_user_can( 'manage_options' );
    }
}
