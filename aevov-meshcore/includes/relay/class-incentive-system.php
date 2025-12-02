<?php
/**
 * Incentive System
 *
 * Manages bandwidth sharing incentives through token economics.
 * Nodes earn tokens by relaying traffic and spend tokens to use the network.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Relay;

use Aevov\Meshcore\Core\NodeManager;

/**
 * Incentive System Class
 */
class IncentiveSystem
{
    /**
     * Node manager
     *
     * @var NodeManager
     */
    private NodeManager $node_manager;

    /**
     * Token exchange rate (tokens per MB)
     *
     * @var float
     */
    private float $token_rate;

    /**
     * Base reputation score
     *
     * @var int
     */
    private int $base_reputation = 100;

    /**
     * Constructor
     *
     * @param NodeManager $node_manager Node manager
     */
    public function __construct(NodeManager $node_manager)
    {
        $this->node_manager = $node_manager;
        $this->token_rate = (float) get_option('aevov_meshcore_token_rate', 100.0);
    }

    /**
     * Calculate tokens earned for relay
     *
     * @param int $bytes_relayed Bytes relayed
     * @param float $quality Quality of service (0-1)
     * @return int Tokens earned
     */
    public function calculate_earned_tokens(int $bytes_relayed, float $quality = 1.0): int
    {
        $mb = $bytes_relayed / (1024 * 1024);
        $base_tokens = $mb * $this->token_rate;

        // Apply quality multiplier
        $tokens = $base_tokens * $quality;

        // Apply reputation multiplier
        $reputation_multiplier = $this->get_reputation_multiplier();
        $tokens *= $reputation_multiplier;

        return (int) $tokens;
    }

    /**
     * Calculate tokens required for data transfer
     *
     * @param int $bytes Bytes to transfer
     * @param int $hop_count Number of hops
     * @return int Tokens required
     */
    public function calculate_required_tokens(int $bytes, int $hop_count = 1): int
    {
        $mb = $bytes / (1024 * 1024);
        $base_tokens = $mb * $this->token_rate;

        // Multiply by hop count (pay for each relay)
        $tokens = $base_tokens * $hop_count;

        return (int) ceil($tokens);
    }

    /**
     * Award tokens to node
     *
     * @param string $node_id Node ID
     * @param int $tokens Tokens to award
     * @param string $reason Reason for award
     * @return bool Success
     */
    public function award_tokens(string $node_id, int $tokens, string $reason = 'relay'): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_bandwidth_tokens';

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (node_id, tokens_earned)
             VALUES (%s, %d)
             ON DUPLICATE KEY UPDATE tokens_earned = tokens_earned + VALUES(tokens_earned)",
            $node_id,
            $tokens
        ));

        do_action('aevov_meshcore_tokens_awarded', $node_id, $tokens, $reason);

        return true;
    }

    /**
     * Deduct tokens from node
     *
     * @param string $node_id Node ID
     * @param int $tokens Tokens to deduct
     * @return bool Success
     */
    public function deduct_tokens(string $node_id, int $tokens): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_bandwidth_tokens';

        // Check if node has enough tokens
        $current = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE node_id = %s",
            $node_id
        ), ARRAY_A);

        if (!$current) {
            return false;
        }

        $balance = ($current['tokens_earned'] ?? 0) - ($current['tokens_spent'] ?? 0);

        if ($balance < $tokens) {
            return false;
        }

        // Deduct tokens
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET tokens_spent = tokens_spent + %d
             WHERE node_id = %s",
            $tokens,
            $node_id
        ));

        do_action('aevov_meshcore_tokens_spent', $node_id, $tokens);

        return true;
    }

    /**
     * Get token balance for node
     *
     * @param string $node_id Node ID
     * @return int Token balance
     */
    public function get_balance(string $node_id): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_bandwidth_tokens';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT tokens_earned, tokens_spent FROM {$table} WHERE node_id = %s",
            $node_id
        ), ARRAY_A);

        if (!$result) {
            return 0;
        }

        return ($result['tokens_earned'] ?? 0) - ($result['tokens_spent'] ?? 0);
    }

    /**
     * Update reputation score
     *
     * @param string $node_id Node ID
     * @param float $modifier Reputation modifier (0.5 = 50% reputation, 1.5 = 150%)
     * @return void
     */
    public function update_reputation(string $node_id, float $modifier): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_bandwidth_tokens';

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (node_id, reputation_modifier)
             VALUES (%s, %f)
             ON DUPLICATE KEY UPDATE reputation_modifier = VALUES(reputation_modifier)",
            $node_id,
            $modifier
        ));
    }

    /**
     * Get reputation multiplier for current node
     *
     * @return float Multiplier (1.0 = normal)
     */
    private function get_reputation_multiplier(): float
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_bandwidth_tokens';
        $node_id = $this->node_manager->get_node_id();

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT reputation_modifier FROM {$table} WHERE node_id = %s",
            $node_id
        ));

        return $result ? (float) $result : 1.0;
    }

    /**
     * Get incentive statistics
     *
     * @return array
     */
    public function get_stats(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_bandwidth_tokens';

        $total_earned = $wpdb->get_var("SELECT SUM(tokens_earned) FROM {$table}");
        $total_spent = $wpdb->get_var("SELECT SUM(tokens_spent) FROM {$table}");
        $total_nodes = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        return [
            'total_tokens_earned' => (int) $total_earned,
            'total_tokens_spent' => (int) $total_spent,
            'total_tokens_in_circulation' => (int) $total_earned - (int) $total_spent,
            'total_participating_nodes' => (int) $total_nodes,
            'token_rate' => $this->token_rate
        ];
    }
}
