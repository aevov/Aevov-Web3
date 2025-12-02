<?php
/**
 * BLOOM integration API endpoints
 * 
 * @package APS
 * @subpackage API\Endpoints
 */

namespace APS\API\Endpoints;

use APS\Integration\BloomIntegration;
use APS\Integration\Auth\BloomAuth;
use APS\Integration\Processors\TensorProcessor;

class BloomEndpoint extends BaseEndpoint {
    protected $base = 'bloom';
    private $bloom_integration;
    private $bloom_auth;
    private $tensor_processor;

    public function __construct($namespace) {
        parent::__construct($namespace);
        
        if (!class_exists('BLOOM_Core')) {
            return;
        }

        $this->bloom_integration = BloomIntegration::get_instance();
        $this->bloom_auth = new BloomAuth();
        $this->tensor_processor = new TensorProcessor();
    }

    public function register_routes() {
        if (!class_exists('BLOOM_Core')) {
            return;
        }

        // Integration status
        register_rest_route($this->namespace, '/' . $this->base . '/status', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_integration_status'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
        ]);

        // Pattern synchronization
        register_rest_route($this->namespace, '/' . $this->base . '/sync', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'sync_patterns'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_sync_args()
            ]
        ]);

        // Pattern analysis endpoint
        register_rest_route($this->namespace, '/' . $this->base . '/analyze', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'analyze_pattern'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_analysis_args()
            ]
        ]);

        // Tensor processing endpoint
        register_rest_route($this->namespace, '/' . $this->base . '/process-tensor', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'process_tensor'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_tensor_args()
            ]
        ]);

        // Integration settings
        register_rest_route($this->namespace, '/' . $this->base . '/settings', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'check_read_permission']
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => $this->get_settings_args()
            ]
        ]);

        // Sync history
        register_rest_route($this->namespace, '/' . $this->base . '/sync-history', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_sync_history'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_history_args()
            ]
        ]);

        // Integration metrics
        register_rest_route($this->namespace, '/' . $this->base . '/metrics', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_metrics'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_metrics_args()
            ]
        ]);

        // Test connection
        register_rest_route($this->namespace, '/' . $this->base . '/test-connection', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'test_connection'],
                'permission_callback' => [$this, 'check_write_permission']
            ]
        ]);
    }

    public function get_integration_status($request) {
        try {
            $status = $this->bloom_integration->get_integration_status();
            
            return rest_ensure_response([
                'success' => true,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'status_fetch_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function sync_patterns($request) {
        try {
            $sync_options = $request['options'] ?? [];
            $sync_result = $this->bloom_integration->sync_with_bloom($sync_options);
            
            return rest_ensure_response([
                'success' => true,
                'sync_result' => $sync_result
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'sync_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function analyze_pattern($request) {
        try {
            $analysis = $this->bloom_integration->analyze_pattern(
                $request->get_param('pattern'),
                $request->get_param('options') ?? []
            );
            
            return rest_ensure_response([
                'success' => true,
                'analysis' => $analysis
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'analysis_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function process_tensor($request) {
        try {
            $processed = $this->tensor_processor->process_tensor(
                $request->get_param('tensor_data'),
                $request->get_param('options') ?? []
            );
            
            return rest_ensure_response([
                'success' => true,
                'processed' => $processed
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'tensor_processing_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_settings($request) {
        try {
            $settings = $this->bloom_integration->get_settings();
            
            return rest_ensure_response([
                'success' => true,
                'settings' => $settings
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'settings_fetch_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function update_settings($request) {
        try {
            $updated = $this->bloom_integration->update_settings(
                $request->get_params()
            );
            
            return rest_ensure_response([
                'success' => true,
                'updated' => $updated
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'settings_update_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_sync_history($request) {
        try {
            $history = $this->bloom_integration->get_sync_history(
                $request->get_param('limit'),
                $request->get_param('offset')
            );
            
            return rest_ensure_response([
                'success' => true,
                'history' => $history
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'history_fetch_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_metrics($request) {
        try {
            $metrics = $this->bloom_integration->get_integration_metrics(
                $request->get_param('type'),
                $request->get_param('period')
            );
            
            return rest_ensure_response([
                'success' => true,
                'metrics' => $metrics
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'metrics_fetch_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function test_connection($request) {
        try {
            $test_result = $this->bloom_integration->test_connection();
            
            return rest_ensure_response([
                'success' => true,
                'connection' => $test_result
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'connection_test_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    protected function get_sync_args() {
        return [
            'options' => [
                'type' => 'object',
                'properties' => [
                    'full_sync' => ['type' => 'boolean'],
                    'sync_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'batch_size' => ['type' => 'integer']
                ]
            ]
        ];
    }

    protected function get_analysis_args() {
        return [
            'pattern' => [
                'required' => true,
                'type' => 'object'
            ],
            'options' => [
                'type' => 'object'
            ]
        ];
    }

    protected function get_tensor_args() {
        return [
            'tensor_data' => [
                'required' => true,
                'type' => 'object',
                'properties' => [
                    'data' => ['required' => true, 'type' => 'array'],
                    'shape' => ['required' => true, 'type' => 'array'],
                    'dtype' => ['required' => true, 'type' => 'string']
                ]
            ],
            'options' => [
                'type' => 'object'
            ]
        ];
    }

    protected function get_settings_args() {
        return [
            'sync_interval' => ['type' => 'integer'],
            'batch_size' => ['type' => 'integer'],
            'confidence_threshold' => ['type' => 'number'],
            'auto_sync' => ['type' => 'boolean']
        ];
    }

    protected function get_history_args() {
        return [
            'limit' => [
                'type' => 'integer',
                'default' => 100,
                'minimum' => 1,
                'maximum' => 1000
            ],
            'offset' => [
                'type' => 'integer',
                'default' => 0,
                'minimum' => 0
            ]
        ];
    }

    protected function get_metrics_args() {
        return [
            'type' => [
                'type' => 'string',
                'enum' => ['sync', 'analysis', 'processing', 'all']
            ],
            'period' => [
                'type' => 'string',
                'enum' => ['hour', 'day', 'week', 'month'],
                'default' => 'day'
            ]
        ];
    }
}