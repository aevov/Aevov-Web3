<?php
/**
 * Unified Cognitive Engine - Complete Cognitive Architecture
 *
 * This is the PRIMARY cognitive system that implements dual-process theory,
 * working memory, long-term memory, attention mechanisms, and metacognition.
 *
 * Architecture based on:
 * - ACT-R (Adaptive Control of Thought-Rational)
 * - Baddeley's Working Memory Model
 * - Kahneman's System 1 & System 2
 * - Global Workspace Theory
 *
 * Native Components:
 * - WorkingMemory (capacity-limited, temporal decay)
 * - LongTermMemory (unlimited, semantic organization)
 * - AttentionMechanism (selective focus, saliency)
 * - DecisionMaker (utility-based, multi-criteria)
 * - HRMModule (Hierarchical Reasoning Machine)
 * - MetaCognition (self-monitoring, confidence)
 * - CognitiveConductor (System 1/2 orchestration)
 *
 * @package AevovCognitiveEngine
 * @since 1.0.0
 */

namespace AevovCognitiveEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UnifiedCognitiveEngine
 */
class UnifiedCognitiveEngine {

    /**
     * Core cognitive components
     */
    private $working_memory;
    private $long_term_memory;
    private $attention_mechanism;
    private $decision_maker;
    private $hrm_module;
    private $metacognition;
    private $conductor;

    /**
     * Reasoning engines (from Pattern Sync Protocol)
     */
    private $analogy_engine;
    private $hrm_reasoning_engine;

    /**
     * Configuration
     */
    private $config = [
        'working_memory_capacity' => 7,
        'attention_focus_count' => 3,
        'system2_threshold' => 0.6,  // Complexity threshold for deliberate reasoning
        'metacognition_enabled' => true,
        'learning_rate' => 0.1,
    ];

    /**
     * Current cognitive state
     */
    private $state = [
        'mode' => 'idle',  // idle, processing, reasoning, learning
        'active_problems' => [],
        'recent_decisions' => [],
        'cognitive_load' => 0.0,
        'confidence' => 1.0,
    ];

    /**
     * Statistics
     */
    private $stats = [
        'total_problems_solved' => 0,
        'system1_activations' => 0,
        'system2_activations' => 0,
        'decisions_made' => 0,
        'learning_events' => 0,
    ];

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct($config = []) {
        $this->config = array_merge($this->config, $config);

        // Initialize core components
        $this->working_memory = new WorkingMemory([
            'capacity' => $this->config['working_memory_capacity'],
            'decay_rate' => 0.5,
            'threshold' => 0.2,
        ]);

        $this->long_term_memory = new LongTermMemory();
        $this->attention_mechanism = new AttentionMechanism();
        $this->decision_maker = new DecisionMaker();
        $this->hrm_module = new HRMModule(4, 4); // 4 cycles, 4 timesteps

        if ($this->config['metacognition_enabled']) {
            $this->metacognition = new MetaCognition();
        }

        $this->conductor = new CognitiveConductor();

        // Initialize reasoning engines
        $this->init_reasoning_engines();

        // Load saved state
        $this->load_state();

        error_log('[Unified Cognitive Engine] Initialized with full cognitive architecture');
    }

    /**
     * Process problem - PRIMARY COGNITIVE METHOD
     *
     * This is the main entry point for cognitive processing
     * Uses dual-process theory (System 1 & System 2)
     *
     * @param mixed $problem Problem description or input
     * @param array $context Additional context
     * @return array Solution with cognitive metadata
     */
    public function process($problem, $context = []) {
        $start_time = microtime(true);

        $this->state['mode'] = 'processing';
        $this->state['active_problems'][] = $problem;

        error_log('[Unified Cognitive Engine] Processing problem: ' . substr(json_encode($problem), 0, 100));

        // Phase 1: Attention - Focus on relevant information
        $focused_input = $this->attend_to_input($problem, $context);

        // Phase 2: Working Memory - Store in short-term memory
        $this->working_memory->store($focused_input, $context);

        // Phase 3: Retrieve relevant long-term memory
        $retrieved_knowledge = $this->retrieve_knowledge($problem);

        // Phase 4: Determine processing mode (System 1 or System 2)
        $complexity = $this->assess_complexity($problem, $context);
        $use_system2 = $complexity > $this->config['system2_threshold'];

        // Phase 5: Problem solving
        if ($use_system2) {
            // System 2: Deliberate, analytical reasoning
            $solution = $this->system2_reasoning($problem, $focused_input, $retrieved_knowledge);
            $this->stats['system2_activations']++;
        } else {
            // System 1: Fast, intuitive problem solving
            $solution = $this->system1_processing($problem, $focused_input, $retrieved_knowledge);
            $this->stats['system1_activations']++;
        }

        // Phase 6: Decision making
        if (isset($solution['options'])) {
            $decision = $this->make_decision($solution['options'], $context);
            $solution['selected_option'] = $decision;
        }

        // Phase 7: Metacognition - Monitor solution quality
        if ($this->metacognition) {
            $confidence = $this->metacognition->assess_confidence($solution, $problem);
            $solution['confidence'] = $confidence;
            $this->state['confidence'] = $confidence;
        }

        // Phase 8: Learning - Store in long-term memory
        $this->learn_from_experience($problem, $solution, $context);

        // Phase 9: Update state
        $this->update_cognitive_state();

        $duration = microtime(true) - $start_time;
        $this->stats['total_problems_solved']++;

        error_log(sprintf(
            '[Unified Cognitive Engine] Problem solved using %s (complexity: %.2f, confidence: %.2f, time: %.3fs)',
            $use_system2 ? 'System 2' : 'System 1',
            $complexity,
            $solution['confidence'] ?? 1.0,
            $duration
        ));

        return [
            'solution' => $solution,
            'system_used' => $use_system2 ? 'system2' : 'system1',
            'complexity' => $complexity,
            'confidence' => $solution['confidence'] ?? 1.0,
            'cognitive_load' => $this->state['cognitive_load'],
            'duration' => $duration,
            'reasoning_trace' => $solution['reasoning_trace'] ?? [],
        ];
    }

    /**
     * System 1: Fast, intuitive processing
     *
     * Uses pattern matching and analogy-based reasoning
     *
     * @param mixed $problem Problem input
     * @param mixed $focused_input Attended input
     * @param array $knowledge Retrieved knowledge
     * @return array Solution
     */
    private function system1_processing($problem, $focused_input, $knowledge) {
        // Use analogy engine to find similar past problems
        if ($this->analogy_engine) {
            $analogies = $this->analogy_engine->reason([
                'problem' => $problem,
                'context' => $focused_input,
                'knowledge' => $knowledge,
            ]);

            if (!empty($analogies) && $analogies['confidence'] > 0.7) {
                return [
                    'type' => 'analogical',
                    'solution' => $analogies['solution'],
                    'confidence' => $analogies['confidence'],
                    'analogies_used' => $analogies['matches'],
                    'reasoning_trace' => ['Fast pattern matching via analogy'],
                ];
            }
        }

        // Fall back to pattern matching in working memory
        $similar_item = $this->working_memory->retrieve($problem, 'fuzzy');

        if ($similar_item) {
            return [
                'type' => 'pattern_match',
                'solution' => $similar_item,
                'confidence' => 0.6,
                'reasoning_trace' => ['Retrieved similar pattern from working memory'],
            ];
        }

        // No match found - return heuristic response
        return [
            'type' => 'heuristic',
            'solution' => $this->apply_heuristics($problem),
            'confidence' => 0.4,
            'reasoning_trace' => ['Applied domain heuristics'],
        ];
    }

    /**
     * System 2: Slow, deliberate reasoning
     *
     * Uses hierarchical reasoning and multi-step analysis
     *
     * @param mixed $problem Problem input
     * @param mixed $focused_input Attended input
     * @param array $knowledge Retrieved knowledge
     * @return array Solution
     */
    private function system2_reasoning($problem, $focused_input, $knowledge) {
        $reasoning_trace = [];

        // Step 1: Problem decomposition
        $subproblems = $this->decompose_problem($problem);
        $reasoning_trace[] = 'Decomposed into ' . count($subproblems) . ' subproblems';

        // Step 2: Use HRM (Hierarchical Reasoning Module)
        $hrm_solution = $this->hrm_module->reason(json_encode([
            'problem' => $problem,
            'subproblems' => $subproblems,
            'knowledge' => $knowledge,
        ]));

        $reasoning_trace[] = 'Applied hierarchical reasoning over 4 cycles';

        // Step 3: Solve subproblems recursively (with lower complexity)
        $subproblem_solutions = [];
        foreach ($subproblems as $subproblem) {
            // Use System 1 for subproblems (faster)
            $sub_solution = $this->system1_processing($subproblem, $focused_input, $knowledge);
            $subproblem_solutions[] = $sub_solution;
            $reasoning_trace[] = 'Solved subproblem: ' . substr(json_encode($subproblem), 0, 50);
        }

        // Step 4: Synthesize solutions
        $synthesized = $this->synthesize_solutions($subproblem_solutions);
        $reasoning_trace[] = 'Synthesized ' . count($subproblem_solutions) . ' partial solutions';

        // Step 5: Validate solution coherence
        $validated = $this->validate_solution($synthesized, $problem);
        $reasoning_trace[] = 'Validated solution coherence';

        return [
            'type' => 'hierarchical_reasoning',
            'solution' => $validated,
            'hrm_output' => $hrm_solution,
            'subproblems' => $subproblems,
            'subproblem_solutions' => $subproblem_solutions,
            'confidence' => 0.85,
            'reasoning_trace' => $reasoning_trace,
        ];
    }

    /**
     * Attend to input using attention mechanism
     *
     * @param mixed $problem Problem input
     * @param array $context Context
     * @return mixed Focused input
     */
    private function attend_to_input($problem, $context) {
        $attention_result = $this->attention_mechanism->focus([
            'input' => $problem,
            'context' => $context,
            'working_memory' => $this->working_memory->get_contents(),
        ]);

        return $attention_result['focused_input'] ?? $problem;
    }

    /**
     * Retrieve relevant knowledge from long-term memory
     *
     * @param mixed $problem Problem as retrieval cue
     * @return array Retrieved knowledge
     */
    private function retrieve_knowledge($problem) {
        return $this->long_term_memory->retrieve($problem, [
            'max_items' => 5,
            'threshold' => 0.3,
        ]);
    }

    /**
     * Assess problem complexity
     *
     * @param mixed $problem Problem input
     * @param array $context Context
     * @return float Complexity score [0, 1]
     */
    private function assess_complexity($problem, $context) {
        $complexity = 0.0;

        // Factor 1: Input size
        $input_size = strlen(json_encode($problem));
        if ($input_size > 1000) {
            $complexity += 0.3;
        } elseif ($input_size > 500) {
            $complexity += 0.2;
        } elseif ($input_size > 100) {
            $complexity += 0.1;
        }

        // Factor 2: Structure complexity (nested arrays/objects)
        if (is_array($problem) || is_object($problem)) {
            $depth = $this->get_array_depth($problem);
            $complexity += min(0.3, $depth * 0.1);
        }

        // Factor 3: No immediate analogies available
        if (!$this->has_direct_analogy($problem)) {
            $complexity += 0.2;
        }

        // Factor 4: Explicit complexity markers
        if (isset($context['complexity'])) {
            $complexity = max($complexity, $context['complexity']);
        }

        return min(1.0, $complexity);
    }

    /**
     * Make decision among options
     *
     * @param array $options Decision options
     * @param array $context Decision context
     * @return mixed Selected option
     */
    private function make_decision($options, $context) {
        $decision = $this->decision_maker->decide($options, $context);

        $this->stats['decisions_made']++;
        $this->state['recent_decisions'][] = [
            'options' => $options,
            'selected' => $decision,
            'timestamp' => microtime(true),
        ];

        // Keep only last 10 decisions
        if (count($this->state['recent_decisions']) > 10) {
            array_shift($this->state['recent_decisions']);
        }

        return $decision;
    }

    /**
     * Learn from problem-solving experience
     *
     * @param mixed $problem Problem
     * @param array $solution Solution
     * @param array $context Context
     */
    private function learn_from_experience($problem, $solution, $context) {
        // Store successful solutions in long-term memory
        if (isset($solution['confidence']) && $solution['confidence'] > 0.6) {
            $this->long_term_memory->store([
                'problem' => $problem,
                'solution' => $solution,
                'context' => $context,
                'timestamp' => time(),
            ]);

            $this->stats['learning_events']++;
        }

        // Update working memory with result
        $this->working_memory->store($solution, ['type' => 'solution']);
    }

    /**
     * Update cognitive state
     */
    private function update_cognitive_state() {
        // Update cognitive load
        $this->state['cognitive_load'] = $this->working_memory->get_load();

        // Update mode
        if (empty($this->state['active_problems'])) {
            $this->state['mode'] = 'idle';
        }

        // Save state periodically
        if ($this->stats['total_problems_solved'] % 10 === 0) {
            $this->save_state();
        }
    }

    /**
     * Decompose problem into subproblems
     *
     * @param mixed $problem Problem
     * @return array Subproblems
     */
    private function decompose_problem($problem) {
        // Simple decomposition based on structure
        if (is_array($problem)) {
            return array_values($problem);
        }

        if (is_string($problem)) {
            // Split by sentences or logical breaks
            $parts = preg_split('/[.!?]+/', $problem);
            return array_filter($parts, function($part) {
                return strlen(trim($part)) > 0;
            });
        }

        return [$problem];
    }

    /**
     * Synthesize multiple solutions
     *
     * @param array $solutions Array of solutions
     * @return mixed Synthesized solution
     */
    private function synthesize_solutions($solutions) {
        if (empty($solutions)) {
            return null;
        }

        // Extract solution values
        $solution_values = array_map(function($s) {
            return $s['solution'] ?? $s;
        }, $solutions);

        // Simple synthesis: combine if possible, otherwise take best
        if (count($solution_values) === 1) {
            return $solution_values[0];
        }

        // If all strings, concatenate
        if (array_reduce($solution_values, function($carry, $item) {
            return $carry && is_string($item);
        }, true)) {
            return implode(' ', $solution_values);
        }

        // If arrays, merge
        if (array_reduce($solution_values, function($carry, $item) {
            return $carry && is_array($item);
        }, true)) {
            return array_merge(...$solution_values);
        }

        // Otherwise, take highest confidence solution
        usort($solutions, function($a, $b) {
            return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
        });

        return $solutions[0]['solution'] ?? $solutions[0];
    }

    /**
     * Validate solution coherence
     *
     * @param mixed $solution Solution to validate
     * @param mixed $problem Original problem
     * @return mixed Validated solution
     */
    private function validate_solution($solution, $problem) {
        // Basic validation - solution should not be empty
        if (empty($solution)) {
            return ['error' => 'Empty solution generated'];
        }

        // Type consistency check
        if (is_array($problem) && !is_array($solution) && !is_string($solution)) {
            return ['error' => 'Solution type mismatch'];
        }

        return $solution;
    }

    /**
     * Apply domain heuristics
     *
     * @param mixed $problem Problem
     * @return mixed Heuristic solution
     */
    private function apply_heuristics($problem) {
        // Simple heuristics based on problem type
        if (is_numeric($problem)) {
            return $problem * 2; // Simple numeric heuristic
        }

        if (is_string($problem)) {
            return "Response to: " . substr($problem, 0, 50);
        }

        return ['heuristic_response' => 'No specific heuristic available'];
    }

    /**
     * Check if direct analogy exists
     *
     * @param mixed $problem Problem
     * @return bool True if analogy found
     */
    private function has_direct_analogy($problem) {
        $similar = $this->long_term_memory->retrieve($problem, ['max_items' => 1, 'threshold' => 0.8]);
        return !empty($similar);
    }

    /**
     * Get array depth
     *
     * @param array $array Array to measure
     * @return int Depth
     */
    private function get_array_depth($array) {
        if (!is_array($array)) {
            return 0;
        }

        $max_depth = 1;
        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->get_array_depth($value) + 1;
                $max_depth = max($max_depth, $depth);
            }
        }

        return $max_depth;
    }

    /**
     * Initialize reasoning engines
     */
    private function init_reasoning_engines() {
        // Check if Pattern Sync Protocol reasoning engines are available
        if (class_exists('\APS\Reasoning\AnalogyReasoningEngine')) {
            $this->analogy_engine = new \APS\Reasoning\AnalogyReasoningEngine([], []);
            error_log('[Unified Cognitive Engine] Analogy reasoning engine loaded');
        }

        if (class_exists('\APS\Reasoning\HRMReasoningEngine')) {
            $this->hrm_reasoning_engine = new \APS\Reasoning\HRMReasoningEngine([
                'n_cycles' => 4,
                't_timesteps' => 4,
            ], []);
            error_log('[Unified Cognitive Engine] HRM reasoning engine loaded');
        }
    }

    /**
     * Load saved state
     */
    private function load_state() {
        $saved_stats = get_option('aevov_cognitive_engine_stats');
        if ($saved_stats) {
            $this->stats = array_merge($this->stats, $saved_stats);
        }
    }

    /**
     * Save state
     */
    private function save_state() {
        update_option('aevov_cognitive_engine_stats', $this->stats, false);
    }

    /**
     * Get statistics
     *
     * @return array Statistics
     */
    public function get_stats() {
        return array_merge($this->stats, [
            'system1_ratio' => $this->stats['total_problems_solved'] > 0
                ? $this->stats['system1_activations'] / $this->stats['total_problems_solved']
                : 0,
            'system2_ratio' => $this->stats['total_problems_solved'] > 0
                ? $this->stats['system2_activations'] / $this->stats['total_problems_solved']
                : 0,
            'current_cognitive_load' => $this->state['cognitive_load'],
            'current_confidence' => $this->state['confidence'],
        ]);
    }

    /**
     * Get current cognitive state
     *
     * @return array State
     */
    public function get_state() {
        return $this->state;
    }

    /**
     * Clear working memory
     */
    public function clear_working_memory() {
        $this->working_memory->clear();
        $this->state['cognitive_load'] = 0.0;
    }
}
