<?php
/**
 * Emergency Stop System for AROS
 *
 * CRITICAL SAFETY SYSTEM - Provides emergency stop functionality with hardware interlocks
 * This system must be fail-safe and respond within milliseconds
 *
 * @package AROS
 * @subpackage Safety
 * @since 1.0.0
 */

namespace AROS\Safety;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class EmergencyStop
 *
 * Implements a comprehensive emergency stop system with:
 * - Hardware interlock support
 * - Multiple trigger methods (hardware button, software, watchdog)
 * - State persistence across restarts
 * - Fail-safe behavior (stops on any error)
 * - Logging and diagnostics
 */
class EmergencyStop {

    /**
     * Emergency stop states
     */
    const STATE_NORMAL = 'normal';
    const STATE_STOPPING = 'stopping';
    const STATE_STOPPED = 'stopped';
    const STATE_LOCKED = 'locked';

    /**
     * Emergency stop triggers
     */
    const TRIGGER_HARDWARE = 'hardware';
    const TRIGGER_SOFTWARE = 'software';
    const TRIGGER_COLLISION = 'collision';
    const TRIGGER_WATCHDOG = 'watchdog';
    const TRIGGER_HEALTH = 'health';
    const TRIGGER_USER = 'user';

    /**
     * @var string Current emergency stop state
     */
    private $state = self::STATE_NORMAL;

    /**
     * @var array Emergency stop history
     */
    private $history = [];

    /**
     * @var int Maximum response time in milliseconds
     */
    private $max_response_time = 50; // 50ms maximum

    /**
     * @var bool Hardware interlock status
     */
    private $hardware_interlock = false;

    /**
     * @var string GPIO pin for hardware E-stop (if available)
     */
    private $gpio_pin = null;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct($config = []) {
        $this->gpio_pin = $config['gpio_pin'] ?? getenv('AROS_ESTOP_GPIO') ?: null;
        $this->max_response_time = $config['max_response_time'] ?? 50;

        // Load state from database
        $this->load_state();

        // Register shutdown handler for fail-safe
        register_shutdown_function([$this, 'shutdown_handler']);

        // If we were stopped, stay stopped until explicitly reset
        if ($this->state === self::STATE_STOPPED || $this->state === self::STATE_LOCKED) {
            $this->log_event('System started in stopped state - safety interlock active');
        }
    }

    /**
     * Trigger emergency stop
     *
     * CRITICAL: This method must execute as fast as possible
     *
     * @param string $reason Reason for emergency stop
     * @param string $trigger Trigger type (hardware, software, etc.)
     * @param array $context Additional context information
     * @return bool True if stop was successful
     */
    public function trigger($reason, $trigger = self::TRIGGER_SOFTWARE, $context = []) {
        $start_time = microtime(true);

        try {
            // Immediately set state to stopping
            $this->state = self::STATE_STOPPING;

            // Log the emergency stop
            $this->log_event('EMERGENCY STOP TRIGGERED', [
                'reason' => $reason,
                'trigger' => $trigger,
                'context' => $context,
                'timestamp' => current_time('mysql'),
            ]);

            // Fire WordPress action FIRST for immediate response
            do_action('aros_emergency_stop', $reason, $trigger, $context);

            // Stop all motors immediately
            $this->stop_all_motors();

            // Disable all actuators
            $this->disable_all_actuators();

            // Engage hardware interlock if available
            if ($this->gpio_pin) {
                $this->engage_hardware_interlock();
            }

            // Set brake on all joints
            $this->engage_all_brakes();

            // Cut power to non-critical systems
            $this->cut_power_to_motion_systems();

            // Set final state
            $this->state = self::STATE_STOPPED;

            // Persist state to database
            $this->save_state();

            // Calculate response time
            $response_time = (microtime(true) - $start_time) * 1000; // Convert to ms

            // Check if we met timing requirements
            if ($response_time > $this->max_response_time) {
                $this->log_event('WARNING: Emergency stop response time exceeded maximum', [
                    'response_time_ms' => $response_time,
                    'max_allowed_ms' => $this->max_response_time,
                ]);
            }

            // Fire completion action
            do_action('aros_emergency_stop_complete', $response_time);

            return true;

        } catch (\Exception $e) {
            // CRITICAL: If emergency stop fails, we MUST still stop the robot
            $this->log_event('CRITICAL: Emergency stop exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback: Try to stop via GPIO if available
            if ($this->gpio_pin) {
                $this->emergency_gpio_stop();
            }

            // Set state to locked (requires manual intervention)
            $this->state = self::STATE_LOCKED;
            $this->save_state();

            return false;
        }
    }

    /**
     * Stop all motors immediately
     *
     * @return bool Success status
     */
    private function stop_all_motors() {
        try {
            // Get motor controller instance
            $motor_controller = apply_filters('aros_get_motor_controller', null);

            if ($motor_controller && method_exists($motor_controller, 'emergency_stop_all')) {
                return $motor_controller->emergency_stop_all();
            }

            // Fallback: Try to stop via action
            do_action('aros_stop_all_motors');

            return true;

        } catch (\Exception $e) {
            $this->log_event('Error stopping motors', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Disable all actuators
     *
     * @return bool Success status
     */
    private function disable_all_actuators() {
        try {
            do_action('aros_disable_all_actuators');
            return true;
        } catch (\Exception $e) {
            $this->log_event('Error disabling actuators', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Engage hardware interlock via GPIO
     *
     * @return bool Success status
     */
    private function engage_hardware_interlock() {
        if (!$this->gpio_pin) {
            return false;
        }

        try {
            // Write to GPIO pin to engage physical interlock
            // This would interface with actual GPIO hardware
            // For now, we'll use a file-based simulation

            $gpio_file = "/sys/class/gpio/gpio{$this->gpio_pin}/value";

            if (file_exists($gpio_file) && is_writable($gpio_file)) {
                file_put_contents($gpio_file, '1');
                $this->hardware_interlock = true;
                return true;
            }

            // Alternative: Use WiringPi if available
            if (function_exists('exec')) {
                exec("gpio write {$this->gpio_pin} 1", $output, $return_var);
                if ($return_var === 0) {
                    $this->hardware_interlock = true;
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            $this->log_event('Error engaging hardware interlock', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Engage brakes on all joints
     *
     * @return bool Success status
     */
    private function engage_all_brakes() {
        try {
            do_action('aros_engage_all_brakes');
            return true;
        } catch (\Exception $e) {
            $this->log_event('Error engaging brakes', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Cut power to motion systems (keep sensors and communication active)
     *
     * @return bool Success status
     */
    private function cut_power_to_motion_systems() {
        try {
            do_action('aros_cut_motion_power');
            return true;
        } catch (\Exception $e) {
            $this->log_event('Error cutting power', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Emergency GPIO stop (last resort)
     *
     * @return bool Success status
     */
    private function emergency_gpio_stop() {
        if (!$this->gpio_pin) {
            return false;
        }

        try {
            $gpio_file = "/sys/class/gpio/gpio{$this->gpio_pin}/value";
            if (file_exists($gpio_file)) {
                file_put_contents($gpio_file, '1');
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Reset emergency stop (requires authorization)
     *
     * @param string $authorization Authorization code
     * @param string $operator Operator name/ID
     * @return bool|WP_Error True if reset successful, WP_Error otherwise
     */
    public function reset($authorization, $operator) {
        // Verify current state allows reset
        if ($this->state === self::STATE_LOCKED) {
            return new \WP_Error(
                'estop_locked',
                'Emergency stop is locked. Manual intervention required.'
            );
        }

        if ($this->state !== self::STATE_STOPPED) {
            return new \WP_Error(
                'estop_invalid_state',
                'Emergency stop is not in stopped state.'
            );
        }

        // Verify authorization
        $valid_auth = apply_filters('aros_verify_estop_authorization', false, $authorization, $operator);

        if (!$valid_auth) {
            $this->log_event('Unauthorized emergency stop reset attempt', [
                'operator' => $operator,
            ]);

            return new \WP_Error(
                'estop_unauthorized',
                'Invalid authorization for emergency stop reset.'
            );
        }

        // Perform safety checks before reset
        $safety_check = $this->perform_safety_checks();

        if (is_wp_error($safety_check)) {
            $this->log_event('Safety check failed during reset', [
                'error' => $safety_check->get_error_message(),
            ]);
            return $safety_check;
        }

        // Release hardware interlock
        if ($this->hardware_interlock) {
            $this->release_hardware_interlock();
        }

        // Update state
        $this->state = self::STATE_NORMAL;
        $this->save_state();

        // Log reset
        $this->log_event('Emergency stop reset', [
            'operator' => $operator,
            'timestamp' => current_time('mysql'),
        ]);

        // Fire action
        do_action('aros_emergency_stop_reset', $operator);

        return true;
    }

    /**
     * Release hardware interlock
     *
     * @return bool Success status
     */
    private function release_hardware_interlock() {
        if (!$this->gpio_pin) {
            return false;
        }

        try {
            $gpio_file = "/sys/class/gpio/gpio{$this->gpio_pin}/value";

            if (file_exists($gpio_file) && is_writable($gpio_file)) {
                file_put_contents($gpio_file, '0');
                $this->hardware_interlock = false;
                return true;
            }

            return false;

        } catch (\Exception $e) {
            $this->log_event('Error releasing hardware interlock', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Perform safety checks before allowing reset
     *
     * @return bool|WP_Error True if safe, WP_Error otherwise
     */
    private function perform_safety_checks() {
        $checks = [];

        // Check for obstacles in immediate vicinity
        $collision_detector = apply_filters('aros_get_collision_detector', null);
        if ($collision_detector) {
            $obstacles = $collision_detector->scan_immediate_area();
            if (!empty($obstacles)) {
                return new \WP_Error(
                    'estop_obstacles_detected',
                    'Obstacles detected in immediate area. Clear area before reset.'
                );
            }
            $checks['obstacles'] = 'clear';
        }

        // Check system health
        $health_monitor = apply_filters('aros_get_health_monitor', null);
        if ($health_monitor) {
            $health = $health_monitor->check_health();
            if ($health['status'] !== 'healthy') {
                return new \WP_Error(
                    'estop_unhealthy',
                    'System health check failed: ' . json_encode($health)
                );
            }
            $checks['health'] = 'healthy';
        }

        // Check all motors are at zero velocity
        $motor_controller = apply_filters('aros_get_motor_controller', null);
        if ($motor_controller) {
            $motor_status = $motor_controller->get_all_motor_status();
            foreach ($motor_status as $motor => $status) {
                if (abs($status['velocity']) > 0.01) {
                    return new \WP_Error(
                        'estop_motors_moving',
                        "Motor {$motor} still showing movement. Wait for complete stop."
                    );
                }
            }
            $checks['motors'] = 'stopped';
        }

        // Log successful checks
        $this->log_event('Safety checks passed', $checks);

        return true;
    }

    /**
     * Get current emergency stop state
     *
     * @return string Current state
     */
    public function get_state() {
        return $this->state;
    }

    /**
     * Check if system is stopped
     *
     * @return bool True if stopped
     */
    public function is_stopped() {
        return in_array($this->state, [self::STATE_STOPPING, self::STATE_STOPPED, self::STATE_LOCKED]);
    }

    /**
     * Get emergency stop history
     *
     * @param int $limit Number of records to return
     * @return array History records
     */
    public function get_history($limit = 10) {
        return array_slice($this->history, 0, $limit);
    }

    /**
     * Load state from database
     */
    private function load_state() {
        $saved_state = get_option('aros_emergency_stop_state', [
            'state' => self::STATE_NORMAL,
            'history' => [],
        ]);

        $this->state = $saved_state['state'];
        $this->history = $saved_state['history'] ?? [];
    }

    /**
     * Save state to database
     */
    private function save_state() {
        update_option('aros_emergency_stop_state', [
            'state' => $this->state,
            'history' => $this->history,
            'last_update' => current_time('mysql'),
        ], false);
    }

    /**
     * Log emergency stop event
     *
     * @param string $message Event message
     * @param array $data Additional data
     */
    private function log_event($message, $data = []) {
        $event = [
            'timestamp' => microtime(true),
            'datetime' => current_time('mysql'),
            'message' => $message,
            'data' => $data,
            'state' => $this->state,
        ];

        // Add to history
        array_unshift($this->history, $event);

        // Keep only last 100 events in memory
        $this->history = array_slice($this->history, 0, 100);

        // Log to WordPress
        error_log(sprintf(
            '[AROS Emergency Stop] %s - %s',
            $message,
            json_encode($data)
        ));

        // Fire action for external logging
        do_action('aros_emergency_stop_event', $event);
    }

    /**
     * Shutdown handler for fail-safe behavior
     */
    public function shutdown_handler() {
        $error = error_get_last();

        // If there was a fatal error and we're not already stopped, trigger emergency stop
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            if (!$this->is_stopped()) {
                $this->trigger(
                    'Fatal error detected: ' . $error['message'],
                    self::TRIGGER_SOFTWARE,
                    ['error' => $error]
                );
            }
        }
    }
}
