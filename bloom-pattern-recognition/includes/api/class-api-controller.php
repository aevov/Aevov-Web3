<?php
/**
 * Handles REST API endpoints and routing
 */
namespace BLOOM\API;

use BLOOM\Models\PatternModel;
use BLOOM\Monitoring\SystemMonitor;
use BLOOM\Processing\TensorProcessor;
use BLOOM\Monitoring\DataValidator;
use WP_Error;
use WP_REST_Response;

class ApiController {
    private $authenticator;
    private $rate_limiter;
    private $validator;
    private $pattern_model;
    private $system_monitor;
    private $tensor_processor;
    private $error_handler;

    public function __construct() {
        $this->authenticator = new ApiAuthenticator();
        $this->rate_limiter = new ApiRateLimiter();
        $this->validator = new DataValidator();
        $this->pattern_model = new PatternModel();
        $this->system_monitor = new SystemMonitor();
        $this->tensor_processor = new TensorProcessor();
        $this->error_handler = BLOOM()->get_error_handler();

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('bloom/v1', '/patterns', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_patterns'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_patterns_args()
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'create_pattern'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_pattern_creation_args()
            ]
        ]);

        register_rest_route('bloom/v1', '/system/status', [
            'methods' => 'GET',
            'callback' => [$this, 'get_system_status'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route('bloom/v1', '/patterns/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_pattern_details'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'id' => [
                    'validate_callback' => function($param, $request, $key) {
                        return is_numeric($param);
                    }
                ]
            ]
        ]);

        register_rest_route('bloom/v1', '/process', [
            'methods' => 'POST',
            'callback' => [$this, 'process_tensor'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => $this->get_process_args()
        ]);

        register_rest_route('bloom/v1', '/upload/url', [
            'methods' => 'POST',
            'callback' => [$this, 'process_url_upload'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'url' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'uri',
                    'description' => 'URL of the tensor data.'
                ]
            ]
        ]);

        register_rest_route('bloom/v1', '/upload/file', [
            'methods' => 'POST',
            'callback' => [$this, 'process_file_upload'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'file' => [
                    'required' => true,
                    'type' => 'object', // Represents $_FILES entry
                    'description' => 'Uploaded tensor file.'
                ]
            ]
        ]);

        register_rest_route('bloom/v1', '/upload/local-path', [
            'methods' => 'POST',
            'callback' => [$this, 'process_local_path_upload'],
            'permission_callback' => [$this, 'check_permission'],
            'args' => [
                'path' => [
                    'required' => true,
                    'type' => 'string',
                    'description' => 'Local path to the tensor file or directory.'
                ]
            ]
        ]);
    }

    public function get_patterns($request) {
        $params = $request->get_params();
        $patterns = $this->pattern_model->get_patterns($params);
        return new WP_REST_Response($patterns);
    }

    public function get_pattern_details($request) {
        $pattern_id = $request['id'];
        $pattern = $this->pattern_model->get($pattern_id);

        if (empty($pattern)) {
            return new WP_REST_Response(['message' => 'Pattern not found'], 404);
        }

        // Decode JSON fields
        if (isset($pattern['features'])) {
            $pattern['features'] = json_decode($pattern['features'], true);
        }
        if (isset($pattern['metadata'])) {
            $pattern['metadata'] = json_decode($pattern['metadata'], true);
        }

        return new WP_REST_Response($pattern);
    }

    public function create_pattern($request) {
        $params = $request->get_params();
        $pattern = $this->pattern_model->create_pattern($params);
        return new WP_REST_Response($pattern, 201);
    }

    public function get_system_status($request) {
        $status = $this->system_monitor->get_system_health();
        return new WP_REST_Response($status);
    }

    public function process_tensor($request) {
        $params = $request->get_params();
        $job_id = $this->tensor_processor->process_tensor($params['tensor_data']);
        return new WP_REST_Response(['job_id' => $job_id], 202);
    }

    public function process_url_upload($request) {
        $url = $request['url'];

        // Validate the URL to prevent SSRF
        if (!wp_http_validate_url($url)) {
            $this->error_handler->log_error('Invalid URL provided for upload', ['url' => $url]);
            return new WP_Error('invalid_url', 'The provided URL is not valid.', ['status' => 400]);
        }

        try {
            $response = wp_remote_get($url, ['timeout' => 30]);
            if (is_wp_error($response)) {
                $this->error_handler->log_error('URL fetch failed', ['url' => $url, 'error' => $response->get_error_message()]);
                return new WP_Error('url_fetch_failed', $response->get_error_message(), ['status' => 500]);
            }
            $body = wp_remote_retrieve_body($response);
            $tensor_data = json_decode($body, true); // Assuming JSON content

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error_handler->log_error('Invalid JSON from URL', ['url' => $url, 'error' => json_last_error_msg()]);
                return new WP_Error('invalid_json', 'Invalid JSON data from URL.', ['status' => 400]);
            }

            $job_id = $this->tensor_processor->process_tensor($tensor_data);
            return new WP_REST_Response(['message' => 'URL content processed.', 'job_id' => $job_id], 200);
        } catch (\Exception $e) {
            $this->error_handler->log_error($e, ['url' => $url]);
            return new WP_Error('url_processing_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function process_file_upload($request) {
        $files = $request->get_file_params();

        if (empty($files['file'])) {
            $this->error_handler->log_error('No file uploaded');
            return new WP_Error('no_file_uploaded', 'No file uploaded.', ['status' => 400]);
        }

        $file = $files['file'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->error_handler->log_error('File upload error', ['error_code' => $file['error']]);
            return new WP_Error('file_upload_error', 'File upload error: ' . $file['error'], ['status' => 500]);
        }

        // Validate file size (e.g., 10MB limit)
        $max_size = 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            $this->error_handler->log_error('File too large', ['size' => $file['size'], 'max_size' => $max_size]);
            return new WP_Error('file_too_large', 'File is too large. Maximum size is 10MB.', ['status' => 400]);
        }

        // Validate file type
        $allowed_types = ['application/json', 'application/octet-stream'];
        $allowed_extensions = ['json', 'safetensors', 'chunk'];
        $file_info = wp_check_filetype($file['name']);
        if (!in_array($file['type'], $allowed_types) || !in_array($file_info['ext'], $allowed_extensions)) {
            $this->error_handler->log_error('Invalid file type', ['type' => $file['type'], 'extension' => $file_info['ext']]);
            return new WP_Error('invalid_file_type', 'Invalid file type. Allowed types: .json, .safetensors, .chunk', ['status' => 400]);
        }

        $file_content = file_get_contents($file['tmp_name']);
        $tensor_data = json_decode($file_content, true); // Assuming JSON content

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error_handler->log_error('Invalid JSON from file', ['file' => $file['name'], 'error' => json_last_error_msg()]);
            return new WP_Error('invalid_json', 'Invalid JSON data from uploaded file.', ['status' => 400]);
        }

        try {
            $job_id = $this->tensor_processor->process_tensor($tensor_data);
            return new WP_REST_Response(['message' => 'File processed.', 'job_id' => $job_id], 200);
        } catch (\Exception $e) {
            $this->error_handler->log_error($e, ['file' => $file['name']]);
            return new WP_Error('file_processing_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    public function process_local_path_upload($request) {
        $path = $request['path'];

        // Sanitize the filename to prevent directory traversal
        $filename = basename($path);
        $safe_path = BLOOM_LOCAL_TENSOR_PATH . '/' . $filename;

        if (!file_exists($safe_path)) {
            $this->error_handler->log_error('File not found at local path', ['path' => $path, 'safe_path' => $safe_path]);
            return new WP_Error('file_not_found', 'File not found at specified path.', ['status' => 404]);
        }
        if (!is_readable($safe_path)) {
            $this->error_handler->log_error('File not readable at local path', ['path' => $path, 'safe_path' => $safe_path]);
            return new WP_Error('file_not_readable', 'File is not readable.', ['status' => 403]);
        }

        $file_content = file_get_contents($safe_path);
        $tensor_data = json_decode($file_content, true); // Assuming JSON content

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error_handler->log_error('Invalid JSON from local path', ['path' => $safe_path, 'error' => json_last_error_msg()]);
            return new WP_Error('invalid_json', 'Invalid JSON data from local path.', ['status' => 400]);
        }

        try {
            $job_id = $this->tensor_processor->process_tensor($tensor_data);
            return new WP_REST_Response(['message' => 'Local path processed.', 'job_id' => $job_id], 200);
        } catch (\Exception $e) {
            $this->error_handler->log_error($e, ['path' => $safe_path]);
            return new WP_Error('local_path_processing_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    private function check_permission($request) {
        // Check if user has the required capability
        if (!current_user_can('manage_network_options')) {
            return new WP_Error('rest_forbidden', esc_html__('You do not have permission to access this endpoint.', 'bloom-pattern-system'), ['status' => 401]);
        }

        // Verify the nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_nonce_invalid', esc_html__('Nonce is invalid.', 'bloom-pattern-system'), ['status' => 403]);
        }

        // You can add back the authenticator and rate limiter if they are needed for other purposes
        // if (!$this->authenticator->verify_request($request)) {
        //     return false;
        // }

        // if (!$this->rate_limiter->check_limit($request)) {
        //     return false;
        // }

        return true;
    }

    private function get_patterns_args() {
        return [
            'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
            'per_page' => ['default' => 20, 'sanitize_callback' => 'absint'],
            'type' => ['type' => 'string', 'enum' => ['sequential', 'structural', 'statistical']],
            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1]
        ];
    }

    private function get_pattern_creation_args() {
        return [
            'type' => ['required' => true, 'type' => 'string'],
            'features' => ['required' => true, 'type' => 'object'],
            'tensor_sku' => ['required' => true, 'type' => 'string']
        ];
    }

    private function get_process_args() {
        return [
            'tensor_data' => ['required' => true, 'type' => 'object']
        ];
    }
}