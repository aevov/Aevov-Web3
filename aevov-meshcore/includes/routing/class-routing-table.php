<?php
/**
 * Routing Table
 *
 * Manages routing table operations and optimizations.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Routing;

/**
 * Routing Table Class
 */
class RoutingTable
{
    /**
     * Get best route to destination
     *
     * @param string $destination_id Destination node ID
     * @return array|null Route or null
     */
    public function get_best_route(string $destination_id): ?array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_routes';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE destination_id = %s
             AND expires_at > NOW()
             ORDER BY path_cost ASC, path_quality DESC
             LIMIT 1",
            $destination_id
        ), ARRAY_A);
    }

    /**
     * Get all routes to destination
     *
     * @param string $destination_id Destination node ID
     * @return array Routes
     */
    public function get_all_routes(string $destination_id): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_routes';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE destination_id = %s
             AND expires_at > NOW()
             ORDER BY path_cost ASC",
            $destination_id
        ), ARRAY_A);
    }

    /**
     * Get routes through next hop
     *
     * @param string $next_hop_id Next hop node ID
     * @return array Routes
     */
    public function get_routes_through(string $next_hop_id): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'meshcore_routes';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE next_hop_id = %s
             AND expires_at > NOW()",
            $next_hop_id
        ), ARRAY_A);
    }
}
