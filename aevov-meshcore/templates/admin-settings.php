<?php
/**
 * Admin Settings Template
 *
 * @package AevovMeshcore
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1><?php echo esc_html__('Meshcore Settings', 'aevov-meshcore'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('aevov_meshcore_settings'); ?>

        <table class="form-table">
            <tr>
                <th scope="row"><?php echo esc_html__('Enable Relay', 'aevov-meshcore'); ?></th>
                <td>
                    <input type="checkbox" name="aevov_meshcore_enable_relay" value="1"
                        <?php checked(get_option('aevov_meshcore_enable_relay', true)); ?> />
                    <p class="description"><?php echo esc_html__('Share bandwidth by relaying traffic for others', 'aevov-meshcore'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Max Connections', 'aevov-meshcore'); ?></th>
                <td>
                    <input type="number" name="aevov_meshcore_max_connections"
                        value="<?php echo esc_attr(get_option('aevov_meshcore_max_connections', 50)); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Relay Bandwidth (MB/s)', 'aevov-meshcore'); ?></th>
                <td>
                    <input type="number" name="aevov_meshcore_relay_bandwidth_mb"
                        value="<?php echo esc_attr(get_option('aevov_meshcore_relay_bandwidth', 5242880) / 1024 / 1024); ?>" />
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
