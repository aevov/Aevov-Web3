<div class="wrap">
    <h1><?php _e('Bulk Chunk Upload', 'aps-tools'); ?></h1>

    <div class="bulk-upload-container">
        <div class="upload-zone">
            <div id="drop-zone" class="drop-zone">
                <div class="drop-zone-content">
                    <span class="dashicons dashicons-upload"></span>
                    <p><?php _e('Drag and drop JSON chunk files here', 'aps-tools'); ?></p>
                    <p><?php _e('or', 'aps-tools'); ?></p>
                    <input type="file" id="chunk-files" multiple accept=".json" class="file-input" />
                    <label for="chunk-files" class="button button-primary">
                        <?php _e('Select Files', 'aps-tools'); ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="model-selection">
            <label for="parent-model"><?php _e('Parent Model:', 'aps-tools'); ?></label>
            <select id="parent-model" required>
                <option value=""><?php _e('Select a model', 'aps-tools'); ?></option>
                <?php
                $models = get_posts([
                    'post_type' => 'bloom_model',
                    'posts_per_page' => -1,
                    'orderby' => 'title',
                    'order' => 'ASC'
                ]);
                foreach ($models as $model): ?>
                    <option value="<?php echo esc_attr($model->ID); ?>">
                        <?php echo esc_html($model->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="file-preview-list" class="file-preview-list">
            <!-- File previews will be dynamically added here -->
        </div>

        <div class="upload-actions">
            <button id="start-upload" class="button button-primary" disabled>
                <?php _e('Upload All', 'aps-tools'); ?>
            </button>
            <button id="clear-files" class="button button-secondary" disabled>
                <?php _e('Clear All', 'aps-tools'); ?>
            </button>
        </div>

        <div id="upload-progress" class="upload-progress">
            <!-- Progress bars will be dynamically added here -->
        </div>
    </div>
</div>

<script type="text/template" id="file-preview-template">
    <div class="file-preview" data-index="<%- index %>">
        <div class="file-preview-header">
            <span class="file-name"><%- fileName %></span>
            <button type="button" class="remove-file button-link">
                <span class="dashicons dashicons-dismiss"></span>
            </button>
        </div>
        <div class="file-details">
            <div class="detail-row">
                <span class="detail-label"><?php _e('Tensor:', 'aps-tools'); ?></span>
                <span class="detail-value"><%- tensorName %></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><?php _e('SKU:', 'aps-tools'); ?></span>
                <span class="detail-value"><%- sku %></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><?php _e('Shape:', 'aps-tools'); ?></span>
                <span class="detail-value"><%- shape.join(' × ') %></span>
            </div>
            <div class="detail-row">
                <span class="detail-label"><?php _e('Type:', 'aps-tools'); ?></span>
                <span class="detail-value"><%- dtype %></span>
            </div>
            <% if (isPartial) { %>
            <div class="detail-row">
                <span class="detail-label"><?php _e('Part:', 'aps-tools'); ?></span>
                <span class="detail-value"><%- partNumber + 1 %> of <%- totalParts %></span>
            </div>
            <% } %>
        </div>
        <div class="upload-status"></div>
    </div>
</script>