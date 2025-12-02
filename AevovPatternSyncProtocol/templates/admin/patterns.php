<?php
/**
 * Template for the APS patterns page
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php _e('APS Patterns', 'aps'); ?></h1>

    <div class="aps-patterns">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col"><?php _e('ID', 'aps'); ?></th>
                    <th scope="col"><?php _e('Type', 'aps'); ?></th>
                    <th scope="col"><?php _e('Confidence', 'aps'); ?></th>
                    <th scope="col"><?php _e('Created', 'aps'); ?></th>
                    <th scope="col"><?php _e('Actions', 'aps'); ?></th>
                </tr>
            </thead>
            <tbody id="pattern-list"></tbody>
        </table>
    </div>
</div>