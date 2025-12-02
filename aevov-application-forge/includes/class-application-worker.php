<?php
/**
 * Application Worker - Background Application Processing
 *
 * Handles the execution loop for generated applications, managing state,
 * broadcasting updates, and coordinating with the WebSocket server.
 *
 * @package AevovApplicationForge
 * @since 1.0.0
 */

namespace AevovApplicationForge;

class ApplicationWorker {

    /**
     * Database instance
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Worker logs table name
     *
     * @var string
     */
    private $logs_table;

    /**
     * Application states table name
     *
     * @var string
     */
    private $states_table;

    /**
     * Current job ID
     *
     * @var string|null
     */
    private $current_job_id;

    /**
     * Log level constants
     */
    const LOG_DEBUG = 'debug';
    const LOG_INFO = 'info';
    const LOG_WARNING = 'warning';
    const LOG_ERROR = 'error';

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logs_table = $wpdb->prefix . 'aevov_worker_logs';
        $this->states_table = $wpdb->prefix . 'aevov_app_states';

        add_action('init', [$this, 'create_tables'], 5);

        $this->log('Application worker initialized', self::LOG_INFO);
    }

    /**
     * Create database tables
     *
     * @return void
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql_logs = "CREATE TABLE IF NOT EXISTS {$this->logs_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id VARCHAR(64) NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX job_id (job_id),
            INDEX level (level),
            INDEX created_at (created_at)
        ) {$charset_collate};";

        $sql_states = "CREATE TABLE IF NOT EXISTS {$this->states_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id VARCHAR(64) NOT NULL UNIQUE,
            state_data LONGTEXT NOT NULL,
            state_hash VARCHAR(64) NULL,
            tick_count INT UNSIGNED DEFAULT 0,
            last_tick_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX job_id (job_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_logs);
        dbDelta($sql_states);
    }

    /**
     * Run the application loop for a job
     *
     * @param string $job_id Job identifier
     * @return void
     */
    public function run_application_loop($job_id) {
        $this->current_job_id = $job_id;
        $this->log("Starting application loop for job: $job_id", self::LOG_INFO, [
            'start_time' => current_time('mysql'),
            'memory_usage' => memory_get_usage(true)
        ]);

        $job_manager = new JobManager();
        $job = $job_manager->get_job($job_id);

        if (!$job) {
            $this->log("Job not found: $job_id", self::LOG_ERROR);
            return;
        }

        // Get or create the application weaver
        $weaver = new ApplicationWeaver();

        // Initialize the genesis state
        $state = $weaver->get_genesis_state($job['params'] ?? []);
        $this->save_state($job_id, $state);

        // Initialize WebSocket server for broadcasting
        $websocket_server = new WebSocketServer();

        // Update job status to running
        $job_manager->update_job($job_id, ['status' => 'running']);

        $this->log("Application loop initialized", self::LOG_INFO, [
            'initial_state' => $state,
            'job_params' => $job['params'] ?? []
        ]);

        // Main application loop
        $max_ticks = 1000; // Safety limit
        $tick_count = 0;

        while ($this->should_continue_loop($job_id) && $tick_count < $max_ticks) {
            $tick_start = microtime(true);

            try {
                // Update the application state
                $state = $this->update_state($state, $job);
                $tick_count++;

                // Save state to database
                $this->save_state($job_id, $state, $tick_count);

                // Broadcast the current state to connected clients
                $broadcast_data = [
                    'type' => 'state_update',
                    'job_id' => $job_id,
                    'tick' => $tick_count,
                    'state' => $state,
                    'timestamp' => current_time('mysql')
                ];

                $websocket_server->broadcast(json_encode($broadcast_data), 'job_' . $job_id);

                // Trigger the application tick action for other plugins to hook into
                do_action('aevov_application_tick', $state, $job_id, $tick_count);

                // Calculate sleep time to maintain consistent tick rate
                $tick_duration = microtime(true) - $tick_start;
                $sleep_time = max(0, 1 - $tick_duration);

                if ($tick_count % 10 === 0) {
                    $this->log("Tick progress", self::LOG_DEBUG, [
                        'tick' => $tick_count,
                        'duration_ms' => round($tick_duration * 1000, 2),
                        'memory_usage' => memory_get_usage(true)
                    ]);
                }

                // Sleep for the remaining time to maintain 1 tick per second
                if ($sleep_time > 0) {
                    usleep((int)($sleep_time * 1000000));
                }

            } catch (\Exception $e) {
                $this->log("Error during tick $tick_count: " . $e->getMessage(), self::LOG_ERROR, [
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString()
                ]);

                // Notify clients of the error
                $websocket_server->broadcast(json_encode([
                    'type' => 'error',
                    'job_id' => $job_id,
                    'tick' => $tick_count,
                    'message' => $e->getMessage()
                ]), 'job_' . $job_id);

                // Continue execution unless it's a critical error
                if ($this->is_critical_error($e)) {
                    break;
                }
            }
        }

        // Determine final status
        $final_status = $tick_count >= $max_ticks ? 'completed' : 'stopped';
        $job_manager->update_job($job_id, ['status' => $final_status]);

        // Broadcast completion
        $websocket_server->broadcast(json_encode([
            'type' => 'completed',
            'job_id' => $job_id,
            'total_ticks' => $tick_count,
            'final_state' => $state,
            'status' => $final_status
        ]), 'job_' . $job_id);

        $this->log("Application loop finished", self::LOG_INFO, [
            'total_ticks' => $tick_count,
            'final_status' => $final_status,
            'end_time' => current_time('mysql')
        ]);

        $this->current_job_id = null;
    }

    /**
     * Check if the application loop should continue
     *
     * @param string $job_id Job identifier
     * @return bool
     */
    private function should_continue_loop($job_id) {
        $job_manager = new JobManager();
        $job = $job_manager->get_job($job_id);

        if (!$job) {
            return false;
        }

        return in_array($job['status'], ['running', 'processing']);
    }

    /**
     * Update the application state
     *
     * @param array $state Current state
     * @param array $job Job data
     * @return array Updated state
     */
    private function update_state($state, $job) {
        // Initialize state tracking if needed
        if (!isset($state['tick_count'])) {
            $state['tick_count'] = 0;
        }
        if (!isset($state['start_time'])) {
            $state['start_time'] = current_time('mysql');
        }

        // Increment tick counter
        $state['tick_count']++;
        $state['last_update'] = current_time('mysql');

        // Process state transitions based on job type
        $job_type = $job['params']['type'] ?? 'default';
        $state = $this->process_state_transition($state, $job_type, $job['params'] ?? []);

        // Update computed values
        $state['computed'] = $this->compute_derived_state($state);

        // Apply any registered state modifiers
        $state = apply_filters('aevov_application_state', $state, $job);

        return $state;
    }

    /**
     * Process state transitions based on application type
     *
     * @param array $state Current state
     * @param string $type Application type
     * @param array $params Job parameters
     * @return array Updated state
     */
    private function process_state_transition($state, $type, $params) {
        switch ($type) {
            case 'simulation':
                $state = $this->process_simulation_state($state, $params);
                break;

            case 'game':
                $state = $this->process_game_state($state, $params);
                break;

            case 'visualization':
                $state = $this->process_visualization_state($state, $params);
                break;

            case 'workflow':
                $state = $this->process_workflow_state($state, $params);
                break;

            default:
                // Generic state update
                if (!isset($state['progress'])) {
                    $state['progress'] = 0;
                }
                $state['progress'] = min(100, $state['progress'] + 0.1);
                break;
        }

        return $state;
    }

    /**
     * Process simulation-type state
     *
     * @param array $state Current state
     * @param array $params Parameters
     * @return array Updated state
     */
    private function process_simulation_state($state, $params) {
        // Initialize simulation state
        if (!isset($state['entities'])) {
            $state['entities'] = [];
            $entity_count = $params['entity_count'] ?? 10;

            for ($i = 0; $i < $entity_count; $i++) {
                $state['entities'][] = [
                    'id' => 'entity_' . $i,
                    'position' => ['x' => mt_rand(0, 100), 'y' => mt_rand(0, 100)],
                    'velocity' => ['x' => (mt_rand(-10, 10) / 10), 'y' => (mt_rand(-10, 10) / 10)],
                    'properties' => []
                ];
            }
        }

        // Update entity positions
        foreach ($state['entities'] as &$entity) {
            $entity['position']['x'] += $entity['velocity']['x'];
            $entity['position']['y'] += $entity['velocity']['y'];

            // Bounce off boundaries
            if ($entity['position']['x'] < 0 || $entity['position']['x'] > 100) {
                $entity['velocity']['x'] *= -1;
            }
            if ($entity['position']['y'] < 0 || $entity['position']['y'] > 100) {
                $entity['velocity']['y'] *= -1;
            }

            // Clamp positions
            $entity['position']['x'] = max(0, min(100, $entity['position']['x']));
            $entity['position']['y'] = max(0, min(100, $entity['position']['y']));
        }

        $state['simulation_time'] = ($state['simulation_time'] ?? 0) + 1;

        return $state;
    }

    /**
     * Process game-type state
     *
     * @param array $state Current state
     * @param array $params Parameters
     * @return array Updated state
     */
    private function process_game_state($state, $params) {
        if (!isset($state['game'])) {
            $state['game'] = [
                'score' => 0,
                'level' => 1,
                'player' => ['x' => 50, 'y' => 50, 'health' => 100],
                'enemies' => [],
                'items' => []
            ];
        }

        // Simple game logic
        $state['game']['score'] += mt_rand(0, 10);

        // Level progression
        if ($state['game']['score'] > $state['game']['level'] * 100) {
            $state['game']['level']++;
        }

        return $state;
    }

    /**
     * Process visualization-type state
     *
     * @param array $state Current state
     * @param array $params Parameters
     * @return array Updated state
     */
    private function process_visualization_state($state, $params) {
        if (!isset($state['visualization'])) {
            $state['visualization'] = [
                'data_points' => [],
                'current_index' => 0
            ];
        }

        // Generate new data point
        $state['visualization']['data_points'][] = [
            'timestamp' => current_time('mysql'),
            'value' => sin($state['tick_count'] / 10) * 50 + 50,
            'secondary' => cos($state['tick_count'] / 8) * 30 + 50
        ];

        // Keep only last 100 data points
        if (count($state['visualization']['data_points']) > 100) {
            array_shift($state['visualization']['data_points']);
        }

        $state['visualization']['current_index']++;

        return $state;
    }

    /**
     * Process workflow-type state
     *
     * @param array $state Current state
     * @param array $params Parameters
     * @return array Updated state
     */
    private function process_workflow_state($state, $params) {
        if (!isset($state['workflow'])) {
            $steps = $params['steps'] ?? [
                ['name' => 'Initialize', 'duration' => 5],
                ['name' => 'Process', 'duration' => 10],
                ['name' => 'Validate', 'duration' => 5],
                ['name' => 'Complete', 'duration' => 3]
            ];

            $state['workflow'] = [
                'steps' => $steps,
                'current_step' => 0,
                'step_progress' => 0
            ];
        }

        $current_step = $state['workflow']['current_step'];
        $steps = $state['workflow']['steps'];

        if ($current_step < count($steps)) {
            $step_duration = $steps[$current_step]['duration'];
            $state['workflow']['step_progress']++;

            if ($state['workflow']['step_progress'] >= $step_duration) {
                $state['workflow']['current_step']++;
                $state['workflow']['step_progress'] = 0;
            }
        }

        $state['workflow']['overall_progress'] = ($current_step / count($steps)) * 100;

        return $state;
    }

    /**
     * Compute derived state values
     *
     * @param array $state Current state
     * @return array Computed values
     */
    private function compute_derived_state($state) {
        $computed = [];

        // Calculate uptime
        if (isset($state['start_time'])) {
            $start = strtotime($state['start_time']);
            $computed['uptime_seconds'] = time() - $start;
            $computed['uptime_formatted'] = $this->format_duration($computed['uptime_seconds']);
        }

        // Calculate tick rate
        if (isset($state['tick_count']) && isset($computed['uptime_seconds']) && $computed['uptime_seconds'] > 0) {
            $computed['tick_rate'] = round($state['tick_count'] / $computed['uptime_seconds'], 2);
        }

        // Memory usage
        $computed['memory_usage_mb'] = round(memory_get_usage(true) / (1024 * 1024), 2);

        return $computed;
    }

    /**
     * Format duration in human-readable format
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    private function format_duration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        }

        return sprintf('%ds', $secs);
    }

    /**
     * Save state to database
     *
     * @param string $job_id Job identifier
     * @param array $state State data
     * @param int $tick_count Current tick count
     * @return bool Success status
     */
    private function save_state($job_id, $state, $tick_count = 0) {
        $state_json = json_encode($state);
        $state_hash = hash('sha256', $state_json);

        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->states_table} WHERE job_id = %s",
            $job_id
        ));

        if ($existing) {
            $result = $this->wpdb->update(
                $this->states_table,
                [
                    'state_data' => $state_json,
                    'state_hash' => $state_hash,
                    'tick_count' => $tick_count,
                    'last_tick_at' => current_time('mysql')
                ],
                ['job_id' => $job_id],
                ['%s', '%s', '%d', '%s'],
                ['%s']
            );
        } else {
            $result = $this->wpdb->insert(
                $this->states_table,
                [
                    'job_id' => $job_id,
                    'state_data' => $state_json,
                    'state_hash' => $state_hash,
                    'tick_count' => $tick_count,
                    'last_tick_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%d', '%s']
            );
        }

        return $result !== false;
    }

    /**
     * Get saved state for a job
     *
     * @param string $job_id Job identifier
     * @return array|null State data
     */
    public function get_state($job_id) {
        $row = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->states_table} WHERE job_id = %s",
            $job_id
        ));

        if ($row) {
            return [
                'state' => json_decode($row->state_data, true),
                'tick_count' => $row->tick_count,
                'last_tick_at' => $row->last_tick_at,
                'state_hash' => $row->state_hash
            ];
        }

        return null;
    }

    /**
     * Check if an exception is critical
     *
     * @param \Exception $e Exception
     * @return bool
     */
    private function is_critical_error(\Exception $e) {
        $critical_types = [
            'OutOfMemoryError',
            'DatabaseException',
            'FatalException'
        ];

        foreach ($critical_types as $type) {
            if (stripos(get_class($e), $type) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log a message
     *
     * @param string $message Log message
     * @param string $level Log level
     * @param array $context Additional context
     * @return void
     */
    private function log($message, $level = self::LOG_INFO, $context = []) {
        // Store in database
        $this->wpdb->insert(
            $this->logs_table,
            [
                'job_id' => $this->current_job_id,
                'level' => $level,
                'message' => $message,
                'context' => !empty($context) ? json_encode($context) : null
            ],
            ['%s', '%s', '%s', '%s']
        );

        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = sprintf(
                '[AevovApplicationForge][%s]%s %s',
                strtoupper($level),
                $this->current_job_id ? "[Job:{$this->current_job_id}]" : '',
                $message
            );

            if (!empty($context)) {
                $log_message .= ' | Context: ' . json_encode($context);
            }

            error_log($log_message);
        }
    }

    /**
     * Get logs for a job
     *
     * @param string $job_id Job identifier
     * @param array $args Query arguments
     * @return array Logs
     */
    public function get_logs($job_id, $args = []) {
        $defaults = [
            'level' => null,
            'limit' => 100,
            'offset' => 0,
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM {$this->logs_table} WHERE job_id = %s";
        $prepare_args = [$job_id];

        if ($args['level']) {
            $sql .= " AND level = %s";
            $prepare_args[] = $args['level'];
        }

        $sql .= " ORDER BY created_at {$args['order']} LIMIT %d OFFSET %d";
        $prepare_args[] = $args['limit'];
        $prepare_args[] = $args['offset'];

        $logs = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $prepare_args),
            ARRAY_A
        );

        foreach ($logs as &$log) {
            if ($log['context']) {
                $log['context'] = json_decode($log['context'], true);
            }
        }

        return $logs;
    }

    /**
     * Clean up old logs
     *
     * @param int $days_old Delete logs older than this many days
     * @return int Number of logs deleted
     */
    public function cleanup_old_logs($days_old = 7) {
        return $this->wpdb->query($this->wpdb->prepare(
            "DELETE FROM {$this->logs_table}
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days_old
        ));
    }

    /**
     * Stop a running application
     *
     * @param string $job_id Job identifier
     * @return bool Success status
     */
    public function stop_application($job_id) {
        $job_manager = new JobManager();
        $result = $job_manager->update_job($job_id, ['status' => 'stopped']);

        $this->log("Application stop requested for job: $job_id", self::LOG_INFO);

        return $result !== false;
    }
}
