<?php
/**
 * Collision Detection System for AROS
 *
 * CRITICAL SAFETY SYSTEM - Detects potential collisions using multiple sensor modalities
 * Prevents robot from colliding with obstacles, people, or other robots
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
 * Class CollisionDetector
 *
 * Multi-modal collision detection using:
 * - LiDAR/depth sensors
 * - Computer vision
 * - Proximity sensors
 * - Force/torque sensors
 * - Predictive trajectory analysis
 */
class CollisionDetector {

    /**
     * Collision threat levels
     */
    const THREAT_NONE = 0;
    const THREAT_LOW = 1;
    const THREAT_MEDIUM = 2;
    const THREAT_HIGH = 3;
    const THREAT_CRITICAL = 4;

    /**
     * Detection zones (distance in meters)
     */
    const ZONE_IMMEDIATE = 0.3;    // 30cm - immediate danger
    const ZONE_CLOSE = 0.5;         // 50cm - close range
    const ZONE_NEAR = 1.0;          // 1m - near range
    const ZONE_FAR = 2.0;           // 2m - far range

    /**
     * @var array Sensor configuration
     */
    private $sensors = [];

    /**
     * @var array Detection history
     */
    private $detection_history = [];

    /**
     * @var float Minimum safe distance (meters)
     */
    private $min_safe_distance = 0.5;

    /**
     * @var float Detection frequency (Hz)
     */
    private $detection_frequency = 10.0;

    /**
     * @var bool Emergency stop interface
     */
    private $emergency_stop = null;

    /**
     * @var array Collision zones
     */
    private $collision_zones = [];

    /**
     * @var int Frame counter for performance monitoring
     */
    private $frame_count = 0;

    /**
     * Constructor
     *
     * @param array $config Configuration options
     */
    public function __construct($config = []) {
        $this->min_safe_distance = $config['min_safe_distance'] ?? 0.5;
        $this->detection_frequency = $config['detection_frequency'] ?? 10.0;

        // Initialize sensors
        $this->initialize_sensors($config['sensors'] ?? []);

        // Define collision zones
        $this->define_collision_zones();

        // Get emergency stop instance
        $this->emergency_stop = apply_filters('aros_get_emergency_stop', null);
    }

    /**
     * Initialize sensor array
     *
     * @param array $sensor_config Sensor configuration
     */
    private function initialize_sensors($sensor_config) {
        // LiDAR/depth sensors
        if (isset($sensor_config['lidar'])) {
            $this->sensors['lidar'] = [
                'type' => 'lidar',
                'enabled' => $sensor_config['lidar']['enabled'] ?? true,
                'range' => $sensor_config['lidar']['range'] ?? 10.0,
                'fov' => $sensor_config['lidar']['fov'] ?? 360,
                'resolution' => $sensor_config['lidar']['resolution'] ?? 0.5,
            ];
        }

        // Proximity sensors (ultrasonic/infrared)
        if (isset($sensor_config['proximity'])) {
            $this->sensors['proximity'] = [
                'type' => 'proximity',
                'enabled' => $sensor_config['proximity']['enabled'] ?? true,
                'count' => $sensor_config['proximity']['count'] ?? 8,
                'range' => $sensor_config['proximity']['range'] ?? 3.0,
            ];
        }

        // Camera/vision
        if (isset($sensor_config['vision'])) {
            $this->sensors['vision'] = [
                'type' => 'vision',
                'enabled' => $sensor_config['vision']['enabled'] ?? true,
                'depth' => $sensor_config['vision']['depth'] ?? true,
            ];
        }

        // Force/torque sensors
        if (isset($sensor_config['force'])) {
            $this->sensors['force'] = [
                'type' => 'force',
                'enabled' => $sensor_config['force']['enabled'] ?? true,
                'threshold' => $sensor_config['force']['threshold'] ?? 10.0,
            ];
        }
    }

    /**
     * Define collision detection zones around robot
     */
    private function define_collision_zones() {
        // Front zone (most critical)
        $this->collision_zones['front'] = [
            'angle_start' => -45,
            'angle_end' => 45,
            'priority' => 'critical',
        ];

        // Side zones
        $this->collision_zones['left'] = [
            'angle_start' => 45,
            'angle_end' => 135,
            'priority' => 'high',
        ];

        $this->collision_zones['right'] = [
            'angle_start' => -135,
            'angle_end' => -45,
            'priority' => 'high',
        ];

        // Rear zone
        $this->collision_zones['rear'] = [
            'angle_start' => 135,
            'angle_end' => 225,
            'priority' => 'medium',
        ];
    }

    /**
     * Check for potential collisions
     *
     * Main detection loop - should be called at detection_frequency Hz
     *
     * @param array $robot_state Current robot state (position, velocity, planned trajectory)
     * @param array $obstacles Known obstacles (optional)
     * @return array|bool Detection result or false if no collision detected
     */
    public function check($robot_state, $obstacles = []) {
        $this->frame_count++;
        $detection_start = microtime(true);

        try {
            // Sensor fusion: Gather data from all sensors
            $sensor_data = $this->gather_sensor_data();

            // Combine with known obstacles
            $all_obstacles = $this->merge_obstacles($sensor_data, $obstacles);

            // Analyze current state for immediate threats
            $immediate_threats = $this->detect_immediate_threats($robot_state, $all_obstacles);

            if (!empty($immediate_threats)) {
                // CRITICAL: Immediate collision threat
                $this->handle_immediate_threat($immediate_threats);
                return $immediate_threats;
            }

            // Predictive collision detection using planned trajectory
            if (isset($robot_state['planned_trajectory'])) {
                $predicted_collisions = $this->predict_collisions(
                    $robot_state['planned_trajectory'],
                    $all_obstacles
                );

                if (!empty($predicted_collisions)) {
                    return $predicted_collisions;
                }
            }

            // Check dynamic obstacles (moving objects/people)
            $dynamic_threats = $this->detect_dynamic_threats($robot_state, $all_obstacles);

            if (!empty($dynamic_threats)) {
                return $dynamic_threats;
            }

            // Performance monitoring
            $detection_time = (microtime(true) - $detection_start) * 1000;
            if ($detection_time > 100) { // 100ms threshold
                error_log("[AROS Collision Detector] Slow detection: {$detection_time}ms");
            }

            return false; // No collision detected

        } catch (\Exception $e) {
            error_log('[AROS Collision Detector] Exception: ' . $e->getMessage());

            // FAIL-SAFE: On error, assume potential collision
            return [
                'threat_level' => self::THREAT_HIGH,
                'reason' => 'Detection system error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Gather data from all enabled sensors
     *
     * @return array Sensor data from all sources
     */
    private function gather_sensor_data() {
        $data = [];

        foreach ($this->sensors as $sensor_name => $sensor_config) {
            if (!$sensor_config['enabled']) {
                continue;
            }

            try {
                switch ($sensor_config['type']) {
                    case 'lidar':
                        $data['lidar'] = $this->read_lidar_data($sensor_config);
                        break;

                    case 'proximity':
                        $data['proximity'] = $this->read_proximity_sensors($sensor_config);
                        break;

                    case 'vision':
                        $data['vision'] = $this->read_vision_data($sensor_config);
                        break;

                    case 'force':
                        $data['force'] = $this->read_force_sensors($sensor_config);
                        break;
                }
            } catch (\Exception $e) {
                error_log("[AROS Collision Detector] Error reading {$sensor_name}: " . $e->getMessage());
            }
        }

        return $data;
    }

    /**
     * Read LiDAR sensor data
     *
     * @param array $config Sensor configuration
     * @return array LiDAR point cloud
     */
    private function read_lidar_data($config) {
        // Hook for actual LiDAR hardware interface
        $lidar_data = apply_filters('aros_read_lidar', [], $config);

        if (empty($lidar_data)) {
            // Fallback: Try to read from ROS topic or sensor driver
            $lidar_data = $this->read_ros_lidar_topic();
        }

        return $lidar_data;
    }

    /**
     * Read ROS LiDAR topic (if ROS is available)
     *
     * @return array LiDAR data
     */
    private function read_ros_lidar_topic() {
        // Check if ROS bridge is available
        $ros_bridge = apply_filters('aros_get_ros_bridge', null);

        if ($ros_bridge && method_exists($ros_bridge, 'read_topic')) {
            return $ros_bridge->read_topic('/scan');
        }

        return [];
    }

    /**
     * Read proximity sensors (ultrasonic/infrared)
     *
     * @param array $config Sensor configuration
     * @return array Proximity readings
     */
    private function read_proximity_sensors($config) {
        $readings = [];

        // Hook for actual proximity sensor hardware
        $sensor_count = $config['count'] ?? 8;

        for ($i = 0; $i < $sensor_count; $i++) {
            $angle = ($i * 360) / $sensor_count;

            $distance = apply_filters("aros_read_proximity_sensor_{$i}", null);

            if ($distance !== null) {
                $readings[] = [
                    'sensor_id' => $i,
                    'angle' => $angle,
                    'distance' => $distance,
                ];
            }
        }

        return $readings;
    }

    /**
     * Read vision/camera data with depth
     *
     * @param array $config Sensor configuration
     * @return array Vision data with depth information
     */
    private function read_vision_data($config) {
        // Get vision processor instance
        $vision_processor = apply_filters('aros_get_vision_processor', null);

        if (!$vision_processor) {
            return [];
        }

        // Request obstacle detection from vision system
        if (method_exists($vision_processor, 'detect_obstacles')) {
            return $vision_processor->detect_obstacles([
                'depth' => $config['depth'],
            ]);
        }

        return [];
    }

    /**
     * Read force/torque sensors for contact detection
     *
     * @param array $config Sensor configuration
     * @return array Force sensor readings
     */
    private function read_force_sensors($config) {
        $readings = [];

        // Hook for actual force sensors
        $force_data = apply_filters('aros_read_force_sensors', []);

        foreach ($force_data as $sensor => $value) {
            // Check if force exceeds threshold (potential collision)
            if (abs($value) > $config['threshold']) {
                $readings[] = [
                    'sensor' => $sensor,
                    'force' => $value,
                    'threshold' => $config['threshold'],
                    'exceeded' => true,
                ];
            }
        }

        return $readings;
    }

    /**
     * Merge sensor data with known obstacles
     *
     * @param array $sensor_data Data from sensors
     * @param array $known_obstacles Known obstacles from map
     * @return array Combined obstacle list
     */
    private function merge_obstacles($sensor_data, $known_obstacles) {
        $obstacles = [];

        // Process LiDAR data
        if (isset($sensor_data['lidar'])) {
            foreach ($sensor_data['lidar'] as $point) {
                if ($point['distance'] < self::ZONE_FAR) {
                    $obstacles[] = [
                        'source' => 'lidar',
                        'distance' => $point['distance'],
                        'angle' => $point['angle'] ?? 0,
                        'position' => $point['position'] ?? null,
                    ];
                }
            }
        }

        // Process proximity sensor data
        if (isset($sensor_data['proximity'])) {
            foreach ($sensor_data['proximity'] as $reading) {
                if ($reading['distance'] < self::ZONE_NEAR) {
                    $obstacles[] = [
                        'source' => 'proximity',
                        'distance' => $reading['distance'],
                        'angle' => $reading['angle'],
                        'sensor_id' => $reading['sensor_id'],
                    ];
                }
            }
        }

        // Process vision data
        if (isset($sensor_data['vision'])) {
            foreach ($sensor_data['vision'] as $object) {
                $obstacles[] = [
                    'source' => 'vision',
                    'distance' => $object['distance'] ?? null,
                    'position' => $object['position'] ?? null,
                    'type' => $object['type'] ?? 'unknown',
                    'moving' => $object['moving'] ?? false,
                ];
            }
        }

        // Add known obstacles
        foreach ($known_obstacles as $obstacle) {
            $obstacles[] = array_merge($obstacle, ['source' => 'map']);
        }

        return $obstacles;
    }

    /**
     * Detect immediate collision threats
     *
     * @param array $robot_state Current robot state
     * @param array $obstacles All detected obstacles
     * @return array Immediate threats or empty array
     */
    private function detect_immediate_threats($robot_state, $obstacles) {
        $threats = [];

        foreach ($obstacles as $obstacle) {
            $distance = $obstacle['distance'] ?? PHP_FLOAT_MAX;

            // Check if obstacle is in immediate danger zone
            if ($distance < self::ZONE_IMMEDIATE) {
                $threats[] = [
                    'threat_level' => self::THREAT_CRITICAL,
                    'distance' => $distance,
                    'obstacle' => $obstacle,
                    'action' => 'EMERGENCY_STOP',
                ];
            } elseif ($distance < self::ZONE_CLOSE) {
                // Check if robot is moving toward obstacle
                if ($this->is_moving_toward_obstacle($robot_state, $obstacle)) {
                    $threats[] = [
                        'threat_level' => self::THREAT_HIGH,
                        'distance' => $distance,
                        'obstacle' => $obstacle,
                        'action' => 'IMMEDIATE_STOP',
                    ];
                }
            }
        }

        return $threats;
    }

    /**
     * Check if robot is moving toward an obstacle
     *
     * @param array $robot_state Robot state with velocity
     * @param array $obstacle Obstacle data
     * @return bool True if moving toward obstacle
     */
    private function is_moving_toward_obstacle($robot_state, $obstacle) {
        if (!isset($robot_state['velocity']) || !isset($obstacle['angle'])) {
            return false;
        }

        $velocity = $robot_state['velocity'];
        $heading = $robot_state['heading'] ?? 0;

        // Simple check: if velocity > 0 and obstacle is in front
        if ($velocity > 0.01) {
            $angle_diff = abs($heading - $obstacle['angle']);
            return $angle_diff < 45; // Within 45° cone
        }

        return false;
    }

    /**
     * Predict future collisions based on planned trajectory
     *
     * @param array $trajectory Planned trajectory points
     * @param array $obstacles All obstacles
     * @return array Predicted collisions or empty array
     */
    private function predict_collisions($trajectory, $obstacles) {
        $predictions = [];

        foreach ($trajectory as $i => $waypoint) {
            foreach ($obstacles as $obstacle) {
                $distance = $this->calculate_distance($waypoint, $obstacle);

                if ($distance < $this->min_safe_distance) {
                    $predictions[] = [
                        'threat_level' => self::THREAT_MEDIUM,
                        'waypoint_index' => $i,
                        'time_to_collision' => $waypoint['time'] ?? null,
                        'distance' => $distance,
                        'obstacle' => $obstacle,
                        'action' => 'ADJUST_TRAJECTORY',
                    ];
                }
            }
        }

        return $predictions;
    }

    /**
     * Calculate distance between two points
     *
     * @param array $point1 First point [x, y, z]
     * @param array $point2 Second point or obstacle
     * @return float Distance in meters
     */
    private function calculate_distance($point1, $point2) {
        $pos1 = $point1['position'] ?? $point1;
        $pos2 = $point2['position'] ?? $point2;

        if (!isset($pos1['x']) || !isset($pos2['x'])) {
            return PHP_FLOAT_MAX;
        }

        $dx = $pos1['x'] - $pos2['x'];
        $dy = $pos1['y'] - $pos2['y'];
        $dz = ($pos1['z'] ?? 0) - ($pos2['z'] ?? 0);

        return sqrt($dx * $dx + $dy * $dy + $dz * $dz);
    }

    /**
     * Detect dynamic threats (moving obstacles)
     *
     * @param array $robot_state Robot state
     * @param array $obstacles All obstacles
     * @return array Dynamic threats or empty array
     */
    private function detect_dynamic_threats($robot_state, $obstacles) {
        $threats = [];

        foreach ($obstacles as $obstacle) {
            // Check if obstacle is marked as moving
            if (isset($obstacle['moving']) && $obstacle['moving']) {
                // Predict collision with moving obstacle
                $collision_time = $this->predict_dynamic_collision($robot_state, $obstacle);

                if ($collision_time !== false && $collision_time < 3.0) { // 3 seconds
                    $threat_level = $collision_time < 1.0 ? self::THREAT_HIGH : self::THREAT_MEDIUM;

                    $threats[] = [
                        'threat_level' => $threat_level,
                        'obstacle' => $obstacle,
                        'collision_time' => $collision_time,
                        'action' => $threat_level === self::THREAT_HIGH ? 'STOP' : 'AVOID',
                    ];
                }
            }
        }

        return $threats;
    }

    /**
     * Predict collision time with moving obstacle
     *
     * @param array $robot_state Robot state
     * @param array $obstacle Obstacle data with velocity
     * @return float|bool Time to collision or false
     */
    private function predict_dynamic_collision($robot_state, $obstacle) {
        // Simplified collision prediction
        // TODO: Implement full trajectory prediction

        if (!isset($obstacle['velocity']) || !isset($obstacle['position'])) {
            return false;
        }

        // Calculate relative velocity
        $robot_vel = $robot_state['velocity'] ?? 0;
        $obstacle_vel = $obstacle['velocity'];

        $relative_vel = $robot_vel - $obstacle_vel;

        if ($relative_vel <= 0) {
            return false; // Moving away or same speed
        }

        // Calculate distance
        $distance = $obstacle['distance'] ?? $this->calculate_distance(
            $robot_state['position'] ?? [],
            $obstacle['position']
        );

        // Time to collision = distance / relative_velocity
        return $distance / $relative_vel;
    }

    /**
     * Handle immediate collision threat
     *
     * @param array $threats Detected threats
     */
    private function handle_immediate_threat($threats) {
        // Find highest threat level
        $max_threat = max(array_column($threats, 'threat_level'));

        if ($max_threat === self::THREAT_CRITICAL) {
            // EMERGENCY STOP
            if ($this->emergency_stop) {
                $this->emergency_stop->trigger(
                    'Critical collision threat detected',
                    'collision',
                    ['threats' => $threats]
                );
            }
        }

        // Log threat
        error_log('[AROS Collision Detector] Immediate threat: ' . json_encode($threats));

        // Fire action
        do_action('aros_collision_threat', $threats, $max_threat);
    }

    /**
     * Scan immediate area for obstacles (used by emergency stop reset)
     *
     * @return array Obstacles in immediate area
     */
    public function scan_immediate_area() {
        $sensor_data = $this->gather_sensor_data();
        $obstacles = $this->merge_obstacles($sensor_data, []);

        // Filter to immediate zone only
        $immediate = array_filter($obstacles, function($obstacle) {
            return isset($obstacle['distance']) && $obstacle['distance'] < self::ZONE_IMMEDIATE;
        });

        return $immediate;
    }

    /**
     * Get detection statistics
     *
     * @return array Statistics
     */
    public function get_statistics() {
        return [
            'frame_count' => $this->frame_count,
            'sensors' => $this->sensors,
            'detection_frequency' => $this->detection_frequency,
            'min_safe_distance' => $this->min_safe_distance,
        ];
    }
}
