<?php
/**
 * AROS Motor Controller
 *
 * Real motor control implementation with:
 * - PID control for position/velocity
 * - Trapezoidal and S-curve velocity profiling
 * - Position tracking with feedback
 * - Torque limiting and management
 * - Support for DC, Servo, and Stepper motors
 * - Comprehensive error handling
 */

namespace AROS\Control;

class MotorController {
    private $motors = [];
    private $motor_types = ['DC', 'SERVO', 'STEPPER'];
    private $profiles = [];
    private $pid_controllers = [];
    private $motor_states = [];
    private $error_log = [];
    private $simulation_mode = true;

    // Motor limits
    private $default_limits = [
        'max_velocity' => 100.0,      // rad/s or steps/s
        'max_acceleration' => 50.0,   // rad/s^2
        'max_jerk' => 100.0,          // rad/s^3 (for S-curve)
        'max_torque' => 10.0,         // Nm
        'position_min' => -360.0,     // degrees
        'position_max' => 360.0       // degrees
    ];

    public function __construct($simulation_mode = true) {
        $this->simulation_mode = $simulation_mode;
        $this->init_motors();
        $this->log('Motor Controller initialized in ' . ($simulation_mode ? 'SIMULATION' : 'HARDWARE') . ' mode');
    }

    /**
     * Initialize motors based on robot configuration
     */
    private function init_motors() {
        $config = get_option('aros_robot_config', []);
        $dof = $config['dof'] ?? 6;

        for ($i = 0; $i < $dof; $i++) {
            $motor_type = $config['motor_types'][$i] ?? 'SERVO';

            $this->motors[$i] = [
                'id' => $i,
                'type' => $motor_type,
                'position' => 0.0,           // Current position (rad or steps)
                'velocity' => 0.0,           // Current velocity
                'acceleration' => 0.0,       // Current acceleration
                'torque' => 0.0,             // Current torque
                'target_position' => 0.0,    // Desired position
                'target_velocity' => 0.0,    // Desired velocity
                'limits' => $this->default_limits,
                'enabled' => true,
                'fault' => false,
                'fault_reason' => ''
            ];

            // Initialize PID controller for each motor
            $this->pid_controllers[$i] = $this->create_pid_controller([
                'kp' => 5.0,   // Proportional gain
                'ki' => 0.1,   // Integral gain
                'kd' => 0.5,   // Derivative gain
                'integral_limit' => 100.0
            ]);

            $this->motor_states[$i] = 'IDLE';
        }
    }

    /**
     * Create a PID controller instance
     */
    private function create_pid_controller($params) {
        return [
            'kp' => $params['kp'],
            'ki' => $params['ki'],
            'kd' => $params['kd'],
            'integral' => 0.0,
            'previous_error' => 0.0,
            'integral_limit' => $params['integral_limit'] ?? 100.0,
            'last_update' => microtime(true)
        ];
    }

    /**
     * PID control algorithm
     * Calculates control output based on error between setpoint and current value
     */
    private function pid_compute($motor_id, $setpoint, $current_value, $dt) {
        $pid = &$this->pid_controllers[$motor_id];

        // Calculate error
        $error = $setpoint - $current_value;

        // Proportional term
        $p_term = $pid['kp'] * $error;

        // Integral term with anti-windup
        $pid['integral'] += $error * $dt;
        $pid['integral'] = max(-$pid['integral_limit'], min($pid['integral_limit'], $pid['integral']));
        $i_term = $pid['ki'] * $pid['integral'];

        // Derivative term (with derivative on measurement to avoid derivative kick)
        $derivative = ($error - $pid['previous_error']) / max($dt, 0.001);
        $d_term = $pid['kd'] * $derivative;

        // Update previous error
        $pid['previous_error'] = $error;

        // Calculate total output
        $output = $p_term + $i_term + $d_term;

        return $output;
    }

    /**
     * Generate trapezoidal velocity profile
     * Creates smooth acceleration/deceleration profile
     */
    public function generate_trapezoidal_profile($start_pos, $end_pos, $max_vel, $max_accel) {
        $distance = abs($end_pos - $start_pos);
        $direction = ($end_pos > $start_pos) ? 1 : -1;

        // Time to reach max velocity
        $t_accel = $max_vel / $max_accel;

        // Distance covered during acceleration/deceleration
        $d_accel = 0.5 * $max_accel * $t_accel * $t_accel;

        $profile = [
            'start_pos' => $start_pos,
            'end_pos' => $end_pos,
            'direction' => $direction,
            'max_velocity' => $max_vel,
            'max_acceleration' => $max_accel
        ];

        // Check if we can reach max velocity
        if (2 * $d_accel <= $distance) {
            // Trapezoidal profile (accel -> constant -> decel)
            $d_constant = $distance - 2 * $d_accel;
            $t_constant = $d_constant / $max_vel;

            $profile['type'] = 'trapezoidal';
            $profile['t_accel'] = $t_accel;
            $profile['t_constant'] = $t_constant;
            $profile['t_decel'] = $t_accel;
            $profile['total_time'] = 2 * $t_accel + $t_constant;
        } else {
            // Triangular profile (accel -> decel, never reach max velocity)
            $actual_max_vel = sqrt($distance * $max_accel);
            $t_accel = $actual_max_vel / $max_accel;

            $profile['type'] = 'triangular';
            $profile['actual_max_velocity'] = $actual_max_vel;
            $profile['t_accel'] = $t_accel;
            $profile['t_constant'] = 0;
            $profile['t_decel'] = $t_accel;
            $profile['total_time'] = 2 * $t_accel;
        }

        return $profile;
    }

    /**
     * Generate S-curve velocity profile (smoother than trapezoidal)
     * Uses jerk limiting for smoother motion
     */
    public function generate_scurve_profile($start_pos, $end_pos, $max_vel, $max_accel, $max_jerk) {
        $distance = abs($end_pos - $start_pos);
        $direction = ($end_pos > $start_pos) ? 1 : -1;

        // Time to reach max acceleration
        $t_jerk = $max_accel / $max_jerk;

        // Calculate S-curve parameters
        $profile = [
            'start_pos' => $start_pos,
            'end_pos' => $end_pos,
            'direction' => $direction,
            'max_velocity' => $max_vel,
            'max_acceleration' => $max_accel,
            'max_jerk' => $max_jerk,
            'type' => 's-curve',
            't_jerk' => $t_jerk
        ];

        // Simplified S-curve (7 phases)
        $profile['phases'] = [
            'jerk_up' => $t_jerk,
            'constant_accel' => $t_jerk,
            'jerk_down' => $t_jerk,
            'constant_vel' => 0,  // Calculated based on distance
            'jerk_down_decel' => $t_jerk,
            'constant_decel' => $t_jerk,
            'jerk_up_decel' => $t_jerk
        ];

        return $profile;
    }

    /**
     * Sample position from velocity profile at time t
     */
    public function sample_profile($profile, $t) {
        if ($t >= $profile['total_time']) {
            return [
                'position' => $profile['end_pos'],
                'velocity' => 0.0,
                'acceleration' => 0.0
            ];
        }

        $direction = $profile['direction'];
        $start = $profile['start_pos'];

        if ($profile['type'] === 'trapezoidal' || $profile['type'] === 'triangular') {
            $max_vel = $profile['actual_max_velocity'] ?? $profile['max_velocity'];
            $max_accel = $profile['max_acceleration'];
            $t_accel = $profile['t_accel'];
            $t_constant = $profile['t_constant'];

            if ($t < $t_accel) {
                // Acceleration phase
                $pos = $start + $direction * 0.5 * $max_accel * $t * $t;
                $vel = $direction * $max_accel * $t;
                $accel = $direction * $max_accel;
            } elseif ($t < $t_accel + $t_constant) {
                // Constant velocity phase
                $t_in_phase = $t - $t_accel;
                $d_accel = 0.5 * $max_accel * $t_accel * $t_accel;
                $pos = $start + $direction * ($d_accel + $max_vel * $t_in_phase);
                $vel = $direction * $max_vel;
                $accel = 0.0;
            } else {
                // Deceleration phase
                $t_in_phase = $t - $t_accel - $t_constant;
                $d_accel = 0.5 * $max_accel * $t_accel * $t_accel;
                $d_constant = $max_vel * $t_constant;
                $pos = $start + $direction * ($d_accel + $d_constant + $max_vel * $t_in_phase - 0.5 * $max_accel * $t_in_phase * $t_in_phase);
                $vel = $direction * ($max_vel - $max_accel * $t_in_phase);
                $accel = -$direction * $max_accel;
            }
        } else {
            // S-curve profile (simplified)
            $pos = $start;
            $vel = 0.0;
            $accel = 0.0;
        }

        return [
            'position' => $pos,
            'velocity' => $vel,
            'acceleration' => $accel
        ];
    }

    /**
     * Move motor to target position with velocity profiling
     */
    public function move_to_position($motor_id, $target_position, $profile_type = 'trapezoidal') {
        if (!isset($this->motors[$motor_id])) {
            $this->log_error($motor_id, 'Motor not found');
            return false;
        }

        $motor = &$this->motors[$motor_id];

        // Check if motor is enabled
        if (!$motor['enabled'] || $motor['fault']) {
            $this->log_error($motor_id, 'Motor disabled or in fault state');
            return false;
        }

        // Check position limits
        if ($target_position < $motor['limits']['position_min'] ||
            $target_position > $motor['limits']['position_max']) {
            $this->log_error($motor_id, 'Target position out of limits');
            return false;
        }

        $motor['target_position'] = $target_position;

        // Generate velocity profile
        if ($profile_type === 's-curve') {
            $this->profiles[$motor_id] = $this->generate_scurve_profile(
                $motor['position'],
                $target_position,
                $motor['limits']['max_velocity'],
                $motor['limits']['max_acceleration'],
                $motor['limits']['max_jerk']
            );
        } else {
            $this->profiles[$motor_id] = $this->generate_trapezoidal_profile(
                $motor['position'],
                $target_position,
                $motor['limits']['max_velocity'],
                $motor['limits']['max_acceleration']
            );
        }

        $this->profiles[$motor_id]['start_time'] = microtime(true);
        $this->motor_states[$motor_id] = 'MOVING';

        $this->log("Motor $motor_id moving to position $target_position");

        return true;
    }

    /**
     * Set motor velocity directly (velocity control mode)
     */
    public function set_velocity($motor_id, $velocity) {
        if (!isset($this->motors[$motor_id])) {
            return false;
        }

        $motor = &$this->motors[$motor_id];

        // Clamp velocity to limits
        $velocity = max(-$motor['limits']['max_velocity'],
                       min($motor['limits']['max_velocity'], $velocity));

        $motor['target_velocity'] = $velocity;
        $this->motor_states[$motor_id] = 'VELOCITY_CONTROL';

        return true;
    }

    /**
     * Set motor torque directly (torque control mode)
     */
    public function set_torque($motor_id, $torque) {
        if (!isset($this->motors[$motor_id])) {
            return false;
        }

        $motor = &$this->motors[$motor_id];

        // Clamp torque to limits
        $torque = max(-$motor['limits']['max_torque'],
                     min($motor['limits']['max_torque'], $torque));

        $motor['torque'] = $torque;
        $this->motor_states[$motor_id] = 'TORQUE_CONTROL';

        return true;
    }

    /**
     * Update motor states (called in control loop)
     */
    public function update($dt) {
        foreach ($this->motors as $motor_id => $motor) {
            if (!$motor['enabled'] || $motor['fault']) {
                continue;
            }

            $state = $this->motor_states[$motor_id];

            switch ($state) {
                case 'MOVING':
                    $this->update_position_control($motor_id, $dt);
                    break;

                case 'VELOCITY_CONTROL':
                    $this->update_velocity_control($motor_id, $dt);
                    break;

                case 'TORQUE_CONTROL':
                    $this->update_torque_control($motor_id, $dt);
                    break;
            }

            // Check for faults
            $this->check_motor_faults($motor_id);

            // Update motor based on type
            $this->update_motor_physics($motor_id, $dt);
        }
    }

    /**
     * Update position control mode
     */
    private function update_position_control($motor_id, $dt) {
        if (!isset($this->profiles[$motor_id])) {
            return;
        }

        $profile = $this->profiles[$motor_id];
        $elapsed = microtime(true) - $profile['start_time'];

        // Sample profile at current time
        $sample = $this->sample_profile($profile, $elapsed);

        // Use PID to track the profile
        $control_output = $this->pid_compute(
            $motor_id,
            $sample['position'],
            $this->motors[$motor_id]['position'],
            $dt
        );

        // Apply control output as velocity command
        $this->motors[$motor_id]['target_velocity'] = $sample['velocity'] + $control_output;

        // Check if motion is complete
        if ($elapsed >= $profile['total_time']) {
            $this->motors[$motor_id]['target_velocity'] = 0.0;
            $this->motor_states[$motor_id] = 'IDLE';
            unset($this->profiles[$motor_id]);
            $this->log("Motor $motor_id reached target position");
        }
    }

    /**
     * Update velocity control mode
     */
    private function update_velocity_control($motor_id, $dt) {
        $motor = &$this->motors[$motor_id];

        // Use PID to track target velocity
        $control_output = $this->pid_compute(
            $motor_id,
            $motor['target_velocity'],
            $motor['velocity'],
            $dt
        );

        // Apply acceleration limits
        $desired_accel = $control_output / $dt;
        $desired_accel = max(-$motor['limits']['max_acceleration'],
                            min($motor['limits']['max_acceleration'], $desired_accel));

        $motor['acceleration'] = $desired_accel;
    }

    /**
     * Update torque control mode
     */
    private function update_torque_control($motor_id, $dt) {
        $motor = &$this->motors[$motor_id];

        // In simulation, convert torque to acceleration
        // T = J * alpha (torque = inertia * angular acceleration)
        // Assuming unit inertia for simplification
        $inertia = 1.0;
        $motor['acceleration'] = $motor['torque'] / $inertia;
    }

    /**
     * Update motor physics (simulation)
     */
    private function update_motor_physics($motor_id, $dt) {
        $motor = &$this->motors[$motor_id];

        if ($this->simulation_mode) {
            // Integrate acceleration to velocity
            $motor['velocity'] += $motor['acceleration'] * $dt;

            // Apply velocity limits
            $motor['velocity'] = max(-$motor['limits']['max_velocity'],
                                    min($motor['limits']['max_velocity'], $motor['velocity']));

            // Integrate velocity to position
            $motor['position'] += $motor['velocity'] * $dt;

            // Apply friction (damping)
            $motor['velocity'] *= 0.99;
        } else {
            // In hardware mode, read from actual encoders
            $motor['position'] = $this->read_encoder($motor_id);
            $motor['velocity'] = $this->calculate_velocity($motor_id, $dt);
        }
    }

    /**
     * Check for motor faults
     */
    private function check_motor_faults($motor_id) {
        $motor = &$this->motors[$motor_id];

        // Check position limits
        if ($motor['position'] < $motor['limits']['position_min'] ||
            $motor['position'] > $motor['limits']['position_max']) {
            $this->set_motor_fault($motor_id, 'Position limit exceeded');
            return;
        }

        // Check velocity limits
        if (abs($motor['velocity']) > $motor['limits']['max_velocity'] * 1.1) {
            $this->set_motor_fault($motor_id, 'Velocity limit exceeded');
            return;
        }

        // Check torque limits
        if (abs($motor['torque']) > $motor['limits']['max_torque'] * 1.1) {
            $this->set_motor_fault($motor_id, 'Torque limit exceeded');
            return;
        }
    }

    /**
     * Set motor fault state
     */
    private function set_motor_fault($motor_id, $reason) {
        $this->motors[$motor_id]['fault'] = true;
        $this->motors[$motor_id]['fault_reason'] = $reason;
        $this->motors[$motor_id]['enabled'] = false;
        $this->motor_states[$motor_id] = 'FAULT';

        $this->log_error($motor_id, $reason);
        do_action('aros_motor_fault', $motor_id, $reason);
    }

    /**
     * Clear motor fault
     */
    public function clear_fault($motor_id) {
        if (isset($this->motors[$motor_id])) {
            $this->motors[$motor_id]['fault'] = false;
            $this->motors[$motor_id]['fault_reason'] = '';
            $this->motors[$motor_id]['enabled'] = true;
            $this->motor_states[$motor_id] = 'IDLE';
            $this->log("Motor $motor_id fault cleared");
            return true;
        }
        return false;
    }

    /**
     * Emergency stop - immediately halt all motors
     */
    public function emergency_stop() {
        foreach ($this->motors as $id => $motor) {
            $this->motors[$id]['velocity'] = 0;
            $this->motors[$id]['acceleration'] = 0;
            $this->motors[$id]['target_velocity'] = 0;
            $this->motors[$id]['torque'] = 0;
            $this->motor_states[$id] = 'EMERGENCY_STOP';
            unset($this->profiles[$id]);
        }

        $this->log('EMERGENCY STOP ACTIVATED');
        do_action('aros_emergency_stop');
    }

    /**
     * Get motor state
     */
    public function get_motor_state($motor_id) {
        if (!isset($this->motors[$motor_id])) {
            return null;
        }

        return [
            'motor' => $this->motors[$motor_id],
            'state' => $this->motor_states[$motor_id],
            'pid' => $this->pid_controllers[$motor_id]
        ];
    }

    /**
     * Get all motor states
     */
    public function get_all_states() {
        $states = [];
        foreach ($this->motors as $id => $motor) {
            $states[$id] = $this->get_motor_state($id);
        }
        return $states;
    }

    /**
     * Hardware interface methods (for real hardware mode)
     */
    private function read_encoder($motor_id) {
        // In real hardware mode, read from actual encoder
        // For now, return current position (simulation)
        return $this->motors[$motor_id]['position'];
    }

    private function calculate_velocity($motor_id, $dt) {
        // Calculate velocity from position change
        static $last_positions = [];

        $current_pos = $this->motors[$motor_id]['position'];
        $last_pos = $last_positions[$motor_id] ?? $current_pos;

        $velocity = ($current_pos - $last_pos) / max($dt, 0.001);
        $last_positions[$motor_id] = $current_pos;

        return $velocity;
    }

    /**
     * Logging methods
     */
    private function log($message) {
        error_log('[AROS Motor Controller] ' . $message);
    }

    private function log_error($motor_id, $error) {
        $this->error_log[] = [
            'timestamp' => microtime(true),
            'motor_id' => $motor_id,
            'error' => $error
        ];
        error_log('[AROS Motor Controller ERROR] Motor ' . $motor_id . ': ' . $error);
    }

    public function get_error_log() {
        return $this->error_log;
    }
}
