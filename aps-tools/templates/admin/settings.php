<?php
/**
 * Template for APS Tools settings page
 */
defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form id="aps-settings-form" action="options.php" method="post">
        <?php
        settings_fields('aps_settings');
        do_settings_sections('aps_settings');
        ?>
        
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="aps_validate_json">
                        <?php _e('JSON Validation', 'aps-tools'); ?>
                    </label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="aps_validate_json" 
                               id="aps_validate_json" 
                               value="1" 
                               <?php checked(get_option('aps_validate_json', true)); ?>>
                        <?php _e('Enable JSON validation for BLOOM chunks', 'aps-tools'); ?>
                    </label>
                    <p class="description">
                        <?php _e('When disabled, allows uploading of non-standard JSON formats', 'aps-tools'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="aps_sync_interval">
                        <?php _e('Sync Interval', 'aps-tools'); ?>
                    </label>
                </th>
                <td>
                    <input type="number" 
                           name="aps_sync_interval" 
                           id="aps_sync_interval" 
                           value="<?php echo esc_attr(get_option('aps_sync_interval', 300)); ?>"
                           min="60" 
                           step="60" 
                           class="small-text">
                    <?php _e('seconds', 'aps-tools'); ?>
                    <p class="description">
                        <?php _e('Time between automatic synchronizations (minimum 60 seconds)', 'aps-tools'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>