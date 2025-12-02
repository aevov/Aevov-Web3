<?php
/**
 * AevIP Integration for Distributed Security Scanning
 *
 * Integrates the Aevov Security Monitor with AevIP (Aevov Internet Protocol)
 * for distributed threat detection, workload distribution, and threat intelligence sharing.
 *
 * Features:
 * - Distributed security scanning across AevIP nodes
 * - Threat intelligence sharing
 * - Coordinated malware detection
 * - Workload distribution for large-scale scans
 * - Real-time security event propagation
 * - Consensus-based threat verification
 *
 * @package AevovSecurityMonitor
 * @since 1.0.0
 */

namespace AevovSecurityMonitor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AevIPIntegration
 */
class AevIPIntegration {

    /**
     * AevIP protocol version
     */
    const PROTOCOL_VERSION = '1.0';

    /**
     * Security packet types
     */
    const PACKET_THREAT_ALERT = 'threat_alert';
    const PACKET_SCAN_REQUEST = 'scan_request';
    const PACKET_SCAN_RESULT = 'scan_result';
    const PACKET_YARA_RULE_SYNC = 'yara_sync';
    const PACKET_NODE_STATUS = 'node_status';

    /**
     * Registered compute nodes
     *
     * @var array
     */
    private $compute_nodes = [];

    /**
     * Threat intelligence cache
     *
     * @var array
     */
    private $threat_cache = [];

    /**
     * Node registration endpoint
     *
     * @var string
     */
    private $registration_endpoint = '/aevov-physics/v1/distributed/node/register';

    /**
     * Initialize AevIP integration
     */
    public function init() {
        // Register REST API endpoints
        add_action('rest_api_init', [$this, 'register_aevip_endpoints']);

        // Load registered nodes
        $this->load_compute_nodes();

        // Start node discovery
        $this->discover_nodes();

        // Schedule threat intelligence sync
        if (!wp_next_scheduled('aevov_security_aevip_sync')) {
            wp_schedule_event(time(), 'hourly', 'aevov_security_aevip_sync');
        }

        add_action('aevov_security_aevip_sync', [$this, 'sync_threat_intelligence']);

        error_log('[AevIP Security] Integration initialized');
    }

    /**
     * Register AevIP-specific REST endpoints
     */
    public function register_aevip_endpoints() {
        // Node registration
        register_rest_route('aevov-security/v1', '/aevip/node/register', [
            'methods' => 'POST',
            'callback' => [$this, 'register_node'],
            'permission_callback' => [$this, 'verify_aevip_auth']
        ]);

        // Receive threat alert
        register_rest_route('aevov-security/v1', '/aevip/threat/receive', [
            'methods' => 'POST',
            'callback' => [$this, 'receive_threat_alert'],
            'permission_callback' => [$this, 'verify_aevip_auth']
        ]);

        // Receive scan request
        register_rest_route('aevov-security/v1', '/aevip/scan/request', [
            'methods' => 'POST',
            'callback' => [$this, 'receive_scan_request'],
            'permission_callback' => [$this, 'verify_aevip_auth']
        ]);

        // Send scan result
        register_rest_route('aevov-security/v1', '/aevip/scan/result', [
            'methods' => 'POST',
            'callback' => [$this, 'receive_scan_result'],
            'permission_callback' => [$this, 'verify_aevip_auth']
        ]);

        // YARA rule sync
        register_rest_route('aevov-security/v1', '/aevip/yara/sync', [
            'methods' => 'POST',
            'callback' => [$this, 'sync_yara_rules'],
            'permission_callback' => [$this, 'verify_aevip_auth']
        ]);

        // Node status heartbeat
        register_rest_route('aevov-security/v1', '/aevip/node/heartbeat', [
            'methods' => 'POST',
            'callback' => [$this, 'node_heartbeat'],
            'permission_callback' => [$this, 'verify_aevip_auth']
        ]);
    }

    /**
     * Verify AevIP authentication
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function verify_aevip_auth($request) {
        $auth_header = $request->get_header('X-AevIP-Auth');

        if (empty($auth_header)) {
            return false;
        }

        // Verify signature using AevIP protocol
        $signature = $request->get_header('X-AevIP-Signature');
        $timestamp = $request->get_header('X-AevIP-Timestamp');

        // Check timestamp (5-minute window)
        if (abs(time() - $timestamp) > 300) {
            return false;
        }

        // Verify HMAC signature
        $expected_signature = $this->generate_signature(
            $request->get_body(),
            $timestamp
        );

        return hash_equals($expected_signature, $signature);
    }

    /**
     * Generate AevIP packet signature
     *
     * @param string $data Packet data
     * @param int $timestamp Timestamp
     * @return string HMAC signature
     */
    private function generate_signature($data, $timestamp) {
        $secret = get_option('aevov_aevip_secret', wp_generate_password(64, true, true));
        return hash_hmac('sha256', $data . $timestamp, $secret);
    }

    /**
     * Share threat intelligence with AevIP network
     *
     * @param array $event Security event data
     */
    public function share_threat_intelligence($event) {
        if (empty($this->compute_nodes)) {
            return;
        }

        // Create AevIP packet
        $packet = $this->create_packet(self::PACKET_THREAT_ALERT, [
            'event_type' => $event['event_type'],
            'severity' => $event['severity'],
            'title' => $event['title'],
            'description' => $event['description'],
            'file_path' => $event['file_path'] ?? null,
            'signature' => $event['signature_match'] ?? null,
            'yara_rule' => $event['yara_rule'] ?? null,
            'mitre_technique' => $event['mitre_technique'] ?? null,
            'mitre_tactic' => $event['mitre_tactic'] ?? null,
            'timestamp' => time(),
            'node_id' => $this->get_node_id()
        ]);

        // Broadcast to all nodes
        foreach ($this->compute_nodes as $node) {
            $this->send_packet($node, $packet);
        }

        error_log(sprintf(
            '[AevIP Security] Shared threat: %s to %d nodes',
            $event['title'],
            count($this->compute_nodes)
        ));
    }

    /**
     * Distribute security scan across AevIP nodes
     *
     * @param array $files Files to scan
     * @param array $options Scan options
     * @return array Aggregated scan results
     */
    public function distribute_scan($files, $options = []) {
        if (empty($this->compute_nodes)) {
            error_log('[AevIP Security] No compute nodes available for distributed scan');
            return [];
        }

        $active_nodes = $this->get_active_nodes();

        if (empty($active_nodes)) {
            error_log('[AevIP Security] No active compute nodes');
            return [];
        }

        // Partition files across nodes
        $partitions = $this->partition_workload($files, count($active_nodes));

        // Send scan requests to each node
        $scan_id = uniqid('aevip_scan_', true);
        $results = [];

        foreach ($active_nodes as $i => $node) {
            $packet = $this->create_packet(self::PACKET_SCAN_REQUEST, [
                'scan_id' => $scan_id,
                'files' => $partitions[$i],
                'options' => $options,
                'timestamp' => time()
            ]);

            $response = $this->send_packet($node, $packet);

            if (!is_wp_error($response)) {
                $results[$node['node_id']] = [
                    'status' => 'pending',
                    'node' => $node,
                    'partition_size' => count($partitions[$i])
                ];
            }
        }

        error_log(sprintf(
            '[AevIP Security] Distributed scan %s across %d nodes (%d files)',
            $scan_id,
            count($active_nodes),
            count($files)
        ));

        // Poll for results (with timeout)
        return $this->collect_scan_results($scan_id, $results, 300); // 5 min timeout
    }

    /**
     * Partition workload across nodes
     *
     * @param array $items Items to partition
     * @param int $num_partitions Number of partitions
     * @return array Partitioned items
     */
    private function partition_workload($items, $num_partitions) {
        $partitions = array_fill(0, $num_partitions, []);
        $total = count($items);
        $per_partition = ceil($total / $num_partitions);

        foreach ($items as $i => $item) {
            $partition_index = floor($i / $per_partition);
            if ($partition_index >= $num_partitions) {
                $partition_index = $num_partitions - 1;
            }
            $partitions[$partition_index][] = $item;
        }

        return $partitions;
    }

    /**
     * Collect scan results from nodes
     *
     * @param string $scan_id Scan identifier
     * @param array $results Results array (passed by reference)
     * @param int $timeout Timeout in seconds
     * @return array Aggregated results
     */
    private function collect_scan_results($scan_id, &$results, $timeout) {
        $start_time = time();
        $completed = 0;
        $total = count($results);

        while ($completed < $total && (time() - $start_time) < $timeout) {
            foreach ($results as $node_id => &$result) {
                if ($result['status'] === 'completed') {
                    continue;
                }

                // Check for result
                $scan_result = get_transient("aevip_scan_result_{$scan_id}_{$node_id}");

                if ($scan_result !== false) {
                    $result['status'] = 'completed';
                    $result['result'] = $scan_result;
                    $completed++;
                }
            }

            if ($completed < $total) {
                sleep(1); // Wait 1 second before checking again
            }
        }

        // Aggregate results
        $aggregated = [
            'scan_id' => $scan_id,
            'nodes' => $total,
            'completed' => $completed,
            'threats_found' => 0,
            'files_scanned' => 0,
            'details' => []
        ];

        foreach ($results as $node_id => $result) {
            if ($result['status'] === 'completed') {
                $aggregated['threats_found'] += $result['result']['threats_found'] ?? 0;
                $aggregated['files_scanned'] += $result['result']['files_scanned'] ?? 0;
                $aggregated['details'][$node_id] = $result['result'];
            }
        }

        return $aggregated;
    }

    /**
     * Create AevIP packet
     *
     * @param string $type Packet type
     * @param array $data Packet data
     * @return array Packet
     */
    private function create_packet($type, $data) {
        return [
            'protocol' => 'aevip',
            'version' => self::PROTOCOL_VERSION,
            'type' => $type,
            'node_id' => $this->get_node_id(),
            'timestamp' => time(),
            'data' => $data,
            'checksum' => $this->calculate_checksum($data)
        ];
    }

    /**
     * Send packet to node
     *
     * @param array $node Node information
     * @param array $packet Packet data
     * @return array|WP_Error Response
     */
    private function send_packet($node, $packet) {
        $timestamp = time();
        $body = json_encode($packet);
        $signature = $this->generate_signature($body, $timestamp);

        $response = wp_remote_post($node['endpoint'], [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-AevIP-Auth' => 'enabled',
                'X-AevIP-Signature' => $signature,
                'X-AevIP-Timestamp' => $timestamp,
                'X-AevIP-Node-ID' => $this->get_node_id()
            ],
            'body' => $body
        ]);

        if (is_wp_error($response)) {
            error_log(sprintf(
                '[AevIP Security] Failed to send packet to node %s: %s',
                $node['node_id'],
                $response->get_error_message()
            ));
            return $response;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Calculate packet checksum
     *
     * @param mixed $data Data to checksum
     * @return string Checksum
     */
    private function calculate_checksum($data) {
        return hash('sha256', json_encode($data));
    }

    /**
     * Get current node ID
     *
     * @return string Node ID
     */
    private function get_node_id() {
        $node_id = get_option('aevov_aevip_node_id');

        if (empty($node_id)) {
            $node_id = wp_generate_uuid4();
            update_option('aevov_aevip_node_id', $node_id);
        }

        return $node_id;
    }

    /**
     * Load registered compute nodes
     */
    private function load_compute_nodes() {
        $this->compute_nodes = get_option('aevov_aevip_compute_nodes', []);
        error_log(sprintf('[AevIP Security] Loaded %d compute nodes', count($this->compute_nodes)));
    }

    /**
     * Discover AevIP nodes on network
     */
    private function discover_nodes() {
        // Query physics engine for registered nodes
        $physics_endpoint = rest_url('aevov-physics/v1/distributed/nodes');

        $response = wp_remote_get($physics_endpoint);

        if (!is_wp_error($response)) {
            $nodes = json_decode(wp_remote_retrieve_body($response), true);

            if (!empty($nodes)) {
                foreach ($nodes as $node) {
                    $this->register_node_internal($node);
                }
            }
        }
    }

    /**
     * Get active nodes (heartbeat within last 5 minutes)
     *
     * @return array Active nodes
     */
    private function get_active_nodes() {
        $cutoff = time() - 300; // 5 minutes

        return array_filter($this->compute_nodes, function($node) use ($cutoff) {
            return isset($node['last_heartbeat']) && $node['last_heartbeat'] > $cutoff;
        });
    }

    /**
     * Register compute node (REST endpoint)
     *
     * @param \WP_REST_Request $request
     * @return array Response
     */
    public function register_node($request) {
        $params = $request->get_json_params();

        $node = [
            'node_id' => $params['node_id'] ?? wp_generate_uuid4(),
            'endpoint' => $params['endpoint'],
            'capabilities' => $params['capabilities'] ?? [],
            'registered_at' => time(),
            'last_heartbeat' => time()
        ];

        $this->register_node_internal($node);

        return [
            'success' => true,
            'message' => 'Node registered successfully',
            'node_id' => $node['node_id']
        ];
    }

    /**
     * Register node internally
     *
     * @param array $node Node data
     */
    private function register_node_internal($node) {
        $this->compute_nodes[$node['node_id']] = $node;
        update_option('aevov_aevip_compute_nodes', $this->compute_nodes);

        error_log(sprintf('[AevIP Security] Registered node: %s', $node['node_id']));
    }

    /**
     * Receive threat alert from network (REST endpoint)
     *
     * @param \WP_REST_Request $request
     * @return array Response
     */
    public function receive_threat_alert($request) {
        $packet = $request->get_json_params();

        // Verify packet integrity
        if (!$this->verify_packet($packet)) {
            return new \WP_Error('invalid_packet', 'Packet verification failed', ['status' => 400]);
        }

        $threat = $packet['data'];

        // Check if we've already seen this threat
        $cache_key = md5(json_encode($threat));
        if (isset($this->threat_cache[$cache_key])) {
            return ['success' => true, 'cached' => true];
        }

        // Store in threat cache
        $this->threat_cache[$cache_key] = $threat;

        // Log as external threat intelligence
        $this->log_external_threat($threat);

        return [
            'success' => true,
            'message' => 'Threat intelligence received',
            'threat_id' => $cache_key
        ];
    }

    /**
     * Log external threat from AevIP network
     *
     * @param array $threat Threat data
     */
    private function log_external_threat($threat) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'aevov_security_events',
            [
                'event_type' => 'external_threat_intel',
                'severity' => $threat['severity'] ?? 'medium',
                'title' => '[AevIP] ' . ($threat['title'] ?? 'External threat'),
                'description' => $threat['description'] ?? '',
                'mitre_technique' => $threat['mitre_technique'] ?? null,
                'yara_rule' => $threat['yara_rule'] ?? null,
                'metadata' => json_encode($threat),
                'created_at' => current_time('mysql')
            ]
        );
    }

    /**
     * Verify packet integrity
     *
     * @param array $packet Packet data
     * @return bool Valid
     */
    private function verify_packet($packet) {
        if (empty($packet['checksum']) || empty($packet['data'])) {
            return false;
        }

        $expected_checksum = $this->calculate_checksum($packet['data']);
        return hash_equals($expected_checksum, $packet['checksum']);
    }

    /**
     * Receive scan request (REST endpoint)
     *
     * @param \WP_REST_Request $request
     * @return array Response
     */
    public function receive_scan_request($request) {
        $packet = $request->get_json_params();

        if (!$this->verify_packet($packet)) {
            return new \WP_Error('invalid_packet', 'Packet verification failed', ['status' => 400]);
        }

        $scan_data = $packet['data'];

        // Execute scan asynchronously
        wp_schedule_single_event(time(), 'aevov_security_execute_remote_scan', [
            $scan_data['scan_id'],
            $scan_data['files'],
            $scan_data['options'],
            $packet['node_id']
        ]);

        return [
            'success' => true,
            'message' => 'Scan request accepted',
            'scan_id' => $scan_data['scan_id']
        ];
    }

    /**
     * Receive scan result (REST endpoint)
     *
     * @param \WP_REST_Request $request
     * @return array Response
     */
    public function receive_scan_result($request) {
        $packet = $request->get_json_params();

        if (!$this->verify_packet($packet)) {
            return new \WP_Error('invalid_packet', 'Packet verification failed', ['status' => 400]);
        }

        $result = $packet['data'];

        // Store result in transient
        set_transient(
            "aevip_scan_result_{$result['scan_id']}_{$packet['node_id']}",
            $result,
            3600 // 1 hour
        );

        return [
            'success' => true,
            'message' => 'Scan result received'
        ];
    }

    /**
     * Sync YARA rules across network (REST endpoint)
     *
     * @param \WP_REST_Request $request
     * @return array Response
     */
    public function sync_yara_rules($request) {
        $packet = $request->get_json_params();

        if (!$this->verify_packet($packet)) {
            return new \WP_Error('invalid_packet', 'Packet verification failed', ['status' => 400]);
        }

        $rules = $packet['data']['rules'] ?? [];

        // Import YARA rules
        foreach ($rules as $rule) {
            $this->import_yara_rule($rule);
        }

        return [
            'success' => true,
            'message' => sprintf('Imported %d YARA rules', count($rules))
        ];
    }

    /**
     * Import YARA rule
     *
     * @param array $rule Rule data
     */
    private function import_yara_rule($rule) {
        global $wpdb;

        $wpdb->replace(
            $wpdb->prefix . 'aevov_security_yara_rules',
            [
                'rule_name' => $rule['rule_name'],
                'rule_content' => $rule['rule_content'],
                'description' => $rule['description'] ?? '',
                'author' => $rule['author'] ?? 'AevIP Network',
                'enabled' => true,
                'malware_family' => $rule['malware_family'] ?? null,
                'severity' => $rule['severity'] ?? 'medium',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]
        );
    }

    /**
     * Node heartbeat (REST endpoint)
     *
     * @param \WP_REST_Request $request
     * @return array Response
     */
    public function node_heartbeat($request) {
        $packet = $request->get_json_params();
        $node_id = $packet['node_id'];

        if (isset($this->compute_nodes[$node_id])) {
            $this->compute_nodes[$node_id]['last_heartbeat'] = time();
            update_option('aevov_aevip_compute_nodes', $this->compute_nodes);
        }

        return [
            'success' => true,
            'timestamp' => time()
        ];
    }

    /**
     * Sync threat intelligence across network
     */
    public function sync_threat_intelligence() {
        // Get recent threats from local database
        global $wpdb;

        $recent_threats = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}aevov_security_events
             WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             AND severity IN ('critical', 'high')
             ORDER BY created_at DESC
             LIMIT 100",
            ARRAY_A
        );

        // Share with network
        foreach ($recent_threats as $threat) {
            $this->share_threat_intelligence($threat);
        }

        error_log(sprintf('[AevIP Security] Synced %d threats', count($recent_threats)));
    }
}
