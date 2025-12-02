<?php
/**
 * Alert management and notification system
 * 
 * @package APS
 * @subpackage Monitoring
 */

namespace APS\Monitoring;

use APS\Core\Logger;

class AlertManager {
    private $logger;
    private $alert_levels = [
        'info' => 0,
        'warning' => 1,
        'critical' => 2
    ];

    private $alert_config = [];
    private $notification_channels = [];
    private $suppressed_alerts = [];

    public function __construct() {
        $this->logger = Logger::get_instance();
        $this->init_alert_config();
        $this->init_notification_channels();
        $this->init_hooks();
        $this->load_suppressed_alerts();
    }

    private function init_hooks() {
        if (function_exists('add_action')) {
            add_action('aps_process_alerts', [$this, 'process_pending_alerts']);
            add_action('aps_clear_old_alerts', [$this, 'clear_old_alerts']);
            add_action('aps_hourly_alert_cleanup', [$this, 'cleanup_alerts']);
        }
    }

    private function init_alert_config() {
        $this->alert_config = [
            'high_cpu_usage' => [
                'level' => 'warning',
                'message' => function_exists('__') ? __('High CPU usage detected', 'aps') : 'High CPU usage detected',
                'threshold' => 85,
                'notify' => true,
                'cooldown' => 1800, // 30 minutes
                'channels' => ['email', 'admin_notice']
            ],
            'high_memory_usage' => [
                'level' => 'warning',
                'message' => function_exists('__') ? __('High memory usage detected', 'aps') : 'High memory usage detected',
                'threshold' => 85,
                'notify' => true,
                'cooldown' => 1800,
                'channels' => ['email', 'admin_notice']
            ],
            'high_error_rate' => [
                'level' => 'critical',
                'message' => function_exists('__') ? __('High pattern processing error rate detected', 'aps') : 'High pattern processing error rate detected',
                'threshold' => 5,
                'notify' => true,
                'cooldown' => 900, // 15 minutes
                'channels' => ['email', 'admin_notice', 'slack']
            ],
            'sync_delay' => [
                'level' => 'warning',
                'message' => function_exists('__') ? __('Pattern sync delay detected', 'aps') : 'Pattern sync delay detected',
                'threshold' => 300,
                'notify' => true,
                'cooldown' => 1800,
                'channels' => ['email', 'admin_notice']
            ],
            'bloom_connection_failed' => [
                'level' => 'critical',
                'message' => function_exists('__') ? __('BLOOM integration connection failed', 'aps') : 'BLOOM integration connection failed',
                'notify' => true,
                'cooldown' => 300, // 5 minutes
                'channels' => ['email', 'admin_notice', 'slack']
            ],
            'low_confidence_pattern' => [
                'level' => 'warning',
                'message' => function_exists('__') ? __('Low confidence pattern detected', 'aps') : 'Low confidence pattern detected',
                'threshold' => 0.75,
                'notify' => false,
                'cooldown' => 3600,
                'channels' => ['admin_notice']
            ]
        ];
    }

    private function init_notification_channels() {
        $this->notification_channels = [
            'email' => [$this, 'send_email_notification'],
            'admin_notice' => [$this, 'display_admin_notice'],
            'slack' => [$this, 'send_slack_notification']
        ];
    }

    private function load_suppressed_alerts() {
        $this->suppressed_alerts = function_exists('get_option') ? get_option('aps_suppressed_alerts', []) : [];
    }

    public function trigger_alert($type, $data = []) {
        if (!isset($this->alert_config[$type])) {
            return false;
        }

        $alert = $this->alert_config[$type];
        
        if ($this->is_alert_suppressed($type)) {
            return false;
        }

        $alert_data = array_merge($data, [
            'type' => $type,
            'level' => $alert['level'],
            'message' => $alert['message'],
            'timestamp' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            'site_id' => function_exists('get_current_blog_id') ? get_current_blog_id() : 1
        ]);

        $alert_id = $this->store_alert($alert_data);
        
        if ($alert_id && $alert['notify']) {
            $this->process_notifications($alert_data, $alert['channels']);
            $this->update_alert_cooldown($type);
        }

        if (function_exists('do_action')) {
            do_action('aps_alert_triggered', $alert_data);
        }
        
        return $alert_id;
    }

    private function store_alert($alert_data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'aps_alerts',
            [
                'alert_type' => $alert_data['type'],
                'alert_level' => $this->alert_levels[$alert_data['level']],
                'alert_message' => $alert_data['message'],
                'alert_data' => json_encode($alert_data),
                'site_id' => $alert_data['site_id'],
                'status' => 'active',
                'created_at' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s')
            ],
            ['%s', '%d', '%s', '%s', '%d', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    private function process_notifications($alert_data, $channels) {
        foreach ($channels as $channel) {
            if (isset($this->notification_channels[$channel])) {
                call_user_func($this->notification_channels[$channel], $alert_data);
            }
        }
    }

    private function send_email_notification($alert_data) {
        $admin_email = function_exists('get_option') ? get_option('admin_email') : 'admin@example.com';
        $site_name = function_exists('get_bloginfo') ? get_bloginfo('name') : 'WordPress Site';
        $subject = sprintf('[%s] APS Alert: %s', $site_name, $alert_data['message']);
        
        $body = sprintf(
            "Alert Type: %s\nLevel: %s\nMessage: %s\nTimestamp: %s\n\nDetails:\n%s",
            $alert_data['type'],
            $alert_data['level'],
            $alert_data['message'],
            $alert_data['timestamp'],
            print_r($alert_data, true)
        );

        if (function_exists('wp_mail')) {
            wp_mail($admin_email, $subject, $body);
        }
    }

    private function display_admin_notice($alert_data) {
        if (function_exists('add_action')) {
            add_action('admin_notices', function() use ($alert_data) {
            $class = 'notice notice-' . ($alert_data['level'] === 'critical' ? 'error' : 'warning');
            printf(
                '<div class="%1$s"><p>%2$s</p></div>',
                function_exists('esc_attr') ? esc_attr($class) : htmlspecialchars($class),
                function_exists('esc_html') ? esc_html($alert_data['message']) : htmlspecialchars($alert_data['message'])
            );
            });
        }
    }

    private function send_slack_notification($alert_data) {
        $webhook_url = function_exists('get_option') ? get_option('aps_slack_webhook_url') : null;
        if (!$webhook_url) {
            return;
        }

        $payload = [
            'text' => sprintf(
                "*APS Alert*\nType: %s\nLevel: %s\nMessage: %s",
                $alert_data['type'],
                strtoupper($alert_data['level']),
                $alert_data['message']
            )
        ];

        if (function_exists('wp_remote_post')) {
            try {
                $response = wp_remote_post($webhook_url, [
                    'body' => json_encode($payload),
                    'headers' => ['Content-Type' => 'application/json'],
                    'timeout' => 10 // 10 second timeout
                ]);

                if (is_wp_error($response)) {
                    $this->logger->error('Slack notification failed: ' . $response->get_error_message());
                }
            } catch (\Exception $e) {
                $this->logger->error('Slack notification failed: ' . $e->getMessage());
            }
        }
    }

    public function process_pending_alerts() {
        global $wpdb;
        
        $pending_alerts = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}aps_alerts 
             WHERE status = 'pending' 
             ORDER BY created_at ASC 
             LIMIT 50"
        );

        foreach ($pending_alerts as $alert) {
            $alert_data = json_decode($alert->alert_data, true);
            $this->process_notifications(
                $alert_data,
                $this->alert_config[$alert_data['type']]['channels']
            );

            $wpdb->update(
                $wpdb->prefix . 'aps_alerts',
                ['status' => 'processed'],
                ['id' => $alert->id]
            );
        }
    }

    public function clear_old_alerts() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}aps_alerts 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }

    private function is_alert_suppressed($type) {
        if (!isset($this->suppressed_alerts[$type])) {
            return false;
        }

        if (time() > $this->suppressed_alerts[$type]) {
            unset($this->suppressed_alerts[$type]);
            if (function_exists('update_option')) {
                update_option('aps_suppressed_alerts', $this->suppressed_alerts);
            }
            return false;
        }

        return true;
    }

    private function update_alert_cooldown($type) {
        if (!isset($this->alert_config[$type]['cooldown'])) {
            return;
        }

        $this->suppressed_alerts[$type] = time() + $this->alert_config[$type]['cooldown'];
        if (function_exists('update_option')) {
            update_option('aps_suppressed_alerts', $this->suppressed_alerts);
        }
    }

    public function cleanup_alerts() {
        global $wpdb;
        
        // Clean up processed alerts older than 7 days
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}aps_alerts 
             WHERE status = 'processed' 
             AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        // Archive critical alerts older than 30 days
        $wpdb->query(
            "UPDATE {$wpdb->prefix}aps_alerts 
             SET status = 'archived' 
             WHERE alert_level = 2 
             AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        // Remove non-critical alerts older than 30 days
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}aps_alerts 
             WHERE alert_level < 2 
             AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
    }

    public function get_active_alerts() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}aps_alerts 
             WHERE status = 'active' 
             ORDER BY created_at DESC"
        );
    }

    public function get_alerts_by_type($type, $limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}aps_alerts 
             WHERE alert_type = %s 
             ORDER BY created_at DESC 
             LIMIT %d",
            $type,
            $limit
        ));
    }

    public function acknowledge_alert($alert_id) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'aps_alerts',
            [
                'status' => 'acknowledged',
                'acknowledged_at' => current_time('mysql'),
                'acknowledged_by' => function_exists('get_current_user_id') ? get_current_user_id() : 0
            ],
            ['id' => $alert_id]
        );
    }
}