<?php
/**
 * AROS Task Planner
 *
 * Hierarchical Task Network (HTN) planner with STRIPS-style planning
 * Features:
 * - HTN hierarchical task decomposition
 * - STRIPS precondition/effect modeling
 * - A* search for optimal action sequences
 * - Multi-goal prioritization
 * - Resource constraint management
 * - Plan repair and replanning
 * - Temporal constraint handling
 */

namespace AROS\Cognition;

class TaskPlanner {

    private $methods = []; // HTN methods for task decomposition
    private $operators = []; // Primitive actions (STRIPS operators)
    private $heuristics = []; // Domain-specific heuristics
    private $max_depth = 10; // Maximum planning depth
    private $max_iterations = 1000; // Maximum A* iterations

    /**
     * Constructor
     */
    public function __construct($config = []) {
        $this->max_depth = $config['max_depth'] ?? 10;
        $this->max_iterations = $config['max_iterations'] ?? 1000;

        // Initialize default operators and methods
        $this->initialize_default_operators();
        $this->initialize_default_methods();
    }

    /**
     * Plan a sequence of actions to achieve a goal
     *
     * @param array $goal Goal state to achieve
     * @param array $initial_state Current state
     * @param array $constraints Resource and temporal constraints
     * @return array|false Plan array or false on failure
     */
    public function plan($goal, $initial_state = [], $constraints = []) {
        error_log('[TaskPlanner] Planning for goal: ' . json_encode($goal));

        // Validate inputs
        if (empty($goal)) {
            error_log('[TaskPlanner] ERROR: Empty goal provided');
            return false;
        }

        // Try HTN planning first (more efficient for complex tasks)
        $htn_plan = $this->htn_plan($goal, $initial_state, $constraints);

        if ($htn_plan !== false) {
            error_log('[TaskPlanner] HTN planning succeeded with ' . count($htn_plan) . ' steps');
            return $htn_plan;
        }

        // Fallback to A* STRIPS planning for primitive goals
        error_log('[TaskPlanner] HTN failed, falling back to A* STRIPS planning');
        $strips_plan = $this->astar_plan($goal, $initial_state, $constraints);

        if ($strips_plan !== false) {
            error_log('[TaskPlanner] A* planning succeeded with ' . count($strips_plan) . ' steps');
            return $strips_plan;
        }

        error_log('[TaskPlanner] ERROR: No plan found');
        return false;
    }

    /**
     * HTN (Hierarchical Task Network) planning
     * Decomposes complex tasks into primitive actions
     *
     * @param array $tasks Tasks to accomplish
     * @param array $state Current state
     * @param array $constraints Constraints
     * @param int $depth Current recursion depth
     * @return array|false Plan or false
     */
    private function htn_plan($tasks, $state, $constraints = [], $depth = 0) {
        // Depth limit check
        if ($depth >= $this->max_depth) {
            return false;
        }

        // Base case: no tasks to accomplish
        if (empty($tasks)) {
            return [];
        }

        // Get first task
        $task = is_array($tasks) ? reset($tasks) : $tasks;
        $remaining_tasks = is_array($tasks) ? array_slice($tasks, 1) : [];

        // Check if task is primitive (can be executed directly)
        if ($this->is_primitive($task)) {
            // Find applicable operator
            $operator = $this->find_operator($task);

            if ($operator && $this->preconditions_met($operator, $state)) {
                // Apply operator effects
                $new_state = $this->apply_effects($operator, $state);

                // Check constraints
                if (!$this->check_constraints($operator, $new_state, $constraints)) {
                    return false;
                }

                // Plan for remaining tasks
                $rest_plan = $this->htn_plan($remaining_tasks, $new_state, $constraints, $depth);

                if ($rest_plan !== false) {
                    return array_merge([$operator], $rest_plan);
                }
            }

            return false;
        }

        // Task is compound - try decomposition methods
        $methods = $this->find_methods($task);

        foreach ($methods as $method) {
            // Check if method is applicable
            if (!$this->method_applicable($method, $state)) {
                continue;
            }

            // Decompose task into subtasks
            $subtasks = $this->decompose($task, $method, $state);

            // Recursively plan for subtasks + remaining tasks
            $combined_tasks = array_merge($subtasks, $remaining_tasks);
            $plan = $this->htn_plan($combined_tasks, $state, $constraints, $depth + 1);

            if ($plan !== false) {
                return $plan;
            }
        }

        return false;
    }

    /**
     * A* STRIPS planning for primitive goals
     *
     * @param array $goal Goal state
     * @param array $initial_state Initial state
     * @param array $constraints Constraints
     * @return array|false Plan or false
     */
    private function astar_plan($goal, $initial_state, $constraints = []) {
        // Initialize open and closed sets
        $open = new \SplPriorityQueue();
        $open->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);

        $closed = [];

        // Initial node
        $start_node = [
            'state' => $initial_state,
            'plan' => [],
            'g_cost' => 0, // Cost from start
            'h_cost' => $this->heuristic($initial_state, $goal), // Estimated cost to goal
        ];
        $start_node['f_cost'] = $start_node['g_cost'] + $start_node['h_cost'];

        // Priority is negative f_cost (lower is better)
        $open->insert($start_node, -$start_node['f_cost']);

        $iterations = 0;

        while (!$open->isEmpty() && $iterations < $this->max_iterations) {
            $iterations++;

            // Get node with lowest f_cost
            $current_data = $open->extract();
            $current = $current_data['data'];

            // Goal check
            if ($this->goal_satisfied($current['state'], $goal)) {
                error_log('[TaskPlanner] A* found solution in ' . $iterations . ' iterations');
                return $current['plan'];
            }

            // Mark as visited
            $state_hash = $this->hash_state($current['state']);
            if (isset($closed[$state_hash])) {
                continue;
            }
            $closed[$state_hash] = true;

            // Expand node - try all applicable operators
            foreach ($this->operators as $operator) {
                if (!$this->preconditions_met($operator, $current['state'])) {
                    continue;
                }

                // Apply operator
                $new_state = $this->apply_effects($operator, $current['state']);

                // Check constraints
                if (!$this->check_constraints($operator, $new_state, $constraints)) {
                    continue;
                }

                // Create successor node
                $successor = [
                    'state' => $new_state,
                    'plan' => array_merge($current['plan'], [$operator]),
                    'g_cost' => $current['g_cost'] + $this->action_cost($operator),
                    'h_cost' => $this->heuristic($new_state, $goal),
                ];
                $successor['f_cost'] = $successor['g_cost'] + $successor['h_cost'];

                // Add to open set
                $new_state_hash = $this->hash_state($new_state);
                if (!isset($closed[$new_state_hash])) {
                    $open->insert($successor, -$successor['f_cost']);
                }
            }
        }

        error_log('[TaskPlanner] A* failed after ' . $iterations . ' iterations');
        return false;
    }

    /**
     * Replan when original plan fails
     *
     * @param array $failed_action Action that failed
     * @param array $partial_plan Actions executed so far
     * @param array $original_goal Original goal
     * @param array $current_state Current state
     * @return array|false New plan or false
     */
    public function replan($failed_action, $partial_plan, $original_goal, $current_state) {
        error_log('[TaskPlanner] Replanning after failure: ' . json_encode($failed_action));

        // Try to find alternative plan from current state
        $new_plan = $this->plan($original_goal, $current_state);

        if ($new_plan !== false) {
            error_log('[TaskPlanner] Replanning successful');
            return $new_plan;
        }

        // Try relaxing constraints if available
        error_log('[TaskPlanner] Replanning failed');
        return false;
    }

    /**
     * Decompose complex task using a method
     */
    private function decompose($task, $method, $state) {
        // Call method's decomposition function
        if (isset($method['decompose']) && is_callable($method['decompose'])) {
            return call_user_func($method['decompose'], $task, $state);
        }

        return $method['subtasks'] ?? [];
    }

    /**
     * Check if task is primitive (executable)
     */
    private function is_primitive($task) {
        if (is_string($task)) {
            return true; // Simple task name
        }

        if (is_array($task) && isset($task['type'])) {
            return $task['type'] === 'primitive';
        }

        return false;
    }

    /**
     * Find operator for primitive task
     */
    private function find_operator($task) {
        $task_name = is_string($task) ? $task : ($task['name'] ?? '');

        foreach ($this->operators as $operator) {
            if ($operator['name'] === $task_name) {
                return $operator;
            }
        }

        return null;
    }

    /**
     * Find decomposition methods for compound task
     */
    private function find_methods($task) {
        $task_name = is_string($task) ? $task : ($task['name'] ?? '');

        $applicable = [];
        foreach ($this->methods as $method) {
            if ($method['task'] === $task_name) {
                $applicable[] = $method;
            }
        }

        return $applicable;
    }

    /**
     * Check if method is applicable in current state
     */
    private function method_applicable($method, $state) {
        if (!isset($method['preconditions'])) {
            return true;
        }

        foreach ($method['preconditions'] as $key => $value) {
            if (!isset($state[$key]) || $state[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if preconditions are met
     */
    private function preconditions_met($operator, $state) {
        if (!isset($operator['preconditions'])) {
            return true;
        }

        foreach ($operator['preconditions'] as $key => $value) {
            if (!isset($state[$key]) || $state[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply operator effects to state
     */
    private function apply_effects($operator, $state) {
        $new_state = $state;

        if (isset($operator['effects'])) {
            foreach ($operator['effects'] as $key => $value) {
                $new_state[$key] = $value;
            }
        }

        return $new_state;
    }

    /**
     * Check resource and temporal constraints
     */
    private function check_constraints($operator, $state, $constraints) {
        // Resource constraints
        if (isset($constraints['resources'])) {
            foreach ($constraints['resources'] as $resource => $limit) {
                $usage = $state[$resource] ?? 0;
                if ($usage > $limit) {
                    return false;
                }
            }
        }

        // Temporal constraints
        if (isset($constraints['deadline'])) {
            $current_time = $state['time'] ?? 0;
            $action_duration = $operator['duration'] ?? 1;

            if ($current_time + $action_duration > $constraints['deadline']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if goal is satisfied
     */
    private function goal_satisfied($state, $goal) {
        foreach ($goal as $key => $value) {
            if (!isset($state[$key]) || $state[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Heuristic function for A* (estimated cost to goal)
     * Uses Hamming distance by default
     */
    private function heuristic($state, $goal) {
        $distance = 0;

        foreach ($goal as $key => $value) {
            if (!isset($state[$key]) || $state[$key] !== $value) {
                $distance++;
            }
        }

        return $distance;
    }

    /**
     * Hash state for comparison
     */
    private function hash_state($state) {
        ksort($state);
        return md5(json_encode($state));
    }

    /**
     * Get cost of action (default: 1)
     */
    private function action_cost($operator) {
        return $operator['cost'] ?? 1;
    }

    /**
     * Register HTN method
     */
    public function register_method($task_name, $preconditions, $subtasks, $decompose_fn = null) {
        $this->methods[] = [
            'task' => $task_name,
            'preconditions' => $preconditions,
            'subtasks' => $subtasks,
            'decompose' => $decompose_fn,
        ];
    }

    /**
     * Register STRIPS operator
     */
    public function register_operator($name, $preconditions, $effects, $cost = 1, $duration = 1) {
        $this->operators[] = [
            'name' => $name,
            'preconditions' => $preconditions,
            'effects' => $effects,
            'cost' => $cost,
            'duration' => $duration,
        ];
    }

    /**
     * Initialize default operators for robot tasks
     */
    private function initialize_default_operators() {
        // Movement
        $this->register_operator('move',
            ['robot_state' => 'idle'],
            ['robot_state' => 'moving', 'position_changed' => true],
            2, 5
        );

        $this->register_operator('stop',
            ['robot_state' => 'moving'],
            ['robot_state' => 'idle'],
            1, 1
        );

        // Grasping
        $this->register_operator('grasp',
            ['gripper_state' => 'open', 'object_detected' => true],
            ['gripper_state' => 'closed', 'holding_object' => true],
            3, 2
        );

        $this->register_operator('release',
            ['gripper_state' => 'closed', 'holding_object' => true],
            ['gripper_state' => 'open', 'holding_object' => false],
            2, 1
        );

        // Sensing
        $this->register_operator('scan',
            ['robot_state' => 'idle'],
            ['environment_scanned' => true],
            1, 3
        );
    }

    /**
     * Initialize default HTN methods
     */
    private function initialize_default_methods() {
        // Pick-and-place decomposition
        $this->register_method('pick_and_place',
            [],
            ['move_to_object', 'grasp', 'move_to_target', 'release']
        );

        // Navigation decomposition
        $this->register_method('navigate',
            [],
            ['scan', 'plan_path', 'move', 'stop']
        );
    }
}
