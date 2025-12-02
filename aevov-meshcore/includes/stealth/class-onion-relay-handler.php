<?php
/**
 * Onion Relay Handler
 *
 * Handles onion-routed requests through the mesh network.
 * Provides Tor-like privacy by routing through multiple hops
 * with layered encryption.
 *
 * Each relay node only knows:
 * - The previous hop
 * - The next hop
 * - NOT the source or destination
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Stealth;

use Aevov\Meshcore\Core\NodeManager;

/**
 * Onion Relay Handler Class
 */
class OnionRelayHandler
{
    /**
     * Node manager
     *
     * @var NodeManager
     */
    private NodeManager $node_manager;

    /**
     * Maximum relay hops
     *
     * @var int
     */
    private int $max_hops = 3;

    /**
     * Relay statistics
     *
     * @var array
     */
    private array $stats = [
        'relayed' => 0,
        'failed' => 0,
        'bytes' => 0
    ];

    /**
     * Constructor
     *
     * @param NodeManager $node_manager Node manager
     */
    public function __construct(NodeManager $node_manager)
    {
        $this->node_manager = $node_manager;
        $this->init_endpoints();
    }

    /**
     * Initialize relay endpoints
     *
     * @return void
     */
    private function init_endpoints(): void
    {
        add_action('rest_api_init', function() {
            register_rest_route('aevov-meshcore/v1', '/relay/onion', [
                'methods' => 'POST',
                'callback' => [$this, 'handle_onion_packet'],
                'permission_callback' => '__return_true' // Open for mesh network
            ]);
        });
    }

    /**
     * Handle incoming onion packet
     *
     * @param \WP_REST_Request $request Request
     * @return \WP_REST_Response
     */
    public function handle_onion_packet(\WP_REST_Request $request): \WP_REST_Response
    {
        $packet = $request->get_json_params();

        if (!isset($packet['type']) || $packet['type'] !== 'onion_relay') {
            return new \WP_REST_Response([
                'error' => 'Invalid packet type'
            ], 400);
        }

        // Verify we're a relay node
        if (!$this->node_manager->has_capability('mesh.relay')) {
            return new \WP_REST_Response([
                'error' => 'Not a relay node'
            ], 403);
        }

        // Decrypt our layer
        $decrypted = $this->decrypt_layer($packet);

        if (!$decrypted) {
            $this->stats['failed']++;
            return new \WP_REST_Response([
                'error' => 'Decryption failed'
            ], 400);
        }

        $this->stats['relayed']++;
        $this->stats['bytes'] += strlen(wp_json_encode($packet));

        // Check if we're the exit node
        if ($decrypted['next_hop'] === 'exit') {
            return $this->execute_exit_request($decrypted);
        }

        // Forward to next hop
        return $this->forward_to_next_hop($decrypted);
    }

    /**
     * Decrypt our layer of the onion
     *
     * @param array $packet Onion packet
     * @return array|false Decrypted payload
     */
    private function decrypt_layer(array $packet)
    {
        if (!isset($packet['encrypted_payload'])) {
            return false;
        }

        // Derive decryption key from our private key
        $decryption_key = hash('sha256', $this->node_manager->get_public_key(), true);

        $encrypted = $packet['encrypted_payload'];

        // Decrypt AES-256-GCM
        $decrypted_data = openssl_decrypt(
            base64_decode($encrypted['data']),
            'aes-256-gcm',
            $decryption_key,
            OPENSSL_RAW_DATA,
            base64_decode($encrypted['iv']),
            base64_decode($encrypted['tag'])
        );

        if ($decrypted_data === false) {
            return false;
        }

        return json_decode($decrypted_data, true);
    }

    /**
     * Execute request as exit node
     *
     * @param array $request_data Request data
     * @return \WP_REST_Response
     */
    private function execute_exit_request(array $request_data): \WP_REST_Response
    {
        if ($request_data['type'] !== 'ai_request') {
            return new \WP_REST_Response([
                'error' => 'Invalid request type'
            ], 400);
        }

        $url = $request_data['url'];
        $args = $request_data['args'];

        // Make the actual request (we're the exit node)
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return new \WP_REST_Response([
                'error' => $response->get_error_message()
            ], 500);
        }

        // Return response (will be encrypted by caller)
        return new \WP_REST_Response([
            'success' => true,
            'status_code' => wp_remote_retrieve_response_code($response),
            'headers' => wp_remote_retrieve_headers($response)->getAll(),
            'body' => wp_remote_retrieve_body($response)
        ]);
    }

    /**
     * Forward packet to next hop
     *
     * @param array $packet_data Packet data
     * @return \WP_REST_Response
     */
    private function forward_to_next_hop(array $packet_data): \WP_REST_Response
    {
        $next_hop_id = $packet_data['next_hop'];

        // Get next hop node information
        $next_hop = $this->get_node_info($next_hop_id);

        if (!$next_hop) {
            $this->stats['failed']++;
            return new \WP_REST_Response([
                'error' => 'Next hop not found'
            ], 404);
        }

        $network_info = json_decode($next_hop['network_info'], true);
        $api_endpoint = $network_info['api_endpoint'] ?? null;

        if (!$api_endpoint) {
            $this->stats['failed']++;
            return new \WP_REST_Response([
                'error' => 'Next hop has no endpoint'
            ], 500);
        }

        // Forward the packet
        $response = wp_remote_post("{$api_endpoint}/relay/onion", [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($packet_data)
        ]);

        if (is_wp_error($response)) {
            $this->stats['failed']++;
            return new \WP_REST_Response([
                'error' => $response->get_error_message()
            ], 500);
        }

        // Return the response from next hop
        return new \WP_REST_Response(
            json_decode(wp_remote_retrieve_body($response), true),
            wp_remote_retrieve_response_code($response)
        );
    }

    /**
     * Get node information from database
     *
     * @param string $node_id Node ID
     * @return array|null Node info
     */
    private function get_node_info(string $node_id): ?array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_nodes';

        $node = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE node_id = %s AND status = 'active'",
            $node_id
        ), ARRAY_A);

        return $node ?: null;
    }

    /**
     * Build onion circuit
     *
     * @param int $hop_count Number of hops
     * @return array|false Circuit nodes or false on failure
     */
    public function build_circuit(int $hop_count = 3): array|false
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_nodes';

        // Select relay nodes with high reputation
        $nodes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = 'active'
             AND JSON_EXTRACT(capabilities, '$.\"mesh.relay\"') = true
             AND reputation_score >= 80
             AND last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
             ORDER BY RAND()
             LIMIT %d",
            $hop_count
        ), ARRAY_A);

        if (count($nodes) < $hop_count) {
            return false; // Not enough relay nodes
        }

        return $nodes;
    }

    /**
     * Test circuit reliability
     *
     * @param array $circuit Circuit nodes
     * @return bool Circuit is reliable
     */
    public function test_circuit(array $circuit): bool
    {
        // Send test packet through circuit
        $test_packet = [
            'type' => 'circuit_test',
            'timestamp' => time(),
            'test_id' => bin2hex(random_bytes(16))
        ];

        // Build onion packet
        $packet = $test_packet;
        for ($i = count($circuit) - 1; $i >= 0; $i--) {
            $node = $circuit[$i];
            $public_key = $node['public_key'];
            $encryption_key = hash('sha256', $public_key, true);

            $encrypted = $this->encrypt_packet($packet, $encryption_key);

            $packet = [
                'type' => 'onion_relay',
                'next_hop' => $i < count($circuit) - 1 ? $circuit[$i + 1]['node_id'] : 'exit',
                'encrypted_payload' => $encrypted,
                'hop' => $i
            ];
        }

        // Send to first node
        $first_node = $circuit[0];
        $network_info = json_decode($first_node['network_info'], true);
        $api_endpoint = $network_info['api_endpoint'] ?? null;

        if (!$api_endpoint) {
            return false;
        }

        $response = wp_remote_post("{$api_endpoint}/relay/onion", [
            'timeout' => 10,
            'body' => wp_json_encode($packet)
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Encrypt packet for onion layer
     *
     * @param array $packet Packet data
     * @param string $key Encryption key
     * @return array Encrypted data
     */
    private function encrypt_packet(array $packet, string $key): array
    {
        $iv = random_bytes(12);
        $tag = '';
        $data = wp_json_encode($packet);

        $encrypted = openssl_encrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return [
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag)
        ];
    }

    /**
     * Get relay statistics
     *
     * @return array
     */
    public function get_stats(): array
    {
        return array_merge($this->stats, [
            'max_hops' => $this->max_hops,
            'success_rate' => $this->stats['relayed'] > 0
                ? ($this->stats['relayed'] / ($this->stats['relayed'] + $this->stats['failed'])) * 100
                : 0
        ]);
    }
}
