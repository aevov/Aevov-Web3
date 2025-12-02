<?php
/**
 * Connection Manager
 *
 * Manages WebRTC peer-to-peer connections between mesh nodes.
 * Handles connection establishment, maintenance, and quality monitoring.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\P2P;

use Aevov\Meshcore\Core\NodeManager;

/**
 * Connection Manager Class
 */
class ConnectionManager
{
    /**
     * Node manager
     *
     * @var NodeManager
     */
    private NodeManager $node_manager;

    /**
     * Active connections
     *
     * @var array
     */
    private array $connections = [];

    /**
     * Maximum connections per node
     *
     * @var int
     */
    private int $max_connections = 50;

    /**
     * Desired minimum connections
     *
     * @var int
     */
    private int $min_connections = 3;

    /**
     * Constructor
     *
     * @param NodeManager $node_manager Node manager
     */
    public function __construct(NodeManager $node_manager)
    {
        $this->node_manager = $node_manager;
        $this->max_connections = (int) get_option('aevov_meshcore_max_connections', 50);
        $this->min_connections = (int) get_option('aevov_meshcore_min_connections', 3);
    }

    /**
     * Initiate connection to a peer
     *
     * @param string $peer_node_id Peer node ID
     * @param array $peer_info Peer information
     * @return array Connection info
     */
    public function connect_to_peer(string $peer_node_id, array $peer_info): array
    {
        global $wpdb;

        // Check if already connected
        if ($this->is_connected_to($peer_node_id)) {
            throw new \Exception('Already connected to peer');
        }

        // Check connection limit
        if ($this->get_connection_count() >= $this->max_connections) {
            throw new \Exception('Maximum connection limit reached');
        }

        // Generate connection ID
        $connection_id = $this->generate_connection_id($this->node_manager->get_node_id(), $peer_node_id);

        // Create connection record
        $table = $wpdb->prefix . 'meshcore_connections';
        $wpdb->insert(
            $table,
            [
                'connection_id' => $connection_id,
                'local_node_id' => $this->node_manager->get_node_id(),
                'remote_node_id' => $peer_node_id,
                'connection_type' => 'webrtc',
                'status' => 'connecting',
                'last_activity' => current_time('mysql'),
                'metadata' => wp_json_encode([
                    'peer_info' => $peer_info,
                    'initiated_at' => time()
                ])
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return [
            'connection_id' => $connection_id,
            'local_node_id' => $this->node_manager->get_node_id(),
            'remote_node_id' => $peer_node_id,
            'status' => 'connecting'
        ];
    }

    /**
     * Accept connection from peer
     *
     * @param string $peer_node_id Peer node ID
     * @param array $offer_data WebRTC offer data
     * @return array Answer data
     */
    public function accept_connection(string $peer_node_id, array $offer_data): array
    {
        global $wpdb;

        // Verify peer signature
        if (!$this->verify_peer($peer_node_id, $offer_data)) {
            throw new \Exception('Invalid peer signature');
        }

        $connection_id = $this->generate_connection_id($peer_node_id, $this->node_manager->get_node_id());

        // Create connection record
        $table = $wpdb->prefix . 'meshcore_connections';
        $wpdb->insert(
            $table,
            [
                'connection_id' => $connection_id,
                'local_node_id' => $this->node_manager->get_node_id(),
                'remote_node_id' => $peer_node_id,
                'connection_type' => 'webrtc',
                'status' => 'connecting',
                'last_activity' => current_time('mysql'),
                'metadata' => wp_json_encode([
                    'offer' => $offer_data,
                    'accepted_at' => time()
                ])
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return [
            'connection_id' => $connection_id,
            'answer' => [], // WebRTC answer will be generated client-side
            'node_info' => $this->node_manager->get_node_info()
        ];
    }

    /**
     * Mark connection as established
     *
     * @param string $connection_id Connection ID
     * @param array $connection_quality Quality metrics
     * @return void
     */
    public function mark_established(string $connection_id, array $connection_quality = []): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';

        $wpdb->update(
            $table,
            [
                'status' => 'connected',
                'established_at' => current_time('mysql'),
                'last_activity' => current_time('mysql'),
                'quality_score' => $connection_quality['quality_score'] ?? 0,
                'latency' => $connection_quality['latency'] ?? 0,
                'bandwidth_up' => $connection_quality['bandwidth_up'] ?? 0,
                'bandwidth_down' => $connection_quality['bandwidth_down'] ?? 0
            ],
            ['connection_id' => $connection_id],
            ['%s', '%s', '%s', '%f', '%d', '%d', '%d'],
            ['%s']
        );

        // Trigger connection established hook
        do_action('aevov_meshcore_connection_established', $connection_id);
    }

    /**
     * Update connection quality metrics
     *
     * @param string $connection_id Connection ID
     * @param array $metrics Quality metrics
     * @return void
     */
    public function update_quality(string $connection_id, array $metrics): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';

        $update_data = [
            'last_activity' => current_time('mysql')
        ];

        if (isset($metrics['latency'])) {
            $update_data['latency'] = (int) $metrics['latency'];
        }
        if (isset($metrics['packet_loss'])) {
            $update_data['packet_loss'] = (float) $metrics['packet_loss'];
        }
        if (isset($metrics['bandwidth_up'])) {
            $update_data['bandwidth_up'] = (int) $metrics['bandwidth_up'];
        }
        if (isset($metrics['bandwidth_down'])) {
            $update_data['bandwidth_down'] = (int) $metrics['bandwidth_down'];
        }

        // Calculate quality score
        $quality_score = $this->calculate_quality_score($metrics);
        $update_data['quality_score'] = $quality_score;

        $wpdb->update(
            $table,
            $update_data,
            ['connection_id' => $connection_id],
            array_fill(0, count($update_data), '%s'),
            ['%s']
        );
    }

    /**
     * Calculate connection quality score
     *
     * @param array $metrics Connection metrics
     * @return float Quality score (0-1)
     */
    private function calculate_quality_score(array $metrics): float
    {
        $score = 1.0;

        // Penalize for high latency
        if (isset($metrics['latency'])) {
            $latency = (int) $metrics['latency'];
            if ($latency > 500) {
                $score *= 0.3;
            } elseif ($latency > 200) {
                $score *= 0.6;
            } elseif ($latency > 100) {
                $score *= 0.8;
            }
        }

        // Penalize for packet loss
        if (isset($metrics['packet_loss'])) {
            $packet_loss = (float) $metrics['packet_loss'];
            $score *= (1.0 - $packet_loss);
        }

        // Reward for high bandwidth
        if (isset($metrics['bandwidth_up'])) {
            $bandwidth = (int) $metrics['bandwidth_up'];
            if ($bandwidth > 10 * 1024 * 1024) { // 10 Mbps
                $score *= 1.2;
            } elseif ($bandwidth < 1024 * 1024) { // 1 Mbps
                $score *= 0.7;
            }
        }

        return min(1.0, max(0.0, $score));
    }

    /**
     * Record data transfer
     *
     * @param string $connection_id Connection ID
     * @param int $bytes_sent Bytes sent
     * @param int $bytes_received Bytes received
     * @return void
     */
    public function record_transfer(string $connection_id, int $bytes_sent, int $bytes_received): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET bytes_sent = bytes_sent + %d,
                 bytes_received = bytes_received + %d,
                 last_activity = %s
             WHERE connection_id = %s",
            $bytes_sent,
            $bytes_received,
            current_time('mysql'),
            $connection_id
        ));
    }

    /**
     * Close connection
     *
     * @param string $connection_id Connection ID
     * @param string $reason Reason for closing
     * @return void
     */
    public function close_connection(string $connection_id, string $reason = 'user_initiated'): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';

        // Get connection info before deleting
        $connection = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE connection_id = %s",
            $connection_id
        ), ARRAY_A);

        if ($connection) {
            // Trigger hook before closing
            do_action('aevov_meshcore_connection_closing', $connection_id, $reason);

            // Delete connection record
            $wpdb->delete($table, ['connection_id' => $connection_id], ['%s']);

            // Trigger hook after closing
            do_action('aevov_meshcore_connection_closed', $connection_id, $reason);
        }
    }

    /**
     * Disconnect all connections
     *
     * @return void
     */
    public function disconnect_all(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';
        $local_node_id = $this->node_manager->get_node_id();

        $connections = $wpdb->get_results($wpdb->prepare(
            "SELECT connection_id FROM {$table} WHERE local_node_id = %s",
            $local_node_id
        ), ARRAY_A);

        foreach ($connections as $conn) {
            $this->close_connection($conn['connection_id'], 'shutdown');
        }
    }

    /**
     * Check if connected to peer
     *
     * @param string $peer_node_id Peer node ID
     * @return bool
     */
    public function is_connected_to(string $peer_node_id): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';
        $local_node_id = $this->node_manager->get_node_id();

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE local_node_id = %s
             AND remote_node_id = %s
             AND status = 'connected'",
            $local_node_id,
            $peer_node_id
        ));

        return (int) $count > 0;
    }

    /**
     * Get connection count
     *
     * @return int
     */
    public function get_connection_count(): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';
        $local_node_id = $this->node_manager->get_node_id();

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE local_node_id = %s
             AND status = 'connected'",
            $local_node_id
        ));
    }

    /**
     * Get all active connections
     *
     * @return array
     */
    public function get_connections(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';
        $local_node_id = $this->node_manager->get_node_id();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE local_node_id = %s
             AND status = 'connected'
             ORDER BY quality_score DESC",
            $local_node_id
        ), ARRAY_A);
    }

    /**
     * Get connection by ID
     *
     * @param string $connection_id Connection ID
     * @return array|null
     */
    public function get_connection(string $connection_id): ?array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';

        $connection = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE connection_id = %s",
            $connection_id
        ), ARRAY_A);

        return $connection ?: null;
    }

    /**
     * Health check all connections
     *
     * @return void
     */
    public function health_check(): void
    {
        $connections = $this->get_connections();

        foreach ($connections as $connection) {
            $last_activity = strtotime($connection['last_activity']);
            $timeout = 5 * 60; // 5 minutes

            // Close stale connections
            if (time() - $last_activity > $timeout) {
                $this->close_connection($connection['connection_id'], 'timeout');
            }

            // Close low-quality connections
            if ((float) $connection['quality_score'] < 0.2) {
                $this->close_connection($connection['connection_id'], 'poor_quality');
            }
        }

        // Ensure minimum connections
        $this->ensure_min_connections();
    }

    /**
     * Ensure minimum number of connections
     *
     * @return void
     */
    private function ensure_min_connections(): void
    {
        $current_count = $this->get_connection_count();

        if ($current_count < $this->min_connections) {
            // Trigger peer discovery to find new peers
            do_action('aevov_meshcore_need_more_peers', $this->min_connections - $current_count);
        }
    }

    /**
     * Cleanup stale connections
     *
     * @return void
     */
    public function cleanup_stale(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';
        $timeout = date('Y-m-d H:i:s', strtotime('-10 minutes'));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE last_activity < %s",
            $timeout
        ));
    }

    /**
     * Generate connection ID
     *
     * @param string $node1 First node ID
     * @param string $node2 Second node ID
     * @return string
     */
    private function generate_connection_id(string $node1, string $node2): string
    {
        // Sort to ensure consistent ID regardless of direction
        $nodes = [$node1, $node2];
        sort($nodes);
        return hash('sha256', implode(':', $nodes));
    }

    /**
     * Verify peer identity
     *
     * @param string $peer_node_id Peer node ID
     * @param array $data Data with signature
     * @return bool
     */
    private function verify_peer(string $peer_node_id, array $data): bool
    {
        if (!isset($data['signature']) || !isset($data['public_key'])) {
            return false;
        }

        // Verify node ID matches public key
        $expected_node_id = hash('sha256', $data['public_key']);
        if ($expected_node_id !== $peer_node_id) {
            return false;
        }

        // Verify signature
        $payload = json_encode($data['offer'] ?? []);
        return $this->node_manager->verify_signature(
            $payload,
            $data['signature'],
            $data['public_key']
        );
    }

    /**
     * Get best connection for routing
     *
     * @param string|null $exclude_node_id Node to exclude
     * @return array|null
     */
    public function get_best_connection(?string $exclude_node_id = null): ?array
    {
        $connections = $this->get_connections();

        if ($exclude_node_id) {
            $connections = array_filter($connections, function ($conn) use ($exclude_node_id) {
                return $conn['remote_node_id'] !== $exclude_node_id;
            });
        }

        if (empty($connections)) {
            return null;
        }

        // Sort by quality score
        usort($connections, function ($a, $b) {
            return $b['quality_score'] <=> $a['quality_score'];
        });

        return $connections[0];
    }
}
