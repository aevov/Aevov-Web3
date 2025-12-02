<?php
/**
 * AevIP Coordinator - Distributed execution across AevIP network
 *
 * Coordinates tile execution across AevIP nodes:
 * - Node discovery and health monitoring
 * - Workload distribution and load balancing
 * - Secure packet communication
 * - Result aggregation from distributed nodes
 * - Fault tolerance and failover
 *
 * @package AevovRuntime
 * @since 1.0.0
 */

namespace AevovRuntime;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AevIPCoordinator
 */
class AevIPCoordinator {

    /**
     * Packet type for runtime task
     */
    const PACKET_TYPE_RUNTIME_TASK = 'aevrt_task';

    /**
     * Packet type for runtime result
     */
    const PACKET_TYPE_RUNTIME_RESULT = 'aevrt_result';

    /**
     * Maximum task timeout in seconds
     */
    const MAX_TASK_TIMEOUT = 60;

    /**
     * Node health check interval in seconds
     */
    const HEALTH_CHECK_INTERVAL = 30;

    /**
     * Active nodes cache
     *
     * @var array
     */
    private $active_nodes = [];

    /**
     * Pending tasks
     *
     * @var array
     */
    private $pending_tasks = [];

    /**
     * AevIP instance
     *
     * @var mixed
     */
    private $aevip;

    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize_aevip();
        $this->refresh_active_nodes();
    }

    /**
     * Initialize AevIP connection
     */
    private function initialize_aevip() {
        // Check if Physics Engine with AevIP is available
        if (class_exists('AevovPhysicsEngine\\AevIP')) {
            $this->aevip = new \AevovPhysicsEngine\AevIP();
        } else {
            $this->aevip = null;
        }
    }

    /**
     * Execute tiles on specific node
     *
     * @param string $node_id Node ID
     * @param array $tiles Tiles to execute
     * @return array Execution results
     */
    public function execute_tiles_on_node($node_id, $tiles) {
        if (!$this->aevip) {
            throw new \Exception('AevIP not available');
        }

        // Get node information
        $node = $this->get_node_info($node_id);

        if (!$node) {
            throw new \Exception("Node not found: {$node_id}");
        }

        // Create task packet
        $task_id = uniqid('aevrt_task_', true);

        $packet = $this->create_task_packet($task_id, $tiles, [
            'timeout' => self::MAX_TASK_TIMEOUT,
            'priority' => max(array_column($tiles, 'priority', 0))
        ]);

        // Send packet to node
        $send_result = $this->send_packet_to_node($node, $packet);

        if (is_wp_error($send_result)) {
            // Mark node as failed and retry on another node
            $this->mark_node_failed($node_id);
            return $this->retry_on_another_node($tiles);
        }

        // Wait for result
        $result = $this->wait_for_result($task_id, self::MAX_TASK_TIMEOUT);

        if (!$result) {
            // Timeout - mark node as slow
            $this->mark_node_slow($node_id);
            return $this->create_timeout_result($tiles);
        }

        return $result['tile_results'] ?? [];
    }

    /**
     * Distribute tasks across multiple nodes
     *
     * @param array $tiles Tiles to distribute
     * @param array $options Distribution options
     * @return array Execution results
     */
    public function distribute_tasks($tiles, $options = []) {
        $active_nodes = $this->get_active_nodes();

        if (empty($active_nodes)) {
            throw new \Exception('No active AevIP nodes available');
        }

        $strategy = $options['strategy'] ?? 'round_robin';

        // Partition tiles across nodes
        $partitions = $this->partition_tiles($tiles, $active_nodes, $strategy);

        // Execute on each node
        $results = [];
        $futures = [];

        foreach ($partitions as $node_id => $node_tiles) {
            // Send task to node (async)
            $task_id = uniqid('aevrt_dist_', true);

            $packet = $this->create_task_packet($task_id, $node_tiles, $options);

            $node = $this->get_node_info($node_id);
            $this->send_packet_to_node($node, $packet);

            $futures[$task_id] = [
                'node_id' => $node_id,
                'tiles' => $node_tiles,
                'sent_at' => microtime(true)
            ];
        }

        // Collect results
        $timeout = $options['timeout'] ?? self::MAX_TASK_TIMEOUT;

        foreach ($futures as $task_id => $future) {
            $elapsed = microtime(true) - $future['sent_at'];
            $remaining_timeout = max(1, $timeout - $elapsed);

            $result = $this->wait_for_result($task_id, $remaining_timeout);

            if ($result) {
                foreach ($result['tile_results'] as $tile_index => $tile_result) {
                    $results[$tile_index] = $tile_result;
                }
            } else {
                // Timeout - create error results
                foreach ($future['tiles'] as $tile_index => $tile) {
                    $results[$tile_index] = [
                        'success' => false,
                        'error' => 'Node execution timeout',
                        'tile_index' => $tile_index
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Partition tiles across nodes
     *
     * @param array $tiles Tiles
     * @param array $nodes Nodes
     * @param string $strategy Partitioning strategy
     * @return array Partitions by node_id
     */
    private function partition_tiles($tiles, $nodes, $strategy) {
        $partitions = [];

        switch ($strategy) {
            case 'round_robin':
                $node_index = 0;
                foreach ($tiles as $tile_index => $tile) {
                    $node_id = $nodes[$node_index % count($nodes)]['node_id'];

                    if (!isset($partitions[$node_id])) {
                        $partitions[$node_id] = [];
                    }

                    $partitions[$node_id][$tile_index] = $tile;
                    $node_index++;
                }
                break;

            case 'capability_based':
                foreach ($tiles as $tile_index => $tile) {
                    $best_node = $this->find_best_node_for_tile($tile, $nodes);

                    if (!isset($partitions[$best_node['node_id']])) {
                        $partitions[$best_node['node_id']] = [];
                    }

                    $partitions[$best_node['node_id']][$tile_index] = $tile;
                }
                break;

            case 'load_balanced':
                // Distribute based on current load
                $partitions = $this->partition_by_load($tiles, $nodes);
                break;

            default:
                // Default to round robin
                $partitions = $this->partition_tiles($tiles, $nodes, 'round_robin');
                break;
        }

        return $partitions;
    }

    /**
     * Find best node for tile
     *
     * @param array $tile Tile
     * @param array $nodes Available nodes
     * @return array Best node
     */
    private function find_best_node_for_tile($tile, $nodes) {
        $type = $tile['type'] ?? 'language';
        $best_node = $nodes[0];
        $best_score = -INF;

        foreach ($nodes as $node) {
            $capabilities = json_decode($node['capabilities'] ?? '{}', true);

            // Check if node supports this task type
            if (!isset($capabilities[$type]) || !$capabilities[$type]) {
                continue;
            }

            // Score based on performance and load
            $performance_score = $capabilities["{$type}_performance"] ?? 0.5;
            $load_factor = 1 - ($node['current_load'] ?? 0);

            $score = ($performance_score * 0.7) + ($load_factor * 0.3);

            if ($score > $best_score) {
                $best_score = $score;
                $best_node = $node;
            }
        }

        return $best_node;
    }

    /**
     * Partition by load
     *
     * @param array $tiles Tiles
     * @param array $nodes Nodes
     * @return array Partitions
     */
    private function partition_by_load($tiles, $nodes) {
        // Sort nodes by current load (ascending)
        usort($nodes, function($a, $b) {
            return ($a['current_load'] ?? 0) <=> ($b['current_load'] ?? 0);
        });

        $partitions = [];
        $node_loads = [];

        // Initialize loads
        foreach ($nodes as $node) {
            $node_loads[$node['node_id']] = $node['current_load'] ?? 0;
        }

        // Assign tiles to least loaded nodes
        foreach ($tiles as $tile_index => $tile) {
            // Find node with minimum load
            $min_load = INF;
            $min_node_id = null;

            foreach ($node_loads as $node_id => $load) {
                if ($load < $min_load) {
                    $min_load = $load;
                    $min_node_id = $node_id;
                }
            }

            if ($min_node_id) {
                if (!isset($partitions[$min_node_id])) {
                    $partitions[$min_node_id] = [];
                }

                $partitions[$min_node_id][$tile_index] = $tile;

                // Increment load (estimate)
                $node_loads[$min_node_id] += 0.1;
            }
        }

        return $partitions;
    }

    /**
     * Create task packet
     *
     * @param string $task_id Task ID
     * @param array $tiles Tiles
     * @param array $options Options
     * @return array Packet
     */
    private function create_task_packet($task_id, $tiles, $options) {
        return [
            'packet_type' => self::PACKET_TYPE_RUNTIME_TASK,
            'task_id' => $task_id,
            'tiles' => $tiles,
            'options' => $options,
            'timestamp' => time(),
            'sender' => $this->get_local_node_id()
        ];
    }

    /**
     * Send packet to node
     *
     * @param array $node Node info
     * @param array $packet Packet data
     * @return mixed Result or WP_Error
     */
    private function send_packet_to_node($node, $packet) {
        if (!$this->aevip) {
            return new \WP_Error('aevip_unavailable', 'AevIP not available');
        }

        // Use AevIP to send packet
        $response = wp_remote_post($node['address'] . '/aevip/receive', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-AevIP-Signature' => $this->sign_packet($packet)
            ],
            'body' => json_encode($packet),
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data;
    }

    /**
     * Sign packet
     *
     * @param array $packet Packet data
     * @return string Signature
     */
    private function sign_packet($packet) {
        $secret = get_option('aevip_secret_key', wp_salt('auth'));
        $data = json_encode($packet);
        return hash_hmac('sha256', $data, $secret);
    }

    /**
     * Wait for result
     *
     * @param string $task_id Task ID
     * @param int $timeout Timeout in seconds
     * @return array|null Result or null on timeout
     */
    private function wait_for_result($task_id, $timeout) {
        $start_time = microtime(true);

        while ((microtime(true) - $start_time) < $timeout) {
            // Check if result is available
            $result = $this->check_result($task_id);

            if ($result) {
                return $result;
            }

            // Sleep briefly before checking again
            usleep(100000); // 100ms
        }

        return null; // Timeout
    }

    /**
     * Check for task result
     *
     * @param string $task_id Task ID
     * @return array|null Result or null
     */
    private function check_result($task_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aevov_runtime_results';

        $result = $wpdb->get_row($wpdb->prepare("
            SELECT result_data
            FROM {$table_name}
            WHERE task_id = %s
        ", $task_id), ARRAY_A);

        if ($result) {
            return json_decode($result['result_data'], true);
        }

        return null;
    }

    /**
     * Store result (called by receiving node)
     *
     * @param string $task_id Task ID
     * @param array $result Result data
     */
    public function store_result($task_id, $result) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aevov_runtime_results';

        $wpdb->insert($table_name, [
            'task_id' => $task_id,
            'result_data' => json_encode($result),
            'created_at' => current_time('mysql')
        ]);
    }

    /**
     * Get active nodes
     *
     * @return array Active nodes
     */
    private function get_active_nodes() {
        // Check cache first
        if (!empty($this->active_nodes)) {
            $cache_age = time() - ($this->active_nodes['cached_at'] ?? 0);

            if ($cache_age < self::HEALTH_CHECK_INTERVAL) {
                return $this->active_nodes['nodes'] ?? [];
            }
        }

        // Refresh from database
        $this->refresh_active_nodes();

        return $this->active_nodes['nodes'] ?? [];
    }

    /**
     * Refresh active nodes from database
     */
    private function refresh_active_nodes() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aevov_physics_nodes';

        $nodes = $wpdb->get_results("
            SELECT node_id, address, status, capabilities, current_load
            FROM {$table_name}
            WHERE status = 'active'
            AND last_heartbeat > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ORDER BY current_load ASC
        ", ARRAY_A);

        $this->active_nodes = [
            'nodes' => $nodes ?: [],
            'cached_at' => time()
        ];
    }

    /**
     * Get node info
     *
     * @param string $node_id Node ID
     * @return array|null Node info or null
     */
    private function get_node_info($node_id) {
        $nodes = $this->get_active_nodes();

        foreach ($nodes as $node) {
            if ($node['node_id'] === $node_id) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Mark node as failed
     *
     * @param string $node_id Node ID
     */
    private function mark_node_failed($node_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aevov_physics_nodes';

        $wpdb->update(
            $table_name,
            ['status' => 'failed'],
            ['node_id' => $node_id]
        );

        // Refresh cache
        $this->refresh_active_nodes();
    }

    /**
     * Mark node as slow
     *
     * @param string $node_id Node ID
     */
    private function mark_node_slow($node_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'aevov_physics_nodes';

        $wpdb->query($wpdb->prepare("
            UPDATE {$table_name}
            SET current_load = current_load + 0.2
            WHERE node_id = %s
        ", $node_id));
    }

    /**
     * Retry on another node
     *
     * @param array $tiles Tiles to retry
     * @return array Results
     */
    private function retry_on_another_node($tiles) {
        $active_nodes = $this->get_active_nodes();

        if (empty($active_nodes)) {
            // No nodes available - return error results
            return $this->create_timeout_result($tiles);
        }

        // Try first available node
        $node = $active_nodes[0];

        return $this->execute_tiles_on_node($node['node_id'], $tiles);
    }

    /**
     * Create timeout result
     *
     * @param array $tiles Tiles
     * @return array Timeout results
     */
    private function create_timeout_result($tiles) {
        $results = [];

        foreach ($tiles as $tile_index => $tile) {
            $results[$tile_index] = [
                'success' => false,
                'error' => 'Execution timeout',
                'tile_index' => $tile_index
            ];
        }

        return $results;
    }

    /**
     * Get local node ID
     *
     * @return string Node ID
     */
    private function get_local_node_id() {
        return get_option('aevip_local_node_id', 'local_' . get_current_blog_id());
    }

    /**
     * Get coordinator statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        return [
            'active_nodes' => count($this->get_active_nodes()),
            'pending_tasks' => count($this->pending_tasks),
            'aevip_available' => $this->aevip !== null
        ];
    }
}
