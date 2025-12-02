<?php
/**
 * WebRTC Signaling
 *
 * Handles WebRTC signaling for peer connection establishment.
 * Manages SDP offer/answer exchange and ICE candidate exchange.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\P2P;

/**
 * WebRTC Signaling Class
 */
class WebRTCSignaling
{
    /**
     * Pending offers cache
     *
     * @var array
     */
    private array $pending_offers = [];

    /**
     * ICE candidates cache
     *
     * @var array
     */
    private array $ice_candidates = [];

    /**
     * Create WebRTC offer
     *
     * @param string $connection_id Connection ID
     * @param array $sdp SDP offer data
     * @param string $node_id Source node ID
     * @return void
     */
    public function create_offer(string $connection_id, array $sdp, string $node_id): void
    {
        $offer_key = "meshcore_offer_{$connection_id}";

        set_transient($offer_key, [
            'connection_id' => $connection_id,
            'sdp' => $sdp,
            'node_id' => $node_id,
            'timestamp' => time()
        ], 300); // 5 minutes TTL
    }

    /**
     * Get pending offer
     *
     * @param string $connection_id Connection ID
     * @return array|null
     */
    public function get_offer(string $connection_id): ?array
    {
        $offer_key = "meshcore_offer_{$connection_id}";
        $offer = get_transient($offer_key);

        return $offer ?: null;
    }

    /**
     * Create WebRTC answer
     *
     * @param string $connection_id Connection ID
     * @param array $sdp SDP answer data
     * @param string $node_id Source node ID
     * @return void
     */
    public function create_answer(string $connection_id, array $sdp, string $node_id): void
    {
        $answer_key = "meshcore_answer_{$connection_id}";

        set_transient($answer_key, [
            'connection_id' => $connection_id,
            'sdp' => $sdp,
            'node_id' => $node_id,
            'timestamp' => time()
        ], 300); // 5 minutes TTL
    }

    /**
     * Get pending answer
     *
     * @param string $connection_id Connection ID
     * @return array|null
     */
    public function get_answer(string $connection_id): ?array
    {
        $answer_key = "meshcore_answer_{$connection_id}";
        $answer = get_transient($answer_key);

        if ($answer) {
            delete_transient($answer_key); // One-time use
        }

        return $answer ?: null;
    }

    /**
     * Add ICE candidate
     *
     * @param string $connection_id Connection ID
     * @param array $candidate ICE candidate data
     * @param string $node_id Source node ID
     * @return void
     */
    public function add_ice_candidate(string $connection_id, array $candidate, string $node_id): void
    {
        $candidates_key = "meshcore_ice_{$connection_id}";
        $candidates = get_transient($candidates_key) ?: [];

        $candidates[] = [
            'candidate' => $candidate,
            'node_id' => $node_id,
            'timestamp' => time()
        ];

        set_transient($candidates_key, $candidates, 300); // 5 minutes TTL
    }

    /**
     * Get ICE candidates
     *
     * @param string $connection_id Connection ID
     * @param string|null $since_timestamp Only get candidates after this timestamp
     * @return array
     */
    public function get_ice_candidates(string $connection_id, ?string $since_timestamp = null): array
    {
        $candidates_key = "meshcore_ice_{$connection_id}";
        $candidates = get_transient($candidates_key) ?: [];

        if ($since_timestamp !== null) {
            $candidates = array_filter($candidates, function ($c) use ($since_timestamp) {
                return $c['timestamp'] > $since_timestamp;
            });
        }

        return array_values($candidates);
    }

    /**
     * Clear signaling data for connection
     *
     * @param string $connection_id Connection ID
     * @return void
     */
    public function clear_signaling(string $connection_id): void
    {
        delete_transient("meshcore_offer_{$connection_id}");
        delete_transient("meshcore_answer_{$connection_id}");
        delete_transient("meshcore_ice_{$connection_id}");
    }

    /**
     * Generate STUN/TURN server configuration
     *
     * @return array
     */
    public function get_ice_servers(): array
    {
        $default_servers = [
            [
                'urls' => 'stun:stun.l.google.com:19302'
            ],
            [
                'urls' => 'stun:stun1.l.google.com:19302'
            ]
        ];

        // Allow custom STUN/TURN servers
        $custom_servers = get_option('aevov_meshcore_ice_servers', []);

        if (!empty($custom_servers)) {
            $default_servers = array_merge($default_servers, $custom_servers);
        }

        return apply_filters('aevov_meshcore_ice_servers', $default_servers);
    }
}
