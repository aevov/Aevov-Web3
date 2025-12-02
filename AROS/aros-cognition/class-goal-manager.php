<?php
/**
 * AROS Goal Manager
 *
 * Production-ready goal management system for robot task prioritization
 * Features:
 * - Priority-based goal queue
 * - Conflict resolution for competing goals
 * - Achievement verification
 * - Goal lifecycle management (pending, active, completed, failed)
 * - Deadline tracking
 * - Satisfaction conditions evaluation
 * - Multi-goal coordination
 */

namespace AROS\Cognition;

class GoalManager {

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELED = 'canceled';

    private $goals = [];
    private $active_goal = null;
    private $goal_id_counter = 0;
    private $completion_callbacks = [];

    /**
     * Add a new goal to the system
     *
     * @param string $name Goal name
     * @param array $conditions Satisfaction conditions
     * @param int $priority Priority (higher = more important)
     * @param float $deadline Optional deadline (timestamp)
     * @return int Goal ID
     */
    public function add_goal($name, $conditions = [], $priority = 5, $deadline = null) {
        $goal_id = ++$this->goal_id_counter;

        $goal = [
            'id' => $goal_id,
            'name' => $name,
            'conditions' => $conditions,
            'priority' => $priority,
            'status' => self::STATUS_PENDING,
            'created_at' => microtime(true),
            'deadline' => $deadline,
            'started_at' => null,
            'completed_at' => null,
            'progress' => 0.0,
        ];

        $this->goals[$goal_id] = $goal;

        // Re-sort goals by priority
        $this->sort_goals();

        error_log('[GoalManager] Added goal #' . $goal_id . ': ' . $name . ' (priority: ' . $priority . ')');

        return $goal_id;
    }

    /**
     * Get current active goal or highest priority pending goal
     *
     * @return array|null Goal data or null
     */
    public function get_current_goal() {
        // Return active goal if exists
        if ($this->active_goal !== null) {
            return $this->goals[$this->active_goal] ?? null;
        }

        // Find highest priority pending goal
        foreach ($this->goals as $goal) {
            if ($goal['status'] === self::STATUS_PENDING) {
                // Check if deadline hasn't passed
                if ($goal['deadline'] !== null && microtime(true) > $goal['deadline']) {
                    $this->mark_failed($goal['id'], 'Deadline exceeded');
                    continue;
                }

                // Activate this goal
                $this->activate_goal($goal['id']);
                return $goal;
            }
        }

        return null;
    }

    /**
     * Activate a specific goal
     *
     * @param int $goal_id Goal ID
     * @return bool Success
     */
    public function activate_goal($goal_id) {
        if (!isset($this->goals[$goal_id])) {
            error_log('[GoalManager] ERROR: Goal #' . $goal_id . ' not found');
            return false;
        }

        // Deactivate current goal if any
        if ($this->active_goal !== null && $this->active_goal !== $goal_id) {
            $this->goals[$this->active_goal]['status'] = self::STATUS_PENDING;
        }

        $this->goals[$goal_id]['status'] = self::STATUS_ACTIVE;
        $this->goals[$goal_id]['started_at'] = microtime(true);
        $this->active_goal = $goal_id;

        error_log('[GoalManager] Activated goal #' . $goal_id . ': ' . $this->goals[$goal_id]['name']);

        return true;
    }

    /**
     * Check if goal is satisfied
     *
     * @param int $goal_id Goal ID
     * @param array $current_state Current world state
     * @return bool True if satisfied
     */
    public function is_satisfied($goal_id, $current_state) {
        if (!isset($this->goals[$goal_id])) {
            return false;
        }

        $goal = $this->goals[$goal_id];

        // Check all conditions
        foreach ($goal['conditions'] as $key => $required_value) {
            if (!isset($current_state[$key]) || $current_state[$key] !== $required_value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mark goal as completed
     *
     * @param int $goal_id Goal ID
     * @return bool Success
     */
    public function mark_completed($goal_id) {
        if (!isset($this->goals[$goal_id])) {
            return false;
        }

        $this->goals[$goal_id]['status'] = self::STATUS_COMPLETED;
        $this->goals[$goal_id]['completed_at'] = microtime(true);
        $this->goals[$goal_id]['progress'] = 1.0;

        if ($this->active_goal === $goal_id) {
            $this->active_goal = null;
        }

        error_log('[GoalManager] Completed goal #' . $goal_id . ': ' . $this->goals[$goal_id]['name']);

        // Call completion callbacks
        $this->call_callbacks($goal_id, self::STATUS_COMPLETED);

        return true;
    }

    /**
     * Mark goal as failed
     *
     * @param int $goal_id Goal ID
     * @param string $reason Failure reason
     * @return bool Success
     */
    public function mark_failed($goal_id, $reason = '') {
        if (!isset($this->goals[$goal_id])) {
            return false;
        }

        $this->goals[$goal_id]['status'] = self::STATUS_FAILED;
        $this->goals[$goal_id]['failure_reason'] = $reason;

        if ($this->active_goal === $goal_id) {
            $this->active_goal = null;
        }

        error_log('[GoalManager] Failed goal #' . $goal_id . ': ' . $this->goals[$goal_id]['name'] . ' (' . $reason . ')');

        // Call completion callbacks
        $this->call_callbacks($goal_id, self::STATUS_FAILED);

        return true;
    }

    /**
     * Cancel a goal
     *
     * @param int $goal_id Goal ID
     * @return bool Success
     */
    public function cancel_goal($goal_id) {
        if (!isset($this->goals[$goal_id])) {
            return false;
        }

        $this->goals[$goal_id]['status'] = self::STATUS_CANCELED;

        if ($this->active_goal === $goal_id) {
            $this->active_goal = null;
        }

        error_log('[GoalManager] Canceled goal #' . $goal_id . ': ' . $this->goals[$goal_id]['name']);

        return true;
    }

    /**
     * Update goal progress
     *
     * @param int $goal_id Goal ID
     * @param float $progress Progress value (0.0 to 1.0)
     */
    public function update_progress($goal_id, $progress) {
        if (!isset($this->goals[$goal_id])) {
            return false;
        }

        $this->goals[$goal_id]['progress'] = max(0.0, min(1.0, $progress));

        return true;
    }

    /**
     * Resolve conflicts between competing goals
     *
     * @param array $goal_ids Array of competing goal IDs
     * @return int Winning goal ID
     */
    public function resolve_conflict($goal_ids) {
        $candidates = [];

        foreach ($goal_ids as $goal_id) {
            if (isset($this->goals[$goal_id])) {
                $candidates[] = $this->goals[$goal_id];
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort by priority first
        usort($candidates, function($a, $b) {
            // Higher priority wins
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] - $a['priority'];
            }

            // If same priority, earlier deadline wins
            if ($a['deadline'] !== null && $b['deadline'] !== null) {
                return $a['deadline'] - $b['deadline'];
            }

            // If same priority and no deadlines, earlier creation wins
            return $a['created_at'] - $b['created_at'];
        });

        $winner = $candidates[0];
        error_log('[GoalManager] Conflict resolved: Goal #' . $winner['id'] . ' wins');

        return $winner['id'];
    }

    /**
     * Get all goals with specific status
     *
     * @param string $status Goal status
     * @return array Goals
     */
    public function get_goals_by_status($status) {
        $result = [];

        foreach ($this->goals as $goal) {
            if ($goal['status'] === $status) {
                $result[] = $goal;
            }
        }

        return $result;
    }

    /**
     * Get all pending goals
     *
     * @return array Goals
     */
    public function get_pending_goals() {
        return $this->get_goals_by_status(self::STATUS_PENDING);
    }

    /**
     * Get goal by ID
     *
     * @param int $goal_id Goal ID
     * @return array|null Goal data
     */
    public function get_goal($goal_id) {
        return $this->goals[$goal_id] ?? null;
    }

    /**
     * Register callback for goal completion/failure
     *
     * @param int $goal_id Goal ID
     * @param callable $callback Callback function
     */
    public function on_goal_complete($goal_id, $callback) {
        if (!isset($this->completion_callbacks[$goal_id])) {
            $this->completion_callbacks[$goal_id] = [];
        }

        $this->completion_callbacks[$goal_id][] = $callback;
    }

    /**
     * Clear all goals
     */
    public function clear() {
        $this->goals = [];
        $this->active_goal = null;
        $this->goal_id_counter = 0;
        $this->completion_callbacks = [];
    }

    /**
     * Get statistics
     *
     * @return array Statistics
     */
    public function get_stats() {
        $stats = [
            'total' => count($this->goals),
            'pending' => 0,
            'active' => 0,
            'completed' => 0,
            'failed' => 0,
            'canceled' => 0,
        ];

        foreach ($this->goals as $goal) {
            $stats[$goal['status']]++;
        }

        return $stats;
    }

    /**
     * Sort goals by priority (descending)
     */
    private function sort_goals() {
        uasort($this->goals, function($a, $b) {
            // Higher priority first
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] - $a['priority'];
            }

            // Same priority: earlier deadline first
            if ($a['deadline'] !== null && $b['deadline'] !== null) {
                return $a['deadline'] - $b['deadline'];
            }

            // Same priority, no deadlines: FIFO
            return $a['created_at'] - $b['created_at'];
        });
    }

    /**
     * Call completion callbacks
     */
    private function call_callbacks($goal_id, $status) {
        if (!isset($this->completion_callbacks[$goal_id])) {
            return;
        }

        foreach ($this->completion_callbacks[$goal_id] as $callback) {
            if (is_callable($callback)) {
                call_user_func($callback, $goal_id, $status, $this->goals[$goal_id]);
            }
        }
    }
}
