<?php
/**
 * templates/shortcodes/comparison-form.php
 * Frontend comparison form template
 */
?>
<form class="aps-comparison-form" action="" method="post">
    <?php wp_nonce_field('aps_comparison_form', 'aps_nonce'); ?>
    
    <div class="aps-form-section">
        <h3><?php _e('Items to Compare', 'aps'); ?></h3>
        <div class="aps-items-container" data-max-items="<?php echo esc_attr($max_items); ?>">
            <div class="aps-item-fields">
                <div class="aps-item-field">
                    <input type="text" 
                           name="aps_items[]" 
                           class="aps-item-input" 
                           placeholder="<?php esc_attr_e('Enter item identifier', 'aps'); ?>"
                           required>
                    <button type="button" class="aps-remove-item" title="<?php esc_attr_e('Remove item', 'aps'); ?>">×</button>
                </div>
            </div>
            <button type="button" class="aps-add-item button">
                <?php _e('Add Another Item', 'aps'); ?>
            </button>
        </div>
    </div>

    <div class="aps-form-section">
        <h3><?php _e('Comparison Options', 'aps'); ?></h3>
        <div class="aps-options-grid">
            <div class="aps-option">
                <label for="aps_engine"><?php _e('Comparison Engine', 'aps'); ?></label>
                <select name="aps_engine" id="aps_engine">
                    <option value="auto"><?php _e('Automatic', 'aps'); ?></option>
                    <option value="pattern"><?php _e('Pattern-based', 'aps'); ?></option>
                    <option value="tensor"><?php _e('Tensor-based', 'aps'); ?></option>
                    <option value="hybrid"><?php _e('Hybrid', 'aps'); ?></option>
                </select>
            </div>

            <div class="aps-option">
                <label for="aps_threshold"><?php _e('Match Threshold', 'aps'); ?></label>
                <input type="range" 
                       name="aps_threshold" 
                       id="aps_threshold" 
                       min="0" 
                       max="100" 
                       value="75">
                <span class="aps-threshold-value">75%</span>
            </div>
        </div>
    </div>

    <div class="aps-form-actions">
        <button type="submit" class="button button-primary">
            <?php _e('Run Comparison', 'aps'); ?>
        </button>
    </div>
</form>