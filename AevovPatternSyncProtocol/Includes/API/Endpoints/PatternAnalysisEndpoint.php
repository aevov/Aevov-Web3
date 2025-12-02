<?php
/**
 * Pattern Analysis API endpoints
 * Handles in-depth pattern analysis requests and batch processing
 * 
 * @package APS
 * @subpackage API\Endpoints
 */

namespace APS\API\Endpoints;

use APS\Analysis\PatternAnalyzer;
use APS\Queue\ProcessQueue;
use APS\DB\MetricsDB;

class PatternAnalysisEndpoint extends BaseEndpoint {
    protected $base = 'analysis';
    private $analyzer;
    private $queue;
    private $metrics;

    public function __construct($namespace) {
        parent::__construct($namespace);
        $this->analyzer = new PatternAnalyzer();
        $this->queue = new ProcessQueue();
        $this->metrics = new MetricsDB();
    }

    public function register_routes() {
        // Single pattern analysis
        register_rest_route($this->namespace, '/' . $this->base . '/analyze', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'analyze_pattern'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_analysis_args()
            ]
        ]);

        // Batch analysis
        register_rest_route($this->namespace, '/' . $this->base . '/batch', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'batch_analyze'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_batch_args()
            ]
        ]);

        // Analysis status
        register_rest_route($this->namespace, '/' . $this->base . '/status/(?P<job_id>[a-zA-Z0-9-]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_analysis_status'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => [
                    'job_id' => [
                        'required' => true,
                        'validate_callback' => [$this, 'validate_job_id']
                    ]
                ]
            ]
        ]);

        // Feature extraction
        register_rest_route($this->namespace, '/' . $this->base . '/features', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'extract_features'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_feature_args()
            ]
        ]);

        // Comparison analysis
        register_rest_route($this->namespace, '/' . $this->base . '/compare', [
            [
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => [$this, 'compare_patterns'],
                'permission_callback' => [$this, 'check_write_permission'],
                'args' => $this->get_comparison_args()
            ]
        ]);

        // Analysis reports
        register_rest_route($this->namespace, '/' . $this->base . '/reports', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_analysis_reports'],
                'permission_callback' => [$this, 'check_read_permission'],
                'args' => $this->get_report_args()
            ]
        ]);
    }

    public function analyze_pattern($request) {
        $start_time = microtime(true);
        
        try {
            $analysis = $this->analyzer->analyze_pattern($request->get_params());
            
            $this->record_analysis_metrics($analysis, microtime(true) - $start_time);
            
            return rest_ensure_response([
                'success' => true,
                'data' => $analysis,
                'processing_time' => microtime(true) - $start_time
            ]);

        } catch (\Exception $e) {
            $this->record_analysis_error($e);
            
            return new \WP_Error(
                'analysis_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function batch_analyze($request) {
        $patterns = $request->get_param('patterns');
        $options = $request->get_param('options') ?? [];
        
        try {
            $job_id = wp_generate_uuid4();
            
            // Queue each pattern for analysis
            foreach ($patterns as $pattern) {
                $this->queue->enqueue_job([
                    'type' => 'pattern_analysis',
                    'data' => [
                        'pattern' => $pattern,
                        'options' => $options,
                        'batch_id' => $job_id
                    ]
                ]);
            }

            return rest_ensure_response([
                'success' => true,
                'job_id' => $job_id,
                'total_patterns' => count($patterns),
                'status_endpoint' => $this->get_status_endpoint($job_id)
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'batch_creation_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_analysis_status($request) {
        $job_id = $request['job_id'];
        
        try {
            $status = $this->get_batch_status($job_id);
            
            return rest_ensure_response([
                'success' => true,
                'status' => $status['status'],
                'progress' => $status['progress'],
                'completed' => $status['completed'],
                'failed' => $status['failed'],
                'results' => $status['results']
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'status_check_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function extract_features($request) {
        try {
            $features = $this->analyzer->extract_features(
                $request->get_param('data'),
                $request->get_param('options') ?? []
            );

            return rest_ensure_response([
                'success' => true,
                'features' => $features
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'feature_extraction_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function compare_patterns($request) {
        try {
            $patterns = $request->get_param('patterns');
            $options = $request->get_param('options') ?? [];

            $comparison = $this->analyzer->compare_patterns($patterns, $options);

            return rest_ensure_response([
                'success' => true,
                'comparison' => $comparison
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'comparison_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function get_analysis_reports($request) {
        try {
            $reports = $this->get_reports(
                $request->get_param('start_date'),
                $request->get_param('end_date'),
                $request->get_param('type')
            );

            return rest_ensure_response([
                'success' => true,
                'reports' => $reports
            ]);

        } catch (\Exception $e) {
            return new \WP_Error(
                'report_generation_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    private function get_batch_status($job_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aps_queue 
             WHERE job_data LIKE %s",
            '%' . $wpdb->esc_like($job_id) . '%'
        ));

        $status = [
            'total' => count($results),
            'completed' => 0,
            'failed' => 0,
            'pending' => 0,
            'results' => []
        ];

        foreach ($results as $result) {
            switch ($result->status) {
                case 'completed':
                    $status['completed']++;
                    $status['results'][] = json_decode($result->result_data, true);
                    break;
                case 'failed':
                    $status['failed']++;
                    break;
                default:
                    $status['pending']++;
            }
        }

        $status['progress'] = ($status['completed'] + $status['failed']) / $status['total'] * 100;
        $status['status'] = $this->determine_batch_status($status);

        return $status;
    }

    private function determine_batch_status($status) {
        if ($status['failed'] === $status['total']) {
            return 'failed';
        }
        if ($status['completed'] === $status['total']) {
            return 'completed';
        }
        if ($status['failed'] > 0) {
            return 'partial';
        }
        return 'processing';
    }

    private function get_status_endpoint($job_id) {
        return rest_url($this->namespace . '/' . $this->base . '/status/' . $job_id);
    }

    private function record_analysis_metrics($analysis, $duration) {
        $this->metrics->record_metric('pattern_analysis', 1, [
            'duration' => $duration,
            'confidence' => $analysis['confidence'],
            'type' => $analysis['type']
        ]);
    }

    private function record_analysis_error(\Exception $e) {
        $this->metrics->record_metric('analysis_errors', 1, [
            'error_type' => get_class($e),
            'message' => $e->getMessage()
        ]);
    }

    protected function get_analysis_args() {
        return [
            'data' => [
                'required' => true,
                'type' => 'object'
            ],
            'options' => [
                'type' => 'object'
            ]
        ];
    }

    protected function get_batch_args() {
        return [
            'patterns' => [
                'required' => true,
                'type' => 'array',
                'items' => [
                    'type' => 'object'
                ]
            ],
            'options' => [
                'type' => 'object'
            ]
        ];
    }

    protected function validate_job_id($job_id) {
        return preg_match('/^[a-zA-Z0-9-]+$/', $job_id);
    }
    private function get_reports($start_date, $end_date, $type) {
        // This is a placeholder implementation.
        // In a real system, this would query a database for stored analysis reports
        // or aggregate data from metrics.
        
        $reports = [];
        
        // Example: Fetching analysis metrics from MetricsDB
        $analysis_metrics = $this->metrics->get_metrics_by_type('pattern_analysis', [
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);

        foreach ($analysis_metrics as $metric) {
            $reports[] = [
                'id' => $metric['id'],
                'type' => $metric['type'],
                'timestamp' => $metric['timestamp'],
                'data' => $metric['metadata'], // Assuming metadata contains relevant analysis data
                'status' => 'completed' // Assuming metrics represent completed analyses
            ];
        }

        // Filter by type if specified
        if ($type) {
            $reports = array_filter($reports, function($report) use ($type) {
                return isset($report['data']['type']) && $report['data']['type'] === $type;
            });
        }

        return $reports;
    }
}