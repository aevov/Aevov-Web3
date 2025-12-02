<?php
namespace APS\API\Endpoints;

class TypebotEndpoint extends BaseEndpoint {
    protected $base = 'typebot';

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base . '/query', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_query'],
                'permission_callback' => '__return_true', // Public endpoint
                'args' => [
                    'input' => [
                        'required' => true,
                        'type' => 'string'
                    ],
                    'site_id' => [
                        'required' => false,
                        'type' => 'integer'
                    ]
                ]
            ]
        ]);
    }

    public function handle_query($request) {
        $input = $request->get_param('input');
        $site_id = $request->get_param('site_id');

        // If site_id is provided, switch to that site
        if ($site_id && is_multisite()) {
            switch_to_blog($site_id);
        }

        try {
            $generator = new \APS\Pattern\PatternGenerator();
            $response = $generator->process_input($input);

            if ($site_id && is_multisite()) {
                restore_current_blog();
            }

            return rest_ensure_response([
                'success' => true,
                'response' => $response
            ]);

        } catch (\Exception $e) {
            if ($site_id && is_multisite()) {
                restore_current_blog();
            }

            return new \WP_Error(
                'processing_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
}