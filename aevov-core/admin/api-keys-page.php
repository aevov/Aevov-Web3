<?php
/**
 * Admin Page: API Keys Management
 *
 * @package AevovCore
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Aevov API Keys Management', 'aevov-core'); ?></h1>

    <p class="description">
        <?php _e('Securely store and manage API keys for all Aevov plugins. All keys are encrypted using AES-256-CBC encryption.', 'aevov-core'); ?>
    </p>

    <hr>

    <!-- Add New API Key Form -->
    <h2><?php _e('Add New API Key', 'aevov-core'); ?></h2>

    <form method="post" action="">
        <?php wp_nonce_field('aevov_save_api_key', 'aevov_api_key_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="plugin_name"><?php _e('Plugin Name', 'aevov-core'); ?></label>
                </th>
                <td>
                    <select name="plugin_name" id="plugin_name" class="regular-text" required>
                        <option value=""><?php _e('Select Plugin', 'aevov-core'); ?></option>
                        <optgroup label="<?php _e('AI Engines', 'aevov-core'); ?>">
                            <option value="language-engine">Language Engine</option>
                            <option value="language-engine-v2">Language Engine v2</option>
                            <option value="image-engine">Image Engine</option>
                            <option value="music-forge">Music Forge</option>
                            <option value="transcription-engine">Transcription Engine</option>
                            <option value="embedding-engine">Embedding Engine</option>
                        </optgroup>
                        <optgroup label="<?php _e('Cognitive Systems', 'aevov-core'); ?>">
                            <option value="cognitive-engine">Cognitive Engine</option>
                            <option value="reasoning-engine">Reasoning Engine</option>
                            <option value="neuro-architect">Neuro Architect</option>
                            <option value="memory-core">Memory Core</option>
                        </optgroup>
                        <optgroup label="<?php _e('Infrastructure', 'aevov-core'); ?>">
                            <option value="cubbit-cdn">Cubbit CDN</option>
                            <option value="cubbit-downloader">Cubbit Downloader</option>
                        </optgroup>
                        <optgroup label="<?php _e('Other', 'aevov-core'); ?>">
                            <option value="custom"><?php _e('Custom Plugin', 'aevov-core'); ?></option>
                        </optgroup>
                    </select>
                    <p class="description"><?php _e('Select the plugin this API key belongs to', 'aevov-core'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="key_name"><?php _e('Key Name', 'aevov-core'); ?></label>
                </th>
                <td>
                    <input type="text" name="key_name" id="key_name" class="regular-text" required
                           placeholder="e.g., openai, anthropic, stability">
                    <p class="description"><?php _e('Unique identifier for this API key (e.g., openai, anthropic, stability)', 'aevov-core'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="key_type"><?php _e('Key Type', 'aevov-core'); ?></label>
                </th>
                <td>
                    <select name="key_type" id="key_type" class="regular-text">
                        <option value="generic"><?php _e('Generic', 'aevov-core'); ?></option>
                        <option value="openai">OpenAI (sk-...)</option>
                        <option value="anthropic">Anthropic (sk-ant-...)</option>
                        <option value="stability">Stability AI</option>
                    </select>
                    <p class="description"><?php _e('Key type for validation (optional)', 'aevov-core'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="api_key"><?php _e('API Key', 'aevov-core'); ?></label>
                </th>
                <td>
                    <input type="password" name="api_key" id="api_key" class="large-text" required
                           placeholder="Enter API key">
                    <button type="button" class="button" onclick="toggleApiKeyVisibility()">
                        <?php _e('Show', 'aevov-core'); ?>
                    </button>
                    <p class="description">
                        <?php _e('The API key will be encrypted before storage. Never share your API keys.', 'aevov-core'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <input type="submit" name="aevov_save_api_key" class="button button-primary"
                   value="<?php _e('Save API Key', 'aevov-core'); ?>">
        </p>
    </form>

    <hr>

    <!-- Stored API Keys -->
    <h2><?php _e('Stored API Keys', 'aevov-core'); ?></h2>

    <?php if (empty($stored_keys)): ?>
        <p><?php _e('No API keys stored yet.', 'aevov-core'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Plugin', 'aevov-core'); ?></th>
                    <th><?php _e('Key Name', 'aevov-core'); ?></th>
                    <th><?php _e('Masked Value', 'aevov-core'); ?></th>
                    <th><?php _e('Actions', 'aevov-core'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stored_keys as $plugin => $keys): ?>
                    <?php foreach ($keys as $key_name => $masked_value): ?>
                        <tr>
                            <td><strong><?php echo esc_html($plugin); ?></strong></td>
                            <td><code><?php echo esc_html($key_name); ?></code></td>
                            <td><code><?php echo esc_html($masked_value); ?></code></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('aevov_delete_api_key', 'aevov_api_key_nonce'); ?>
                                    <input type="hidden" name="plugin_name" value="<?php echo esc_attr($plugin); ?>">
                                    <input type="hidden" name="key_name" value="<?php echo esc_attr($key_name); ?>">
                                    <button type="submit" name="aevov_delete_api_key" class="button button-small button-link-delete"
                                            onclick="return confirm('<?php _e('Are you sure you want to delete this API key?', 'aevov-core'); ?>')">
                                        <?php _e('Delete', 'aevov-core'); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Security Notice -->
    <div class="notice notice-info inline" style="margin-top: 20px;">
        <p>
            <strong><?php _e('Security Information:', 'aevov-core'); ?></strong><br>
            <?php _e('All API keys are encrypted using AES-256-CBC encryption with your WordPress AUTH_KEY.', 'aevov-core'); ?><br>
            <?php _e('Keys are stored in the WordPress database with encryption at rest.', 'aevov-core'); ?><br>
            <?php _e('Never commit .env files or configuration files containing API keys to version control.', 'aevov-core'); ?>
        </p>
    </div>
</div>

<script>
function toggleApiKeyVisibility() {
    var input = document.getElementById('api_key');
    var button = event.target;

    if (input.type === 'password') {
        input.type = 'text';
        button.textContent = '<?php _e('Hide', 'aevov-core'); ?>';
    } else {
        input.type = 'password';
        button.textContent = '<?php _e('Show', 'aevov-core'); ?>';
    }
}
</script>

<style>
.form-table th {
    width: 200px;
}
</style>
