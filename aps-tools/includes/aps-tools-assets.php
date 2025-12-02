<?php
/**
 * Enqueues assets for the APS Tools plugin
 */

add_action('admin_enqueue_scripts', 'aps_tools_enqueue_assets');

function aps_tools_enqueue_assets($hook) {
    if (strpos($hook, 'aps-tools') === false) {
        return;
    }

    wp_enqueue_style(
        'aps-tools-admin',
        plugin_dir_url(__FILE__) . 'assets/css/admin.css',
        [],
        APS_Tools::instance()->get_version()
    );

    wp_enqueue_script(
        'aps-tools-admin',
        plugin_dir_url(__FILE__) . 'assets/js/admin.js',
        ['jquery', 'wp-api'],
        APS_Tools::instance()->get_version(),
        true
    );

    wp_localize_script('aps-tools-admin', 'apsTools', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aps-tools-nonce'),
        'apsAvailable' => class_exists('APS\APS_Plugin')
    ]);
}