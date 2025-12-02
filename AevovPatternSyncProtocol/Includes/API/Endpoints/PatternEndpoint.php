<?php
/**
 * Pattern-related API endpoints
 * 
 * @package APS
 * @subpackage API\Endpoints
 */

namespace APS\API\Endpoints;

use APS\Analysis\PatternAnalyzer;
use APS\Network\PatternDistributor;
use APS\DB\APS_Pattern_DB; // New import

class PatternEndpoint extends BaseEndpoint {
    protected $base = 'patterns';
    private $pattern_db; // New property

    public function __construct($namespace) {
        parent::__construct($namespace);
        $this->pattern_db = new APS_Pattern_DB(); // Initialize
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/' . $this->base, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_patterns'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_collection_params()
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_pattern'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_endpoint_args_for_item_schema(true)
            ]
        ]);

        register_rest_route($this->namespace, '/' . $this->base . '/(?P<hash>[a-zA-Z0-9]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_pattern'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'hash' => [
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => [$this, 'validate_hash']
                    ]
                ]
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_pattern'],
                'permission_callback' => [$this, 'check_write_permission']
            ],
            [
                'methods' => \WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_pattern'],
                'permission_callback' => [$this, 'check_admin_permission']
            ]
        ]);

    }

    public function get_patterns($request) {
        $args = [
            'type' => $request->get_param('type'),
            'confidence' => $request->get_param('confidence'),
            'tensor_sku' => $request->get_param('tensor_sku'),
            'site_id' => $request->get_param('site_id'),
            'status' => $request->get_param('status'),
            'start_date' => $request->get_param('start_date'),
            'end_date' => $request->get_param('end_date'),
            'search' => $request->get_param('search'),
            'page' => $request->get_param('page') ?? 1,
            'per_page' => min($request->get_param('per_page') ?? 10, 100),
            'orderby' => $request->get_param('orderby') ?? 'created_at',
            'order' => $request->get_param('order') ?? 'desc'
        ];
 
        $patterns = $this->pattern_db->get_pattern_collection($args);
        $total = $this->pattern_db->get_patterns_count($args);
 
        $response = rest_ensure_response($patterns);
        $response->header('X-WP-Total', $total);
        $response->header('X-WP-TotalPages', ceil($total / $args['per_page']));
 
        return $response;
    }

    public function create_pattern($request) {
        $pattern_data = $this->prepare_pattern_for_database($request);
        
        try {
            $pattern_id = $this->pattern_db->insert_pattern($pattern_data);
            $pattern = $this->pattern_db->get_pattern_by_id($pattern_id);
            
            return rest_ensure_response($pattern);
        } catch (\Exception $e) {
            return new \WP_Error(
                'pattern_creation_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_pattern($request) {
        $pattern = $this->pattern_db->get_pattern($request['hash']);
        
        if (!$pattern) {
            return new \WP_Error(
                'pattern_not_found',
                'Pattern not found',
                ['status' => 404]
            );
        }

        return rest_ensure_response($pattern);
    }

    public function update_pattern($request) {
        $hash = $request['hash'];
        $pattern_data = $this->prepare_pattern_for_database($request, true); // Pass true for update

        try {
            $updated = $this->pattern_db->update_pattern($hash, $pattern_data);
            if (!$updated) {
                return new \WP_Error(
                    'pattern_update_failed',
                    'Failed to update pattern or pattern not found.',
                    ['status' => 500]
                );
            }
            $pattern = $this->pattern_db->get_pattern($hash); // Fetch updated pattern
            return rest_ensure_response($pattern);
        } catch (\Exception $e) {
            return new \WP_Error(
                'pattern_update_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function delete_pattern($request) {
        $hash = $request['hash'];

        try {
            $deleted = $this->pattern_db->delete_pattern($hash);
            if (!$deleted) {
                return new \WP_Error(
                    'pattern_delete_failed',
                    'Failed to delete pattern or pattern not found.',
                    ['status' => 500]
                );
            }
            return new \WP_REST_Response(null, 204); // No Content
        } catch (\Exception $e) {
            return new \WP_Error(
                'pattern_delete_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }
 

    protected function get_collection_params() {
        return [
            'page' => [
                'description' => 'Current page of the collection.',
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
            ],
            'per_page' => [
                'description' => 'Maximum number of items to be returned in result set.',
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'type' => [
                'description' => 'Filter patterns by type.',
                'type' => 'string',
            ],
            'confidence' => [
                'description' => 'Filter patterns by minimum confidence score.',
                'type' => 'number',
                'minimum' => 0,
                'maximum' => 1,
            ],
            'tensor_sku' => [
                'description' => 'Filter patterns by associated tensor SKU.',
                'type' => 'string',
            ],
            'site_id' => [
                'description' => 'Filter patterns by site ID (for multisite).',
                'type' => 'integer',
                'minimum' => 1,
            ],
            'status' => [
                'description' => 'Filter patterns by status (e.g., active, archived).',
                'type' => 'string',
                'enum' => ['active', 'archived', 'pending', 'error'],
            ],
            'start_date' => [
                'description' => 'Filter patterns created after this date (YYYY-MM-DD).',
                'type' => 'string',
                'format' => 'date',
            ],
            'end_date' => [
                'description' => 'Filter patterns created before this date (YYYY-MM-DD).',
                'type' => 'string',
                'format' => 'date',
            ],
            'orderby' => [
                'description' => 'Order collection by object attribute.',
                'type' => 'string',
                'default' => 'created_at',
                'enum' => [
                    'id',
                    'pattern_hash',
                    'pattern_type',
                    'confidence',
                    'created_at',
                    'updated_at',
                ],
            ],
            'order' => [
                'description' => 'Order sort attribute ascending or descending.',
                'type' => 'string',
                'default' => 'desc',
                'enum' => ['asc', 'desc'],
            ],
            'search' => [
                'description' => 'Search for patterns by keywords in features or metadata.',
                'type' => 'string',
            ],
        ];
    }


    private function validate_hash($hash) {
        return preg_match('/^[a-zA-Z0-9]+$/', $hash);
    }
}