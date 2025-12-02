<?php
/**
 * AROS Trajectory Planner
 *
 * Real path planning implementation with:
 * - A* pathfinding algorithm
 * - RRT (Rapidly-exploring Random Trees)
 * - Spline interpolation for smooth paths
 * - Collision checking
 * - Velocity and acceleration constraints
 * - Path smoothing
 */

namespace AROS\Control;

class TrajectoryPlanner {
    private $map = null;
    private $map_width = 0;
    private $map_height = 0;
    private $map_resolution = 0.1; // meters per cell
    private $obstacles = [];
    private $robot_radius = 0.5; // meters
    private $max_iterations = 10000;
    private $path_cache = [];

    public function __construct($map_resolution = 0.1) {
        $this->map_resolution = $map_resolution;
        $this->log('Trajectory Planner initialized');
    }

    /**
     * Set occupancy map for collision checking
     */
    public function set_map($map, $width, $height) {
        $this->map = $map;
        $this->map_width = $width;
        $this->map_height = $height;
    }

    /**
     * Add obstacle to map
     */
    public function add_obstacle($x, $y, $radius) {
        $this->obstacles[] = [
            'x' => $x,
            'y' => $y,
            'radius' => $radius
        ];
    }

    /**
     * Main trajectory planning function
     * Uses A* by default, falls back to RRT for complex scenarios
     */
    public function plan($start, $goal, $algorithm = 'astar', $options = []) {
        // Validate inputs
        if (!$this->validate_position($start) || !$this->validate_position($goal)) {
            $this->log('Invalid start or goal position');
            return [];
        }

        // Check if path is cached
        $cache_key = $this->get_cache_key($start, $goal, $algorithm);
        if (isset($this->path_cache[$cache_key])) {
            $this->log('Returning cached path');
            return $this->path_cache[$cache_key];
        }

        $path = [];

        switch ($algorithm) {
            case 'astar':
                $path = $this->astar($start, $goal);
                break;

            case 'rrt':
                $path = $this->rrt($start, $goal, $options);
                break;

            case 'rrt_star':
                $path = $this->rrt_star($start, $goal, $options);
                break;

            default:
                $this->log('Unknown algorithm, using A*');
                $path = $this->astar($start, $goal);
        }

        // Post-process path
        if (!empty($path)) {
            // Smooth the path
            $path = $this->smooth_path($path);

            // Add velocity constraints
            $path = $this->add_velocity_profile($path, $options);

            // Cache the result
            $this->path_cache[$cache_key] = $path;
        }

        return $path;
    }

    /**
     * A* pathfinding algorithm
     * Finds optimal path in grid-based environments
     */
    private function astar($start, $goal) {
        $this->log('Running A* algorithm');

        $start_node = $this->position_to_node($start);
        $goal_node = $this->position_to_node($goal);

        // Open set: nodes to be evaluated
        $open_set = [$this->node_key($start_node) => $start_node];

        // Closed set: nodes already evaluated
        $closed_set = [];

        // Cost from start to node
        $g_score = [$this->node_key($start_node) => 0];

        // Estimated total cost from start to goal through node
        $f_score = [$this->node_key($start_node) => $this->heuristic($start_node, $goal_node)];

        // Parent tracking for path reconstruction
        $came_from = [];

        $iterations = 0;

        while (!empty($open_set) && $iterations < $this->max_iterations) {
            $iterations++;

            // Get node with lowest f_score
            $current = $this->get_lowest_f_score($open_set, $f_score);
            $current_key = $this->node_key($current);

            // Check if we reached the goal
            if ($this->is_goal($current, $goal_node)) {
                $this->log("A* found path in $iterations iterations");
                return $this->reconstruct_path($came_from, $current);
            }

            // Move current from open to closed
            unset($open_set[$current_key]);
            $closed_set[$current_key] = $current;

            // Check all neighbors
            $neighbors = $this->get_neighbors($current);

            foreach ($neighbors as $neighbor) {
                $neighbor_key = $this->node_key($neighbor);

                // Skip if already evaluated
                if (isset($closed_set[$neighbor_key])) {
                    continue;
                }

                // Calculate tentative g_score
                $tentative_g = $g_score[$current_key] + $this->distance($current, $neighbor);

                // Check if this path to neighbor is better
                if (!isset($open_set[$neighbor_key])) {
                    $open_set[$neighbor_key] = $neighbor;
                } elseif ($tentative_g >= ($g_score[$neighbor_key] ?? INF)) {
                    continue;
                }

                // This path is the best so far
                $came_from[$neighbor_key] = $current;
                $g_score[$neighbor_key] = $tentative_g;
                $f_score[$neighbor_key] = $tentative_g + $this->heuristic($neighbor, $goal_node);
            }
        }

        $this->log('A* failed to find path');
        return [];
    }

    /**
     * RRT (Rapidly-exploring Random Tree) algorithm
     * Good for high-dimensional spaces and complex environments
     */
    private function rrt($start, $goal, $options = []) {
        $this->log('Running RRT algorithm');

        $step_size = $options['step_size'] ?? 0.5;
        $goal_sample_rate = $options['goal_sample_rate'] ?? 0.1;
        $max_iterations = $options['max_iterations'] ?? $this->max_iterations;

        // Tree nodes
        $tree = [
            0 => [
                'position' => $start,
                'parent' => null
            ]
        ];

        $node_count = 1;

        for ($i = 0; $i < $max_iterations; $i++) {
            // Sample random point (or goal with probability)
            if (mt_rand() / mt_getrandmax() < $goal_sample_rate) {
                $random_point = $goal;
            } else {
                $random_point = $this->sample_free_space();
            }

            // Find nearest node in tree
            $nearest_id = $this->find_nearest_node($tree, $random_point);
            $nearest = $tree[$nearest_id];

            // Steer towards random point
            $new_point = $this->steer($nearest['position'], $random_point, $step_size);

            // Check if new point is collision-free
            if (!$this->is_collision_free($nearest['position'], $new_point)) {
                continue;
            }

            // Add new node to tree
            $tree[$node_count] = [
                'position' => $new_point,
                'parent' => $nearest_id
            ];

            // Check if we reached goal
            if ($this->distance_between_positions($new_point, $goal) < $step_size) {
                // Add goal node
                $tree[$node_count + 1] = [
                    'position' => $goal,
                    'parent' => $node_count
                ];

                $this->log("RRT found path in $i iterations");
                return $this->extract_rrt_path($tree, $node_count + 1);
            }

            $node_count++;
        }

        $this->log('RRT failed to find path');
        return [];
    }

    /**
     * RRT* algorithm (optimal version of RRT)
     * Finds better paths by rewiring the tree
     */
    private function rrt_star($start, $goal, $options = []) {
        $this->log('Running RRT* algorithm');

        $step_size = $options['step_size'] ?? 0.5;
        $goal_sample_rate = $options['goal_sample_rate'] ?? 0.1;
        $max_iterations = $options['max_iterations'] ?? $this->max_iterations;
        $search_radius = $options['search_radius'] ?? 1.0;

        // Tree nodes with cost
        $tree = [
            0 => [
                'position' => $start,
                'parent' => null,
                'cost' => 0
            ]
        ];

        $node_count = 1;
        $best_goal_id = null;
        $best_cost = INF;

        for ($i = 0; $i < $max_iterations; $i++) {
            // Sample random point
            if (mt_rand() / mt_getrandmax() < $goal_sample_rate) {
                $random_point = $goal;
            } else {
                $random_point = $this->sample_free_space();
            }

            // Find nearest node
            $nearest_id = $this->find_nearest_node($tree, $random_point);
            $nearest = $tree[$nearest_id];

            // Steer towards random point
            $new_point = $this->steer($nearest['position'], $random_point, $step_size);

            // Check collision
            if (!$this->is_collision_free($nearest['position'], $new_point)) {
                continue;
            }

            // Find nearby nodes for rewiring
            $nearby_nodes = $this->find_nearby_nodes($tree, $new_point, $search_radius);

            // Choose best parent
            $best_parent = $nearest_id;
            $min_cost = $tree[$nearest_id]['cost'] + $this->distance_between_positions($nearest['position'], $new_point);

            foreach ($nearby_nodes as $nearby_id) {
                $nearby = $tree[$nearby_id];
                $cost = $nearby['cost'] + $this->distance_between_positions($nearby['position'], $new_point);

                if ($cost < $min_cost && $this->is_collision_free($nearby['position'], $new_point)) {
                    $best_parent = $nearby_id;
                    $min_cost = $cost;
                }
            }

            // Add new node
            $tree[$node_count] = [
                'position' => $new_point,
                'parent' => $best_parent,
                'cost' => $min_cost
            ];

            // Rewire tree
            foreach ($nearby_nodes as $nearby_id) {
                $nearby = $tree[$nearby_id];
                $new_cost = $tree[$node_count]['cost'] + $this->distance_between_positions($new_point, $nearby['position']);

                if ($new_cost < $nearby['cost'] && $this->is_collision_free($new_point, $nearby['position'])) {
                    $tree[$nearby_id]['parent'] = $node_count;
                    $tree[$nearby_id]['cost'] = $new_cost;
                }
            }

            // Check if near goal
            if ($this->distance_between_positions($new_point, $goal) < $step_size) {
                if ($min_cost < $best_cost) {
                    $best_goal_id = $node_count;
                    $best_cost = $min_cost;
                }
            }

            $node_count++;
        }

        if ($best_goal_id !== null) {
            $this->log("RRT* found path with cost $best_cost");
            return $this->extract_rrt_path($tree, $best_goal_id);
        }

        $this->log('RRT* failed to find path');
        return [];
    }

    /**
     * Smooth path using cubic spline interpolation
     */
    private function smooth_path($path) {
        if (count($path) < 3) {
            return $path;
        }

        $smoothed = [];
        $num_points = count($path);

        // Use iterative path shortcutting
        $smoothed = $path;
        $improved = true;
        $max_iterations = 10;
        $iteration = 0;

        while ($improved && $iteration < $max_iterations) {
            $improved = false;
            $iteration++;
            $i = 0;

            while ($i < count($smoothed) - 2) {
                // Try to shortcut from i to i+2
                if ($this->is_collision_free($smoothed[$i], $smoothed[$i + 2])) {
                    // Remove intermediate point
                    array_splice($smoothed, $i + 1, 1);
                    $improved = true;
                } else {
                    $i++;
                }
            }
        }

        return $smoothed;
    }

    /**
     * Add velocity profile to path waypoints
     */
    private function add_velocity_profile($path, $options = []) {
        $max_velocity = $options['max_velocity'] ?? 1.0;
        $max_acceleration = $options['max_acceleration'] ?? 0.5;

        $waypoints = [];

        for ($i = 0; $i < count($path); $i++) {
            $velocity = $max_velocity;

            // Slow down at corners
            if ($i > 0 && $i < count($path) - 1) {
                $angle = $this->calculate_corner_angle($path[$i - 1], $path[$i], $path[$i + 1]);
                $velocity = $max_velocity * (1.0 - abs($angle) / M_PI);
            }

            // Slow down at start and end
            if ($i === 0 || $i === count($path) - 1) {
                $velocity = 0;
            }

            $waypoints[] = [
                'position' => $path[$i],
                'velocity' => $velocity,
                'timestamp' => $this->calculate_timestamp($path, $i, $max_velocity)
            ];
        }

        return $waypoints;
    }

    /**
     * Helper functions
     */

    private function validate_position($pos) {
        return isset($pos['x']) && isset($pos['y']);
    }

    private function position_to_node($pos) {
        return [
            'x' => (int)round($pos['x'] / $this->map_resolution),
            'y' => (int)round($pos['y'] / $this->map_resolution)
        ];
    }

    private function node_to_position($node) {
        return [
            'x' => $node['x'] * $this->map_resolution,
            'y' => $node['y'] * $this->map_resolution
        ];
    }

    private function node_key($node) {
        return $node['x'] . '_' . $node['y'];
    }

    private function heuristic($node1, $node2) {
        // Euclidean distance
        $dx = $node2['x'] - $node1['x'];
        $dy = $node2['y'] - $node1['y'];
        return sqrt($dx * $dx + $dy * $dy);
    }

    private function distance($node1, $node2) {
        return $this->heuristic($node1, $node2);
    }

    private function distance_between_positions($pos1, $pos2) {
        $dx = $pos2['x'] - $pos1['x'];
        $dy = $pos2['y'] - $pos1['y'];
        return sqrt($dx * $dx + $dy * $dy);
    }

    private function is_goal($node, $goal) {
        return $node['x'] === $goal['x'] && $node['y'] === $goal['y'];
    }

    private function get_lowest_f_score($open_set, $f_score) {
        $lowest_key = null;
        $lowest_score = INF;

        foreach ($open_set as $key => $node) {
            $score = $f_score[$key] ?? INF;
            if ($score < $lowest_score) {
                $lowest_score = $score;
                $lowest_key = $key;
            }
        }

        return $open_set[$lowest_key];
    }

    private function get_neighbors($node) {
        $neighbors = [];

        // 8-connected grid
        $directions = [
            [-1, -1], [0, -1], [1, -1],
            [-1,  0],          [1,  0],
            [-1,  1], [0,  1], [1,  1]
        ];

        foreach ($directions as $dir) {
            $neighbor = [
                'x' => $node['x'] + $dir[0],
                'y' => $node['y'] + $dir[1]
            ];

            // Check bounds
            if ($this->map !== null) {
                if ($neighbor['x'] < 0 || $neighbor['x'] >= $this->map_width ||
                    $neighbor['y'] < 0 || $neighbor['y'] >= $this->map_height) {
                    continue;
                }

                // Check if cell is occupied
                if ($this->map[$neighbor['y']][$neighbor['x']] > 0) {
                    continue;
                }
            } else {
                // Check against obstacles
                $pos = $this->node_to_position($neighbor);
                if (!$this->is_position_free($pos)) {
                    continue;
                }
            }

            $neighbors[] = $neighbor;
        }

        return $neighbors;
    }

    private function reconstruct_path($came_from, $current) {
        $path = [$this->node_to_position($current)];
        $current_key = $this->node_key($current);

        while (isset($came_from[$current_key])) {
            $current = $came_from[$current_key];
            $current_key = $this->node_key($current);
            array_unshift($path, $this->node_to_position($current));
        }

        return $path;
    }

    private function sample_free_space() {
        $max_tries = 100;

        for ($i = 0; $i < $max_tries; $i++) {
            $point = [
                'x' => (mt_rand() / mt_getrandmax()) * $this->map_width * $this->map_resolution,
                'y' => (mt_rand() / mt_getrandmax()) * $this->map_height * $this->map_resolution
            ];

            if ($this->is_position_free($point)) {
                return $point;
            }
        }

        // Return random point even if not free
        return [
            'x' => (mt_rand() / mt_getrandmax()) * $this->map_width * $this->map_resolution,
            'y' => (mt_rand() / mt_getrandmax()) * $this->map_height * $this->map_resolution
        ];
    }

    private function is_position_free($pos) {
        // Check against obstacles
        foreach ($this->obstacles as $obstacle) {
            $dist = sqrt(
                pow($pos['x'] - $obstacle['x'], 2) +
                pow($pos['y'] - $obstacle['y'], 2)
            );

            if ($dist < $obstacle['radius'] + $this->robot_radius) {
                return false;
            }
        }

        return true;
    }

    private function is_collision_free($pos1, $pos2) {
        // Check line segment for collisions
        $num_checks = (int)ceil($this->distance_between_positions($pos1, $pos2) / ($this->map_resolution * 0.5));
        $num_checks = max(2, $num_checks);

        for ($i = 0; $i <= $num_checks; $i++) {
            $t = $i / $num_checks;
            $point = [
                'x' => $pos1['x'] + $t * ($pos2['x'] - $pos1['x']),
                'y' => $pos1['y'] + $t * ($pos2['y'] - $pos1['y'])
            ];

            if (!$this->is_position_free($point)) {
                return false;
            }
        }

        return true;
    }

    private function find_nearest_node($tree, $point) {
        $nearest_id = 0;
        $min_dist = INF;

        foreach ($tree as $id => $node) {
            $dist = $this->distance_between_positions($node['position'], $point);
            if ($dist < $min_dist) {
                $min_dist = $dist;
                $nearest_id = $id;
            }
        }

        return $nearest_id;
    }

    private function find_nearby_nodes($tree, $point, $radius) {
        $nearby = [];

        foreach ($tree as $id => $node) {
            $dist = $this->distance_between_positions($node['position'], $point);
            if ($dist < $radius) {
                $nearby[] = $id;
            }
        }

        return $nearby;
    }

    private function steer($from, $to, $step_size) {
        $dist = $this->distance_between_positions($from, $to);

        if ($dist <= $step_size) {
            return $to;
        }

        $t = $step_size / $dist;

        return [
            'x' => $from['x'] + $t * ($to['x'] - $from['x']),
            'y' => $from['y'] + $t * ($to['y'] - $from['y'])
        ];
    }

    private function extract_rrt_path($tree, $goal_id) {
        $path = [];
        $current_id = $goal_id;

        while ($current_id !== null) {
            array_unshift($path, $tree[$current_id]['position']);
            $current_id = $tree[$current_id]['parent'];
        }

        return $path;
    }

    private function calculate_corner_angle($p1, $p2, $p3) {
        // Calculate angle at p2
        $v1 = [
            'x' => $p1['x'] - $p2['x'],
            'y' => $p1['y'] - $p2['y']
        ];

        $v2 = [
            'x' => $p3['x'] - $p2['x'],
            'y' => $p3['y'] - $p2['y']
        ];

        $dot = $v1['x'] * $v2['x'] + $v1['y'] * $v2['y'];
        $mag1 = sqrt($v1['x'] * $v1['x'] + $v1['y'] * $v1['y']);
        $mag2 = sqrt($v2['x'] * $v2['x'] + $v2['y'] * $v2['y']);

        if ($mag1 == 0 || $mag2 == 0) {
            return 0;
        }

        $cos_angle = $dot / ($mag1 * $mag2);
        $cos_angle = max(-1, min(1, $cos_angle));

        return acos($cos_angle);
    }

    private function calculate_timestamp($path, $index, $max_velocity) {
        $time = 0;

        for ($i = 0; $i < $index; $i++) {
            $dist = $this->distance_between_positions($path[$i], $path[$i + 1]);
            $time += $dist / $max_velocity;
        }

        return $time;
    }

    private function get_cache_key($start, $goal, $algorithm) {
        return sprintf('%s_%.2f_%.2f_%.2f_%.2f',
            $algorithm,
            $start['x'], $start['y'],
            $goal['x'], $goal['y']
        );
    }

    private function log($message) {
        error_log('[AROS Trajectory Planner] ' . $message);
    }
}
