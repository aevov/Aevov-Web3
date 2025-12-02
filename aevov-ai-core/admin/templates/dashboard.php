<?php
/**
 * Admin Dashboard Template
 *
 * @package AevovAICore
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$provider_manager = $GLOBALS['aevov_ai_core']->get_provider_manager();
$model_manager = $GLOBALS['aevov_ai_core']->get_model_manager();
$debug_engine = $GLOBALS['aevov_ai_core']->get_debug_engine();

$providers = $provider_manager->get_all_providers();
$model_stats = $model_manager->get_statistics();
$usage_stats = $provider_manager->get_usage_stats();
?>

<div class="wrap aevov-ai-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="aevov-dashboard-grid">
        <!-- System Status -->
        <div class="aevov-card">
            <h2>System Status</h2>
            <div class="aevov-status-grid">
                <div class="aevov-stat">
                    <span class="aevov-stat-label">Active Providers</span>
                    <span class="aevov-stat-value"><?php echo count(array_filter($providers, function ($p) {
                        return $p['configured'];
                    })); ?></span>
                </div>
                <div class="aevov-stat">
                    <span class="aevov-stat-label">Total Models</span>
                    <span class="aevov-stat-value"><?php echo esc_html($model_stats['total_models']); ?></span>
                </div>
                <div class="aevov-stat">
                    <span class="aevov-stat-label">API Requests (24h)</span>
                    <span class="aevov-stat-value"><?php echo esc_html($usage_stats['total_requests']); ?></span>
                </div>
                <div class="aevov-stat">
                    <span class="aevov-stat-label">Total Cost (24h)</span>
                    <span class="aevov-stat-value">$<?php echo number_format($usage_stats['total_cost'], 4); ?></span>
                </div>
            </div>
        </div>

        <!-- Providers Overview -->
        <div class="aevov-card">
            <h2>AI Providers</h2>
            <table class="aevov-table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Status</th>
                        <th>Models</th>
                        <th>Requests</th>
                        <th>Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($providers as $provider_name => $provider): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($provider['name']); ?></strong>
                            </td>
                            <td>
                                <?php if ($provider['configured']): ?>
                                    <span class="aevov-status aevov-status-active">Active</span>
                                <?php else: ?>
                                    <span class="aevov-status aevov-status-inactive">Not Configured</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo count($provider['models']); ?></td>
                            <td>
                                <?php
                                $provider_usage = $usage_stats['by_provider'][$provider_name] ?? null;
                                echo $provider_usage ? esc_html($provider_usage['request_count']) : '0';
                                ?>
                            </td>
                            <td>
                                <?php
                                echo $provider_usage ? '$' . number_format($provider_usage['total_cost'], 4) : '$0.00';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <a href="<?php echo admin_url('admin.php?page=aevov-ai-providers'); ?>" class="button">
                    Manage Providers
                </a>
            </p>
        </div>

        <!-- Recent Activity -->
        <div class="aevov-card">
            <h2>Recent Activity</h2>
            <?php
            $recent_logs = $debug_engine->get_recent_logs(10, ['level' => 'info']);
            if (!empty($recent_logs)):
            ?>
                <ul class="aevov-activity-list">
                    <?php foreach ($recent_logs as $log): ?>
                        <li>
                            <span class="aevov-activity-time">
                                <?php echo esc_html(human_time_diff(strtotime($log['created_at']), current_time('timestamp'))); ?> ago
                            </span>
                            <span class="aevov-activity-component">[<?php echo esc_html($log['component']); ?>]</span>
                            <?php echo esc_html($log['message']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No recent activity</p>
            <?php endif; ?>
            <p>
                <a href="<?php echo admin_url('admin.php?page=aevov-ai-debug'); ?>" class="button">
                    View Debug Dashboard
                </a>
            </p>
        </div>

        <!-- Quick Actions -->
        <div class="aevov-card">
            <h2>Quick Actions</h2>
            <div class="aevov-quick-actions">
                <a href="<?php echo admin_url('admin.php?page=aevov-ai-providers'); ?>" class="button button-primary">
                    Configure Providers
                </a>
                <a href="<?php echo admin_url('admin.php?page=aevov-ai-models'); ?>" class="button">
                    Manage Models
                </a>
                <button type="button" class="button" id="aevov-test-providers">Test All Providers</button>
                <button type="button" class="button" id="aevov-clear-cache">Clear Cache</button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#aevov-test-providers').on('click', function() {
        if (confirm('Test all configured providers?')) {
            $(this).prop('disabled', true).text('Testing...');

            $.post(ajaxurl, {
                action: 'aevov_test_providers',
                nonce: '<?php echo wp_create_nonce('aevov_test_providers'); ?>'
            }, function(response) {
                alert(response.data.message || 'Test complete');
                location.reload();
            }).fail(function() {
                alert('Test failed');
            });
        }
    });

    $('#aevov-clear-cache').on('click', function() {
        if (confirm('Clear all AI response caches?')) {
            $(this).prop('disabled', true).text('Clearing...');

            $.post(ajaxurl, {
                action: 'aevov_clear_cache',
                nonce: '<?php echo wp_create_nonce('aevov_clear_cache'); ?>'
            }, function(response) {
                alert(response.data.message || 'Cache cleared');
                location.reload();
            });
        }
    });
});
</script>
