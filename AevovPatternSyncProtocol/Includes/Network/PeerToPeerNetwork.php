<?php
/**
 * Peer-to-Peer Network Layer
 *
 * Implements P2P communication with DHT-based node discovery,
 * message routing, connection management, and NAT traversal simulation.
 *
 * @package AevovPatternSyncProtocol
 * @subpackage Network
 * @since 1.0.0
 */

namespace APS\Network;

use APS\Core\Logger;
use APS\DB\NetworkCache;

class PeerToPeerNetwork {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Network cache for peer storage
     *
     * @var NetworkCache
     */
    private $networkCache;

    /**
     * Local node information
     *
     * @var array
     */
    private $localNode;

    /**
     * Connected peers
     *
     * @var array
     */
    private $peers;

    /**
     * DHT (Distributed Hash Table) storage
     *
     * @var array
     */
    private $dht;

    /**
     * Routing table for message routing
     *
     * @var array
     */
    private $routingTable;

    /**
     * Connection pool
     *
     * @var array
     */
    private $connections;

    /**
     * Maximum number of connections
     *
     * @var int
     */
    private $maxConnections;

    /**
     * DHT bucket size (k-bucket)
     *
     * @var int
     */
    private $bucketSize;

    /**
     * Network ID/namespace
     *
     * @var string
     */
    private $networkId;

    /**
     * Message handlers
     *
     * @var array
     */
    private $messageHandlers;

    /**
     * Constructor
     *
     * @param array $config Network configuration
     */
    public function __construct($config = []) {
        $this->logger = Logger::get_instance();
        $this->networkCache = new NetworkCache();

        $this->maxConnections = $config['max_connections'] ?? 50;
        $this->bucketSize = $config['bucket_size'] ?? 20;
        $this->networkId = $config['network_id'] ?? 'aps_network';

        $this->peers = [];
        $this->dht = [];
        $this->routingTable = [];
        $this->connections = [];
        $this->messageHandlers = [];

        // Initialize local node
        $this->initializeLocalNode($config);

        // Initialize DHT
        $this->initializeDHT();

        $this->logger->log('info', 'P2P Network initialized', [
            'node_id' => $this->localNode['id'],
            'max_connections' => $this->maxConnections
        ]);
    }

    /**
     * Initialize local node
     *
     * @param array $config Configuration
     * @return void
     */
    private function initializeLocalNode($config) {
        $this->localNode = [
            'id' => $config['node_id'] ?? $this->generateNodeId(),
            'address' => $config['address'] ?? $this->getLocalAddress(),
            'port' => $config['port'] ?? 8080,
            'public_key' => $config['public_key'] ?? $this->generatePublicKey(),
            'capabilities' => $config['capabilities'] ?? ['sync', 'consensus', 'storage'],
            'reputation' => 100,
            'uptime' => 0,
            'started_at' => time()
        ];
    }

    /**
     * Initialize DHT (Distributed Hash Table)
     *
     * @return void
     */
    private function initializeDHT() {
        // Initialize k-buckets for Kademlia-style DHT
        // DHT uses XOR distance metric
        for ($i = 0; $i < 160; $i++) { // 160 bits for SHA-1 based IDs
            $this->dht[$i] = [];
        }

        $this->logger->log('debug', 'DHT initialized', ['buckets' => count($this->dht)]);
    }

    /**
     * Join the P2P network
     *
     * @param array $bootstrap_nodes Bootstrap nodes to connect to
     * @return bool Success status
     */
    public function joinNetwork($bootstrap_nodes = []) {
        $this->logger->log('info', 'Joining P2P network', [
            'bootstrap_nodes' => count($bootstrap_nodes)
        ]);

        if (empty($bootstrap_nodes)) {
            // Bootstrap mode - become first node
            $this->logger->log('info', 'Bootstrap mode - first node in network');
            $this->storeInDHT($this->localNode['id'], $this->localNode);
            return true;
        }

        // Connect to bootstrap nodes
        foreach ($bootstrap_nodes as $bootstrap_node) {
            if ($this->connectToPeer($bootstrap_node)) {
                $this->logger->log('info', 'Connected to bootstrap node', [
                    'node' => $bootstrap_node['id'] ?? 'unknown'
                ]);
            }
        }

        // Perform node discovery
        $this->discoverNodes();

        // Store self in DHT
        $this->storeInDHT($this->localNode['id'], $this->localNode);

        return true;
    }

    /**
     * Discover nodes in the network
     *
     * @return array Discovered nodes
     */
    public function discoverNodes() {
        $this->logger->log('debug', 'Starting node discovery');

        $discovered_nodes = [];

        // FIND_NODE RPC to connected peers
        foreach ($this->peers as $peer) {
            $response = $this->sendMessage($peer['id'], [
                'type' => 'FIND_NODE',
                'target' => $this->localNode['id'],
                'sender' => $this->localNode['id']
            ]);

            if ($response && isset($response['nodes'])) {
                foreach ($response['nodes'] as $node) {
                    if ($node['id'] !== $this->localNode['id']) {
                        $discovered_nodes[] = $node;
                        $this->addPeer($node);
                    }
                }
            }
        }

        $this->logger->log('info', 'Node discovery completed', [
            'discovered' => count($discovered_nodes)
        ]);

        return $discovered_nodes;
    }

    /**
     * Connect to a peer
     *
     * @param array $peer_info Peer information
     * @return bool Success status
     */
    public function connectToPeer($peer_info) {
        // Check connection limit
        if (count($this->connections) >= $this->maxConnections) {
            $this->logger->log('warning', 'Max connections reached', [
                'max' => $this->maxConnections
            ]);
            return false;
        }

        $peer_id = $peer_info['id'] ?? null;

        if (!$peer_id) {
            return false;
        }

        // Check if already connected
        if (isset($this->connections[$peer_id])) {
            return true;
        }

        // Simulate NAT traversal
        $nat_result = $this->performNATTraversal($peer_info);

        if (!$nat_result['success']) {
            $this->logger->log('warning', 'NAT traversal failed', [
                'peer' => $peer_id
            ]);
            return false;
        }

        // Create connection
        $connection = [
            'peer_id' => $peer_id,
            'address' => $peer_info['address'],
            'port' => $peer_info['port'],
            'established_at' => time(),
            'last_activity' => time(),
            'bytes_sent' => 0,
            'bytes_received' => 0,
            'status' => 'connected',
            'nat_type' => $nat_result['nat_type']
        ];

        $this->connections[$peer_id] = $connection;
        $this->addPeer($peer_info);

        // Update routing table
        $this->updateRoutingTable($peer_info);

        $this->logger->log('info', 'Connected to peer', [
            'peer_id' => $peer_id,
            'address' => $peer_info['address']
        ]);

        return true;
    }

    /**
     * Disconnect from a peer
     *
     * @param string $peer_id Peer ID
     * @return bool Success status
     */
    public function disconnectFromPeer($peer_id) {
        if (!isset($this->connections[$peer_id])) {
            return false;
        }

        unset($this->connections[$peer_id]);
        $this->removePeer($peer_id);

        $this->logger->log('info', 'Disconnected from peer', ['peer_id' => $peer_id]);

        return true;
    }

    /**
     * Send message to a peer
     *
     * @param string $peer_id Target peer ID
     * @param array $message Message to send
     * @return array|null Response
     */
    public function sendMessage($peer_id, $message) {
        // Check if directly connected
        if (isset($this->connections[$peer_id])) {
            return $this->sendDirectMessage($peer_id, $message);
        }

        // Route message through network
        return $this->routeMessage($peer_id, $message);
    }

    /**
     * Send direct message to connected peer
     *
     * @param string $peer_id Peer ID
     * @param array $message Message
     * @return array|null Response
     */
    private function sendDirectMessage($peer_id, $message) {
        $connection = $this->connections[$peer_id];

        // Add message metadata
        $message['sender'] = $this->localNode['id'];
        $message['timestamp'] = time();
        $message['message_id'] = $this->generateMessageId();

        // Simulate message transmission
        $encoded_message = json_encode($message);
        $message_size = strlen($encoded_message);

        // Update connection stats
        $this->connections[$peer_id]['bytes_sent'] += $message_size;
        $this->connections[$peer_id]['last_activity'] = time();

        // In production, this would use actual network transmission
        // For simulation, we'll call message handler if registered
        $response = $this->handleIncomingMessage($message, $peer_id);

        $this->logger->log('debug', 'Message sent', [
            'peer_id' => $peer_id,
            'type' => $message['type'],
            'size' => $message_size
        ]);

        return $response;
    }

    /**
     * Route message through the network
     *
     * @param string $target_id Target node ID
     * @param array $message Message
     * @return array|null Response
     */
    private function routeMessage($target_id, $message) {
        // Find route using DHT
        $route = $this->findRoute($this->localNode['id'], $target_id);

        if (empty($route)) {
            $this->logger->log('warning', 'No route found to target', [
                'target' => $target_id
            ]);
            return null;
        }

        // Send through first hop
        $next_hop = $route[0];
        $message['route'] = $route;
        $message['hops'] = 0;
        $message['max_hops'] = 10;

        return $this->sendDirectMessage($next_hop, $message);
    }

    /**
     * Find route to target node
     *
     * @param string $source_id Source node ID
     * @param string $target_id Target node ID
     * @return array Route (array of node IDs)
     */
    private function findRoute($source_id, $target_id) {
        // Use Kademlia-style routing
        $closest_nodes = $this->findClosestNodes($target_id, 3);

        if (empty($closest_nodes)) {
            return [];
        }

        // Return first closest node as next hop
        return [$closest_nodes[0]['id']];
    }

    /**
     * Find closest nodes to target ID using DHT
     *
     * @param string $target_id Target ID
     * @param int $count Number of nodes to return
     * @return array Closest nodes
     */
    private function findClosestNodes($target_id, $count = 20) {
        $all_nodes = [];

        // Collect all known nodes from DHT
        foreach ($this->dht as $bucket) {
            $all_nodes = array_merge($all_nodes, $bucket);
        }

        // Add connected peers
        foreach ($this->peers as $peer) {
            $all_nodes[] = $peer;
        }

        // Calculate XOR distances
        $nodes_with_distance = [];
        foreach ($all_nodes as $node) {
            $distance = $this->xorDistance($target_id, $node['id']);
            $nodes_with_distance[] = [
                'node' => $node,
                'distance' => $distance
            ];
        }

        // Sort by distance
        usort($nodes_with_distance, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        // Return closest nodes
        $closest = array_slice($nodes_with_distance, 0, $count);
        return array_map(function($item) {
            return $item['node'];
        }, $closest);
    }

    /**
     * Calculate XOR distance between two node IDs
     *
     * @param string $id1 First ID
     * @param string $id2 Second ID
     * @return int Distance
     */
    private function xorDistance($id1, $id2) {
        // Convert IDs to integers and XOR
        $hash1 = hexdec(substr(hash('sha256', $id1), 0, 16));
        $hash2 = hexdec(substr(hash('sha256', $id2), 0, 16));

        return $hash1 ^ $hash2;
    }

    /**
     * Store key-value in DHT
     *
     * @param string $key Key to store
     * @param mixed $value Value to store
     * @return bool Success status
     */
    public function storeInDHT($key, $value) {
        $bucket_index = $this->getDHTBucketIndex($key);

        // Store in appropriate bucket
        $this->dht[$bucket_index][] = [
            'key' => $key,
            'value' => $value,
            'stored_at' => time(),
            'stored_by' => $this->localNode['id']
        ];

        // Maintain bucket size
        if (count($this->dht[$bucket_index]) > $this->bucketSize) {
            array_shift($this->dht[$bucket_index]); // Remove oldest
        }

        // Replicate to closest nodes
        $this->replicateDHTEntry($key, $value);

        $this->logger->log('debug', 'Stored in DHT', [
            'key' => substr($key, 0, 16),
            'bucket' => $bucket_index
        ]);

        return true;
    }

    /**
     * Retrieve value from DHT
     *
     * @param string $key Key to retrieve
     * @return mixed|null Value or null if not found
     */
    public function retrieveFromDHT($key) {
        $bucket_index = $this->getDHTBucketIndex($key);

        // Search in local bucket
        foreach ($this->dht[$bucket_index] as $entry) {
            if ($entry['key'] === $key) {
                return $entry['value'];
            }
        }

        // Query closest nodes
        $closest_nodes = $this->findClosestNodes($key, 3);

        foreach ($closest_nodes as $node) {
            $response = $this->sendMessage($node['id'], [
                'type' => 'GET_VALUE',
                'key' => $key
            ]);

            if ($response && isset($response['value'])) {
                // Cache locally
                $this->storeInDHT($key, $response['value']);
                return $response['value'];
            }
        }

        return null;
    }

    /**
     * Get DHT bucket index for key
     *
     * @param string $key Key
     * @return int Bucket index
     */
    private function getDHTBucketIndex($key) {
        // Use first byte of hash to determine bucket
        $hash = hash('sha256', $key);
        $first_byte = hexdec(substr($hash, 0, 2));

        return $first_byte % 160;
    }

    /**
     * Replicate DHT entry to closest nodes
     *
     * @param string $key Key
     * @param mixed $value Value
     * @return void
     */
    private function replicateDHTEntry($key, $value) {
        $closest_nodes = $this->findClosestNodes($key, 3);

        foreach ($closest_nodes as $node) {
            if ($node['id'] !== $this->localNode['id']) {
                $this->sendMessage($node['id'], [
                    'type' => 'STORE',
                    'key' => $key,
                    'value' => $value
                ]);
            }
        }
    }

    /**
     * Add peer to peer list
     *
     * @param array $peer_info Peer information
     * @return void
     */
    private function addPeer($peer_info) {
        $peer_id = $peer_info['id'];

        if (!isset($this->peers[$peer_id])) {
            $this->peers[$peer_id] = array_merge($peer_info, [
                'added_at' => time(),
                'last_seen' => time()
            ]);

            $this->logger->log('debug', 'Peer added', ['peer_id' => $peer_id]);
        }
    }

    /**
     * Remove peer from peer list
     *
     * @param string $peer_id Peer ID
     * @return void
     */
    private function removePeer($peer_id) {
        unset($this->peers[$peer_id]);
        $this->logger->log('debug', 'Peer removed', ['peer_id' => $peer_id]);
    }

    /**
     * Update routing table
     *
     * @param array $node_info Node information
     * @return void
     */
    private function updateRoutingTable($node_info) {
        $distance = $this->xorDistance($this->localNode['id'], $node_info['id']);

        $this->routingTable[$node_info['id']] = [
            'node' => $node_info,
            'distance' => $distance,
            'updated_at' => time()
        ];
    }

    /**
     * Perform NAT traversal simulation
     *
     * @param array $peer_info Peer information
     * @return array NAT traversal result
     */
    private function performNATTraversal($peer_info) {
        // Simulate different NAT types
        $nat_types = ['none', 'full_cone', 'restricted', 'port_restricted', 'symmetric'];
        $nat_type = $nat_types[array_rand($nat_types)];

        // Simulate success based on NAT type
        $success_rate = [
            'none' => 1.0,
            'full_cone' => 0.95,
            'restricted' => 0.8,
            'port_restricted' => 0.7,
            'symmetric' => 0.5
        ];

        $success = (mt_rand() / mt_getrandmax()) < $success_rate[$nat_type];

        if ($success) {
            $this->logger->log('debug', 'NAT traversal successful', [
                'nat_type' => $nat_type,
                'peer' => $peer_info['id'] ?? 'unknown'
            ]);
        }

        return [
            'success' => $success,
            'nat_type' => $nat_type,
            'method' => $nat_type === 'symmetric' ? 'relay' : 'direct'
        ];
    }

    /**
     * Handle incoming message
     *
     * @param array $message Message
     * @param string $sender_id Sender ID
     * @return array|null Response
     */
    private function handleIncomingMessage($message, $sender_id) {
        $type = $message['type'] ?? 'unknown';

        $this->logger->log('debug', 'Handling incoming message', [
            'type' => $type,
            'sender' => $sender_id
        ]);

        // Call registered handler
        if (isset($this->messageHandlers[$type])) {
            return call_user_func($this->messageHandlers[$type], $message, $sender_id);
        }

        // Default handlers
        switch ($type) {
            case 'FIND_NODE':
                return $this->handleFindNode($message);

            case 'STORE':
                return $this->handleStore($message);

            case 'GET_VALUE':
                return $this->handleGetValue($message);

            case 'PING':
                return ['type' => 'PONG', 'node' => $this->localNode];

            default:
                return null;
        }
    }

    /**
     * Handle FIND_NODE message
     *
     * @param array $message Message
     * @return array Response
     */
    private function handleFindNode($message) {
        $target = $message['target'] ?? null;

        if (!$target) {
            return ['error' => 'No target specified'];
        }

        $closest_nodes = $this->findClosestNodes($target, $this->bucketSize);

        return [
            'type' => 'FOUND_NODES',
            'nodes' => $closest_nodes
        ];
    }

    /**
     * Handle STORE message
     *
     * @param array $message Message
     * @return array Response
     */
    private function handleStore($message) {
        $key = $message['key'] ?? null;
        $value = $message['value'] ?? null;

        if (!$key || !$value) {
            return ['error' => 'Invalid store request'];
        }

        $this->storeInDHT($key, $value);

        return ['type' => 'STORED', 'success' => true];
    }

    /**
     * Handle GET_VALUE message
     *
     * @param array $message Message
     * @return array Response
     */
    private function handleGetValue($message) {
        $key = $message['key'] ?? null;

        if (!$key) {
            return ['error' => 'No key specified'];
        }

        $value = $this->retrieveFromDHT($key);

        if ($value !== null) {
            return ['type' => 'VALUE', 'value' => $value];
        }

        return ['type' => 'NOT_FOUND'];
    }

    /**
     * Register message handler
     *
     * @param string $message_type Message type
     * @param callable $handler Handler callback
     * @return void
     */
    public function registerMessageHandler($message_type, $handler) {
        $this->messageHandlers[$message_type] = $handler;
        $this->logger->log('debug', 'Message handler registered', ['type' => $message_type]);
    }

    /**
     * Generate node ID
     *
     * @return string Node ID
     */
    private function generateNodeId() {
        return hash('sha256', get_site_url() . time() . wp_rand());
    }

    /**
     * Generate message ID
     *
     * @return string Message ID
     */
    private function generateMessageId() {
        return uniqid('msg_', true);
    }

    /**
     * Get local address
     *
     * @return string Address
     */
    private function getLocalAddress() {
        return get_site_url();
    }

    /**
     * Generate public key (placeholder)
     *
     * @return string Public key
     */
    private function generatePublicKey() {
        return hash('sha256', $this->localNode['id'] ?? wp_rand());
    }

    /**
     * Get network statistics
     *
     * @return array Statistics
     */
    public function getStatistics() {
        return [
            'node_id' => $this->localNode['id'],
            'peers_count' => count($this->peers),
            'connections_count' => count($this->connections),
            'dht_entries' => array_sum(array_map('count', $this->dht)),
            'routing_table_size' => count($this->routingTable),
            'uptime' => time() - $this->localNode['started_at']
        ];
    }

    /**
     * Get connected peers
     *
     * @return array Peers
     */
    public function getPeers() {
        return $this->peers;
    }

    /**
     * Get active connections
     *
     * @return array Connections
     */
    public function getConnections() {
        return $this->connections;
    }
}
