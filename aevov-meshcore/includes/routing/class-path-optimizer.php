<?php
/**
 * Path Optimizer
 *
 * Optimizes routing paths based on quality metrics.
 *
 * @package AevovMeshcore
 */

namespace Aevov\Meshcore\Routing;

/**
 * Path Optimizer Class
 */
class PathOptimizer
{
    /**
     * Calculate path quality score
     *
     * @param array $path_nodes Node IDs in path
     * @param array $connection_qualities Connection quality map
     * @return float Quality score (0-1)
     */
    public function calculate_path_quality(array $path_nodes, array $connection_qualities): float
    {
        if (count($path_nodes) < 2) {
            return 1.0;
        }

        $total_quality = 1.0;

        for ($i = 0; $i < count($path_nodes) - 1; $i++) {
            $from = $path_nodes[$i];
            $to = $path_nodes[$i + 1];
            $link_key = "{$from}:{$to}";

            $link_quality = $connection_qualities[$link_key] ?? 0.5;
            $total_quality *= $link_quality;
        }

        return $total_quality;
    }

    /**
     * Find optimal path using Dijkstra's algorithm
     *
     * @param string $source Source node ID
     * @param string $destination Destination node ID
     * @param array $graph Network graph
     * @return array|null Path or null
     */
    public function find_optimal_path(string $source, string $destination, array $graph): ?array
    {
        $distances = [];
        $previous = [];
        $unvisited = [];

        foreach ($graph as $node_id => $neighbors) {
            $distances[$node_id] = PHP_FLOAT_MAX;
            $previous[$node_id] = null;
            $unvisited[$node_id] = true;
        }

        $distances[$source] = 0;

        while (!empty($unvisited)) {
            // Find node with minimum distance
            $min_distance = PHP_FLOAT_MAX;
            $current = null;

            foreach ($unvisited as $node_id => $flag) {
                if ($distances[$node_id] < $min_distance) {
                    $min_distance = $distances[$node_id];
                    $current = $node_id;
                }
            }

            if ($current === null || $current === $destination) {
                break;
            }

            unset($unvisited[$current]);

            foreach ($graph[$current] ?? [] as $neighbor => $cost) {
                $alt = $distances[$current] + $cost;

                if ($alt < $distances[$neighbor]) {
                    $distances[$neighbor] = $alt;
                    $previous[$neighbor] = $current;
                }
            }
        }

        // Reconstruct path
        if ($previous[$destination] === null && $destination !== $source) {
            return null;
        }

        $path = [];
        $current = $destination;

        while ($current !== null) {
            array_unshift($path, $current);
            $current = $previous[$current];
        }

        return $path;
    }
}
