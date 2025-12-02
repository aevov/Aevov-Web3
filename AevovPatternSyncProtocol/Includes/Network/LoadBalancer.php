<?php

namespace APS\Network;

class LoadBalancer {
    private static $instance = null;
    
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('init', [$this, 'init_site_processing']);
    }

    public function init_site_processing() {
        if (!is_multisite()) return;

        // Get least loaded site
        $site_id = $this->get_least_loaded_site();
        
        // Store current processing site
        set_site_transient('aps_processing_site', $site_id, 5 * MINUTE_IN_SECONDS);
    }

    private function get_least_loaded_site() {
        $sites = get_sites(['fields' => 'ids']);
        $loads = [];

        foreach ($sites as $site_id) {
            switch_to_blog($site_id);
            $loads[$site_id] = $this->get_site_load($site_id);
            restore_current_blog();
        }

        asort($loads);
        return key($loads);
    }

    private function get_site_load($site_id) {
        global $wpdb;
        
        $processing_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}aps_queue 
             WHERE status = 'processing'"
        );

        return $processing_count;
    }

    public static function get_processing_site() {
        $site_id = get_site_transient('aps_processing_site');
        return $site_id ?: get_current_blog_id();
    }
}