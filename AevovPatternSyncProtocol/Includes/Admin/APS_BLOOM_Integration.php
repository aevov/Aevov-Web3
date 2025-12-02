<?php
/**
 * APS BLOOM Integration Admin Handler
 * 
 * Handles admin-side integration between AevovPatternSyncProtocol and BLOOM Pattern Recognition
 */

namespace APS\Admin;

use APS\Core\Logger;
use APS\Integration\BloomIntegration;

class APS_BLOOM_Integration {
    private $logger;
    private $bloom_integration;
    private $admin_notices = [];
    
    public function __construct() {
        $this->logger = Logger::get_instance();
        $this->bloom_integration = BloomIntegration::get_instance();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks for admin integration
     */
    private function init_hooks() {
        if (function_exists('add_action')) {
            // Admin notices and status
            add_action('admin_notices', [$this, 'display_admin_notices']);
            add_action('admin_init', [$this, 'check_bloom_status']);
            
            // AJAX handlers for BLOOM integration
            add_action('wp_ajax_aps_test_bloom_connection', [$this, 'handle_test_bloom_connection']);
            add_action('wp_ajax_aps_sync_bloom_patterns', [$this, 'handle_sync_bloom_patterns']);
            add_action('wp_ajax_aps_get_bloom_status', [$this, 'handle_get_bloom_status']);
            
            // Integration status dashboard widget
            add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        }
    }
    
    /**
     * Check BLOOM plugin status and connectivity
     */
    public function check_bloom_status() {
        if (!$this->is_bloom_plugin_active()) {
            $this->add_admin_notice(
                'BLOOM Pattern Recognition plugin is not active. Some APS features may not work properly.',
                'warning'
            );
            return;
        }
        
        // Test API connectivity
        $connection_status = $this->bloom_integration->check_connection();
        if (!$connection_status) {
            $this->add_admin_notice(
                'Unable to connect to BLOOM Pattern Recognition: ' . $connection_status['message'],
                'error'
            );
        }
    }
    
    /**
     * Check if BLOOM plugin is active
     */
    private function is_bloom_plugin_active() {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        return function_exists('is_plugin_active') && 
               is_plugin_active('bloom-pattern-recognition/bloom-pattern-system.php');
    }
    
    /**
     * Add admin notice to queue
     */
    private function add_admin_notice($message, $type = 'info') {
        $this->admin_notices[] = [
            'message' => $message,
            'type' => $type
        ];
    }
    
    /**
     * Display queued admin notices
     */
    public function display_admin_notices() {
        foreach ($this->admin_notices as $notice) {
            $class = 'notice notice-' . $notice['type'];
            echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($notice['message']) . '</p></div>';
        }
        
        // Clear notices after displaying
        $this->admin_notices = [];
    }
    
    /**
     * AJAX handler for testing BLOOM connection
     */
    public function handle_test_bloom_connection() {
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
            $result = $this->bloom_integration->test_connection();
            
            if (function_exists('wp_send_json_success')) {
                wp_send_json_success($result);
            }
        } catch (Exception $e) {
            $this->logger->error('BLOOM connection test failed: ' . $e->getMessage());
            
            if (function_exists('wp_send_json_error')) {
                wp_send_json_error(['message' => $e->getMessage()]);
            }
        }
    }
    
    /**
     * AJAX handler for syncing BLOOM patterns
     */
    public function handle_sync_bloom_patterns() {
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
            $sync_result = $this->bloom_integration->sync_with_bloom();
            
            $this->logger->info('BLOOM pattern sync completed', [
                'patterns_synced' => $sync_result['patterns_synced'] ?? 0,
                'errors' => $sync_result['errors'] ?? []
            ]);
            
            if (function_exists('wp_send_json_success')) {
                wp_send_json_success($sync_result);
            }
        } catch (Exception $e) {
            $this->logger->error('BLOOM pattern sync failed: ' . $e->getMessage());
            
            if (function_exists('wp_send_json_error')) {
                wp_send_json_error(['message' => $e->getMessage()]);
            }
        }
    }
    
    /**
     * AJAX handler for getting BLOOM status
     */
    public function handle_get_bloom_status() {
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
            $status = [
                'plugin_active' => $this->is_bloom_plugin_active(),
                'connection_status' => $this->bloom_integration->check_connection(),
                'last_sync' => $this->get_last_sync_time(),
                'pattern_count' => $this->get_bloom_pattern_count()
            ];
            
            if (function_exists('wp_send_json_success')) {
                wp_send_json_success($status);
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to get BLOOM status: ' . $e->getMessage());
            
            if (function_exists('wp_send_json_error')) {
                wp_send_json_error(['message' => $e->getMessage()]);
            }
        }
    }
    
    /**
     * Add dashboard widget for BLOOM integration status
     */
    public function add_dashboard_widget() {
        if (function_exists('wp_add_dashboard_widget')) {
            wp_add_dashboard_widget(
                'aps_bloom_status',
                'APS-BLOOM Integration Status',
                [$this, 'render_dashboard_widget']
            );
        }
    }
    
    /**
     * Render dashboard widget content
     */
    public function render_dashboard_widget() {
        $status = [
            'plugin_active' => $this->is_bloom_plugin_active(),
            'connection_status' => $this->bloom_integration->check_connection(),
            'last_sync' => $this->get_last_sync_time(),
            'pattern_count' => $this->get_bloom_pattern_count()
        ];
        
        echo '<div class="aps-bloom-status-widget">';
        echo '<h4>BLOOM Plugin Status</h4>';
        
        if ($status['plugin_active']) {
            echo '<p><span class="dashicons dashicons-yes-alt" style="color: green;"></span> BLOOM Plugin Active</p>';
        } else {
            echo '<p><span class="dashicons dashicons-warning" style="color: orange;"></span> BLOOM Plugin Inactive</p>';
        }
        
        if ($status['connection_status']['success']) {
            echo '<p><span class="dashicons dashicons-yes-alt" style="color: green;"></span> API Connection OK</p>';
        } else {
            echo '<p><span class="dashicons dashicons-dismiss" style="color: red;"></span> API Connection Failed</p>';
        }
        
        echo '<p><strong>Patterns:</strong> ' . intval($status['pattern_count']) . '</p>';
        echo '<p><strong>Last Sync:</strong> ' . ($status['last_sync'] ?: 'Never') . '</p>';
        
        echo '<p>';
        echo '<button type="button" class="button button-secondary" onclick="apsTestBloomConnection()">Test Connection</button> ';
        echo '<button type="button" class="button button-primary" onclick="apsSyncBloomPatterns()">Sync Patterns</button>';
        echo '</p>';
        
        echo '</div>';
        
        // Add inline JavaScript for widget buttons
        echo '<script>
        function apsTestBloomConnection() {
            jQuery.post(ajaxurl, {
                action: "aps_test_bloom_connection",
                nonce: "' . (function_exists('wp_create_nonce') ? wp_create_nonce('aps-admin') : '') . '"
            }, function(response) {
                if (response.success) {
                    alert("Connection test successful!");
                } else {
                    alert("Connection test failed: " + response.data.message);
                }
            });
        }
        
        function apsSyncBloomPatterns() {
            if (confirm("Start pattern synchronization?")) {
                jQuery.post(ajaxurl, {
                    action: "aps_sync_bloom_patterns",
                    nonce: "' . (function_exists('wp_create_nonce') ? wp_create_nonce('aps-admin') : '') . '"
                }, function(response) {
                    if (response.success) {
                        alert("Pattern sync completed! Synced: " + response.data.patterns_synced + " patterns");
                        location.reload();
                    } else {
                        alert("Pattern sync failed: " + response.data.message);
                    }
                });
            }
        }
        </script>';
    }
    
    /**
     * Get last synchronization time
     */
    private function get_last_sync_time() {
        if (function_exists('get_option')) {
            $last_sync = get_option('aps_bloom_last_sync', false);
            if ($last_sync) {
                return date('Y-m-d H:i:s', $last_sync);
            }
        }
        return false;
    }
    
    /**
     * Get BLOOM pattern count
     */
    private function get_bloom_pattern_count() {
        try {
            global $wpdb;
            
            if ($wpdb) {
                $count = $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}aps_patterns_cache 
                     WHERE source = 'bloom' AND cache_expires > NOW()"
                );
                return intval($count);
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to get BLOOM pattern count: ' . $e->getMessage());
        }
        
        return 0;
    }
    
    /**
     * Get integration statistics for admin display
     */
    public function get_integration_stats() {
        return [
            'plugin_active' => $this->is_bloom_plugin_active(),
            'connection_status' => $this->bloom_integration->test_connection(),
            'last_sync' => $this->get_last_sync_time(),
            'pattern_count' => $this->get_bloom_pattern_count(),
            'sync_errors' => $this->get_sync_error_count()
        ];
    }
    
    /**
     * Get synchronization error count
     */
    private function get_sync_error_count() {
        if (function_exists('get_option')) {
            return intval(get_option('aps_bloom_sync_errors', 0));
        }
        return 0;
    }
    
    /**
     * Reset integration status and clear cache
     */
    public function reset_integration() {
        if (function_exists('delete_option')) {
            delete_option('aps_bloom_last_sync');
            delete_option('aps_bloom_sync_errors');
        }
        
        // Clear pattern cache
        global $wpdb;
        if ($wpdb) {
            $wpdb->query(
                "DELETE FROM {$wpdb->prefix}aps_patterns_cache 
                 WHERE source = 'bloom'"
            );
        }
        
        $this->logger->info('BLOOM integration reset completed');
    }
}