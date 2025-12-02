<?php
/**
 * Admin Interface Handler for APS Tools
 *
 * Manages all admin-related functionality including settings pages,
 * dashboard widgets, admin notices, and administrative operations.
 *
 * @package APSTools
 * @subpackage Admin
 * @since 1.0.0
 */

namespace APSTools;

use APSTools\DB\APS_Bloom_Tensors_DB;

class APS_Tools_Admin {

    /**
     * Singleton instance
     *
     * @var APS_Tools_Admin
     */
    private static $instance = null;

    /**
     * Database handler
     *
     * @var APS_Bloom_Tensors_DB
     */
    private $db;

    /**
     * WordPress database object
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Get singleton instance
     *
     * @return APS_Tools_Admin
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
        global $wpdb;
        $this->wpdb = $wpdb;

        if (class_exists('\APSTools\DB\APS_Bloom_Tensors_DB')) {
            $this->db = new APS_Bloom_Tensors_DB();
        }

        // Initialize admin hooks
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * @return void
     */
    private function init_hooks() {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [$this, 'add_admin_pages']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
        add_action('admin_bar_menu', [$this, 'add_admin_bar_items'], 100);
        add_action('dashboard_glance_items', [$this, 'add_dashboard_glance_items']);
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widgets']);

        // AJAX handlers
        add_action('wp_ajax_aps_get_system_status', [$this, 'ajax_get_system_status']);
        add_action('wp_ajax_aps_clear_cache', [$this, 'ajax_clear_cache']);
        add_action('wp_ajax_aps_export_data', [$this, 'ajax_export_data']);
        add_action('wp_ajax_aps_import_data', [$this, 'ajax_import_data']);
        add_action('wp_ajax_aps_test_connection', [$this, 'ajax_test_connection']);
    }

    /**
     * Add admin menu pages
     *
     * @return void
     */
    public function add_admin_pages() {
        // Settings page
        add_submenu_page(
            'aps-dashboard',
            __('APS Tools Settings', 'aps-tools'),
            __('Settings', 'aps-tools'),
            'manage_options',
            'aps-tools-settings',
            [$this, 'render_settings_page']
        );

        // System Info page
        add_submenu_page(
            'aps-dashboard',
            __('System Information', 'aps-tools'),
            __('System Info', 'aps-tools'),
            'manage_options',
            'aps-system-info',
            [$this, 'render_system_info_page']
        );

        // Activity Log page
        add_submenu_page(
            'aps-dashboard',
            __('Activity Log', 'aps-tools'),
            __('Activity Log', 'aps-tools'),
            'manage_options',
            'aps-activity-log',
            [$this, 'render_activity_log_page']
        );

        // Tools page
        add_submenu_page(
            'aps-dashboard',
            __('APS Tools & Utilities', 'aps-tools'),
            __('Tools', 'aps-tools'),
            'manage_options',
            'aps-tools-utilities',
            [$this, 'render_tools_page']
        );
    }

    /**
     * Register plugin settings
     *
     * @return void
     */
    public function register_settings() {
        // General settings section
        add_settings_section(
            'aps_general_settings',
            __('General Settings', 'aps-tools'),
            [$this, 'render_general_settings_section'],
            'aps-tools-settings'
        );

        // Storage settings section
        add_settings_section(
            'aps_storage_settings',
            __('Storage Settings', 'aps-tools'),
            [$this, 'render_storage_settings_section'],
            'aps-tools-settings'
        );

        // Performance settings section
        add_settings_section(
            'aps_performance_settings',
            __('Performance Settings', 'aps-tools'),
            [$this, 'render_performance_settings_section'],
            'aps-tools-settings'
        );

        // Register settings fields
        $settings = [
            'aps_tools_enable_logging',
            'aps_tools_log_level',
            'aps_tools_storage_type',
            'aps_tools_max_upload_size',
            'aps_tools_chunk_size',
            'aps_tools_batch_size',
            'aps_tools_enable_cron',
            'aps_tools_enable_metrics',
            'aps_tools_enable_cache',
            'aps_tools_cache_ttl',
            'aps_tools_debug_mode'
        ];

        foreach ($settings as $setting) {
            register_setting('aps_tools_options', $setting);
        }
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_assets($hook) {
        // Only load on APS Tools pages
        if (strpos($hook, 'aps-') === false && strpos($hook, 'aps_') === false) {
            return;
        }

        // Enqueue admin styles
        wp_enqueue_style(
            'aps-tools-admin',
            APSTOOLS_URL . 'assets/css/admin.css',
            [],
            APSTOOLS_VERSION
        );

        // Enqueue admin scripts
        wp_enqueue_script(
            'aps-tools-admin',
            APSTOOLS_URL . 'assets/js/admin.js',
            ['jquery'],
            APSTOOLS_VERSION,
            true
        );

        // Localize script
        wp_localize_script('aps-tools-admin', 'apsToolsAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aps_tools_admin'),
            'i18n' => [
                'loading' => __('Loading...', 'aps-tools'),
                'error' => __('An error occurred', 'aps-tools'),
                'success' => __('Operation completed successfully', 'aps-tools'),
                'confirm' => __('Are you sure?', 'aps-tools')
            ]
        ]);
    }

    /**
     * Display admin notices
     *
     * @return void
     */
    public function display_admin_notices() {
        // Check if plugin was just activated
        if (get_transient('aps_tools_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('APS Tools has been activated successfully!', 'aps-tools'); ?></p>
            </div>
            <?php
            delete_transient('aps_tools_activation_notice');
        }

        // Check for dependency issues
        if (!class_exists('APS\Core\APS_Core')) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('APS Tools requires the Aevov Pattern Sync Protocol plugin to be installed and activated.', 'aps-tools'); ?></p>
            </div>
            <?php
        }

        if (!class_exists('BLOOM_Pattern_System')) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('APS Tools requires the BLOOM Pattern Recognition System plugin to be installed and activated.', 'aps-tools'); ?></p>
            </div>
            <?php
        }

        // Check for system warnings
        $this->display_system_warnings();
    }

    /**
     * Display system warnings
     *
     * @return void
     */
    private function display_system_warnings() {
        // Check disk space
        $upload_dir = wp_upload_dir();
        $free_space = @disk_free_space($upload_dir['basedir']);
        if ($free_space !== false && $free_space < 100 * 1024 * 1024) { // Less than 100MB
            ?>
            <div class="notice notice-warning">
                <p><?php _e('Low disk space warning: Less than 100MB available for uploads.', 'aps-tools'); ?></p>
            </div>
            <?php
        }

        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->convert_to_bytes($memory_limit);
        if ($memory_bytes < 128 * 1024 * 1024) { // Less than 128MB
            ?>
            <div class="notice notice-warning">
                <p><?php printf(__('Low PHP memory limit: %s. Recommended: 256M or higher.', 'aps-tools'), $memory_limit); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Add items to admin bar
     *
     * @param \WP_Admin_Bar $wp_admin_bar Admin bar object
     * @return void
     */
    public function add_admin_bar_items($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get system status
        $status = $this->get_quick_system_status();

        $wp_admin_bar->add_node([
            'id' => 'aps-tools',
            'title' => '<span class="ab-icon dashicons-networking"></span> APS Tools',
            'href' => admin_url('admin.php?page=aps-dashboard')
        ]);

        $wp_admin_bar->add_node([
            'id' => 'aps-tools-status',
            'parent' => 'aps-tools',
            'title' => sprintf(__('Status: %s', 'aps-tools'), $status['status']),
            'href' => admin_url('admin.php?page=aps-status')
        ]);

        $wp_admin_bar->add_node([
            'id' => 'aps-tools-tensors',
            'parent' => 'aps-tools',
            'title' => sprintf(__('Tensors: %d', 'aps-tools'), $status['tensor_count']),
            'href' => admin_url('admin.php?page=aps-dashboard')
        ]);
    }

    /**
     * Add dashboard glance items
     *
     * @return void
     */
    public function add_dashboard_glance_items() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tensor_count = $this->db ? $this->db->count_tensors('completed') : 0;
        $pattern_count = $this->get_pattern_count();

        echo '<li class="aps-tensor-count">';
        echo '<a href="' . admin_url('admin.php?page=aps-dashboard') . '">';
        printf(__('%d Tensors', 'aps-tools'), $tensor_count);
        echo '</a></li>';

        echo '<li class="aps-pattern-count">';
        echo '<a href="' . admin_url('admin.php?page=aps-dashboard') . '">';
        printf(__('%d Patterns', 'aps-tools'), $pattern_count);
        echo '</a></li>';
    }

    /**
     * Add dashboard widgets
     *
     * @return void
     */
    public function add_dashboard_widgets() {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            'aps_tools_status_widget',
            __('APS Tools Status', 'aps-tools'),
            [$this, 'render_status_widget']
        );

        wp_add_dashboard_widget(
            'aps_tools_activity_widget',
            __('Recent Activity', 'aps-tools'),
            [$this, 'render_activity_widget']
        );
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('APS Tools Settings', 'aps-tools'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('aps_tools_options');
                do_settings_sections('aps-tools-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render system info page
     *
     * @return void
     */
    public function render_system_info_page() {
        $system_info = $this->get_system_info();
        ?>
        <div class="wrap">
            <h1><?php _e('System Information', 'aps-tools'); ?></h1>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Property', 'aps-tools'); ?></th>
                        <th><?php _e('Value', 'aps-tools'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($system_info as $key => $value): ?>
                    <tr>
                        <td><strong><?php echo esc_html($key); ?></strong></td>
                        <td><?php echo esc_html($value); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render activity log page
     *
     * @return void
     */
    public function render_activity_log_page() {
        $activities = $this->get_recent_activities(50);
        ?>
        <div class="wrap">
            <h1><?php _e('Activity Log', 'aps-tools'); ?></h1>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'aps-tools'); ?></th>
                        <th><?php _e('User', 'aps-tools'); ?></th>
                        <th><?php _e('Action', 'aps-tools'); ?></th>
                        <th><?php _e('Description', 'aps-tools'); ?></th>
                        <th><?php _e('Status', 'aps-tools'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity): ?>
                    <tr>
                        <td><?php echo esc_html($activity['created_at']); ?></td>
                        <td><?php echo esc_html($this->get_user_name($activity['user_id'])); ?></td>
                        <td><?php echo esc_html($activity['action_type']); ?></td>
                        <td><?php echo esc_html($activity['action_description']); ?></td>
                        <td><span class="status-<?php echo esc_attr($activity['status']); ?>"><?php echo esc_html($activity['status']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render tools page
     *
     * @return void
     */
    public function render_tools_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('APS Tools & Utilities', 'aps-tools'); ?></h1>
            <div class="aps-tools-grid">
                <div class="tool-card">
                    <h3><?php _e('Clear Cache', 'aps-tools'); ?></h3>
                    <p><?php _e('Clear all cached data to free up space and ensure fresh data.', 'aps-tools'); ?></p>
                    <button class="button" id="aps-clear-cache"><?php _e('Clear Cache', 'aps-tools'); ?></button>
                </div>
                <div class="tool-card">
                    <h3><?php _e('Export Data', 'aps-tools'); ?></h3>
                    <p><?php _e('Export tensors and patterns data for backup or migration.', 'aps-tools'); ?></p>
                    <button class="button" id="aps-export-data"><?php _e('Export', 'aps-tools'); ?></button>
                </div>
                <div class="tool-card">
                    <h3><?php _e('Test Connection', 'aps-tools'); ?></h3>
                    <p><?php _e('Test connection to APS and BLOOM plugins.', 'aps-tools'); ?></p>
                    <button class="button" id="aps-test-connection"><?php _e('Test Connection', 'aps-tools'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render status widget
     *
     * @return void
     */
    public function render_status_widget() {
        $status = $this->get_quick_system_status();
        echo '<ul>';
        echo '<li><strong>' . __('System Status:', 'aps-tools') . '</strong> ' . esc_html($status['status']) . '</li>';
        echo '<li><strong>' . __('Tensors:', 'aps-tools') . '</strong> ' . esc_html($status['tensor_count']) . '</li>';
        echo '<li><strong>' . __('Patterns:', 'aps-tools') . '</strong> ' . esc_html($status['pattern_count']) . '</li>';
        echo '</ul>';
    }

    /**
     * Render activity widget
     *
     * @return void
     */
    public function render_activity_widget() {
        $activities = $this->get_recent_activities(5);
        echo '<ul>';
        foreach ($activities as $activity) {
            echo '<li>' . esc_html($activity['action_description']) . ' <em>(' . esc_html($activity['created_at']) . ')</em></li>';
        }
        echo '</ul>';
    }

    /**
     * AJAX: Get system status
     *
     * @return void
     */
    public function ajax_get_system_status() {
        check_ajax_referer('aps_tools_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'aps-tools')]);
        }

        $status = $this->get_system_info();
        wp_send_json_success($status);
    }

    /**
     * AJAX: Clear cache
     *
     * @return void
     */
    public function ajax_clear_cache() {
        check_ajax_referer('aps_tools_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'aps-tools')]);
        }

        // Clear WordPress cache
        wp_cache_flush();

        wp_send_json_success(['message' => __('Cache cleared successfully', 'aps-tools')]);
    }

    /**
     * AJAX: Export data
     *
     * @return void
     */
    public function ajax_export_data() {
        check_ajax_referer('aps_tools_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'aps-tools')]);
        }

        $export_data = [
            'tensors' => $this->db ? $this->db->get_statistics() : [],
            'timestamp' => current_time('mysql')
        ];

        wp_send_json_success($export_data);
    }

    /**
     * AJAX: Test connection
     *
     * @return void
     */
    public function ajax_test_connection() {
        check_ajax_referer('aps_tools_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'aps-tools')]);
        }

        $results = [
            'aps' => class_exists('APS\Core\APS_Core'),
            'bloom' => class_exists('BLOOM_Pattern_System')
        ];

        wp_send_json_success($results);
    }

    /**
     * Get quick system status
     *
     * @return array System status
     */
    private function get_quick_system_status() {
        return [
            'status' => 'operational',
            'tensor_count' => $this->db ? $this->db->count_tensors('completed') : 0,
            'pattern_count' => $this->get_pattern_count()
        ];
    }

    /**
     * Get system information
     *
     * @return array System information
     */
    private function get_system_info() {
        global $wpdb;

        return [
            'Plugin Version' => APSTOOLS_VERSION,
            'WordPress Version' => get_bloginfo('version'),
            'PHP Version' => PHP_VERSION,
            'MySQL Version' => $wpdb->db_version(),
            'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'Memory Limit' => ini_get('memory_limit'),
            'Max Upload Size' => size_format(wp_max_upload_size()),
            'Multisite' => is_multisite() ? 'Yes' : 'No',
            'Debug Mode' => WP_DEBUG ? 'Enabled' : 'Disabled'
        ];
    }

    /**
     * Get recent activities
     *
     * @param int $limit Limit results
     * @return array Activities
     */
    private function get_recent_activities($limit = 10) {
        $table = $this->wpdb->prefix . 'aps_activity_log';
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Get pattern count
     *
     * @return int Pattern count
     */
    private function get_pattern_count() {
        $table = $this->wpdb->prefix . 'aps_patterns';
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        return $count ? intval($count) : 0;
    }

    /**
     * Get user name by ID
     *
     * @param int $user_id User ID
     * @return string User name
     */
    private function get_user_name($user_id) {
        if (!$user_id) {
            return __('System', 'aps-tools');
        }
        $user = get_userdata($user_id);
        return $user ? $user->display_name : __('Unknown', 'aps-tools');
    }

    /**
     * Convert memory limit to bytes
     *
     * @param string $value Memory value
     * @return int Bytes
     */
    private function convert_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Render general settings section
     *
     * @return void
     */
    public function render_general_settings_section() {
        echo '<p>' . __('Configure general plugin settings.', 'aps-tools') . '</p>';
    }

    /**
     * Render storage settings section
     *
     * @return void
     */
    public function render_storage_settings_section() {
        echo '<p>' . __('Configure storage and upload settings.', 'aps-tools') . '</p>';
    }

    /**
     * Render performance settings section
     *
     * @return void
     */
    public function render_performance_settings_section() {
        echo '<p>' . __('Configure performance and caching settings.', 'aps-tools') . '</p>';
    }
}
