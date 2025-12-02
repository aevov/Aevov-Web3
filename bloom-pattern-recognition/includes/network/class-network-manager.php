<?php
namespace BLOOM\Network;

use BLOOM\Models\PatternModel;
use BLOOM\Models\TensorModel;
use BLOOM\Network\MessageQueue;

/**
 * Network Management for BLOOM Pattern Recognition across multisite
 */
class NetworkManager {
    private $sync_manager;
    private $message_queue;
    private $sites = [];
    private $pattern_model;
    private $tensor_model;
    private $sync_interval = 300; // 5 minutes
    
    public function __construct() {
        // Conditional instantiation for dependencies
        if (class_exists('BLOOM\Network\MessageQueue')) {
            $this->message_queue = new MessageQueue();
        }
        
        $this->pattern_model = new PatternModel();
        $this->tensor_model = new TensorModel();
        $this->init_network();
        $this->setup_hooks();
    }
    
    public function init_network() {
        // Only initialize if WordPress multisite functions are available
        if (!function_exists('is_multisite') || !is_multisite()) {
            return;
        }
        
        if (function_exists('get_sites')) {
            $this->sites = get_sites(['fields' => 'ids']);
            $this->build_network_topology();
            $this->setup_connections();
        }
    }
    
    private function build_network_topology() {
        foreach ($this->sites as $site_id) {
            $this->register_site($site_id);
        }
    }

    private function setup_connections() {
        // Setup inter-site communication channels
        foreach ($this->sites as $site_id) {
            $this->establish_connection($site_id);
        }
    }

    private function register_site($site_id) {
        if (!function_exists('switch_to_blog')) {
            return;
        }
        
        switch_to_blog($site_id);
        
        $site_info = [
            'id' => $site_id,
            'url' => function_exists('get_site_url') ? get_site_url() : '',
            'name' => function_exists('get_bloginfo') ? get_bloginfo('name') : '',
            'status' => 'active',
            'last_sync' => function_exists('get_option') ? get_option('bloom_last_sync', 0) : 0,
            'pattern_count' => $this->get_site_pattern_count(),
            'capabilities' => $this->get_site_capabilities()
        ];
        
        $this->sites[$site_id] = $site_info;
        
        if (function_exists('restore_current_blog')) {
            restore_current_blog();
        }
    }

    private function establish_connection($site_id) {
        if (!function_exists('switch_to_blog')) {
            return;
        }
        
        // Test connection to site
        switch_to_blog($site_id);
        
        $connection_test = $this->test_site_connection();
        if (function_exists('update_option')) {
            if ($connection_test) {
                update_option('bloom_network_status', 'connected');
            } else {
                update_option('bloom_network_status', 'disconnected');
            }
        }
        
        if (function_exists('restore_current_blog')) {
            restore_current_blog();
        }
    }

    private function test_site_connection() {
        // Simple connection test - check if BLOOM is active
        return class_exists('BLOOM_Pattern_System');
    }

    private function get_site_pattern_count() {
        return $this->pattern_model->get_pattern_statistics();
    }

    private function get_site_capabilities() {
        return [
            'tensor_processing' => class_exists('BLOOM\Processing\TensorProcessor'),
            'pattern_analysis' => class_exists('BLOOM\Models\PatternModel'),
            'network_sync' => true,
            'storage_capacity' => $this->get_storage_capacity()
        ];
    }

    private function get_storage_capacity() {
        // Estimate available storage capacity
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            $free_space = disk_free_space($upload_dir['basedir']);
            return $free_space ? $free_space : 0;
        }
        
        // Fallback for non-WordPress environments
        return 0;
    }

    public function sync_patterns_across_network() {
        if (!is_multisite()) {
            return false;
        }

        $current_site = get_current_blog_id();
        $sync_results = [];

        foreach ($this->sites as $site_id) {
            if ($site_id == $current_site) {
                continue;
            }

            try {
                $result = $this->sync_with_site($site_id);
                $sync_results[$site_id] = $result;
            } catch (\Exception $e) {
                $sync_results[$site_id] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $sync_results;
    }

    private function sync_with_site($target_site_id) {
        $current_site = get_current_blog_id();
        
        // Get patterns from current site
        $local_patterns = $this->pattern_model->get_patterns_by_site($current_site, 100);
        
        if (empty($local_patterns)) {
            return ['success' => true, 'patterns_synced' => 0];
        }

        switch_to_blog($target_site_id);
        
        $synced_count = 0;
        $target_pattern_model = new PatternModel();
        
        foreach ($local_patterns as $pattern) {
            // Check if pattern already exists on target site
            $existing = $target_pattern_model->get_by_hash($pattern['pattern_hash']);
            
            if (!$existing) {
                // Create pattern on target site
                $pattern['site_id'] = $target_site_id; // Update site_id for target
                $result = $target_pattern_model->create($pattern);
                
                if ($result) {
                    $synced_count++;
                }
            }
        }
        
        restore_current_blog();
        
        return [
            'success' => true,
            'patterns_synced' => $synced_count,
            'total_patterns' => count($local_patterns)
        ];
    }

    public function distribute_pattern($pattern_data, $target_sites = null) {
        if (!$target_sites) {
            $target_sites = $this->get_optimal_distribution_sites($pattern_data);
        }

        $distribution_results = [];

        foreach ($target_sites as $site_id) {
            try {
                $result = $this->send_pattern_to_site($pattern_data, $site_id);
                $distribution_results[$site_id] = $result;
            } catch (\Exception $e) {
                $distribution_results[$site_id] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $distribution_results;
    }

    private function get_optimal_distribution_sites($pattern_data) {
        // Simple load balancing - distribute to sites with lowest pattern count
        $site_loads = [];
        
        foreach ($this->sites as $site_id => $site_info) {
            $site_loads[$site_id] = count($site_info['pattern_count'] ?? []);
        }
        
        asort($site_loads);
        
        // Return top 3 sites with lowest load
        return array_slice(array_keys($site_loads), 0, 3);
    }

    private function send_pattern_to_site($pattern_data, $site_id) {
        switch_to_blog($site_id);
        
        $pattern_model = new PatternModel();
        $pattern_data['site_id'] = $site_id;
        
        $result = $pattern_model->create($pattern_data);
        
        restore_current_blog();
        
        return [
            'success' => (bool)$result,
            'pattern_id' => $result
        ];
    }

    private function setup_hooks() {
        // Only setup hooks if WordPress functions are available
        if (function_exists('add_action')) {
            // Schedule network sync
            add_action('bloom_sync_network', [$this, 'sync_patterns_across_network']);
            
            // Handle pattern distribution requests
            add_action('bloom_distribute_pattern', [$this, 'handle_pattern_distribution'], 10, 2);
            
            // Network status monitoring
            add_action('bloom_network_health_check', [$this, 'perform_health_check']);
        }
    }

    public function handle_pattern_distribution($pattern_data, $target_sites) {
        return $this->distribute_pattern($pattern_data, $target_sites);
    }

    public function perform_health_check() {
        $health_status = [];
        
        foreach ($this->sites as $site_id => $site_info) {
            switch_to_blog($site_id);
            
            $health_status[$site_id] = [
                'status' => get_option('bloom_network_status', 'unknown'),
                'last_sync' => get_option('bloom_last_sync', 0),
                'pattern_count' => $this->pattern_model->get_pattern_statistics(),
                'storage_available' => $this->get_storage_capacity(),
                'timestamp' => time()
            ];
            
            restore_current_blog();
        }
        
        update_site_option(get_current_network_id(), 'bloom_network_health', $health_status);
        
        return $health_status;
    }

    public function get_network_status() {
        return get_site_option(get_current_network_id(), 'bloom_network_health', []);
    }
    
    private function get_message_queue() {
        if (!$this->message_queue && class_exists('BLOOM\Network\MessageQueue')) {
            $this->message_queue = new MessageQueue();
        }
        return $this->message_queue;
    }
}

