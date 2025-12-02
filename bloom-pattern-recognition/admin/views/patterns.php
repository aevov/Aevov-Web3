
<?php
require_once BLOOM_PATH . 'admin/templates/partials/header.php';
?>

<div class="patterns-header">
    <div class="patterns-controls">
        <select id="pattern-filter" class="bloom-select">
            <option value="all"><?php _e('All Patterns', 'bloom-pattern-system'); ?></option>
            <option value="high_confidence"><?php _e('High Confidence (>0.9)', 'bloom-pattern-system'); ?></option>
            <option value="recent"><?php _e('Recently Added', 'bloom-pattern-system'); ?></option>
            <option value="frequent"><?php _e('Most Frequent', 'bloom-pattern-system'); ?></option>
            <option value="clustered"><?php _e('Clustered Patterns', 'bloom-pattern-system'); ?></option>
        </select>
        
        <div class="pattern-actions">
            <button id="analyze-selected" class="bloom-button bloom-button-secondary">
                <?php _e('Analyze Selected', 'bloom-pattern-system'); ?>
            </button>
            <button id="export-patterns" class="bloom-button bloom-button-secondary">
                <?php _e('Export', 'bloom-pattern-system'); ?>
            </button>
        </div>
    </div>
    
    <div class="pattern-metrics">
        <div class="metric">
            <span class="metric-label"><?php _e('Total', 'bloom-pattern-system'); ?></span>
            <span class="metric-value" id="total-pattern-count">0</span>
        </div>
        <div class="metric">
            <span class="metric-label"><?php _e('Avg Confidence', 'bloom-pattern-system'); ?></span>
            <span class="metric-value" id="avg-confidence">0%</span>
        </div>
    </div>
</div>

<div class="patterns-table-container">
    <table class="bloom-table" id="pattern-list">
        <thead>
            <tr>
                <th class="sortable" data-sort="hash">
                    <?php _e('Pattern Hash', 'bloom-pattern-system'); ?>
                </th>
                <th class="sortable" data-sort="type">
                    <?php _e('Type', 'bloom-pattern-system'); ?>
                </th>
                <th class="sortable" data-sort="confidence">
                    <?php _e('Confidence', 'bloom-pattern-system'); ?>
                </th>
                <th class="sortable" data-sort="created">
                    <?php _e('Created', 'bloom-pattern-system'); ?>
                </th>
                <th><?php _e('Actions', 'bloom-pattern-system'); ?></th>
            </tr>
        </thead>
        <tbody>
            <!-- Dynamically populated by JS -->
        </tbody>
    </table>
</div>

<div class="patterns-pagination">
    <div class="pagination-info">
        <span id="pagination-range"></span>
        <?php _e('of', 'bloom-pattern-system'); ?>
        <span id="total-items"></span>
        <?php _e('items', 'bloom-pattern-system'); ?>
    </div>
    <div class="pagination-controls">
        <!-- Dynamically populated by JS -->
    </div>
</div>

<!-- Pattern Details Modal -->
<div id="pattern-modal" class="bloom-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><?php _e('Pattern Details', 'bloom-pattern-system'); ?></h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Dynamically populated by JS -->
            <div id="pattern-details-content"></div>
            <div id="cluster-details" style="display:none;">
                <h3><?php _e('Cluster Information', 'bloom-pattern-system'); ?></h3>
                <p><strong><?php _e('Centroid:', 'bloom-pattern-system'); ?></strong> <span id="cluster-centroid"></span></p>
                <p><strong><?php _e('Size:', 'bloom-pattern-system'); ?></strong> <span id="cluster-size"></span></p>
                <button id="view-cluster-patterns" class="bloom-button bloom-button-secondary"><?php _e('View All Cluster Patterns', 'bloom-pattern-system'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php require_once BLOOM_PATH . 'admin/templates/partials/footer.php'; ?>