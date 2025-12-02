<?php
/**
 * templates/comparison/single-comparison.php
 * Single comparison template for frontend display
 */
?>
<div class="aps-comparison" data-comparison-id="<?php echo esc_attr($comparison->id); ?>">
    <div class="aps-comparison-header">
        <h2><?php echo esc_html($comparison->title); ?></h2>
        <div class="aps-meta">
            <span class="aps-date"><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($comparison->created_at))); ?></span>
            <span class="aps-type"><?php echo esc_html($comparison->comparison_type); ?></span>
        </div>
    </div>

    <div class="aps-comparison-content">
        <?php if (!empty($comparison->items)): ?>
            <div class="aps-items-grid">
                <?php foreach ($comparison->items as $item): ?>
                    <div class="aps-item">
                        <div class="aps-item-header">
                            <h3><?php echo esc_html($item->title); ?></h3>
                            <span class="aps-score"><?php echo number_format($item->match_score * 100, 1); ?>%</span>
                        </div>
                        <div class="aps-item-details">
                            <?php $this->render_pattern_details($item->pattern_data); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="aps-comparison-results">
                <h3><?php _e('Comparison Results', 'aps'); ?></h3>
                <?php $this->render_comparison_results($comparison->results); ?>
            </div>
        <?php else: ?>
            <p class="aps-no-items"><?php _e('No items to compare', 'aps'); ?></p>
        <?php endif; ?>
    </div>
</div>