<?php
/**
 * Pattern comparison API endpoints
 * Provides functionality for comparing patterns
 * 
 * @package APS
 * @subpackage API\Endpoints
 */

namespace APS\API\Endpoints;

use APS\Comparison\APS_Comparator;
use APS\Analysis\SymbolicPatternAnalyzer;

class PatternComparisonEndpoint extends BaseEndpoint {
    protected $base = 'patterns/comparison';
    
    public function __construct($namespace) {
        parent::__construct($namespace);
    }
    
    public function register_routes() {
        // Compare patterns
        register_rest_route($this->namespace, '/' . $this->base, [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'compare_patterns'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_comparison_args()
            ]
        ]);
        
        // Get comparison history
        register_rest_route($this->namespace, '/' . $this->base . '/history', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_comparison_history'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_history_args()
            ]
        ]);
        
        // Get specific comparison result
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>[\d]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_comparison_result'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'id' => [
                        'required' => true,
                        'type' => 'integer',
                        'validate_callback' => [$this, 'validate_id']
                    ]
                ]
            ]
        ]);
    }
    
    public function compare_patterns($request) {
        $items = $request->get_param('items');
        $options = $request->get_param('options') ?: [];
        
        if (empty($items) || !is_array($items)) {
            return $this->prepare_error(
                'invalid_items',
                'Items parameter is required and must be an array',
                400
            );
        }
        
        try {
            $comparator = new APS_Comparator();
            $result = $comparator->compare_patterns($items, $options);
            
            // Store comparison result
            $comparison_id = $this->store_comparison_result($result);
            
            return $this->prepare_response([
                'comparison_id' => $comparison_id,
                'result' => $result
            ]);
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'comparison_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    public function get_comparison_history($request) {
        $page = $request->get_param('page') ?: 1;
        $per_page = min($request->get_param('per_page') ?: 10, 100);
        
        try {
            $history = $this->get_comparison_history_data($page, $per_page);
            $total = $this->get_comparison_history_count();
            
            $response = $this->prepare_response($history);
            $response->header('X-WP-Total', $total);
            $response->header('X-WP-TotalPages', ceil($total / $per_page));
            
            return $response;
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'history_fetch_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    public function get_comparison_result($request) {
        $id = $request->get_param('id');
        
        try {
            $result = $this->get_comparison_result_data($id);
            
            if (!$result) {
                return $this->prepare_error(
                    'comparison_not_found',
                    'Comparison result not found',
                    404
                );
            }
            
            return $this->prepare_response($result);
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'result_fetch_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    protected function get_comparison_args() {
        return [
            'items' => [
                'required' => true,
                'type' => 'array',
                'description' => 'Array of patterns to compare'
            ],
            'options' => [
                'type' => 'object',
                'description' => 'Comparison options',
                'default' => []
            ]
        ];
    }
    
    protected function get_history_args() {
        return [
            'page' => [
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1
            ],
            'per_page' => [
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100
            ]
        ];
    }
    
    private function validate_id($id) {
        return is_numeric($id) && $id > 0;
    }
    
    private function store_comparison_result($result) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'aps_comparisons',
            [
                'comparison_uuid' => wp_generate_uuid4(),
                'comparison_type' => $result['type'] ?? 'generic',
                'items_data' => json_encode($result['items'] ?? []),
                'settings' => json_encode($result['settings'] ?? []),
                'status' => 'completed'
            ]
        );
        
        $comparison_id = $wpdb->insert_id;

        // Store detailed result in aps_comparison_results table
        $wpdb->insert(
            $wpdb->prefix . 'aps_comparison_results',
            [
                'comparison_id' => $comparison_id,
                'result_data' => json_encode($result)
            ],
            [
                '%d',
                '%s'
            ]
        );

        return $comparison_id;
    }
    
    private function get_comparison_history_data($page, $per_page) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, comparison_uuid, comparison_type, created_at, status
                 FROM {$wpdb->prefix}aps_comparisons
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
    }
    
    private function get_comparison_history_count() {
        global $wpdb;
        
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aps_comparisons");
    }
    
    private function get_comparison_result_data($id) {
        global $wpdb;
        
        $comparison = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aps_comparisons WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
        
        if (!$comparison) {
            return null;
        }
        
        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}aps_results WHERE comparison_id = %d",
                $id
            ),
            ARRAY_A
        );
        
        return [
            'comparison' => $comparison,
            'result' => $result
        ];
    }
}