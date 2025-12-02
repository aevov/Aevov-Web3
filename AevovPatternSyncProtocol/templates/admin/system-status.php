<?php
/**
 * Template for system status page
 */

defined('ABSPATH') || exit;

$system_status = $this->get_system_status();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="aps-status-header">
        <div class="status-summary">
            <div class="status-item <?php echo esc_attr($system_status['health_status']); ?>">
                <span class="status-icon"></span>
                <span class="status-label"><?php _e('System Health', 'aps'); ?></span>
            </div>
        </div>
        <div class="status-actions">
            <button id="refresh-status" class="button button-primary">
                <?php _e('Refresh Status', 'aps'); ?>
            </button>
            <button id="download-report" class="button button-secondary">
                <?php _e('Download Report', 'aps'); ?>
            </button>
        </div>
    </div>

    <div class="aps-status-grid">
        <!-- System Information -->
        <div class="status-card">
            <h3><?php _e('System Information', 'aps'); ?></h3>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <td><?php _e('PHP Version', 'aps'); ?></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('WordPress Version', 'aps'); ?></td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('APS Version', 'aps'); ?></td>
                        <td><?php echo APS_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Memory Limit', 'aps'); ?></td>
                        <td><?php echo ini_get('memory_limit'); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Max Execution Time', 'aps'); ?></td>
                        <td><?php echo ini_get('max_execution_time'); ?>s</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Database Status -->
        <div class="status-card">
            <h3><?php _e('Database Status', 'aps'); ?></h3>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <td><?php _e('Database Size', 'aps'); ?></td>
                        <td><?php echo esc_html($system_status['database']['size']); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Pattern Count', 'aps'); ?></td>
                        <td><?php echo esc_html($system_status['database']['pattern_count']); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Last Optimization', 'aps'); ?></td>
                        <td><?php echo esc_html($system_status['database']['last_optimization']); ?></td>
                    </tr>
                </tbody>
            </table>
            <div class="card-actions">
                <button id="optimize-db" class="button button-secondary">
                    <?php _e('Optimize Database', 'aps'); ?>
                </button>
            </div>
        </div>

        <!-- File System Status -->
        <div class="status-card">
            <h3><?php _e('File System Status', 'aps'); ?></h3>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <td><?php _e('Upload Directory', 'aps'); ?></td>
                        <td><?php echo esc_html($system_status['filesystem']['upload_dir']); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Available Space', 'aps'); ?></td>
                        <td><?php echo esc_html($system_status['filesystem']['available_space']); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Permissions', 'aps'); ?></td>
                        <td><?php echo esc_html($system_status['filesystem']['permissions']); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Active Processes -->
        <div class="status-card">
            <h3><?php _e('Active Processes', 'aps'); ?></h3>
            <div class="process-list">
                <?php foreach ($system_status['processes'] as $process): ?>
                    <div class="process-item">
                        <span class="process-name"><?php echo esc_html($process['name']); ?></span>
                        <span class="process-status <?php echo esc_attr($process['status']); ?>">
                            <?php echo esc_html($process['status']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Error Log -->
        <div class="status-card">
            <h3><?php _e('Recent Errors', 'aps'); ?></h3>
            <div class="error-log">
                <?php if (!empty($system_status['errors'])): ?>
                    <?php foreach ($system_status['errors'] as $error): ?>
                        <div class="error-item">
                            <div class="error-time">
                                <?php echo esc_html($error['timestamp']); ?>
                            </div>
                            <div class="error-message">
                                <?php echo esc_html($error['message']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-errors"><?php _e('No recent errors found.', 'aps'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>