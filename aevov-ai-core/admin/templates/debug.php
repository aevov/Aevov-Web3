<?php
/**
 * Debug Dashboard Template
 *
 * @package AevovAICore
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$debug_engine = $GLOBALS['aevov_ai_core']->get_debug_engine();
$dashboard_data = $debug_engine->get_dashboard_data();
$system_info = $dashboard_data['system_info'];
$error_counts = $dashboard_data['error_counts'];
$recent_logs = $dashboard_data['recent_logs'];
?>

<div class="wrap aevov-ai-debug">
    <h1>
        <?php echo esc_html(get_admin_page_title()); ?>
        <button type="button" class="page-title-action" id="aevov-clear-logs">Clear Old Logs</button>
    </h1>

    <div class="aevov-dashboard-grid">
        <!-- System Info -->
        <div class="aevov-card">
            <h2>System Information</h2>
            <table class="aevov-info-table">
                <tr>
                    <th>PHP Version</th>
                    <td><?php echo esc_html($system_info['php_version']); ?></td>
                </tr>
                <tr>
                    <th>WordPress Version</th>
                    <td><?php echo esc_html($system_info['wordpress_version']); ?></td>
                </tr>
                <tr>
                    <th>MySQL Version</th>
                    <td><?php echo esc_html($system_info['mysql_version']); ?></td>
                </tr>
                <tr>
                    <th>Memory Limit</th>
                    <td><?php echo esc_html($system_info['memory_limit']); ?></td>
                </tr>
                <tr>
                    <th>Memory Usage</th>
                    <td><?php echo esc_html($system_info['memory_usage']); ?></td>
                </tr>
                <tr>
                    <th>Peak Memory</th>
                    <td><?php echo esc_html($system_info['peak_memory']); ?></td>
                </tr>
                <tr>
                    <th>Debug Enabled</th>
                    <td>
                        <?php if ($system_info['debug_enabled']): ?>
                            <span class="aevov-status aevov-status-active">Yes</span>
                        <?php else: ?>
                            <span class="aevov-status aevov-status-inactive">No</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Debug Level</th>
                    <td><?php echo esc_html(strtoupper($system_info['debug_level'])); ?></td>
                </tr>
            </table>
        </div>

        <!-- Error Summary -->
        <div class="aevov-card">
            <h2>Errors (Last 24 Hours)</h2>
            <div class="aevov-error-summary">
                <?php foreach ($error_counts as $error): ?>
                    <div class="aevov-error-stat aevov-error-<?php echo esc_attr($error['level']); ?>">
                        <span class="aevov-error-level"><?php echo esc_html(strtoupper($error['level'])); ?></span>
                        <span class="aevov-error-count"><?php echo esc_html($error['count']); ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($error_counts)): ?>
                    <p>No errors in the last 24 hours</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- API Performance -->
        <div class="aevov-card">
            <h2>API Performance</h2>
            <?php if (!empty($dashboard_data['api_metrics'])): ?>
                <table class="aevov-table">
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Requests</th>
                            <th>Avg Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard_data['api_metrics'] as $provider => $metrics): ?>
                            <tr>
                                <td><?php echo esc_html(ucfirst($provider)); ?></td>
                                <td><?php echo esc_html($metrics['total_requests']); ?></td>
                                <td><?php echo number_format($metrics['avg_duration'] * 1000, 0); ?>ms</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No API requests recorded</p>
            <?php endif; ?>
        </div>

        <!-- Active Profiles -->
        <div class="aevov-card">
            <h2>Active Profiles</h2>
            <?php if (!empty($dashboard_data['active_profiles'])): ?>
                <ul class="aevov-profile-list">
                    <?php foreach ($dashboard_data['active_profiles'] as $profile): ?>
                        <li>
                            <strong><?php echo esc_html($profile['name']); ?></strong>
                            - Running for <?php echo number_format((microtime(true) - $profile['start_time']) * 1000, 0); ?>ms
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No active profiles</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Logs -->
    <div class="aevov-card">
        <h2>Recent Logs</h2>

        <div class="aevov-log-filters">
            <label>
                Level:
                <select id="aevov-log-level">
                    <option value="">All</option>
                    <option value="debug">Debug</option>
                    <option value="info">Info</option>
                    <option value="warning">Warning</option>
                    <option value="error">Error</option>
                    <option value="critical">Critical</option>
                </select>
            </label>

            <label>
                Component:
                <select id="aevov-log-component">
                    <option value="">All</option>
                    <option value="API">API</option>
                    <option value="Database">Database</option>
                    <option value="Cache">Cache</option>
                    <option value="ProviderManager">Provider Manager</option>
                    <option value="ModelManager">Model Manager</option>
                </select>
            </label>

            <button type="button" class="button" id="aevov-refresh-logs">Refresh</button>
        </div>

        <div id="aevov-logs-container">
            <?php if (!empty($recent_logs)): ?>
                <table class="aevov-logs-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Level</th>
                            <th>Component</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                            <tr class="aevov-log-<?php echo esc_attr($log['level']); ?>">
                                <td><?php echo esc_html(date('H:i:s', strtotime($log['created_at']))); ?></td>
                                <td><span class="aevov-log-badge"><?php echo esc_html(strtoupper($log['level'])); ?></span></td>
                                <td><?php echo esc_html($log['component']); ?></td>
                                <td>
                                    <?php echo esc_html($log['message']); ?>
                                    <?php if (!empty($log['context'])): ?>
                                        <button
                                            type="button"
                                            class="button button-small aevov-view-context"
                                            data-context="<?php echo esc_attr(wp_json_encode($log['context'])); ?>"
                                        >
                                            View Context
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No logs found</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Context Modal -->
<div id="aevov-context-modal" class="aevov-modal" style="display:none;">
    <div class="aevov-modal-content">
        <span class="aevov-modal-close">&times;</span>
        <h2>Log Context</h2>
        <pre id="aevov-context-data"></pre>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Auto-refresh logs every 5 seconds
    var autoRefresh = setInterval(function() {
        refreshLogs();
    }, 5000);

    function refreshLogs() {
        var level = $('#aevov-log-level').val();
        var component = $('#aevov-log-component').val();

        $.get(ajaxurl, {
            action: 'aevov_get_logs',
            level: level,
            component: component,
            nonce: '<?php echo wp_create_nonce('aevov_get_logs'); ?>'
        }, function(response) {
            if (response.success) {
                // Update logs table
                // Implementation would update the table content
            }
        });
    }

    $('#aevov-refresh-logs').on('click', function() {
        refreshLogs();
    });

    $('#aevov-log-level, #aevov-log-component').on('change', function() {
        refreshLogs();
    });

    $('#aevov-clear-logs').on('click', function() {
        if (confirm('Clear logs older than 7 days?')) {
            $.post(ajaxurl, {
                action: 'aevov_clear_logs',
                nonce: '<?php echo wp_create_nonce('aevov_clear_logs'); ?>'
            }, function(response) {
                alert(response.data.message || 'Logs cleared');
                location.reload();
            });
        }
    });

    $('.aevov-view-context').on('click', function() {
        var context = $(this).data('context');
        $('#aevov-context-data').text(JSON.stringify(context, null, 2));
        $('#aevov-context-modal').show();
    });

    $('.aevov-modal-close').on('click', function() {
        $('.aevov-modal').hide();
    });
});
</script>
