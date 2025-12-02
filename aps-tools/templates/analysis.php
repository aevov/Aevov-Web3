<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap aps-tools">
    <h1><?php _e('Pattern Analysis', 'aps-tools'); ?></h1>

    <div class="aps-analysis-container">
        <div class="analysis-form-card">
            <h2><?php _e('Analyze Pattern', 'aps-tools'); ?></h2>
            <form id="pattern-analysis-form" class="aps-form">
                <div class="form-row">
                    <label for="pattern_type"><?php _e('Pattern Type', 'aps-tools'); ?></label>
                    <select id="pattern_type" name="pattern_type" required>
                        <option value="sequential"><?php _e('Sequential', 'aps-tools'); ?></option>
                        <option value="structural"><?php _e('Structural', 'aps-tools'); ?></option>
                        <option value="statistical"><?php _e('Statistical', 'aps-tools'); ?></option>
                        <option value="file"><?php _e('File', 'aps-tools'); ?></option>
                    </select>
                </div>

                <div class="form-row">
                    <label for="pattern_data"><?php _e('Pattern Data', 'aps-tools'); ?></label>
                    <textarea id="pattern_data" name="pattern_data" rows="10" required></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Analyze', 'aps-tools'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="clear-form">
                        <?php _e('Clear', 'aps-tools'); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="analysis-results-card">
            <h2><?php _e('Analysis Results', 'aps-tools'); ?></h2>
            <div id="analysis-results">
                <!-- Populated via JavaScript -->
            </div>
        </div>

        <div class="analysis-history-card">
            <h2><?php _e('Recent Analyses', 'aps-tools'); ?></h2>
            <div id="analysis-history" class="scrollable-list">
                <!-- Populated via JavaScript -->
            </div>
        </div>
    </div>
</div>