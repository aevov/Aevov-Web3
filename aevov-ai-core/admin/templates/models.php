<?php
/**
 * Models Management Template
 *
 * @package AevovAICore
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$model_manager = $GLOBALS['aevov_ai_core']->get_model_manager();
$models = $model_manager->list_models();
$stats = $model_manager->get_statistics();
?>

<div class="wrap aevov-ai-models">
    <h1>
        <?php echo esc_html(get_admin_page_title()); ?>
        <button type="button" class="page-title-action" id="aevov-extract-model">Extract New Model</button>
        <button type="button" class="page-title-action" id="aevov-import-model">Import Model</button>
    </h1>

    <div class="aevov-card">
        <h2>Model Statistics</h2>
        <div class="aevov-stats-grid">
            <div class="aevov-stat">
                <span class="aevov-stat-label">Total Models</span>
                <span class="aevov-stat-value"><?php echo esc_html($stats['total_models']); ?></span>
            </div>
            <?php foreach ($stats['by_provider'] as $provider_stat): ?>
                <div class="aevov-stat">
                    <span class="aevov-stat-label"><?php echo esc_html(ucfirst($provider_stat['base_provider'])); ?></span>
                    <span class="aevov-stat-value"><?php echo esc_html($provider_stat['count']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="aevov-card">
        <h2>Your Models</h2>

        <?php if (empty($models)): ?>
            <p>No models found. Extract your first model from conversations or import an existing one.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Version</th>
                        <th>Base Provider</th>
                        <th>Base Model</th>
                        <th>Training Data</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($models as $model): ?>
                        <tr>
                            <td><strong><?php echo esc_html($model['name']); ?></strong></td>
                            <td><?php echo esc_html($model['version']); ?></td>
                            <td><?php echo esc_html(ucfirst($model['base_provider'])); ?></td>
                            <td><?php echo esc_html($model['base_model']); ?></td>
                            <td>
                                <?php
                                $loaded_model = $model_manager->load_model($model['model_id']);
                                echo $loaded_model ? $loaded_model->get_training_data_count() . ' examples' : 'N/A';
                                ?>
                            </td>
                            <td><?php echo human_time_diff(strtotime($model['updated_at']), current_time('timestamp')); ?> ago</td>
                            <td>
                                <button
                                    class="button button-small aevov-view-model"
                                    data-model-id="<?php echo esc_attr($model['model_id']); ?>"
                                >
                                    View
                                </button>
                                <button
                                    class="button button-small aevov-export-model"
                                    data-model-id="<?php echo esc_attr($model['model_id']); ?>"
                                >
                                    Export
                                </button>
                                <button
                                    class="button button-small aevov-clone-model"
                                    data-model-id="<?php echo esc_attr($model['model_id']); ?>"
                                >
                                    Clone
                                </button>
                                <button
                                    class="button button-small aevov-delete-model"
                                    data-model-id="<?php echo esc_attr($model['model_id']); ?>"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Extract Model Modal -->
<div id="aevov-extract-modal" class="aevov-modal" style="display:none;">
    <div class="aevov-modal-content">
        <span class="aevov-modal-close">&times;</span>
        <h2>Extract Model from Conversations</h2>

        <form id="aevov-extract-form">
            <table class="form-table">
                <tr>
                    <th scope="row">Model Name</th>
                    <td>
                        <input type="text" name="model_name" class="regular-text" required />
                    </td>
                </tr>
                <tr>
                    <th scope="row">Base Provider</th>
                    <td>
                        <select name="base_provider" required>
                            <option value="deepseek">DeepSeek</option>
                            <option value="minimax">MiniMax</option>
                            <option value="openai">OpenAI</option>
                            <option value="anthropic">Anthropic</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Date Range</th>
                    <td>
                        <input type="date" name="start_date" />
                        to
                        <input type="date" name="end_date" />
                        <p class="description">Leave empty to extract from all conversations</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Quality Threshold</th>
                    <td>
                        <input type="range" name="quality_threshold" min="0" max="1" step="0.1" value="0.6" />
                        <span id="quality-value">0.6</span>
                        <p class="description">Higher values = more selective filtering</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Max Examples</th>
                    <td>
                        <input type="number" name="max_examples" value="1000" min="10" max="10000" />
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Extract Model</button>
                <button type="button" class="button aevov-modal-close">Cancel</button>
            </p>
        </form>
    </div>
</div>

<!-- Import Model Modal -->
<div id="aevov-import-modal" class="aevov-modal" style="display:none;">
    <div class="aevov-modal-content">
        <span class="aevov-modal-close">&times;</span>
        <h2>Import Model</h2>

        <form id="aevov-import-form" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th scope="row">Model File</th>
                    <td>
                        <input type="file" name="model_file" accept=".aev,.json,.jsonl" required />
                        <p class="description">Upload .aev, .json, or .jsonl file</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Format</th>
                    <td>
                        <select name="format">
                            <option value="aev">AEV Format</option>
                            <option value="openai">OpenAI JSONL</option>
                            <option value="anthropic">Anthropic JSONL</option>
                        </select>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Import</button>
                <button type="button" class="button aevov-modal-close">Cancel</button>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Modal controls
    $('#aevov-extract-model').on('click', function() {
        $('#aevov-extract-modal').show();
    });

    $('#aevov-import-model').on('click', function() {
        $('#aevov-import-modal').show();
    });

    $('.aevov-modal-close').on('click', function() {
        $('.aevov-modal').hide();
    });

    // Quality threshold slider
    $('input[name="quality_threshold"]').on('input', function() {
        $('#quality-value').text($(this).val());
    });

    // Extract form
    $('#aevov-extract-form').on('submit', function(e) {
        e.preventDefault();

        var formData = $(this).serialize();
        var button = $(this).find('button[type="submit"]');

        button.prop('disabled', true).text('Extracting...');

        $.post(ajaxurl, {
            action: 'aevov_extract_model',
            ...Object.fromEntries(new URLSearchParams(formData)),
            nonce: '<?php echo wp_create_nonce('aevov_extract_model'); ?>'
        }, function(response) {
            if (response.success) {
                alert('Model extracted successfully!');
                location.reload();
            } else {
                alert('Extraction failed: ' + response.data.message);
                button.prop('disabled', false).text('Extract Model');
            }
        });
    });

    // Export model
    $('.aevov-export-model').on('click', function() {
        var modelId = $(this).data('model-id');
        window.location.href = ajaxurl + '?action=aevov_export_model&model_id=' + modelId + '&nonce=<?php echo wp_create_nonce('aevov_export_model'); ?>';
    });

    // Delete model
    $('.aevov-delete-model').on('click', function() {
        if (!confirm('Delete this model? This cannot be undone.')) {
            return;
        }

        var modelId = $(this).data('model-id');
        var row = $(this).closest('tr');

        $.post(ajaxurl, {
            action: 'aevov_delete_model',
            model_id: modelId,
            nonce: '<?php echo wp_create_nonce('aevov_delete_model'); ?>'
        }, function(response) {
            if (response.success) {
                row.fadeOut();
            } else {
                alert('Delete failed: ' + response.data.message);
            }
        });
    });
});
</script>
