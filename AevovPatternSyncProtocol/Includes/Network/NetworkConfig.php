<?php

namespace APS\Network;

class NetworkConfig {
    private static $instance = null;
    private $option_keys = [
        'aps_validate_json',
        'aps_sync_interval',
        'aps_pattern_confidence_threshold',
        'aps_bloom_api_key',
        // Add other configuration keys
    ];

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (is_multisite()) {
            add_action('network_admin_menu', [$this, 'add_network_menu']);
            add_action('wp_initialize_site', [$this, 'init_new_site'], 10, 2);
            add_action('wp_update_site', [$this, 'sync_site_config']);
        }
    }

    public function add_network_menu() {
        add_submenu_page(
            'settings.php',
            'APS Network Settings',
            'APS Settings',
            'manage_network_options',
            'aps-network-settings',
            [$this, 'render_network_settings']
        );
    }

    public function render_network_settings() {
        if (isset($_POST['submit']) && check_admin_referer('aps_network_settings')) {
            $this->save_network_settings($_POST);
        }

        include APS_PATH . 'templates/admin/network-settings.php';
    }

    private function save_network_settings($data) {
        foreach ($this->option_keys as $key) {
            if (isset($data[$key])) {
                update_site_option($key, $data[$key]);
            }
        }

        // Sync to all sites
        $this->sync_all_sites();
    }

    public function init_new_site($new_site, $args) {
        if (!is_int($new_site)) {
            $new_site = $new_site->blog_id;
        }

        switch_to_blog($new_site);

        // Copy network settings to new site
        foreach ($this->option_keys as $key) {
            $value = get_site_option($key);
            if ($value !== false) {
                update_option($key, $value);
            }
        }

        // Initialize necessary tables
        $this->init_site_tables();

        restore_current_blog();
    }

    public function sync_site_config($site_id) {
        if (!is_int($site_id)) {
            $site_id = $site_id->blog_id;
        }

        switch_to_blog($site_id);

        // Sync network settings to site
        foreach ($this->option_keys as $key) {
            $value = get_site_option($key);
            if ($value !== false) {
                update_option($key, $value);
            }
        }

        restore_current_blog();
    }

    private function sync_all_sites() {
        $sites = get_sites(['fields' => 'ids']);
        foreach ($sites as $site_id) {
            $this->sync_site_config($site_id);
        }
    }

    private function init_site_tables() {
        // Initialize necessary database tables for the site
        require_once APS_PATH . 'includes/core/class-aps-activator.php';
        \APS\Core\Activator::create_site_tables();
    }

    // Method to get configuration for current site
    public static function get_config($key) {
        if (is_multisite()) {
            return get_site_option($key);
        }
        return get_option($key);
    }
}