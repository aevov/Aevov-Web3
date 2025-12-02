<?php
/**
 * AROS Ultrasonic Sensor Processor
 *
 * Real ultrasonic sensor processing implementation with:
 * - Distance measurement and filtering
 * - Multi-sensor fusion
 * - Obstacle detection
 * - Range validation
 * - Kalman filtering for noise reduction
 * - Dead zone handling
 * - Temperature compensation
 */

namespace AROS\Perception;

class UltrasonicProcessor {
    private $sensors = [];              // Array of ultrasonic sensors
    private $max_range = 4.0;           // Maximum reliable range (meters)
    private $min_range = 0.02;          // Minimum range (meters)
    private $speed_of_sound = 343.0;    // m/s at 20°C
    private $temperature = 20.0;        // Celsius

    // Kalman filter state for each sensor
    private $kalman_states = [];

    // Moving average filter
    private $window_size = 5;
    private $history = [];

    public function __construct($config = []) {
        $this->max_range = $config['max_range'] ?? 4.0;
        $this->min_range = $config['min_range'] ?? 0.02;
        $this->window_size = $config['window_size'] ?? 5;
        $this->temperature = $config['temperature'] ?? 20.0;

        // Update speed of sound based on temperature
        $this->update_speed_of_sound();

        // Initialize sensors
        $num_sensors = $config['num_sensors'] ?? 4;
        for ($i = 0; $i < $num_sensors; $i++) {
            $this->sensors[$i] = [
                'id' => $i,
                'position' => $config['positions'][$i] ?? ['x' => 0, 'y' => 0, 'z' => 0],
                'orientation' => $config['orientations'][$i] ?? 0.0,
                'distance' => $this->max_range,
                'valid' => false
            ];

            // Initialize Kalman filter for this sensor
            $this->kalman_states[$i] = [
                'x' => $this->max_range,     // State estimate
                'P' => 1.0,                  // Estimate covariance
                'Q' => 0.01,                 // Process noise
                'R' => 0.1,                  // Measurement noise
                'K' => 0.0                   // Kalman gain
            ];

            $this->history[$i] = [];
        }
    }

    /**
     * Main processing function for ultrasonic sensors
     * Input: Raw sensor readings (time-of-flight or distance)
     * Output: Filtered distances and obstacle detection
     */
    public function process($sensor_data) {
        $obstacles = [];
        $readings = [];

        foreach ($this->sensors as $id => $sensor) {
            // Get raw measurement
            $raw_distance = $this->get_distance_from_data($sensor_data, $id);

            // Validate range
            $is_valid = $this->validate_range($raw_distance);

            // Apply filters
            if ($is_valid) {
                // Kalman filter
                $filtered_distance = $this->kalman_filter($id, $raw_distance);

                // Moving average filter
                $smoothed_distance = $this->moving_average_filter($id, $filtered_distance);

                $this->sensors[$id]['distance'] = $smoothed_distance;
                $this->sensors[$id]['valid'] = true;
            } else {
                $this->sensors[$id]['valid'] = false;
                $smoothed_distance = $this->max_range;
            }

            $readings[] = [
                'sensor_id' => $id,
                'raw_distance' => $raw_distance,
                'filtered_distance' => $smoothed_distance,
                'valid' => $is_valid,
                'position' => $sensor['position'],
                'orientation' => $sensor['orientation']
            ];

            // Detect obstacles
            if ($is_valid && $smoothed_distance < $this->max_range * 0.8) {
                $obstacle = $this->calculate_obstacle_position($sensor, $smoothed_distance);
                $obstacles[] = $obstacle;
            }
        }

        // Merge nearby obstacles
        $merged_obstacles = $this->merge_obstacles($obstacles);

        return [
            'readings' => $readings,
            'obstacles' => $merged_obstacles,
            'closest_obstacle' => $this->find_closest_obstacle($merged_obstacles),
            'summary' => [
                'num_valid_readings' => count(array_filter($readings, fn($r) => $r['valid'])),
                'num_obstacles' => count($merged_obstacles),
                'min_distance' => $this->get_minimum_distance()
            ]
        ];
    }

    /**
     * Extract distance from sensor data
     */
    private function get_distance_from_data($sensor_data, $sensor_id) {
        // Check if data contains distance directly
        if (isset($sensor_data['distances'][$sensor_id])) {
            return $sensor_data['distances'][$sensor_id];
        }

        // Check if data contains time-of-flight
        if (isset($sensor_data['tof'][$sensor_id])) {
            $tof = $sensor_data['tof'][$sensor_id]; // Time in microseconds
            return $this->time_of_flight_to_distance($tof);
        }

        // Return max range if no data
        return $this->max_range;
    }

    /**
     * Convert time-of-flight to distance
     */
    private function time_of_flight_to_distance($tof_microseconds) {
        // Distance = (speed_of_sound * time) / 2
        // Divide by 2 because sound travels to object and back
        $tof_seconds = $tof_microseconds / 1000000.0;
        return ($this->speed_of_sound * $tof_seconds) / 2.0;
    }

    /**
     * Validate if distance reading is within acceptable range
     */
    private function validate_range($distance) {
        if ($distance < $this->min_range || $distance > $this->max_range) {
            return false;
        }

        // Check for common error values
        if ($distance == 0.0 || is_nan($distance) || is_infinite($distance)) {
            return false;
        }

        return true;
    }

    /**
     * Kalman filter for noise reduction
     */
    private function kalman_filter($sensor_id, $measurement) {
        $state = &$this->kalman_states[$sensor_id];

        // Prediction step
        // x_pred = x
        $x_pred = $state['x'];
        $P_pred = $state['P'] + $state['Q'];

        // Update step
        $state['K'] = $P_pred / ($P_pred + $state['R']);
        $state['x'] = $x_pred + $state['K'] * ($measurement - $x_pred);
        $state['P'] = (1 - $state['K']) * $P_pred;

        return $state['x'];
    }

    /**
     * Moving average filter for smoothing
     */
    private function moving_average_filter($sensor_id, $value) {
        // Add to history
        $this->history[$sensor_id][] = $value;

        // Keep only recent values
        if (count($this->history[$sensor_id]) > $this->window_size) {
            array_shift($this->history[$sensor_id]);
        }

        // Calculate average
        $sum = array_sum($this->history[$sensor_id]);
        $count = count($this->history[$sensor_id]);

        return $count > 0 ? ($sum / $count) : $value;
    }

    /**
     * Calculate obstacle position in world coordinates
     */
    private function calculate_obstacle_position($sensor, $distance) {
        $sensor_pos = $sensor['position'];
        $orientation = $sensor['orientation'];

        // Calculate obstacle position relative to sensor
        $obstacle_x = $sensor_pos['x'] + $distance * cos($orientation);
        $obstacle_y = $sensor_pos['y'] + $distance * sin($orientation);
        $obstacle_z = $sensor_pos['z'];

        return [
            'position' => [
                'x' => $obstacle_x,
                'y' => $obstacle_y,
                'z' => $obstacle_z
            ],
            'distance' => $distance,
            'sensor_id' => $sensor['id'],
            'confidence' => $this->calculate_confidence($distance)
        ];
    }

    /**
     * Calculate measurement confidence based on distance
     */
    private function calculate_confidence($distance) {
        // Confidence decreases with distance
        // Maximum confidence at optimal range (0.5-2.0m)
        if ($distance < 0.5) {
            return 0.5 + ($distance / 0.5) * 0.5;
        } elseif ($distance < 2.0) {
            return 1.0;
        } else {
            $remaining = $this->max_range - $distance;
            return max(0.3, $remaining / ($this->max_range - 2.0));
        }
    }

    /**
     * Merge nearby obstacles from different sensors
     */
    private function merge_obstacles($obstacles, $merge_distance = 0.3) {
        if (empty($obstacles)) {
            return [];
        }

        $merged = [];
        $used = [];

        foreach ($obstacles as $i => $obs1) {
            if (isset($used[$i])) continue;

            $cluster = [$obs1];
            $used[$i] = true;

            foreach ($obstacles as $j => $obs2) {
                if ($i === $j || isset($used[$j])) continue;

                $dist = sqrt(
                    pow($obs1['position']['x'] - $obs2['position']['x'], 2) +
                    pow($obs1['position']['y'] - $obs2['position']['y'], 2) +
                    pow($obs1['position']['z'] - $obs2['position']['z'], 2)
                );

                if ($dist < $merge_distance) {
                    $cluster[] = $obs2;
                    $used[$j] = true;
                }
            }

            // Average positions in cluster
            $avg_x = 0;
            $avg_y = 0;
            $avg_z = 0;
            $avg_confidence = 0;
            $min_distance = INF;

            foreach ($cluster as $obs) {
                $avg_x += $obs['position']['x'];
                $avg_y += $obs['position']['y'];
                $avg_z += $obs['position']['z'];
                $avg_confidence += $obs['confidence'];
                $min_distance = min($min_distance, $obs['distance']);
            }

            $count = count($cluster);

            $merged[] = [
                'position' => [
                    'x' => $avg_x / $count,
                    'y' => $avg_y / $count,
                    'z' => $avg_z / $count
                ],
                'distance' => $min_distance,
                'confidence' => $avg_confidence / $count,
                'sensor_count' => $count
            ];
        }

        return $merged;
    }

    /**
     * Find closest obstacle
     */
    private function find_closest_obstacle($obstacles) {
        if (empty($obstacles)) {
            return null;
        }

        $closest = null;
        $min_distance = INF;

        foreach ($obstacles as $obstacle) {
            if ($obstacle['distance'] < $min_distance) {
                $min_distance = $obstacle['distance'];
                $closest = $obstacle;
            }
        }

        return $closest;
    }

    /**
     * Get minimum distance across all sensors
     */
    private function get_minimum_distance() {
        $min = $this->max_range;

        foreach ($this->sensors as $sensor) {
            if ($sensor['valid'] && $sensor['distance'] < $min) {
                $min = $sensor['distance'];
            }
        }

        return $min;
    }

    /**
     * Update speed of sound based on temperature
     * v = 331.3 + 0.606 * T (where T is in Celsius)
     */
    private function update_speed_of_sound() {
        $this->speed_of_sound = 331.3 + (0.606 * $this->temperature);
    }

    /**
     * Set temperature for compensation
     */
    public function set_temperature($celsius) {
        $this->temperature = $celsius;
        $this->update_speed_of_sound();
    }

    /**
     * Check if path is clear in a given direction
     */
    public function is_path_clear($direction, $min_clearance = 0.5) {
        foreach ($this->sensors as $sensor) {
            if (!$sensor['valid']) continue;

            // Check if sensor is facing the requested direction
            $angle_diff = abs($sensor['orientation'] - $direction);

            if ($angle_diff < M_PI / 4) { // Within 45 degrees
                if ($sensor['distance'] < $min_clearance) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Generate simulated ultrasonic readings for testing
     */
    public function generate_simulated_data($scenario = 'clear') {
        $data = ['distances' => []];

        switch ($scenario) {
            case 'obstacle_front':
                $data['distances'] = [
                    0.5,  // Front sensor - obstacle detected
                    2.0,  // Right sensor - clear
                    2.0,  // Back sensor - clear
                    2.0   // Left sensor - clear
                ];
                break;

            case 'obstacle_right':
                $data['distances'] = [
                    2.0,  // Front sensor - clear
                    0.3,  // Right sensor - close obstacle
                    2.0,  // Back sensor - clear
                    2.0   // Left sensor - clear
                ];
                break;

            case 'surrounded':
                $data['distances'] = [
                    0.8,  // Front
                    0.6,  // Right
                    0.9,  // Back
                    0.7   // Left
                ];
                break;

            default: // clear
                $data['distances'] = array_fill(0, count($this->sensors), $this->max_range);
                break;
        }

        // Add noise to simulate real sensors
        foreach ($data['distances'] as &$dist) {
            $dist += ($this->gaussian_noise(0, 0.02));
            $dist = max($this->min_range, min($this->max_range, $dist));
        }

        return $data;
    }

    /**
     * Generate Gaussian noise
     */
    private function gaussian_noise($mean, $stddev) {
        static $has_spare = false;
        static $spare = 0.0;

        if ($has_spare) {
            $has_spare = false;
            return $mean + $stddev * $spare;
        }

        $has_spare = true;
        $u = mt_rand() / mt_getrandmax();
        $v = mt_rand() / mt_getrandmax();
        $u = max(1e-10, $u);

        $mag = $stddev * sqrt(-2.0 * log($u));
        $spare = $mag * sin(2.0 * M_PI * $v);

        return $mean + $mag * cos(2.0 * M_PI * $v);
    }

    /**
     * Reset all filters and history
     */
    public function reset() {
        foreach ($this->sensors as $id => $sensor) {
            $this->kalman_states[$id]['x'] = $this->max_range;
            $this->kalman_states[$id]['P'] = 1.0;
            $this->history[$id] = [];
        }
    }
}
