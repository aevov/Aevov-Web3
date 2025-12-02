<?php
/**
 * Mesh Network
 *
 * Manages the overall mesh network topology, neighbor relationships,
 * and network-wide operations.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Core;

/**
 * Mesh Network Class
 */
class MeshNetwork
{
    /**
     * Node manager instance
     *
     * @var NodeManager
     */
    private NodeManager $node_manager;

    /**
     * Network topology cache
     *
     * @var array|null
     */
    private ?array $topology_cache = null;

    /**
     * Constructor
     *
     * @param NodeManager $node_manager Node manager
     */
    public function __construct(NodeManager $node_manager)
    {
        $this->node_manager = $node_manager;
    }

    /**
     * Get all active nodes in the mesh
     *
     * @return array
     */
    public function get_active_nodes(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_nodes';
        $timeout = date('Y-m-d H:i:s', strtotime('-5 minutes'));

        $nodes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = 'active'
             AND last_seen >= %s
             ORDER BY last_seen DESC",
            $timeout
        ), ARRAY_A);

        return array_map(function ($node) {
            $node['capabilities'] = json_decode($node['capabilities'], true);
            $node['network_info'] = json_decode($node['network_info'], true);
            return $node;
        }, $nodes);
    }

    /**
     * Get direct neighbors (nodes with active connections)
     *
     * @return array
     */
    public function get_neighbors(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';
        $local_node_id = $this->node_manager->get_node_id();

        $connections = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE local_node_id = %s
             AND status = 'connected'
             ORDER BY quality_score DESC",
            $local_node_id
        ), ARRAY_A);

        return $connections;
    }

    /**
     * Get network topology
     *
     * @param bool $use_cache Whether to use cached topology
     * @return array
     */
    public function get_topology(bool $use_cache = true): array
    {
        if ($use_cache && $this->topology_cache !== null) {
            return $this->topology_cache;
        }

        $nodes = $this->get_active_nodes();
        $connections = $this->get_all_connections();

        // Build adjacency list
        $adjacency = [];
        foreach ($nodes as $node) {
            $adjacency[$node['node_id']] = [];
        }

        foreach ($connections as $conn) {
            if (!isset($adjacency[$conn['local_node_id']])) {
                $adjacency[$conn['local_node_id']] = [];
            }
            $adjacency[$conn['local_node_id']][] = $conn['remote_node_id'];
        }

        $topology = [
            'nodes' => $nodes,
            'edges' => $connections,
            'adjacency' => $adjacency,
            'statistics' => $this->calculate_network_stats($nodes, $connections)
        ];

        $this->topology_cache = $topology;

        return $topology;
    }

    /**
     * Get all connections in the network
     *
     * @return array
     */
    private function get_all_connections(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_connections';

        return $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE status = 'connected'",
            ARRAY_A
        );
    }

    /**
     * Calculate network statistics
     *
     * @param array $nodes All nodes
     * @param array $connections All connections
     * @return array
     */
    private function calculate_network_stats(array $nodes, array $connections): array
    {
        $total_nodes = count($nodes);
        $total_connections = count($connections);

        // Calculate average degree
        $degrees = [];
        foreach ($connections as $conn) {
            if (!isset($degrees[$conn['local_node_id']])) {
                $degrees[$conn['local_node_id']] = 0;
            }
            $degrees[$conn['local_node_id']]++;
        }

        $avg_degree = $total_nodes > 0 ? array_sum($degrees) / $total_nodes : 0;

        // Calculate network diameter (max shortest path)
        $diameter = $this->calculate_diameter($nodes, $connections);

        // Calculate clustering coefficient
        $clustering = $this->calculate_clustering_coefficient($nodes, $connections);

        return [
            'total_nodes' => $total_nodes,
            'total_connections' => $total_connections,
            'average_degree' => $avg_degree,
            'diameter' => $diameter,
            'clustering_coefficient' => $clustering,
            'density' => $total_nodes > 1 ? (2 * $total_connections) / ($total_nodes * ($total_nodes - 1)) : 0
        ];
    }

    /**
     * Calculate network diameter using BFS
     *
     * @param array $nodes All nodes
     * @param array $connections All connections
     * @return int
     */
    private function calculate_diameter(array $nodes, array $connections): int
    {
        if (count($nodes) === 0) {
            return 0;
        }

        // Build adjacency list
        $adj = [];
        foreach ($nodes as $node) {
            $adj[$node['node_id']] = [];
        }
        foreach ($connections as $conn) {
            $adj[$conn['local_node_id']][] = $conn['remote_node_id'];
        }

        $max_distance = 0;

        // Run BFS from each node
        foreach ($nodes as $start_node) {
            $distances = $this->bfs_distances($start_node['node_id'], $adj);
            $max_dist = max(array_values($distances));
            if ($max_dist > $max_distance && $max_dist !== PHP_INT_MAX) {
                $max_distance = $max_dist;
            }
        }

        return $max_distance;
    }

    /**
     * BFS to calculate distances from a source node
     *
     * @param string $source Source node ID
     * @param array $adj Adjacency list
     * @return array Distances to all nodes
     */
    private function bfs_distances(string $source, array $adj): array
    {
        $distances = [];
        foreach ($adj as $node_id => $neighbors) {
            $distances[$node_id] = PHP_INT_MAX;
        }
        $distances[$source] = 0;

        $queue = [$source];
        $visited = [$source => true];

        while (!empty($queue)) {
            $current = array_shift($queue);

            foreach ($adj[$current] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $distances[$neighbor] = $distances[$current] + 1;
                    $queue[] = $neighbor;
                }
            }
        }

        return $distances;
    }

    /**
     * Calculate clustering coefficient
     *
     * @param array $nodes All nodes
     * @param array $connections All connections
     * @return float
     */
    private function calculate_clustering_coefficient(array $nodes, array $connections): float
    {
        if (count($nodes) < 3) {
            return 0.0;
        }

        // Build adjacency list
        $adj = [];
        foreach ($nodes as $node) {
            $adj[$node['node_id']] = [];
        }
        foreach ($connections as $conn) {
            $adj[$conn['local_node_id']][] = $conn['remote_node_id'];
        }

        $total_coefficient = 0;
        $node_count = 0;

        foreach ($nodes as $node) {
            $node_id = $node['node_id'];
            $neighbors = $adj[$node_id];
            $k = count($neighbors);

            if ($k < 2) {
                continue;
            }

            // Count edges between neighbors
            $edges_between_neighbors = 0;
            for ($i = 0; $i < $k; $i++) {
                for ($j = $i + 1; $j < $k; $j++) {
                    if (in_array($neighbors[$j], $adj[$neighbors[$i]], true)) {
                        $edges_between_neighbors++;
                    }
                }
            }

            // Clustering coefficient for this node
            $max_edges = ($k * ($k - 1)) / 2;
            $coefficient = $max_edges > 0 ? $edges_between_neighbors / $max_edges : 0;

            $total_coefficient += $coefficient;
            $node_count++;
        }

        return $node_count > 0 ? $total_coefficient / $node_count : 0;
    }

    /**
     * Find best path to destination
     *
     * @param string $destination_id Destination node ID
     * @return array|null Path as array of node IDs
     */
    public function find_path_to(string $destination_id): ?array
    {
        $topology = $this->get_topology();
        $adj = $topology['adjacency'];
        $source = $this->node_manager->get_node_id();

        // BFS to find shortest path
        $parent = [];
        $queue = [$source];
        $visited = [$source => true];
        $parent[$source] = null;

        while (!empty($queue)) {
            $current = array_shift($queue);

            if ($current === $destination_id) {
                // Reconstruct path
                $path = [];
                while ($current !== null) {
                    array_unshift($path, $current);
                    $current = $parent[$current];
                }
                return $path;
            }

            if (!isset($adj[$current])) {
                continue;
            }

            foreach ($adj[$current] as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $visited[$neighbor] = true;
                    $parent[$neighbor] = $current;
                    $queue[] = $neighbor;
                }
            }
        }

        return null; // No path found
    }

    /**
     * Check if network is partitioned
     *
     * @return bool
     */
    public function is_partitioned(): bool
    {
        $topology = $this->get_topology();
        $nodes = $topology['nodes'];
        $adj = $topology['adjacency'];

        if (count($nodes) === 0) {
            return false;
        }

        // Run DFS from first node
        $first_node = $nodes[0]['node_id'];
        $visited = [];
        $this->dfs($first_node, $adj, $visited);

        // If all nodes visited, network is connected
        return count($visited) < count($nodes);
    }

    /**
     * DFS traversal
     *
     * @param string $node Current node
     * @param array $adj Adjacency list
     * @param array &$visited Visited nodes
     * @return void
     */
    private function dfs(string $node, array $adj, array &$visited): void
    {
        $visited[$node] = true;

        if (!isset($adj[$node])) {
            return;
        }

        foreach ($adj[$node] as $neighbor) {
            if (!isset($visited[$neighbor])) {
                $this->dfs($neighbor, $adj, $visited);
            }
        }
    }

    /**
     * Get network health score (0-100)
     *
     * @return float
     */
    public function get_health_score(): float
    {
        $topology = $this->get_topology();
        $stats = $topology['statistics'];

        $score = 100;

        // Penalize if network is partitioned
        if ($this->is_partitioned()) {
            $score -= 50;
        }

        // Reward for good connectivity
        $avg_degree = $stats['average_degree'];
        if ($avg_degree < 2) {
            $score -= 20; // Poor connectivity
        } elseif ($avg_degree >= 4) {
            $score += 0; // Good connectivity
        }

        // Penalize for large diameter
        $diameter = $stats['diameter'];
        if ($diameter > 10) {
            $score -= 10;
        }

        // Reward for high clustering
        $clustering = $stats['clustering_coefficient'];
        $score += $clustering * 10;

        return max(0, min(100, $score));
    }

    /**
     * Clear topology cache
     *
     * @return void
     */
    public function clear_cache(): void
    {
        $this->topology_cache = null;
    }
}
