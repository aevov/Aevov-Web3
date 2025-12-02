<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap aps-tools">
    <h1><?php _e('Pattern Comparison', 'aps-tools'); ?></h1>

    <div class="aps-comparison-container">
        <div class="comparison-form-card">
            <h2><?php _e('Compare Patterns', 'aps-tools'); ?></h2>
            <form id="pattern-comparison-form" class="aps-form">
                <div class="form-row">
                    <label><?php _e('Select Patterns', 'aps-tools'); ?></label>
                    <div id="pattern-selector" class="pattern-selector">
                        <!-- Populated via JavaScript -->
                    </div>
                    <button type="button" class="button add-pattern">
                        <?php _e('Add Pattern', 'aps-tools'); ?>
                    </button>
                </div>

                <div class="form-row">
                    <label for="comparison_type"><?php _e('Comparison Type', 'aps-tools'); ?></label>
                    <select id="comparison_type" name="comparison_type" required>
                        <option value="similarity"><?php _e('Similarity', 'aps-tools'); ?></option>
                        <option value="difference"><?php _e('Difference', 'aps-tools'); ?></option>
                        <option value="structural"><?php _e('Structural', 'aps-tools'); ?></option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Compare', 'aps-tools'); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="comparison-results-card">
            <h2><?php _e('Comparison Results', 'aps-tools'); ?></h2>
            <div id="comparison-results">
                <!-- Populated via JavaScript -->
            </div>
        </div>

        <div class="comparison-history-card">
            <h2><?php _e('Recent Comparisons', 'aps-tools'); ?></h2>
            <div id="comparison-history" class="scrollable-list">
                <?php if (!empty($recent_comparisons)): ?>
                    <?php foreach ($recent_comparisons as $comparison): ?>
                        <div class="history-item">
                            <div class="item-header">
                                <span class="item-id">#<?php echo esc_html($comparison->id); ?></span>
                                <span class="item-date"><?php echo esc_html($comparison->created_at); ?></span>
                            </div>
                            <div class="item-content">
                                <span class="item-type"><?php echo esc_html($comparison->comparison_type); ?></span>
                                <span class="item-score"><?php echo esc_html(number_format($comparison->match_score * 100, 1)); ?>%</span>
                            </div>
                            <div class="item-actions">
                                <button class="button button-small view-comparison" data-id="<?php echo esc_attr($comparison->id); ?>">
                                    <?php _e('View', 'aps-tools'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-items"><?php _e('No recent comparisons found.', 'aps-tools'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>