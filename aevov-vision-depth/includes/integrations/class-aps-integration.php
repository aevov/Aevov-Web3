<?php
/**
 * APS Integration
 *
 * Integrates Vision Depth with AevovPatternSyncProtocol for pattern storage
 * and consensus validation
 *
 * @package AevovVisionDepth\Integrations
 * @since 1.0.0
 */

namespace AevovVisionDepth\Integrations;

class APS_Integration {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function init() {
        // Hook into pattern discovery
        add_action('avd_pattern_discovered', [$this, 'sync_to_aps'], 10, 2);

        // Hook into data scraping for analysis
        add_action('avd_data_scraped', [$this, 'analyze_for_patterns'], 10, 2);
    }

    public function sync_to_aps($pattern_data, $user_id) {
        if (!get_option('avd_enable_aps_integration', true)) {
            return;
        }

        // Check if APS Pattern DB is available
        if (!class_exists('\\APS\\DB\\APS_Pattern_DB')) {
            return;
        }

        try {
            $aps_pattern_db = new \APS\DB\APS_Pattern_DB();

            // Convert Vision Depth pattern to APS format
            $aps_pattern = [
                'pattern_hash' => $pattern_data['pattern_hash'] ?? hash('sha256', json_encode($pattern_data)),
                'pattern_data' => json_encode($pattern_data),
                'source' => 'vision_depth',
                'contributor_id' => $user_id,
                'confidence_score' => $pattern_data['confidence_score'] ?? 0.75,
            ];

            // Insert pattern into APS
            $pattern_id = $aps_pattern_db->insert_pattern($aps_pattern);

            // Link Vision Depth pattern to APS pattern
            if ($pattern_id && isset($pattern_data['avd_pattern_id'])) {
                $this->link_pattern($pattern_data['avd_pattern_id'], $pattern_id);
            }

            // Log integration
            error_log('[Vision Depth] Synced pattern to APS: ' . $pattern_id);

            return $pattern_id;

        } catch (\Exception $e) {
            error_log('[Vision Depth] APS integration error: ' . $e->getMessage());
            return null;
        }
    }

    public function analyze_for_patterns($scraped_data, $user_id) {
        // Extract behavioral patterns from scraped data
        // This is a simplified pattern detection - real implementation would be more sophisticated

        global $wpdb;
        $table = $wpdb->prefix . 'avd_behavioral_patterns';

        // Simple pattern: frequent visits to same domain
        $url_hash = $scraped_data['url_hash'] ?? null;
        if ($url_hash) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND pattern_hash = %s",
                $user_id,
                $url_hash
            ));

            if ($existing) {
                // Increment occurrences
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$table} SET occurrences = occurrences + 1,
                     last_seen = NOW() WHERE id = %d",
                    $existing->id
                ));
            } else {
                // Create new pattern
                $wpdb->insert($table, [
                    'user_id' => $user_id,
                    'pattern_hash' => $url_hash,
                    'pattern_type' => 'domain_visit',
                    'pattern_data' => json_encode(['url_hash' => $url_hash]),
                    'confidence_score' => 0.5,
                ], ['%d', '%s', '%s', '%s', '%f']);

                // Trigger pattern discovered action
                $pattern_id = $wpdb->insert_id;
                do_action('avd_pattern_discovered', [
                    'avd_pattern_id' => $pattern_id,
                    'pattern_hash' => $url_hash,
                    'pattern_type' => 'domain_visit',
                    'confidence_score' => 0.5,
                ], $user_id);
            }
        }
    }

    private function link_pattern($avd_pattern_id, $aps_pattern_id) {
        $this->wpdb->update(
            $this->wpdb->prefix . 'avd_behavioral_patterns',
            ['aps_pattern_id' => $aps_pattern_id],
            ['id' => $avd_pattern_id],
            ['%d'],
            ['%d']
        );
    }

    public function get_synced_count() {
        return $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}avd_behavioral_patterns
             WHERE aps_pattern_id IS NOT NULL"
        );
    }
}
