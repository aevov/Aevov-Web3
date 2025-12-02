<?php

namespace APS\Core;

use APS\DB\MetricsDB;
use APS\DB\APS_Reward_DB; // New import
use APS\Monitoring\AlertManager;
use APS\Network\NetworkMonitor;
use APS\Queue\QueueManager;
use APS\Comparison\APS_Comparator;
use APS\Integration\BloomIntegration;
use APS\Frontend\PublicFrontend;
use APS\Admin\APS_Admin;
use Aevov\Decentralized\RewardSystem; // New import

/**
 * Core plugin functionality and bootstrapping
 */
class APS_Core {
    private static $instance = null;
    private $initialized = false;
    private $bloom_instance = null;
    private $logger;

    // Component instances
    private $loader;
    private $i18n;
    private $admin;
    private $public;
    private $comparator;
    private $integration;
    private $system_monitor;
    private $alert_manager;
    private $network_monitor;
    private $queue_manager;
    private $reward_system; // New property

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->logger = \APS\Core\Logger::get_instance();
        // Initialize only after plugins are loaded
        if (function_exists('add_action')) {
            add_action('plugins_loaded', [$this, 'maybe_initialize'], 1);
        }
    }

    /**
     * Maybe initialize the core if dependencies are met
     */
    public function maybe_initialize() {
        if ($this->initialized) {
            $this->logger->debug('APS Core already initialized, skipping');
            return;
        }
        
        $this->logger->info('Attempting to initialize APS Core');
        
        if ($this->check_dependencies()) {
            $this->initialized = true;
            $this->init_core();
            $this->logger->info('APS Core initialized successfully');
        } else {
            $this->logger->warning('APS Core dependencies not met, initialization deferred');
        }
    }

    /**
     * Initialize core functionality
     */
    private function init_core() {
        $this->logger->info('Initializing APS Core components');
        try {
            // Dependencies are now loaded via Composer autoloader
            $this->init_components();
            $this->setup_hooks();
            
            if (function_exists('do_action')) {
                do_action('aps_core_initialized');
                $this->logger->info('Action "aps_core_initialized" triggered');
            }
            $this->logger->info('APS Core components initialized successfully');
        } catch (\Exception $e) {
            $this->logger->error('Error during APS Core initialization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->handle_error($e);
        }
    }

    /**
     * Initialize core components
     */
    private function init_components() {
        $this->logger->info('Initializing core components');
        try {
            $this->loader = new \APS\Core\Loader();
            $this->i18n = new \APS\Core\APS_i18n();
 
            if (class_exists('\APS\Admin\APS_Admin')) {
                $this->admin = new \APS\Admin\APS_Admin();
                $this->logger->debug('Admin component initialized');
            } else {
                $this->logger->warning('Admin component class not found');
            }
 
            if (class_exists('\APS\Frontend\PublicFrontend')) {
                $this->public = new \APS\Frontend\PublicFrontend();
                $this->logger->debug('Public frontend component initialized');
            } else {
                $this->logger->warning('Public frontend component class not found');
            }
 
            if (class_exists('\APS\Comparison\APS_Comparator')) {
                $this->comparator = new \APS\Comparison\APS_Comparator();
                $this->logger->debug('Comparator component initialized');
            } else {
                $this->logger->warning('Comparator component class not found');
            }
 
            if (class_exists('\APS\Integration\BloomIntegration')) {
                $this->integration = new \APS\Integration\BloomIntegration(); // Removed $this->bloom_instance as it's handled internally
                $this->logger->debug('Bloom Integration component initialized');
            } else {
                $this->logger->warning('Bloom Integration component class not found');
            }
 
            if (class_exists('\APS\Monitoring\SystemMonitor')) {
                $this->system_monitor = new \APS\Monitoring\SystemMonitor();
                $this->logger->debug('System Monitor component initialized');
            } else {
                $this->logger->warning('System Monitor component class not found');
            }
 
            if (class_exists('\APS\Monitoring\AlertManager')) {
                $this->alert_manager = new \APS\Monitoring\AlertManager();
                $this->logger->debug('Alert Manager component initialized');
            } else {
                $this->logger->warning('Alert Manager component class not found');
            }
 
            if (class_exists('\APS\Network\NetworkMonitor')) {
                $this->network_monitor = new \APS\Network\NetworkMonitor();
                $this->logger->debug('Network Monitor component initialized');
            } else {
                $this->logger->warning('Network Monitor component class not found');
            }
 
            if (class_exists('\APS\Queue\QueueManager')) {
                $this->queue_manager = new \APS\Queue\QueueManager();
                $this->logger->debug('Queue Manager component initialized');
            } else {
                $this->logger->warning('Queue Manager component class not found');
            }

            // Initialize RewardSystem
            if (class_exists('\APS\DB\APS_Reward_DB') && class_exists('\Aevov\Decentralized\RewardSystem')) {
                $reward_db = new \APS\DB\APS_Reward_DB();
                $this->reward_system = new \Aevov\Decentralized\RewardSystem($reward_db);
                $this->logger->debug('Reward System component initialized');
            } else {
                $this->logger->warning('Reward System component classes not found');
            }

            $this->logger->info('All core components initialized');
        } catch (\Exception $e) {
            $this->logger->error('Error initializing core components', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Set up WordPress hooks
     */
    private function setup_hooks() {
        $this->logger->info('Setting up WordPress hooks');
        try {
            // Core hooks
            $this->loader->add_action('init', $this->i18n, 'load_plugin_textdomain');
            $this->logger->debug('Core textdomain hook added');
 
            // Admin hooks
            if ($this->admin && function_exists('is_admin') && is_admin()) {
                $this->loader->add_action('admin_init', $this->admin, 'init_settings');
                $this->loader->add_action('admin_enqueue_scripts', $this->admin, 'enqueue_assets');
                $this->logger->debug('Admin hooks added');
            }
 
            // BLOOM integration hooks
            if ($this->integration && $this->bloom_instance) {
                $this->loader->add_action('bloom_pattern_processed', $this->integration, 'handle_bloom_pattern'); // Corrected method name
                $this->loader->add_filter('bloom_pre_pattern_process', $this->integration, 'prepare_pattern_data');
                $this->logger->debug('BLOOM integration hooks added');
            }
 
            // Monitoring hooks
            if ($this->system_monitor) {
                $this->loader->add_action('init', $this->system_monitor, 'schedule_health_checks');
                $this->logger->debug('System monitor hooks added');
            }
 
            if ($this->queue_manager) {
                $this->loader->add_action('init', $this->queue_manager, 'schedule_processor');
                $this->logger->debug('Queue manager hooks added');
            }
            
            if ($this->network_monitor) {
                $this->loader->add_action('init', $this->network_monitor, 'schedule_network_sync'); // New hook
                $this->logger->debug('Network monitor hooks added');
            }
 
            // Run the loader
            $this->loader->run();
            $this->logger->info('All WordPress hooks set up');
        } catch (\Exception $e) {
            $this->logger->error('Error setting up WordPress hooks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Check if all dependencies are available
     */
    private function check_dependencies() {
        $this->logger->info('Checking plugin dependencies');
        $bloom_exists = class_exists('BLOOM_Pattern_System') ||
                       function_exists('BLOOM') ||
                       defined('BLOOM_VERSION');
 
        if (!$bloom_exists) {
            $this->logger->error('BLOOM Pattern Recognition System is not active or installed.');
            if (function_exists('add_action')) {
                add_action('admin_notices', function() {
                    $message = function_exists('__') ? __('APS Core requires the BLOOM Pattern Recognition System plugin to be installed and activated.', 'aps') : 'APS Core requires the BLOOM Pattern Recognition System plugin to be installed and activated.';
                    $escaped_message = function_exists('esc_html') ? esc_html($message) : htmlspecialchars($message);
                    echo '<div class="notice notice-error"><p>' . $escaped_message . '</p></div>';
                });
            }
            return false;
        }
 
        // Store BLOOM instance if available
        if (function_exists('BLOOM')) {
            $this->bloom_instance = BLOOM();
            $this->logger->info('BLOOM instance retrieved');
        } else {
            $this->logger->warning('BLOOM function not found, BLOOM instance not set');
        }
 
        $this->logger->info('All plugin dependencies met');
        return true;
    }

    /**
     * Handle initialization errors
     */
    private function handle_error(\Exception $e) {
        $aps_settings = get_option('aps_settings', []);
        $debug_mode = isset($aps_settings['debug_mode']) && $aps_settings['debug_mode'];

        if ((defined('WP_DEBUG') && WP_DEBUG) || $debug_mode) {
            error_log('APS Core Error: ' . $e->getMessage());
        }

        if (function_exists('add_action')) {
            add_action('admin_notices', function() use ($e) {
                $message = function_exists('__') ? sprintf(__('APS Core Error: %s', 'aps'), $e->getMessage()) : 'APS Core Error: ' . $e->getMessage();
                $escaped_message = function_exists('esc_html') ? esc_html($message) : htmlspecialchars($message);
                echo '<div class="notice notice-error"><p>' . $escaped_message . '</p></div>';
            });
        }
    }

    public function get_bloom_instance() {
        return $this->bloom_instance;
    }

    public function is_bloom_active() {
        return !is_null($this->bloom_instance);
    }

    /**
     * Get component instances
     */
    public function get_loader() {
        return $this->loader;
    }

    public function get_admin() {
        return $this->admin;
    }

    public function get_public() {
        return $this->public;
    }

    public function get_integration() {
        return $this->integration;
    }

    public function get_system_monitor() {
        return $this->system_monitor;
    }

    public function get_network_monitor() {
        return $this->network_monitor;
    }
 
    public function get_queue_manager() {
        return $this->queue_manager;
    }

    public function get_reward_system() {
        return $this->reward_system;
    }
 
    private function __clone() {
        $this->logger->warning('Attempted to clone APS_Core singleton instance');
    }
 
    public function __wakeup() {
        $this->logger->error('Attempted to unserialize APS_Core singleton instance');
        throw new \Exception("Cannot unserialize singleton");
    }
}
