<?php
/**
 * Peer Discovery
 *
 * Discovers and manages peers in the mesh network using multiple strategies:
 * - DHT-based discovery
 * - Bootstrap nodes
 * - Peer exchange (PEX)
 * - Local network broadcast
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Discovery;

use Aevov\Meshcore\Core\NodeManager;

/**
 * Peer Discovery Class
 */
class PeerDiscovery
{
    /**
     * Node manager
     *
     * @var NodeManager
     */
    private NodeManager $node_manager;

    /**
     * DHT service
     *
     * @var DHTService
     */
    private DHTService $dht_service;

    /**
     * Constructor
     *
     * @param NodeManager $node_manager Node manager
     * @param DHTService $dht_service DHT service
     */
    public function __construct(NodeManager $node_manager, DHTService $dht_service)
    {
        $this->node_manager = $node_manager;
        $this->dht_service = $dht_service;
    }

    /**
     * Discover new peers
     *
     * @param int $count Number of peers to discover
     * @return array Discovered peers
     */
    public function discover_peers(int $count = 10): array
    {
        $discovered_peers = [];

        // Strategy 1: Bootstrap nodes
        $bootstrap_peers = $this->discover_from_bootstrap($count);
        $discovered_peers = array_merge($discovered_peers, $bootstrap_peers);

        if (count($discovered_peers) >= $count) {
            return array_slice($discovered_peers, 0, $count);
        }

        // Strategy 2: DHT lookup for random IDs
        $dht_peers = $this->discover_from_dht($count - count($discovered_peers));
        $discovered_peers = array_merge($discovered_peers, $dht_peers);

        if (count($discovered_peers) >= $count) {
            return array_slice($discovered_peers, 0, $count);
        }

        // Strategy 3: Peer exchange from known nodes
        $pex_peers = $this->discover_from_peer_exchange($count - count($discovered_peers));
        $discovered_peers = array_merge($discovered_peers, $pex_peers);

        // Strategy 4: Local network broadcast (mDNS/SSDP)
        if (get_option('aevov_meshcore_enable_local_discovery', true)) {
            $local_peers = $this->discover_local_network($count - count($discovered_peers));
            $discovered_peers = array_merge($discovered_peers, $local_peers);
        }

        return array_slice(array_unique($discovered_peers, SORT_REGULAR), 0, $count);
    }

    /**
     * Discover peers from bootstrap nodes
     *
     * @param int $count Number of peers
     * @return array
     */
    private function discover_from_bootstrap(int $count): array
    {
        $bootstrap_urls = get_option('aevov_meshcore_bootstrap_urls', []);
        $peers = [];

        foreach ($bootstrap_urls as $bootstrap_url) {
            $response = wp_remote_get("{$bootstrap_url}/api/peers?limit={$count}", [
                'timeout' => 5
            ]);

            if (is_wp_error($response)) {
                continue;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['peers'])) {
                $peers = array_merge($peers, $body['peers']);
            }

            if (count($peers) >= $count) {
                break;
            }
        }

        return array_slice($peers, 0, $count);
    }

    /**
     * Discover peers from DHT
     *
     * @param int $count Number of peers
     * @return array
     */
    private function discover_from_dht(int $count): array
    {
        $peers = [];

        // Generate random node IDs and find closest nodes
        for ($i = 0; $i < min($count, 5); $i++) {
            $random_id = bin2hex(random_bytes(32));
            $closest_nodes = $this->dht_service->find_closest_nodes($random_id, 5);

            foreach ($closest_nodes as $node) {
                if ($node['node_id'] !== $this->node_manager->get_node_id()) {
                    $peers[] = $node;
                }
            }

            if (count($peers) >= $count) {
                break;
            }
        }

        return array_slice($peers, 0, $count);
    }

    /**
     * Discover peers through peer exchange
     *
     * @param int $count Number of peers
     * @return array
     */
    private function discover_from_peer_exchange(int $count): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';
        $local_node_id = $this->node_manager->get_node_id();

        // Get connected peers
        $connected_peers = $wpdb->get_results($wpdb->prepare(
            "SELECT remote_node_id FROM {$table}
             WHERE local_node_id = %s
             AND status = 'connected'
             ORDER BY RAND()
             LIMIT 5",
            $local_node_id
        ), ARRAY_A);

        $peers = [];

        // Ask each connected peer for their peers
        foreach ($connected_peers as $peer) {
            $peer_node = $this->get_node_info($peer['remote_node_id']);
            if (!$peer_node) {
                continue;
            }

            $peer_peers = $this->request_peers_from_node($peer_node, $count);
            $peers = array_merge($peers, $peer_peers);

            if (count($peers) >= $count) {
                break;
            }
        }

        return array_slice($peers, 0, $count);
    }

    /**
     * Discover peers on local network
     *
     * @param int $count Number of peers
     * @return array
     */
    private function discover_local_network(int $count): array
    {
        // This would implement mDNS/SSDP discovery
        // For now, return empty array - can be implemented with system calls
        return [];
    }

    /**
     * Request peers from a node
     *
     * @param array $node Node info
     * @param int $count Number of peers to request
     * @return array
     */
    private function request_peers_from_node(array $node, int $count): array
    {
        $network_info = json_decode($node['network_info'], true);
        $endpoint = $network_info['api_endpoint'] ?? null;

        if (!$endpoint) {
            return [];
        }

        $response = wp_remote_get("{$endpoint}/peers?limit={$count}", [
            'timeout' => 5
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['peers'] ?? [];
    }

    /**
     * Get node info from database
     *
     * @param string $node_id Node ID
     * @return array|null
     */
    private function get_node_info(string $node_id): ?array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_nodes';

        $node = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE node_id = %s",
            $node_id
        ), ARRAY_A);

        return $node ?: null;
    }

    /**
     * Register discovered peer
     *
     * @param array $peer_info Peer information
     * @return bool Success
     */
    public function register_peer(array $peer_info): bool
    {
        global $wpdb;

        if (!isset($peer_info['node_id']) || !isset($peer_info['public_key'])) {
            return false;
        }

        // Verify node ID matches public key
        $expected_node_id = hash('sha256', $peer_info['public_key']);
        if ($expected_node_id !== $peer_info['node_id']) {
            return false;
        }

        $table = $wpdb->prefix . 'meshcore_nodes';

        $wpdb->replace(
            $table,
            [
                'node_id' => $peer_info['node_id'],
                'peer_id' => $peer_info['node_id'],
                'public_key' => $peer_info['public_key'],
                'capabilities' => wp_json_encode($peer_info['capabilities'] ?? []),
                'network_info' => wp_json_encode($peer_info['network_info'] ?? []),
                'last_seen' => current_time('mysql'),
                'status' => 'discovered',
                'reputation_score' => $peer_info['reputation_score'] ?? 100
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        do_action('aevov_meshcore_peer_discovered', $peer_info);

        return true;
    }

    /**
     * Get candidate peers for connection
     *
     * @param int $count Number of candidates
     * @return array
     */
    public function get_connection_candidates(int $count = 10): array
    {
        global $wpdb;

        $table_nodes = $wpdb->prefix . 'meshcore_nodes';
        $table_connections = $wpdb->prefix . 'meshcore_connections';
        $local_node_id = $this->node_manager->get_node_id();

        // Get nodes we're not already connected to
        $candidates = $wpdb->get_results($wpdb->prepare(
            "SELECT n.* FROM {$table_nodes} n
             LEFT JOIN {$table_connections} c ON (
                 c.remote_node_id = n.node_id
                 AND c.local_node_id = %s
                 AND c.status = 'connected'
             )
             WHERE n.node_id != %s
             AND n.status = 'active'
             AND n.last_seen >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
             AND c.connection_id IS NULL
             ORDER BY n.reputation_score DESC, n.last_seen DESC
             LIMIT %d",
            $local_node_id,
            $local_node_id,
            $count
        ), ARRAY_A);

        return $candidates;
    }
}
