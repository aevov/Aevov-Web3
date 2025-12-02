<?php
/**
 * Network API endpoints for multisite functionality
 * 
 * @package APS
 * @subpackage API\Endpoints
 */

namespace APS\API\Endpoints;

use APS\Network\NetworkMonitor;
use APS\Network\PatternDistributor;
use APS\Network\SyncManager;
use APS\DB\MetricsDB;

class NetworkEndpoint extends BaseEndpoint {
    protected $base = 'network';
    private $network_monitor;
    private $pattern_distributor;
    private $sync_manager;
    private $metrics;

    public function __construct($namespace) {
        parent::__construct($namespace);
        
        if (!is_multisite()) {
            return;
        }

        $this->network_monitor = new NetworkMonitor();
        $this->pattern_distributor = new PatternDistributor();
        $this->sync_manager = new SyncManager();
        $this->metrics = new MetricsDB();
    }

    public function register_routes() {
        if (!is_multisite()) {
            return;
        }

        // Network topology and status
        register_rest_route($this->namespace, '/' . $this->base . '/topology', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_network_topology'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
        ]);

        // Site management
        register_rest_route($this->namespace, '/' . $this->base . '/sites', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_network_sites'],
                'permission_callback' => [$this, 'check_read_permission']
            ],
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'update_site_status'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args' => $this->get_site_update_args()
            ]
        ]);

        // Pattern distribution
        register_rest_route($this->namespace, '/' . $this->base . '/distribute', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'distribute_patterns'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_distribution_args()
            ]
        ]);

        // Network sync
        register_rest_route($this->namespace, '/' . $this->base . '/sync', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'sync_network'],
                'permission_callback' => [$this, 'check_write_permission']
            ]
        ]);

        // Distribution status
        register_rest_route($this->namespace, '/' . $this->base . '/distribution-status', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_distribution_status'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
        ]);

        // Network metrics
        register_rest_route($this->namespace, '/' . $this->base . '/metrics', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_network_metrics'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_metrics_args()
            ]
        ]);

        // Site health
        register_rest_route($this->namespace, '/' . $this->base . '/sites/(?P<site_id>\d+)/health', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_site_health'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'site_id' => [
                        'required' => true,
                        'type' => 'integer'
                    ]
                ]
            ]
        ]);

        // Network rebalancing
        register_rest_route($this->namespace, '/' . $this->base . '/rebalance', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'rebalance_network'],
                'permission_callback' => [$this, 'check_admin_permission']
            ]
        ]);
    }

    public function get_network_topology($request) {
        try {
            $topology = $this->network_monitor->get_network_topology();
            
            return rest_ensure_response([
                'success' => true,
                'topology' => $topology,
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'topology_fetch_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_network_sites($request) {
        try {
            $sites = $this->network_monitor->get_active_sites();
            
            return rest_ensure_response([
                'success' => true,
                'sites' => $sites,
                'total' => count($sites),
                'status' => $this->network_monitor->get_sites_status()
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'sites_fetch_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function update_site_status($request) {
        try {
            $site_id = $request['site_id'];
            $status = $request['status'];
            
            $updated = $this->network_monitor->update_site_status($site_id, $status);
            
            return rest_ensure_response([
                'success' => true,
                'site_id' => $site_id,
                'status' => $status,
                'updated' => $updated
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'status_update_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function distribute_patterns($request) {
        try {
            $pattern_ids = $request['pattern_ids'];
            $target_sites = $request['target_sites'] ?? null;
            
            $distribution = $this->pattern_distributor->distribute_patterns(
                $pattern_ids,
                $target_sites
            );
            
            return rest_ensure_response([
                'success' => true,
                'distribution_id' => $distribution['id'],
                'patterns' => $distribution['patterns'],
                'target_sites' => $distribution['target_sites']
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'distribution_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function sync_network($request) {
        try {
            $sync_result = $this->sync_manager->perform_network_sync();
            
            return rest_ensure_response([
                'success' => true,
                'sync_id' => $sync_result['sync_id'],
                'sites_synced' => $sync_result['sites_synced'],
                'timestamp' => $sync_result['timestamp']
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'sync_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_distribution_status($request) {
        try {
            $status = $this->pattern_distributor->get_distribution_stats();
            
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

    public function get_network_metrics($request) {
        try {
            $metrics = $this->metrics->get_network_metrics([
                'duration' => $request['duration'] ?? '24hours',
                'type' => $request['type'] ?? 'all'
            ]);
            
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

    public function get_site_health($request) {
        try {
            $site_id = $request['site_id'];
            $health = $this->network_monitor->get_site_health($site_id);
            
            return rest_ensure_response([
                'success' => true,
                'site_id' => $site_id,
                'health' => $health
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'health_check_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function rebalance_network($request) {
        try {
            $rebalance_result = $this->pattern_distributor->rebalance_patterns();
            
            return rest_ensure_response([
                'success' => true,
                'rebalance_id' => $rebalance_result['id'],
                'patterns_moved' => $rebalance_result['patterns_moved'],
                'sites_affected' => $rebalance_result['sites_affected']
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'rebalance_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    protected function get_site_update_args() {
        return [
            'site_id' => [
                'required' => true,
                'type' => 'integer'
            ],
            'status' => [
                'required' => true,
                'type' => 'string',
                'enum' => ['active', 'inactive', 'maintenance']
            ]
        ];
    }

    protected function get_distribution_args() {
        return [
            'pattern_ids' => [
                'required' => true,
                'type' => 'array',
                'items' => [
                    'type' => 'string'
                ]
            ],
            'target_sites' => [
                'type' => 'array',
                'items' => [
                    'type' => 'integer'
                ]
            ]
        ];
    }

    protected function get_metrics_args() {
        return [
            'duration' => [
                'type' => 'string',
                'enum' => ['1hour', '24hours', '7days', '30days'],
                'default' => '24hours'
            ],
            'type' => [
                'type' => 'string',
                'enum' => ['all', 'distribution', 'sync', 'performance']
            ]
        ];
    }
}