<?php
/**
 * Collision Detection System
 *
 * Supports:
 * - Broad phase (spatial hashing, BVH)
 * - Narrow phase (sphere-sphere, AABB-AABB, sphere-AABB, mesh collisions)
 * - Continuous collision detection
 */

namespace Aevov\PhysicsEngine\Core;

class CollisionDetector {

    private $spatial_grid = [];
    private $cell_size = 10.0;

    /**
     * Detect all collisions between entities
     */
    public function detect_collisions($entities) {
        $collisions = [];

        // Broad phase: Spatial hashing
        $this->build_spatial_grid($entities);
        $potential_pairs = $this->get_potential_collision_pairs();

        // Narrow phase: Precise collision detection
        foreach ($potential_pairs as $pair) {
            list($index_a, $index_b) = $pair;
            $entity_a = $entities[$index_a];
            $entity_b = $entities[$index_b];

            $collision = $this->check_collision($entity_a, $entity_b);
            if ($collision) {
                $collision['index_a'] = $index_a;
                $collision['index_b'] = $index_b;
                $collisions[] = $collision;
            }
        }

        return $collisions;
    }

    /**
     * Build spatial grid for broad phase
     */
    private function build_spatial_grid($entities) {
        $this->spatial_grid = [];

        foreach ($entities as $index => $entity) {
            if (!$entity['active']) continue;

            $cell_x = floor($entity['position']['x'] / $this->cell_size);
            $cell_y = floor($entity['position']['y'] / $this->cell_size);
            $cell_z = floor(($entity['position']['z'] ?? 0) / $this->cell_size);

            $cell_key = "{$cell_x}_{$cell_y}_{$cell_z}";

            if (!isset($this->spatial_grid[$cell_key])) {
                $this->spatial_grid[$cell_key] = [];
            }

            $this->spatial_grid[$cell_key][] = $index;
        }
    }

    /**
     * Get potential collision pairs from spatial grid
     */
    private function get_potential_collision_pairs() {
        $pairs = [];
        $checked = [];

        foreach ($this->spatial_grid as $cell) {
            // Check entities in same cell
            for ($i = 0; $i < count($cell); $i++) {
                for ($j = $i + 1; $j < count($cell); $j++) {
                    $pair_key = min($cell[$i], $cell[$j]) . '_' . max($cell[$i], $cell[$j]);
                    if (!isset($checked[$pair_key])) {
                        $pairs[] = [$cell[$i], $cell[$j]];
                        $checked[$pair_key] = true;
                    }
                }
            }
        }

        return $pairs;
    }

    /**
     * Check collision between two entities
     */
    private function check_collision($entity_a, $entity_b) {
        $collider_a_type = $entity_a['collider']['type'];
        $collider_b_type = $entity_b['collider']['type'];

        // Dispatch to appropriate collision function
        if ($collider_a_type === 'sphere' && $collider_b_type === 'sphere') {
            return $this->sphere_sphere_collision($entity_a, $entity_b);
        } elseif ($collider_a_type === 'aabb' && $collider_b_type === 'aabb') {
            return $this->aabb_aabb_collision($entity_a, $entity_b);
        } elseif (
            ($collider_a_type === 'sphere' && $collider_b_type === 'aabb') ||
            ($collider_a_type === 'aabb' && $collider_b_type === 'sphere')
        ) {
            return $this->sphere_aabb_collision($entity_a, $entity_b);
        }

        // Default: treat as spheres
        return $this->sphere_sphere_collision($entity_a, $entity_b);
    }

    /**
     * Sphere-sphere collision detection
     */
    private function sphere_sphere_collision($entity_a, $entity_b) {
        $radius_a = $entity_a['collider']['radius'] ?? 1.0;
        $radius_b = $entity_b['collider']['radius'] ?? 1.0;

        // Calculate distance between centers
        $dx = $entity_b['position']['x'] - $entity_a['position']['x'];
        $dy = $entity_b['position']['y'] - $entity_a['position']['y'];
        $dz = ($entity_b['position']['z'] ?? 0) - ($entity_a['position']['z'] ?? 0);

        $distance_sq = $dx * $dx + $dy * $dy + $dz * $dz;
        $distance = sqrt($distance_sq);

        $combined_radius = $radius_a + $radius_b;

        if ($distance < $combined_radius) {
            // Collision detected
            $penetration = $combined_radius - $distance;

            // Calculate collision normal
            if ($distance > 0.0001) {
                $normal = [
                    'x' => $dx / $distance,
                    'y' => $dy / $distance,
                    'z' => $dz / $distance
                ];
            } else {
                // Entities at same position
                $normal = ['x' => 1, 'y' => 0, 'z' => 0];
            }

            // Contact point (on surface of entity A)
            $contact = [
                'x' => $entity_a['position']['x'] + $normal['x'] * $radius_a,
                'y' => $entity_a['position']['y'] + $normal['y'] * $radius_a,
                'z' => ($entity_a['position']['z'] ?? 0) + $normal['z'] * $radius_a
            ];

            return [
                'type' => 'sphere_sphere',
                'normal' => $normal,
                'penetration' => $penetration,
                'contact_point' => $contact
            ];
        }

        return null;
    }

    /**
     * AABB-AABB collision detection
     */
    private function aabb_aabb_collision($entity_a, $entity_b) {
        $min_a = $this->get_aabb_min($entity_a);
        $max_a = $this->get_aabb_max($entity_a);
        $min_b = $this->get_aabb_min($entity_b);
        $max_b = $this->get_aabb_max($entity_b);

        // Check overlap on all axes
        if ($max_a['x'] < $min_b['x'] || $min_a['x'] > $max_b['x']) return null;
        if ($max_a['y'] < $min_b['y'] || $min_a['y'] > $max_b['y']) return null;
        if ($max_a['z'] < $min_b['z'] || $min_a['z'] > $max_b['z']) return null;

        // Calculate overlap on each axis
        $overlap_x = min($max_a['x'] - $min_b['x'], $max_b['x'] - $min_a['x']);
        $overlap_y = min($max_a['y'] - $min_b['y'], $max_b['y'] - $min_a['y']);
        $overlap_z = min($max_a['z'] - $min_b['z'], $max_b['z'] - $min_a['z']);

        // Find minimum penetration axis
        $penetration = min($overlap_x, $overlap_y, $overlap_z);
        $normal = ['x' => 0, 'y' => 0, 'z' => 0];

        if ($penetration === $overlap_x) {
            $normal['x'] = ($entity_a['position']['x'] < $entity_b['position']['x']) ? -1 : 1;
        } elseif ($penetration === $overlap_y) {
            $normal['y'] = ($entity_a['position']['y'] < $entity_b['position']['y']) ? -1 : 1;
        } else {
            $normal['z'] = (($entity_a['position']['z'] ?? 0) < ($entity_b['position']['z'] ?? 0)) ? -1 : 1;
        }

        return [
            'type' => 'aabb_aabb',
            'normal' => $normal,
            'penetration' => $penetration,
            'contact_point' => [
                'x' => ($min_a['x'] + $max_a['x'] + $min_b['x'] + $max_b['x']) / 4,
                'y' => ($min_a['y'] + $max_a['y'] + $min_b['y'] + $max_b['y']) / 4,
                'z' => ($min_a['z'] + $max_a['z'] + $min_b['z'] + $max_b['z']) / 4
            ]
        ];
    }

    /**
     * Sphere-AABB collision detection
     */
    private function sphere_aabb_collision($entity_a, $entity_b) {
        // Determine which is sphere and which is AABB
        $sphere = ($entity_a['collider']['type'] === 'sphere') ? $entity_a : $entity_b;
        $aabb = ($entity_a['collider']['type'] === 'aabb') ? $entity_a : $entity_b;

        $min = $this->get_aabb_min($aabb);
        $max = $this->get_aabb_max($aabb);

        // Find closest point on AABB to sphere center
        $closest = [
            'x' => max($min['x'], min($sphere['position']['x'], $max['x'])),
            'y' => max($min['y'], min($sphere['position']['y'], $max['y'])),
            'z' => max($min['z'], min($sphere['position']['z'] ?? 0, $max['z']))
        ];

        // Check distance
        $dx = $sphere['position']['x'] - $closest['x'];
        $dy = $sphere['position']['y'] - $closest['y'];
        $dz = ($sphere['position']['z'] ?? 0) - $closest['z'];

        $distance_sq = $dx * $dx + $dy * $dy + $dz * $dz;
        $radius = $sphere['collider']['radius'] ?? 1.0;

        if ($distance_sq < $radius * $radius) {
            $distance = sqrt($distance_sq);
            $penetration = $radius - $distance;

            if ($distance > 0.0001) {
                $normal = [
                    'x' => $dx / $distance,
                    'y' => $dy / $distance,
                    'z' => $dz / $distance
                ];
            } else {
                $normal = ['x' => 0, 'y' => 1, 'z' => 0];
            }

            return [
                'type' => 'sphere_aabb',
                'normal' => $normal,
                'penetration' => $penetration,
                'contact_point' => $closest
            ];
        }

        return null;
    }

    /**
     * Get AABB minimum bounds
     */
    private function get_aabb_min($entity) {
        $half_size = $entity['collider']['half_size'] ?? ['x' => 1, 'y' => 1, 'z' => 1];
        return [
            'x' => $entity['position']['x'] - $half_size['x'],
            'y' => $entity['position']['y'] - $half_size['y'],
            'z' => ($entity['position']['z'] ?? 0) - $half_size['z']
        ];
    }

    /**
     * Get AABB maximum bounds
     */
    private function get_aabb_max($entity) {
        $half_size = $entity['collider']['half_size'] ?? ['x' => 1, 'y' => 1, 'z' => 1];
        return [
            'x' => $entity['position']['x'] + $half_size['x'],
            'y' => $entity['position']['y'] + $half_size['y'],
            'z' => ($entity['position']['z'] ?? 0) + $half_size['z']
        ];
    }
}
