<?php
// templates/patterns.php
defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php _e('Pattern List', 'aps-tools'); ?></h1>

    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="pattern-type-filter">
                <option value=""><?php _e('All Types', 'aps-tools'); ?></option>
                <option value="tensor"><?php _e('Tensor', 'aps-tools'); ?></option>
                <option value="symbolic"><?php _e('Symbolic', 'aps-tools'); ?></option>
                <option value="hybrid"><?php _e('Hybrid', 'aps-tools'); ?></option>
            </select>
            <select id="confidence-filter">
                <option value=""><?php _e('All Confidence', 'aps-tools'); ?></option>
                <option value="high"><?php _e('High (>90%)', 'aps-tools'); ?></option>
                <option value="medium"><?php _e('Medium (70-90%)', 'aps-tools'); ?></option>
                <option value="low"><?php _e('Low (<70%)', 'aps-tools'); ?></option>
            </select>
            <button class="button" id="apply-filters"><?php _e('Apply', 'aps-tools'); ?></button>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('ID', 'aps-tools'); ?></th>
                <th><?php _e('Type', 'aps-tools'); ?></th>
                <th><?php _e('SKU', 'aps-tools'); ?></th>
                <th><?php _e('Confidence', 'aps-tools'); ?></th>
                <th><?php _e('Created', 'aps-tools'); ?></th>
                <th><?php _e('Status', 'aps-tools'); ?></th>
                <th><?php _e('Actions', 'aps-tools'); ?></th>
            </tr>
        </thead>
        <tbody id="pattern-list">
            <!-- Populated by JavaScript -->
        </tbody>
    </table>

    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <span class="displaying-num"></span>
            <span class="pagination-links">
                <button class="button prev-page" disabled>‹</button>
                <span class="paging-input">
                    <label for="current-page-selector" class="screen-reader-text">Current Page</label>
                    <input class="current-page" id="current-page-selector" type="text" value="1" size="1">
                    <span class="tablenav-paging-text"> of <span class="total-pages">1</span></span>
                </span>
                <button class="button next-page">›</button>
            </span>
        </div>
    </div>
</div>

<script type="text/template" id="pattern-row-template">
    <tr>
        <td><%- id %></td>
        <td><%- type %></td>
        <td><%- sku %></td>
        <td><%- (confidence * 100).toFixed(1) %>%</td>
        <td><%- created_at %></td>
        <td><span class="status-<%- status.toLowerCase() %>"><%- status %></span></td>
        <td>
            <button class="button view-pattern" data-id="<%- id %>">View</button>
            <button class="button analyze-pattern" data-id="<%- id %>">Analyze</button>
        </td>
    </tr>
</script>