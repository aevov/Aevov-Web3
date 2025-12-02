<?php
/**
 * Monitoring Widget
 *
 * Real-time monitoring interface for Vision Depth activity
 *
 * @package AevovVisionDepth\Dashboard
 * @since 1.0.0
 */

namespace AevovVisionDepth\Dashboard;

class Monitoring_Widget {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function render() {
        $user_id = get_current_user_id();
        $stats = $this->get_live_stats($user_id);

        ?>
        <div class="avd-monitoring-widget">
            <div class="avd-stat-row">
                <span class="label"><?php _e('Scrapes Today:', 'aevov-vision-depth'); ?></span>
                <span class="value"><?php echo number_format($stats['scrapes_today']); ?></span>
            </div>

            <div class="avd-stat-row">
                <span class="label"><?php _e('Patterns Found:', 'aevov-vision-depth'); ?></span>
                <span class="value"><?php echo number_format($stats['patterns_found']); ?></span>
            </div>

            <div class="avd-stat-row">
                <span class="label"><?php _e('AevCoin Earned:', 'aevov-vision-depth'); ?></span>
                <span class="value"><?php echo number_format($stats['rewards_earned'], 4); ?></span>
            </div>

            <div class="avd-stat-row">
                <span class="label"><?php _e('Privacy Mode:', 'aevov-vision-depth'); ?></span>
                <span class="value"><?php echo esc_html($stats['privacy_mode']); ?></span>
            </div>

            <div class="avd-stat-row">
                <span class="label"><?php _e('Rate Limit:', 'aevov-vision-depth'); ?></span>
                <span class="value"><?php echo $stats['rate_limit_remaining']; ?>/<?php echo $stats['rate_limit_max']; ?></span>
            </div>
        </div>
        <?php
    }

    public function get_live_stats($user_id) {
        // Scrapes today
        $scrapes_today = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}avd_scraped_data
             WHERE user_id = %d AND DATE(created_at) = CURDATE()",
            $user_id
        ));

        // Patterns found
        $patterns_found = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}avd_behavioral_patterns
             WHERE user_id = %d",
            $user_id
        ));

        // Rewards earned
        $rewards_earned = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(amount) FROM {$this->wpdb->prefix}avd_user_rewards
             WHERE user_id = %d",
            $user_id
        ));

        // Privacy mode
        $consent = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT privacy_mode FROM {$this->wpdb->prefix}avd_user_consent
             WHERE user_id = %d",
            $user_id
        ));

        // Rate limit
        $rate_limit_used = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(count) FROM {$this->wpdb->prefix}avd_rate_limits
             WHERE user_id = %d AND action_type = 'scrape'
             AND window_start >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            $user_id
        ));

        $rate_limit_max = get_option('avd_rate_limit_per_minute', 100);

        return [
            'scrapes_today' => intval($scrapes_today),
            'patterns_found' => intval($patterns_found),
            'rewards_earned' => floatval($rewards_earned),
            'privacy_mode' => $consent ? $consent->privacy_mode : 'balanced',
            'rate_limit_remaining' => max(0, $rate_limit_max - intval($rate_limit_used)),
            'rate_limit_max' => $rate_limit_max,
        ];
    }
}
