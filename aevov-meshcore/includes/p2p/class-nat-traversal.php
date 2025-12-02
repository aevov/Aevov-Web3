<?php
/**
 * NAT Traversal
 *
 * Helps with NAT traversal using STUN/TURN servers.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\P2P;

/**
 * NAT Traversal Class
 */
class NATTraversal
{
    /**
     * STUN servers
     *
     * @var array
     */
    private array $stun_servers;

    /**
     * TURN servers
     *
     * @var array
     */
    private array $turn_servers;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->stun_servers = get_option('aevov_meshcore_stun_servers', [
            'stun:stun.l.google.com:19302',
            'stun:stun1.l.google.com:19302'
        ]);

        $this->turn_servers = get_option('aevov_meshcore_turn_servers', []);
    }

    /**
     * Get ICE servers configuration
     *
     * @return array ICE servers
     */
    public function get_ice_servers(): array
    {
        $ice_servers = [];

        // Add STUN servers
        foreach ($this->stun_servers as $stun) {
            $ice_servers[] = ['urls' => $stun];
        }

        // Add TURN servers
        foreach ($this->turn_servers as $turn) {
            $ice_servers[] = [
                'urls' => $turn['urls'],
                'username' => $turn['username'] ?? '',
                'credential' => $turn['credential'] ?? ''
            ];
        }

        return $ice_servers;
    }

    /**
     * Test STUN server connectivity
     *
     * @param string $stun_url STUN server URL
     * @return bool Success
     */
    public function test_stun(string $stun_url): bool
    {
        // This would test STUN connectivity
        // Placeholder for now
        return true;
    }

    /**
     * Get external IP using STUN
     *
     * @return string|null External IP
     */
    public function get_external_ip(): ?string
    {
        // This would query STUN server for external IP
        // Placeholder - actual implementation would use STUN protocol
        return null;
    }
}
