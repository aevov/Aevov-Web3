<?php
/**
 * DHT Service
 *
 * Implements a Kademlia-based Distributed Hash Table for decentralized
 * peer discovery and data storage across the mesh network.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Discovery;

use Aevov\Meshcore\Core\NodeManager;

/**
 * DHT Service Class
 *
 * Implements a simplified Kademlia DHT with:
 * - 160-bit key space (SHA-1)
 * - k-buckets for peer organization
 * - Iterative lookups
 * - Data replication
 */
class DHTService
{
    /**
     * Node manager
     *
     * @var NodeManager
     */
    private NodeManager $node_manager;

    /**
     * K-bucket size (max peers per bucket)
     *
     * @var int
     */
    private int $k_bucket_size = 20;

    /**
     * Alpha (parallel lookup factor)
     *
     * @var int
     */
    private int $alpha = 3;

    /**
     * Replication factor
     *
     * @var int
     */
    private int $replication_factor = 3;

    /**
     * Constructor
     *
     * @param NodeManager $node_manager Node manager
     */
    public function __construct(NodeManager $node_manager)
    {
        $this->node_manager = $node_manager;
        $this->k_bucket_size = (int) get_option('aevov_meshcore_dht_k_bucket_size', 20);
        $this->alpha = (int) get_option('aevov_meshcore_dht_alpha', 3);
        $this->replication_factor = (int) get_option('aevov_meshcore_dht_replication', 3);
    }

    /**
     * Store key-value pair in DHT
     *
     * @param string $key Key to store
     * @param mixed $value Value to store
     * @param int $ttl Time to live in seconds
     * @return bool Success
     */
    public function put(string $key, $value, int $ttl = 3600): bool
    {
        $key_hash = $this->hash_key($key);

        // Find closest nodes to store the data
        $closest_nodes = $this->find_closest_nodes($key_hash, $this->replication_factor);

        // Store locally if we're one of the closest
        $local_node_id = $this->node_manager->get_node_id();
        if ($this->should_store_locally($key_hash, $closest_nodes, $local_node_id)) {
            $this->store_locally($key_hash, $value, $ttl);
        }

        // Replicate to closest nodes
        $success_count = 0;
        foreach ($closest_nodes as $node) {
            if ($node['node_id'] === $local_node_id) {
                continue; // Skip self
            }

            if ($this->store_remotely($node, $key_hash, $value, $ttl)) {
                $success_count++;
            }
        }

        // Success if stored on at least one node
        return $success_count > 0 || $this->should_store_locally($key_hash, $closest_nodes, $local_node_id);
    }

    /**
     * Get value from DHT
     *
     * @param string $key Key to retrieve
     * @return mixed|null Value or null if not found
     */
    public function get(string $key)
    {
        $key_hash = $this->hash_key($key);

        // Check local storage first
        $local_value = $this->get_locally($key_hash);
        if ($local_value !== null) {
            return $local_value;
        }

        // Query closest nodes
        $closest_nodes = $this->find_closest_nodes($key_hash, $this->alpha);

        foreach ($closest_nodes as $node) {
            $value = $this->get_remotely($node, $key_hash);
            if ($value !== null) {
                // Cache locally
                $this->store_locally($key_hash, $value, 3600);
                return $value;
            }
        }

        return null;
    }

    /**
     * Announce this node to the DHT
     *
     * @return void
     */
    public function announce_node(): void
    {
        $node_info = $this->node_manager->get_node_info();
        $node_id = $this->node_manager->get_node_id();

        // Store node info under its node ID
        $this->put("node:{$node_id}", $node_info, 600); // 10 minutes TTL

        // Announce to bootstrap nodes
        $bootstrap_nodes = $this->get_bootstrap_nodes();
        foreach ($bootstrap_nodes as $bootstrap_node) {
            $this->ping_node($bootstrap_node);
        }
    }

    /**
     * Find node by ID
     *
     * @param string $node_id Node ID to find
     * @return array|null Node information
     */
    public function find_node(string $node_id): ?array
    {
        // Check if we know this node
        $node_info = $this->get("node:{$node_id}");
        if ($node_info) {
            return $node_info;
        }

        // Perform iterative lookup
        $closest_nodes = $this->find_closest_nodes($node_id, $this->k_bucket_size);

        // Query each node for the target
        foreach ($closest_nodes as $node) {
            $result = $this->query_node($node, 'find_node', ['target' => $node_id]);
            if ($result && isset($result['node'])) {
                return $result['node'];
            }
        }

        return null;
    }

    /**
     * Find closest nodes to a key
     *
     * @param string $key_hash Key hash
     * @param int $count Number of nodes to return
     * @return array Closest nodes
     */
    public function find_closest_nodes(string $key_hash, int $count = 20): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_nodes';
        $local_node_id = $this->node_manager->get_node_id();

        // Get all active nodes
        $nodes = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE status = 'active'
             AND last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
            ARRAY_A
        );

        // Calculate XOR distance to each node
        $distances = [];
        foreach ($nodes as $node) {
            $node_id = $node['node_id'];
            $distance = $this->xor_distance($key_hash, $node_id);
            $distances[$node_id] = [
                'distance' => $distance,
                'node' => $node
            ];
        }

        // Sort by distance
        uasort($distances, function ($a, $b) {
            return gmp_cmp(gmp_init($a['distance'], 16), gmp_init($b['distance'], 16));
        });

        // Return top K nodes
        $result = array_slice(array_column($distances, 'node'), 0, $count);

        return $result;
    }

    /**
     * Calculate XOR distance between two IDs
     *
     * @param string $id1 First ID (hex)
     * @param string $id2 Second ID (hex)
     * @return string Distance (hex)
     */
    private function xor_distance(string $id1, string $id2): string
    {
        // Ensure both are same length
        $max_len = max(strlen($id1), strlen($id2));
        $id1 = str_pad($id1, $max_len, '0', STR_PAD_LEFT);
        $id2 = str_pad($id2, $max_len, '0', STR_PAD_LEFT);

        // XOR the hex strings
        $result = '';
        for ($i = 0; $i < $max_len; $i++) {
            $result .= dechex(hexdec($id1[$i]) ^ hexdec($id2[$i]));
        }

        return $result;
    }

    /**
     * Hash key to get key hash
     *
     * @param string $key Key
     * @return string Key hash
     */
    private function hash_key(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Check if we should store data locally
     *
     * @param string $key_hash Key hash
     * @param array $closest_nodes Closest nodes to the key
     * @param string $local_node_id Local node ID
     * @return bool
     */
    private function should_store_locally(string $key_hash, array $closest_nodes, string $local_node_id): bool
    {
        // Store if we're in the top K closest nodes
        $top_k = array_slice($closest_nodes, 0, $this->replication_factor);

        foreach ($top_k as $node) {
            if ($node['node_id'] === $local_node_id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Store data locally
     *
     * @param string $key_hash Key hash
     * @param mixed $value Value
     * @param int $ttl Time to live
     * @return void
     */
    private function store_locally(string $key_hash, $value, int $ttl): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_dht';
        $expires_at = date('Y-m-d H:i:s', time() + $ttl);

        $wpdb->replace(
            $table,
            [
                'key_hash' => $key_hash,
                'value_data' => wp_json_encode($value),
                'node_id' => $this->node_manager->get_node_id(),
                'ttl' => $ttl,
                'expires_at' => $expires_at
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Get data from local storage
     *
     * @param string $key_hash Key hash
     * @return mixed|null Value or null
     */
    private function get_locally(string $key_hash)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_dht';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT value_data FROM {$table}
             WHERE key_hash = %s
             AND expires_at > NOW()",
            $key_hash
        ));

        if ($result) {
            return json_decode($result->value_data, true);
        }

        return null;
    }

    /**
     * Store data on remote node
     *
     * @param array $node Remote node info
     * @param string $key_hash Key hash
     * @param mixed $value Value
     * @param int $ttl Time to live
     * @return bool Success
     */
    private function store_remotely(array $node, string $key_hash, $value, int $ttl): bool
    {
        // This would send a PUT request to the remote node's API
        // For now, we'll use WordPress HTTP API

        $node_info = json_decode($node['network_info'], true);
        $endpoint = $node_info['api_endpoint'] ?? null;

        if (!$endpoint) {
            return false;
        }

        $response = wp_remote_post("{$endpoint}/dht/put", [
            'timeout' => 5,
            'body' => [
                'key_hash' => $key_hash,
                'value' => wp_json_encode($value),
                'ttl' => $ttl,
                'signature' => $this->node_manager->sign_data($key_hash . wp_json_encode($value))
            ]
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Get data from remote node
     *
     * @param array $node Remote node info
     * @param string $key_hash Key hash
     * @return mixed|null Value or null
     */
    private function get_remotely(array $node, string $key_hash)
    {
        $node_info = json_decode($node['network_info'], true);
        $endpoint = $node_info['api_endpoint'] ?? null;

        if (!$endpoint) {
            return null;
        }

        $response = wp_remote_get("{$endpoint}/dht/get?key_hash={$key_hash}", [
            'timeout' => 5
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['value'] ?? null;
    }

    /**
     * Query a remote node
     *
     * @param array $node Node info
     * @param string $operation Operation name
     * @param array $params Parameters
     * @return array|null Response or null
     */
    private function query_node(array $node, string $operation, array $params = []): ?array
    {
        $node_info = json_decode($node['network_info'], true);
        $endpoint = $node_info['api_endpoint'] ?? null;

        if (!$endpoint) {
            return null;
        }

        $response = wp_remote_post("{$endpoint}/dht/{$operation}", [
            'timeout' => 5,
            'body' => $params
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Ping a node
     *
     * @param array $node Node info
     * @return bool Success
     */
    private function ping_node(array $node): bool
    {
        $result = $this->query_node($node, 'ping', [
            'node_id' => $this->node_manager->get_node_id(),
            'timestamp' => time()
        ]);

        return $result !== null;
    }

    /**
     * Get bootstrap nodes
     *
     * @return array
     */
    private function get_bootstrap_nodes(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_nodes';

        // Get nodes marked as bootstrap
        $nodes = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE status = 'active'
             AND JSON_EXTRACT(capabilities, '$.\"services.bootstrap\"') = true
             LIMIT 10",
            ARRAY_A
        );

        // Add hardcoded bootstrap nodes from config
        $config_nodes = get_option('aevov_meshcore_bootstrap_nodes', []);

        return array_merge($nodes, $config_nodes);
    }

    /**
     * Cleanup expired DHT entries
     *
     * @return void
     */
    public function cleanup_expired(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_dht';

        $wpdb->query("DELETE FROM {$table} WHERE expires_at < NOW()");
    }

    /**
     * Get DHT statistics
     *
     * @return array
     */
    public function get_stats(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_dht';

        $total_entries = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $total_size = $wpdb->get_var("SELECT SUM(LENGTH(value_data)) FROM {$table}");

        return [
            'total_entries' => (int) $total_entries,
            'total_size_bytes' => (int) $total_size,
            'k_bucket_size' => $this->k_bucket_size,
            'alpha' => $this->alpha,
            'replication_factor' => $this->replication_factor
        ];
    }
}
