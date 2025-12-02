<?php
/**
 * APS Tools Integration
 *
 * Integrates Vision Depth with APS Tools for unified dashboard
 *
 * @package AevovVisionDepth\Integrations
 * @since 1.0.0
 */

namespace AevovVisionDepth\Integrations;

class APS_Tools_Integration {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function init() {
        // Add Vision Depth widget to APS Tools dashboard
        add_filter('aps_tools_dashboard_widgets', [$this, 'add_dashboard_widget']);

        // Add Vision Depth to system status
        add_filter('aps_tools_system_status', [$this, 'add_status_info']);
    }

    public function add_dashboard_widget($widgets) {
        $widgets['vision_depth'] = [
            'title' => __('Vision Depth Activity', 'aevov-vision-depth'),
            'callback' => [$this, 'render_widget'],
        ];
        return $widgets;
    }

    public function render_widget() {
        $stats = $this->get_statistics();

        echo '<div class="avd-widget">';
        echo '<ul>';
        echo '<li><strong>' . __('Total Scrapes:', 'aevov-vision-depth') . '</strong> ' . number_format($stats['total_scrapes']) . '</li>';
        echo '<li><strong>' . __('Patterns Discovered:', 'aevov-vision-depth') . '</strong> ' . number_format($stats['total_patterns']) . '</li>';
        echo '<li><strong>' . __('Consented Users:', 'aevov-vision-depth') . '</strong> ' . number_format($stats['consented_users']) . '</li>';
        echo '<li><strong>' . __('AevCoin Distributed:', 'aevov-vision-depth') . '</strong> ' . number_format($stats['total_rewards'], 4) . '</li>';
        echo '</ul>';
        echo '</div>';
    }

    public function add_status_info($status) {
        $status['vision_depth'] = [
            'status' => 'operational',
            'version' => AVD_VERSION,
            'integrations' => [
                'aps' => class_exists('\\APS\\Core\\APS_Core'),
                'bloom' => class_exists('\\BLOOM_Pattern_System'),
            ],
            'statistics' => $this->get_statistics(),
        ];
        return $status;
    }

    private function get_statistics() {
        $total_scrapes = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}avd_scraped_data"
        );

        $total_patterns = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}avd_behavioral_patterns"
        );

        $consented_users = $this->wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$this->wpdb->prefix}avd_user_consent
             WHERE consent_given = 1"
        );

        $total_rewards = $this->wpdb->get_var(
            "SELECT SUM(amount) FROM {$this->wpdb->prefix}avd_user_rewards
             WHERE status = 'distributed'"
        );

        return [
            'total_scrapes' => intval($total_scrapes),
            'total_patterns' => intval($total_patterns),
            'consented_users' => intval($consented_users),
            'total_rewards' => floatval($total_rewards),
        ];
    }
}
