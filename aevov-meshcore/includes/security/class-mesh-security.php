<?php
/**
 * Mesh Security
 *
 * Handles security for mesh networking including:
 * - Packet encryption
 * - Node authentication
 * - Signature verification
 * - Anti-spam measures
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Security;

use Aevov\Meshcore\Core\NodeManager;

/**
 * Mesh Security Class
 */
class MeshSecurity
{
    /**
     * Node manager
     *
     * @var NodeManager
     */
    private NodeManager $node_manager;

    /**
     * Rate limiting
     *
     * @var array
     */
    private array $rate_limits = [];

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
     * Verify packet signature
     *
     * @param array $packet Packet with signature
     * @return bool Valid
     */
    public function verify_packet(array $packet): bool
    {
        if (!isset($packet['signature']) || !isset($packet['public_key'])) {
            return false;
        }

        // Verify node ID matches public key
        $expected_node_id = hash('sha256', $packet['public_key']);
        if (($packet['source_id'] ?? '') !== $expected_node_id) {
            return false;
        }

        // Extract payload
        $payload = $packet;
        unset($payload['signature']);
        $payload_json = wp_json_encode($payload);

        // Verify signature
        return $this->node_manager->verify_signature(
            $payload_json,
            $packet['signature'],
            $packet['public_key']
        );
    }

    /**
     * Sign packet
     *
     * @param array $packet Packet to sign
     * @return array Signed packet
     */
    public function sign_packet(array $packet): array
    {
        $packet['source_id'] = $this->node_manager->get_node_id();
        $packet['public_key'] = $this->node_manager->get_public_key();
        $packet['timestamp'] = time();

        $payload_json = wp_json_encode($packet);
        $packet['signature'] = $this->node_manager->sign_data($payload_json);

        return $packet;
    }

    /**
     * Check rate limit for node
     *
     * @param string $node_id Node ID
     * @param string $action Action type
     * @return bool Allowed
     */
    public function check_rate_limit(string $node_id, string $action = 'packet'): bool
    {
        $key = "{$node_id}:{$action}";
        $now = time();

        if (!isset($this->rate_limits[$key])) {
            $this->rate_limits[$key] = [
                'count' => 0,
                'window_start' => $now
            ];
        }

        $limit = &$this->rate_limits[$key];

        // Reset if window expired (1 second)
        if ($now - $limit['window_start'] > 1) {
            $limit['count'] = 0;
            $limit['window_start'] = $now;
        }

        $max_per_second = $this->get_rate_limit_for_action($action);

        if ($limit['count'] >= $max_per_second) {
            return false;
        }

        $limit['count']++;
        return true;
    }

    /**
     * Get rate limit for action
     *
     * @param string $action Action type
     * @return int Max actions per second
     */
    private function get_rate_limit_for_action(string $action): int
    {
        return match ($action) {
            'packet' => 100,
            'route_request' => 10,
            'connection' => 5,
            default => 50
        };
    }

    /**
     * Validate packet timestamp
     *
     * @param array $packet Packet
     * @param int $max_age_seconds Max age in seconds
     * @return bool Valid
     */
    public function validate_timestamp(array $packet, int $max_age_seconds = 300): bool
    {
        if (!isset($packet['timestamp'])) {
            return false;
        }

        $age = time() - $packet['timestamp'];

        return $age >= 0 && $age <= $max_age_seconds;
    }

    /**
     * Check if node is blacklisted
     *
     * @param string $node_id Node ID
     * @return bool Blacklisted
     */
    public function is_blacklisted(string $node_id): bool
    {
        $blacklist = get_option('aevov_meshcore_blacklist', []);
        return in_array($node_id, $blacklist, true);
    }

    /**
     * Blacklist a node
     *
     * @param string $node_id Node ID
     * @param string $reason Reason for blacklisting
     * @return void
     */
    public function blacklist_node(string $node_id, string $reason = ''): void
    {
        $blacklist = get_option('aevov_meshcore_blacklist', []);
        $blacklist[] = $node_id;
        update_option('aevov_meshcore_blacklist', array_unique($blacklist));

        do_action('aevov_meshcore_node_blacklisted', $node_id, $reason);
    }

    /**
     * Remove node from blacklist
     *
     * @param string $node_id Node ID
     * @return void
     */
    public function unblacklist_node(string $node_id): void
    {
        $blacklist = get_option('aevov_meshcore_blacklist', []);
        $blacklist = array_diff($blacklist, [$node_id]);
        update_option('aevov_meshcore_blacklist', array_values($blacklist));

        do_action('aevov_meshcore_node_unblacklisted', $node_id);
    }
}
