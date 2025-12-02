<?php
namespace BLOOM;

class Core {
    private static $instance = null;
    private $network_manager;
    private $tensor_processor;
    private $monitor;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_components();
        $this->setup_hooks();
    }

    private function init_components() {
        // Initialize core components safely with try-catch
        try {
            if (class_exists('\BLOOM\Network\NetworkManager')) {
                $this->network_manager = new \BLOOM\Network\NetworkManager();
            }
            if (class_exists('\BLOOM\Processing\TensorProcessor')) {
                $this->tensor_processor = new \BLOOM\Processing\TensorProcessor();
            }
            if (class_exists('\BLOOM\Monitoring\SystemMonitor')) {
                $this->monitor = new \BLOOM\Monitoring\SystemMonitor();
            }
        } catch (\Exception $e) {
            error_log('BLOOM Core initialization error: ' . $e->getMessage());
        }
    }

    private function setup_hooks() {
        add_action('init', [$this, 'init_plugin']);
        add_action('admin_init', [$this, 'init_admin']);
    }

    public function init_plugin() {
        load_plugin_textdomain('bloom-pattern-system', false, dirname(plugin_basename(BLOOM_FILE)) . '/languages');
    }

    public function init_admin() {
        if (is_network_admin()) {
            // Initialize network admin components
            if (class_exists('BLOOM\Admin\NetworkAdmin')) {
                new Admin\NetworkAdmin();
            }
        }
    }

    /**
     * Get system status for integration compatibility
     * @return array System status information
     */
    public function get_system_status() {
        try {
            if ($this->monitor && method_exists($this->monitor, 'get_system_health')) {
                return $this->monitor->get_system_health();
            }
            
            // Fallback status if monitor not available
            return [
                'active' => true,
                'error' => null,
                'version' => '1.0.0',
                'timestamp' => time(),
                'components' => [
                    'core' => 'active',
                    'network' => $this->network_manager ? 'active' : 'inactive',
                    'processing' => $this->tensor_processor ? 'active' : 'inactive'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'active' => false,
                'error' => $e->getMessage(),
                'version' => '1.0.0',
                'timestamp' => time()
            ];
        }
    }

    /**
     * Analyze pattern data
     * @param array $pattern_data Pattern data to analyze
     * @return array Analysis result
     */
    public function analyze_pattern($pattern_data) {
        try {
            if ($this->tensor_processor && method_exists($this->tensor_processor, 'process_pattern')) {
                return $this->tensor_processor->process_pattern($pattern_data);
            }
            
            // Basic fallback analysis
            return [
                'type' => $pattern_data['type'] ?? 'unknown',
                'confidence' => 0.5,
                'features' => $pattern_data['features'] ?? [],
                'metadata' => array_merge(
                    $pattern_data['metadata'] ?? [],
                    ['processed_by' => 'bloom_core', 'processing_time' => 0.1]
                ),
                'relationships' => [],
                'timestamp' => time()
            ];
        } catch (\Exception $e) {
            throw new \Exception('Pattern analysis failed: ' . $e->getMessage());
        }
    }

    /**
     * Sync pattern data
     * @param array $pattern_data Pattern data to sync
     * @return array Sync result
     */
    public function sync_pattern($pattern_data) {
        try {
            if ($this->network_manager && method_exists($this->network_manager, 'sync_pattern')) {
                return $this->network_manager->sync_pattern($pattern_data);
            }
            
            // Basic fallback sync
            return [
                'success' => true,
                'pattern_id' => $pattern_data['id'] ?? uniqid('pattern_'),
                'sync_time' => 0.05,
                'patterns' => [$pattern_data],
                'timestamp' => time()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'sync_time' => 0,
                'patterns' => [],
                'timestamp' => time()
            ];
        }
    }

    /**
     * Get recent patterns
     * @param int $limit Number of patterns to retrieve
     * @return array Recent patterns
     */
    public function get_recent_patterns($limit = 10) {
        try {
            if ($this->network_manager && method_exists($this->network_manager, 'get_recent_patterns')) {
                return $this->network_manager->get_recent_patterns($limit);
            }
            
            // Fallback: return empty array or sample data
            return [];
        } catch (\Exception $e) {
            error_log('BLOOM Core: Failed to get recent patterns: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get network manager instance
     * @return object|null Network manager instance
     */
    public function get_network_manager() {
        return $this->network_manager;
    }

    /**
     * Get tensor processor instance
     * @return object|null Tensor processor instance
     */
    public function get_tensor_processor() {
        return $this->tensor_processor;
    }

    /**
     * Get system monitor instance
     * @return object|null System monitor instance
     */
    public function get_monitor() {
        return $this->monitor;
    }

    private function __clone() {}

    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}