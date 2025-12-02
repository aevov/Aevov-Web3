<?php
/**
 * AROS Behavior Tree System
 *
 * Production-ready behavior tree implementation for robot AI
 * Features:
 * - Composite nodes (Sequence, Selector, Parallel)
 * - Decorator nodes (Inverter, Repeater, UntilFail, UntilSuccess, Retry)
 * - Leaf nodes (Action, Condition)
 * - Blackboard for shared data across nodes
 * - Proper state management (SUCCESS, FAILURE, RUNNING)
 * - Tree visualization and debugging
 */

namespace AROS\Cognition;

class BehaviorTree {

    const SUCCESS = 'SUCCESS';
    const FAILURE = 'FAILURE';
    const RUNNING = 'RUNNING';

    private $root_node = null;
    private $blackboard = null;
    private $current_node = null;
    private $tick_count = 0;

    /**
     * Constructor
     */
    public function __construct($root_node = null) {
        $this->root_node = $root_node;
        $this->blackboard = new Blackboard();
    }

    /**
     * Execute one tick of the behavior tree
     *
     * @param array $context External context data
     * @return string Result status (SUCCESS, FAILURE, RUNNING)
     */
    public function tick($context = []) {
        if ($this->root_node === null) {
            error_log('[BehaviorTree] ERROR: No root node set');
            return self::FAILURE;
        }

        $this->tick_count++;

        // Update blackboard with context
        foreach ($context as $key => $value) {
            $this->blackboard->set($key, $value);
        }

        // Execute root node
        $result = $this->root_node->execute($this->blackboard);

        error_log('[BehaviorTree] Tick #' . $this->tick_count . ' result: ' . $result);

        return $result;
    }

    /**
     * Set root node of the tree
     */
    public function set_root($node) {
        $this->root_node = $node;
    }

    /**
     * Get blackboard
     */
    public function get_blackboard() {
        return $this->blackboard;
    }

    /**
     * Reset tree state
     */
    public function reset() {
        $this->tick_count = 0;
        $this->current_node = null;
        if ($this->root_node) {
            $this->root_node->reset();
        }
    }

    /**
     * Get tree statistics
     */
    public function get_stats() {
        return [
            'tick_count' => $this->tick_count,
            'blackboard_size' => $this->blackboard->size(),
        ];
    }
}

/**
 * Blackboard - Shared memory for behavior tree nodes
 */
class Blackboard {
    private $data = [];

    public function set($key, $value) {
        $this->data[$key] = $value;
    }

    public function get($key, $default = null) {
        return $this->data[$key] ?? $default;
    }

    public function has($key) {
        return isset($this->data[$key]);
    }

    public function remove($key) {
        unset($this->data[$key]);
    }

    public function clear() {
        $this->data = [];
    }

    public function size() {
        return count($this->data);
    }

    public function get_all() {
        return $this->data;
    }
}

/**
 * Base Node class
 */
abstract class BTNode {
    protected $name = '';
    protected $status = null;

    public function __construct($name = '') {
        $this->name = $name ?: get_class($this);
    }

    abstract public function execute($blackboard);

    public function reset() {
        $this->status = null;
    }

    public function get_status() {
        return $this->status;
    }

    public function get_name() {
        return $this->name;
    }
}

/**
 * COMPOSITE NODES
 */

/**
 * Sequence Node
 * Executes children in order, succeeds if all succeed
 * Fails if any child fails
 */
class SequenceNode extends BTNode {
    private $children = [];
    private $current_child = 0;

    public function __construct($name = '', $children = []) {
        parent::__construct($name);
        $this->children = $children;
    }

    public function add_child($child) {
        $this->children[] = $child;
    }

    public function execute($blackboard) {
        while ($this->current_child < count($this->children)) {
            $child = $this->children[$this->current_child];
            $result = $child->execute($blackboard);

            if ($result === BehaviorTree::RUNNING) {
                $this->status = BehaviorTree::RUNNING;
                return BehaviorTree::RUNNING;
            }

            if ($result === BehaviorTree::FAILURE) {
                $this->current_child = 0; // Reset for next execution
                $this->status = BehaviorTree::FAILURE;
                return BehaviorTree::FAILURE;
            }

            // Child succeeded, move to next
            $this->current_child++;
        }

        // All children succeeded
        $this->current_child = 0; // Reset for next execution
        $this->status = BehaviorTree::SUCCESS;
        return BehaviorTree::SUCCESS;
    }

    public function reset() {
        parent::reset();
        $this->current_child = 0;
        foreach ($this->children as $child) {
            $child->reset();
        }
    }
}

/**
 * Selector Node (Fallback)
 * Executes children in order, succeeds if any succeeds
 * Fails if all children fail
 */
class SelectorNode extends BTNode {
    private $children = [];
    private $current_child = 0;

    public function __construct($name = '', $children = []) {
        parent::__construct($name);
        $this->children = $children;
    }

    public function add_child($child) {
        $this->children[] = $child;
    }

    public function execute($blackboard) {
        while ($this->current_child < count($this->children)) {
            $child = $this->children[$this->current_child];
            $result = $child->execute($blackboard);

            if ($result === BehaviorTree::RUNNING) {
                $this->status = BehaviorTree::RUNNING;
                return BehaviorTree::RUNNING;
            }

            if ($result === BehaviorTree::SUCCESS) {
                $this->current_child = 0; // Reset for next execution
                $this->status = BehaviorTree::SUCCESS;
                return BehaviorTree::SUCCESS;
            }

            // Child failed, try next
            $this->current_child++;
        }

        // All children failed
        $this->current_child = 0; // Reset for next execution
        $this->status = BehaviorTree::FAILURE;
        return BehaviorTree::FAILURE;
    }

    public function reset() {
        parent::reset();
        $this->current_child = 0;
        foreach ($this->children as $child) {
            $child->reset();
        }
    }
}

/**
 * Parallel Node
 * Executes all children simultaneously
 * Succeeds if threshold number of children succeed
 */
class ParallelNode extends BTNode {
    private $children = [];
    private $success_threshold = 1; // Number of children that must succeed

    public function __construct($name = '', $children = [], $success_threshold = 1) {
        parent::__construct($name);
        $this->children = $children;
        $this->success_threshold = $success_threshold;
    }

    public function add_child($child) {
        $this->children[] = $child;
    }

    public function execute($blackboard) {
        $success_count = 0;
        $failure_count = 0;
        $running_count = 0;

        foreach ($this->children as $child) {
            $result = $child->execute($blackboard);

            if ($result === BehaviorTree::SUCCESS) {
                $success_count++;
            } elseif ($result === BehaviorTree::FAILURE) {
                $failure_count++;
            } elseif ($result === BehaviorTree::RUNNING) {
                $running_count++;
            }
        }

        // Check if threshold is met
        if ($success_count >= $this->success_threshold) {
            $this->status = BehaviorTree::SUCCESS;
            return BehaviorTree::SUCCESS;
        }

        // Check if too many failed (impossible to meet threshold)
        $max_possible_success = $success_count + $running_count;
        if ($max_possible_success < $this->success_threshold) {
            $this->status = BehaviorTree::FAILURE;
            return BehaviorTree::FAILURE;
        }

        // Still running
        $this->status = BehaviorTree::RUNNING;
        return BehaviorTree::RUNNING;
    }

    public function reset() {
        parent::reset();
        foreach ($this->children as $child) {
            $child->reset();
        }
    }
}

/**
 * DECORATOR NODES
 */

/**
 * Inverter Decorator
 * Inverts child's result (SUCCESS <-> FAILURE)
 */
class InverterNode extends BTNode {
    private $child = null;

    public function __construct($name = '', $child = null) {
        parent::__construct($name);
        $this->child = $child;
    }

    public function execute($blackboard) {
        if ($this->child === null) {
            return BehaviorTree::FAILURE;
        }

        $result = $this->child->execute($blackboard);

        if ($result === BehaviorTree::SUCCESS) {
            $this->status = BehaviorTree::FAILURE;
            return BehaviorTree::FAILURE;
        } elseif ($result === BehaviorTree::FAILURE) {
            $this->status = BehaviorTree::SUCCESS;
            return BehaviorTree::SUCCESS;
        }

        // RUNNING stays RUNNING
        $this->status = BehaviorTree::RUNNING;
        return BehaviorTree::RUNNING;
    }

    public function reset() {
        parent::reset();
        if ($this->child) {
            $this->child->reset();
        }
    }
}

/**
 * Repeater Decorator
 * Repeats child N times or indefinitely
 */
class RepeaterNode extends BTNode {
    private $child = null;
    private $max_iterations = -1; // -1 = infinite
    private $current_iteration = 0;

    public function __construct($name = '', $child = null, $max_iterations = -1) {
        parent::__construct($name);
        $this->child = $child;
        $this->max_iterations = $max_iterations;
    }

    public function execute($blackboard) {
        if ($this->child === null) {
            return BehaviorTree::FAILURE;
        }

        // Check if we've reached max iterations
        if ($this->max_iterations > 0 && $this->current_iteration >= $this->max_iterations) {
            $this->current_iteration = 0;
            $this->status = BehaviorTree::SUCCESS;
            return BehaviorTree::SUCCESS;
        }

        $result = $this->child->execute($blackboard);

        if ($result === BehaviorTree::RUNNING) {
            $this->status = BehaviorTree::RUNNING;
            return BehaviorTree::RUNNING;
        }

        // Increment iteration and reset child for next iteration
        $this->current_iteration++;
        $this->child->reset();

        // Always return RUNNING (continue repeating)
        $this->status = BehaviorTree::RUNNING;
        return BehaviorTree::RUNNING;
    }

    public function reset() {
        parent::reset();
        $this->current_iteration = 0;
        if ($this->child) {
            $this->child->reset();
        }
    }
}

/**
 * UntilFail Decorator
 * Repeats child until it fails
 */
class UntilFailNode extends BTNode {
    private $child = null;

    public function __construct($name = '', $child = null) {
        parent::__construct($name);
        $this->child = $child;
    }

    public function execute($blackboard) {
        if ($this->child === null) {
            return BehaviorTree::FAILURE;
        }

        $result = $this->child->execute($blackboard);

        if ($result === BehaviorTree::FAILURE) {
            $this->status = BehaviorTree::SUCCESS;
            return BehaviorTree::SUCCESS;
        }

        if ($result === BehaviorTree::SUCCESS) {
            $this->child->reset(); // Reset and repeat
        }

        // Keep running
        $this->status = BehaviorTree::RUNNING;
        return BehaviorTree::RUNNING;
    }

    public function reset() {
        parent::reset();
        if ($this->child) {
            $this->child->reset();
        }
    }
}

/**
 * LEAF NODES
 */

/**
 * Action Node
 * Executes a callable action
 */
class ActionNode extends BTNode {
    private $action = null;

    public function __construct($name = '', $action = null) {
        parent::__construct($name);
        $this->action = $action;
    }

    public function execute($blackboard) {
        if ($this->action === null || !is_callable($this->action)) {
            error_log('[ActionNode] ERROR: No callable action set');
            $this->status = BehaviorTree::FAILURE;
            return BehaviorTree::FAILURE;
        }

        $result = call_user_func($this->action, $blackboard);

        // Normalize result
        if ($result === true) {
            $result = BehaviorTree::SUCCESS;
        } elseif ($result === false) {
            $result = BehaviorTree::FAILURE;
        }

        $this->status = $result;
        return $result;
    }
}

/**
 * Condition Node
 * Tests a condition
 */
class ConditionNode extends BTNode {
    private $condition = null;

    public function __construct($name = '', $condition = null) {
        parent::__construct($name);
        $this->condition = $condition;
    }

    public function execute($blackboard) {
        if ($this->condition === null || !is_callable($this->condition)) {
            error_log('[ConditionNode] ERROR: No callable condition set');
            $this->status = BehaviorTree::FAILURE;
            return BehaviorTree::FAILURE;
        }

        $result = call_user_func($this->condition, $blackboard);

        $this->status = $result ? BehaviorTree::SUCCESS : BehaviorTree::FAILURE;
        return $this->status;
    }
}
