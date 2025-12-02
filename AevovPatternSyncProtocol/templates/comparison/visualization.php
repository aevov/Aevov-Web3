<?php
/**
 * Template for rendering metrics shortcode
 */

defined('ABSPATH') || exit;

$metrics = aps_get_metrics($type);
?>

<div class="aps-metrics">
    <?php if ($type === 'summary'): ?>
        <div class="metric">
            <span class="metric-label"><?php _e('Patterns Processed', 'aps'); ?></span>
            <span class="metric-value"><?php echo esc_html($metrics['patterns_processed']); ?></span>
        </div>
        <div class="metric">
            <span class="metric-label"><?php _e('Processing Rate', 'aps'); ?></span>
            <span class="metric-value"><?php echo esc_html($metrics['processing_rate']); ?></span>
        </div>
        <div class="metric">
            <span class="metric-label"><?php _e('Error Rate', 'aps'); ?></span>
            <span class="metric-value"><?php echo esc_html($metrics['error_rate']); ?></span>
        </div>
    <?php else: ?>
        <div id="aps-metrics-chart"
             data-metrics="<?php echo esc_attr(json_encode($metrics)); ?>"></div>
    <?php endif; ?>
</div>