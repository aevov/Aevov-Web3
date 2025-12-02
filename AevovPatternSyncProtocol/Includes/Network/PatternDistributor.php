<?php
/**
 * Pattern distribution across network sites
 * 
 * @package APS
 * @subpackage Network
 */

namespace APS\Network;

use APS\DB\MetricsDB;
use APS\Monitoring\AlertManager;
use APS\DB\NetworkCache;

class PatternDistributor {
    private $metrics;
    private $alert_manager;
    private $network_cache;
    private $distribution_threshold = 0.75;
    private $min_sites = 2;
    private $max_sites = 5;
    private $retry_limit = 3;
    private $distribution_table;

    public function __construct() {
        global $wpdb;
        $this->distribution_table = $wpdb->prefix . 'aps_pattern_distribution';
        
        $this->metrics = new MetricsDB();
        $this->alert_manager = new AlertManager();
        $this->network_cache = new NetworkCache();
        
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('aps_distribute_pattern', [$this, 'distribute_pattern']);
        add_action('aps_verify_distribution', [$this, 'verify_distribution']);
        add_action('aps_rebalance_patterns', [$this, 'rebalance_patterns']);
        add_action('wp_initialize_site', [$this, 'handle_new_site'], 10, 2);
        add_action('wp_uninitialize_site', [$this, 'handle_site_deletion']);
    }

    public function distribute_pattern($pattern_data) {
        $start_time = microtime(true);

        try {
            // Check if pattern meets distribution criteria
            if (!$this->should_distribute($pattern_data)) {
                return false;
            }

            // Select target sites
            $target_sites = $this->select_target_sites($pattern_data);
            if (empty($target_sites)) {
                throw new \Exception('No suitable target sites found for distribution');
            }

            // Prepare distribution package
            $distribution_data = $this->prepare_distribution($pattern_data, $target_sites);

            // Distribute to each site
            $distribution_results = $this->perform_distribution($distribution_data);

            // Verify distribution
            $this->verify_distribution($distribution_data['distribution_id']);

            // Record metrics
            $this->record_distribution_metrics($pattern_data, $distribution_results, microtime(true) - $start_time);

            return $distribution_results;

        } catch (\Exception $e) {
            $this->handle_distribution_error($e, $pattern_data);
            throw $e;
        }
    }

    private function should_distribute($pattern_data) {
        // Check confidence threshold
        if ($pattern_data['confidence'] < $this->distribution_threshold) {
            return false;
        }

        // Check if already distributed
        if ($this->is_pattern_distributed($pattern_data['pattern_hash'])) {
            return false;
        }

        return true;
    }

    private function select_target_sites($pattern_data) {
        $available_sites = $this->get_available_sites();
        $selected_sites = [];

        // Remove current site from candidates
        $current_site_id = get_current_blog_id();
        unset($available_sites[$current_site_id]);

        // Get site loads and capabilities
        $site_metrics = $this->get_site_metrics($available_sites);

        // Sort sites by suitability
        uasort($site_metrics, function($a, $b) {
            return $this->calculate_site_suitability($b) - $this->calculate_site_suitability($a);
        });

        // Select best sites within limits
        $target_count = min(
            $this->max_sites,
            max($this->min_sites, count($available_sites) / 3)
        );

        foreach ($site_metrics as $site_id => $metrics) {
            if (count($selected_sites) >= $target_count) {
                break;
            }

            if ($this->is_site_suitable($metrics)) {
                $selected_sites[] = $site_id;
            }
        }

        return $selected_sites;
    }

    private function prepare_distribution($pattern_data, $target_sites) {
        return [
            'distribution_id' => wp_generate_uuid4(),
            'pattern_hash' => $pattern_data['pattern_hash'],
            'source_site' => get_current_blog_id(),
            'target_sites' => $target_sites,
            'pattern_data' => $pattern_data,
            'timestamp' => time(),
            'verification_token' => wp_generate_uuid4()
        ];
    }

    private function perform_distribution($distribution_data) {
        $results = [
            'successful_sites' => [],
            'failed_sites' => [],
            'distribution_id' => $distribution_data['distribution_id']
        ];

        foreach ($distribution_data['target_sites'] as $site_id) {
            try {
                $this->distribute_to_site($site_id, $distribution_data);
                $results['successful_sites'][] = $site_id;
            } catch (\Exception $e) {
                $results['failed_sites'][] = [
                    'site_id' => $site_id,
                    'error' => $e->getMessage()
                ];

                $this->alert_manager->trigger_alert('distribution_site_failure', [
                    'site_id' => $site_id,
                    'distribution_id' => $distribution_data['distribution_id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->store_distribution_results($distribution_data, $results);
        return $results;
    }

    private function distribute_to_site($site_id, $distribution_data) {
        switch_to_blog($site_id);

        try {
            // Store pattern data
            $this->store_pattern($distribution_data['pattern_data']);

            // Record distribution
            $this->record_distribution($distribution_data);

            // Store verification data
            $this->store_verification_data($distribution_data);

            restore_current_blog();
            return true;

        } catch (\Exception $e) {
            restore_current_blog();
            throw $e;
        }
    }

    public function verify_distribution($distribution_id) {
        $distribution = $this->get_distribution($distribution_id);
        if (!$distribution) {
            throw new \Exception('Distribution not found');
        }

        $verification_results = [];
        foreach ($distribution['target_sites'] as $site_id) {
            switch_to_blog($site_id);
            
            try {
                $verification = $this->verify_site_distribution($distribution);
                $verification_results[$site_id] = [
                    'verified' => true,
                    'timestamp' => current_time('mysql')
                ];
            } catch (\Exception $e) {
                $verification_results[$site_id] = [
                    'verified' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            restore_current_blog();
        }

        $this->update_distribution_verification($distribution_id, $verification_results);
        return $verification_results;
    }

    public function rebalance_patterns() {
        $sites = get_sites(['fields' => 'ids']);
        $pattern_distribution = $this->analyze_pattern_distribution();

        $rebalancing_actions = $this->calculate_rebalancing_actions($pattern_distribution);
        
        foreach ($rebalancing_actions as $action) {
            try {
                switch ($action['type']) {
                    case 'redistribute':
                        $this->redistribute_pattern($action['pattern_hash'], $action['target_sites']);
                        break;
                    case 'replicate':
                        $this->replicate_pattern($action['pattern_hash'], $action['target_sites']);
                        break;
                    case 'remove':
                        $this->remove_pattern($action['pattern_hash'], $action['from_sites']);
                        break;
                }
            } catch (\Exception $e) {
                $this->alert_manager->trigger_alert('rebalancing_error', [
                    'action' => $action,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->record_rebalancing_metrics($rebalancing_actions);
    }

    private function calculate_site_suitability($metrics) {
        $weights = [
            'load_score' => 0.3,
            'reliability_score' => 0.3,
            'performance_score' => 0.2,
            'capacity_score' => 0.2
        ];

        $suitability = 0;
        foreach ($weights as $metric => $weight) {
            $suitability += ($metrics[$metric] ?? 0) * $weight;
        }

        return $suitability;
    }

    private function is_site_suitable($metrics) {
        return $metrics['load_score'] < 0.8 && 
               $metrics['reliability_score'] > 0.7 && 
               $metrics['performance_score'] > 0.6;
    }

    private function record_distribution_metrics($pattern_data, $results, $duration) {
        $this->metrics->record_metric('pattern_distribution', 1, [
            'pattern_hash' => $pattern_data['pattern_hash'],
            'successful_sites' => count($results['successful_sites']),
            'failed_sites' => count($results['failed_sites']),
            'duration' => $duration
        ]);

        $this->metrics->record_metric('pattern_distribution_time', $duration, [
            'pattern_hash' => $pattern_data['pattern_hash']
        ]);
    }

    private function handle_distribution_error(\Exception $e, $pattern_data) {
        $this->alert_manager->trigger_alert('distribution_error', [
            'pattern_hash' => $pattern_data['pattern_hash'],
            'error' => $e->getMessage()
        ]);

        $this->metrics->record_metric('distribution_errors', 1, [
            'error_type' => get_class($e),
            'pattern_hash' => $pattern_data['pattern_hash']
        ]);
    }

    public function get_distribution_stats() {
        return [
            'total_distributions' => $this->metrics->get_metric_sum('pattern_distribution'),
            'average_distribution_time' => $this->metrics->get_metric_average('pattern_distribution_time'),
            'error_rate' => $this->calculate_error_rate(),
            'site_distribution' => $this->get_site_distribution_stats()
        ];
    }

    public function handle_new_site($site, $args) {
        // Initialize pattern storage for new site
        switch_to_blog($site->blog_id);
        $this->initialize_pattern_storage();
        restore_current_blog();

        // Trigger rebalancing if needed
        if ($this->should_rebalance_on_new_site()) {
            wp_schedule_single_event(time() + 300, 'aps_rebalance_patterns');
        }
    }

    public function handle_site_deletion($site_id) {
        // Get patterns that need redistribution
        $patterns = $this->get_site_patterns($site_id);

        // Queue patterns for redistribution
        foreach ($patterns as $pattern) {
            wp_schedule_single_event(
                time() + rand(0, 300),
                'aps_distribute_pattern',
                [$pattern]
            );
        }
    }

    private function calculate_error_rate() {
        $total_distributions = $this->metrics->get_metric_sum('pattern_distribution');
        if (!$total_distributions) {
            return 0;
        }

        $errors = $this->metrics->get_metric_sum('distribution_errors');
        return ($errors / $total_distributions) * 100;
    }
}