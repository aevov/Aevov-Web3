<?php
/**
 * Metrics API endpoints
 * Provides access to system and pattern metrics
 * 
 * @package APS
 * @subpackage API\Endpoints
 */

namespace APS\API\Endpoints;

use APS\DB\MetricsDB;
use APS\Monitoring\SystemMonitor;

class MetricsEndpoint extends BaseEndpoint {
    protected $base = 'metrics';
    private $metrics_db;
    private $system_monitor;
    
    public function __construct($namespace) {
        parent::__construct($namespace);
        $this->metrics_db = new MetricsDB();
        $this->system_monitor = new SystemMonitor();
    }
    
    public function register_routes() {
        // Get system metrics
        register_rest_route($this->namespace, '/' . $this->base . '/system', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_system_metrics'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_system_metrics_args()
            ]
        ]);
        
        // Get pattern metrics
        register_rest_route($this->namespace, '/' . $this->base . '/patterns', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_pattern_metrics'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_pattern_metrics_args()
            ]
        ]);
        
        // Get performance metrics
        register_rest_route($this->namespace, '/' . $this->base . '/performance', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_performance_metrics'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_performance_args()
            ]
        ]);
        
        // Get metrics summary
        register_rest_route($this->namespace, '/' . $this->base . '/summary', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_metrics_summary'],
                'permission_callback' => [$this, 'check_read_permission']
            ]
        ]);
        
        // Get specific metric by ID
        register_rest_route($this->namespace, '/' . $this->base . '/(?P<id>[\d]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_metric'],
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

        // Manually trigger metric collection
        register_rest_route($this->namespace, '/' . $this->base . '/collect', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'collect_metrics'],
                'permission_callback' => [$this, 'check_write_permission']
            ]
        ]);
    }
    
    public function get_system_metrics($request) {
        $duration = $request->get_param('duration') ?: '1hour';
        $type = $request->get_param('type') ?: 'all';
        
        try {
            $metrics = $this->metrics_db->get_system_metrics([
                'duration' => $duration,
                'type' => $type
            ]);
            
            return $this->prepare_response([
                'metrics' => $metrics,
                'duration' => $duration,
                'type' => $type
            ]);
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'system_metrics_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    public function get_pattern_metrics($request) {
        $duration = $request->get_param('duration') ?: '1hour';
        $aggregation = $request->get_param('aggregation') ?: 'none';
        
        try {
            $metrics = $this->metrics_db->get_pattern_metrics([
                'duration' => $duration,
                'aggregation' => $aggregation
            ]);
            
            return $this->prepare_response([
                'metrics' => $metrics,
                'duration' => $duration,
                'aggregation' => $aggregation
            ]);
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'pattern_metrics_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    public function get_performance_metrics($request) {
        $duration = $request->get_param('duration') ?: '1hour';
        $type = $request->get_param('type') ?: 'all';
        
        try {
            $metrics = $this->metrics_db->get_performance_metrics([
                'duration' => $duration,
                'type' => $type
            ]);
            
            return $this->prepare_response([
                'metrics' => $metrics,
                'duration' => $duration,
                'type' => $type
            ]);
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'performance_metrics_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    public function get_metrics_summary($request) {
        try {
            $summary = [
                'system' => $this->system_monitor->get_system_status(),
                'patterns' => $this->get_pattern_summary(),
                'performance' => $this->get_performance_summary(),
                'storage' => $this->get_storage_summary()
            ];
            
            return $this->prepare_response($summary);
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'metrics_summary_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    public function get_metric($request) {
        $id = $request->get_param('id');
        
        try {
            $metric = $this->metrics_db->get_metric_by_id($id);
            
            if (!$metric) {
                return $this->prepare_error(
                    'metric_not_found',
                    'Metric not found',
                    404
                );
            }
            
            return $this->prepare_response($metric);
            
        } catch (\Exception $e) {
            return $this->prepare_error(
                'metric_fetch_failed',
                $e->getMessage(),
                500
            );
        }
    }
    
    protected function get_system_metrics_args() {
        return [
            'duration' => [
                'type' => 'string',
                'enum' => ['1hour', '24hours', '7days', '30days'],
                'default' => '1hour'
            ],
            'type' => [
                'type' => 'string',
                'enum' => ['all', 'cpu', 'memory', 'disk', 'network'],
                'default' => 'all'
            ]
        ];
    }
    
    protected function get_pattern_metrics_args() {
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
            ]
        ];
    }
    
    protected function get_performance_args() {
        return [
            'duration' => [
                'type' => 'string',
                'enum' => ['1hour', '24hours', '7days', '30days'],
                'default' => '1hour'
            ],
            'type' => [
                'type' => 'string',
                'enum' => ['all', 'processing', 'sync', 'api'],
                'default' => 'all'
            ]
        ];
    }
    
    private function validate_id($id) {
        return is_numeric($id) && $id > 0;
    }
    
    private function get_pattern_summary() {
        global $wpdb;
        
        return [
            'total_patterns' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aps_patterns"),
            'patterns_today' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aps_patterns WHERE DATE(created_at) = CURDATE()"),
            'average_confidence' => $wpdb->get_var("SELECT AVG(confidence) FROM {$wpdb->prefix}aps_patterns"),
            'pattern_types' => $wpdb->get_results("SELECT pattern_type, COUNT(*) as count FROM {$wpdb->prefix}aps_patterns GROUP BY pattern_type", ARRAY_A)
        ];
    }
    
    private function get_performance_summary() {
        return [
            'processing_rate' => $this->metrics_db->get_metric_average('processing_rate'),
            'error_rate' => $this->metrics_db->get_metric_average('error_rate'),
            'sync_success_rate' => $this->metrics_db->get_metric_average('sync_success_rate'),
            'api_response_time' => $this->metrics_db->get_metric_average('api_response_time')
        ];
    }
    
    private function get_storage_summary() {
        global $wpdb;
        
        $table_sizes = [];
        // Dynamically get all tables with the APS prefix
        $aps_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}aps_%'");
        
        foreach ($aps_tables as $full_table_name) {
            // Extract the short name for display
            $table_short_name = str_replace($wpdb->prefix . 'aps_', 'aps_', $full_table_name);
            $size = $wpdb->get_var("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '{$full_table_name}'");
            $table_sizes[$table_short_name] = $size ? $size . ' MB' : '0 MB';
        }
        
        return [
            'table_sizes' => $table_sizes,
            'total_size' => array_sum(array_map(function($size) {
                return floatval(str_replace(' MB', '', $size));
            }, $table_sizes)) . ' MB'
        ];
    }
    public function collect_metrics($request) {
        try {
            $this->system_monitor->collect_metrics();
            return $this->prepare_response(['message' => 'Metrics collection triggered successfully.']);
        } catch (\Exception $e) {
            return $this->prepare_error(
                'metrics_collection_failed',
                $e->getMessage(),
                500
            );
        }
    }
}