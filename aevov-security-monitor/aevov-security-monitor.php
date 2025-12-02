<?php
/**
 * Plugin Name: Aevov Security Monitor (Ghost-PHP)
 * Plugin URI: https://aevov.com/plugins/security-monitor
 * Description: Advanced security monitoring system inspired by Ghost, detecting process injection, memory manipulation, and malware patterns. Integrated with AevIP for distributed security scanning.
 * Version: 1.0.0
 * Author: Aevov Security Team
 * Author URI: https://aevov.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: aevov-security-monitor
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 *
 * Based on Ghost (https://github.com/pandaadir05/ghost) - Process Injection Detection
 * Rewritten in PHP for WordPress/Aevov ecosystem integration
 *
 * @package AevovSecurityMonitor
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Define plugin constants
 */
define('AEVOV_SECURITY_MONITOR_VERSION', '1.0.0');
define('AEVOV_SECURITY_MONITOR_PLUGIN_FILE', __FILE__);
define('AEVOV_SECURITY_MONITOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AEVOV_SECURITY_MONITOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AEVOV_SECURITY_MONITOR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Aevov Security Monitor Plugin Class
 *
 * Provides Ghost-inspired security monitoring capabilities:
 * - Process monitoring (limited in PHP context - focuses on web processes)
 * - File integrity monitoring
 * - Malware pattern detection
 * - YARA-like signature matching
 * - Memory analysis (PHP memory inspection)
 * - Code injection detection
 * - AevIP integration for distributed scanning
 */
class AevovSecurityMonitor {

    /**
     * Plugin instance
     *
     * @var AevovSecurityMonitor
     */
    private static $instance = null;

    /**
     * Process scanner instance
     *
     * @var ProcessScanner
     */
    private $process_scanner;

    /**
     * File scanner instance
     *
     * @var FileScanner
     */
    private $file_scanner;

    /**
     * Malware detector instance
     *
     * @var MalwareDetector
     */
    private $malware_detector;

    /**
     * AevIP integration instance
     *
     * @var AevIPIntegration
     */
    private $aevip;

    /**
     * Get singleton instance
     *
     * @return AevovSecurityMonitor
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
     */
    private function load_dependencies() {
        // Core scanners
        require_once AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'includes/scanner/class-process-scanner.php';
        require_once AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'includes/scanner/class-file-scanner.php';
        require_once AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'includes/scanner/class-memory-scanner.php';

        // Detectors (Ghost-inspired)
        require_once AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'includes/detector/class-malware-detector.php';
        require_once AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'includes/detector/class-injection-detector.php';
        require_once AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'includes/detector/class-pattern-matcher.php';
        require_once AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'includes/detector/class-yara-engine.php';

        // Integrations
        require_once AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'includes/integrations/class-aevip-integration.php';
        require_once AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'includes/integrations/class-mitre-attack-mapper.php';

        // API
        require_once AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'includes/api/class-security-endpoint.php';
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation/deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // REST API
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Scheduled scans
        add_action('aevov_security_hourly_scan', [$this, 'run_hourly_scan']);
        add_action('aevov_security_daily_scan', [$this, 'run_daily_scan']);

        // Real-time monitoring
        add_action('plugins_loaded', [$this, 'start_realtime_monitoring'], 1);
        add_action('shutdown', [$this, 'end_request_monitoring']);

        // File upload monitoring
        add_filter('wp_handle_upload_prefilter', [$this, 'scan_upload'], 10, 1);

        // Theme/plugin installation monitoring
        add_action('upgrader_process_complete', [$this, 'scan_installation'], 10, 2);
    }

    /**
     * Initialize components
     */
    private function init_components() {
        $this->process_scanner = new \AevovSecurityMonitor\ProcessScanner();
        $this->file_scanner = new \AevovSecurityMonitor\FileScanner();
        $this->malware_detector = new \AevovSecurityMonitor\MalwareDetector();
        $this->aevip = new \AevovSecurityMonitor\AevIPIntegration();

        // Initialize AevIP distributed scanning
        if (get_option('aevov_security_enable_aevip', true)) {
            $this->aevip->init();
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();

        // Schedule scans
        if (!wp_next_scheduled('aevov_security_hourly_scan')) {
            wp_schedule_event(time(), 'hourly', 'aevov_security_hourly_scan');
        }

        if (!wp_next_scheduled('aevov_security_daily_scan')) {
            wp_schedule_event(time(), 'daily', 'aevov_security_daily_scan');
        }

        // Set default options
        add_option('aevov_security_enable_aevip', true);
        add_option('aevov_security_realtime_monitoring', true);
        add_option('aevov_security_scan_uploads', true);
        add_option('aevov_security_yara_enabled', true);
        add_option('aevov_security_mitre_mapping', true);

        // Run initial scan
        $this->run_initial_scan();

        error_log('[Aevov Security Monitor] Plugin activated - version ' . AEVOV_SECURITY_MONITOR_VERSION);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('aevov_security_hourly_scan');
        wp_clear_scheduled_hook('aevov_security_daily_scan');

        error_log('[Aevov Security Monitor] Plugin deactivated');
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Security events table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevov_security_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            severity enum('critical','high','medium','low','info') NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            file_path text,
            process_id int(11),
            user_id bigint(20) unsigned,
            ip_address varchar(45),
            user_agent text,
            mitre_technique varchar(20),
            mitre_tactic varchar(50),
            yara_rule varchar(100),
            signature_match text,
            status enum('new','investigating','resolved','false_positive') DEFAULT 'new',
            metadata longtext,
            created_at datetime NOT NULL,
            updated_at datetime,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY severity (severity),
            KEY status (status),
            KEY created_at (created_at),
            KEY mitre_technique (mitre_technique)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Scan results table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevov_security_scans (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scan_type varchar(50) NOT NULL,
            status enum('running','completed','failed') DEFAULT 'running',
            files_scanned int(11) DEFAULT 0,
            threats_found int(11) DEFAULT 0,
            scan_duration float,
            aevip_distributed boolean DEFAULT false,
            aevip_nodes int(11),
            started_at datetime NOT NULL,
            completed_at datetime,
            results longtext,
            PRIMARY KEY  (id),
            KEY scan_type (scan_type),
            KEY status (status),
            KEY started_at (started_at)
        ) $charset_collate;";

        dbDelta($sql);

        // YARA rules table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aevov_security_yara_rules (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rule_name varchar(100) NOT NULL UNIQUE,
            rule_content longtext NOT NULL,
            description text,
            author varchar(100),
            enabled boolean DEFAULT true,
            malware_family varchar(100),
            severity enum('critical','high','medium','low') DEFAULT 'medium',
            created_at datetime NOT NULL,
            updated_at datetime,
            PRIMARY KEY  (id),
            KEY rule_name (rule_name),
            KEY enabled (enabled),
            KEY malware_family (malware_family)
        ) $charset_collate;";

        dbDelta($sql);

        error_log('[Aevov Security Monitor] Database tables created');
    }

    /**
     * Run initial baseline scan
     */
    private function run_initial_scan() {
        error_log('[Aevov Security Monitor] Running initial baseline scan...');

        // Start scan in background
        wp_schedule_single_event(time() + 60, 'aevov_security_initial_scan');

        add_action('aevov_security_initial_scan', function() {
            $this->file_scanner->full_scan([
                'create_baseline' => true,
                'distributed' => get_option('aevov_security_enable_aevip', true)
            ]);
        });
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Security Monitor',
            'Security Monitor',
            'manage_options',
            'aevov-security-monitor',
            [$this, 'render_dashboard'],
            'dashicons-shield-alt',
            80
        );

        add_submenu_page(
            'aevov-security-monitor',
            'Security Events',
            'Events',
            'manage_options',
            'aevov-security-events',
            [$this, 'render_events_page']
        );

        add_submenu_page(
            'aevov-security-monitor',
            'Scan Results',
            'Scans',
            'manage_options',
            'aevov-security-scans',
            [$this, 'render_scans_page']
        );

        add_submenu_page(
            'aevov-security-monitor',
            'YARA Rules',
            'YARA Rules',
            'manage_options',
            'aevov-security-yara',
            [$this, 'render_yara_page']
        );

        add_submenu_page(
            'aevov-security-monitor',
            'Settings',
            'Settings',
            'manage_options',
            'aevov-security-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        $endpoint = new \AevovSecurityMonitor\SecurityEndpoint();
        $endpoint->register_routes();
    }

    /**
     * Run hourly scan
     */
    public function run_hourly_scan() {
        $this->process_scanner->scan_running_processes();
        $this->file_scanner->scan_recent_changes();
    }

    /**
     * Run daily scan
     */
    public function run_daily_scan() {
        $this->file_scanner->full_scan([
            'distributed' => get_option('aevov_security_enable_aevip', true)
        ]);
    }

    /**
     * Start real-time monitoring for current request
     */
    public function start_realtime_monitoring() {
        if (!get_option('aevov_security_realtime_monitoring', true)) {
            return;
        }

        // Monitor for suspicious function calls
        $this->process_scanner->monitor_request_start();
    }

    /**
     * End request monitoring
     */
    public function end_request_monitoring() {
        if (!get_option('aevov_security_realtime_monitoring', true)) {
            return;
        }

        $this->process_scanner->monitor_request_end();
    }

    /**
     * Scan file upload
     */
    public function scan_upload($file) {
        if (!get_option('aevov_security_scan_uploads', true)) {
            return $file;
        }

        $result = $this->malware_detector->scan_file($file['tmp_name']);

        if ($result['threat_detected']) {
            // Log threat
            $this->log_security_event([
                'event_type' => 'malware_detected',
                'severity' => 'critical',
                'title' => 'Malware detected in file upload',
                'description' => sprintf(
                    'File: %s, Signature: %s',
                    $file['name'],
                    $result['signature']
                ),
                'file_path' => $file['tmp_name'],
                'yara_rule' => $result['yara_rule'] ?? null,
                'metadata' => json_encode($result)
            ]);

            // Reject upload
            $file['error'] = sprintf(
                'Security threat detected: %s',
                $result['threat_type']
            );
        }

        return $file;
    }

    /**
     * Scan plugin/theme installation
     */
    public function scan_installation($upgrader_object, $options) {
        if (!isset($options['type'])) {
            return;
        }

        $path = '';

        if ($options['type'] === 'plugin' && isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                $path = WP_PLUGIN_DIR . '/' . dirname($plugin);
                $this->file_scanner->scan_directory($path);
            }
        } elseif ($options['type'] === 'theme' && isset($options['theme'])) {
            $path = get_theme_root() . '/' . $options['theme'];
            $this->file_scanner->scan_directory($path);
        }
    }

    /**
     * Log security event
     */
    public function log_security_event($event_data) {
        global $wpdb;

        $defaults = [
            'event_type' => 'unknown',
            'severity' => 'info',
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => current_time('mysql')
        ];

        $event = wp_parse_args($event_data, $defaults);

        $wpdb->insert(
            $wpdb->prefix . 'aevov_security_events',
            $event,
            [
                '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s', '%s'
            ]
        );

        // Fire action for integrations
        do_action('aevov_security_event_logged', $event);

        // Send to AevIP for distributed threat intelligence
        if (get_option('aevov_security_enable_aevip', true)) {
            $this->aevip->share_threat_intelligence($event);
        }
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard() {
        include AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'admin/dashboard.php';
    }

    /**
     * Render events page
     */
    public function render_events_page() {
        include AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'admin/events.php';
    }

    /**
     * Render scans page
     */
    public function render_scans_page() {
        include AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'admin/scans.php';
    }

    /**
     * Render YARA rules page
     */
    public function render_yara_page() {
        include AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'admin/yara-rules.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include AEVOV_SECURITY_MONITOR_PLUGIN_DIR . 'admin/settings.php';
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'aevov-security') === false) {
            return;
        }

        wp_enqueue_style(
            'aevov-security-monitor-admin',
            AEVOV_SECURITY_MONITOR_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AEVOV_SECURITY_MONITOR_VERSION
        );

        wp_enqueue_script(
            'aevov-security-monitor-admin',
            AEVOV_SECURITY_MONITOR_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            AEVOV_SECURITY_MONITOR_VERSION,
            true
        );

        wp_localize_script('aevov-security-monitor-admin', 'aevovSecurityMonitor', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('aevov-security/v1'),
            'nonce' => wp_create_nonce('aevov_security_monitor'),
            'aevipEnabled' => get_option('aevov_security_enable_aevip', true)
        ]);
    }
}

/**
 * Initialize plugin
 */
function aevov_security_monitor() {
    return AevovSecurityMonitor::instance();
}

// Kick off the plugin
aevov_security_monitor();
