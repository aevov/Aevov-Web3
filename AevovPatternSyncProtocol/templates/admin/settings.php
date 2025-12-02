<?php
/**
 * Template for the APS settings page
 */

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h1><?php _e('APS Settings', 'aps'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('aps_settings'); ?>
        <?php do_settings_sections('aps_settings'); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="aps_confidence_threshold">
                        <?php _e('Confidence Threshold', 'aps'); ?>
                    </label>
                </th>
                <td>
                    <input type="number" step="0.01" min="0" max="1"
                           name="aps_confidence_threshold"
                           id="aps_confidence_threshold"
                           value="<?php echo esc_attr(get_option('aps_confidence_threshold', 0.75)); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="aps_sync_interval">
                        <?php _e('Sync Interval (seconds)', 'aps'); ?>
                    </label>
                </th>
                <td>
                    <input type="number" min="60" step="1"
                           name="aps_sync_interval"
                           id="aps_sync_interval"
                           value="<?php echo esc_attr(get_option('aps_sync_interval', 300)); ?>">
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>