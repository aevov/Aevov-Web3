<?php
/**
 * Service Registry
 *
 * Decentralized service discovery registry (Mesh DNS).
 * Allows nodes to register and discover services across the mesh network.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Discovery;

use Aevov\Meshcore\Core\NodeManager;

/**
 * Service Registry Class
 */
class ServiceRegistry
{
    /**
     * Node manager
     *
     * @var NodeManager
     */
    private NodeManager $node_manager;

    /**
     * DHT service
     *
     * @var DHTService
     */
    private DHTService $dht_service;

    /**
     * Constructor
     *
     * @param NodeManager $node_manager Node manager
     * @param DHTService $dht_service DHT service
     */
    public function __construct(NodeManager $node_manager, DHTService $dht_service)
    {
        $this->node_manager = $node_manager;
        $this->dht_service = $dht_service;
    }

    /**
     * Register a service
     *
     * @param string $service_name Service name (e.g., "api.example")
     * @param string $service_type Service type (e.g., "http", "websocket")
     * @param string $endpoint Service endpoint
     * @param array $metadata Additional metadata
     * @return string Service ID
     */
    public function register_service(
        string $service_name,
        string $service_type,
        string $endpoint,
        array $metadata = []
    ): string {
        global $wpdb;

        $service_id = $this->generate_service_id($service_name, $this->node_manager->get_node_id());

        $table = $wpdb->prefix . 'meshcore_services';

        $wpdb->replace(
            $table,
            [
                'service_id' => $service_id,
                'service_name' => $service_name,
                'service_type' => $service_type,
                'node_id' => $this->node_manager->get_node_id(),
                'endpoint' => $endpoint,
                'metadata' => wp_json_encode($metadata),
                'status' => 'active'
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        // Announce to DHT
        $service_info = [
            'service_id' => $service_id,
            'service_name' => $service_name,
            'service_type' => $service_type,
            'node_id' => $this->node_manager->get_node_id(),
            'endpoint' => $endpoint,
            'metadata' => $metadata,
            'timestamp' => time()
        ];

        $this->dht_service->put("service:{$service_name}", $service_info, 600);

        do_action('aevov_meshcore_service_registered', $service_id, $service_info);

        return $service_id;
    }

    /**
     * Unregister a service
     *
     * @param string $service_id Service ID
     * @return bool Success
     */
    public function unregister_service(string $service_id): bool
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_services';

        $result = $wpdb->update(
            $table,
            ['status' => 'inactive'],
            ['service_id' => $service_id],
            ['%s'],
            ['%s']
        );

        if ($result) {
            do_action('aevov_meshcore_service_unregistered', $service_id);
        }

        return $result !== false;
    }

    /**
     * Discover services by name
     *
     * @param string $service_name Service name
     * @return array Service instances
     */
    public function discover_service(string $service_name): array
    {
        // Check local registry first
        $local_services = $this->get_local_services($service_name);

        // Query DHT for additional instances
        $dht_service = $this->dht_service->get("service:{$service_name}");

        $services = $local_services;

        if ($dht_service) {
            $services[] = $dht_service;
        }

        return $services;
    }

    /**
     * Discover services by type
     *
     * @param string $service_type Service type
     * @return array Services
     */
    public function discover_by_type(string $service_type): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_services';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE service_type = %s
             AND status = 'active'
             ORDER BY updated_at DESC",
            $service_type
        ), ARRAY_A);
    }

    /**
     * Resolve service name to endpoint
     *
     * @param string $service_name Service name
     * @return string|null Endpoint or null
     */
    public function resolve(string $service_name): ?string
    {
        $services = $this->discover_service($service_name);

        if (empty($services)) {
            return null;
        }

        // Return first active service endpoint
        $service = $services[0];
        return $service['endpoint'] ?? null;
    }

    /**
     * Get local services
     *
     * @param string|null $service_name Optional service name filter
     * @return array
     */
    private function get_local_services(?string $service_name = null): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_services';

        if ($service_name) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE service_name = %s
                 AND status = 'active'",
                $service_name
            ), ARRAY_A);
        }

        return $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'active'",
            ARRAY_A
        );
    }

    /**
     * Generate service ID
     *
     * @param string $service_name Service name
     * @param string $node_id Node ID
     * @return string
     */
    private function generate_service_id(string $service_name, string $node_id): string
    {
        return hash('sha256', $service_name . ':' . $node_id);
    }

    /**
     * List all services from this node
     *
     * @return array
     */
    public function list_local_services(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_services';
        $local_node_id = $this->node_manager->get_node_id();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE node_id = %s
             AND status = 'active'",
            $local_node_id
        ), ARRAY_A);
    }
}
