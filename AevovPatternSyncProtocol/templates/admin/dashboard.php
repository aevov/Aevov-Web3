<?php
/**
 * templates/admin/dashboard.php
 * Admin dashboard template
 */
?>
<div class="wrap aps-dashboard">
    <h1><?php _e('APS Pattern System Dashboard', 'aps'); ?></h1>

    <div class="aps-dashboard-grid">
        <div class="aps-card aps-stats-card">
            <h2><?php _e('System Overview', 'aps'); ?></h2>
            <div class="aps-stats-grid">
                <div class="aps-stat">
                    <span class="aps-stat-value" id="total-comparisons">Loading...</span>
                    <span class="aps-stat-label"><?php _e('Total Comparisons', 'aps'); ?></span>
                </div>
                <div class="aps-stat">
                    <span class="aps-stat-value" id="patterns-analyzed">Loading...</span>
                    <span class="aps-stat-label"><?php _e('Patterns Analyzed', 'aps'); ?></span>
                </div>
                <div class="aps-stat">
                    <span class="aps-stat-value" id="avg-match-score">Loading...</span>
                    <span class="aps-stat-label"><?php _e('Average Match Score', 'aps'); ?></span>
                </div>
            </div>
        </div>

        <div class="aps-card aps-recent-card">
            <h2><?php _e('Recent Comparisons', 'aps'); ?></h2>
            <table class="wp-list-table widefat fixed striped" id="recent-comparisons-table">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'aps'); ?></th>
                        <th><?php _e('Type', 'aps'); ?></th>
                        <th><?php _e('Items', 'aps'); ?></th>
                        <th><?php _e('Score', 'aps'); ?></th>
                        <th><?php _e('Date', 'aps'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="5"><?php _e('Loading recent comparisons...', 'aps'); ?></td></tr>
                </tbody>
            </table>
        </div>

        <div class="aps-card aps-integration-card">
            <h2><?php _e('BLOOM Integration Status', 'aps'); ?></h2>
            <div class="aps-integration-status" id="bloom-integration-status">
                <p><?php _e('Loading BLOOM integration status...', 'aps'); ?></p>
            </div>
        </div>

        <div class="aps-card aps-ledger-card">
            <h2><?php _e('Distributed Ledger', 'aps'); ?></h2>
            <div class="aps-stats-grid">
                <div class="aps-stat">
                    <span class="aps-stat-value" id="total-blocks">Loading...</span>
                    <span class="aps-stat-label"><?php _e('Total Blocks', 'aps'); ?></span>
                </div>
                <div class="aps-stat">
                    <span class="aps-stat-value" id="total-transactions">Loading...</span>
                    <span class="aps-stat-label"><?php _e('Total Transactions', 'aps'); ?></span>
                </div>
                <div class="aps-stat">
                    <span class="aps-stat-value" id="last-block-hash">Loading...</span>
                    <span class="aps-stat-label"><?php _e('Last Block Hash', 'aps'); ?></span>
                </div>
            </div>
        </div>

        <div class="aps-card aps-consensus-card">
            <h2><?php _e('Consensus Mechanism', 'aps'); ?></h2>
            <div class="aps-stats-grid">
                <div class="aps-stat">
                    <span class="aps-stat-value" id="consensus-status">Loading...</span>
                    <span class="aps-stat-label"><?php _e('Consensus Status', 'aps'); ?></span>
                </div>
                <div class="aps-stat">
                    <span class="aps-stat-value" id="active-proposals">Loading...</span>
                    <span class="aps-stat-label"><?php _e('Active Proposals', 'aps'); ?></span>
                </div>
                <div class="aps-stat">
                    <span class="aps-stat-value" id="total-votes">Loading...</span>
                    <span class="aps-stat-label"><?php _e('Total Votes', 'aps'); ?></span>
                </div>
            </div>
        </div>

        <div class="aps-card aps-activity-card">
            <h2><?php _e('Recent Activity', 'aps'); ?></h2>
            <div class="aps-activity-list" id="recent-activity">
                <p><?php _e('Loading recent activity...', 'aps'); ?></p>
            </div>
        </div>
    </div>
</div>
