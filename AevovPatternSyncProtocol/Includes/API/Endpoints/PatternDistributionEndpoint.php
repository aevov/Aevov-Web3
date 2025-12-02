<?php
/**
 * Pattern distribution API endpoints
 * Provides functionality for distributing patterns across multisite network
 * 
 * @package APS
 * @subpackage API\Endpoints
 */

namespace APS\API\Endpoints;

use APS\Network\PatternDistributor;
use APS\Network\NetworkConfig;
use APS\DB\APS_Pattern_DB; // New import

class PatternDistributionEndpoint extends BaseEndpoint {
    protected $base = 'patterns/distribution';
    private $distributor;
    private $network_config;
    private $pattern_db; // New property
    
    public function __construct($namespace) {
        parent::__construct($namespace);
        $this->distributor = new PatternDistributor();
        $this->network_config = new NetworkConfig();
        $this->pattern_db = new APS_Pattern_DB(); // Initialize
    }
    
    public function register_routes() {
        // Distribute a pattern to network
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<hash>[a-zA-Z0-9]+)', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'distribute_pattern'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => [
                    'hash' => [
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => [$this, 'validate_hash']
                    ],
                    'sites' => [
                        'type' => 'array',
                        'description' => 'Specific sites to distribute to (optional, defaults to all)',
                        'default' => []
                    ],
                    'options' => [
                        'type' => 'object',
                        'description' => 'Distribution options',
                        'default' => []
                    ]
                ]
            ]
        ]);
        
        // Get distribution status
        register_rest_route($this->namespace, '/' . $this->base . '/status/(?P<hash>[a-zA-Z0-9]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_distribution_status'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'hash' => [
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => [$this, 'validate_hash']
                    ]
                ]
            ]
        ]);
        
        // Get network distribution info
        register_rest_route($this->namespace, '/' . $this->base . '/network', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_network_info'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
        ]);
        
        // Bulk distribute patterns
        register_rest_route($this->namespace, '/' . $this->base . '/bulk', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'bulk_distribute'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => $this->get_bulk_args()
            ]
        ]);
    }
    
    public function distribute_pattern($request) {
        if (!is_multisite()) {
            return $this->prepare_error(
                'multisite_required',
                'Pattern distribution requires multisite installation',
                400
            );
        }
        
        $hash = $request->get_param('hash');
        $sites = $request->get_param('sites') ?: [];
        $options = $request->get_param('options') ?: [];
        
        try {
            // Get pattern by hash
            $pattern = $this->get_pattern_by_hash($hash);
            if (!$pattern) {
                return $this->prepare_error(
                    'pattern_not_found',
                    'Pattern not found',
                    404
                );
            }
            
            // Distribute pattern
            $distribution_result = $this->distributor->distribute_pattern($pattern, $sites, $options);
            
            return $this->prepare_response([
                'pattern_hash' => $hash,
                'distributed_to' => $distribution_result['distributed_to'],
                'failed_sites' => $distribution_result['failed_sites'],
                'total_sites' => $distribution_result['total_sites']
            ]);
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'distribution_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    public function get_distribution_status($request) {
        if (!is_multisite()) {
            return $this->prepare_error(
                'multisite_required',
                'Pattern distribution requires multisite installation',
                400
            );
        }
        
        $hash = $request->get_param('hash');
        
        try {
            $status = $this->distributor->get_distribution_status($hash);
            
            return $this->prepare_response([
                'pattern_hash' => $hash,
                'status' => $status
            ]);
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'status_fetch_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    public function get_network_info($request) {
        if (!is_multisite()) {
            return $this->prepare_error(
                'multisite_required',
                'Network information requires multisite installation',
                400
            );
        }
        
        try {
            $sites = get_sites(['fields' => 'ids']);
            $network_info = [
                'total_sites' => count($sites),
                'active_sites' => $this->network_config->get_active_sites(),
                'sync_enabled' => $this->network_config->is_sync_enabled(),
                'last_sync' => $this->network_config->get_last_sync_time(),
                'distribution_stats' => $this->distributor->get_distribution_stats()
            ];
            
            return $this->prepare_response($network_info);
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'network_info_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    public function bulk_distribute($request) {
        if (!is_multisite()) {
            return $this->prepare_error(
                'multisite_required',
                'Bulk distribution requires multisite installation',
                400
            );
        }
        
        $patterns = $request->get_param('patterns');
        $sites = $request->get_param('sites') ?: [];
        $options = $request->get_param('options') ?: [];
        
        if (empty($patterns) || !is_array($patterns)) {
            return $this->prepare_error(
                'invalid_patterns',
                'Patterns parameter is required and must be an array',
                400
            );
        }
        
        try {
            $results = [];
            $errors = [];
            
            foreach ($patterns as $pattern_hash) {
                try {
                    $pattern = $this->get_pattern_by_hash($pattern_hash);
                    if (!$pattern) {
                        $errors[] = [
                            'pattern_hash' => $pattern_hash,
                            'error' => 'Pattern not found'
                        ];
                        continue;
                    }
                    
                    $distribution_result = $this->distributor->distribute_pattern($pattern, $sites, $options);
                    $results[] = [
                        'pattern_hash' => $pattern_hash,
                        'distributed_to' => $distribution_result['distributed_to'],
                        'failed_sites' => $distribution_result['failed_sites']
                    ];
                } catch (\Exception $e) {
                    $errors[] = [
                        'pattern_hash' => $pattern_hash,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return $this->prepare_response([
                'results' => $results,
                'errors' => $errors,
                'total_processed' => count($results),
                'total_errors' => count($errors)
            ]);
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'bulk_distribution_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    protected function get_bulk_args() {
        return [
            'patterns' => [
                'required' => true,
                'type' => 'array',
                'description' => 'Array of pattern hashes to distribute'
            ],
            'sites' => [
                'type' => 'array',
                'description' => 'Specific sites to distribute to (optional, defaults to all)',
                'default' => []
            ],
            'options' => [
                'type' => 'object',
                'description' => 'Distribution options',
                'default' => []
            ]
        ];
    }
    
    private function validate_hash($hash) {
        return preg_match('/^[a-zA-Z0-9]+$/', $hash);
    }
    
    private function get_pattern_by_hash($hash) {
        return $this->pattern_db->get_pattern($hash);
    }
}