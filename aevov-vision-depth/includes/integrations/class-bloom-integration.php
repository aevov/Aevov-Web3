<?php
/**
 * Bloom Integration
 *
 * Integrates Vision Depth with Bloom Pattern Recognition for behavioral analysis
 *
 * @package AevovVisionDepth\Integrations
 * @since 1.0.0
 */

namespace AevovVisionDepth\Integrations;

class Bloom_Integration {

    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    public function init() {
        add_action('avd_data_scraped', [$this, 'analyze_with_bloom'], 10, 2);
    }

    public function analyze_with_bloom($scraped_data, $user_id) {
        if (!get_option('avd_enable_bloom_integration', true)) {
            return;
        }

        // Check if Bloom is available
        if (!class_exists('\\BLOOM\\Models\\Pattern_Model') && !class_exists('BLOOM_Pattern_System')) {
            return;
        }

        // Create tensor from scraped data (simplified)
        $tensor = $this->create_tensor_from_data($scraped_data);

        // Store tensor for Bloom processing
        $this->store_tensor_for_bloom($tensor, $user_id);

        // If Bloom Pattern Model is available, analyze immediately
        if (class_exists('\\BLOOM\\Models\\Pattern_Model')) {
            try {
                $pattern_model = new \BLOOM\Models\Pattern_Model();
                $patterns = $pattern_model->recognize($tensor);

                foreach ($patterns as $pattern) {
                    $this->store_behavioral_pattern($pattern, $user_id);
                }
            } catch (\Exception $e) {
                error_log('[Vision Depth] Bloom analysis error: ' . $e->getMessage());
            }
        }
    }

    private function create_tensor_from_data($data) {
        // Simplified tensor creation - real implementation would use embeddings
        return [
            'url_features' => isset($data['url_hash']) ? [$this->hash_to_float($data['url_hash'])] : [0.0],
            'content_features' => $this->extract_content_features($data),
            'temporal_features' => [
                'hour' => (int) date('G') / 24,
                'day' => (int) date('N') / 7,
            ],
            'metadata' => [
                'privacy_mode' => $data['privacy_mode'] ?? 'balanced',
                'timestamp' => $data['timestamp'] ?? time(),
            ],
        ];
    }

    private function extract_content_features($data) {
        // Simplified feature extraction
        return [
            0.5, // placeholder features
            0.5,
            0.5,
        ];
    }

    private function hash_to_float($hash) {
        // Convert hash to float between 0 and 1
        return hexdec(substr($hash, 0, 8)) / 0xFFFFFFFF;
    }

    private function store_tensor_for_bloom($tensor, $user_id) {
        // Store tensor data for Bloom to process later
        $metadata_table = $this->wpdb->prefix . 'avd_scraped_data';
        // Tensors would be stored in Bloom's tensor tables
        // This is just a placeholder for the integration point
    }

    private function store_behavioral_pattern($pattern, $user_id) {
        $pattern_hash = hash('sha256', json_encode($pattern));

        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->wpdb->prefix}avd_behavioral_patterns
             WHERE user_id = %d AND pattern_hash = %s",
            $user_id,
            $pattern_hash
        ));

        if ($existing) {
            $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->wpdb->prefix}avd_behavioral_patterns
                 SET occurrences = occurrences + 1, last_seen = NOW()
                 WHERE id = %d",
                $existing
            ));
        } else {
            $this->wpdb->insert(
                $this->wpdb->prefix . 'avd_behavioral_patterns',
                [
                    'user_id' => $user_id,
                    'pattern_hash' => $pattern_hash,
                    'pattern_type' => $pattern['type'] ?? 'bloom_detected',
                    'pattern_data' => json_encode($pattern),
                    'confidence_score' => $pattern['confidence'] ?? 0.75,
                ],
                ['%d', '%s', '%s', '%s', '%f']
            );

            $pattern_id = $this->wpdb->insert_id;

            // Award pattern reward
            $this->award_pattern_reward($user_id, $pattern_id);

            // Trigger action for APS integration
            do_action('avd_pattern_discovered', array_merge($pattern, [
                'avd_pattern_id' => $pattern_id,
                'pattern_hash' => $pattern_hash,
            ]), $user_id);
        }
    }

    private function award_pattern_reward($user_id, $pattern_id) {
        $reward_amount = get_option('avd_reward_per_pattern', 0.01);

        $this->wpdb->insert(
            $this->wpdb->prefix . 'avd_user_rewards',
            [
                'user_id' => $user_id,
                'reward_type' => 'pattern_discovery',
                'amount' => $reward_amount,
                'pattern_id' => $pattern_id,
                'status' => 'pending',
            ],
            ['%d', '%s', '%f', '%d', '%s']
        );
    }

    public function get_analyzed_count() {
        return $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->wpdb->prefix}avd_behavioral_patterns
             WHERE bloom_pattern_id IS NOT NULL"
        );
    }
}
