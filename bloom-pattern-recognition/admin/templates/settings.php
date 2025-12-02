<?php
require_once BLOOM_PATH . 'admin/templates/partials/header.php';
?>

<div class="settings-container">
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="bloom-form">
        <?php wp_nonce_field('bloom_save_settings'); ?>
        <input type="hidden" name="action" value="bloom_save_settings">

        <div class="settings-section bloom-card">
            <h2><?php _e('Network Settings', 'bloom-pattern-system'); ?></h2>
            
            <div class="bloom-form-group">
                <label for="chunk_size"><?php _e('Chunk Size (MB)', 'bloom-pattern-system'); ?></label>
                <input type="number" 
                       id="chunk_size" 
                       name="chunk_size" 
                       value="<?php echo esc_attr(get_option('bloom_chunk_size', 7)); ?>"
                       min="1" 
                       max="50"
                       class="bloom-input">
                <p class="form-help">
                    <?php _e('Size of tensor chunks for distributed processing', 'bloom-pattern-system'); ?>
                </p>
            </div>

            <div class="bloom-form-group">
                <label for="sites_per_chunk">
                    <?php _e('Sites Per Chunk', 'bloom-pattern-system'); ?>
                </label>
                <input type="number" 
                       id="sites_per_chunk" 
                       name="sites_per_chunk" 
                       value="<?php echo esc_attr(get_option('bloom_sites_per_chunk', 3)); ?>"
                       min="2" 
                       max="10"
                       class="bloom-input">
                <p class="form-help">
                    <?php _e('Number of sites to distribute each chunk to', 'bloom-pattern-system'); ?>
                </p>
            </div>

            <div class="bloom-form-group">
                <label for="sync_interval">
                    <?php _e('Sync Interval (seconds)', 'bloom-pattern-system'); ?>
                </label>
                <input type="number" 
                       id="sync_interval" 
                       name="sync_interval" 
                       value="<?php echo esc_attr(get_option('bloom_sync_interval', 300)); ?>"
                       min="60" 
                       max="3600"
                       class="bloom-input">
                <p class="form-help">
                    <?php _e('How often to synchronize data across the network', 'bloom-pattern-system'); ?>
                </p>
            </div>
        </div>

        <div class="settings-section bloom-card">
            <h2><?php _e('Processing Settings', 'bloom-pattern-system'); ?></h2>
            
            <div class="bloom-form-group">
                <label for="batch_size">
                    <?php _e('Processing Batch Size', 'bloom-pattern-system'); ?>
                </label>
                <input type="number" 
                       id="batch_size" 
                       name="batch_size" 
                       value="<?php echo esc_attr(get_option('bloom_batch_size', 100)); ?>"
                       min="10" 
                       max="1000"
                       class="bloom-input">
                <p class="form-help">
                    <?php _e('Number of patterns to process in each batch', 'bloom-pattern-system'); ?>
                </p>
            </div>

            <div class="bloom-form-group">
                <label for="confidence_threshold">
                    <?php _e('Confidence Threshold', 'bloom-pattern-system'); ?>
                </label>
                <input type="number" 
                       id="confidence_threshold" 
                       name="confidence_threshold" 
                       value="<?php echo esc_attr(get_option('bloom_confidence_threshold', 0.75)); ?>"
                       min="0" 
                       max="1" 
                       step="0.01"
                       class="bloom-input">
                <p class="form-help">
                    <?php _e('Minimum confidence score for pattern recognition', 'bloom-pattern-system'); ?>
                </p>
            </div>
        </div>

        <div class="settings-section bloom-card">
            <h2><?php _e('Integration Settings', 'bloom-pattern-system'); ?></h2>
            
            <div class="bloom-form-group">
                <label for="typebot_webhook">
                    <?php _e('Typebot Webhook URL', 'bloom-pattern-system'); ?>
                </label>
                <input type="url" 
                       id="typebot_webhook" 
                       name="typebot_webhook" 
                       value="<?php echo esc_url(get_option('bloom_typebot_webhook')); ?>"
                       class="bloom-input">
            </div>

            <div class="bloom-form-group">
                <label for="typebot_secret">
                    <?php _e('Typebot Secret', 'bloom-pattern-system'); ?>
                </label>
                <div class="input-group">
                    <input type="text" 
                           id="typebot_secret" 
                           name="typebot_secret" 
                           value="<?php echo esc_attr(get_option('bloom_typebot_secret')); ?>"
                           class="bloom-input"
                           readonly>
                    <button type="button" 
                            id="generate-secret" 
                            class="bloom-button bloom-button-secondary">
                        <?php _e('Generate New', 'bloom-pattern-system'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="settings-actions">
            <button type="submit" class="bloom-button bloom-button-primary">
                <?php _e('Save Settings', 'bloom-pattern-system'); ?>
            </button>
            <button type="reset" class="bloom-button bloom-button-secondary">
                <?php _e('Reset', 'bloom-pattern-system'); ?>
            </button>
        </div>
    </form>
</div>

<?php require_once BLOOM_PATH . 'admin/templates/partials/footer.php'; ?>