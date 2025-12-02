<?php
/**
 * Providers Management Template
 *
 * @package AevovAICore
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$provider_manager = $GLOBALS['aevov_ai_core']->get_provider_manager();
$providers = $provider_manager->get_all_providers();
$default_provider = get_option('aevov_default_ai_provider', 'deepseek');
$fallback_chain = get_option('aevov_ai_fallback_chain', ['deepseek', 'minimax', 'openai', 'anthropic']);

// Handle form submissions
if (isset($_POST['aevov_save_providers']) && check_admin_referer('aevov_save_providers')) {
    // Save provider settings
    foreach ($_POST['providers'] as $provider_name => $settings) {
        $provider = $provider_manager->get_provider($provider_name);
        if ($provider && isset($settings['api_key'])) {
            $provider->set_api_key($settings['api_key']);
        }
    }

    // Save default provider
    if (isset($_POST['default_provider'])) {
        $provider_manager->set_default_provider($_POST['default_provider']);
        $default_provider = $_POST['default_provider'];
    }

    // Save fallback chain
    if (isset($_POST['fallback_chain'])) {
        $provider_manager->set_fallback_chain($_POST['fallback_chain']);
        $fallback_chain = $_POST['fallback_chain'];
    }

    echo '<div class="notice notice-success"><p>Provider settings saved!</p></div>';
}
?>

<div class="wrap aevov-ai-providers">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="">
        <?php wp_nonce_field('aevov_save_providers'); ?>

        <div class="aevov-card">
            <h2>Provider Configuration</h2>
            <p>Configure your AI provider API keys and settings.</p>

            <?php foreach ($providers as $provider_name => $provider_info): ?>
                <div class="aevov-provider-section">
                    <h3>
                        <?php echo esc_html($provider_info['name']); ?>
                        <?php if ($provider_info['configured']): ?>
                            <span class="aevov-status aevov-status-active">Configured</span>
                        <?php else: ?>
                            <span class="aevov-status aevov-status-inactive">Not Configured</span>
                        <?php endif; ?>
                    </h3>

                    <table class="form-table">
                        <tr>
                            <th scope="row">API Key</th>
                            <td>
                                <input
                                    type="password"
                                    name="providers[<?php echo esc_attr($provider_name); ?>][api_key]"
                                    class="regular-text"
                                    value="<?php echo esc_attr($provider_manager->get_provider($provider_name)->get_api_key()); ?>"
                                    placeholder="Enter API key"
                                />
                                <p class="description">
                                    <?php
                                    switch ($provider_name) {
                                        case 'deepseek':
                                            echo 'Get your API key from <a href="https://platform.deepseek.com" target="_blank">platform.deepseek.com</a>';
                                            break;
                                        case 'minimax':
                                            echo 'Get your API key from <a href="https://platform.minimax.chat" target="_blank">platform.minimax.chat</a>';
                                            break;
                                        case 'openai':
                                            echo 'Get your API key from <a href="https://platform.openai.com" target="_blank">platform.openai.com</a>';
                                            break;
                                        case 'anthropic':
                                            echo 'Get your API key from <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>';
                                            break;
                                    }
                                    ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Available Models</th>
                            <td>
                                <ul class="aevov-model-list">
                                    <?php foreach ($provider_info['models'] as $model): ?>
                                        <li>
                                            <strong><?php echo esc_html($model['name'] ?? $model['id']); ?></strong>
                                            <?php if (isset($model['description'])): ?>
                                                - <?php echo esc_html($model['description']); ?>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">Capabilities</th>
                            <td>
                                <?php
                                $capabilities = array_filter($provider_info['capabilities']);
                                echo implode(', ', array_keys($capabilities));
                                ?>
                            </td>
                        </tr>
                    </table>

                    <button
                        type="button"
                        class="button aevov-test-provider"
                        data-provider="<?php echo esc_attr($provider_name); ?>"
                    >
                        Test Connection
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="aevov-card">
            <h2>Provider Routing</h2>

            <table class="form-table">
                <tr>
                    <th scope="row">Default Provider</th>
                    <td>
                        <select name="default_provider">
                            <?php foreach ($providers as $provider_name => $provider_info): ?>
                                <option
                                    value="<?php echo esc_attr($provider_name); ?>"
                                    <?php selected($default_provider, $provider_name); ?>
                                >
                                    <?php echo esc_html($provider_info['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Provider to use by default for AI requests</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Fallback Chain</th>
                    <td>
                        <div id="aevov-fallback-chain">
                            <?php foreach ($fallback_chain as $index => $provider_name): ?>
                                <div class="aevov-fallback-item">
                                    <span class="aevov-fallback-order"><?php echo $index + 1; ?></span>
                                    <select name="fallback_chain[]">
                                        <?php foreach ($providers as $pname => $pinfo): ?>
                                            <option
                                                value="<?php echo esc_attr($pname); ?>"
                                                <?php selected($provider_name, $pname); ?>
                                            >
                                                <?php echo esc_html($pinfo['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="description">Order of providers to try if primary fails</p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <input type="submit" name="aevov_save_providers" class="button button-primary" value="Save Changes" />
        </p>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('.aevov-test-provider').on('click', function() {
        var button = $(this);
        var provider = button.data('provider');

        button.prop('disabled', true).text('Testing...');

        $.post(ajaxurl, {
            action: 'aevov_test_provider',
            provider: provider,
            nonce: '<?php echo wp_create_nonce('aevov_test_provider'); ?>'
        }, function(response) {
            if (response.success) {
                alert('✓ ' + provider + ' connection successful!');
            } else {
                alert('✗ ' + provider + ' test failed: ' + response.data.message);
            }
            button.prop('disabled', false).text('Test Connection');
        }).fail(function() {
            alert('✗ Test request failed');
            button.prop('disabled', false).text('Test Connection');
        });
    });
});
</script>
