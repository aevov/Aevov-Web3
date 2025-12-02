<?php
/**
 * Admin Page: System Status
 *
 * @package AevovCore
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Aevov System Status', 'aevov-core'); ?></h1>

    <p class="description">
        <?php _e('System information and status for the Aevov ecosystem.', 'aevov-core'); ?>
    </p>

    <hr>

    <!-- Core Information -->
    <h2><?php _e('Core Information', 'aevov-core'); ?></h2>

    <table class="widefat striped">
        <tbody>
            <tr>
                <th style="width: 300px;"><?php _e('Aevov Core Version', 'aevov-core'); ?></th>
                <td><strong><?php echo esc_html($status['aevov_core_version']); ?></strong></td>
            </tr>
            <tr>
                <th><?php _e('WordPress Version', 'aevov-core'); ?></th>
                <td><?php echo esc_html($status['wordpress_version']); ?></td>
            </tr>
            <tr>
                <th><?php _e('PHP Version', 'aevov-core'); ?></th>
                <td>
                    <?php echo esc_html($status['php_version']); ?>
                    <?php if (version_compare($status['php_version'], '8.0', '>=')): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning" style="color: orange;"></span>
                        <?php _e('PHP 8.0+ recommended', 'aevov-core'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Memory Limit', 'aevov-core'); ?></th>
                <td><?php echo esc_html($status['memory_limit']); ?></td>
            </tr>
            <tr>
                <th><?php _e('Max Execution Time', 'aevov-core'); ?></th>
                <td><?php echo esc_html($status['max_execution_time']); ?> seconds</td>
            </tr>
            <tr>
                <th><?php _e('Upload Max Filesize', 'aevov-core'); ?></th>
                <td><?php echo esc_html($status['upload_max_filesize']); ?></td>
            </tr>
        </tbody>
    </table>

    <hr>

    <!-- PHP Extensions -->
    <h2><?php _e('PHP Extensions', 'aevov-core'); ?></h2>

    <table class="widefat striped">
        <tbody>
            <tr>
                <th style="width: 300px;"><?php _e('OpenSSL (for encryption)', 'aevov-core'); ?></th>
                <td>
                    <?php if ($status['openssl_available']): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <?php _e('Available', 'aevov-core'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                        <?php _e('Not Available - API key encryption will not work!', 'aevov-core'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('cURL (for API calls)', 'aevov-core'); ?></th>
                <td>
                    <?php if ($status['curl_available']): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <?php _e('Available', 'aevov-core'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                        <?php _e('Not Available - API integrations will not work!', 'aevov-core'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Redis Extension', 'aevov-core'); ?></th>
                <td>
                    <?php if ($status['redis_available']): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <?php _e('Available', 'aevov-core'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning" style="color: orange;"></span>
                        <?php _e('Not Available - Using fallback storage for rate limiting', 'aevov-core'); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <hr>

    <!-- Redis Connection Status -->
    <h2><?php _e('Redis Connection', 'aevov-core'); ?></h2>

    <table class="widefat striped">
        <tbody>
            <tr>
                <th style="width: 300px;"><?php _e('Redis Host', 'aevov-core'); ?></th>
                <td><?php echo esc_html(get_option('aevov_redis_host', 'redis')); ?></td>
            </tr>
            <tr>
                <th><?php _e('Redis Port', 'aevov-core'); ?></th>
                <td><?php echo esc_html(get_option('aevov_redis_port', 6379)); ?></td>
            </tr>
            <tr>
                <th><?php _e('Connection Status', 'aevov-core'); ?></th>
                <td>
                    <?php if (isset($status['redis_connected']) && $status['redis_connected']): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <?php _e('Connected', 'aevov-core'); ?>
                    <?php elseif (isset($status['redis_error'])): ?>
                        <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                        <?php echo esc_html($status['redis_error']); ?>
                    <?php elseif (!$status['redis_available']): ?>
                        <span class="dashicons dashicons-warning" style="color: orange;"></span>
                        <?php _e('Redis extension not installed', 'aevov-core'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                        <?php _e('Unable to connect', 'aevov-core'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (isset($status['redis_ping']) && $status['redis_ping']): ?>
                <tr>
                    <th><?php _e('Ping Test', 'aevov-core'); ?></th>
                    <td>
                        <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                        <?php _e('Success', 'aevov-core'); ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <hr>

    <!-- Aevov Plugins -->
    <h2><?php _e('Aevov Plugins', 'aevov-core'); ?></h2>

    <p class="description">
        <?php
        $active_count = array_filter($status['aevov_plugins'], function($plugin) {
            return $plugin['active'];
        });
        printf(
            __('%d Aevov plugins installed, %d active', 'aevov-core'),
            count($status['aevov_plugins']),
            count($active_count)
        );
        ?>
    </p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Plugin Name', 'aevov-core'); ?></th>
                <th><?php _e('Version', 'aevov-core'); ?></th>
                <th><?php _e('Status', 'aevov-core'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Sort plugins by active status first, then by name
            $plugins_sorted = $status['aevov_plugins'];
            uasort($plugins_sorted, function($a, $b) {
                if ($a['active'] === $b['active']) {
                    return strcmp($a['name'], $b['name']);
                }
                return $a['active'] ? -1 : 1;
            });
            ?>
            <?php foreach ($plugins_sorted as $plugin_path => $plugin): ?>
                <tr>
                    <td><strong><?php echo esc_html($plugin['name']); ?></strong></td>
                    <td><?php echo esc_html($plugin['version']); ?></td>
                    <td>
                        <?php if ($plugin['active']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                            <?php _e('Active', 'aevov-core'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-marker" style="color: gray;"></span>
                            <?php _e('Inactive', 'aevov-core'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <hr>

    <!-- REST API Test -->
    <h2><?php _e('REST API Test', 'aevov-core'); ?></h2>

    <p class="description">
        <?php _e('Test the Aevov Core REST API endpoint to ensure it\'s working correctly.', 'aevov-core'); ?>
    </p>

    <button type="button" class="button" id="test-api-button">
        <?php _e('Test API Endpoint', 'aevov-core'); ?>
    </button>

    <div id="api-test-result" style="margin-top: 10px;"></div>

    <!-- System Health Check -->
    <hr>
    <h2><?php _e('System Health', 'aevov-core'); ?></h2>

    <?php
    $health_issues = [];

    if (!$status['openssl_available']) {
        $health_issues[] = __('OpenSSL extension is not available - API key encryption will not work', 'aevov-core');
    }

    if (!$status['curl_available']) {
        $health_issues[] = __('cURL extension is not available - API integrations will not work', 'aevov-core');
    }

    if (version_compare($status['php_version'], '8.0', '<')) {
        $health_issues[] = __('PHP version is below 8.0 - some features may not work correctly', 'aevov-core');
    }

    if (isset($status['redis_available']) && $status['redis_available'] && (!isset($status['redis_connected']) || !$status['redis_connected'])) {
        $health_issues[] = __('Redis extension is available but connection failed - rate limiting will use fallback storage', 'aevov-core');
    }
    ?>

    <?php if (empty($health_issues)): ?>
        <div class="notice notice-success inline">
            <p>
                <span class="dashicons dashicons-yes-alt" style="color: green; font-size: 20px; vertical-align: middle;"></span>
                <strong><?php _e('All systems operational!', 'aevov-core'); ?></strong>
            </p>
        </div>
    <?php else: ?>
        <div class="notice notice-warning inline">
            <p><strong><?php _e('Health Issues Detected:', 'aevov-core'); ?></strong></p>
            <ul style="margin-left: 20px;">
                <?php foreach ($health_issues as $issue): ?>
                    <li><?php echo esc_html($issue); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Debug Information -->
    <hr>
    <h2><?php _e('Debug Information', 'aevov-core'); ?></h2>

    <details>
        <summary style="cursor: pointer;"><?php _e('Show Debug Information (for support)', 'aevov-core'); ?></summary>
        <pre style="background: #f5f5f5; padding: 15px; margin-top: 10px; border-radius: 3px; overflow: auto;"><?php
            echo esc_html(json_encode($status, JSON_PRETTY_PRINT));
        ?></pre>
    </details>
</div>

<script>
jQuery(document).ready(function($) {
    $('#test-api-button').on('click', function() {
        var button = $(this);
        var resultDiv = $('#api-test-result');

        button.prop('disabled', true).text('<?php _e('Testing...', 'aevov-core'); ?>');
        resultDiv.html('<p><?php _e('Testing API endpoint...', 'aevov-core'); ?></p>');

        $.ajax({
            url: '<?php echo esc_url(rest_url('aevov/v1/core/test')); ?>',
            method: 'GET',
            success: function(response) {
                resultDiv.html(
                    '<div class="notice notice-success inline"><p>' +
                    '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ' +
                    '<strong><?php _e('API Test Successful!', 'aevov-core'); ?></strong><br>' +
                    '<?php _e('Message:', 'aevov-core'); ?> ' + response.message + '<br>' +
                    '<?php _e('Version:', 'aevov-core'); ?> ' + response.version +
                    '</p></div>'
                );
            },
            error: function(xhr) {
                resultDiv.html(
                    '<div class="notice notice-error inline"><p>' +
                    '<span class="dashicons dashicons-dismiss" style="color: red;"></span> ' +
                    '<strong><?php _e('API Test Failed!', 'aevov-core'); ?></strong><br>' +
                    '<?php _e('Status:', 'aevov-core'); ?> ' + xhr.status + '<br>' +
                    '<?php _e('Error:', 'aevov-core'); ?> ' + (xhr.responseJSON ? xhr.responseJSON.message : xhr.statusText) +
                    '</p></div>'
                );
            },
            complete: function() {
                button.prop('disabled', false).text('<?php _e('Test API Endpoint', 'aevov-core'); ?>');
            }
        });
    });
});
</script>

<style>
table th {
    font-weight: 600;
}
.dashicons {
    vertical-align: middle;
    margin-right: 5px;
}
</style>
