<?php
/**
 * Relay Manager
 *
 * Manages bandwidth sharing and packet relaying across the mesh network.
 * Implements incentive-based relay system where nodes earn tokens for relaying.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Relay;

use Aevov\Meshcore\Core\NodeManager;
use Aevov\Meshcore\P2P\ConnectionManager;
use Aevov\Meshcore\Routing\MeshRouter;

/**
 * Relay Manager Class
 */
class RelayManager
{
    /**
     * Node manager
     *
     * @var NodeManager
     */
    private NodeManager $node_manager;

    /**
     * Connection manager
     *
     * @var ConnectionManager
     */
    private ConnectionManager $connection_manager;

    /**
     * Mesh router
     *
     * @var MeshRouter
     */
    private MeshRouter $mesh_router;

    /**
     * Relay queue
     *
     * @var array
     */
    private array $relay_queue = [];

    /**
     * Max relay bandwidth (bytes/sec)
     *
     * @var int
     */
    private int $max_relay_bandwidth;

    /**
     * Tokens earned per MB relayed
     *
     * @var int
     */
    private int $tokens_per_mb = 100;

    /**
     * Constructor
     *
     * @param NodeManager $node_manager Node manager
     * @param ConnectionManager $connection_manager Connection manager
     * @param MeshRouter $mesh_router Mesh router
     */
    public function __construct(
        NodeManager $node_manager,
        ConnectionManager $connection_manager,
        MeshRouter $mesh_router
    ) {
        $this->node_manager = $node_manager;
        $this->connection_manager = $connection_manager;
        $this->mesh_router = $mesh_router;
        $this->max_relay_bandwidth = (int) get_option('aevov_meshcore_relay_bandwidth', 5 * 1024 * 1024); // 5 MB/s
        $this->tokens_per_mb = (int) get_option('aevov_meshcore_tokens_per_mb', 100);
    }

    /**
     * Relay packet
     *
     * @param array $packet Packet to relay
     * @param string $from_node_id Source node
     * @return bool Success
     */
    public function relay_packet(array $packet, string $from_node_id): bool
    {
        // Check if relay is enabled
        if (!$this->node_manager->has_capability('mesh.relay')) {
            return false;
        }

        // Check bandwidth limit
        if (!$this->check_bandwidth_available($packet)) {
            // Queue for later
            $this->queue_packet($packet, $from_node_id);
            return false;
        }

        $destination_id = $packet['destination_id'] ?? null;
        if (!$destination_id) {
            return false;
        }

        // Route the packet
        $success = $this->mesh_router->route_packet($destination_id, $packet, $packet['hop_count'] ?? 0);

        if ($success) {
            // Record relay for incentives
            $packet_size = strlen(wp_json_encode($packet));
            $this->record_relay($from_node_id, $destination_id, $packet_size);
        }

        return $success;
    }

    /**
     * Check if bandwidth is available
     *
     * @param array $packet Packet
     * @return bool
     */
    private function check_bandwidth_available(array $packet): bool
    {
        $packet_size = strlen(wp_json_encode($packet));
        $current_usage = $this->get_current_relay_usage();

        return ($current_usage + $packet_size) <= $this->max_relay_bandwidth;
    }

    /**
     * Get current relay bandwidth usage (bytes/sec)
     *
     * @return int
     */
    private function get_current_relay_usage(): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_relay_stats';
        $local_node_id = $this->node_manager->get_node_id();

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(bytes_relayed)
             FROM {$table}
             WHERE relay_node_id = %s
             AND relay_time >= DATE_SUB(NOW(), INTERVAL 1 SECOND)",
            $local_node_id
        ));

        return (int) $result;
    }

    /**
     * Queue packet for later relay
     *
     * @param array $packet Packet
     * @param string $from_node_id Source node
     * @return void
     */
    private function queue_packet(array $packet, string $from_node_id): void
    {
        $this->relay_queue[] = [
            'packet' => $packet,
            'from_node_id' => $from_node_id,
            'queued_at' => time()
        ];
    }

    /**
     * Process relay queue
     *
     * @return void
     */
    public function process_relay_queue(): void
    {
        if (empty($this->relay_queue)) {
            return;
        }

        $processed = [];

        foreach ($this->relay_queue as $index => $item) {
            // Check age - drop if too old (30 seconds)
            if (time() - $item['queued_at'] > 30) {
                $processed[] = $index;
                continue;
            }

            // Try to relay
            if ($this->check_bandwidth_available($item['packet'])) {
                $this->relay_packet($item['packet'], $item['from_node_id']);
                $processed[] = $index;
            }
        }

        // Remove processed items
        foreach (array_reverse($processed) as $index) {
            unset($this->relay_queue[$index]);
        }

        $this->relay_queue = array_values($this->relay_queue);
    }

    /**
     * Record relay for incentive calculation
     *
     * @param string $source_node_id Source node
     * @param string $destination_node_id Destination node
     * @param int $bytes_relayed Bytes relayed
     * @return void
     */
    private function record_relay(string $source_node_id, string $destination_node_id, int $bytes_relayed): void
    {
        global $wpdb;

        $local_node_id = $this->node_manager->get_node_id();

        // Calculate tokens earned
        $mb_relayed = $bytes_relayed / (1024 * 1024);
        $tokens_earned = (int) ($mb_relayed * $this->tokens_per_mb);

        // Record relay stats
        $table_stats = $wpdb->prefix . 'meshcore_relay_stats';
        $wpdb->insert(
            $table_stats,
            [
                'relay_node_id' => $local_node_id,
                'source_node_id' => $source_node_id,
                'destination_node_id' => $destination_node_id,
                'bytes_relayed' => $bytes_relayed,
                'packets_relayed' => 1,
                'tokens_earned' => $tokens_earned
            ],
            ['%s', '%s', '%s', '%d', '%d', '%d']
        );

        // Update bandwidth tokens
        $table_tokens = $wpdb->prefix . 'meshcore_bandwidth_tokens';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table_tokens} (node_id, tokens_earned, bytes_relayed)
             VALUES (%s, %d, %d)
             ON DUPLICATE KEY UPDATE
             tokens_earned = tokens_earned + VALUES(tokens_earned),
             bytes_relayed = bytes_relayed + VALUES(bytes_relayed)",
            $local_node_id,
            $tokens_earned,
            $bytes_relayed
        ));

        do_action('aevov_meshcore_relay_recorded', $local_node_id, $bytes_relayed, $tokens_earned);
    }

    /**
     * Get relay statistics
     *
     * @return array
     */
    public function get_stats(): array
    {
        global $wpdb;

        $table_stats = $wpdb->prefix . 'meshcore_relay_stats';
        $table_tokens = $wpdb->prefix . 'meshcore_bandwidth_tokens';
        $local_node_id = $this->node_manager->get_node_id();

        // Total relayed
        $total_relayed = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(bytes_relayed) FROM {$table_stats} WHERE relay_node_id = %s",
            $local_node_id
        ));

        // Tokens
        $tokens = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_tokens} WHERE node_id = %s",
            $local_node_id
        ), ARRAY_A);

        return [
            'total_bytes_relayed' => (int) $total_relayed,
            'tokens_earned' => $tokens['tokens_earned'] ?? 0,
            'tokens_spent' => $tokens['tokens_spent'] ?? 0,
            'tokens_balance' => ($tokens['tokens_earned'] ?? 0) - ($tokens['tokens_spent'] ?? 0),
            'queue_size' => count($this->relay_queue),
            'max_relay_bandwidth' => $this->max_relay_bandwidth
        ];
    }

    /**
     * Check if node has enough tokens
     *
     * @param int $required_tokens Required tokens
     * @return bool
     */
    public function has_tokens(int $required_tokens): bool
    {
        $stats = $this->get_stats();
        return $stats['tokens_balance'] >= $required_tokens;
    }

    /**
     * Spend tokens
     *
     * @param int $tokens Tokens to spend
     * @return bool Success
     */
    public function spend_tokens(int $tokens): bool
    {
        if (!$this->has_tokens($tokens)) {
            return false;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_bandwidth_tokens';
        $local_node_id = $this->node_manager->get_node_id();

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET tokens_spent = tokens_spent + %d
             WHERE node_id = %s",
            $tokens,
            $local_node_id
        ));

        return true;
    }
}
