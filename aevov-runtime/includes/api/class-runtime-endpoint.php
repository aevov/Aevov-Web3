<?php
/**
 * Runtime Endpoint - REST API for AevRT
 *
 * Provides REST API endpoints for:
 * - Task submission and execution
 * - Schedule management
 * - Execution status monitoring
 * - Runtime statistics and metrics
 * - Configuration management
 *
 * @package AevovRuntime
 * @since 1.0.0
 */

namespace AevovRuntime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class RuntimeEndpoint
 */
class RuntimeEndpoint {

    /**
     * API namespace
     */
    const NAMESPACE = 'aevov-runtime/v1';

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Execute task
        register_rest_route(self::NAMESPACE, '/execute', [
            'methods' => 'POST',
            'callback' => [$this, 'execute_task'],
            'permission_callback' => [$this, 'check_api_permission'],
            'args' => [
                'task' => [
                    'required' => true,
                    'type' => 'object',
                    'description' => 'Task to execute'
                ],
                'options' => [
                    'required' => false,
                    'type' => 'object',
                    'description' => 'Execution options'
                ]
            ]
        ]);

        // Get execution status
        register_rest_route(self::NAMESPACE, '/status/(?P<schedule_id>[a-zA-Z0-9_-]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_execution_status'],
            'permission_callback' => [$this, 'check_api_permission']
        ]);

        // Get runtime statistics
        register_rest_route(self::NAMESPACE, '/statistics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_statistics'],
            'permission_callback' => [$this, 'check_api_permission']
        ]);

        // Get runtime configuration
        register_rest_route(self::NAMESPACE, '/config', [
            'methods' => 'GET',
            'callback' => [$this, 'get_config'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);

        // Update runtime configuration
        register_rest_route(self::NAMESPACE, '/config', [
            'methods' => 'POST',
            'callback' => [$this, 'update_config'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'max_latency_ms' => ['type' => 'integer'],
                'enable_prefetch' => ['type' => 'boolean'],
                'enable_aevip' => ['type' => 'boolean'],
                'default_parallelization' => ['type' => 'string']
            ]
        ]);

        // Get active nodes
        register_rest_route(self::NAMESPACE, '/nodes', [
            'methods' => 'GET',
            'callback' => [$this, 'get_nodes'],
            'permission_callback' => [$this, 'check_api_permission']
        ]);

        // Optimize task (preview)
        register_rest_route(self::NAMESPACE, '/optimize', [
            'methods' => 'POST',
            'callback' => [$this, 'optimize_task'],
            'permission_callback' => [$this, 'check_api_permission'],
            'args' => [
                'task' => ['required' => true, 'type' => 'object'],
                'constraints' => ['required' => false, 'type' => 'object']
            ]
        ]);

        // Get metrics
        register_rest_route(self::NAMESPACE, '/metrics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_metrics'],
            'permission_callback' => [$this, 'check_api_permission'],
            'args' => [
                'task_type' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'default' => 100]
            ]
        ]);
    }

    /**
     * Execute task endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function execute_task($request) {
        $task = $request->get_param('task');
        $options = $request->get_param('options') ?: [];

        try {
            // Get runtime instance
            global $aevov_runtime;

            if (!$aevov_runtime) {
                return new \WP_REST_Response([
                    'success' => false,
                    'error' => 'Runtime not initialized'
                ], 500);
            }

            // Add task ID if not present
            if (!isset($task['task_id'])) {
                $task['task_id'] = uniqid('task_', true);
            }

            // Optimize task if requested
            if ($options['optimize'] ?? true) {
                $constraints = $options['constraints'] ?? [];
                $task = $aevov_runtime->optimizer->optimize_task($task, $constraints);
            }

            // Decompose into tiles
            $tiles = $aevov_runtime->scheduler->decompose_task($task);

            // Create schedule
            $schedule_options = [
                'target_latency' => $options['target_latency'] ?? get_option('aevrt_max_latency_ms', 100),
                'enable_prefetch' => $options['enable_prefetch'] ?? get_option('aevrt_enable_prefetch', true),
                'use_aevip' => $options['use_aevip'] ?? get_option('aevrt_enable_aevip', true)
            ];

            $schedule = $aevov_runtime->scheduler->create_schedule($tiles, $schedule_options);

            // Execute schedule
            $result = $aevov_runtime->executor->execute_schedule($schedule);

            // Clean up
            $aevov_runtime->scheduler->remove_schedule($schedule['schedule_id']);

            return new \WP_REST_Response([
                'success' => true,
                'task_id' => $task['task_id'],
                'schedule_id' => $schedule['schedule_id'],
                'result' => $result['aggregated_result'],
                'metrics' => [
                    'estimated_latency' => $schedule['estimated_latency'],
                    'actual_latency' => $result['actual_latency'],
                    'num_tiles' => count($tiles),
                    'num_stages' => count($schedule['stages']),
                    'used_aevip' => $schedule['use_aevip']
                ]
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get execution status endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function get_execution_status($request) {
        $schedule_id = $request->get_param('schedule_id');

        global $aevov_runtime;

        if (!$aevov_runtime) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Runtime not initialized'
            ], 500);
        }

        $status = $aevov_runtime->executor->get_execution_status($schedule_id);

        if (!$status) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Schedule not found or completed'
            ], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'status' => $status
        ], 200);
    }

    /**
     * Get statistics endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function get_statistics($request) {
        global $aevov_runtime;

        if (!$aevov_runtime) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Runtime not initialized'
            ], 500);
        }

        $stats = [
            'scheduler' => [
                'active_schedules' => count($aevov_runtime->scheduler->get_schedule('all') ?: [])
            ],
            'executor' => $aevov_runtime->executor->get_execution_status('_stats') ?: [],
            'optimizer' => $aevov_runtime->optimizer->get_statistics(),
            'latency_analyzer' => $aevov_runtime->latency_analyzer->get_statistics()
        ];

        if ($aevov_runtime->aevip_coordinator) {
            $stats['aevip'] = $aevov_runtime->aevip_coordinator->get_statistics();
        }

        return new \WP_REST_Response([
            'success' => true,
            'statistics' => $stats
        ], 200);
    }

    /**
     * Get configuration endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function get_config($request) {
        $config = [
            'max_latency_ms' => get_option('aevrt_max_latency_ms', 100),
            'enable_prefetch' => get_option('aevrt_enable_prefetch', true),
            'enable_aevip' => get_option('aevrt_enable_aevip', true),
            'default_parallelization' => get_option('aevrt_default_parallelization', 'auto'),
            'tile_size_optimization' => get_option('aevrt_tile_size_optimization', true)
        ];

        return new \WP_REST_Response([
            'success' => true,
            'config' => $config
        ], 200);
    }

    /**
     * Update configuration endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function update_config($request) {
        $params = $request->get_params();

        $allowed_options = [
            'max_latency_ms' => 'aevrt_max_latency_ms',
            'enable_prefetch' => 'aevrt_enable_prefetch',
            'enable_aevip' => 'aevrt_enable_aevip',
            'default_parallelization' => 'aevrt_default_parallelization',
            'tile_size_optimization' => 'aevrt_tile_size_optimization'
        ];

        $updated = [];

        foreach ($params as $key => $value) {
            if (isset($allowed_options[$key])) {
                update_option($allowed_options[$key], $value);
                $updated[$key] = $value;
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'updated' => $updated
        ], 200);
    }

    /**
     * Get nodes endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function get_nodes($request) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aevov_physics_nodes';

        $nodes = $wpdb->get_results("
            SELECT node_id, address, status, capabilities, current_load, last_heartbeat
            FROM {$table_name}
            WHERE last_heartbeat > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ORDER BY current_load ASC
        ", ARRAY_A);

        return new \WP_REST_Response([
            'success' => true,
            'nodes' => $nodes ?: [],
            'total' => count($nodes)
        ], 200);
    }

    /**
     * Optimize task endpoint (preview only)
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function optimize_task($request) {
        $task = $request->get_param('task');
        $constraints = $request->get_param('constraints') ?: [];

        global $aevov_runtime;

        if (!$aevov_runtime) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => 'Runtime not initialized'
            ], 500);
        }

        try {
            $optimized = $aevov_runtime->optimizer->optimize_task($task, $constraints);

            return new \WP_REST_Response([
                'success' => true,
                'original' => $task,
                'optimized' => $optimized,
                'changes' => $this->calculate_changes($task, $optimized)
            ], 200);

        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get metrics endpoint
     *
     * @param \WP_REST_Request $request Request object
     * @return \WP_REST_Response Response
     */
    public function get_metrics($request) {
        global $wpdb;

        $task_type = $request->get_param('task_type');
        $limit = $request->get_param('limit') ?: 100;

        $table_name = $wpdb->prefix . 'aevov_runtime_metrics';

        $where = '';
        if ($task_type) {
            $where = $wpdb->prepare('WHERE task_type = %s', $task_type);
        }

        $metrics = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$table_name}
            {$where}
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit), ARRAY_A);

        // Calculate aggregate statistics
        $aggregate = [
            'total_executions' => count($metrics),
            'avg_latency' => 0,
            'min_latency' => 0,
            'max_latency' => 0,
            'success_rate' => 0
        ];

        if (!empty($metrics)) {
            $latencies = array_column($metrics, 'actual_latency');
            $successes = array_filter(array_column($metrics, 'success'));

            $aggregate['avg_latency'] = array_sum($latencies) / count($latencies);
            $aggregate['min_latency'] = min($latencies);
            $aggregate['max_latency'] = max($latencies);
            $aggregate['success_rate'] = (count($successes) / count($metrics)) * 100;
        }

        return new \WP_REST_Response([
            'success' => true,
            'metrics' => $metrics,
            'aggregate' => $aggregate
        ], 200);
    }

    /**
     * Calculate changes between original and optimized task
     *
     * @param array $original Original task
     * @param array $optimized Optimized task
     * @return array Changes
     */
    private function calculate_changes($original, $optimized) {
        $changes = [];

        if (($original['model'] ?? null) !== ($optimized['model'] ?? null)) {
            $changes[] = [
                'field' => 'model',
                'from' => $original['model'] ?? null,
                'to' => $optimized['model'] ?? null
            ];
        }

        if (isset($optimized['optimal_tile_size'])) {
            $changes[] = [
                'field' => 'tile_size',
                'value' => $optimized['optimal_tile_size'],
                'reason' => 'Optimized for target latency'
            ];
        }

        if (isset($optimized['parallelization_strategy'])) {
            $changes[] = [
                'field' => 'parallelization',
                'value' => $optimized['parallelization_strategy'],
                'parallel_degree' => $optimized['optimal_parallel_degree'] ?? null
            ];
        }

        return $changes;
    }

    /**
     * Check API permission
     *
     * @return bool True if permitted
     */
    public function check_api_permission() {
        // Check if API key is valid or user is authenticated
        $api_key = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : null;

        if ($api_key) {
            return $this->validate_api_key($api_key);
        }

        return current_user_can('edit_posts');
    }

    /**
     * Check admin permission
     *
     * @return bool True if permitted
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Validate API key
     *
     * @param string $api_key API key
     * @return bool True if valid
     */
    private function validate_api_key($api_key) {
        // Use API key manager if available
        if (class_exists('Aevov\\APIKeyManager')) {
            return \Aevov\APIKeyManager::validate_key('aevov-runtime', $api_key);
        }

        // Fallback to simple validation
        $stored_key = get_option('aevrt_api_key');
        return $stored_key && hash_equals($stored_key, $api_key);
    }
}
