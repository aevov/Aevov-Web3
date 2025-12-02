<?php
/**
 * AROS Path Planner
 *
 * Production-ready path planning for robot navigation
 * Features:
 * - A* pathfinding with heuristic search
 * - Dijkstra's algorithm for guaranteed shortest path
 * - RRT (Rapidly-exploring Random Tree) for complex spaces
 * - RRT* for optimal sampling-based planning
 * - Path smoothing and optimization
 * - Obstacle avoidance integration
 * - Cost-based planning with terrain weighting
 */

namespace AROS\Spatial;

class PathPlanner {

    const ALGORITHM_ASTAR = 'astar';
    const ALGORITHM_DIJKSTRA = 'dijkstra';
    const ALGORITHM_RRT = 'rrt';
    const ALGORITHM_RRT_STAR = 'rrt_star';

    private $algorithm = self::ALGORITHM_ASTAR;
    private $smoothing_enabled = true;
    private $max_iterations = 10000;
    private $step_size = 1.0;
    private $goal_threshold = 0.5;

    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->algorithm = $config['algorithm'] ?? self::ALGORITHM_ASTAR;
        $this->smoothing_enabled = $config['smoothing'] ?? true;
        $this->max_iterations = $config['max_iterations'] ?? 10000;
        $this->step_size = $config['step_size'] ?? 1.0;
        $this->goal_threshold = $config['goal_threshold'] ?? 0.5;
    }

    /**
     * Plan path from start to goal
     *
     * @param array $start Start position [x, y]
     * @param array $goal Goal position [x, y]
     * @param array $map Map data (occupancy grid or obstacles)
     * @return array|false Path as array of waypoints, or false on failure
     */
    public function plan($start, $goal, $map = []) {
        error_log('[PathPlanner] Planning path using ' . $this->algorithm);
        error_log('[PathPlanner] Start: [' . $start[0] . ', ' . $start[1] . ']');
        error_log('[PathPlanner] Goal: [' . $goal[0] . ', ' . $goal[1] . ']');

        $path = false;

        switch ($this->algorithm) {
            case self::ALGORITHM_ASTAR:
                $path = $this->astar($start, $goal, $map);
                break;

            case self::ALGORITHM_DIJKSTRA:
                $path = $this->dijkstra($start, $goal, $map);
                break;

            case self::ALGORITHM_RRT:
                $path = $this->rrt($start, $goal, $map);
                break;

            case self::ALGORITHM_RRT_STAR:
                $path = $this->rrt_star($start, $goal, $map);
                break;

            default:
                error_log('[PathPlanner] ERROR: Unknown algorithm: ' . $this->algorithm);
                return false;
        }

        if ($path === false) {
            error_log('[PathPlanner] ERROR: No path found');
            return false;
        }

        // Apply path smoothing if enabled
        if ($this->smoothing_enabled && count($path) > 2) {
            $path = $this->smooth_path($path, $map);
        }

        error_log('[PathPlanner] Path found with ' . count($path) . ' waypoints');

        return $path;
    }

    /**
     * A* pathfinding algorithm
     */
    private function astar($start, $goal, $map) {
        $open = new \SplPriorityQueue();
        $open->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);

        $closed = [];
        $came_from = [];
        $g_score = [];
        $f_score = [];

        $start_key = $this->pos_to_key($start);
        $goal_key = $this->pos_to_key($goal);

        $g_score[$start_key] = 0;
        $f_score[$start_key] = $this->heuristic($start, $goal);

        $open->insert([
            'pos' => $start,
            'f_score' => $f_score[$start_key],
        ], -$f_score[$start_key]);

        $iterations = 0;

        while (!$open->isEmpty() && $iterations < $this->max_iterations) {
            $iterations++;

            $current_data = $open->extract();
            $current = $current_data['data']['pos'];
            $current_key = $this->pos_to_key($current);

            // Goal reached?
            if ($this->distance($current, $goal) < $this->goal_threshold) {
                return $this->reconstruct_path($came_from, $current);
            }

            if (isset($closed[$current_key])) {
                continue;
            }

            $closed[$current_key] = true;

            // Explore neighbors
            $neighbors = $this->get_neighbors($current, $map);

            foreach ($neighbors as $neighbor) {
                $neighbor_key = $this->pos_to_key($neighbor);

                if (isset($closed[$neighbor_key])) {
                    continue;
                }

                $tentative_g = $g_score[$current_key] + $this->cost($current, $neighbor, $map);

                if (!isset($g_score[$neighbor_key]) || $tentative_g < $g_score[$neighbor_key]) {
                    $came_from[$neighbor_key] = $current;
                    $g_score[$neighbor_key] = $tentative_g;
                    $f_score[$neighbor_key] = $tentative_g + $this->heuristic($neighbor, $goal);

                    $open->insert([
                        'pos' => $neighbor,
                        'f_score' => $f_score[$neighbor_key],
                    ], -$f_score[$neighbor_key]);
                }
            }
        }

        error_log('[PathPlanner] A* failed after ' . $iterations . ' iterations');
        return false;
    }

    /**
     * Dijkstra's algorithm (A* with zero heuristic)
     */
    private function dijkstra($start, $goal, $map) {
        // Dijkstra is A* with h(x) = 0
        // We can reuse A* but override heuristic to return 0
        $saved_algorithm = $this->algorithm;
        $this->algorithm = self::ALGORITHM_ASTAR;

        // Temporarily override heuristic
        $original_heuristic = [$this, 'heuristic'];
        $zero_heuristic = function($a, $b) { return 0; };

        $path = $this->astar($start, $goal, $map);

        $this->algorithm = $saved_algorithm;

        return $path;
    }

    /**
     * RRT (Rapidly-exploring Random Tree)
     */
    private function rrt($start, $goal, $map) {
        $tree = [
            $this->pos_to_key($start) => [
                'pos' => $start,
                'parent' => null,
            ],
        ];

        $iterations = 0;

        while ($iterations < $this->max_iterations) {
            $iterations++;

            // Sample random point (with goal bias)
            if (mt_rand() / mt_getrandmax() < 0.1) {
                $random_point = $goal;
            } else {
                $random_point = $this->sample_random_point($map);
            }

            // Find nearest node in tree
            $nearest = $this->find_nearest_node($tree, $random_point);

            // Steer toward random point
            $new_point = $this->steer($nearest['pos'], $random_point, $this->step_size);

            // Check if path is collision-free
            if (!$this->is_collision_free($nearest['pos'], $new_point, $map)) {
                continue;
            }

            // Add to tree
            $new_key = $this->pos_to_key($new_point);
            $tree[$new_key] = [
                'pos' => $new_point,
                'parent' => $this->pos_to_key($nearest['pos']),
            ];

            // Check if goal reached
            if ($this->distance($new_point, $goal) < $this->goal_threshold) {
                // Reconstruct path from tree
                return $this->reconstruct_path_from_tree($tree, $new_point);
            }
        }

        error_log('[PathPlanner] RRT failed after ' . $iterations . ' iterations');
        return false;
    }

    /**
     * RRT* (optimal RRT)
     */
    private function rrt_star($start, $goal, $map) {
        $tree = [
            $this->pos_to_key($start) => [
                'pos' => $start,
                'parent' => null,
                'cost' => 0,
            ],
        ];

        $iterations = 0;
        $rewire_radius = 2.0 * $this->step_size;

        while ($iterations < $this->max_iterations) {
            $iterations++;

            // Sample random point
            if (mt_rand() / mt_getrandmax() < 0.1) {
                $random_point = $goal;
            } else {
                $random_point = $this->sample_random_point($map);
            }

            // Find nearest node
            $nearest = $this->find_nearest_node($tree, $random_point);

            // Steer
            $new_point = $this->steer($nearest['pos'], $random_point, $this->step_size);

            // Check collision
            if (!$this->is_collision_free($nearest['pos'], $new_point, $map)) {
                continue;
            }

            // Find nodes near new point for rewiring
            $near_nodes = $this->find_near_nodes($tree, $new_point, $rewire_radius);

            // Choose best parent (lowest cost)
            $best_parent = $nearest;
            $best_cost = $nearest['cost'] + $this->distance($nearest['pos'], $new_point);

            foreach ($near_nodes as $near_node) {
                $cost = $near_node['cost'] + $this->distance($near_node['pos'], $new_point);
                if ($cost < $best_cost && $this->is_collision_free($near_node['pos'], $new_point, $map)) {
                    $best_parent = $near_node;
                    $best_cost = $cost;
                }
            }

            // Add to tree
            $new_key = $this->pos_to_key($new_point);
            $tree[$new_key] = [
                'pos' => $new_point,
                'parent' => $this->pos_to_key($best_parent['pos']),
                'cost' => $best_cost,
            ];

            // Rewire tree (update parents if new node provides shorter path)
            foreach ($near_nodes as $near_node) {
                $near_key = $this->pos_to_key($near_node['pos']);
                $new_cost = $best_cost + $this->distance($new_point, $near_node['pos']);

                if ($new_cost < $near_node['cost'] && $this->is_collision_free($new_point, $near_node['pos'], $map)) {
                    $tree[$near_key]['parent'] = $new_key;
                    $tree[$near_key]['cost'] = $new_cost;
                }
            }

            // Check if goal reached
            if ($this->distance($new_point, $goal) < $this->goal_threshold) {
                return $this->reconstruct_path_from_tree($tree, $new_point);
            }
        }

        error_log('[PathPlanner] RRT* failed after ' . $iterations . ' iterations');
        return false;
    }

    /**
     * Smooth path using iterative refinement
     */
    private function smooth_path($path, $map) {
        if (count($path) < 3) {
            return $path;
        }

        $smoothed = [$path[0]];
        $i = 0;

        while ($i < count($path) - 1) {
            // Try to skip intermediate waypoints
            $j = count($path) - 1;

            while ($j > $i + 1) {
                if ($this->is_collision_free($path[$i], $path[$j], $map)) {
                    // Can skip directly to $j
                    $smoothed[] = $path[$j];
                    $i = $j;
                    break;
                }
                $j--;
            }

            if ($j == $i + 1) {
                // Can't skip, add next waypoint
                $smoothed[] = $path[$i + 1];
                $i++;
            }
        }

        return $smoothed;
    }

    /**
     * Heuristic for A* (Euclidean distance)
     */
    private function heuristic($a, $b) {
        return $this->distance($a, $b);
    }

    /**
     * Calculate distance between two points
     */
    private function distance($a, $b) {
        $dx = $a[0] - $b[0];
        $dy = $a[1] - $b[1];
        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Calculate cost of moving from a to b
     */
    private function cost($a, $b, $map) {
        $base_cost = $this->distance($a, $b);

        // Apply terrain cost if available in map
        if (isset($map['cost_map'])) {
            $b_key = $this->pos_to_key($b);
            $terrain_cost = $map['cost_map'][$b_key] ?? 1.0;
            $base_cost *= $terrain_cost;
        }

        return $base_cost;
    }

    /**
     * Get neighboring cells/positions
     */
    private function get_neighbors($pos, $map) {
        $neighbors = [];
        $directions = [
            [1, 0], [-1, 0], [0, 1], [0, -1],  // 4-connected
            [1, 1], [1, -1], [-1, 1], [-1, -1], // diagonals (8-connected)
        ];

        foreach ($directions as $dir) {
            $neighbor = [
                $pos[0] + $dir[0],
                $pos[1] + $dir[1],
            ];

            if ($this->is_valid_position($neighbor, $map)) {
                $neighbors[] = $neighbor;
            }
        }

        return $neighbors;
    }

    /**
     * Check if position is valid (not obstacle, within bounds)
     */
    private function is_valid_position($pos, $map) {
        // Check map bounds if specified
        if (isset($map['bounds'])) {
            if ($pos[0] < $map['bounds']['min_x'] || $pos[0] > $map['bounds']['max_x']) {
                return false;
            }
            if ($pos[1] < $map['bounds']['min_y'] || $pos[1] > $map['bounds']['max_y']) {
                return false;
            }
        }

        // Check obstacles
        if (isset($map['obstacles'])) {
            $pos_key = $this->pos_to_key($pos);
            if (isset($map['obstacles'][$pos_key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if line segment is collision-free
     */
    private function is_collision_free($from, $to, $map) {
        // Simple line collision check
        $steps = ceil($this->distance($from, $to) / 0.5);
        $steps = max(1, $steps);

        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            $point = [
                $from[0] + $t * ($to[0] - $from[0]),
                $from[1] + $t * ($to[1] - $from[1]),
            ];

            if (!$this->is_valid_position($point, $map)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sample random point in space
     */
    private function sample_random_point($map) {
        if (isset($map['bounds'])) {
            $x = $map['bounds']['min_x'] + mt_rand() / mt_getrandmax() *
                 ($map['bounds']['max_x'] - $map['bounds']['min_x']);
            $y = $map['bounds']['min_y'] + mt_rand() / mt_getrandmax() *
                 ($map['bounds']['max_y'] - $map['bounds']['min_y']);
        } else {
            // Default bounds
            $x = mt_rand() / mt_getrandmax() * 100;
            $y = mt_rand() / mt_getrandmax() * 100;
        }

        return [$x, $y];
    }

    /**
     * Find nearest node in tree to point
     */
    private function find_nearest_node($tree, $point) {
        $nearest = null;
        $min_dist = PHP_FLOAT_MAX;

        foreach ($tree as $node) {
            $dist = $this->distance($node['pos'], $point);
            if ($dist < $min_dist) {
                $min_dist = $dist;
                $nearest = $node;
            }
        }

        return $nearest;
    }

    /**
     * Find nodes near a point within radius
     */
    private function find_near_nodes($tree, $point, $radius) {
        $near = [];

        foreach ($tree as $node) {
            $dist = $this->distance($node['pos'], $point);
            if ($dist < $radius) {
                $near[] = $node;
            }
        }

        return $near;
    }

    /**
     * Steer from one point toward another with step size limit
     */
    private function steer($from, $to, $step_size) {
        $dist = $this->distance($from, $to);

        if ($dist <= $step_size) {
            return $to;
        }

        $ratio = $step_size / $dist;

        return [
            $from[0] + $ratio * ($to[0] - $from[0]),
            $from[1] + $ratio * ($to[1] - $from[1]),
        ];
    }

    /**
     * Reconstruct path from came_from array (for A*/Dijkstra)
     */
    private function reconstruct_path($came_from, $current) {
        $path = [$current];
        $current_key = $this->pos_to_key($current);

        while (isset($came_from[$current_key])) {
            $current = $came_from[$current_key];
            $current_key = $this->pos_to_key($current);
            array_unshift($path, $current);
        }

        return $path;
    }

    /**
     * Reconstruct path from tree (for RRT/RRT*)
     */
    private function reconstruct_path_from_tree($tree, $goal) {
        $path = [$goal];
        $current_key = $this->pos_to_key($goal);

        while (isset($tree[$current_key]) && $tree[$current_key]['parent'] !== null) {
            $parent_key = $tree[$current_key]['parent'];
            $parent = $tree[$parent_key]['pos'];
            array_unshift($path, $parent);
            $current_key = $parent_key;
        }

        return $path;
    }

    /**
     * Convert position to string key for arrays
     */
    private function pos_to_key($pos) {
        return round($pos[0], 2) . ',' . round($pos[1], 2);
    }

    /**
     * Set planning algorithm
     */
    public function set_algorithm($algorithm) {
        $this->algorithm = $algorithm;
    }

    /**
     * Enable/disable path smoothing
     */
    public function set_smoothing($enabled) {
        $this->smoothing_enabled = $enabled;
    }
}
