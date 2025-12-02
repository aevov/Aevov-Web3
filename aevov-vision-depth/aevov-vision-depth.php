<?php
/**
 * Plugin Name: Aevov Vision Depth
 * Plugin URI: https://aevov.com/vision-depth
 * Description: Privacy-First Behavioral Intelligence System - Learn from user browser behavior while maintaining absolute privacy, powered by Ultimate Web Scraper
 * Version: 1.0.0
 * Author: Aevov Team
 * Author URI: https://aevov.com
 * License: MIT
 * Text Domain: aevov-vision-depth
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

namespace AevovVisionDepth;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AVD_VERSION', '1.0.0');
define('AVD_FILE', __FILE__);
define('AVD_PATH', plugin_dir_path(__FILE__));
define('AVD_URL', plugin_dir_url(__FILE__));
define('AVD_INCLUDES', AVD_PATH . 'includes/');
define('AVD_LIB', AVD_PATH . 'lib/');

/**
 * Main Vision Depth Plugin Class
 */
class Vision_Depth {

    /**
     * Singleton instance
     *
     * @var Vision_Depth
     */
    private static $instance = null;

    /**
     * Privacy Manager
     *
     * @var Privacy\Privacy_Manager
     */
    public $privacy;

    /**
     * Scraper Manager
     *
     * @var Scraper\Scraper_Manager
     */
    public $scraper;

    /**
     * Dashboard Manager
     *
     * @var Dashboard\Dashboard_Manager
     */
    public $dashboard;

    /**
     * Integration Manager
     *
     * @var Integrations\Integration_Manager
     */
    public $integrations;

    /**
     * Get singleton instance
     *
     * @return Vision_Depth
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
        $this->load_dependencies();
        $this->init_hooks();
        $this->init_components();
    }

    /**
     * Load plugin dependencies
     *
     * @return void
     */
    private function load_dependencies() {
        // Check if Ultimate Web Scraper is available
        if (!file_exists(AVD_LIB . 'ultimate_web_scraper_toolkit/web_browser.php')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                printf(
                    __('Vision Depth requires Ultimate Web Scraper Toolkit. Please download it from %s and place it in %s', 'aevov-vision-depth'),
                    '<a href="https://github.com/cubiclesoft/ultimate-web-scraper" target="_blank">GitHub</a>',
                    '<code>' . AVD_LIB . 'ultimate_web_scraper_toolkit/</code>'
                );
                echo '</p></div>';
            });
        }

        // Load Ultimate Web Scraper if available
        if (file_exists(AVD_LIB . 'ultimate_web_scraper_toolkit/support/web_browser.php')) {
            require_once AVD_LIB . 'ultimate_web_scraper_toolkit/support/web_browser.php';
            require_once AVD_LIB . 'ultimate_web_scraper_toolkit/support/tag_filter.php';
        }

        // Load core classes
        require_once AVD_INCLUDES . 'privacy/class-privacy-manager.php';
        require_once AVD_INCLUDES . 'privacy/class-consent-manager.php';
        require_once AVD_INCLUDES . 'privacy/class-encryption-manager.php';
        require_once AVD_INCLUDES . 'privacy/class-anonymization-manager.php';

        require_once AVD_INCLUDES . 'scraper/class-scraper-manager.php';
        require_once AVD_INCLUDES . 'scraper/class-rate-limiter.php';
        require_once AVD_INCLUDES . 'scraper/class-data-extractor.php';

        require_once AVD_INCLUDES . 'dashboard/class-dashboard-manager.php';
        require_once AVD_INCLUDES . 'dashboard/class-monitoring-widget.php';

        require_once AVD_INCLUDES . 'integrations/class-integration-manager.php';
        require_once AVD_INCLUDES . 'integrations/class-aps-integration.php';
        require_once AVD_INCLUDES . 'integrations/class-bloom-integration.php';
        require_once AVD_INCLUDES . 'integrations/class-aps-tools-integration.php';

        require_once AVD_INCLUDES . 'class-activator.php';
        require_once AVD_INCLUDES . 'class-database.php';
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks() {
        register_activation_hook(AVD_FILE, ['AevovVisionDepth\Activator', 'activate']);
        register_deactivation_hook(AVD_FILE, ['AevovVisionDepth\Activator', 'deactivate']);

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Cron jobs
        add_action('avd_auto_delete_old_data', [$this, 'auto_delete_old_data']);
        add_action('avd_process_scrape_queue', [$this, 'process_scrape_queue']);
    }

    /**
     * Initialize plugin components
     *
     * @return void
     */
    private function init_components() {
        $this->privacy = new Privacy\Privacy_Manager();
        $this->scraper = new Scraper\Scraper_Manager();
        $this->dashboard = new Dashboard\Dashboard_Manager();
        $this->integrations = new Integrations\Integration_Manager();
    }

    /**
     * Load plugin text domain
     *
     * @return void
     */
    public function load_textdomain() {
        load_plugin_textdomain('aevov-vision-depth', false, dirname(plugin_basename(AVD_FILE)) . '/languages');
    }

    /**
     * Initialize plugin
     *
     * @return void
     */
    public function init() {
        // Check dependencies
        if (!$this->check_dependencies()) {
            return;
        }

        // Initialize components
        $this->privacy->init();
        $this->scraper->init();
        $this->dashboard->init();
        $this->integrations->init();

        // Register custom post types if needed
        $this->register_post_types();

        // Schedule cron jobs if not already scheduled
        if (!wp_next_scheduled('avd_auto_delete_old_data')) {
            wp_schedule_event(time(), 'daily', 'avd_auto_delete_old_data');
        }

        if (!wp_next_scheduled('avd_process_scrape_queue')) {
            wp_schedule_event(time(), 'hourly', 'avd_process_scrape_queue');
        }
    }

    /**
     * Check plugin dependencies
     *
     * @return bool
     */
    private function check_dependencies() {
        $dependencies_met = true;

        // Check for AevovPatternSyncProtocol
        if (!class_exists('APS\Core\APS_Core')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>';
                _e('Vision Depth works best with Aevov Pattern Sync Protocol plugin installed.', 'aevov-vision-depth');
                echo '</p></div>';
            });
        }

        // Check for Bloom Pattern Recognition
        if (!class_exists('BLOOM_Pattern_System')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>';
                _e('Vision Depth works best with BLOOM Pattern Recognition plugin installed.', 'aevov-vision-depth');
                echo '</p></div>';
            });
        }

        return $dependencies_met;
    }

    /**
     * Register custom post types
     *
     * @return void
     */
    private function register_post_types() {
        // Register scrape jobs post type for queue management
        register_post_type('avd_scrape_job', [
            'labels' => [
                'name' => __('Scrape Jobs', 'aevov-vision-depth'),
                'singular_name' => __('Scrape Job', 'aevov-vision-depth'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'manage_options',
            ],
            'map_meta_cap' => true,
        ]);
    }

    /**
     * Add admin menu pages
     *
     * @return void
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Vision Depth', 'aevov-vision-depth'),
            __('Vision Depth', 'aevov-vision-depth'),
            'manage_options',
            'vision-depth',
            [$this->dashboard, 'render_main_page'],
            'dashicons-visibility',
            31
        );

        add_submenu_page(
            'vision-depth',
            __('Privacy Settings', 'aevov-vision-depth'),
            __('Privacy', 'aevov-vision-depth'),
            'manage_options',
            'vision-depth-privacy',
            [$this->privacy, 'render_settings_page']
        );

        add_submenu_page(
            'vision-depth',
            __('Scraper Settings', 'aevov-vision-depth'),
            __('Scraper', 'aevov-vision-depth'),
            'manage_options',
            'vision-depth-scraper',
            [$this->scraper, 'render_settings_page']
        );

        add_submenu_page(
            'vision-depth',
            __('Integrations', 'aevov-vision-depth'),
            __('Integrations', 'aevov-vision-depth'),
            'manage_options',
            'vision-depth-integrations',
            [$this->integrations, 'render_settings_page']
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'vision-depth') === false) {
            return;
        }

        wp_enqueue_style('avd-admin', AVD_URL . 'assets/css/admin.css', [], AVD_VERSION);
        wp_enqueue_script('avd-admin', AVD_URL . 'assets/js/admin.js', ['jquery'], AVD_VERSION, true);

        wp_localize_script('avd-admin', 'avdAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('avd_admin'),
            'i18n' => [
                'loading' => __('Loading...', 'aevov-vision-depth'),
                'error' => __('An error occurred', 'aevov-vision-depth'),
                'success' => __('Success', 'aevov-vision-depth'),
            ],
        ]);
    }

    /**
     * Enqueue frontend assets
     *
     * @return void
     */
    public function enqueue_frontend_assets() {
        // Only load if user has consented
        if (!$this->privacy->user_has_consented()) {
            return;
        }

        wp_enqueue_style('avd-frontend', AVD_URL . 'assets/css/frontend.css', [], AVD_VERSION);
        wp_enqueue_script('avd-frontend', AVD_URL . 'assets/js/frontend.js', ['jquery'], AVD_VERSION, true);

        wp_localize_script('avd-frontend', 'avdFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('avd_frontend_' . get_current_user_id()),
            'userId' => get_current_user_id(),
            'privacyMode' => get_user_meta(get_current_user_id(), 'avd_privacy_mode', true) ?: 'balanced',
            'rateLimits' => [
                'perSecond' => 10,
                'perMinute' => 100,
            ],
        ]);
    }

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_rest_routes() {
        register_rest_route('vision-depth/v1', '/scrape', [
            'methods' => 'POST',
            'callback' => [$this->scraper, 'rest_scrape_url'],
            'permission_callback' => [$this->privacy, 'rest_permission_check'],
        ]);

        register_rest_route('vision-depth/v1', '/consent', [
            'methods' => 'POST',
            'callback' => [$this->privacy, 'rest_update_consent'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('vision-depth/v1', '/data', [
            'methods' => 'GET',
            'callback' => [$this->privacy, 'rest_get_user_data'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route('vision-depth/v1', '/data', [
            'methods' => 'DELETE',
            'callback' => [$this->privacy, 'rest_delete_user_data'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    /**
     * Auto-delete old data (GDPR compliance)
     *
     * @return void
     */
    public function auto_delete_old_data() {
        global $wpdb;

        $retention_days = get_option('avd_data_retention_days', 7);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        $table = $wpdb->prefix . 'avd_scraped_data';
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            $cutoff_date
        ));

        if ($deleted > 0) {
            error_log("[Vision Depth] Auto-deleted {$deleted} records older than {$retention_days} days");
        }
    }

    /**
     * Process scrape queue
     *
     * @return void
     */
    public function process_scrape_queue() {
        $jobs = get_posts([
            'post_type' => 'avd_scrape_job',
            'post_status' => 'pending',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'ASC',
        ]);

        foreach ($jobs as $job) {
            $this->scraper->process_job($job->ID);
        }
    }
}

/**
 * Initialize the plugin
 *
 * @return Vision_Depth
 */
function vision_depth() {
    return Vision_Depth::instance();
}

// Initialize
vision_depth();
