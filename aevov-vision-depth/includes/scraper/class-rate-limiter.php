<?php
/**
 * Rate Limiter
 *
 * Implements sliding window rate limiting to prevent abuse.
 *
 * @package AevovVisionDepth\Scraper
 * @since 1.0.0
 */

namespace AevovVisionDepth\Scraper;

class Rate_Limiter {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function check_limit($user_id, $action_type) {
        $now = current_time('mysql');

        // Check per-second limit (10/sec)
        $count_sec = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(count) FROM {$this->wpdb->prefix}avd_rate_limits
             WHERE user_id = %d AND action_type = %s
             AND window_start >= DATE_SUB(%s, INTERVAL 1 SECOND)",
            $user_id, $action_type, $now
        ));

        if ($count_sec >= get_option('avd_rate_limit_per_second', 10)) {
            return false;
        }

        // Check per-minute limit (100/min)
        $count_min = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(count) FROM {$this->wpdb->prefix}avd_rate_limits
             WHERE user_id = %d AND action_type = %s
             AND window_start >= DATE_SUB(%s, INTERVAL 1 MINUTE)",
            $user_id, $action_type, $now
        ));

        if ($count_min >= get_option('avd_rate_limit_per_minute', 100)) {
            return false;
        }

        // Check per-hour limit (1000/hour)
        $count_hour = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(count) FROM {$this->wpdb->prefix}avd_rate_limits
             WHERE user_id = %d AND action_type = %s
             AND window_start >= DATE_SUB(%s, INTERVAL 1 HOUR)",
            $user_id, $action_type, $now
        ));

        if ($count_hour >= get_option('avd_rate_limit_per_hour', 1000)) {
            return false;
        }

        return true;
    }

    public function record_action($user_id, $action_type) {
        $this->wpdb->insert(
            $this->wpdb->prefix . 'avd_rate_limits',
            [
                'user_id' => $user_id,
                'action_type' => $action_type,
                'count' => 1,
                'window_start' => current_time('mysql'),
            ],
            ['%d', '%s', '%d', '%s']
        );

        // Cleanup old records (older than 1 hour)
        $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->wpdb->prefix}avd_rate_limits
             WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        ));
    }
}
