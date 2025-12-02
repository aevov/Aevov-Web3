<?php
/**
 * Plugin Name: BLOOM Pattern Recognition System
 * Description: Distributed pattern recognition system for BLOOM tensor chunks using WordPress Multisite
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: bloom-pattern-system
 * Network: true
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define minimum PHP version
define('BLOOM_MIN_PHP_VERSION', '7.4');

// Define constants first
if (!defined('BLOOM_VERSION')) {
    define('BLOOM_VERSION', '1.0.0');
}
if (!defined('BLOOM_FILE')) {
    define('BLOOM_FILE', __FILE__);
}
if (!defined('BLOOM_PATH')) {
    define('BLOOM_PATH', plugin_dir_path(__FILE__));
}
if (!defined('BLOOM_URL')) {
    define('BLOOM_URL', plugin_dir_url(__FILE__));
}
if (!defined('BLOOM_CHUNK_SIZE')) {
    define('BLOOM_CHUNK_SIZE', 7 * 1024 * 1024);
}
if (!defined('BLOOM_LOCAL_TENSOR_PATH')) {
    $upload_dir = wp_get_upload_dir();
    define('BLOOM_LOCAL_TENSOR_PATH', $upload_dir['basedir'] . '/bloom-tensors');
}

// Include Composer autoloader
require_once BLOOM_PATH . 'vendor/autoload.php';

// Include debug logging (if still needed, consider integrating into autoloader or removing)
require_once BLOOM_PATH . 'debug-log.php';

// Core plugin class
/**
 * Core plugin class for the BLOOM Pattern Recognition System.
 *
 * @since 1.0.0
 */
final class BLOOM_Pattern_System {
    /**
     * The single instance of the class.
     *
     * @since 1.0.0
     * @var   BLOOM_Pattern_System
     */
    private static $instance = null;

    /**
     * The plugin slug.
     *
     * @since 1.0.0
     * @var   string
     */
    private $plugin_slug = 'bloom-pattern-system';

    /**
     * The core plugin object.
     *
     * @since 1.0.0
     * @var   object
     */
    private $core;

    /**
     * The APS integration object.
     *
     * @since 1.0.0
     * @var   object
     */
    private $aps_integration;

    /**
     * The error handler object.
     *
     * @since 1.0.0
     * @var   \BLOOM\Utilities\ErrorHandler
     */
    public $error_handler;
    
    /**
     * Get the singleton instance of the class.
     *
     * @since  1.0.0
     * @return BLOOM_Pattern_System
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
        $this->load_dependencies();

        $this->error_handler = new \BLOOM\Utilities\ErrorHandler();

        // Schedule cron job for clearing old error logs
        if (!wp_next_scheduled('bloom_clear_old_error_logs')) {
            wp_schedule_event(time(), 'daily', 'bloom_clear_old_error_logs');
        }
        add_action('bloom_clear_old_error_logs', [$this->error_handler, 'clear_old_errors']);
    }

    /**
     * Define constants.
     *
     * @since 1.0.0
     */
    private function define_constants() {
        // Constants already defined above to avoid issues with require_once
        // This method kept for compatibility
    }

    /**
     * Initialize hooks.
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        register_activation_hook(BLOOM_FILE, function() {
            $activator = new \BLOOM\Core\PluginActivator();
            $activator->activate();
        });
        
        register_deactivation_hook(BLOOM_FILE, function() {
            $activator = new \BLOOM\Core\PluginActivator();
            $activator->deactivate();
        });

        add_action('network_admin_menu', [$this, 'add_network_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('init', [$this, 'load_textdomain']); // Add this line
    }

    /**
     * Load textdomain.
     *
     * @since 1.0.0
     */
    public function load_textdomain() {
        load_plugin_textdomain('bloom-pattern-system', false, BLOOM_PATH . 'languages');
    }

    /**
     * Load dependencies.
     *
     * @since 1.0.0
     */
    private function load_dependencies() {
        if (!function_exists('bloom')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo esc_html__('The BLOOM Pattern Recognition System plugin requires the main BLOOM plugin to be installed and activated.', 'bloom-pattern-system');
                echo '</p></div>';
            });
            return;
        }

        // Add diagnostic logging to validate class loading
        $required_classes = [
            'BLOOM\Models\PatternModel',
            'BLOOM\Models\ChunkModel',
            'BLOOM\Models\TensorModel',
            'BLOOM\Processing\TensorProcessor',
            'BLOOM\Monitoring\MetricsCollector',
            'BLOOM\Monitoring\SystemMonitor',
            'BLOOM\Utilities\DataValidator',
            'BLOOM\Utilities\ErrorHandler'
        ];
        
        $missing_classes = [];
        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                $missing_classes[] = $class;
            }
        }
        
        if (!empty($missing_classes)) {
            $this->error_handler->log_error('BLOOM: Missing required classes: ' . implode(', ', $missing_classes));
            wp_die('BLOOM Plugin Error: Required classes not found. Please check plugin installation.');
        }
        
        $this->error_handler->log_debug('BLOOM: All required classes loaded successfully');
        
        $this->core = \BLOOM\Core::get_instance();
        $this->aps_integration = new \BLOOM\Integration\APSIntegration();
        
        $this->error_handler->log_debug('BLOOM: Plugin dependencies loaded successfully');
    }

    /**
     * Add network admin menu.
     *
     * @since 1.0.0
     */
    public function add_network_menu() {
        // Add main menu
        add_menu_page(
            __('BLOOM Pattern System', 'bloom-pattern-system'),
            __('BLOOM Patterns', 'bloom-pattern-system'),
            'manage_network_options',
            $this->plugin_slug,
            [$this, 'render_main_page'],
            'dashicons-visibility',
            30
        );

        // Add submenus
        add_submenu_page(
            $this->plugin_slug,
            __('Dashboard', 'bloom-pattern-system'),
            __('Dashboard', 'bloom-pattern-system'),
            'manage_network_options',
            $this->plugin_slug,
            [$this, 'render_main_page']
        );

        add_submenu_page(
            $this->plugin_slug,
            __('Pattern Management', 'bloom-pattern-system'),
            __('Patterns', 'bloom-pattern-system'),
            'manage_network_options',
            $this->plugin_slug . '-patterns',
            [$this, 'render_patterns_page']
        );

        add_submenu_page(
            $this->plugin_slug,
            __('Tensor Upload', 'bloom-pattern-system'),
            __('Upload Tensors', 'bloom-pattern-system'),
            'manage_network_options',
            $this->plugin_slug . '-upload',
            [$this, 'render_upload_page']
        );

        add_submenu_page(
            $this->plugin_slug,
            __('Settings', 'bloom-pattern-system'),
            __('Settings', 'bloom-pattern-system'),
            'manage_network_options',
            $this->plugin_slug . '-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @since 1.0.0
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, $this->plugin_slug) === false) {
            return;
        }
 
        // Enqueue admin styles
        wp_enqueue_style(
            'bloom-admin',
            BLOOM_URL . 'assets/css/admin.css',
            [],
            BLOOM_VERSION
        );
 
        wp_localize_script('bloom-admin', 'bloomAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('bloom/v1'), // Add REST API base URL
            'nonce' => wp_create_nonce('wp_rest'), // Use wp_rest nonce for REST API calls
            'i18n' => [
                'confirm' => __('Are you sure?', 'bloom-pattern-system'),
                'success' => __('Operation successful', 'bloom-pattern-system'),
                'error' => __('An error occurred', 'bloom-pattern-system'),
                'view' => __('View', 'bloom-pattern-system'), // Add new i18n strings
                'analyze' => __('Analyze', 'bloom-pattern-system'),
                'confirmAnalyze' => __('Are you sure you want to analyze this pattern?', 'bloom-pattern-system'),
                'analyzeError' => __('Error analyzing pattern.', 'bloom-pattern-system')
            ]
        ]);

        // Enqueue upload.js only on the upload page
        if ($hook === 'toplevel_page_bloom-pattern-system-upload') {
            wp_enqueue_script(
                'bloom-upload',
                BLOOM_URL . 'assets/js/admin/upload.js',
                ['jquery'],
                BLOOM_VERSION,
                true
            );
        }
    }
 
    /**
     * Render the main page.
     *
     * @since 1.0.0
     */
    public function render_main_page() {
        include BLOOM_PATH . 'admin/views/dashboard.php';
    }
 
    /**
     * Render the patterns page.
     *
     * @since 1.0.0
     */
    public function render_patterns_page() {
        include BLOOM_PATH . 'admin/views/patterns.php';
    }
 
    /**
     * Render the upload page.
     *
     * @since 1.0.0
     */
    public function render_upload_page() {
        include BLOOM_PATH . 'admin/views/upload.php';
    }
 
    /**
     * Render the settings page.
     *
     * @since 1.0.0
     */
    public function render_settings_page() {
        include BLOOM_PATH . 'admin/views/settings.php';
    }

    /**
     * Get the error handler instance.
     *
     * @since  1.0.0
     * @return \BLOOM\Utilities\ErrorHandler
     */
    public function get_error_handler() {
        return $this->error_handler;
    }
 
}

// Initialize plugin
function bloom() {
    return BLOOM_Pattern_System::instance();
}

// Start the plugin
add_action('plugins_loaded', 'bloom');