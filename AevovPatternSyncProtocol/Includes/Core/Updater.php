<?php
/**
 * Handles plugin updates and migrations
 * 
 * @package APS
 * @subpackage Core
 */

namespace APS\Core;

class Updater {
    private $version;

    public function __construct() {
        $this->version = get_option('aps_version', '1.0.0');
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('init', [$this, 'check_for_updates']);
    }

    public function check_for_updates() {
        if ($this->version !== APS_VERSION) {
            $this->run_migration($this->version, APS_VERSION);
            update_option('aps_version', APS_VERSION);
        }
    }

    private function run_migration($from_version, $to_version) {
        $this->migrate_database_schema($from_version, $to_version);
        $this->migrate_settings($from_version, $to_version);
        $this->migrate_data($from_version, $to_version);
    }

    private function migrate_database_schema($from_version, $to_version) {
        global $wpdb;

        if (version_compare($from_version, '1.1.0', '<')) {
            $wpdb->query("ALTER TABLE {$wpdb->prefix}aps_patterns ADD COLUMN `metadata` LONGTEXT NULL");
        }

        if (version_compare($from_version, '1.2.0', '<')) {
            $wpdb->query("CREATE TABLE {$wpdb->prefix}aps_network_state (
                id BIGINT(20) NOT NULL AUTO_INCREMENT,
                state_data LONGTEXT NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) {$wpdb->get_charset_collate()};");
        }
    }

    private function migrate_settings($from_version, $to_version) {
        if (version_compare($from_version, '1.1.0', '<')) {
            $old_settings = get_option('aps_options', []);
            $new_settings = [
                'confidence_threshold' => $old_settings['aps_confidence_threshold'] ?? 0.75,
                'sync_interval' => $old_settings['aps_sync_interval'] ?? 300,
                'bloom_api_key' => $old_settings['aps_bloom_api_key'] ?? ''
            ];
            update_option('aps_settings', $new_settings);
        }
    }

    private function migrate_data($from_version, $to_version) {
        if (version_compare($from_version, '1.2.0', '<')) {
            $this->migrate_pattern_data();
        }
    }

    private function migrate_pattern_data() {
        global $wpdb;

        $patterns = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}aps_patterns");
        foreach ($patterns as $pattern) {
            $metadata = json_decode($pattern->metadata, true);
            $metadata['source'] = 'legacy';
            $wpdb->update(
                $wpdb->prefix . 'aps_patterns',
                ['metadata' => json_encode($metadata)],
                ['id' => $pattern->id]
            );
        }
    }
}