<?php
/**
 * templates/comparison/comparison-grid.php
 * Grid view template for multiple comparisons
 */
?>
<div class="aps-comparisons-grid">
    <?php if (!empty($comparisons)): ?>
        <?php foreach ($comparisons as $comparison): ?>
            <div class="aps-comparison-card">
                <div class="aps-comparison-card-header">
                    <h3><a href="<?php echo esc_url($this->get_comparison_url($comparison->id)); ?>">
                        <?php echo esc_html($comparison->title); ?>
                    </a></h3>
                    <span class="aps-type-badge"><?php echo esc_html($comparison->comparison_type); ?></span>
                </div>
                <div class="aps-comparison-card-content">
                    <div class="aps-stats">
                        <div class="aps-stat">
                            <span class="aps-stat-label"><?php _e('Items', 'aps'); ?></span>
                            <span class="aps-stat-value"><?php echo count($comparison->items); ?></span>
                        </div>
                        <div class="aps-stat">
                            <span class="aps-stat-label"><?php _e('Score', 'aps'); ?></span>
                            <span class="aps-stat-value"><?php echo number_format($comparison->average_score * 100, 1); ?>%</span>
                        </div>
                    </div>
                </div>
                <div class="aps-comparison-card-footer">
                    <time datetime="<?php echo esc_attr($comparison->created_at); ?>">
                        <?php echo esc_html(human_time_diff(strtotime($comparison->created_at), current_time('timestamp'))); ?>
                    </time>
                    <a href="<?php echo esc_url($this->get_comparison_url($comparison->id)); ?>" class="button">
                        <?php _e('View Details', 'aps'); ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="aps-no-comparisons"><?php _e('No comparisons found', 'aps'); ?></p>
    <?php endif; ?>
</div>