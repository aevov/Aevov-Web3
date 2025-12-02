<?php
/**
 * Integration with Typebot for pattern processing instructions
 */
class BLOOM_Typebot_Integration {
    private $webhook_secret;
    private $api_endpoint;
    
    public function __construct() {
        $this->webhook_secret = get_option('bloom_typebot_secret');
        $this->api_endpoint = get_option('bloom_typebot_endpoint');
        
        add_action('rest_api_init', [$this, 'register_endpoints']);
    }

    public function register_endpoints() {
        register_rest_route('bloom/v1', '/typebot/instruction', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_instruction'],
            'permission_callback' => [$this, 'verify_webhook']
        ]);

        register_rest_route('bloom/v1', '/typebot/result', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_result'],
            'permission_callback' => [$this, 'verify_webhook']
        ]);
    }

    public function handle_instruction($request) {
        try {
            $instruction = $request->get_json_params();
            $job_id = wp_generate_uuid4();
            
            $this->queue_instruction_job($job_id, $instruction);
            
            return new WP_REST_Response([
                'job_id' => $job_id,
                'status' => 'accepted'
            ], 202);
        } catch (Exception $e) {
            return new WP_Error(
                'instruction_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    private function verify_webhook($request) {
        $signature = $request->get_header('X-Typebot-Signature');
        if (!$signature) {
            return false;
        }

        $payload = $request->get_body();
        $expected = hash_hmac('sha256', $payload, $this->webhook_secret);
        
        return hash_equals($expected, $signature);
    }

    private function queue_instruction_job($job_id, $instruction) {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'bloom_instructions',
            [
                'job_id' => $job_id,
                'instruction_type' => $instruction['type'],
                'instruction_data' => json_encode($instruction['data']),
                'callback_url' => $instruction['callback_url'],
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ]
        );
    }
}