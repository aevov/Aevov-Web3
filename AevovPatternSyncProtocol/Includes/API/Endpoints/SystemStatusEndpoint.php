<?php
/**
 * System Status API endpoints
 * Provides system health and monitoring information
 * 
 * @package APS
 * @subpackage API\Endpoints
 */

namespace APS\API\Endpoints;

use APS\Monitoring\SystemMonitor;
use APS\Monitoring\NetworkMonitor;
use APS\DB\MetricsDB;

class SystemStatusEndpoint extends BaseEndpoint {
    protected $base = 'status';
    private $system_monitor;
    private $network_monitor;
    private $metrics;

    public function __construct($namespace) {
        parent::__construct($namespace);
        $this->system_monitor = new SystemMonitor();
        $this->network_monitor = new NetworkMonitor();
        $this->metrics = new MetricsDB();
    }

    public function register_routes() {
        // System health status
        register_rest_route($this->namespace, '/' . $this->base . '/health', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_health_status'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
        ]);

        // Performance metrics
        register_rest_route($this->namespace, '/' . $this->base . '/performance', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_performance_metrics'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_performance_args()
            ]
        ]);

        // Resource usage
        register_rest_route($this->namespace, '/' . $this->base . '/resources', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_resource_usage'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
        ]);

        // Queue status
        register_rest_route($this->namespace, '/' . $this->base . '/queue', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_queue_status'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
        ]);

        // Network status (multisite only)
        register_rest_route($this->namespace, '/' . $this->base . '/network', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_network_status'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
        ]);

        // Integration status
        register_rest_route($this->namespace, '/' . $this->base . '/integrations', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_integration_status'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
        ]);
    }

    public function get_health_status($request) {
        try {
            $health = $this->system_monitor->get_system_status();
            
            return rest_ensure_response([
                'success' => true,
                'status' => $health['status'],
                'components' => $health['components'],
                'last_check' => $health['last_check'],
                'alerts' => $health['alerts']
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'health_check_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_performance_metrics($request) {
        $duration = $request->get_param('duration') ?? '1hour';
        
        try {
            $metrics = $this->metrics->get_performance_metrics([
                'duration' => $duration,
                'aggregation' => $request->get_param('aggregation'),
                'type' => $request->get_param('type')
            ]);

            return rest_ensure_response([
                'success' => true,
                'metrics' => $metrics,
                'period' => [
                    'start' => $metrics['period_start'],
                    'end' => $metrics['period_end']
                ]
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'metrics_fetch_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_resource_usage($request) {
        try {
            $resources = $this->system_monitor->get_resource_usage();
            
            return rest_ensure_response([
                'success' => true,
                'resources' => $resources,
                'thresholds' => $this->system_monitor->get_resource_thresholds(),
                'timestamp' => time()
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'resource_check_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_queue_status($request) {
        try {
            $queue_stats = $this->system_monitor->get_queue_stats();
            
            return rest_ensure_response([
                'success' => true,
                'queue' => $queue_stats,
                'processing_rate' => $this->metrics->get_metric_average('processing_rate'),
                'error_rate' => $this->metrics->get_metric_average('error_rate')
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'queue_stats_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_network_status($request) {
        if (!is_multisite()) {
            return new \WP_Error(
                'network_not_supported',
                'Network features require multisite',
                ['status' => 400]
            );
        }

        try {
            $network_status = $this->network_monitor->get_network_status();
            
            return rest_ensure_response([
                'success' => true,
                'network' => $network_status,
                'distribution' => $this->network_monitor->get_pattern_distribution(),
                'sync_status' => $this->network_monitor->get_sync_status()
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'network_status_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_integration_status($request) {
        try {
            $integrations = $this->system_monitor->get_integration_status();
            
            return rest_ensure_response([
                'success' => true,
                'integrations' => $integrations,
                'sync_status' => $this->system_monitor->get_integration_sync_status()
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'integration_status_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    protected function get_performance_args() {
        return [
            'duration' => [
                'type' => 'string',
                'enum' => ['1hour', '24hours', '7days', '30days'],
                'default' => '1hour'
            ],
            'aggregation' => [
                'type' => 'string',
                'enum' => ['none', 'minute', 'hour', 'day'],
                'default' => 'none'
            ],
            'type' => [
                'type' => 'string',
                'enum' => ['all', 'system', 'patterns', 'network']
            ]
        ];
    }
}