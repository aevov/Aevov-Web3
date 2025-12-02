<?php
/**
 * includes/admin/class-aps-tools.php
 */

namespace APS\Admin;

use APS\Core\Logger;
use APS\Monitoring\SystemMonitor;
use APS\Network\NetworkMonitor;
use APS\DB\MetricsDB;
use APS\DB\APS_Cache;

class APS_Tools {
    private $logger;

    public function __construct() {
        $this->logger = Logger::get_instance();
        $this->init_hooks();
    }

    private function init_hooks() {
        if (function_exists('add_action')) {
            add_action('wp_ajax_aps_clear_cache', [$this, 'handle_clear_cache_ajax']);
            add_action('wp_ajax_aps_run_diagnostics', [$this, 'handle_run_diagnostics_ajax']);
            add_action('wp_ajax_aps_run_pattern_sync', [$this, 'handle_sync_patterns_ajax']); // New AJAX action
        }
    }

    public function render_tools_page() {
        echo '<div class="wrap">';
        echo '<h1>' . (function_exists('__') ? __('APS Tools', 'aps') : 'APS Tools') . '</h1>';
        echo '<div class="aps-tools-grid">';
 
        // System diagnostics tool
        echo '<div class="aps-tool-card">';
        echo '<h3>' . (function_exists('__') ? __('System Diagnostics', 'aps') : 'System Diagnostics') . '</h3>';
        echo '<p>' . (function_exists('__') ? __('Run comprehensive system checks and diagnostics.', 'aps') : 'Run comprehensive system checks and diagnostics.') . '</p>';
        echo '<button class="button button-primary" onclick="apsRunDiagnostics()">' . (function_exists('__') ? __('Run Diagnostics', 'aps') : 'Run Diagnostics') . '</button>';
        echo '<div id="aps-diagnostics-results" class="aps-tool-results"></div>'; // Placeholder for results
        echo '</div>';
 
        // Pattern sync tool
        echo '<div class="aps-tool-card">';
        echo '<h3>' . (function_exists('__') ? __('Pattern Synchronization', 'aps') : 'Pattern Synchronization') . '</h3>';
        echo '<p>' . (function_exists('__') ? __('Synchronize patterns with BLOOM integration.', 'aps') : 'Synchronize patterns with BLOOM integration.') . '</p>';
        echo '<button class="button button-primary" onclick="apsRunPatternSync()">' . (function_exists('__') ? __('Sync Patterns', 'aps') : 'Sync Patterns') . '</button>';
        echo '<div id="aps-sync-status" class="aps-tool-results"></div>'; // Placeholder for status
        echo '</div>';
 
        // Clear cache tool
        echo '<div class="aps-tool-card">';
        echo '<h3>' . (function_exists('__') ? __('Clear Cache', 'aps') : 'Clear Cache') . '</h3>';
        echo '<p>' . (function_exists('__') ? __('Clear all APS related caches.', 'aps') : 'Clear all APS related caches.') . '</p>';
        echo '<button class="button button-secondary" id="aps-clear-cache">' . (function_exists('__') ? __('Clear Cache', 'aps') : 'Clear Cache') . '</button>';
        echo '</div>';
 
        echo '</div>';
        echo '</div>';
    }

    public function handle_clear_cache_ajax() {
        if (function_exists('check_ajax_referer')) {
            check_ajax_referer('aps-admin', 'nonce');
        }

        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            if (function_exists('wp_send_json_error')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
            }
            return;
        }

        try {
            // Clear the cache
            $cache = new \APS\DB\APS_Cache();
            $cache->flush();

            if (function_exists('wp_send_json_success')) {
                wp_send_json_success(['message' => 'Cache cleared successfully.']);
            }
        } catch (\Exception $e) {
            if (function_exists('wp_send_json_error')) {
                wp_send_json_error(['message' => $e->getMessage()]);
            }
        }
    }

    public function handle_run_diagnostics_ajax() {
        if (function_exists('check_ajax_referer')) {
            check_ajax_referer('aps-admin', 'nonce');
        }

        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            if (function_exists('wp_send_json_error')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
            }
            return;
        }

        try {
            $diagnostics = [];

            // 1. Check plugin dependencies
            $diagnostics['plugin_dependencies'] = $this->check_plugin_dependencies();

            // 2. Check database health
            $diagnostics['database_health'] = $this->check_database_health();

            // 3. Check file system permissions (basic check)
            $diagnostics['filesystem_permissions'] = $this->check_filesystem_permissions();

            // 4. Check network connectivity (example: ping Google)
            $diagnostics['network_connectivity'] = $this->check_network_connectivity();

            // 5. Check queue status
            $diagnostics['queue_status'] = $this->check_queue_status();

            // 6. Basic performance metrics
            $diagnostics['performance_metrics'] = $this->get_performance_metrics();

            if (function_exists('wp_send_json_success')) {
                wp_send_json_success(['message' => 'Diagnostics completed.', 'results' => $diagnostics]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error during diagnostics: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if (function_exists('wp_send_json_error')) {
                wp_send_json_error(['message' => 'Error during diagnostics: ' . $e->getMessage()]);
            }
        }
    }

    private function check_plugin_dependencies() {
        $results = [];
        $bloom_active = class_exists('\BLOOM\Core');
        $results['bloom_pattern_recognition'] = [
            'status' => $bloom_active ? 'active' : 'inactive',
            'message' => $bloom_active ? 'BLOOM Pattern Recognition is active.' : 'BLOOM Pattern Recognition is not active.'
        ];
        // Add checks for other Aevov plugins if needed
        return $results;
    }

    private function check_database_health() {
        global $wpdb;
        $results = [];
        $required_tables = [
            $wpdb->prefix . 'aps_comparisons',
            $wpdb->prefix . 'aps_results',
            $wpdb->prefix . 'aps_patterns',
            $wpdb->prefix . 'aps_chunks', // New table
            $wpdb->prefix . 'bloom_chunks', // BLOOM's table
            $wpdb->prefix . 'aps_metrics',
            $wpdb->prefix . 'aps_alerts',
            $wpdb->prefix . 'aps_queue'
        ];

        foreach ($required_tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
            $results[$table] = [
                'status' => $exists ? 'exists' : 'missing',
                'message' => $exists ? 'Table exists.' : 'Table is missing.'
            ];
        }
        return $results;
    }

    private function check_filesystem_permissions() {
        $results = [];
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'];

        $results['upload_dir_writable'] = [
            'status' => is_writable($upload_path) ? 'writable' : 'not_writable',
            'message' => is_writable($upload_path) ? 'Upload directory is writable.' : 'Upload directory is not writable. This may cause issues with file uploads.'
        ];
        return $results;
    }

    private function check_network_connectivity() {
        $results = [];
        $test_url = 'https://www.google.com';
        $response = wp_remote_get($test_url, ['timeout' => 5]);

        if (is_wp_error($response)) {
            $results['external_connectivity'] = [
                'status' => 'failed',
                'message' => 'Failed to connect to external URL: ' . $response->get_error_message()
            ];
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $results['external_connectivity'] = [
                'status' => ($response_code >= 200 && $response_code < 300) ? 'success' : 'failed',
                'message' => 'HTTP Status: ' . $response_code
            ];
        }
        return $results;
    }

    private function check_queue_status() {
        $results = [];
        $queue_manager = new \APS\Queue\QueueManager();
        $pending_jobs = $queue_manager->get_pending_jobs_count();
        $failed_jobs = $queue_manager->get_failed_jobs_count();

        $results['pending_jobs'] = [
            'count' => $pending_jobs,
            'status' => $pending_jobs > 0 ? 'pending' : 'clear',
            'message' => $pending_jobs . ' jobs pending in queue.'
        ];
        $results['failed_jobs'] = [
            'count' => $failed_jobs,
            'status' => $failed_jobs > 0 ? 'failed' : 'clear',
            'message' => $failed_jobs . ' jobs failed in queue.'
        ];
        return $results;
    }

    private function get_performance_metrics() {
        $results = [];
        $system_monitor = new SystemMonitor();
        $metrics = $system_monitor->collect_and_store_metrics(); // This might be too heavy for a quick diagnostic

        $results['memory_usage'] = [
            'value' => round(memory_get_usage(true) / (1024 * 1024), 2) . ' MB',
            'message' => 'Current PHP memory usage.'
        ];
        // Add more metrics as needed, e.g., from $metrics if it's lightweight enough
        return $results;
    }

    public function handle_sync_patterns_ajax() {
        if (function_exists('check_ajax_referer')) {
            check_ajax_referer('aps-admin', 'nonce');
        }

        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            if (function_exists('wp_send_json_error')) {
                wp_send_json_error(['message' => 'Insufficient permissions']);
            }
            return;
        }

        try {
            $bloom_integration = new \APS\Integration\BloomIntegration();
            $sync_result = $bloom_integration->sync_with_bloom();

            if ($sync_result) {
                if (function_exists('wp_send_json_success')) {
                    wp_send_json_success(['message' => 'Patterns synchronized successfully.']);
                }
            } else {
                if (function_exists('wp_send_json_error')) {
                    wp_send_json_error(['message' => 'Pattern synchronization failed. Check logs for details.']);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error during pattern synchronization: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if (function_exists('wp_send_json_error')) {
                wp_send_json_error(['message' => 'Error during pattern synchronization: ' . $e->getMessage()]);
            }
        }
    }
}
