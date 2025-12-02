<?php
/**
 * Mesh Router
 *
 * Implements multi-hop mesh routing using a hybrid approach:
 * - Reactive routing (AODV-inspired)
 * - Proactive routing for frequent destinations
 * - Quality-aware path selection
 * - Loop prevention
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Routing;

use Aevov\Meshcore\Core\NodeManager;
use Aevov\Meshcore\P2P\ConnectionManager;

/**
 * Mesh Router Class
 */
class MeshRouter
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
     * Route cache
     *
     * @var array
     */
    private array $route_cache = [];

    /**
     * Max hops for route discovery
     *
     * @var int
     */
    private int $max_hops = 10;

    /**
     * Route timeout (seconds)
     *
     * @var int
     */
    private int $route_timeout = 300;

    /**
     * Constructor
     *
     * @param NodeManager $node_manager Node manager
     * @param ConnectionManager $connection_manager Connection manager
     */
    public function __construct(NodeManager $node_manager, ConnectionManager $connection_manager)
    {
        $this->node_manager = $node_manager;
        $this->connection_manager = $connection_manager;
        $this->max_hops = (int) get_option('aevov_meshcore_max_hops', 10);
        $this->route_timeout = (int) get_option('aevov_meshcore_route_timeout', 300);
    }

    /**
     * Route packet to destination
     *
     * @param string $destination_id Destination node ID
     * @param array $packet Packet data
     * @param int $hop_count Current hop count
     * @return bool Success
     */
    public function route_packet(string $destination_id, array $packet, int $hop_count = 0): bool
    {
        $local_node_id = $this->node_manager->get_node_id();

        // Check if we're the destination
        if ($destination_id === $local_node_id) {
            $this->deliver_packet($packet);
            return true;
        }

        // Check hop limit
        if ($hop_count >= $this->max_hops) {
            error_log("Meshcore: Packet dropped - exceeded max hops");
            return false;
        }

        // Check if directly connected
        if ($this->connection_manager->is_connected_to($destination_id)) {
            return $this->send_direct($destination_id, $packet);
        }

        // Find route to destination
        $route = $this->find_route($destination_id);

        if (!$route) {
            // No route found - initiate route discovery
            $this->discover_route($destination_id);
            return false;
        }

        // Forward to next hop
        $next_hop = $route['next_hop_id'];

        $packet['hop_count'] = $hop_count + 1;
        $packet['route_path'] = $packet['route_path'] ?? [];
        $packet['route_path'][] = $local_node_id;

        return $this->send_direct($next_hop, $packet);
    }

    /**
     * Find route to destination
     *
     * @param string $destination_id Destination node ID
     * @return array|null Route info or null
     */
    public function find_route(string $destination_id): ?array
    {
        global $wpdb;

        // Check cache first
        if (isset($this->route_cache[$destination_id])) {
            $cached_route = $this->route_cache[$destination_id];
            if ($cached_route['expires_at'] > time()) {
                return $cached_route;
            }
        }

        // Query routing table
        $table = $wpdb->prefix . 'meshcore_routes';

        $route = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE destination_id = %s
             AND expires_at > NOW()
             ORDER BY path_quality DESC, hop_count ASC
             LIMIT 1",
            $destination_id
        ), ARRAY_A);

        if ($route) {
            // Cache the route
            $this->route_cache[$destination_id] = [
                'next_hop_id' => $route['next_hop_id'],
                'hop_count' => (int) $route['hop_count'],
                'path_quality' => (float) $route['path_quality'],
                'expires_at' => strtotime($route['expires_at'])
            ];

            return $this->route_cache[$destination_id];
        }

        return null;
    }

    /**
     * Update routing table
     *
     * @return void
     */
    public function update_routes(): void
    {
        // Get all direct neighbors
        $neighbors = $this->connection_manager->get_connections();

        foreach ($neighbors as $neighbor) {
            $this->add_route(
                $neighbor['remote_node_id'],
                $neighbor['remote_node_id'],
                1,
                (float) $neighbor['quality_score']
            );

            // Request routes from neighbor
            $this->request_routes_from($neighbor['remote_node_id']);
        }
    }

    /**
     * Add route to routing table
     *
     * @param string $destination_id Destination node ID
     * @param string $next_hop_id Next hop node ID
     * @param int $hop_count Hop count
     * @param float $path_quality Path quality (0-1)
     * @param array $path_nodes Optional path nodes
     * @return void
     */
    public function add_route(
        string $destination_id,
        string $next_hop_id,
        int $hop_count,
        float $path_quality,
        array $path_nodes = []
    ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_routes';
        $expires_at = date('Y-m-d H:i:s', time() + $this->route_timeout);

        // Calculate path cost (lower is better)
        $path_cost = $hop_count * (1.0 - $path_quality);

        $wpdb->replace(
            $table,
            [
                'destination_id' => $destination_id,
                'next_hop_id' => $next_hop_id,
                'hop_count' => $hop_count,
                'path_quality' => $path_quality,
                'path_cost' => $path_cost,
                'path_nodes' => wp_json_encode($path_nodes),
                'expires_at' => $expires_at
            ],
            ['%s', '%s', '%d', '%f', '%f', '%s', '%s']
        );

        // Update cache
        $this->route_cache[$destination_id] = [
            'next_hop_id' => $next_hop_id,
            'hop_count' => $hop_count,
            'path_quality' => $path_quality,
            'expires_at' => time() + $this->route_timeout
        ];

        do_action('aevov_meshcore_route_added', $destination_id, $next_hop_id, $hop_count);
    }

    /**
     * Remove route from routing table
     *
     * @param string $destination_id Destination node ID
     * @return void
     */
    public function remove_route(string $destination_id): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_routes';

        $wpdb->delete($table, ['destination_id' => $destination_id], ['%s']);

        unset($this->route_cache[$destination_id]);

        do_action('aevov_meshcore_route_removed', $destination_id);
    }

    /**
     * Discover route to destination
     *
     * @param string $destination_id Destination node ID
     * @return void
     */
    private function discover_route(string $destination_id): void
    {
        // Broadcast route request (RREQ) to neighbors
        $rreq = [
            'type' => 'RREQ',
            'destination_id' => $destination_id,
            'source_id' => $this->node_manager->get_node_id(),
            'request_id' => bin2hex(random_bytes(16)),
            'hop_count' => 0,
            'timestamp' => time()
        ];

        $neighbors = $this->connection_manager->get_connections();

        foreach ($neighbors as $neighbor) {
            $this->send_direct($neighbor['remote_node_id'], $rreq);
        }

        do_action('aevov_meshcore_route_discovery_started', $destination_id);
    }

    /**
     * Handle route request (RREQ)
     *
     * @param array $rreq Route request data
     * @param string $from_node_id Node that sent the request
     * @return void
     */
    public function handle_route_request(array $rreq, string $from_node_id): void
    {
        $local_node_id = $this->node_manager->get_node_id();
        $destination_id = $rreq['destination_id'];
        $source_id = $rreq['source_id'];

        // Check if we've seen this request before
        $seen_key = "meshcore_rreq_{$rreq['request_id']}";
        if (get_transient($seen_key)) {
            return; // Already processed
        }
        set_transient($seen_key, true, 60); // 1 minute

        // Add reverse route to source
        $this->add_route(
            $source_id,
            $from_node_id,
            $rreq['hop_count'] + 1,
            0.8 // Default quality
        );

        // Check if we're the destination
        if ($destination_id === $local_node_id) {
            // Send route reply (RREP)
            $this->send_route_reply($source_id, $from_node_id, $rreq['hop_count'] + 1);
            return;
        }

        // Check if we have a route to destination
        $route = $this->find_route($destination_id);
        if ($route) {
            // Send route reply with our route
            $this->send_route_reply($source_id, $from_node_id, $rreq['hop_count'] + $route['hop_count'] + 1);
            return;
        }

        // Forward RREQ to neighbors (except sender)
        $rreq['hop_count']++;

        if ($rreq['hop_count'] < $this->max_hops) {
            $neighbors = $this->connection_manager->get_connections();

            foreach ($neighbors as $neighbor) {
                if ($neighbor['remote_node_id'] === $from_node_id) {
                    continue; // Don't send back to sender
                }

                $this->send_direct($neighbor['remote_node_id'], $rreq);
            }
        }
    }

    /**
     * Send route reply
     *
     * @param string $destination_id Destination for the reply
     * @param string $next_hop_id Next hop to send through
     * @param int $hop_count Total hop count
     * @return void
     */
    private function send_route_reply(string $destination_id, string $next_hop_id, int $hop_count): void
    {
        $rrep = [
            'type' => 'RREP',
            'destination_id' => $destination_id,
            'source_id' => $this->node_manager->get_node_id(),
            'hop_count' => $hop_count,
            'timestamp' => time()
        ];

        $this->send_direct($next_hop_id, $rrep);
    }

    /**
     * Handle route reply (RREP)
     *
     * @param array $rrep Route reply data
     * @param string $from_node_id Node that sent the reply
     * @return void
     */
    public function handle_route_reply(array $rrep, string $from_node_id): void
    {
        $destination_id = $rrep['source_id']; // Reply source is our destination

        // Add route
        $this->add_route(
            $destination_id,
            $from_node_id,
            $rrep['hop_count'],
            0.9 // Good quality for fresh route
        );

        do_action('aevov_meshcore_route_discovered', $destination_id, $from_node_id);
    }

    /**
     * Request routes from neighbor
     *
     * @param string $neighbor_id Neighbor node ID
     * @return void
     */
    private function request_routes_from(string $neighbor_id): void
    {
        $request = [
            'type' => 'ROUTE_REQUEST',
            'source_id' => $this->node_manager->get_node_id(),
            'timestamp' => time()
        ];

        $this->send_direct($neighbor_id, $request);
    }

    /**
     * Handle route request from neighbor
     *
     * @param string $requester_id Requester node ID
     * @return void
     */
    public function handle_route_table_request(string $requester_id): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_routes';

        // Get our routing table
        $routes = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE expires_at > NOW()
             ORDER BY path_quality DESC
             LIMIT 100",
            ARRAY_A
        );

        // Send routing table
        $response = [
            'type' => 'ROUTE_TABLE',
            'source_id' => $this->node_manager->get_node_id(),
            'routes' => $routes,
            'timestamp' => time()
        ];

        $this->send_direct($requester_id, $response);
    }

    /**
     * Handle routing table from neighbor
     *
     * @param array $route_table Route table data
     * @param string $from_node_id Neighbor node ID
     * @return void
     */
    public function handle_route_table(array $route_table, string $from_node_id): void
    {
        $routes = $route_table['routes'] ?? [];

        foreach ($routes as $route) {
            // Add route through this neighbor
            $this->add_route(
                $route['destination_id'],
                $from_node_id,
                ((int) $route['hop_count']) + 1,
                (float) $route['path_quality'] * 0.9 // Slightly reduce quality for indirect route
            );
        }
    }

    /**
     * Send packet directly to connected peer
     *
     * @param string $peer_id Peer node ID
     * @param array $packet Packet data
     * @return bool Success
     */
    private function send_direct(string $peer_id, array $packet): bool
    {
        // This would send via WebRTC data channel
        // Trigger action for actual transmission
        do_action('aevov_meshcore_send_packet', $peer_id, $packet);

        return true;
    }

    /**
     * Deliver packet to local application
     *
     * @param array $packet Packet data
     * @return void
     */
    private function deliver_packet(array $packet): void
    {
        // Trigger action for local delivery
        do_action('aevov_meshcore_packet_received', $packet);
    }

    /**
     * Cleanup expired routes
     *
     * @return void
     */
    public function cleanup_expired_routes(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_routes';

        $wpdb->query("DELETE FROM {$table} WHERE expires_at < NOW()");

        // Clear route cache
        $this->route_cache = [];
    }

    /**
     * Get routing table
     *
     * @return array
     */
    public function get_routing_table(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_routes';

        return $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE expires_at > NOW()
             ORDER BY destination_id, path_cost ASC",
            ARRAY_A
        );
    }

    /**
     * Get routing statistics
     *
     * @return array
     */
    public function get_stats(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_routes';

        $total_routes = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE expires_at > NOW()");
        $avg_hop_count = $wpdb->get_var("SELECT AVG(hop_count) FROM {$table} WHERE expires_at > NOW()");
        $avg_quality = $wpdb->get_var("SELECT AVG(path_quality) FROM {$table} WHERE expires_at > NOW()");

        return [
            'total_routes' => (int) $total_routes,
            'average_hop_count' => round((float) $avg_hop_count, 2),
            'average_path_quality' => round((float) $avg_quality, 3),
            'max_hops' => $this->max_hops,
            'route_timeout' => $this->route_timeout
        ];
    }
}
