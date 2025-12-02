<?php
/**
 * REST API
 *
 * Provides REST API endpoints for mesh network operations.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\API;

use Aevov\Meshcore\Core\NodeManager;
use Aevov\Meshcore\P2P\ConnectionManager;
use Aevov\Meshcore\Discovery\DHTService;
use Aevov\Meshcore\Routing\MeshRouter;
use Aevov\Meshcore\Relay\RelayManager;

/**
 * REST API Class
 */
class RestAPI
{
    /**
     * API namespace
     *
     * @var string
     */
    private string $namespace = 'aevov-meshcore/v1';

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
     * DHT service
     *
     * @var DHTService
     */
    private DHTService $dht_service;

    /**
     * Mesh router
     *
     * @var MeshRouter
     */
    private MeshRouter $mesh_router;

    /**
     * Relay manager
     *
     * @var RelayManager
     */
    private RelayManager $relay_manager;

    /**
     * Constructor
     *
     * @param NodeManager $node_manager Node manager
     * @param ConnectionManager $connection_manager Connection manager
     * @param DHTService $dht_service DHT service
     * @param MeshRouter $mesh_router Mesh router
     * @param RelayManager $relay_manager Relay manager
     */
    public function __construct(
        NodeManager $node_manager,
        ConnectionManager $connection_manager,
        DHTService $dht_service,
        MeshRouter $mesh_router,
        RelayManager $relay_manager
    ) {
        $this->node_manager = $node_manager;
        $this->connection_manager = $connection_manager;
        $this->dht_service = $dht_service;
        $this->mesh_router = $mesh_router;
        $this->relay_manager = $relay_manager;

        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes
     *
     * @return void
     */
    public function register_routes(): void
    {
        // Node endpoints
        register_rest_route($this->namespace, '/node/info', [
            'methods' => 'GET',
            'callback' => [$this, 'get_node_info'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route($this->namespace, '/node/announce', [
            'methods' => 'POST',
            'callback' => [$this, 'announce_node'],
            'permission_callback' => '__return_true'
        ]);

        // Connection endpoints
        register_rest_route($this->namespace, '/connections', [
            'methods' => 'GET',
            'callback' => [$this, 'get_connections'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/connect', [
            'methods' => 'POST',
            'callback' => [$this, 'connect_to_peer'],
            'permission_callback' => '__return_true',
            'args' => [
                'peer_id' => ['required' => true],
                'peer_info' => ['required' => true]
            ]
        ]);

        register_rest_route($this->namespace, '/disconnect/(?P<connection_id>[a-zA-Z0-9]+)', [
            'methods' => 'POST',
            'callback' => [$this, 'disconnect'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // DHT endpoints
        register_rest_route($this->namespace, '/dht/put', [
            'methods' => 'POST',
            'callback' => [$this, 'dht_put'],
            'permission_callback' => '__return_true',
            'args' => [
                'key' => ['required' => true],
                'value' => ['required' => true]
            ]
        ]);

        register_rest_route($this->namespace, '/dht/get', [
            'methods' => 'GET',
            'callback' => [$this, 'dht_get'],
            'permission_callback' => '__return_true',
            'args' => [
                'key' => ['required' => true]
            ]
        ]);

        // Routing endpoints
        register_rest_route($this->namespace, '/route/find', [
            'methods' => 'POST',
            'callback' => [$this, 'find_route'],
            'permission_callback' => '__return_true',
            'args' => [
                'destination' => ['required' => true]
            ]
        ]);

        register_rest_route($this->namespace, '/route/table', [
            'methods' => 'GET',
            'callback' => [$this, 'get_routing_table'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Peer discovery
        register_rest_route($this->namespace, '/peers', [
            'methods' => 'GET',
            'callback' => [$this, 'get_peers'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route($this->namespace, '/peers/discover', [
            'methods' => 'POST',
            'callback' => [$this, 'discover_peers'],
            'permission_callback' => '__return_true'
        ]);

        // Network stats
        register_rest_route($this->namespace, '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/network/topology', [
            'methods' => 'GET',
            'callback' => [$this, 'get_topology'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        // Signaling endpoints for WebRTC
        register_rest_route($this->namespace, '/signal/offer', [
            'methods' => 'POST',
            'callback' => [$this, 'signal_offer'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route($this->namespace, '/signal/answer', [
            'methods' => 'POST',
            'callback' => [$this, 'signal_answer'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route($this->namespace, '/signal/ice', [
            'methods' => 'POST',
            'callback' => [$this, 'signal_ice'],
            'permission_callback' => '__return_true'
        ]);

        // Relay endpoints
        register_rest_route($this->namespace, '/relay/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_relay_stats'],
            'permission_callback' => [$this, 'check_permission']
        ]);

        register_rest_route($this->namespace, '/tokens/balance', [
            'methods' => 'GET',
            'callback' => [$this, 'get_token_balance'],
            'permission_callback' => [$this, 'check_permission']
        ]);
    }

    /**
     * Check permission
     *
     * @return bool
     */
    public function check_permission(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Get node information
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_node_info(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'node' => $this->node_manager->get_node_info()
        ]);
    }

    /**
     * Announce node to network
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function announce_node(\WP_REST_Request $request): \WP_REST_Response
    {
        $this->dht_service->announce_node();

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Node announced to network'
        ]);
    }

    /**
     * Get connections
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_connections(\WP_REST_Request $request): \WP_REST_Response
    {
        $connections = $this->connection_manager->get_connections();

        return new \WP_REST_Response([
            'success' => true,
            'connections' => $connections,
            'count' => count($connections)
        ]);
    }

    /**
     * Connect to peer
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function connect_to_peer(\WP_REST_Request $request): \WP_REST_Response
    {
        $peer_id = $request->get_param('peer_id');
        $peer_info = $request->get_param('peer_info');

        try {
            $connection = $this->connection_manager->connect_to_peer($peer_id, $peer_info);

            return new \WP_REST_Response([
                'success' => true,
                'connection' => $connection
            ]);
        } catch (\Exception $e) {
            return new \WP_REST_Response([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Disconnect from peer
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function disconnect(\WP_REST_Request $request): \WP_REST_Response
    {
        $connection_id = $request->get_param('connection_id');

        $this->connection_manager->close_connection($connection_id);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Connection closed'
        ]);
    }

    /**
     * DHT put
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function dht_put(\WP_REST_Request $request): \WP_REST_Response
    {
        $key = $request->get_param('key');
        $value = $request->get_param('value');
        $ttl = $request->get_param('ttl') ?? 3600;

        $success = $this->dht_service->put($key, $value, $ttl);

        return new \WP_REST_Response([
            'success' => $success
        ]);
    }

    /**
     * DHT get
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function dht_get(\WP_REST_Request $request): \WP_REST_Response
    {
        $key = $request->get_param('key');
        $value = $this->dht_service->get($key);

        return new \WP_REST_Response([
            'success' => $value !== null,
            'value' => $value
        ]);
    }

    /**
     * Find route
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function find_route(\WP_REST_Request $request): \WP_REST_Response
    {
        $destination = $request->get_param('destination');
        $route = $this->mesh_router->find_route($destination);

        return new \WP_REST_Response([
            'success' => $route !== null,
            'route' => $route
        ]);
    }

    /**
     * Get routing table
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_routing_table(\WP_REST_Request $request): \WP_REST_Response
    {
        $routes = $this->mesh_router->get_routing_table();

        return new \WP_REST_Response([
            'success' => true,
            'routes' => $routes,
            'count' => count($routes)
        ]);
    }

    /**
     * Get peers
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_peers(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $limit = $request->get_param('limit') ?? 10;
        $table = $wpdb->prefix . 'meshcore_nodes';

        $peers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = 'active'
             ORDER BY last_seen DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);

        return new \WP_REST_Response([
            'success' => true,
            'peers' => $peers
        ]);
    }

    /**
     * Discover peers
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function discover_peers(\WP_REST_Request $request): \WP_REST_Response
    {
        // Trigger peer discovery
        do_action('aevov_meshcore_discover_peers');

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Peer discovery initiated'
        ]);
    }

    /**
     * Get network statistics
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_stats(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'stats' => [
                'node' => $this->node_manager->get_node_info(),
                'connections' => [
                    'count' => $this->connection_manager->get_connection_count(),
                    'connections' => $this->connection_manager->get_connections()
                ],
                'routing' => $this->mesh_router->get_stats(),
                'dht' => $this->dht_service->get_stats(),
                'relay' => $this->relay_manager->get_stats()
            ]
        ]);
    }

    /**
     * Get network topology
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_topology(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        // Get all nodes
        $nodes_table = $wpdb->prefix . 'meshcore_nodes';
        $nodes = $wpdb->get_results(
            "SELECT * FROM {$nodes_table} WHERE status = 'active'",
            ARRAY_A
        );

        // Get all connections
        $conn_table = $wpdb->prefix . 'meshcore_connections';
        $connections = $wpdb->get_results(
            "SELECT * FROM {$conn_table} WHERE status = 'connected'",
            ARRAY_A
        );

        return new \WP_REST_Response([
            'success' => true,
            'topology' => [
                'nodes' => $nodes,
                'connections' => $connections
            ]
        ]);
    }

    /**
     * Signal WebRTC offer
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function signal_offer(\WP_REST_Request $request): \WP_REST_Response
    {
        // Handle WebRTC signaling
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Offer received'
        ]);
    }

    /**
     * Signal WebRTC answer
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function signal_answer(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Answer received'
        ]);
    }

    /**
     * Signal ICE candidate
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function signal_ice(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'ICE candidate received'
        ]);
    }

    /**
     * Get relay statistics
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_relay_stats(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => true,
            'stats' => $this->relay_manager->get_stats()
        ]);
    }

    /**
     * Get token balance
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function get_token_balance(\WP_REST_Request $request): \WP_REST_Response
    {
        $stats = $this->relay_manager->get_stats();

        return new \WP_REST_Response([
            'success' => true,
            'balance' => $stats['tokens_balance'],
            'earned' => $stats['tokens_earned'],
            'spent' => $stats['tokens_spent']
        ]);
    }
}
