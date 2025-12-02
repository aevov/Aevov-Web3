<?php
/**
 * Admin Page: Rate Limiting Configuration
 *
 * @package AevovCore
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_strategy = get_option('aevov_rate_limit_strategy', 'combined');
$use_redis = get_option('aevov_rate_limit_use_redis', true);
$enable_headers = get_option('aevov_rate_limit_headers', true);
$log_violations = get_option('aevov_rate_limit_log', true);
$redis_host = get_option('aevov_redis_host', 'redis');
$redis_port = get_option('aevov_redis_port', 6379);
?>

<div class="wrap">
    <h1><?php _e('Rate Limiting Configuration', 'aevov-core'); ?></h1>

    <p class="description">
        <?php _e('Configure rate limiting for all Aevov REST API endpoints to protect against abuse and ensure fair usage.', 'aevov-core'); ?>
    </p>

    <hr>

    <!-- Configuration Form -->
    <h2><?php _e('Settings', 'aevov-core'); ?></h2>

    <form method="post" action="">
        <?php wp_nonce_field('aevov_rate_limits'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="rate_limit_strategy"><?php _e('Limiting Strategy', 'aevov-core'); ?></label>
                </th>
                <td>
                    <select name="rate_limit_strategy" id="rate_limit_strategy" class="regular-text">
                        <option value="ip" <?php selected($current_strategy, 'ip'); ?>>
                            <?php _e('IP Address Only', 'aevov-core'); ?>
                        </option>
                        <option value="user" <?php selected($current_strategy, 'user'); ?>>
                            <?php _e('User ID Only', 'aevov-core'); ?>
                        </option>
                        <option value="combined" <?php selected($current_strategy, 'combined'); ?>>
                            <?php _e('Combined (User + IP)', 'aevov-core'); ?>
                        </option>
                        <option value="endpoint" <?php selected($current_strategy, 'endpoint'); ?>>
                            <?php _e('Endpoint Only', 'aevov-core'); ?>
                        </option>
                    </select>
                    <p class="description">
                        <?php _e('How to identify and track requesters. Combined is recommended for best security.', 'aevov-core'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Redis Storage', 'aevov-core'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="use_redis" value="1" <?php checked($use_redis); ?>>
                        <?php _e('Use Redis for rate limiting storage', 'aevov-core'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Recommended for production. Falls back to in-memory storage if Redis is unavailable.', 'aevov-core'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="redis_host"><?php _e('Redis Host', 'aevov-core'); ?></label>
                </th>
                <td>
                    <input type="text" name="redis_host" id="redis_host" class="regular-text"
                           value="<?php echo esc_attr($redis_host); ?>">
                    <p class="description"><?php _e('Redis server hostname or IP address', 'aevov-core'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="redis_port"><?php _e('Redis Port', 'aevov-core'); ?></label>
                </th>
                <td>
                    <input type="number" name="redis_port" id="redis_port" class="small-text"
                           value="<?php echo esc_attr($redis_port); ?>" min="1" max="65535">
                    <p class="description"><?php _e('Redis server port (default: 6379)', 'aevov-core'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Rate Limit Headers', 'aevov-core'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_headers" value="1" <?php checked($enable_headers); ?>>
                        <?php _e('Add X-RateLimit-* headers to API responses', 'aevov-core'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Helps API clients know their current rate limit status', 'aevov-core'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <?php _e('Violation Logging', 'aevov-core'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="log_violations" value="1" <?php checked($log_violations); ?>>
                        <?php _e('Log rate limit violations to database', 'aevov-core'); ?>
                    </label>
                    <p class="description">
                        <?php _e('Track violations for monitoring and abuse detection', 'aevov-core'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="aevov_save_rate_limits" class="button button-primary"
                   value="<?php _e('Save Settings', 'aevov-core'); ?>">
        </p>
    </form>

    <hr>

    <!-- Statistics -->
    <h2><?php _e('Rate Limit Statistics', 'aevov-core'); ?></h2>

    <?php if ($stats): ?>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <th><?php _e('Total Violations', 'aevov-core'); ?></th>
                    <td><strong><?php echo number_format((int) ($stats['total_violations'] ?? 0)); ?></strong></td>
                </tr>
                <tr>
                    <th><?php _e('Unique Identifiers', 'aevov-core'); ?></th>
                    <td><?php echo number_format((int) ($stats['unique_identifiers'] ?? 0)); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Unique Endpoints', 'aevov-core'); ?></th>
                    <td><?php echo number_format((int) ($stats['unique_endpoints'] ?? 0)); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Last Violation', 'aevov-core'); ?></th>
                    <td><?php echo $stats['last_violation'] ? esc_html($stats['last_violation']) : __('None', 'aevov-core'); ?></td>
                </tr>
            </tbody>
        </table>
    <?php else: ?>
        <p><?php _e('No violation statistics available yet.', 'aevov-core'); ?></p>
    <?php endif; ?>

    <hr>

    <!-- Clear Limits Form -->
    <h2><?php _e('Clear Rate Limits', 'aevov-core'); ?></h2>

    <p class="description">
        <?php _e('Clear rate limit counters for a specific identifier (IP address, user ID, or combined).', 'aevov-core'); ?>
    </p>

    <form method="post" action="">
        <?php wp_nonce_field('aevov_clear_limits'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="identifier"><?php _e('Identifier', 'aevov-core'); ?></label>
                </th>
                <td>
                    <input type="text" name="identifier" id="identifier" class="regular-text"
                           placeholder="e.g., ip:192.168.1.1 or user:123">
                    <p class="description">
                        <?php _e('Format: ip:192.168.1.1 or user:123 or user:123:ip:192.168.1.1', 'aevov-core'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="aevov_clear_limits" class="button"
                   value="<?php _e('Clear Limits', 'aevov-core'); ?>"
                   onclick="return confirm('<?php _e('Are you sure you want to clear rate limits for this identifier?', 'aevov-core'); ?>')">
        </p>
    </form>

    <!-- Default Limits Information -->
    <hr>
    <h2><?php _e('Default Rate Limits', 'aevov-core'); ?></h2>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php _e('Endpoint Pattern', 'aevov-core'); ?></th>
                <th><?php _e('Per Minute', 'aevov-core'); ?></th>
                <th><?php _e('Per Hour', 'aevov-core'); ?></th>
                <th><?php _e('Per Day', 'aevov-core'); ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong><?php _e('Default (All Endpoints)', 'aevov-core'); ?></strong></td>
                <td>60</td>
                <td>1,000</td>
                <td>10,000</td>
            </tr>
            <tr>
                <td><code>/aevov/v1/language/chat</code></td>
                <td>10</td>
                <td>100</td>
                <td>500</td>
            </tr>
            <tr>
                <td><code>/aevov/v1/image/generate</code></td>
                <td>5</td>
                <td>50</td>
                <td>200</td>
            </tr>
            <tr>
                <td><code>/aevov/v1/music/generate</code></td>
                <td>3</td>
                <td>30</td>
                <td>100</td>
            </tr>
        </tbody>
    </table>

    <p class="description">
        <?php _e('These limits can be customized using the aevov_rate_limit_endpoint_limits filter.', 'aevov-core'); ?>
    </p>

    <!-- Integration Information -->
    <div class="notice notice-info inline" style="margin-top: 20px;">
        <p>
            <strong><?php _e('For Developers:', 'aevov-core'); ?></strong><br>
            <?php _e('Use the following filter to customize rate limits for specific endpoints:', 'aevov-core'); ?>
        </p>
        <pre style="background: #f5f5f5; padding: 10px; border-radius: 3px;">add_filter('aevov_rate_limit_endpoint_limits', function($limits, $endpoint) {
    if (strpos($endpoint, '/my-custom-endpoint') !== false) {
        return [
            'minute' => 30,
            'hour' => 500,
            'day' => 5000,
        ];
    }
    return $limits;
}, 10, 2);</pre>
    </div>
</div>

<style>
.form-table th {
    width: 250px;
}
</style>
