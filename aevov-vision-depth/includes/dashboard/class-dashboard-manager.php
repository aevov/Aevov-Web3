<?php
/**
 * Dashboard Manager
 *
 * Manages Vision Depth dashboard and monitoring interfaces
 *
 * @package AevovVisionDepth\Dashboard
 * @since 1.0.0
 */

namespace AevovVisionDepth\Dashboard;

class Dashboard_Manager {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function init() {
        add_action('wp_footer', [$this, 'render_monitoring_button'], 100);
        add_action('wp_ajax_avd_get_stats', [$this, 'ajax_get_stats']);
    }

    public function render_main_page() {
        $stats = $this->get_dashboard_statistics();
        $recent_activity = $this->get_recent_activity();

        ?>
        <div class="wrap">
            <h1><?php _e('Vision Depth Dashboard', 'aevov-vision-depth'); ?></h1>

            <div class="avd-stats-grid">
                <div class="avd-stat-card">
                    <h3><?php _e('Total Scrapes', 'aevov-vision-depth'); ?></h3>
                    <div class="stat-value"><?php echo number_format($stats['total_scrapes']); ?></div>
                </div>

                <div class="avd-stat-card">
                    <h3><?php _e('Patterns Discovered', 'aevov-vision-depth'); ?></h3>
                    <div class="stat-value"><?php echo number_format($stats['total_patterns']); ?></div>
                </div>

                <div class="avd-stat-card">
                    <h3><?php _e('AevCoin Earned', 'aevov-vision-depth'); ?></h3>
                    <div class="stat-value"><?php echo number_format($stats['total_rewards'], 4); ?></div>
                </div>

                <div class="avd-stat-card">
                    <h3><?php _e('Consented Users', 'aevov-vision-depth'); ?></h3>
                    <div class="stat-value"><?php echo number_format($stats['consented_users']); ?></div>
                </div>
            </div>

            <h2><?php _e('Recent Activity', 'aevov-vision-depth'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'aevov-vision-depth'); ?></th>
                        <th><?php _e('User', 'aevov-vision-depth'); ?></th>
                        <th><?php _e('Action', 'aevov-vision-depth'); ?></th>
                        <th><?php _e('Description', 'aevov-vision-depth'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_activity as $activity): ?>
                    <tr>
                        <td><?php echo esc_html($activity['created_at']); ?></td>
                        <td><?php echo esc_html($this->get_user_name($activity['user_id'])); ?></td>
                        <td><?php echo esc_html($activity['action_type']); ?></td>
                        <td><?php echo esc_html($activity['action_description']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_monitoring_button() {
        if (!is_user_logged_in()) return;
        if (!vision_depth()->privacy->user_has_consented()) return;

        ?>
        <div id="avd-monitoring-button" style="position:fixed;bottom:20px;right:20px;z-index:9999;">
            <button class="button button-primary" id="avd-toggle-monitor">
                <?php _e('Vision Depth Field', 'aevov-vision-depth'); ?>
            </button>
        </div>

        <div id="avd-monitoring-widget" style="display:none;position:fixed;bottom:80px;right:20px;width:400px;max-height:600px;background:#fff;border:1px solid #ccc;box-shadow:0 2px 10px rgba(0,0,0,0.1);z-index:9998;padding:20px;">
            <h3><?php _e('Activity Monitor', 'aevov-vision-depth'); ?></h3>
            <div id="avd-stats-container"></div>
            <button id="avd-close-monitor" class="button"><?php _e('Close', 'aevov-vision-depth'); ?></button>
        </div>
        <?php
    }

    private function get_dashboard_statistics() {
        return [
            'total_scrapes' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}avd_scraped_data"
            ),
            'total_patterns' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}avd_behavioral_patterns"
            ),
            'total_rewards' => $this->wpdb->get_var(
                "SELECT SUM(amount) FROM {$this->wpdb->prefix}avd_user_rewards
                 WHERE status = 'distributed'"
            ),
            'consented_users' => $this->wpdb->get_var(
                "SELECT COUNT(DISTINCT user_id) FROM {$this->wpdb->prefix}avd_user_consent
                 WHERE consent_given = 1"
            ),
        ];
    }

    private function get_recent_activity($limit = 50) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}avd_activity_log
             ORDER BY created_at DESC LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    private function get_user_name($user_id) {
        if (!$user_id) return __('System', 'aevov-vision-depth');
        $user = get_userdata($user_id);
        return $user ? $user->display_name : __('Unknown', 'aevov-vision-depth');
    }

    public function ajax_get_stats() {
        check_ajax_referer('avd_frontend_' . get_current_user_id(), 'nonce');

        $user_id = get_current_user_id();
        $stats = [
            'user_scrapes' => $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}avd_scraped_data WHERE user_id = %d",
                $user_id
            )),
            'user_patterns' => $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->wpdb->prefix}avd_behavioral_patterns WHERE user_id = %d",
                $user_id
            )),
            'user_rewards' => $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT SUM(amount) FROM {$this->wpdb->prefix}avd_user_rewards
                 WHERE user_id = %d",
                $user_id
            )),
        ];

        wp_send_json_success($stats);
    }
}
