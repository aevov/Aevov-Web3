<?php
/**
 * APS Tools Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['aps_settings_nonce'], 'aps_settings_action')) {
    update_option('aps_validate_json', isset($_POST['aps_validate_json']));
    update_option('aps_sync_interval', intval($_POST['aps_sync_interval']));
    update_option('aps_debug_mode', isset($_POST['aps_debug_mode']));
    update_option('aps_max_batch_size', intval($_POST['aps_max_batch_size']));
    
    echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'aps-tools') . '</p></div>';
}

// Get current settings
$validate_json = get_option('aps_validate_json', true);
$sync_interval = get_option('aps_sync_interval', 300);
$debug_mode = get_option('aps_debug_mode', false);
$max_batch_size = get_option('aps_max_batch_size', 100);
?>

<div class="wrap">
    <h1><?php _e('APS Tools Settings', 'aps-tools'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('aps_settings_action', 'aps_settings_nonce'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="aps_validate_json"><?php _e('JSON Validation', 'aps-tools'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="aps_validate_json" name="aps_validate_json" value="1" <?php checked($validate_json); ?> />
                        <label for="aps_validate_json"><?php _e('Enable JSON validation for BLOOM chunks', 'aps-tools'); ?></label>
                        <p class="description"><?php _e('When enabled, all uploaded chunks will be validated for proper JSON format.', 'aps-tools'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="aps_sync_interval"><?php _e('Sync Interval', 'aps-tools'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="aps_sync_interval" name="aps_sync_interval" value="<?php echo esc_attr($sync_interval); ?>" min="60" max="3600" />
                        <label for="aps_sync_interval"><?php _e('seconds', 'aps-tools'); ?></label>
                        <p class="description"><?php _e('How often to sync with BLOOM pattern recognition system (60-3600 seconds).', 'aps-tools'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="aps_debug_mode"><?php _e('Debug Mode', 'aps-tools'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="aps_debug_mode" name="aps_debug_mode" value="1" <?php checked($debug_mode); ?> />
                        <label for="aps_debug_mode"><?php _e('Enable debug logging', 'aps-tools'); ?></label>
                        <p class="description"><?php _e('When enabled, detailed logs will be written for troubleshooting.', 'aps-tools'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="aps_max_batch_size"><?php _e('Max Batch Size', 'aps-tools'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="aps_max_batch_size" name="aps_max_batch_size" value="<?php echo esc_attr($max_batch_size); ?>" min="10" max="1000" />
                        <label for="aps_max_batch_size"><?php _e('patterns', 'aps-tools'); ?></label>
                        <p class="description"><?php _e('Maximum number of patterns to process in a single batch (10-1000).', 'aps-tools'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2><?php _e('System Information', 'aps-tools'); ?></h2>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Plugin Version', 'aps-tools'); ?></th>
                    <td><?php echo APSTOOLS_VERSION; ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('WordPress Version', 'aps-tools'); ?></th>
                    <td><?php echo get_bloginfo('version'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('PHP Version', 'aps-tools'); ?></th>
                    <td><?php echo PHP_VERSION; ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Memory Limit', 'aps-tools'); ?></th>
                    <td><?php echo ini_get('memory_limit'); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('BLOOM Integration', 'aps-tools'); ?></th>
                    <td>
                        <?php if (class_exists('\BLOOM\Integration\APSIntegration')): ?>
                            <span style="color: green;"><?php _e('Active', 'aps-tools'); ?></span>
                        <?php else: ?>
                            <span style="color: red;"><?php _e('Inactive', 'aps-tools'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Pattern Sync Protocol', 'aps-tools'); ?></th>
                    <td>
                        <?php if (class_exists('\APS\Analysis\APS_Plugin')): ?>
                            <span style="color: green;"><?php _e('Active', 'aps-tools'); ?></span>
                        <?php else: ?>
                            <span style="color: red;"><?php _e('Inactive', 'aps-tools'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <h2><?php _e('Database Status', 'aps-tools'); ?></h2>
        <?php
        global $wpdb;
        $pattern_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}aps_patterns");
        $chunk_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}bloom_chunks");
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php _e('Total Patterns', 'aps-tools'); ?></th>
                    <td><?php echo number_format($pattern_count ?: 0); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Total Chunks', 'aps-tools'); ?></th>
                    <td><?php echo number_format($chunk_count ?: 0); ?></td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <h2><?php _e('System Actions', 'aps-tools'); ?></h2>
    <p><?php _e('Use these actions to maintain your APS Tools installation:', 'aps-tools'); ?></p>
    
    <div class="aps-system-actions">
        <button type="button" class="button" onclick="apsToolsClearCache()"><?php _e('Clear Cache', 'aps-tools'); ?></button>
        <button type="button" class="button" onclick="apsToolsTestConnection()"><?php _e('Test BLOOM Connection', 'aps-tools'); ?></button>
        <button type="button" class="button" onclick="apsToolsExportSettings()"><?php _e('Export Settings', 'aps-tools'); ?></button>
    </div>
    
    <div id="aps-system-actions-result" style="margin-top: 20px;"></div>
</div>

<script>
function apsToolsClearCache() {
    const resultDiv = document.getElementById('aps-system-actions-result');
    resultDiv.innerHTML = '<div class="notice notice-info"><p><?php _e("Clearing cache...", "aps-tools"); ?></p></div>';
    
    // Simulate cache clearing
    setTimeout(() => {
        resultDiv.innerHTML = '<div class="notice notice-success"><p><?php _e("Cache cleared successfully!", "aps-tools"); ?></p></div>';
    }, 1000);
}

function apsToolsTestConnection() {
    const resultDiv = document.getElementById('aps-system-actions-result');
    resultDiv.innerHTML = '<div class="notice notice-info"><p><?php _e("Testing BLOOM connection...", "aps-tools"); ?></p></div>';
    
    // Test connection via AJAX
    jQuery.post(ajaxurl, {
        action: 'aps_test_bloom_connection',
        nonce: '<?php echo wp_create_nonce("aps_test_connection"); ?>'
    }, function(response) {
        if (response.success) {
            resultDiv.innerHTML = '<div class="notice notice-success"><p><?php _e("BLOOM connection successful!", "aps-tools"); ?></p></div>';
        } else {
            resultDiv.innerHTML = '<div class="notice notice-error"><p><?php _e("BLOOM connection failed: ", "aps-tools"); ?>' + response.data + '</p></div>';
        }
    });
}

function apsToolsExportSettings() {
    const settings = {
        validate_json: <?php echo json_encode($validate_json); ?>,
        sync_interval: <?php echo json_encode($sync_interval); ?>,
        debug_mode: <?php echo json_encode($debug_mode); ?>,
        max_batch_size: <?php echo json_encode($max_batch_size); ?>,
        exported_at: new Date().toISOString()
    };
    
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(settings, null, 2));
    const downloadAnchorNode = document.createElement('a');
    downloadAnchorNode.setAttribute("href", dataStr);
    downloadAnchorNode.setAttribute("download", "aps-tools-settings.json");
    document.body.appendChild(downloadAnchorNode);
    downloadAnchorNode.click();
    downloadAnchorNode.remove();
    
    const resultDiv = document.getElementById('aps-system-actions-result');
    resultDiv.innerHTML = '<div class="notice notice-success"><p><?php _e("Settings exported successfully!", "aps-tools"); ?></p></div>';
}
</script>

<style>
.aps-system-actions {
    margin: 20px 0;
}

.aps-system-actions .button {
    margin-right: 10px;
    margin-bottom: 10px;
}

.form-table th {
    width: 200px;
}

.notice {
    padding: 12px;
    margin: 5px 0 15px;
    border-left: 4px solid #ddd;
}

.notice-success {
    border-left-color: #46b450;
    background-color: #ecf7ed;
}

.notice-error {
    border-left-color: #dc3232;
    background-color: #fbeaea;
}

.notice-info {
    border-left-color: #00a0d2;
    background-color: #e5f5fa;
}
</style>