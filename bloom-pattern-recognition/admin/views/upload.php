
<?php
require_once BLOOM_PATH . 'admin/templates/partials/header.php';
?>

<div class="upload-container">
    <div class="upload-methods">
        <div class="upload-section bloom-card">
            <h2><?php _e('Upload via URL', 'bloom-pattern-system'); ?></h2>
            <form id="tensor-url-form" class="bloom-form">
                <?php wp_nonce_field('bloom_tensor_upload'); ?>
                <div class="bloom-form-group">
                    <label for="tensor_url">
                        <?php _e('Tensor URL', 'bloom-pattern-system'); ?>
                    </label>
                    <input type="url" 
                           id="tensor_url" 
                           name="tensor_url" 
                           class="bloom-input" 
                           required>
                </div>
                <button type="submit" class="bloom-button bloom-button-primary">
                    <?php _e('Process URL', 'bloom-pattern-system'); ?>
                </button>
            </form>
        </div>

        <div class="upload-section bloom-card">
            <h2><?php _e('Upload File', 'bloom-pattern-system'); ?></h2>
            <form id="tensor-file-form" class="bloom-form">
                <?php wp_nonce_field('bloom_tensor_upload'); ?>
                <div class="bloom-form-group">
                    <label for="tensor_file">
                        <?php _e('Tensor File', 'bloom-pattern-system'); ?>
                    </label>
                    <input type="file" 
                           id="tensor_file" 
                           name="tensor_file" 
                           accept=".json,.safetensors,.chunk"
                           class="bloom-input"
                           required>
                </div>
                <div class="bloom-form-group">
                    <label>
                        <input type="checkbox" 
                               name="process_immediately"
                               checked>
                        <?php _e('Process Immediately', 'bloom-pattern-system'); ?>
                    </label>
                </div>
                <button type="submit" class="bloom-button bloom-button-primary">
                    <?php _e('Upload', 'bloom-pattern-system'); ?>
                </button>
            </form>
        </div>

        <div class="upload-section bloom-card">
            <h2><?php _e('Process Local Path', 'bloom-pattern-system'); ?></h2>
            <form id="tensor-path-form" class="bloom-form">
                <?php wp_nonce_field('bloom_tensor_upload'); ?>
                <div class="bloom-form-group">
                    <label for="tensor_path">
                        <?php _e('Local Path', 'bloom-pattern-system'); ?>
                    </label>
                    <input type="text" 
                           id="tensor_path" 
                           name="tensor_path" 
                           class="bloom-input"
                           required>
                    <p class="form-help">
                        <?php _e('Absolute path to tensor file or directory', 'bloom-pattern-system'); ?>
                    </p>
                </div>
                <button type="submit" class="bloom-button bloom-button-primary">
                    <?php _e('Process', 'bloom-pattern-system'); ?>
                </button>
            </form>
        </div>
    </div>

    <div class="upload-status bloom-card">
        <h2><?php _e('Processing Status', 'bloom-pattern-system'); ?></h2>
        <div id="processing-progress"></div>
        <div id="status-messages"></div>
    </div>
</div>

<?php require_once BLOOM_PATH . 'admin/templates/partials/footer.php'; ?>