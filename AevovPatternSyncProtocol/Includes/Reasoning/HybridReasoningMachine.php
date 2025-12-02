<?php
/**
 * Hybrid Reasoning Machine - Forward and Backward Chaining
 *
 * Implements a production system with both data-driven (forward chaining)
 * and goal-driven (backward chaining) reasoning strategies.
 *
 * @package AevovPatternSyncProtocol
 * @subpackage Reasoning
 * @since 1.0.0
 */

namespace APS\Reasoning;

use APS\Core\Logger;

class HybridReasoningMachine {

    /**
     * Working memory - facts and inferred data
     *
     * @var array
     */
    private $workingMemory;

    /**
     * Rule base - production rules
     *
     * @var array
     */
    private $ruleBase;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Conflict resolution strategy
     *
     * @var string
     */
    private $conflictStrategy;

    /**
     * Inference trace for debugging
     *
     * @var array
     */
    private $inferenceTrace;

    /**
     * Maximum reasoning depth to prevent infinite loops
     *
     * @var int
     */
    private $maxDepth;

    /**
     * Constructor
     *
     * @param array $initial_facts Initial working memory facts
     * @param string $conflict_strategy Conflict resolution strategy (specificity|recency|simplicity)
     * @param int $max_depth Maximum reasoning depth
     */
    public function __construct($initial_facts = [], $conflict_strategy = 'specificity', $max_depth = 100) {
        $this->workingMemory = $initial_facts;
        $this->ruleBase = [];
        $this->logger = Logger::get_instance();
        $this->conflictStrategy = $conflict_strategy;
        $this->inferenceTrace = [];
        $this->maxDepth = $max_depth;
    }

    /**
     * Add rule to rule base
     *
     * @param array $rule Rule definition with 'conditions', 'action', 'priority', 'name'
     * @return void
     */
    public function addRule($rule) {
        if (!isset($rule['conditions']) || !isset($rule['action'])) {
            $this->logger->log('error', 'Invalid rule structure', ['rule' => $rule]);
            return;
        }

        // Add default values
        $rule['priority'] = $rule['priority'] ?? 0;
        $rule['name'] = $rule['name'] ?? 'rule_' . count($this->ruleBase);
        $rule['specificity'] = count($rule['conditions']);
        $rule['timestamp'] = time();

        $this->ruleBase[] = $rule;

        $this->logger->log('info', 'Rule added to rule base', ['rule_name' => $rule['name']]);
    }

    /**
     * Add fact to working memory
     *
     * @param string $key Fact key
     * @param mixed $value Fact value
     * @return void
     */
    public function addFact($key, $value) {
        $this->workingMemory[$key] = [
            'value' => $value,
            'timestamp' => time(),
            'source' => 'user'
        ];

        $this->logger->log('debug', 'Fact added to working memory', ['key' => $key, 'value' => $value]);
    }

    /**
     * Forward chaining - data-driven reasoning
     *
     * Starts with known facts and applies rules to infer new facts
     * until no more rules can be applied.
     *
     * @return array Results of forward chaining
     */
    public function forwardChain() {
        $this->inferenceTrace = [];
        $depth = 0;
        $changed = true;

        $this->logger->log('info', 'Starting forward chaining');

        while ($changed && $depth < $this->maxDepth) {
            $changed = false;
            $depth++;

            // Get applicable rules
            $applicableRules = $this->getApplicableRules();

            if (empty($applicableRules)) {
                $this->logger->log('debug', 'No more applicable rules', ['depth' => $depth]);
                break;
            }

            // Resolve conflicts
            $selectedRule = $this->resolveConflict($applicableRules);

            if ($selectedRule) {
                // Apply rule
                $result = $this->applyRule($selectedRule);

                if ($result) {
                    $changed = true;
                    $this->inferenceTrace[] = [
                        'depth' => $depth,
                        'rule' => $selectedRule['name'],
                        'action' => $selectedRule['action'],
                        'result' => $result
                    ];

                    $this->logger->log('debug', 'Rule applied', [
                        'rule' => $selectedRule['name'],
                        'depth' => $depth
                    ]);
                }
            }
        }

        if ($depth >= $this->maxDepth) {
            $this->logger->log('warning', 'Forward chaining reached maximum depth', ['max_depth' => $this->maxDepth]);
        }

        $this->logger->log('info', 'Forward chaining completed', [
            'depth' => $depth,
            'inferences' => count($this->inferenceTrace)
        ]);

        return [
            'working_memory' => $this->workingMemory,
            'inferences' => $this->inferenceTrace,
            'depth' => $depth
        ];
    }

    /**
     * Backward chaining - goal-driven reasoning
     *
     * Starts with a goal and works backwards to find supporting facts
     *
     * @param string $goal Goal to prove
     * @param int $depth Current recursion depth
     * @return bool|array True if goal can be proven, false otherwise
     */
    public function backwardChain($goal, $depth = 0) {
        // Prevent infinite recursion
        if ($depth >= $this->maxDepth) {
            $this->logger->log('warning', 'Backward chaining reached maximum depth', [
                'goal' => $goal,
                'max_depth' => $this->maxDepth
            ]);
            return false;
        }

        $this->logger->log('debug', 'Backward chaining for goal', ['goal' => $goal, 'depth' => $depth]);

        // Check if goal is already in working memory
        if (isset($this->workingMemory[$goal])) {
            $this->logger->log('debug', 'Goal found in working memory', ['goal' => $goal]);
            return [
                'proven' => true,
                'value' => $this->workingMemory[$goal]['value'],
                'path' => ['fact']
            ];
        }

        // Find rules that can prove this goal
        $rulesForGoal = $this->getRulesForGoal($goal);

        if (empty($rulesForGoal)) {
            $this->logger->log('debug', 'No rules found for goal', ['goal' => $goal]);
            return false;
        }

        // Try each rule
        foreach ($rulesForGoal as $rule) {
            $allConditionsMet = true;
            $subgoalResults = [];

            // Check if all conditions can be proven
            foreach ($rule['conditions'] as $condition) {
                $conditionKey = $this->extractConditionKey($condition);

                if (!isset($this->workingMemory[$conditionKey])) {
                    // Try to prove this condition recursively
                    $subgoalResult = $this->backwardChain($conditionKey, $depth + 1);

                    if ($subgoalResult === false) {
                        $allConditionsMet = false;
                        break;
                    }

                    $subgoalResults[$conditionKey] = $subgoalResult;
                } else {
                    // Check if condition is satisfied
                    if (!$this->evaluateCondition($condition, $this->workingMemory[$conditionKey]['value'])) {
                        $allConditionsMet = false;
                        break;
                    }
                }
            }

            // If all conditions met, apply rule and prove goal
            if ($allConditionsMet) {
                $result = $this->applyRule($rule);

                if ($result) {
                    $this->logger->log('debug', 'Goal proven via rule', [
                        'goal' => $goal,
                        'rule' => $rule['name']
                    ]);

                    return [
                        'proven' => true,
                        'value' => $result,
                        'rule' => $rule['name'],
                        'subgoals' => $subgoalResults,
                        'path' => array_merge(['rule:' . $rule['name']], array_keys($subgoalResults))
                    ];
                }
            }
        }

        $this->logger->log('debug', 'Goal cannot be proven', ['goal' => $goal]);
        return false;
    }

    /**
     * Get rules that are currently applicable
     *
     * @return array Applicable rules
     */
    private function getApplicableRules() {
        $applicable = [];

        foreach ($this->ruleBase as $rule) {
            if ($this->isRuleApplicable($rule)) {
                $applicable[] = $rule;
            }
        }

        return $applicable;
    }

    /**
     * Check if a rule is applicable given current working memory
     *
     * @param array $rule Rule to check
     * @return bool True if rule is applicable
     */
    private function isRuleApplicable($rule) {
        foreach ($rule['conditions'] as $condition) {
            $conditionKey = $this->extractConditionKey($condition);

            if (!isset($this->workingMemory[$conditionKey])) {
                return false;
            }

            // Evaluate condition
            if (!$this->evaluateCondition($condition, $this->workingMemory[$conditionKey]['value'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Extract the key from a condition
     *
     * @param mixed $condition Condition (string or array)
     * @return string Condition key
     */
    private function extractConditionKey($condition) {
        if (is_string($condition)) {
            return $condition;
        }

        if (is_array($condition) && isset($condition['key'])) {
            return $condition['key'];
        }

        return '';
    }

    /**
     * Evaluate a condition against a value
     *
     * @param mixed $condition Condition definition
     * @param mixed $value Value to test
     * @return bool True if condition is satisfied
     */
    private function evaluateCondition($condition, $value) {
        // Simple string condition - just check existence
        if (is_string($condition)) {
            return true;
        }

        // Array condition with operator
        if (is_array($condition)) {
            $operator = $condition['operator'] ?? '=';
            $expected = $condition['value'] ?? null;

            switch ($operator) {
                case '=':
                case '==':
                    return $value == $expected;
                case '!=':
                    return $value != $expected;
                case '>':
                    return $value > $expected;
                case '<':
                    return $value < $expected;
                case '>=':
                    return $value >= $expected;
                case '<=':
                    return $value <= $expected;
                case 'contains':
                    return is_array($value) && in_array($expected, $value);
                case 'matches':
                    return preg_match($expected, $value);
                default:
                    return false;
            }
        }

        return false;
    }

    /**
     * Apply a rule and update working memory
     *
     * @param array $rule Rule to apply
     * @return mixed Result of rule application
     */
    private function applyRule($rule) {
        $action = $rule['action'];

        // Execute action
        if (is_callable($action)) {
            $result = call_user_func($action, $this->workingMemory);
        } elseif (is_array($action)) {
            // Array action: ['assert' => ['key' => 'value']]
            if (isset($action['assert'])) {
                foreach ($action['assert'] as $key => $value) {
                    $this->workingMemory[$key] = [
                        'value' => $value,
                        'timestamp' => time(),
                        'source' => 'inference',
                        'rule' => $rule['name']
                    ];
                }
                $result = $action['assert'];
            } else {
                $result = $action;
            }
        } else {
            $result = $action;
        }

        return $result;
    }

    /**
     * Resolve conflict among applicable rules
     *
     * @param array $rules Applicable rules
     * @return array|null Selected rule
     */
    private function resolveConflict($rules) {
        if (empty($rules)) {
            return null;
        }

        if (count($rules) === 1) {
            return $rules[0];
        }

        // Apply conflict resolution strategy
        switch ($this->conflictStrategy) {
            case 'specificity':
                // Choose rule with most conditions
                usort($rules, function($a, $b) {
                    return $b['specificity'] - $a['specificity'];
                });
                break;

            case 'recency':
                // Choose rule added most recently
                usort($rules, function($a, $b) {
                    return $b['timestamp'] - $a['timestamp'];
                });
                break;

            case 'simplicity':
                // Choose rule with fewest conditions
                usort($rules, function($a, $b) {
                    return $a['specificity'] - $b['specificity'];
                });
                break;

            case 'priority':
                // Choose rule with highest priority
                usort($rules, function($a, $b) {
                    return $b['priority'] - $a['priority'];
                });
                break;

            default:
                // Default to first rule
                break;
        }

        return $rules[0];
    }

    /**
     * Get rules that can prove a specific goal
     *
     * @param string $goal Goal to find rules for
     * @return array Rules that can prove the goal
     */
    private function getRulesForGoal($goal) {
        $rulesForGoal = [];

        foreach ($this->ruleBase as $rule) {
            // Check if rule's action asserts the goal
            if (is_array($rule['action']) && isset($rule['action']['assert'])) {
                if (array_key_exists($goal, $rule['action']['assert'])) {
                    $rulesForGoal[] = $rule;
                }
            }
        }

        return $rulesForGoal;
    }

    /**
     * Get working memory
     *
     * @return array Working memory
     */
    public function getWorkingMemory() {
        return $this->workingMemory;
    }

    /**
     * Get inference trace
     *
     * @return array Inference trace
     */
    public function getInferenceTrace() {
        return $this->inferenceTrace;
    }

    /**
     * Clear working memory
     *
     * @return void
     */
    public function clearWorkingMemory() {
        $this->workingMemory = [];
        $this->inferenceTrace = [];
        $this->logger->log('debug', 'Working memory cleared');
    }

    /**
     * Reset rule base
     *
     * @return void
     */
    public function clearRuleBase() {
        $this->ruleBase = [];
        $this->logger->log('debug', 'Rule base cleared');
    }

    /**
     * Get statistics about the reasoning process
     *
     * @return array Statistics
     */
    public function getStatistics() {
        return [
            'rules_count' => count($this->ruleBase),
            'facts_count' => count($this->workingMemory),
            'inferences_count' => count($this->inferenceTrace),
            'conflict_strategy' => $this->conflictStrategy,
            'max_depth' => $this->maxDepth
        ];
    }
}
