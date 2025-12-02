<?php
/**
 * Node Manager
 *
 * Manages the identity, capabilities, and state of the local mesh node.
 * Each node has a unique identity (public/private key pair) and advertises
 * its capabilities to the mesh network.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Core;

/**
 * Node Manager Class
 */
class NodeManager
{
    /**
     * Local node ID (hash of public key)
     *
     * @var string|null
     */
    private ?string $node_id = null;

    /**
     * Public key for node identity
     *
     * @var string|null
     */
    private ?string $public_key = null;

    /**
     * Private key for signing
     *
     * @var string|null
     */
    private ?string $private_key = null;

    /**
     * Node capabilities
     *
     * @var array
     */
    private array $capabilities = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->load_identity();
        $this->detect_capabilities();
    }

    /**
     * Ensure node has a unique identity
     *
     * @return void
     */
    public function ensure_node_identity(): void
    {
        if ($this->node_id !== null) {
            return;
        }

        $this->generate_identity();
        $this->save_identity();
    }

    /**
     * Load node identity from database
     *
     * @return void
     */
    private function load_identity(): void
    {
        $this->public_key = get_option('aevov_meshcore_public_key');
        $this->private_key = get_option('aevov_meshcore_private_key');

        if ($this->public_key && $this->private_key) {
            $this->node_id = $this->hash_public_key($this->public_key);
        }
    }

    /**
     * Generate new cryptographic identity
     *
     * @return void
     */
    private function generate_identity(): void
    {
        // Generate Ed25519 key pair for signing
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1'
        ];

        $res = openssl_pkey_new($config);
        if ($res === false) {
            throw new \Exception('Failed to generate key pair');
        }

        // Export private key
        openssl_pkey_export($res, $private_key);
        $this->private_key = $private_key;

        // Export public key
        $key_details = openssl_pkey_get_details($res);
        $this->public_key = $key_details['key'];

        // Generate node ID from public key
        $this->node_id = $this->hash_public_key($this->public_key);
    }

    /**
     * Save identity to database
     *
     * @return void
     */
    private function save_identity(): void
    {
        update_option('aevov_meshcore_public_key', $this->public_key);
        update_option('aevov_meshcore_private_key', $this->private_key);
        update_option('aevov_meshcore_node_id', $this->node_id);
    }

    /**
     * Hash public key to generate node ID
     *
     * @param string $public_key Public key
     * @return string Node ID
     */
    private function hash_public_key(string $public_key): string
    {
        return hash('sha256', $public_key);
    }

    /**
     * Detect node capabilities
     *
     * @return void
     */
    private function detect_capabilities(): void
    {
        $this->capabilities = [
            // Core mesh capabilities
            'mesh.routing' => true,
            'mesh.relay' => $this->can_relay(),
            'mesh.storage' => $this->can_store_dht(),

            // Protocol support
            'protocols.webrtc' => $this->supports_webrtc(),
            'protocols.websocket' => true,
            'protocols.http' => true,

            // Network capabilities
            'network.public_ip' => $this->has_public_ip(),
            'network.ipv6' => $this->supports_ipv6(),
            'network.upnp' => $this->supports_upnp(),

            // Bandwidth
            'bandwidth.upload' => $this->estimate_upload_bandwidth(),
            'bandwidth.download' => $this->estimate_download_bandwidth(),
            'bandwidth.share' => get_option('aevov_meshcore_share_bandwidth', true),

            // Storage
            'storage.available' => $this->get_available_storage(),
            'storage.dht' => get_option('aevov_meshcore_dht_storage', 1024 * 1024 * 100), // 100MB default

            // Compute
            'compute.cores' => $this->get_cpu_cores(),
            'compute.available' => get_option('aevov_meshcore_share_compute', false),

            // AevIP integration
            'aevip.enabled' => class_exists('Aevov\\PhysicsEngine\\AevIP\\NodeManager'),
            'aevip.version' => defined('AEVOV_PHYSICS_VERSION') ? AEVOV_PHYSICS_VERSION : null,

            // Services
            'services.stun' => get_option('aevov_meshcore_run_stun', false),
            'services.turn' => get_option('aevov_meshcore_run_turn', false),
            'services.bootstrap' => get_option('aevov_meshcore_is_bootstrap', false),

            // Reliability
            'reliability.uptime' => $this->get_uptime_percentage(),
            'reliability.reputation' => $this->get_reputation_score(),
        ];

        // Allow filtering of capabilities
        $this->capabilities = apply_filters('aevov_meshcore_node_capabilities', $this->capabilities);
    }

    /**
     * Check if node can relay traffic
     *
     * @return bool
     */
    private function can_relay(): bool
    {
        // Check if relay is enabled in settings
        $relay_enabled = get_option('aevov_meshcore_enable_relay', true);

        // Check if we have sufficient bandwidth
        $min_bandwidth = 1024 * 1024; // 1 Mbps minimum
        $upload_bw = $this->estimate_upload_bandwidth();

        return $relay_enabled && $upload_bw >= $min_bandwidth;
    }

    /**
     * Check if node can store DHT data
     *
     * @return bool
     */
    private function can_store_dht(): bool
    {
        $storage_available = $this->get_available_storage();
        $min_storage = 1024 * 1024 * 10; // 10MB minimum

        return $storage_available >= $min_storage;
    }

    /**
     * Check WebRTC support (browser-based, assume true)
     *
     * @return bool
     */
    private function supports_webrtc(): bool
    {
        return true; // WebRTC support is client-side
    }

    /**
     * Check if node has public IP
     *
     * @return bool
     */
    private function has_public_ip(): bool
    {
        // Try to get external IP
        $external_ip = $this->get_external_ip();

        if (!$external_ip) {
            return false;
        }

        // Check if it's a private IP
        return filter_var(
            $external_ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * Get external IP address
     *
     * @return string|null
     */
    private function get_external_ip(): ?string
    {
        $cached_ip = get_transient('aevov_meshcore_external_ip');
        if ($cached_ip) {
            return $cached_ip;
        }

        // Try multiple services
        $services = [
            'https://api.ipify.org',
            'https://icanhazip.com',
            'https://ifconfig.me/ip'
        ];

        foreach ($services as $service) {
            $response = wp_remote_get($service, ['timeout' => 5]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $ip = trim(wp_remote_retrieve_body($response));
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    set_transient('aevov_meshcore_external_ip', $ip, HOUR_IN_SECONDS);
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Check IPv6 support
     *
     * @return bool
     */
    private function supports_ipv6(): bool
    {
        // Simple check - try to connect to IPv6 address
        $socket = @socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            return false;
        }
        socket_close($socket);
        return true;
    }

    /**
     * Check UPnP support
     *
     * @return bool
     */
    private function supports_upnp(): bool
    {
        // This would require actual UPnP detection
        // For now, return false - can be implemented later
        return false;
    }

    /**
     * Estimate upload bandwidth (bytes/sec)
     *
     * @return int
     */
    private function estimate_upload_bandwidth(): int
    {
        // Get from settings or use default
        $bandwidth = get_option('aevov_meshcore_upload_bandwidth', 0);

        if ($bandwidth === 0) {
            // Use conservative default (1 Mbps)
            $bandwidth = 1024 * 1024;
        }

        return (int) $bandwidth;
    }

    /**
     * Estimate download bandwidth (bytes/sec)
     *
     * @return int
     */
    private function estimate_download_bandwidth(): int
    {
        // Get from settings or use default
        $bandwidth = get_option('aevov_meshcore_download_bandwidth', 0);

        if ($bandwidth === 0) {
            // Use conservative default (10 Mbps)
            $bandwidth = 10 * 1024 * 1024;
        }

        return (int) $bandwidth;
    }

    /**
     * Get available storage in bytes
     *
     * @return int
     */
    private function get_available_storage(): int
    {
        $upload_dir = wp_upload_dir();
        $path = $upload_dir['basedir'];

        if (function_exists('disk_free_space')) {
            return (int) @disk_free_space($path);
        }

        // Default to 1GB if can't determine
        return 1024 * 1024 * 1024;
    }

    /**
     * Get number of CPU cores
     *
     * @return int
     */
    private function get_cpu_cores(): int
    {
        $cores = 1;

        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $cores = count($matches[0]);
        } elseif (DIRECTORY_SEPARATOR === '\\') {
            // Windows
            $process = @popen('wmic cpu get NumberOfCores', 'rb');
            if ($process !== false) {
                fgets($process); // Skip header
                $cores = (int) fgets($process);
                pclose($process);
            }
        }

        return max(1, $cores);
    }

    /**
     * Get uptime percentage (last 30 days)
     *
     * @return float
     */
    private function get_uptime_percentage(): float
    {
        global $wpdb;

        // Calculate from connection history
        $table = $wpdb->prefix . 'meshcore_connections';
        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));

        $total_time = 30 * 24 * 60 * 60; // 30 days in seconds

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(TIMESTAMPDIFF(SECOND, established_at, COALESCE(last_activity, NOW())))
             FROM {$table}
             WHERE local_node_id = %s
             AND established_at >= %s",
            $this->node_id,
            $thirty_days_ago
        ));

        $connected_time = $result ? (int) $result : 0;

        return min(100, ($connected_time / $total_time) * 100);
    }

    /**
     * Get reputation score
     *
     * @return int
     */
    private function get_reputation_score(): int
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_bandwidth_tokens';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT reputation_modifier FROM {$table} WHERE node_id = %s",
            $this->node_id
        ));

        if ($result) {
            return (int) (100 * $result->reputation_modifier);
        }

        return 100; // Default reputation
    }

    /**
     * Get node ID
     *
     * @return string
     */
    public function get_node_id(): string
    {
        if ($this->node_id === null) {
            $this->ensure_node_identity();
        }
        return $this->node_id;
    }

    /**
     * Get public key
     *
     * @return string
     */
    public function get_public_key(): string
    {
        if ($this->public_key === null) {
            $this->ensure_node_identity();
        }
        return $this->public_key;
    }

    /**
     * Get capabilities
     *
     * @return array
     */
    public function get_capabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Check if node has specific capability
     *
     * @param string $capability Capability name
     * @return bool
     */
    public function has_capability(string $capability): bool
    {
        return isset($this->capabilities[$capability]) && $this->capabilities[$capability];
    }

    /**
     * Sign data with private key
     *
     * @param string $data Data to sign
     * @return string Signature
     */
    public function sign_data(string $data): string
    {
        $signature = '';
        openssl_sign($data, $signature, $this->private_key, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * Verify signature from another node
     *
     * @param string $data Original data
     * @param string $signature Signature to verify
     * @param string $public_key Public key of signer
     * @return bool
     */
    public function verify_signature(string $data, string $signature, string $public_key): bool
    {
        $signature_binary = base64_decode($signature);
        return openssl_verify($data, $signature_binary, $public_key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Get node information
     *
     * @return array
     */
    public function get_node_info(): array
    {
        return [
            'node_id' => $this->get_node_id(),
            'public_key' => $this->get_public_key(),
            'capabilities' => $this->get_capabilities(),
            'network_info' => [
                'external_ip' => $this->get_external_ip(),
                'supports_ipv6' => $this->supports_ipv6(),
                'has_public_ip' => $this->has_public_ip()
            ],
            'status' => [
                'uptime' => $this->get_uptime_percentage(),
                'reputation' => $this->get_reputation_score()
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Update node in database
     *
     * @return void
     */
    public function update_node_record(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_nodes';
        $node_info = $this->get_node_info();

        $wpdb->replace(
            $table,
            [
                'node_id' => $this->get_node_id(),
                'peer_id' => $this->get_node_id(), // Same as node_id for local node
                'public_key' => $this->get_public_key(),
                'capabilities' => wp_json_encode($this->get_capabilities()),
                'network_info' => wp_json_encode($node_info['network_info']),
                'last_seen' => current_time('mysql'),
                'status' => 'active',
                'reputation_score' => $this->get_reputation_score()
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );
    }
}
