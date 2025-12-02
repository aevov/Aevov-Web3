<?php
// templates/bloom.php
defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php _e('BLOOM Integration', 'aps-tools'); ?></h1>

    <div class="integration-status">
        <div class="status-card">
            <h2><?php _e('Connection Status', 'aps-tools'); ?></h2>
            <div class="status-indicator">
                <span id="bloom-status-icon"></span>
                <span id="bloom-status-text"></span>
            </div>
            <div class="status-actions">
                <button id="test-connection" class="button">
                    <?php _e('Test Connection', 'aps-tools'); ?>
                </button>
                <button id="sync-now" class="button">
                    <?php _e('Sync Now', 'aps-tools'); ?>
                </button>
            </div>
        </div>

        <div class="status-card">
            <h2><?php _e('Sync Statistics', 'aps-tools'); ?></h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <label><?php _e('Last Sync', 'aps-tools'); ?></label>
                    <span id="last-sync-time"></span>
                </div>
                <div class="stat-item">
                    <label><?php _e('Patterns Synced', 'aps-tools'); ?></label>
                    <span id="patterns-synced"></span>
                </div>
                <div class="stat-item">
                    <label><?php _e('Success Rate', 'aps-tools'); ?></label>
                    <span id="sync-success-rate"></span>
                </div>
                <div class="stat-item">
                    <label><?php _e('Errors', 'aps-tools'); ?></label>
                    <span id="sync-errors"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="sync-history">
        <h2><?php _e('Sync History', 'aps-tools'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'aps-tools'); ?></th>
                    <th><?php _e('Patterns', 'aps-tools'); ?></th>
                    <th><?php _e('Status', 'aps-tools'); ?></th>
                    <th><?php _e('Duration', 'aps-tools'); ?></th>
                    <th><?php _e('Details', 'aps-tools'); ?></th>
                </tr>
            </thead>
            <tbody id="sync-history-list">
                <!-- Populated by JavaScript -->
            </tbody>
        </table>
    </div>
</div>

<script type="text/template" id="sync-history-row-template">
    <tr>
        <td><%- timestamp %></td>
        <td><%- patterns_count %></td>
        <td><span class="status-<%- status.toLowerCase() %>"><%- status %></span></td>
        <td><%- duration %>s</td>
        <td>
            <button class="button view-details" data-id="<%- id %>">
                <?php _e('View Details', 'aps-tools'); ?>
            </button>
        </td>
    </tr>
</script>

<!-- Add this section to templates/bloom.php -->
<div class="chunk-upload">
    <h2><?php _e('Manual Chunk Upload', 'aps-tools'); ?></h2>
    
    <div class="chunk-upload-form">
        <div class="form-row">
            <label for="chunk-file"><?php _e('JSON Chunk File', 'aps-tools'); ?></label>
            <input type="file" id="chunk-file" accept=".json" />
        </div>
        
        <div class="form-row">
            <label for="chunk-content"><?php _e('Or Paste JSON Content', 'aps-tools'); ?></label>
            <textarea id="chunk-content" rows="6" placeholder="<?php esc_attr_e('Paste JSON content here...', 'aps-tools'); ?>"></textarea>
        </div>

        <div class="chunk-preview" style="display: none;">
            <h4><?php _e('Preview', 'aps-tools'); ?></h4>
            <pre id="chunk-preview-content"></pre>
        </div>

        <div class="form-row">
            <button id="upload-chunk" class="button button-primary"><?php _e('Upload Chunk', 'aps-tools'); ?></button>
        </div>
    </div>

    <div class="chunk-list">
        <h3><?php _e('Uploaded Chunks', 'aps-tools'); ?></h3>
        <div id="uploaded-chunks">
            <!-- Populated by JavaScript -->
        </div>
    </div>
</div>