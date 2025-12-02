<?php
/**
 * Template for network status page
 */

defined('ABSPATH') || exit;

$network_status = $this->get_network_status();
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="network-controls">
        <div class="control-group">
            <button id="refresh-network" class="button button-primary">
                <?php _e('Refresh Status', 'aps'); ?>
            </button>
            <button id="sync-network" class="button button-secondary">
                <?php _e('Force Sync', 'aps'); ?>
            </button>
        </div>
        <div class="network-overview">
            <div class="overview-item">
                <span class="overview-label"><?php _e('Active Sites', 'aps'); ?></span>
                <span id="active-sites" class="overview-value">
                    <?php echo esc_html($network_status['active_sites']); ?>
                </span>
            </div>
            <div class="overview-item">
                <span class="overview-label"><?php _e('Last Sync', 'aps'); ?></span>
                <span id="last-sync" class="overview-value">
                    <?php echo esc_html($network_status['last_sync']); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="network-grid">
        <!-- Site Status -->
        <div class="network-card">
            <h3><?php _e('Site Status', 'aps'); ?></h3>
            <div class="site-list">
                <?php foreach ($network_status['sites'] as $site): ?>
                    <div class="site-item">
                        <div class="site-info">
                            <span class="site-name"><?php echo esc_html($site['name']); ?></span>
                            <span class="site-url"><?php echo esc_html($site['url']); ?></span>
                        </div>
                        <div class="site-metrics">
                            <div class="metric">
                                <span class="metric-label"><?php _e('Patterns', 'aps'); ?></span>
                                <span class="metric-value"><?php echo esc_html($site['pattern_count']); ?></span>
                            </div>
                            <div class="metric">
                                <span class="metric-label"><?php _e('Queue', 'aps'); ?></span>
                                <span class="metric-value"><?php echo esc_html($site['queue_size']); ?></span>
                            </div>
                        </div>
                        <div class="site-status <?php echo esc_attr($site['status']); ?>">
                            <?php echo esc_html($site['status']); ?>
                        </div>
                        <div class="site-actions">
                            <button class="button button-small sync-site" 
                                    data-site-id="<?php echo esc_attr($site['id']); ?>">
                                <?php _e('Sync', 'aps'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Pattern Distribution -->
        <div class="network-card">
            <h3><?php _e('Pattern Distribution', 'aps'); ?></h3>
            <div id="pattern-distribution-chart" class="chart-container"></div>
        </div>

        <!-- Network Load -->
        <div class="network-card">
            <h3><?php _e('Network Load', 'aps'); ?></h3>
            <div id="network-load-chart" class="chart-container"></div>
        </div>

        <!-- Sync History -->
        <div class="network-card">
            <h3><?php _e('Sync History', 'aps'); ?></h3>
            <div class="sync-history">
                <?php foreach ($network_status['sync_history'] as $sync): ?>
                    <div class="sync-item">
                        <div class="sync-time">
                            <?php echo esc_html($sync['timestamp']); ?>
                        </div>
                        <div class="sync-details">
                            <div class="sync-type">
                                <?php echo esc_html($sync['type']); ?>
                            </div>
                            <div class<div class="sync-stats">
                                <span class="stat">
                                    <?php _e('Patterns:', 'aps'); ?> 
                                    <?php echo esc_html($sync['patterns_synced']); ?>
                                </span>
                                <span class="stat">
                                    <?php _e('Sites:', 'aps'); ?> 
                                    <?php echo esc_html($sync['sites_synced']); ?>
                                </span>
                                <span class="stat">
                                    <?php _e('Duration:', 'aps'); ?> 
                                    <?php echo esc_html($sync['duration']); ?>s
                                </span>
                            </div>
                        </div>
                        <div class="sync-status <?php echo esc_attr($sync['status']); ?>">
                            <?php echo esc_html($sync['status']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Network Topology -->
        <div class="network-card full-width">
            <h3><?php _e('Network Topology', 'aps'); ?></h3>
            <div id="network-topology" class="topology-container"></div>
        </div>
    </div>
</div>

<!-- Templates for JavaScript -->
<script type="text/template" id="site-tooltip-template">
    <div class="site-tooltip">
        <h4><%- name %></h4>
        <div class="tooltip-metrics">
            <div class="tooltip-metric">
                <span class="label"><?php _e('Patterns', 'aps'); ?></span>
                <span class="value"><%- pattern_count %></span>
            </div>
            <div class="tooltip-metric">
                <span class="label"><?php _e('Processing Rate', 'aps'); ?></span>
                <span class="value"><%- processing_rate %>/min</span>
            </div>
            <div class="tooltip-metric">
                <span class="label"><?php _e('Success Rate', 'aps'); ?></span>
                <span class="value"><%- success_rate %>%</span>
            </div>
        </div>
    </div>
</script>