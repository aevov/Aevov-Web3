<?php
/**
 * AROS Aevov Integrator
 *
 * Central integration hub connecting AROS with the Aevov ecosystem:
 * - Typebot: Conversational AI for natural language robot commands
 * - Cubbit DS3: Distributed storage for SLAM maps, trajectories, sensor logs
 * - Bloom: Pattern recognition for robot behavior learning
 * - APS: Task pattern recognition and comparison
 * - Physics Engine: Simulation and dynamics
 * - NeuroArchitect: Neural network architectures
 * - Language Engine: NLP and semantic understanding
 *
 * @package AROS\Integration
 * @since 1.0.0
 */

namespace AROS\Integration;

class AevovIntegrator {

    private $typebot_enabled = false;
    private $cubbit_enabled = false;
    private $bloom_enabled = false;
    private $aps_enabled = false;

    private $hri = null; // Human-Robot Interface
    private $slam = null; // SLAM Engine
    private $task_planner = null; // Task Planner

    /**
     * Constructor
     */
    public function __construct() {
        $this->check_integrations();
        $this->initialize_aros_components();
        $this->setup_hooks();

        error_log('[AROS Integrator] Initialized - Typebot: ' . ($this->typebot_enabled ? 'ON' : 'OFF') .
                  ', Cubbit: ' . ($this->cubbit_enabled ? 'ON' : 'OFF') .
                  ', Bloom: ' . ($this->bloom_enabled ? 'ON' : 'OFF') .
                  ', APS: ' . ($this->aps_enabled ? 'ON' : 'OFF'));
    }

    /**
     * Check which integrations are available
     */
    private function check_integrations() {
        // Check Typebot
        $this->typebot_enabled = class_exists('BLOOM_Typebot_Integration');

        // Check Cubbit
        $this->cubbit_enabled = class_exists('APS_Tools\\Integrations\\Cubbit_Integration_Protocol') ||
                                class_exists('CubbitDirectoryManager');

        // Check Bloom Pattern Recognition
        $this->bloom_enabled = class_exists('\\BLOOM\\Models\\PatternModel') ||
                              class_exists('BLOOM_Pattern_System');

        // Check APS
        $this->aps_enabled = class_exists('\\APS\\Core\\APS_Core') ||
                            class_exists('APS_Pattern_DB');
    }

    /**
     * Initialize AROS components
     */
    private function initialize_aros_components() {
        // Initialize HRI if available
        if (class_exists('\\AROS\\Communication\\HumanRobotInterface')) {
            $this->hri = new \AROS\Communication\HumanRobotInterface();
        }

        // Initialize SLAM if available
        if (class_exists('\\AROS\\Spatial\\SLAMEngine')) {
            $this->slam = new \AROS\Spatial\SLAMEngine();
        }

        // Initialize Task Planner if available
        if (class_exists('\\AROS\\Cognition\\TaskPlanner')) {
            $this->task_planner = new \AROS\Cognition\TaskPlanner();
        }
    }

    /**
     * Setup WordPress hooks
     */
    private function setup_hooks() {
        // Typebot integration
        if ($this->typebot_enabled) {
            add_filter('bloom_typebot_instruction', [$this, 'process_typebot_command'], 10, 1);
            add_action('bloom_instruction_processed', [$this, 'send_typebot_result'], 10, 2);
        }

        // Cubbit integration
        if ($this->cubbit_enabled) {
            add_action('aros_slam_map_updated', [$this, 'backup_map_to_cubbit'], 10, 1);
            add_action('aros_trajectory_completed', [$this, 'store_trajectory_in_cubbit'], 10, 1);
            add_action('aros_sensor_log', [$this, 'log_sensor_data_to_cubbit'], 10, 2);
        }

        // Bloom integration
        if ($this->bloom_enabled) {
            add_action('aros_behavior_completed', [$this, 'analyze_behavior_with_bloom'], 10, 1);
            add_filter('aros_predict_next_action', [$this, 'predict_with_bloom'], 10, 2);
        }

        // APS integration
        if ($this->aps_enabled) {
            add_filter('aros_task_pattern_match', [$this, 'match_task_with_aps'], 10, 2);
            add_action('aros_task_completed', [$this, 'share_task_pattern_via_aps'], 10, 1);
        }
    }

    // ==================== TYPEBOT INTEGRATION ====================

    /**
     * Process Typebot conversational command into robot action
     *
     * @param array $instruction Typebot instruction
     * @return array Processed robot command
     */
    public function process_typebot_command($instruction) {
        if (!$this->hri) {
            error_log('[AROS Integrator] HRI not available for Typebot command');
            return $instruction;
        }

        $instruction_text = $instruction['data']['text'] ?? '';
        $instruction_type = $instruction['type'] ?? 'unknown';

        error_log('[AROS Integrator] Processing Typebot command: ' . $instruction_text);

        // Use AROS HRI to parse natural language command
        $robot_command = $this->hri->process_command($instruction_text, 'text');

        if ($robot_command !== false) {
            // Execute robot command
            $result = $this->execute_robot_command($robot_command);

            // Store result for callback
            $instruction['aros_result'] = $result;
            $instruction['aros_command'] = $robot_command;
        }

        return $instruction;
    }

    /**
     * Send result back to Typebot
     */
    public function send_typebot_result($job_id, $result) {
        // Prepare response for Typebot
        $response = [
            'job_id' => $job_id,
            'status' => $result['success'] ? 'completed' : 'failed',
            'robot_response' => $this->generate_natural_response($result),
            'timestamp' => microtime(true),
        ];

        // Send via webhook if callback URL exists
        if (isset($result['callback_url'])) {
            $this->send_webhook($result['callback_url'], $response);
        }
    }

    /**
     * Generate natural language response
     */
    private function generate_natural_response($result) {
        if (!$this->hri) {
            return 'Command processed';
        }

        if ($result['success']) {
            $responses = [
                'move' => 'I have moved to the target location.',
                'grasp' => 'I have picked up the object.',
                'release' => 'I have released the object.',
                'stop' => 'I have stopped all movement.',
                'status' => 'All systems operational.',
            ];

            $intent = $result['intent'] ?? 'unknown';
            return $responses[$intent] ?? 'Task completed successfully.';
        } else {
            return 'I encountered an error: ' . ($result['error'] ?? 'Unknown error');
        }
    }

    // ==================== CUBBIT STORAGE INTEGRATION ====================

    /**
     * Backup SLAM map to Cubbit distributed storage
     *
     * @param array $map_data SLAM map data
     */
    public function backup_map_to_cubbit($map_data) {
        if (!$this->cubbit_enabled) {
            return;
        }

        $map_key = 'aros/maps/' . date('Y/m/d/') . uniqid('map_') . '.json';

        $backup_data = [
            'timestamp' => microtime(true),
            'map' => $map_data['map'] ?? [],
            'pose' => $map_data['pose'] ?? [],
            'resolution' => $map_data['resolution'] ?? 0.1,
            'metadata' => [
                'robot_id' => $this->get_robot_id(),
                'map_size' => count($map_data['map'] ?? []),
                'confidence' => $map_data['confidence'] ?? 0.0,
            ],
        ];

        $this->upload_to_cubbit($map_key, json_encode($backup_data));

        error_log('[AROS Integrator] SLAM map backed up to Cubbit: ' . $map_key);

        do_action('aros_cubbit_map_backed_up', $map_key, $backup_data);
    }

    /**
     * Store robot trajectory in Cubbit
     *
     * @param array $trajectory Trajectory data
     */
    public function store_trajectory_in_cubbit($trajectory) {
        if (!$this->cubbit_enabled) {
            return;
        }

        $traj_key = 'aros/trajectories/' . date('Y/m/d/') . uniqid('traj_') . '.json';

        $traj_data = [
            'timestamp' => microtime(true),
            'robot_id' => $this->get_robot_id(),
            'path' => $trajectory['path'] ?? [],
            'duration' => $trajectory['duration'] ?? 0,
            'distance' => $trajectory['distance'] ?? 0,
            'success' => $trajectory['success'] ?? false,
            'obstacles_avoided' => $trajectory['obstacles_avoided'] ?? 0,
        ];

        $this->upload_to_cubbit($traj_key, json_encode($traj_data));

        error_log('[AROS Integrator] Trajectory stored in Cubbit: ' . $traj_key);
    }

    /**
     * Log sensor data to Cubbit
     *
     * @param string $sensor_type Sensor type
     * @param array $sensor_data Sensor readings
     */
    public function log_sensor_data_to_cubbit($sensor_type, $sensor_data) {
        if (!$this->cubbit_enabled) {
            return;
        }

        // Only log periodically to avoid storage overload
        if (rand(1, 100) > 10) { // 10% sampling rate
            return;
        }

        $log_key = 'aros/sensors/' . $sensor_type . '/' . date('Y/m/d/H/') . uniqid('log_') . '.json';

        $log_data = [
            'timestamp' => microtime(true),
            'robot_id' => $this->get_robot_id(),
            'sensor_type' => $sensor_type,
            'readings' => $sensor_data,
        ];

        $this->upload_to_cubbit($log_key, json_encode($log_data));
    }

    /**
     * Upload data to Cubbit storage
     */
    private function upload_to_cubbit($key, $data) {
        // Use WordPress action to trigger Cubbit upload
        // The Cubbit Integration Protocol will handle the actual upload
        do_action('aps_cubbit_upload', $key, $data, 'application/json');
    }

    // ==================== BLOOM PATTERN LEARNING ====================

    /**
     * Analyze robot behavior with Bloom pattern recognition
     *
     * @param array $behavior Behavior data
     */
    public function analyze_behavior_with_bloom($behavior) {
        if (!$this->bloom_enabled) {
            return;
        }

        error_log('[AROS Integrator] Analyzing behavior with Bloom');

        // Create tensor from behavior data
        $tensor = $this->create_behavior_tensor($behavior);

        // Store for Bloom processing
        do_action('bloom_analyze_tensor', $tensor, 'robot_behavior');

        // If Bloom Pattern Model is available, analyze immediately
        if (class_exists('\\BLOOM\\Models\\PatternModel')) {
            try {
                $pattern_model = new \BLOOM\Models\PatternModel();
                $patterns = $pattern_model->recognize($tensor);

                // Store learned patterns
                foreach ($patterns as $pattern) {
                    $this->store_learned_behavior_pattern($pattern);
                }

                error_log('[AROS Integrator] Bloom identified ' . count($patterns) . ' behavior patterns');

            } catch (\Exception $e) {
                error_log('[AROS Integrator] Bloom analysis error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Predict next action using Bloom patterns
     *
     * @param mixed $default Default prediction
     * @param array $context Current context
     * @return array Predicted action
     */
    public function predict_with_bloom($default, $context) {
        if (!$this->bloom_enabled) {
            return $default;
        }

        // Create tensor from current context
        $context_tensor = $this->create_context_tensor($context);

        // Use Bloom to predict next action
        $prediction = apply_filters('bloom_predict_action', null, $context_tensor);

        if ($prediction !== null) {
            error_log('[AROS Integrator] Bloom predicted action: ' . ($prediction['action'] ?? 'unknown'));
            return $prediction;
        }

        return $default;
    }

    /**
     * Create tensor from behavior data
     */
    private function create_behavior_tensor($behavior) {
        $action = $behavior['action'] ?? 'unknown';
        $success = $behavior['success'] ?? false;
        $duration = $behavior['duration'] ?? 0;

        return [
            'action_features' => $this->encode_action($action),
            'outcome_features' => [$success ? 1.0 : 0.0],
            'temporal_features' => [
                'duration' => min(1.0, $duration / 60.0), // Normalize to 0-1
                'hour' => (int) date('G') / 24,
                'day' => (int) date('N') / 7,
            ],
            'context_features' => $this->extract_context_features($behavior),
            'metadata' => [
                'robot_id' => $this->get_robot_id(),
                'timestamp' => microtime(true),
                'action' => $action,
            ],
        ];
    }

    /**
     * Create tensor from current context
     */
    private function create_context_tensor($context) {
        return [
            'position_features' => [
                $context['position']['x'] ?? 0.0,
                $context['position']['y'] ?? 0.0,
                $context['position']['theta'] ?? 0.0,
            ],
            'goal_features' => [
                $context['goal']['x'] ?? 0.0,
                $context['goal']['y'] ?? 0.0,
            ],
            'obstacle_features' => $this->encode_obstacles($context['obstacles'] ?? []),
            'temporal_features' => [
                'hour' => (int) date('G') / 24,
                'day' => (int) date('N') / 7,
            ],
        ];
    }

    /**
     * Encode action as feature vector
     */
    private function encode_action($action) {
        $action_map = [
            'move' => [1, 0, 0, 0, 0],
            'grasp' => [0, 1, 0, 0, 0],
            'release' => [0, 0, 1, 0, 0],
            'rotate' => [0, 0, 0, 1, 0],
            'wait' => [0, 0, 0, 0, 1],
        ];

        return $action_map[$action] ?? [0, 0, 0, 0, 0];
    }

    /**
     * Extract context features from behavior
     */
    private function extract_context_features($behavior) {
        return [
            'obstacles_nearby' => isset($behavior['obstacles']) ? (count($behavior['obstacles']) / 10.0) : 0.0,
            'battery_level' => $behavior['battery'] ?? 1.0,
            'task_priority' => $behavior['priority'] ?? 0.5,
        ];
    }

    /**
     * Encode obstacles as features
     */
    private function encode_obstacles($obstacles) {
        $count = min(10, count($obstacles));
        $avg_distance = 0.0;

        if ($count > 0) {
            foreach (array_slice($obstacles, 0, 10) as $obs) {
                $avg_distance += $obs['distance'] ?? 0.0;
            }
            $avg_distance /= $count;
        }

        return [
            'count' => $count / 10.0,
            'avg_distance' => min(1.0, $avg_distance / 10.0),
        ];
    }

    /**
     * Store learned behavior pattern
     */
    private function store_learned_behavior_pattern($pattern) {
        // Store in WordPress options for persistence
        $learned_patterns = get_option('aros_learned_behaviors', []);

        $pattern_hash = hash('sha256', json_encode($pattern));

        if (!isset($learned_patterns[$pattern_hash])) {
            $learned_patterns[$pattern_hash] = [
                'pattern' => $pattern,
                'first_seen' => microtime(true),
                'occurrences' => 1,
            ];
        } else {
            $learned_patterns[$pattern_hash]['occurrences']++;
            $learned_patterns[$pattern_hash]['last_seen'] = microtime(true);
        }

        update_option('aros_learned_behaviors', $learned_patterns);

        do_action('aros_behavior_pattern_learned', $pattern);
    }

    // ==================== APS TASK PATTERN MATCHING ====================

    /**
     * Match current task with known patterns using APS
     *
     * @param mixed $default Default match
     * @param array $task Task to match
     * @return array Best matching pattern
     */
    public function match_task_with_aps($default, $task) {
        if (!$this->aps_enabled) {
            return $default;
        }

        error_log('[AROS Integrator] Matching task with APS patterns');

        // Create pattern representation of task
        $task_pattern = $this->create_task_pattern($task);

        // Use APS to find best match
        $match = apply_filters('aps_pattern_analysis', null, $task_pattern);

        if ($match !== null && isset($match['confidence']) && $match['confidence'] > 0.75) {
            error_log('[AROS Integrator] APS found pattern match: ' . ($match['pattern_id'] ?? 'unknown') .
                     ' (confidence: ' . $match['confidence'] . ')');
            return $match;
        }

        return $default;
    }

    /**
     * Share completed task pattern via APS
     *
     * @param array $task Completed task
     */
    public function share_task_pattern_via_aps($task) {
        if (!$this->aps_enabled) {
            return;
        }

        if (!($task['success'] ?? false)) {
            return; // Only share successful task patterns
        }

        error_log('[AROS Integrator] Sharing task pattern via APS');

        $task_pattern = $this->create_task_pattern($task);

        // Distribute pattern via APS
        do_action('aps_pattern_distributed', $task_pattern, [
            'robot_id' => $this->get_robot_id(),
            'success_rate' => 1.0,
            'executions' => 1,
        ]);
    }

    /**
     * Create task pattern representation
     */
    private function create_task_pattern($task) {
        return [
            'task_type' => $task['type'] ?? 'unknown',
            'preconditions' => $task['preconditions'] ?? [],
            'actions' => $task['actions'] ?? [],
            'effects' => $task['effects'] ?? [],
            'duration' => $task['duration'] ?? 0,
            'complexity' => $this->calculate_task_complexity($task),
        ];
    }

    /**
     * Calculate task complexity score
     */
    private function calculate_task_complexity($task) {
        $actions_count = count($task['actions'] ?? []);
        $preconditions_count = count($task['preconditions'] ?? []);
        $effects_count = count($task['effects'] ?? []);

        return ($actions_count + $preconditions_count + $effects_count) / 30.0; // Normalize
    }

    // ==================== AEVOV ECOSYSTEM INTEGRATION ====================

    /**
     * Get physics simulation for robot dynamics
     *
     * @param array $params Simulation parameters
     * @return array Simulation results
     */
    public function get_physics_simulation($params) {
        // Check if Aevov Physics Engine is available
        if (!class_exists('\\Aevov\\Physics\\Engine')) {
            error_log('[AROS Integrator] Physics Engine not available');
            return [];
        }

        try {
            $physics = new \Aevov\Physics\Engine();
            return $physics->simulate($params);
        } catch (\Exception $e) {
            error_log('[AROS Integrator] Physics simulation error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get neural network blueprint from NeuroArchitect
     *
     * @param string $architecture Architecture type
     * @return array Network blueprint
     */
    public function get_neural_blueprint($architecture = 'default') {
        // Check if NeuroArchitect is available
        if (!class_exists('\\Aevov\\NeuroArchitect\\Builder')) {
            error_log('[AROS Integrator] NeuroArchitect not available');
            return [];
        }

        try {
            $builder = new \Aevov\NeuroArchitect\Builder();
            return $builder->build($architecture);
        } catch (\Exception $e) {
            error_log('[AROS Integrator] NeuroArchitect error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Process text with Language Engine
     *
     * @param string $text Text to process
     * @return array Processed result
     */
    public function process_language($text) {
        // Check if Language Engine is available
        if (!class_exists('\\Aevov\\Language\\Engine')) {
            error_log('[AROS Integrator] Language Engine not available');

            // Fallback to AROS HRI NLU
            if ($this->hri) {
                return $this->hri->process_command($text, 'text');
            }

            return ['text' => $text, 'processed' => false];
        }

        try {
            $language = new \Aevov\Language\Engine();
            return $language->process($text);
        } catch (\Exception $e) {
            error_log('[AROS Integrator] Language Engine error: ' . $e->getMessage());
            return ['text' => $text, 'error' => $e->getMessage()];
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Execute robot command
     *
     * @param array $command Robot command
     * @return array Execution result
     */
    private function execute_robot_command($command) {
        $intent = $command['intent'] ?? 'unknown';

        error_log('[AROS Integrator] Executing robot command: ' . $intent);

        // Trigger AROS action
        do_action('aros_execute_command', $command);

        // Simulate execution (in production, this would interface with actual robot)
        $result = [
            'success' => true,
            'intent' => $intent,
            'command' => $command,
            'timestamp' => microtime(true),
        ];

        return $result;
    }

    /**
     * Send webhook
     */
    private function send_webhook($url, $data) {
        wp_remote_post($url, [
            'body' => json_encode($data),
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 10,
        ]);
    }

    /**
     * Get robot ID
     */
    private function get_robot_id() {
        return get_option('aros_robot_id', 'robot_' . get_current_blog_id());
    }

    /**
     * Get integration status
     *
     * @return array Integration status
     */
    public function get_status() {
        return [
            'integrations' => [
                'typebot' => $this->typebot_enabled,
                'cubbit' => $this->cubbit_enabled,
                'bloom' => $this->bloom_enabled,
                'aps' => $this->aps_enabled,
            ],
            'aros_components' => [
                'hri' => $this->hri !== null,
                'slam' => $this->slam !== null,
                'task_planner' => $this->task_planner !== null,
            ],
            'robot_id' => $this->get_robot_id(),
        ];
    }
}
