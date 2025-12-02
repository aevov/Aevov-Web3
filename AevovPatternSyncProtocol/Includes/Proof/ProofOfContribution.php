<?php
/**
 * Proof of Contribution System for AevovPatternSyncProtocol
 *
 * Implements cryptographic proof generation and verification for contributor
 * rewards in the distributed pattern synchronization network.
 *
 * @package AevovPatternSyncProtocol
 * @subpackage Proof
 * @since 1.0.0
 */

namespace APS\Proof;

use APS\DB\APS_Pattern_DB;
use APS\Consensus\ConsensusMechanism;

class ProofOfContribution {

    /**
     * Database handler
     *
     * @var APS_Pattern_DB
     */
    private $pattern_db;

    /**
     * Consensus mechanism handler
     *
     * @var ConsensusMechanism
     */
    private $consensus;

    /**
     * WordPress database object
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Minimum contribution value required for proof generation
     *
     * @var float
     */
    private $min_contribution_value = 0.01;

    /**
     * Reward pool percentage for contributors (70% of total value)
     *
     * @var float
     */
    private $contributor_reward_percentage = 0.70;

    /**
     * Network fee percentage (30% of total value)
     *
     * @var float
     */
    private $network_fee_percentage = 0.30;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->pattern_db = new APS_Pattern_DB();
        $this->consensus = new ConsensusMechanism();

        // Create proof tracking tables
        $this->create_tables();
    }

    /**
     * Create database tables for proof tracking
     *
     * @return void
     */
    public function create_tables() {
        $proofs_table = $this->wpdb->prefix . 'aps_proofs';
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$proofs_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            proof_hash VARCHAR(64) NOT NULL UNIQUE,
            contributor_id BIGINT UNSIGNED NOT NULL,
            pattern_id BIGINT UNSIGNED NOT NULL,
            consensus_id BIGINT UNSIGNED NULL,
            contribution_type ENUM('creation', 'validation', 'synchronization', 'dispute_resolution') NOT NULL,
            contribution_value DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            proof_data LONGTEXT NULL,
            signature VARCHAR(255) NULL,
            verified TINYINT(1) DEFAULT 0,
            verified_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            metadata LONGTEXT NULL,
            INDEX contributor_id (contributor_id),
            INDEX pattern_id (pattern_id),
            INDEX consensus_id (consensus_id),
            INDEX proof_hash (proof_hash),
            INDEX verified (verified),
            INDEX created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Create rewards table
        $rewards_table = $this->wpdb->prefix . 'aps_rewards';
        $sql_rewards = "CREATE TABLE IF NOT EXISTS {$rewards_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            proof_id BIGINT UNSIGNED NOT NULL,
            contributor_id BIGINT UNSIGNED NOT NULL,
            pattern_id BIGINT UNSIGNED NOT NULL,
            reward_amount DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            reward_type ENUM('contribution', 'validation', 'bonus', 'penalty') NOT NULL,
            status ENUM('pending', 'distributed', 'failed') DEFAULT 'pending',
            distributed_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            metadata LONGTEXT NULL,
            INDEX proof_id (proof_id),
            INDEX contributor_id (contributor_id),
            INDEX pattern_id (pattern_id),
            INDEX status (status),
            INDEX created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql_rewards);

        // Create contribution history table
        $history_table = $this->wpdb->prefix . 'aps_contribution_history';
        $sql_history = "CREATE TABLE IF NOT EXISTS {$history_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contributor_id BIGINT UNSIGNED NOT NULL,
            pattern_id BIGINT UNSIGNED NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            contribution_score DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            proof_hash VARCHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            metadata LONGTEXT NULL,
            INDEX contributor_id (contributor_id),
            INDEX pattern_id (pattern_id),
            INDEX action_type (action_type),
            INDEX created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql_history);
    }

    /**
     * Generate proof of contribution for a contributor
     *
     * @param int $contributor_id WordPress user ID
     * @param int $pattern_id Pattern ID
     * @param array $contribution_data Contribution details
     * @return array Proof generation result
     */
    public function generate_proof($contributor_id, $pattern_id, $contribution_data = []) {
        // Validate inputs
        if (empty($contributor_id) || empty($pattern_id)) {
            return [
                'success' => false,
                'error' => 'Invalid contributor or pattern ID'
            ];
        }

        // Get pattern data
        $pattern = $this->pattern_db->get_pattern($pattern_id);
        if (!$pattern) {
            return [
                'success' => false,
                'error' => 'Pattern not found'
            ];
        }

        // Determine contribution type and value
        $contribution_type = $contribution_data['type'] ?? 'validation';
        $contribution_value = $this->calculate_contribution_value($contribution_type, $contribution_data);

        if ($contribution_value < $this->min_contribution_value) {
            return [
                'success' => false,
                'error' => 'Contribution value below minimum threshold'
            ];
        }

        // Generate cryptographic proof
        $proof_data = [
            'contributor_id' => $contributor_id,
            'pattern_id' => $pattern_id,
            'contribution_type' => $contribution_type,
            'contribution_value' => $contribution_value,
            'timestamp' => current_time('mysql', 1),
            'nonce' => wp_generate_password(32, false)
        ];

        $proof_hash = $this->generate_proof_hash($proof_data);
        $signature = $this->sign_proof($proof_hash, $contributor_id);

        // Store proof in database
        $proofs_table = $this->wpdb->prefix . 'aps_proofs';
        $result = $this->wpdb->insert(
            $proofs_table,
            [
                'proof_hash' => $proof_hash,
                'contributor_id' => $contributor_id,
                'pattern_id' => $pattern_id,
                'consensus_id' => $contribution_data['consensus_id'] ?? null,
                'contribution_type' => $contribution_type,
                'contribution_value' => $contribution_value,
                'proof_data' => json_encode($proof_data),
                'signature' => $signature,
                'verified' => 0
            ],
            ['%s', '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%d']
        );

        if ($result === false) {
            return [
                'success' => false,
                'error' => 'Failed to store proof: ' . $this->wpdb->last_error
            ];
        }

        $proof_id = $this->wpdb->insert_id;

        // Record in contribution history
        $this->record_contribution_history($contributor_id, $pattern_id, $contribution_type, $contribution_value, $proof_hash);

        // Trigger action hook
        do_action('aps_proof_generated', $proof_id, $contributor_id, $pattern_id, $proof_hash);

        return [
            'success' => true,
            'proof_id' => $proof_id,
            'proof_hash' => $proof_hash,
            'contribution_value' => $contribution_value,
            'signature' => $signature
        ];
    }

    /**
     * Verify proof of contribution
     *
     * @param array $proof_data Proof data to verify
     * @return array Verification result
     */
    public function verify_proof($proof_data) {
        if (empty($proof_data['proof_hash'])) {
            return [
                'valid' => false,
                'error' => 'Proof hash required'
            ];
        }

        $proof_hash = $proof_data['proof_hash'];

        // Get proof from database
        $proofs_table = $this->wpdb->prefix . 'aps_proofs';
        $proof = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$proofs_table} WHERE proof_hash = %s LIMIT 1",
            $proof_hash
        ));

        if (!$proof) {
            return [
                'valid' => false,
                'error' => 'Proof not found'
            ];
        }

        // Verify proof hasn't been tampered with
        $stored_proof_data = json_decode($proof->proof_data, true);
        $recalculated_hash = $this->generate_proof_hash($stored_proof_data);

        if ($recalculated_hash !== $proof_hash) {
            return [
                'valid' => false,
                'error' => 'Proof hash mismatch - possible tampering'
            ];
        }

        // Verify signature if provided
        if (!empty($proof->signature)) {
            $signature_valid = $this->verify_signature($proof_hash, $proof->signature, $proof->contributor_id);
            if (!$signature_valid) {
                return [
                    'valid' => false,
                    'error' => 'Invalid signature'
                ];
            }
        }

        // Verify pattern exists
        $pattern = $this->pattern_db->get_pattern($proof->pattern_id);
        if (!$pattern) {
            return [
                'valid' => false,
                'error' => 'Associated pattern not found'
            ];
        }

        // Mark proof as verified
        if ($proof->verified == 0) {
            $this->wpdb->update(
                $proofs_table,
                [
                    'verified' => 1,
                    'verified_at' => current_time('mysql', 1)
                ],
                ['id' => $proof->id],
                ['%d', '%s'],
                ['%d']
            );
        }

        // Trigger action hook
        do_action('aps_proof_verified', $proof->id, $proof->contributor_id, $proof->pattern_id);

        return [
            'valid' => true,
            'proof_id' => $proof->id,
            'contributor_id' => $proof->contributor_id,
            'pattern_id' => $proof->pattern_id,
            'contribution_type' => $proof->contribution_type,
            'contribution_value' => $proof->contribution_value,
            'verified' => true
        ];
    }

    /**
     * Calculate rewards for a pattern based on all contributions
     *
     * @param int $pattern_id Pattern ID
     * @return array Reward calculation result
     */
    public function calculate_rewards($pattern_id) {
        // Get all verified proofs for this pattern
        $proofs_table = $this->wpdb->prefix . 'aps_proofs';
        $proofs = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$proofs_table} WHERE pattern_id = %d AND verified = 1",
            $pattern_id
        ), ARRAY_A);

        if (empty($proofs)) {
            return [
                'success' => false,
                'error' => 'No verified proofs found for pattern'
            ];
        }

        // Calculate total contribution value
        $total_value = array_sum(array_column($proofs, 'contribution_value'));

        // Calculate reward pool (70% of total value)
        $reward_pool = $total_value * $this->contributor_reward_percentage;
        $network_fee = $total_value * $this->network_fee_percentage;

        // Distribute rewards proportionally
        $rewards = [];
        foreach ($proofs as $proof) {
            $contributor_share = ($proof['contribution_value'] / $total_value);
            $reward_amount = $reward_pool * $contributor_share;

            $rewards[] = [
                'proof_id' => $proof['id'],
                'contributor_id' => $proof['contributor_id'],
                'pattern_id' => $pattern_id,
                'contribution_value' => $proof['contribution_value'],
                'contribution_share' => $contributor_share * 100, // percentage
                'reward_amount' => $reward_amount
            ];
        }

        return [
            'success' => true,
            'pattern_id' => $pattern_id,
            'total_value' => $total_value,
            'reward_pool' => $reward_pool,
            'network_fee' => $network_fee,
            'contributor_count' => count($proofs),
            'rewards' => $rewards
        ];
    }

    /**
     * Distribute rewards to contributors
     *
     * @param array $rewards_array Rewards to distribute
     * @return array Distribution result
     */
    public function distribute_rewards($rewards_array) {
        if (empty($rewards_array)) {
            return [
                'success' => false,
                'error' => 'No rewards to distribute'
            ];
        }

        $rewards_table = $this->wpdb->prefix . 'aps_rewards';
        $distributed = 0;
        $failed = 0;

        foreach ($rewards_array as $reward) {
            // Create reward record
            $result = $this->wpdb->insert(
                $rewards_table,
                [
                    'proof_id' => $reward['proof_id'],
                    'contributor_id' => $reward['contributor_id'],
                    'pattern_id' => $reward['pattern_id'],
                    'reward_amount' => $reward['reward_amount'],
                    'reward_type' => 'contribution',
                    'status' => 'pending',
                    'metadata' => json_encode([
                        'contribution_value' => $reward['contribution_value'],
                        'contribution_share' => $reward['contribution_share']
                    ])
                ],
                ['%d', '%d', '%d', '%f', '%s', '%s', '%s']
            );

            if ($result !== false) {
                $reward_id = $this->wpdb->insert_id;

                // Update status to distributed
                $this->wpdb->update(
                    $rewards_table,
                    [
                        'status' => 'distributed',
                        'distributed_at' => current_time('mysql', 1)
                    ],
                    ['id' => $reward_id],
                    ['%s', '%s'],
                    ['%d']
                );

                // Trigger reward distribution action
                do_action('aps_reward_distributed', $reward_id, $reward['contributor_id'], $reward['reward_amount']);

                $distributed++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => true,
            'distributed' => $distributed,
            'failed' => $failed,
            'total' => count($rewards_array)
        ];
    }

    /**
     * Get contribution history for a contributor
     *
     * @param int $contributor_id WordPress user ID
     * @param array $args Query arguments
     * @return array Contribution history
     */
    public function get_contributor_history($contributor_id, $args = []) {
        $defaults = [
            'limit' => 50,
            'offset' => 0,
            'order_by' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $history_table = $this->wpdb->prefix . 'aps_contribution_history';
        $proofs_table = $this->wpdb->prefix . 'aps_proofs';
        $rewards_table = $this->wpdb->prefix . 'aps_rewards';

        // Get contribution history
        $history = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT * FROM {$history_table}
             WHERE contributor_id = %d
             ORDER BY {$args['order_by']} {$args['order']}
             LIMIT %d OFFSET %d",
            $contributor_id,
            $args['limit'],
            $args['offset']
        ), ARRAY_A);

        // Get total contribution score
        $total_score = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(contribution_score) FROM {$history_table} WHERE contributor_id = %d",
            $contributor_id
        ));

        // Get total verified proofs
        $verified_proofs = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$proofs_table} WHERE contributor_id = %d AND verified = 1",
            $contributor_id
        ));

        // Get total rewards earned
        $total_rewards = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT SUM(reward_amount) FROM {$rewards_table} WHERE contributor_id = %d AND status = 'distributed'",
            $contributor_id
        ));

        return [
            'contributor_id' => $contributor_id,
            'total_contribution_score' => floatval($total_score),
            'verified_proofs_count' => intval($verified_proofs),
            'total_rewards_earned' => floatval($total_rewards),
            'history' => $history,
            'history_count' => count($history)
        ];
    }

    /**
     * Get leaderboard of top contributors
     *
     * @param array $args Query arguments
     * @return array Leaderboard data
     */
    public function get_leaderboard($args = []) {
        $defaults = [
            'limit' => 100,
            'time_period' => 'all_time' // all_time, month, week, day
        ];

        $args = wp_parse_args($args, $defaults);

        $history_table = $this->wpdb->prefix . 'aps_contribution_history';

        // Build time filter
        $time_filter = '';
        if ($args['time_period'] !== 'all_time') {
            $intervals = [
                'day' => '1 DAY',
                'week' => '7 DAY',
                'month' => '30 DAY'
            ];

            if (isset($intervals[$args['time_period']])) {
                $time_filter = "AND created_at >= DATE_SUB(NOW(), INTERVAL {$intervals[$args['time_period']]})";
            }
        }

        $leaderboard = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT
                contributor_id,
                COUNT(*) as contribution_count,
                SUM(contribution_score) as total_score,
                MAX(created_at) as last_contribution
             FROM {$history_table}
             WHERE 1=1 {$time_filter}
             GROUP BY contributor_id
             ORDER BY total_score DESC
             LIMIT %d",
            $args['limit']
        ), ARRAY_A);

        return [
            'time_period' => $args['time_period'],
            'leaderboard' => $leaderboard,
            'count' => count($leaderboard)
        ];
    }

    /**
     * Calculate contribution value based on type and data
     *
     * @param string $contribution_type Type of contribution
     * @param array $contribution_data Contribution details
     * @return float Contribution value
     */
    private function calculate_contribution_value($contribution_type, $contribution_data) {
        // Base values for different contribution types
        $base_values = [
            'creation' => 1.0,
            'validation' => 0.5,
            'synchronization' => 0.3,
            'dispute_resolution' => 0.7
        ];

        $base_value = $base_values[$contribution_type] ?? 0.1;

        // Apply multipliers based on contribution data
        $multiplier = 1.0;

        // Quality multiplier
        if (isset($contribution_data['quality_score'])) {
            $multiplier *= (1 + ($contribution_data['quality_score'] - 0.5));
        }

        // Complexity multiplier
        if (isset($contribution_data['complexity'])) {
            $multiplier *= (1 + ($contribution_data['complexity'] * 0.5));
        }

        // Consensus multiplier (if contribution helped reach consensus)
        if (isset($contribution_data['consensus_reached']) && $contribution_data['consensus_reached']) {
            $multiplier *= 1.5;
        }

        return $base_value * $multiplier;
    }

    /**
     * Generate cryptographic hash for proof data
     *
     * @param array $proof_data Proof data
     * @return string Proof hash
     */
    private function generate_proof_hash($proof_data) {
        $hash_string = json_encode($proof_data, JSON_UNESCAPED_UNICODE);
        return hash('sha256', $hash_string);
    }

    /**
     * Sign proof with contributor credentials
     *
     * @param string $proof_hash Proof hash to sign
     * @param int $contributor_id Contributor ID
     * @return string Signature
     */
    private function sign_proof($proof_hash, $contributor_id) {
        // In a production system, this would use proper cryptographic signing
        // For now, use HMAC with WordPress salt
        $secret = wp_salt('auth') . $contributor_id;
        return hash_hmac('sha256', $proof_hash, $secret);
    }

    /**
     * Verify proof signature
     *
     * @param string $proof_hash Proof hash
     * @param string $signature Signature to verify
     * @param int $contributor_id Contributor ID
     * @return bool True if valid
     */
    private function verify_signature($proof_hash, $signature, $contributor_id) {
        $expected_signature = $this->sign_proof($proof_hash, $contributor_id);
        return hash_equals($expected_signature, $signature);
    }

    /**
     * Record contribution in history table
     *
     * @param int $contributor_id Contributor ID
     * @param int $pattern_id Pattern ID
     * @param string $action_type Action type
     * @param float $contribution_score Contribution score
     * @param string $proof_hash Proof hash
     * @return bool Success status
     */
    private function record_contribution_history($contributor_id, $pattern_id, $action_type, $contribution_score, $proof_hash) {
        $history_table = $this->wpdb->prefix . 'aps_contribution_history';

        $result = $this->wpdb->insert(
            $history_table,
            [
                'contributor_id' => $contributor_id,
                'pattern_id' => $pattern_id,
                'action_type' => $action_type,
                'contribution_score' => $contribution_score,
                'proof_hash' => $proof_hash
            ],
            ['%d', '%d', '%s', '%f', '%s']
        );

        return $result !== false;
    }

    /**
     * Get proof details by ID
     *
     * @param int $proof_id Proof ID
     * @return object|null Proof record
     */
    public function get_proof($proof_id) {
        $proofs_table = $this->wpdb->prefix . 'aps_proofs';

        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$proofs_table} WHERE id = %d",
            $proof_id
        ));
    }

    /**
     * Get all proofs for a pattern
     *
     * @param int $pattern_id Pattern ID
     * @param bool $verified_only Only return verified proofs
     * @return array Array of proofs
     */
    public function get_pattern_proofs($pattern_id, $verified_only = false) {
        $proofs_table = $this->wpdb->prefix . 'aps_proofs';

        $where = $this->wpdb->prepare("pattern_id = %d", $pattern_id);
        if ($verified_only) {
            $where .= " AND verified = 1";
        }

        return $this->wpdb->get_results(
            "SELECT * FROM {$proofs_table} WHERE {$where} ORDER BY created_at DESC",
            ARRAY_A
        );
    }
}
