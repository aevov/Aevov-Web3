<?php
/**
 * AevIP Integration
 *
 * Integrates Meshcore with the existing AevIP distributed computing protocol.
 * Enables AevIP workloads to be distributed over the mesh network.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Core;

/**
 * AevIP Integration Class
 */
class AevIPIntegration
{
    /**
     * Node manager
     *
     * @var NodeManager
     */
    private NodeManager $node_manager;

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
     * Handle AevIP node registered event
     *
     * @param string $node_id AevIP node ID
     * @param array $node_info AevIP node information
     * @return void
     */
    public function on_aevip_node_registered(string $node_id, array $node_info): void
    {
        // Check if this is also a mesh node
        $mesh_node_id = $this->node_manager->get_node_id();

        if ($node_id === $mesh_node_id) {
            // Same node - register mesh capabilities with AevIP
            $this->register_mesh_capabilities();
        }
    }

    /**
     * Register mesh capabilities with AevIP
     *
     * @return void
     */
    private function register_mesh_capabilities(): void
    {
        if (!class_exists('Aevov\\PhysicsEngine\\AevIP\\NodeManager')) {
            return;
        }

        // Add mesh transport to AevIP capabilities
        $capabilities = [
            'transport.mesh' => true,
            'transport.p2p' => true,
            'transport.relay' => $this->node_manager->has_capability('mesh.relay')
        ];

        do_action('aevip_register_capabilities', $capabilities);
    }

    /**
     * Add mesh transport method to AevIP
     *
     * @param array $transports Existing transports
     * @return array Updated transports
     */
    public function add_mesh_transport(array $transports): array
    {
        $transports['mesh'] = [
            'name' => 'Mesh Network',
            'description' => 'P2P mesh network transport',
            'priority' => 10,
            'send_callback' => [$this, 'send_via_mesh'],
            'receive_callback' => [$this, 'receive_via_mesh']
        ];

        return $transports;
    }

    /**
     * Send AevIP packet via mesh network
     *
     * @param string $destination_id Destination node ID
     * @param array $packet Packet data
     * @return bool Success
     */
    public function send_via_mesh(string $destination_id, array $packet): bool
    {
        // Wrap AevIP packet in mesh packet
        $mesh_packet = [
            'type' => 'aevip',
            'destination_id' => $destination_id,
            'payload' => $packet,
            'timestamp' => time()
        ];

        // Route via mesh
        return do_action('aevov_meshcore_send_packet', $destination_id, $mesh_packet);
    }

    /**
     * Receive AevIP packet from mesh network
     *
     * @param array $mesh_packet Mesh packet
     * @return void
     */
    public function receive_via_mesh(array $mesh_packet): void
    {
        if ($mesh_packet['type'] !== 'aevip') {
            return;
        }

        $aevip_packet = $mesh_packet['payload'] ?? null;

        if ($aevip_packet) {
            do_action('aevip_packet_received', $aevip_packet);
        }
    }

    /**
     * Check if AevIP is available
     *
     * @return bool
     */
    public function is_aevip_available(): bool
    {
        return class_exists('Aevov\\PhysicsEngine\\AevIP\\NodeManager');
    }

    /**
     * Get AevIP integration status
     *
     * @return array
     */
    public function get_status(): array
    {
        return [
            'aevip_available' => $this->is_aevip_available(),
            'mesh_transport_enabled' => $this->node_manager->has_capability('aevip.enabled'),
            'capabilities' => [
                'p2p' => true,
                'relay' => $this->node_manager->has_capability('mesh.relay'),
                'distributed_workload' => true
            ]
        ];
    }
}
