<?php
namespace APSTools\Admin;

class Settings {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings() {
        register_setting('aps_settings', 'aps_validate_json', [
            'type' => 'boolean',
            'default' => true,
            'description' => 'Enable or disable JSON validation for BLOOM chunks',
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        register_setting('aps_settings', 'aps_sync_interval', [
            'type' => 'integer',
            'default' => 300,
            'description' => 'Sync interval in seconds',
            'sanitize_callback' => [$this, 'sanitize_sync_interval']
        ]);

        register_setting('aps_settings', 'aps_debug_mode', [
            'type' => 'boolean',
            'default' => false,
            'description' => 'Enable or disable debug logging',
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);

        register_setting('aps_settings', 'aps_max_batch_size', [
            'type' => 'integer',
            'default' => 100,
            'description' => 'Maximum number of patterns to process in a single batch',
            'sanitize_callback' => 'absint'
        ]);
    }

    public function sanitize_sync_interval($value) {
        $value = absint($value);
        return max(60, $value); // Ensure minimum of 60 seconds
    }
}
